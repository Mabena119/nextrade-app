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
import android.view.ViewOutlineProvider
import android.view.WindowManager
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import app.auraai.app.MainActivity
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.UiThreadUtil
import com.facebook.react.modules.core.DeviceEventManagerModule
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
  private var tradePhaseViews: List<TextView> = emptyList()

  private var lastLogoDiameterPx: Int = 350
  private var tradePanelVisible: Boolean = false

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

  companion object {
    private const val TAG = "EaNativePoll"
    private const val PREFS = "ea_native_bg_poll"
    private const val KEY_LAST_POLL = "last_poll_iso"
    private const val KEY_EMPTY_COUNT = "empty_count"
    private const val KEY_PENDING_TYPE = "pending_type"
    private const val KEY_PENDING_JSON = "pending_payload"
    private const val KEY_LAST_WARMUP_AT = "last_chart_warmup_at_ms"
    private const val KEY_OVERLAY_HANDOFF = "overlay_handoff_at_ms"
    private const val EMPTY_POLLS_BEFORE_WARMUP = 10
    private const val CHART_WARMUP_COOLDOWN_MS = 45L * 60L * 1000L
    private const val EVENT_EXECUTE_SIGNAL = "EaOverlayExecuteSignal"
    private const val TRADE_CARD_WIDTH_DP = 340
    private const val TRADE_PANEL_GAP_DP = 10
    private const val TRADE_PANEL_HEIGHT_DP = 168
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
    lp.flags =
      if (tradeVisible) {
        WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS
      } else {
        WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
          WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS
      }
  }

  private fun refreshOverlayWindowSize() {
    val lp = layoutParams ?: return
    val wm = windowManager ?: return
    val root = overlayRoot ?: return
    val cardWidth = dp(TRADE_CARD_WIDTH_DP)
    val width = max(lastLogoDiameterPx, cardWidth)
    val height =
      if (tradePanelVisible) {
        lastLogoDiameterPx + dp(TRADE_PANEL_GAP_DP) + dp(TRADE_PANEL_HEIGHT_DP)
      } else {
        lastLogoDiameterPx
      }
    lp.width = width
    lp.height = height
    try {
      wm.updateViewLayout(root, lp)
    } catch (_: Exception) {
    }
  }

  private fun buildTradePanel(ctx: Context): LinearLayout {
    val cardWidth = dp(TRADE_CARD_WIDTH_DP)
    val pad = dp(14)
    val card = LinearLayout(ctx).apply {
      orientation = LinearLayout.VERTICAL
      val bg = GradientDrawable().apply {
        setColor(Color.parseColor("#0C0E16"))
        cornerRadius = dp(18).toFloat()
        setStroke(dp(1), Color.parseColor("#33FFFFFF"))
      }
      background = bg
      setPadding(pad, pad, pad, pad)
      layoutParams = LinearLayout.LayoutParams(cardWidth, LinearLayout.LayoutParams.WRAP_CONTENT)
    }

    val header = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
    }
    val action = TextView(ctx).apply {
      setTextColor(Color.WHITE)
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
      typeface = Typeface.DEFAULT_BOLD
      setPadding(dp(8), dp(4), dp(8), dp(4))
      background = GradientDrawable().apply {
        cornerRadius = dp(8).toFloat()
        setColor(Color.parseColor("#33FFFFFF"))
      }
    }
    tradeActionView = action
    header.addView(action)

    val symbol = TextView(ctx).apply {
      setTextColor(Color.WHITE)
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 18f)
      typeface = Typeface.DEFAULT_BOLD
      setPadding(dp(10), 0, 0, 0)
      layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
    }
    tradeSymbolView = symbol
    header.addView(symbol)
    card.addView(header)

    val status = TextView(ctx).apply {
      setTextColor(Color.parseColor("#9AA7B5"))
      setTextSize(TypedValue.COMPLEX_UNIT_SP, 13f)
      setPadding(0, dp(10), 0, dp(10))
    }
    tradeStatusView = status
    card.addView(status)

    val phases = LinearLayout(ctx).apply {
      orientation = LinearLayout.HORIZONTAL
      gravity = Gravity.CENTER_VERTICAL
    }
    val labels = listOf("Connect", "Terminal", "Execute")
    val phaseViews = mutableListOf<TextView>()
    labels.forEachIndexed { index, label ->
      val tv = TextView(ctx).apply {
        text = label
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 10f)
        setTextColor(Color.parseColor("#667085"))
        layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
        gravity = Gravity.CENTER
      }
      phaseViews.add(tv)
      phases.addView(tv)
      if (index < labels.lastIndex) {
        val line = View(ctx).apply {
          setBackgroundColor(Color.parseColor("#334155"))
          layoutParams = LinearLayout.LayoutParams(dp(12), dp(2))
        }
        phases.addView(line)
      }
    }
    tradePhaseViews = phaseViews
    card.addView(phases)

    return card
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
    tradePhaseViews.forEachIndexed { index, tv ->
      val active = index <= phase
      tv.setTextColor(if (active) Color.parseColor("#38BDF8") else Color.parseColor("#667085"))
      tv.typeface = if (active) Typeface.DEFAULT_BOLD else Typeface.DEFAULT
    }
  }

  private fun applyTradeCardUi(symbol: String, action: String, status: String) {
    val sym = symbol.trim().ifEmpty { "MARKET" }.uppercase(Locale.US)
    val act = action.trim().uppercase(Locale.US)
    tradeSymbolView?.text = sym
    tradeActionView?.text = if (act == "SELL") "SELL ↓" else if (act == "BUY") "BUY ↑" else "READY"
    val actionColor =
      when (act) {
        "SELL" -> Color.parseColor("#FB7185")
        "BUY" -> Color.parseColor("#34D399")
        else -> Color.parseColor("#38BDF8")
      }
    tradeActionView?.setTextColor(Color.WHITE)
    (tradeActionView?.background as? GradientDrawable)?.setColor(actionColor)
    tradeStatusView?.text = status.ifBlank { "Connecting to server…" }
    applyTradePhase(inferTradePhase(status))
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
        tradePanel?.visibility = View.GONE
        tradePanelVisible = false
        applyOverlayWindowFlags(false)
        refreshOverlayWindowSize()
        promise.resolve(true)
      } catch (e: Exception) {
        promise.reject("E_TRADE_HIDE", e.message, e)
      }
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
      showTradePanelInternal(symbol, action, "Connecting to server…")
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
        }

        val iv = ImageView(ctx)
        iv.layoutParams = LinearLayout.LayoutParams(diameter, diameter)
        applyCircleClip(iv, diameter)
        logoView = iv
        column.addView(iv)

        val trade = buildTradePanel(ctx)
        trade.visibility = View.GONE
        val tradeLp = LinearLayout.LayoutParams(
          LinearLayout.LayoutParams.WRAP_CONTENT,
          LinearLayout.LayoutParams.WRAP_CONTENT
        )
        tradeLp.topMargin = dp(TRADE_PANEL_GAP_DP)
        trade.layoutParams = tradeLp
        tradePanel = trade
        column.addView(trade)

        val columnLp = FrameLayout.LayoutParams(
          FrameLayout.LayoutParams.WRAP_CONTENT,
          FrameLayout.LayoutParams.WRAP_CONTENT
        )
        root.addView(column, columnLp)
        overlayColumn = column

        val cardWidth = dp(TRADE_CARD_WIDTH_DP)
        val windowWidth = max(diameter, cardWidth)
        val params = WindowManager.LayoutParams(
          windowWidth,
          diameter,
          overlayWindowType(),
          WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
            WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
          PixelFormat.TRANSLUCENT
        ).apply {
          gravity = Gravity.TOP or Gravity.START
          this.x = x.roundToInt()
          this.y = y.roundToInt()
        }
        layoutParams = params
        attachDrag(root, wm, params)

        wm.addView(root, params)
        overlayRoot = root

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
    logoLoadGeneration.incrementAndGet()
    UiThreadUtil.runOnUiThread {
      try {
        tradePanel?.visibility = View.GONE
        tradePanelVisible = false
        overlayRoot?.let { v ->
          windowManager?.removeView(v)
        }
      } catch (_: Exception) {
      }
      overlayRoot = null
      logoView = null
      overlayColumn = null
      tradePanel = null
      layoutParams = null
      promise.resolve(true)
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
    val handoffAt = prefs.getLong(KEY_OVERLAY_HANDOFF, 0L)
    if (type == "signal" && handoffAt > 0L) {
      prefs.edit()
        .remove(KEY_PENDING_TYPE)
        .remove(KEY_PENDING_JSON)
        .remove(KEY_OVERLAY_HANDOFF)
        .apply()
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
      val payload = arr.toString()
      prefs.edit()
        .putString(KEY_LAST_POLL, isoUtc(System.currentTimeMillis()))
        .putInt(KEY_EMPTY_COUNT, 0)
        .putString(KEY_PENDING_TYPE, "signal")
        .putString(KEY_PENDING_JSON, payload)
        .putLong(KEY_OVERLAY_HANDOFF, System.currentTimeMillis())
        .apply()
      stopNativeBackgroundPollingInternal()
      UiThreadUtil.runOnUiThread {
        showTradeOverlayFromPayload(payload)
      }
      emitOverlayExecuteSignal(payload)
      Log.i(TAG, "Signal found — overlay trade card shown, app stays in background")
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
          .remove(KEY_OVERLAY_HANDOFF)
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
