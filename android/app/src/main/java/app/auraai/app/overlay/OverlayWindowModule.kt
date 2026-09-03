package app.auraai.app.overlay

import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.graphics.Outline
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Build
import android.provider.Settings
import android.util.Log
import android.util.TypedValue
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.view.ViewOutlineProvider
import android.view.WindowManager
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import android.os.Handler
import android.os.Looper
import app.auraai.app.MainActivity
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.UiThreadUtil
import com.facebook.react.modules.core.DeviceEventManagerModule
import java.lang.ref.WeakReference
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.concurrent.Executors
import java.util.concurrent.ScheduledFuture
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicInteger
import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.max
import kotlin.math.roundToInt

/**
 * SYSTEM_ALERT_WINDOW overlay: circular EA logo + optional trade card below it.
 * Copy trades while backgrounded execute on the overlay without bringing MainActivity forward.
 */
class OverlayWindowModule(private val reactContext: ReactApplicationContext) :
  ReactContextBaseJavaModule(reactContext) {

  private var windowManager: WindowManager? = null
  private var overlayRoot: FrameLayout? = null
  private var overlayColumn: LinearLayout? = null
  private var layoutParams: WindowManager.LayoutParams? = null
  private var logoView: ImageView? = null
  private var tradePanel: LinearLayout? = null
  private var tradeSymbolView: TextView? = null
  private var tradeActionView: TextView? = null
  private var tradeStatusView: TextView? = null
  private var tradeLiveView: TextView? = null
  private var tradeSubheadView: TextView? = null
  private var tradePhaseViews: List<TextView> = emptyList()
  private var tradePhaseDots: List<View> = emptyList()
  private var tradePhaseConnectors: List<View> = emptyList()
  private var tradeHudAccentColor: Int = Color.parseColor(ACCENT_COLOR)

  private var lastLogoDiameterPx: Int = 350
  private var tradePanelVisible: Boolean = false

  private fun screenWidthPx(): Int =
    reactApplicationContext.resources.displayMetrics.widthPixels

  private var lastBotName: String = "NexTradeAI"
  private var lastPaused: Boolean = false
  private var lastBotImageUrl: String? = null

  private val imageLoadExecutor = Executors.newSingleThreadExecutor()
  private val logoLoadGeneration = AtomicInteger(0)

  private var bgPollScheduler: java.util.concurrent.ScheduledExecutorService? = null
  private var bgPollFuture: ScheduledFuture<*>? = null
  private var bgPollLicenseKey: String? = null
  private var bgPollApiBase: String? = null
  private var bgChartWarmupEnabled: Boolean = true
  private var tradeExecutor: OverlayTradeExecutor? = null
  private var overlayTradeConfig: OverlayTradeConfig? = null

  companion object {
    private const val TAG = "EaNativePoll"
    private const val PREFS = "ea_native_bg_poll"
    private const val KEY_LAST_POLL = "last_poll_iso"
    private const val KEY_EMPTY_COUNT = "empty_count"
    private const val KEY_PENDING_TYPE = "pending_type"
    private const val KEY_PENDING_JSON = "pending_payload"
    private const val KEY_LAST_WARMUP_AT = "last_chart_warmup_at_ms"
    private const val EMPTY_POLLS_BEFORE_WARMUP = 10
    private const val CHART_WARMUP_COOLDOWN_MS = 45L * 60L * 1000L
    private const val EVENT_EXECUTE_SIGNAL = "EaOverlayExecuteSignal"
    private const val EVENT_TRADE_COMPLETED = "EaOverlayTradeCompleted"
    private const val EVENT_TRADE_STARTED = "EaOverlayTradeStarted"
    private const val KEY_OVERLAY_HANDOFF = "overlay_handoff_at_ms"
    private const val KEY_TRADE_CONFIG = "overlay_trade_config_json"
    private const val KEY_PROCESSED_SIGNAL_IDS = "processed_signal_ids"
    private const val MAX_PROCESSED_SIGNAL_IDS = 500
    private const val TRADE_CARD_HORIZONTAL_PAD_DP = 16
    private const val TRADE_PANEL_GAP_DP = 12
    private const val TRADE_PANEL_HEIGHT_DP = 220
    private const val ACCENT_COLOR = "#00A8FF"

    @Volatile
    private var activeInstance: WeakReference<OverlayWindowModule>? = null

    @Volatile
    private var trackedOverlayRoot: FrameLayout? = null

    @Volatile
    private var trackedWindowManager: WindowManager? = null

    /** Remove draw-on-top UI when the app task dies or is swiped from recents. */
    @JvmStatic
    fun teardownGlobal(context: Context) {
      val module = activeInstance?.get()
      if (module != null) {
        UiThreadUtil.runOnUiThread {
          module.tearDownAllInternal(stopLifecycleService = true)
        }
        return
      }
      Handler(Looper.getMainLooper()).post {
        try {
          trackedOverlayRoot?.let { root ->
            trackedWindowManager?.removeView(root)
          }
        } catch (_: Exception) {
        }
        trackedOverlayRoot = null
        trackedWindowManager = null
        try {
          context.applicationContext.stopService(
            Intent(context.applicationContext, OverlayLifecycleService::class.java)
          )
        } catch (_: Exception) {
        }
        MainActivity.headlessTradeActive = false
      }
    }

    private fun trackOverlayViews(root: FrameLayout?, wm: WindowManager?) {
      trackedOverlayRoot = root
      trackedWindowManager = wm
    }
  }

  init {
    activeInstance = WeakReference(this)
  }

  override fun invalidate() {
    UiThreadUtil.runOnUiThread {
      tearDownAllInternal(stopLifecycleService = true)
      if (activeInstance?.get() === this@OverlayWindowModule) {
        activeInstance = null
      }
    }
    super.invalidate()
  }

  private fun startOverlayLifecycleService() {
    try {
      val intent = Intent(reactApplicationContext, OverlayLifecycleService::class.java)
      reactApplicationContext.startService(intent)
    } catch (e: Exception) {
      Log.w(TAG, "startOverlayLifecycleService", e)
    }
  }

  private fun stopOverlayLifecycleService() {
    try {
      reactApplicationContext.stopService(
        Intent(reactApplicationContext, OverlayLifecycleService::class.java)
      )
    } catch (e: Exception) {
      Log.w(TAG, "stopOverlayLifecycleService", e)
    }
  }

  private fun tearDownAllInternal(stopLifecycleService: Boolean) {
    logoLoadGeneration.incrementAndGet()
    try {
      tradeExecutor?.stop()
    } catch (_: Exception) {
    }
    tradeExecutor = null
    stopNativeBackgroundPollingInternal()
    bgPollLicenseKey = null
    bgPollApiBase = null
    tradePanelVisible = false
    MainActivity.headlessTradeActive = false

    try {
      overlayRoot?.let { v ->
        windowManager?.removeView(v)
      }
    } catch (_: Exception) {
    }

    overlayRoot = null
    logoView = null
    overlayColumn = null
    tradePanel = null
    tradeSymbolView = null
    tradeActionView = null
    tradeStatusView = null
    tradeLiveView = null
    tradeSubheadView = null
    tradePhaseViews = emptyList()
    tradePhaseDots = emptyList()
    tradePhaseConnectors = emptyList()
    layoutParams = null
    windowManager = null
    trackOverlayViews(null, null)

    if (stopLifecycleService) {
      stopOverlayLifecycleService()
    }
  }

  override fun getName(): String = "OverlayWindowModule"

  private fun dp(v: Int): Int =
    (v * reactApplicationContext.resources.displayMetrics.density).roundToInt()

  private fun canDrawOverlays(): Boolean =
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
      Settings.canDrawOverlays(reactApplicationContext)
    } else {
      true
    }

  @ReactMethod
  fun checkOverlayPermission(promise: Promise) {
    promise.resolve(canDrawOverlays())
  }

  @ReactMethod
  fun requestOverlayPermission(promise: Promise) {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
      promise.resolve(true)
      return
    }
    if (Settings.canDrawOverlays(reactApplicationContext)) {
      promise.resolve(true)
      return
    }
    val intent = Intent(
      Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
      Uri.parse("package:${reactApplicationContext.packageName}")
    ).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
    reactApplicationContext.startActivity(intent)
    promise.resolve(false)
  }

  @ReactMethod
  fun openAppNotificationSettings(promise: Promise) {
    try {
      val pkg = reactApplicationContext.packageName
      val intent =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
          Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).apply {
            putExtra(Settings.EXTRA_APP_PACKAGE, pkg)
          }
        } else {
          Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS).apply {
            data = Uri.parse("package:$pkg")
          }
        }
      intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
      reactApplicationContext.startActivity(intent)
      promise.resolve(true)
    } catch (e: Exception) {
      promise.reject("E_NOTIF_SETTINGS", e.message, e)
    }
  }

  private fun applyCircleClip(iv: ImageView, diameterPx: Int) {
    iv.scaleType = ImageView.ScaleType.CENTER_CROP
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
      iv.outlineProvider = object : ViewOutlineProvider() {
        override fun getOutline(view: View, outline: Outline) {
          val w = if (view.width > 0) view.width else diameterPx
          val h = if (view.height > 0) view.height else diameterPx
          outline.setOval(0, 0, w, h)
        }
      }
      iv.clipToOutline = true
      iv.elevation = 10f
    }
  }

  private fun applyPausedAlpha() {
    logoView?.alpha = if (lastPaused) 0.55f else 1f
  }

  private fun overlayWindowType(): Int =
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
      WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
    } else {
      @Suppress("DEPRECATION")
      WindowManager.LayoutParams.TYPE_PHONE
    }

  private fun applyOverlayWindowFlags(tradeVisible: Boolean) {
    val lp = layoutParams ?: return
    val wm = windowManager ?: return
    val root = overlayRoot ?: return
    lp.flags =
      if (tradeVisible) {
        WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS
      } else {
        WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
          WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS
      }
    try {
      wm.updateViewLayout(root, lp)
    } catch (_: Exception) {
    }
  }

  private fun refreshOverlayWindowSize() {
    val lp = layoutParams ?: return
    val wm = windowManager ?: return
    val root = overlayRoot ?: return
    val screenW = screenWidthPx()
    lp.width = screenW
    if (tradePanelVisible) {
      lp.x = 0
      lp.height = lastLogoDiameterPx + dp(TRADE_PANEL_GAP_DP) + dp(TRADE_PANEL_HEIGHT_DP)
    } else {
      lp.height = lastLogoDiameterPx
    }
    try {
      wm.updateViewLayout(root, lp)
    } catch (_: Exception) {
    }
  }

  private fun buildTradePanel(ctx: Context): LinearLayout {
    val hPad = dp(TRADE_CARD_HORIZONTAL_PAD_DP)
    val innerPad = dp(18)
    val outer =
      LinearLayout(ctx).apply {
        orientation = LinearLayout.VERTICAL
        layoutParams =
          LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            LinearLayout.LayoutParams.WRAP_CONTENT
          ).apply {
            leftMargin = hPad
            rightMargin = hPad
          }
      }

    val card = FrameLayout(ctx)
    val cardBg =
      GradientDrawable().apply {
        setColor(Color.parseColor("#06080E"))
        cornerRadius = dp(22).toFloat()
        setStroke(dp(1), Color.parseColor("#33FFFFFF"))
      }
    card.background = cardBg
    card.setPadding(innerPad, innerPad, innerPad, innerPad)

    val content = LinearLayout(ctx).apply { orientation = LinearLayout.VERTICAL }

    val topRow = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
    }

    val badgeRow = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
      layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
    }

    val action = TextView(ctx).apply {
      setTextColor(Color.WHITE)
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
      typeface = Typeface.DEFAULT_BOLD
      setPadding(dp(10), dp(6), dp(10), dp(6))
      background =
        GradientDrawable().apply {
          cornerRadius = dp(10).toFloat()
          setColor(Color.parseColor("#33FFFFFF"))
          setStroke(dp(1), Color.parseColor("#44FFFFFF"))
        }
    }
    tradeActionView = action
    badgeRow.addView(action)

    val liveRow = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
      setPadding(dp(10), dp(5), dp(10), dp(5))
      val liveBg =
        GradientDrawable().apply {
          cornerRadius = dp(10).toFloat()
          setColor(Color.parseColor("#14FFFFFF"))
        }
      background = liveBg
      layoutParams =
        LinearLayout.LayoutParams(
          LinearLayout.LayoutParams.WRAP_CONTENT,
          LinearLayout.LayoutParams.WRAP_CONTENT
        ).apply {
          leftMargin = dp(8)
        }
    }
    val liveDot = View(ctx).apply {
      layoutParams = LinearLayout.LayoutParams(dp(7), dp(7))
      background =
        GradientDrawable().apply {
          shape = GradientDrawable.OVAL
          setColor(Color.parseColor(ACCENT_COLOR))
        }
    }
    liveRow.addView(liveDot)
    val liveText = TextView(ctx).apply {
      text = "LIVE"
      setTextColor(Color.parseColor(ACCENT_COLOR))
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 10f)
      typeface = Typeface.DEFAULT_BOLD
      setPadding(dp(6), 0, 0, 0)
    }
    tradeLiveView = liveText
    liveRow.addView(liveText)
    badgeRow.addView(liveRow)
    topRow.addView(badgeRow)

    val closeBtn = TextView(ctx).apply {
      text = "✕"
      setTextColor(Color.parseColor("#CBD5E1"))
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 17f)
      gravity = Gravity.CENTER
      setPadding(dp(8), dp(4), dp(4), dp(4))
      isClickable = true
      setOnClickListener { hideTradeOverlayInternal() }
    }
    topRow.addView(closeBtn)
    content.addView(topRow)

    val mainRow = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
      setPadding(0, dp(14), 0, 0)
    }
    val copyBlock = LinearLayout(ctx).apply {
      orientation = LinearLayout.VERTICAL
      layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
    }
    val symbol = TextView(ctx).apply {
      setTextColor(Color.WHITE)
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 26f)
      typeface = Typeface.DEFAULT_BOLD
    }
    tradeSymbolView = symbol
    copyBlock.addView(symbol)
    val subhead = TextView(ctx).apply {
      setTextColor(Color.parseColor("#94A3B8"))
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 13f)
      setPadding(0, dp(2), 0, 0)
    }
    tradeSubheadView = subhead
    copyBlock.addView(subhead)
    mainRow.addView(copyBlock)

    val zapBtn = TextView(ctx).apply {
      text = "⚡"
      gravity = Gravity.CENTER
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 20f)
      setTextColor(Color.parseColor(ACCENT_COLOR))
      setPadding(dp(16), dp(14), dp(16), dp(14))
      background =
        GradientDrawable().apply {
          shape = GradientDrawable.OVAL
          setColor(Color.parseColor("#1AFFFFFF"))
          setStroke(dp(2), Color.parseColor("#5500A8FF"))
        }
    }
    mainRow.addView(zapBtn)
    content.addView(mainRow)

    val status = TextView(ctx).apply {
      setTextColor(Color.parseColor("#9AA7B5"))
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 13f)
      setPadding(0, dp(12), 0, dp(14))
      maxLines = 2
      setLineSpacing(0f, 1.15f)
    }
    tradeStatusView = status
    content.addView(status)

    val phases = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
    }
    val labels = listOf("Connect", "Terminal", "Execute")
    val phaseViews = mutableListOf<TextView>()
    val phaseDots = mutableListOf<View>()
    val phaseConnectors = mutableListOf<View>()
    labels.forEachIndexed { index, label ->
      val phaseCol = LinearLayout(ctx).apply {
        orientation = LinearLayout.VERTICAL
        gravity = Gravity.CENTER_HORIZONTAL
        layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
      }
      val dot = View(ctx).apply {
        layoutParams = LinearLayout.LayoutParams(dp(9), dp(9))
        background =
          GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(Color.parseColor("#1E293B"))
            setStroke(dp(1), Color.parseColor("#475569"))
          }
      }
      phaseDots.add(dot)
      phaseCol.addView(dot)
      val tv = TextView(ctx).apply {
        text = label
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 10f)
        setTextColor(Color.parseColor("#64748B"))
        gravity = Gravity.CENTER
        setPadding(0, dp(5), 0, 0)
      }
      phaseViews.add(tv)
      phaseCol.addView(tv)
      phases.addView(phaseCol)
      if (index < labels.lastIndex) {
        val line = View(ctx).apply {
          setBackgroundColor(Color.parseColor("#334155"))
          layoutParams = LinearLayout.LayoutParams(dp(20), dp(2))
        }
        phaseConnectors.add(line)
        phases.addView(line)
      }
    }
    tradePhaseViews = phaseViews
    tradePhaseDots = phaseDots
    tradePhaseConnectors = phaseConnectors
    content.addView(phases)

    card.addView(
      content,
      FrameLayout.LayoutParams(
        FrameLayout.LayoutParams.MATCH_PARENT,
        FrameLayout.LayoutParams.WRAP_CONTENT
      )
    )
    outer.addView(card)
    return outer
  }

  private fun hideTradeOverlayInternal() {
    tradePanel?.visibility = View.GONE
    tradePanelVisible = false
    applyOverlayWindowFlags(false)
    refreshOverlayWindowSize()
  }

  private fun inferTradePhase(status: String): Int {
    val s = status.lowercase(Locale.US)
    return when {
      Regex("order|placing|executing|confirm|trade|volume|completed|buy order|sell order").containsMatchIn(s) -> 2
      Regex("chart|terminal|login|signing|waiting|removing|linking|snapshot|analys|connect").containsMatchIn(s) -> 1
      else -> 0
    }
  }

  private fun applyTradePhase(phase: Int) {
    val accent = tradeHudAccentColor
    tradePhaseViews.forEachIndexed { index, tv ->
      val active = index <= phase
      val current = index == phase
      tv.setTextColor(if (active) Color.parseColor("#F8FAFC") else Color.parseColor("#64748B"))
      tv.typeface = if (active) Typeface.DEFAULT_BOLD else Typeface.DEFAULT
    }
    tradePhaseDots.forEachIndexed { index, dot ->
      val active = index <= phase
      val current = index == phase
      (dot.background as? GradientDrawable)?.let { bg ->
        bg.setColor(if (active) accent else Color.parseColor("#1E293B"))
        bg.setStroke(dp(if (current) 2 else 1), if (active) accent else Color.parseColor("#475569"))
      }
    }
    tradePhaseConnectors.forEachIndexed { index, line ->
      val active = index < phase
      line.setBackgroundColor(if (active) Color.argb(85, Color.red(accent), Color.green(accent), Color.blue(accent)) else Color.parseColor("#334155"))
    }
  }

  private fun applyTradeCardUi(symbol: String, action: String, status: String) {
    val sym = symbol.trim().ifEmpty { "MARKET" }.uppercase(Locale.US)
    val act = action.trim().uppercase(Locale.US)
    tradeSymbolView?.text = sym
    val actionColor =
      when (act) {
        "SELL" -> Color.parseColor("#FB7185")
        "BUY" -> Color.parseColor("#34D399")
        else -> Color.parseColor(ACCENT_COLOR)
      }
    tradeHudAccentColor = actionColor
    tradeActionView?.text = act
    (tradeActionView?.background as? GradientDrawable)?.let { badge ->
      badge.setColor(Color.argb(38, Color.red(actionColor), Color.green(actionColor), Color.blue(actionColor)))
      badge.setStroke(dp(1), Color.argb(112, Color.red(actionColor), Color.green(actionColor), Color.blue(actionColor)))
    }
    tradeActionView?.setTextColor(actionColor)
    tradeLiveView?.setTextColor(actionColor)
    (tradeLiveView?.parent as? ViewGroup)?.let { liveParent ->
      (liveParent.getChildAt(0)?.background as? GradientDrawable)?.setColor(actionColor)
    }
    tradeSubheadView?.text =
      when (act) {
        "SELL" -> "Sell order"
        "BUY" -> "Buy order"
        else -> "Copy signal"
      }
    tradeStatusView?.text = status.ifBlank { "Logging in — waiting to execute active signal…" }
    val phase = inferTradePhase(status)
    val showReady = status.equals("Ready", ignoreCase = true) ||
      status.contains("completed", ignoreCase = true)
    tradeLiveView?.text = if (showReady) "READY" else "LIVE"
    applyTradePhase(phase)
  }

  private fun showTradePanelInternal(symbol: String, action: String, status: String) {
    val panel = tradePanel ?: return
    applyTradeCardUi(symbol, action, status)
    panel.visibility = View.VISIBLE
    tradePanelVisible = true
    applyOverlayWindowFlags(true)
    refreshOverlayWindowSize()
  }

  @ReactMethod
  fun showTradeOverlay(symbol: String, action: String, status: String, promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        showTradePanelInternal(symbol, action, status)
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_TRADE_OVERLAY", e.message, e)
      }
    }
  }

  @ReactMethod
  fun updateTradeOverlayStatus(status: String, promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        if (tradePanelVisible) {
          tradeStatusView?.text = status.ifBlank { "Working…" }
          val showReady = status.equals("Ready", ignoreCase = true) ||
            status.contains("completed", ignoreCase = true)
          tradeLiveView?.text = if (showReady) "READY" else "LIVE"
          applyTradePhase(inferTradePhase(status))
        }
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_TRADE_STATUS", e.message, e)
      }
    }
  }

  @ReactMethod
  fun hideTradeOverlay(promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        hideTradeOverlayInternal()
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_TRADE_HIDE", e.message, e)
      }
    }
  }

  @ReactMethod
  fun isHeadlessTradeActive(promise: Promise) {
    promise.resolve(MainActivity.headlessTradeActive)
  }

  @ReactMethod
  fun startHeadlessTrade(promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        startHeadlessTradeActivity()
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_HEADLESS_START", e.message, e)
      }
    }
  }

  @ReactMethod
  fun finishHeadlessTrade(promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        val act = reactApplicationContext.currentActivity
        if (act is MainActivity) {
          act.exitHeadlessTrade()
        } else {
          MainActivity.headlessTradeActive = false
        }
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_HEADLESS_FINISH", e.message, e)
      }
    }
  }

  private fun startHeadlessTradeActivity() {
    try {
      MainActivity.headlessTradeActive = true
      Log.i(TAG, "Starting headless trade activity (transparent MainActivity)")
      val intent =
        Intent(reactContext, MainActivity::class.java).apply {
          putExtra(MainActivity.EXTRA_HEADLESS_TRADE, true)
          addFlags(
            Intent.FLAG_ACTIVITY_NEW_TASK or
              Intent.FLAG_ACTIVITY_SINGLE_TOP or
              Intent.FLAG_ACTIVITY_NO_ANIMATION or
              Intent.FLAG_ACTIVITY_REORDER_TO_FRONT
          )
        }
      reactContext.startActivity(intent)
    } catch (e: Exception) {
      Log.e(TAG, "startHeadlessTradeActivity", e)
    }
  }

  private fun isSignalProcessed(signalId: String): Boolean {
    if (signalId.isBlank()) return false
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    val stored = prefs.getStringSet(KEY_PROCESSED_SIGNAL_IDS, null) ?: return false
    return stored.contains(signalId)
  }

  private fun markSignalProcessedNative(signalId: String) {
    if (signalId.isBlank()) return
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    val existing =
      HashSet(prefs.getStringSet(KEY_PROCESSED_SIGNAL_IDS, emptySet()) ?: emptySet())
    existing.add(signalId)
    val trimmed =
      if (existing.size > MAX_PROCESSED_SIGNAL_IDS) {
        existing.toList().takeLast(MAX_PROCESSED_SIGNAL_IDS).toSet()
      } else {
        existing
      }
    prefs.edit().putStringSet(KEY_PROCESSED_SIGNAL_IDS, trimmed).apply()
    Log.i(TAG, "Marked signal processed: $signalId")
  }

  private fun filterUnprocessedSignals(arr: JSONArray): JSONArray {
    val out = JSONArray()
    for (i in 0 until arr.length()) {
      val row = arr.getJSONObject(i)
      val id = row.optString("id", "").trim()
      if (id.isEmpty() || !isSignalProcessed(id)) {
        out.put(row)
      }
    }
    return out
  }

  private fun handleBackgroundSignalFound(payload: String) {
    UiThreadUtil.runOnUiThread {
      try {
        val arr = JSONArray(payload)
        if (arr.length() == 0) return@runOnUiThread
        val row = arr.getJSONObject(0)
        val signalId = row.optString("id", "").trim()
        if (signalId.isNotEmpty() && isSignalProcessed(signalId)) {
          Log.i(TAG, "Background signal $signalId already executed — skipping")
          return@runOnUiThread
        }
        val symbol = row.optString("asset", "")
        val action = row.optString("action", "")
        showTradePanelInternal(
          symbol,
          action,
          "Logging in — waiting to execute active signal…"
        )
        startOverlayTradeExecution(row)
      } catch (e: Exception) {
        Log.e(TAG, "handleBackgroundSignalFound", e)
      }
    }
  }

  private fun loadOverlayTradeConfig(): OverlayTradeConfig? {
    overlayTradeConfig?.let { return it }
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    val raw = prefs.getString(KEY_TRADE_CONFIG, null) ?: return null
    return try {
      val json = JSONObject(raw)
      OverlayTradeConfig(
        mt5Login = json.optString("mt5Login", ""),
        mt5Password = json.optString("mt5Password", ""),
        mt5Server = json.optString("mt5Server", ""),
        terminalUrl = json.optString("terminalUrl", ""),
        brokerKey = json.optString("brokerKey", ""),
        proxyBaseUrl = json.optString("proxyBaseUrl", ""),
        robotName = json.optString("robotName", "NexTradeAI"),
        volume = json.optString("volume", "0.01"),
        numberOfTrades = json.optString("numberOfTrades", "1"),
        symbolMapJson = json.optString("symbolMapJson", "{}"),
      ).also { overlayTradeConfig = it }
    } catch (e: Exception) {
      Log.e(TAG, "loadOverlayTradeConfig", e)
      null
    }
  }

  private fun startOverlayTradeExecution(signalRow: JSONObject) {
    if (tradeExecutor != null) {
      Log.i(TAG, "Overlay trade already in progress — ignoring duplicate signal")
      return
    }
    val signalId = signalRow.optString("id", "").trim()
    if (signalId.isNotEmpty() && isSignalProcessed(signalId)) {
      Log.i(TAG, "Signal $signalId already executed — skipping overlay trade")
      return
    }
    if (signalId.isNotEmpty()) {
      markSignalProcessedNative(signalId)
    }
    val config = loadOverlayTradeConfig()
    val root = overlayRoot
    if (config == null) {
      Log.e(TAG, "Overlay trade config missing — sync MT5 from app settings")
      showTradePanelInternal(
        signalRow.optString("asset", ""),
        signalRow.optString("action", ""),
        "MT5 not configured — open NexTradeAI once to sync"
      )
      return
    }
    if (config.mt5Login.isBlank() || config.mt5Password.isBlank()) {
      Log.e(TAG, "Overlay trade config missing MT5 credentials")
      showTradePanelInternal(
        signalRow.optString("asset", ""),
        signalRow.optString("action", ""),
        "MT5 login required — open NexTradeAI to connect"
      )
      return
    }
    if (root == null) {
      Log.e(TAG, "Overlay root missing — cannot host trade WebView")
      return
    }

    emitOverlayTradeStarted()
    tradeExecutor =
      OverlayTradeExecutor(
        reactApplicationContext,
        root,
        onStatus = { status ->
          UiThreadUtil.runOnUiThread {
            if (tradePanelVisible) {
              tradeStatusView?.text = status
              applyTradePhase(inferTradePhase(status))
            }
          }
        },
        onFinished = { success, message ->
          UiThreadUtil.runOnUiThread {
            Log.i(TAG, "Overlay trade finished success=$success msg=$message")
            hideTradeOverlayInternal()
            if (success) {
              val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
              prefs.edit().putString(KEY_LAST_POLL, isoUtc(System.currentTimeMillis())).apply()
            }
            emitOverlayTradeCompleted(signalRow, success, message)
            tradeExecutor = null
            val restartDelayMs = if (success) 35_000L else 8_000L
            maybeRestartBackgroundPolling(restartDelayMs)
          }
        }
      )
    tradeExecutor?.start(config, signalRow)
  }

  private fun emitOverlayTradeStarted() {
    try {
      if (!reactContext.hasActiveReactInstance()) return
      reactContext
        .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
        .emit(EVENT_TRADE_STARTED, null)
    } catch (e: Exception) {
      Log.e(TAG, "emitOverlayTradeStarted", e)
    }
  }

  private fun emitOverlayTradeCompleted(signalRow: JSONObject, success: Boolean, message: String) {
    try {
      if (!reactContext.hasActiveReactInstance()) return
      val params = Arguments.createMap()
      params.putString("signalId", signalRow.optString("id", ""))
      params.putString("asset", signalRow.optString("asset", ""))
      params.putBoolean("success", success)
      params.putString("message", message)
      reactContext
        .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
        .emit(EVENT_TRADE_COMPLETED, params)
    } catch (e: Exception) {
      Log.e(TAG, "emitOverlayTradeCompleted", e)
    }
  }

  private fun maybeRestartBackgroundPolling(initialDelayMs: Long = 8_000L) {
    val lic = bgPollLicenseKey ?: return
    val base = bgPollApiBase ?: return
    if (bgPollFuture != null) return
    try {
      val scheduler = Executors.newSingleThreadScheduledExecutor()
      bgPollScheduler = scheduler
      bgPollFuture =
        scheduler.scheduleWithFixedDelay({
          try {
            runNativeBackgroundPollIteration()
          } catch (e: Exception) {
            Log.e(TAG, "poll iteration", e)
          }
        }, initialDelayMs, 5, TimeUnit.SECONDS)
      Log.i(TAG, "Restarted native background polling after overlay trade (delay ${initialDelayMs}ms)")
    } catch (e: Exception) {
      Log.e(TAG, "maybeRestartBackgroundPolling", e)
    }
  }

  @ReactMethod
  fun setOverlayTradeConfig(configJson: String, promise: Promise) {
    try {
      val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
      prefs.edit().putString(KEY_TRADE_CONFIG, configJson).apply()
      overlayTradeConfig = null
      loadOverlayTradeConfig()
      promise.resolve(true)
    } catch (e: Exception) {
      promise.reject("E_TRADE_CONFIG", e.message, e)
    }
  }

  @ReactMethod
  fun isOverlayTradeActive(promise: Promise) {
    promise.resolve(tradeExecutor != null)
  }

  @ReactMethod
  fun markOverlaySignalProcessed(signalId: String, promise: Promise) {
    try {
      markSignalProcessedNative(signalId.trim())
      promise.resolve(true)
    } catch (e: Exception) {
      promise.reject("E_SIGNAL_PROCESSED", e.message, e)
    }
  }

  @ReactMethod
  fun executeOverlayTradeFromSignal(payload: String, promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        val arr = JSONArray(payload)
        if (arr.length() == 0) {
          promise.resolve(false)
          return@runOnUiThread
        }
        val row = arr.getJSONObject(0)
        showTradePanelInternal(
          row.optString("asset", ""),
          row.optString("action", ""),
          "Logging in — waiting to execute active signal…"
        )
        startOverlayTradeExecution(row)
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_OVERLAY_TRADE", e.message, e)
      }
    }
  }

  @ReactMethod
  fun clearOverlayTradeConfig(promise: Promise) {
    try {
      val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
      prefs.edit().remove(KEY_TRADE_CONFIG).apply()
      overlayTradeConfig = null
      tradeExecutor?.stop()
      tradeExecutor = null
      promise.resolve(true)
    } catch (e: Exception) {
      promise.reject("E_TRADE_CONFIG_CLEAR", e.message, e)
    }
  }

  private fun emitOverlayExecuteSignal(payload: String) {
    try {
      if (!reactContext.hasActiveReactInstance()) {
        Log.w(TAG, "No active React instance for overlay execute emit")
        return
      }
      val params = Arguments.createMap()
      params.putString("payload", payload)
      reactContext
        .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
        .emit(EVENT_EXECUTE_SIGNAL, params)
      Log.i(TAG, "Emitted $EVENT_EXECUTE_SIGNAL to JS")
    } catch (e: Exception) {
      Log.e(TAG, "emitOverlayExecuteSignal", e)
    }
  }

  private fun showTradeOverlayFromPayload(payload: String) {
    try {
      val arr = JSONArray(payload)
      if (arr.length() == 0) return
      val row = arr.getJSONObject(0)
      val symbol = row.optString("asset", "")
      val action = row.optString("action", "")
      showTradePanelInternal(symbol, action, "Logging in — waiting to execute active signal…")
    } catch (e: Exception) {
      Log.e(TAG, "showTradeOverlayFromPayload", e)
    }
  }

  private fun loadLogoIntoView() {
    val iv = logoView ?: return
    val generation = logoLoadGeneration.incrementAndGet()
    val url = lastBotImageUrl?.trim().orEmpty()

    imageLoadExecutor.execute {
      val bmp: Bitmap? =
        try {
          if (url.isNotEmpty()) {
            val conn = URL(url).openConnection() as HttpURLConnection
            conn.connectTimeout = 15000
            conn.readTimeout = 15000
            conn.instanceFollowRedirects = true
            conn.useCaches = false
            BitmapFactory.decodeStream(conn.inputStream)
          } else {
            null
          }
        } catch (_: Exception) {
          null
        }

      if (logoLoadGeneration.get() != generation) {
        bmp?.recycle()
        return@execute
      }

      UiThreadUtil.runOnUiThread {
        if (logoLoadGeneration.get() != generation) {
          bmp?.recycle()
          return@runOnUiThread
        }
        try {
          if (bmp != null) {
            iv.setImageBitmap(bmp)
          } else {
            val pm = reactApplicationContext.packageManager
            val icon = pm.getApplicationIcon(reactApplicationContext.packageName)
            iv.setImageDrawable(icon)
          }
        } catch (_: Exception) {
          iv.setImageResource(android.R.drawable.ic_dialog_info)
        }
      }
    }
  }

  private fun attachDrag(root: View, wm: WindowManager, params: WindowManager.LayoutParams) {
    root.setOnTouchListener(object : View.OnTouchListener {
      private var initX = 0
      private var initY = 0
      private var downRawX = 0f
      private var downRawY = 0f

      override fun onTouch(v: View, e: MotionEvent): Boolean {
        when (e.actionMasked) {
          MotionEvent.ACTION_DOWN -> {
            initX = params.x
            initY = params.y
            downRawX = e.rawX
            downRawY = e.rawY
            return true
          }
          MotionEvent.ACTION_MOVE -> {
            params.x = initX + (e.rawX - downRawX).toInt()
            params.y = initY + (e.rawY - downRawY).toInt()
            try {
              wm.updateViewLayout(root, params)
            } catch (_: Exception) {
            }
            return true
          }
        }
        return false
      }
    })
  }

  @ReactMethod
  fun showOverlay(x: Double, y: Double, width: Double, height: Double, promise: Promise) {
    if (!canDrawOverlays()) {
      promise.resolve(false)
      return
    }
    UiThreadUtil.runOnUiThread {
      try {
        val ctx = reactApplicationContext
        val wm = ctx.getSystemService(Context.WINDOW_SERVICE) as WindowManager
        windowManager = wm

        overlayRoot?.let { old ->
          try {
            wm.removeView(old)
          } catch (_: Exception) {
          }
        }
        overlayRoot = null
        logoView = null
        overlayColumn = null
        tradePanel = null
        tradePanelVisible = false
        layoutParams = null

        val diameter = max(max(width.roundToInt(), height.roundToInt()), 96)
        lastLogoDiameterPx = diameter

        val root = FrameLayout(ctx).apply {
          setBackgroundColor(Color.TRANSPARENT)
        }

        val column = LinearLayout(ctx).apply {
          orientation = LinearLayout.VERTICAL
          gravity = Gravity.CENTER_HORIZONTAL
          layoutParams =
            FrameLayout.LayoutParams(
              FrameLayout.LayoutParams.MATCH_PARENT,
              FrameLayout.LayoutParams.WRAP_CONTENT
            )
        }

        val iv = ImageView(ctx)
        iv.layoutParams = LinearLayout.LayoutParams(diameter, diameter)
        applyCircleClip(iv, diameter)
        logoView = iv
        column.addView(iv)

        val trade = buildTradePanel(ctx)
        trade.visibility = View.GONE
        val tradeLp =
          LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            LinearLayout.LayoutParams.WRAP_CONTENT
          )
        tradeLp.topMargin = dp(TRADE_PANEL_GAP_DP)
        trade.layoutParams = tradeLp
        tradePanel = trade
        column.addView(trade)

        root.addView(column)
        overlayColumn = column

        val screenW = screenWidthPx()
        val params =
          WindowManager.LayoutParams(
            screenW,
            diameter,
            overlayWindowType(),
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
              WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
            PixelFormat.TRANSLUCENT
          ).apply {
            gravity = Gravity.TOP or Gravity.START
            this.x = 0
            this.y = y.roundToInt()
          }
        layoutParams = params
        attachDrag(root, wm, params)

        wm.addView(root, params)
        overlayRoot = root
        trackOverlayViews(root, wm)
        startOverlayLifecycleService()

        applyPausedAlpha()
        iv.contentDescription = lastBotName
        loadLogoIntoView()

        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_OVERLAY_SHOW", e.message, e)
      }
    }
  }

  @ReactMethod
  fun updateOverlayPosition(x: Double, y: Double, promise: Promise) {
    UiThreadUtil.runOnUiThread {
      val lp = layoutParams
      val v = overlayRoot
      val wm = windowManager
      if (lp == null || v == null || wm == null) {
        promise.resolve(false)
        return@runOnUiThread
      }
      lp.x = x.roundToInt()
      lp.y = y.roundToInt()
      try {
        wm.updateViewLayout(v, lp)
        promise.resolve(true)
      } catch (_: Exception) {
        promise.resolve(false)
      }
    }
  }

  @ReactMethod
  fun updateOverlaySize(width: Double, height: Double, promise: Promise) {
    UiThreadUtil.runOnUiThread {
      val lp = layoutParams
      val v = overlayRoot
      val wm = windowManager
      val iv = logoView
      if (lp == null || v == null || wm == null || iv == null) {
        promise.resolve(false)
        return@runOnUiThread
      }
      val diameter = max(max(width.roundToInt(), height.roundToInt()), 96)
      lastLogoDiameterPx = diameter
      iv.layoutParams = LinearLayout.LayoutParams(diameter, diameter)
      applyCircleClip(iv, diameter)
      refreshOverlayWindowSize()
      try {
        wm.updateViewLayout(v, lp)
        promise.resolve(true)
      } catch (_: Exception) {
        promise.resolve(false)
      }
    }
  }

  @ReactMethod
  fun hideOverlay(promise: Promise) {
    UiThreadUtil.runOnUiThread {
      try {
        tearDownAllInternal(stopLifecycleService = true)
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_OVERLAY_HIDE", e.message, e)
      }
    }
  }

  @ReactMethod
  fun getOverlayViewTag(promise: Promise) {
    promise.resolve(-1)
  }

  @ReactMethod
  fun updateOverlayData(
    botName: String,
    isActive: Boolean,
    isPaused: Boolean,
    botImageURL: String?,
    promise: Promise
  ) {
    lastBotName = botName.ifBlank { "NexTradeAI" }
    lastPaused = isPaused
    val nextUrl = botImageURL?.trim()?.takeIf { it.isNotEmpty() }
    lastBotImageUrl = nextUrl

    UiThreadUtil.runOnUiThread {
      logoView?.contentDescription = lastBotName
      applyPausedAlpha()
      if (logoView != null) {
        loadLogoIntoView()
      }
      promise.resolve(true)
    }
  }

  private fun stopNativeBackgroundPollingInternal() {
    try {
      bgPollFuture?.cancel(false)
    } catch (_: Exception) {
    }
    bgPollFuture = null
    try {
      bgPollScheduler?.shutdownNow()
    } catch (_: Exception) {
    }
    bgPollScheduler = null
  }

  @ReactMethod
  fun startNativeBackgroundPolling(
    licenseKey: String,
    apiBaseUrl: String,
    chartWarmupEnabled: Boolean,
    promise: Promise
  ) {
    val lic = licenseKey.trim()
    val base = apiBaseUrl.trim().trimEnd('/')
    if (lic.isEmpty() || base.isEmpty()) {
      promise.resolve(false)
      return
    }
    bgPollLicenseKey = lic
    bgPollApiBase = base
    bgChartWarmupEnabled = chartWarmupEnabled
    stopNativeBackgroundPollingInternal()
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    prefs.edit().putInt(KEY_EMPTY_COUNT, 0).apply()
    val scheduler = Executors.newSingleThreadScheduledExecutor()
    bgPollScheduler = scheduler
    bgPollFuture = scheduler.scheduleWithFixedDelay({
      try {
        runNativeBackgroundPollIteration()
      } catch (e: Exception) {
        Log.e(TAG, "poll iteration", e)
      }
    }, 5, 5, TimeUnit.SECONDS)
    Log.i(TAG, "Started native background signal polling")
    promise.resolve(true)
  }

  @ReactMethod
  fun stopNativeBackgroundPolling(promise: Promise) {
    stopNativeBackgroundPollingInternal()
    bgPollLicenseKey = null
    bgPollApiBase = null
    Log.i(TAG, "Stopped native background signal polling")
    promise.resolve(true)
  }

  @ReactMethod
  fun consumePendingForegroundAction(promise: Promise) {
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    val type = prefs.getString(KEY_PENDING_TYPE, null)
    if (type.isNullOrEmpty()) {
      promise.resolve(null)
      return
    }
    if (type == "signal" && prefs.getLong(KEY_OVERLAY_HANDOFF, 0L) > 0L) {
      prefs.edit()
        .remove(KEY_PENDING_TYPE)
        .remove(KEY_PENDING_JSON)
        .remove(KEY_OVERLAY_HANDOFF)
        .apply()
      Log.i(TAG, "Discarding pending signal — handled by overlay headless handoff")
      promise.resolve(null)
      return
    }
    val payload = prefs.getString(KEY_PENDING_JSON, null)
    prefs.edit().remove(KEY_PENDING_TYPE).remove(KEY_PENDING_JSON).remove(KEY_OVERLAY_HANDOFF).apply()
    val map = Arguments.createMap()
    map.putString("type", type)
    if (!payload.isNullOrEmpty()) {
      map.putString("payload", payload)
    }
    promise.resolve(map)
  }

  @ReactMethod
  fun addListener(eventName: String) {
    // Required for NativeEventEmitter
  }

  @ReactMethod
  fun removeListeners(count: Int) {
    // Required for NativeEventEmitter
  }

  @ReactMethod
  fun setLastChartWarmupAt(ms: Double, promise: Promise) {
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    val at = ms.toLong().coerceAtLeast(0L)
    prefs.edit().putLong(KEY_LAST_WARMUP_AT, at).apply()
    promise.resolve(true)
  }

  private fun isoUtc(ms: Long): String {
    val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.US)
    sdf.timeZone = TimeZone.getTimeZone("UTC")
    return sdf.format(Date(ms))
  }

  private fun httpGet(urlStr: String): String? {
    return try {
      val conn = URL(urlStr).openConnection() as HttpURLConnection
      conn.connectTimeout = 20000
      conn.readTimeout = 20000
      conn.instanceFollowRedirects = true
      conn.useCaches = false
      if (conn.responseCode !in 200..299) {
        Log.w(TAG, "HTTP ${conn.responseCode}: $urlStr")
        return null
      }
      conn.inputStream.bufferedReader().use { it.readText() }
    } catch (e: Exception) {
      Log.w(TAG, "GET failed: $urlStr", e)
      null
    }
  }

  private fun runNativeBackgroundPollIteration() {
    val lic = bgPollLicenseKey ?: return
    val base = bgPollApiBase ?: return
    val prefs = reactContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    val eaUrl =
      "$base/api/get-ea-from-license?licenseKey=${URLEncoder.encode(lic, "UTF-8")}"
    val eaBody = httpGet(eaUrl) ?: return
    val eaJson =
      try {
        JSONObject(eaBody)
      } catch (e: Exception) {
        Log.w(TAG, "EA JSON parse", e)
        return
      }
    val eaId =
      eaJson.optString("id", "").ifEmpty { eaJson.optString("eaId", "") }.ifEmpty { return }

    val since =
      prefs.getString(KEY_LAST_POLL, null)
        ?: isoUtc(System.currentTimeMillis() - 86400_000L)

    val sigUrl =
      "$base/api/get-new-signals?eaId=${URLEncoder.encode(eaId, "UTF-8")}&since=${URLEncoder.encode(since, "UTF-8")}"
    val sigBody = httpGet(sigUrl) ?: return
    val sigJson =
      try {
        JSONObject(sigBody)
      } catch (e: Exception) {
        Log.w(TAG, "signals JSON parse", e)
        return
      }
    val arr = sigJson.optJSONArray("signals") ?: JSONArray()

    if (arr.length() > 0) {
      val unprocessed = filterUnprocessedSignals(arr)
      if (unprocessed.length() == 0) {
        prefs.edit().putString(KEY_LAST_POLL, isoUtc(System.currentTimeMillis())).apply()
        Log.i(TAG, "All polled signals already executed — advancing poll cursor")
        return
      }
      val payload = unprocessed.toString()
      prefs.edit()
        .putString(KEY_LAST_POLL, isoUtc(System.currentTimeMillis()))
        .putInt(KEY_EMPTY_COUNT, 0)
        .putString(KEY_PENDING_TYPE, "signal")
        .putString(KEY_PENDING_JSON, payload)
        .putLong(KEY_OVERLAY_HANDOFF, System.currentTimeMillis())
        .apply()
      stopNativeBackgroundPollingInternal()
      handleBackgroundSignalFound(payload)
      Log.i(TAG, "Signal found — headless overlay trade (no visible app UI)")
    } else {
      val nextCount = prefs.getInt(KEY_EMPTY_COUNT, 0) + 1
      prefs.edit()
        .putInt(KEY_EMPTY_COUNT, nextCount)
        .putString(KEY_LAST_POLL, isoUtc(System.currentTimeMillis() - 5000))
        .apply()
      if (bgChartWarmupEnabled && nextCount >= EMPTY_POLLS_BEFORE_WARMUP) {
        val lastWarmup = prefs.getLong(KEY_LAST_WARMUP_AT, 0L)
        val elapsed = System.currentTimeMillis() - lastWarmup
        if (lastWarmup > 0L && elapsed < CHART_WARMUP_COOLDOWN_MS) {
          prefs.edit().putInt(KEY_EMPTY_COUNT, EMPTY_POLLS_BEFORE_WARMUP).apply()
          Log.i(
            TAG,
            "Chart warmup cooldown active — ~${((CHART_WARMUP_COOLDOWN_MS - elapsed) / 60000L).coerceAtLeast(1)} min left"
          )
          return
        }
        prefs.edit()
          .putString(KEY_PENDING_TYPE, "chart_warmup")
          .remove(KEY_PENDING_JSON)
          .putInt(KEY_EMPTY_COUNT, 0)
          .apply()
        stopNativeBackgroundPollingInternal()
        bringMainActivityToFront("chart_warmup")
      }
    }
  }

  private fun bringMainActivityToFront(reason: String) {
    try {
      Log.i(TAG, "Bringing main activity to front: $reason")
      val intent = Intent(reactContext, MainActivity::class.java).apply {
        addFlags(
          Intent.FLAG_ACTIVITY_NEW_TASK or
            Intent.FLAG_ACTIVITY_REORDER_TO_FRONT or
            Intent.FLAG_ACTIVITY_SINGLE_TOP
        )
      }
      reactContext.startActivity(intent)
    } catch (e: Exception) {
      Log.e(TAG, "bringMainActivityToFront", e)
    }
  }
}
