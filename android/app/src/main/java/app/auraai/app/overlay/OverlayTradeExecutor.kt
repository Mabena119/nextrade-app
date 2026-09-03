package app.auraai.app.overlay

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.concurrent.Executors

data class OverlayTradeConfig(
  val mt5Login: String,
  val mt5Password: String,
  val mt5Server: String,
  val terminalUrl: String,
  val brokerKey: String,
  val proxyBaseUrl: String,
  val robotName: String,
  val volume: String,
  val numberOfTrades: String,
  val symbolMapJson: String,
)

class OverlayTradeExecutor(
  private val context: Context,
  private val hostRoot: FrameLayout,
  private val onStatus: (String) -> Unit,
  private val onFinished: (success: Boolean, message: String) -> Unit,
) {
  companion object {
    private const val TAG = "OverlayTradeWebView"
    private val fetchExecutor = Executors.newSingleThreadExecutor()
  }

  private var webView: WebView? = null
  private val mainHandler = Handler(Looper.getMainLooper())
  private var finished = false
  private var destroyScheduled = false

  @SuppressLint("SetJavaScriptEnabled")
  fun start(config: OverlayTradeConfig, signalJson: JSONObject) {
    cancelPendingDestroy()
    stopInternal(destroy = true, immediate = true)
    finished = false

    val asset = signalJson.optString("asset", "").trim()
    val action = signalJson.optString("action", "").trim()
    val sl = signalJson.optString("sl", "")
    val tp = signalJson.optString("tp", "")
    val volume =
      signalJson.optString("lot", "").trim().ifEmpty { config.volume }.ifEmpty { "0.01" }
    val executionSymbol = resolveExecutionSymbol(asset, config.symbolMapJson)
    if (executionSymbol.isEmpty()) {
      onStatus("Quote set not found for $asset")
      finish(false, "symbol_not_found")
      return
    }

    val proxyUrl = buildTradingProxyUrl(config, executionSymbol, action, sl, tp, volume)
    val proxyBase = config.proxyBaseUrl.trimEnd('/')
    Log.i(TAG, "Preparing overlay trade WebView")

    val wv =
      WebView(context).apply {
        setBackgroundColor(Color.TRANSPARENT)
        alpha = 0.01f
        importantForAccessibility = View.IMPORTANT_FOR_ACCESSIBILITY_NO
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.mediaPlaybackRequiresUserGesture = false
        settings.userAgentString =
          "Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(this, true)
      }

    wv.addJavascriptInterface(
      Mt5MessageBridge { raw -> mainHandler.post { handleMessage(raw) } },
      "ReactNativeWebView"
    )

    wv.webChromeClient = WebChromeClient()
    wv.webViewClient =
      object : WebViewClient() {
        override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
          val req = request ?: return false
          if (!req.isForMainFrame) return false
          val url = req.url?.toString().orEmpty()
          // Keep the injected trading shell — block main-frame hops to bare /terminal wrapper pages.
          if (
            url.contains("/terminal") &&
            !url.contains("/api/mt5-trading-proxy") &&
            !url.contains("/api/mt5-proxy")
          ) {
            Log.w(TAG, "Blocked main-frame navigation off trading shell: ${url.take(120)}")
            return true
          }
          return false
        }

        override fun onPageFinished(view: WebView?, url: String?) {
          super.onPageFinished(view, url)
          Log.i(TAG, "Page finished: ${url?.take(120)}")
        }
      }

    val lp =
      FrameLayout.LayoutParams(
        FrameLayout.LayoutParams.MATCH_PARENT,
        FrameLayout.LayoutParams.MATCH_PARENT,
        Gravity.CENTER
      )
    hostRoot.addView(wv, 0, lp)
    webView = wv
    onStatus("Logging in — waiting to execute active signal…")

    fetchExecutor.execute {
      val html = httpGet(proxyUrl)
      mainHandler.post {
        if (finished || webView !== wv) return@post
        if (!html.isNullOrBlank()) {
          Log.i(TAG, "Loading trading-proxy HTML via loadDataWithBaseURL")
          wv.loadDataWithBaseURL("$proxyBase/", html, "text/html", "UTF-8", null)
        } else {
          Log.w(TAG, "Proxy HTML fetch failed — falling back to loadUrl")
          wv.loadUrl(proxyUrl)
        }
      }
    }
  }

  fun stop() {
    cancelPendingDestroy()
    stopInternal(destroy = true, immediate = false)
  }

  private var pendingDestroy: Runnable? = null

  private fun cancelPendingDestroy() {
    pendingDestroy?.let { mainHandler.removeCallbacks(it) }
    pendingDestroy = null
    destroyScheduled = false
  }

  private fun stopInternal(destroy: Boolean, immediate: Boolean) {
    val wv = webView ?: return
    webView = null
    try {
      wv.stopLoading()
      wv.loadUrl("about:blank")
      (wv.parent as? ViewGroup)?.removeView(wv)
    } catch (e: Exception) {
      Log.w(TAG, "stopInternal detach", e)
    }

    if (!destroy) return

    val destroyTask = Runnable {
      try {
        wv.removeJavascriptInterface("ReactNativeWebView")
        wv.destroy()
      } catch (e: Exception) {
        Log.w(TAG, "stopInternal destroy", e)
      }
      destroyScheduled = false
      pendingDestroy = null
    }

    if (immediate) {
      destroyTask.run()
    } else {
      destroyScheduled = true
      pendingDestroy = destroyTask
      mainHandler.postDelayed(destroyTask, 450)
    }
  }

  private fun finish(success: Boolean, message: String) {
    if (finished) return
    finished = true
    onFinished(success, message)
    stopInternal(destroy = true, immediate = false)
  }

  private fun handleMessage(raw: String) {
    if (finished) return
    try {
      val data = JSONObject(raw)
      val type = data.optString("type", "")
      val message = data.optString("message", "")
      Log.i(TAG, "MT5 message: $type — ${message.take(80)}")

      when (type) {
        "step_update", "symbol_search", "symbol_selected" -> {
          if (!message.contains("Market Watch already visible", ignoreCase = true)) {
            onStatus(message.ifBlank { "Working…" })
          }
        }
        "authentication_success" -> onStatus("Ready")
        "authentication_failed" -> {
          onStatus("Authentication failed: $message")
          finish(false, message)
        }
        "error" -> {
          onStatus(message.ifBlank { "Error" })
          finish(false, message)
        }
        "all_trades_completed" -> {
          onStatus("All trades completed")
          finish(true, "completed")
        }
        "chart_screenshot", "chart_warmup_capture_failed", "ai_trade_inject_failed" -> {
          // Copy-trade overlay path — ignore chart AI messages
        }
      }
    } catch (e: Exception) {
      Log.w(TAG, "handleMessage parse", e)
    }
  }

  private fun resolveExecutionSymbol(asset: String, symbolMapJson: String): String {
    if (asset.isEmpty()) return ""
    try {
      val map = JSONObject(symbolMapJson.ifBlank { "{}" })
      val direct = map.optString(asset, "").trim()
      if (direct.isNotEmpty()) return direct
      val upper = asset.uppercase()
      val keys = map.keys()
      while (keys.hasNext()) {
        val key = keys.next()
        if (key.uppercase() == upper) {
          val v = map.optString(key, "").trim()
          if (v.isNotEmpty()) return v
        }
      }
    } catch (_: Exception) {
    }
    return asset
  }

  private fun buildTradingProxyUrl(
    config: OverlayTradeConfig,
    symbol: String,
    action: String,
    sl: String,
    tp: String,
    volume: String,
  ): String {
    val base = config.proxyBaseUrl.trimEnd('/')
    val enc = { v: String -> URLEncoder.encode(v, "UTF-8") }
    // Server appends " - NexTradeAI" — pass raw bot name only.
    val robot =
      config.robotName
        .trim()
        .removeSuffix(" - NexTradeAI")
        .trim()
        .ifEmpty { "NexTradeAI" }
    return buildString {
      append(base)
      append("/api/mt5-trading-proxy?")
      append("url=").append(enc(config.terminalUrl))
      append("&login=").append(enc(config.mt5Login))
      append("&password=").append(enc(config.mt5Password))
      append("&broker=").append(enc(config.brokerKey))
      append("&symbol=").append(enc(symbol))
      append("&action=").append(enc(action))
      append("&sl=").append(enc(sl))
      append("&tp=").append(enc(tp))
      append("&volume=").append(enc(volume))
      append("&robotName=").append(enc(robot))
      append("&numberOfTrades=").append(enc(config.numberOfTrades))
    }
  }

  private fun httpGet(urlStr: String): String? {
    return try {
      val conn = URL(urlStr).openConnection() as HttpURLConnection
      conn.connectTimeout = 25000
      conn.readTimeout = 25000
      conn.instanceFollowRedirects = true
      conn.useCaches = false
      conn.setRequestProperty(
        "User-Agent",
        "Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
      )
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

  private class Mt5MessageBridge(private val onMessage: (String) -> Unit) {
    @JavascriptInterface
    fun postMessage(message: String) {
      onMessage(message)
    }
  }
}
