package app.nextradeai.app.overlay

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
    val numberOfTrades =
      signalJson
        .optString("numberOfTrades", "")
        .trim()
        .ifEmpty { config.numberOfTrades }
        .ifEmpty { "1" }
    val executionSymbol = resolveExecutionSymbol(asset, config.symbolMapJson)
    if (executionSymbol.isEmpty()) {
      onStatus("Quote set not found for $asset")
      finish(false, "symbol_not_found")
      return
    }

    val proxyUrl =
      buildTradingProxyUrl(config, executionSymbol, action, sl, tp, volume, numberOfTrades)
    val proxyBase = config.proxyBaseUrl.trimEnd('/')
    Log.i(TAG, "Preparing overlay trade WebView")

    val wv =
      WebView(context).apply {
        setBackgroundColor(Color.TRANSPARENT)
        // Near-zero alpha triggers OEM Freecess freezes mid-trade; keep lightly visible.
        alpha = 0.18f
        keepScreenOn = true
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
          val sanitized = sanitizeTradingProxyHtml(html)
          Log.i(
            TAG,
            "Loading trading-proxy HTML via loadDataWithBaseURL (sanitized=${sanitized.length != html.length})"
          )
          wv.loadDataWithBaseURL("$proxyBase/", sanitized, "text/html", "UTF-8", null)
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
      mainHandler.postDelayed(destroyTask, 2200)
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
      // Alnum match so "#BTCUSD" maps when quotes stored as "BTCUSD" (or reverse).
      val wantAlnum = upper.replace(Regex("[^A-Z0-9]"), "")
      if (wantAlnum.length >= 3) {
        val it = map.keys()
        while (it.hasNext()) {
          val key = it.next()
          val keyAlnum = key.uppercase().replace(Regex("[^A-Z0-9]"), "")
          if (keyAlnum == wantAlnum) {
            val v = map.optString(key, "").trim()
            if (v.isNotEmpty()) return v
          }
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
    numberOfTrades: String,
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
      append("&numberOfTrades=").append(enc(numberOfTrades))
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

  /**
   * Rewrite stale Render trading-proxy shells that click Buy/Sell then sleep +
   * "auto-confirmed" without waiting for the terminal. Align with EA Trade:
   * mouse/touch click + waitOrderAccepted before success.
   */
  private fun sanitizeTradingProxyHtml(html: String): String {
    val hadStale =
      html.contains("BUY order executed") ||
        html.contains("SELL order executed") ||
        html.contains("auto-confirmed") ||
        (html.contains("Confirming trade") && !html.contains("waitOrderAccepted"))

    if (!hadStale) return html

    Log.i(TAG, "Sanitizing stale trading-proxy HTML (forceTradeClick + waitOrderAccepted)")

    var out = html

    // 1) Drop post-fill fake confirm (Confirming… → OK / auto-confirmed).
    // Old HTML uses string concat: 'Confirming trade ' + tradeNumber + '...'
    // and if/else ending in either "confirmed (OK clicked)" or "auto-confirmed".
    out =
      out.replace(
        Regex(
          """sendMessage\(\s*'step_update'\s*,[\s\S]{0,120}?Confirming trade[\s\S]{0,120}?\)\s*;[\s\S]{0,2500}?(?:auto-confirmed|confirmed \(OK clicked\))[\s\S]{0,200}?\)\s*;(?:\s*await sleep\([^)]+\)\s*;)?\s*\}(?:\s*else\s*\{[\s\S]{0,400}?auto-confirmed[\s\S]{0,120}?\)\s*;\s*\})?""",
          RegexOption.MULTILINE
        ),
        "/* overlay: removed stale auto-confirm */"
      )

    val eaClickBlock =
      """
                  const actionLowerRaw = (action || '').trim().toLowerCase();
                  var actionLower = actionLowerRaw.indexOf('sell') >= 0 ? 'sell' : (actionLowerRaw.indexOf('buy') >= 0 ? 'buy' : actionLowerRaw);
                  const forceTradeClick = function(el) {
                    if (!el) return false;
                    try {
                      var rect = el.getBoundingClientRect();
                      var x = rect.left + rect.width / 2;
                      var y = rect.top + rect.height / 2;
                      ['mousedown','mouseup','click'].forEach(function(type) {
                        el.dispatchEvent(new MouseEvent(type, {
                          bubbles: true, cancelable: true, view: window, button: 0,
                          clientX: x, clientY: y, screenX: x, screenY: y
                        }));
                      });
                      try {
                        if (typeof TouchEvent !== 'undefined' && typeof Touch !== 'undefined') {
                          var touch = new Touch({ identifier: 1, target: el, clientX: x, clientY: y, screenX: x, screenY: y, pageX: x, pageY: y });
                          el.dispatchEvent(new TouchEvent('touchstart', { bubbles: true, cancelable: true, touches: [touch], targetTouches: [touch], changedTouches: [touch] }));
                          el.dispatchEvent(new TouchEvent('touchend', { bubbles: true, cancelable: true, touches: [], targetTouches: [], changedTouches: [touch] }));
                        }
                      } catch (eT) {}
                      try { el.click(); } catch (eC) {}
                      return true;
                    } catch (e) { try { el.click(); return true; } catch (e2) { return false; } }
                  };
                  const waitOrderAccepted = async function(tradeNumber) {
                    var deadline = Date.now() + 4500;
                    while (Date.now() < deadline) {
                      var bt = '';
                      try { bt = (document.body && (document.body.innerText || document.body.textContent)) || ''; } catch (e) {}
                      var tail = bt.slice(Math.max(0, bt.length - 1200));
                      if (/not enough money|not enough funds|invalid volume|invalid stops|trade.*(disabled|context|forbidden)|requote|off quotes|market is closed|no prices|common error|request rejected|order rejected/i.test(tail)) {
                        sendMessage('step_update', '❌ Trade ' + tradeNumber + ' rejected by terminal');
                        return false;
                      }
                      var okButton = Array.from(document.querySelectorAll('button')).find(function(btn) {
                        var text = (btn.innerText || btn.textContent || '').trim();
                        if (/^(buy|sell)/i.test(text)) return false;
                        return text === 'OK' || text === 'Ok' || text === 'Done' || text === 'Close';
                      });
                      if (okButton && okButton.offsetParent !== null) {
                        forceTradeClick(okButton);
                        sendMessage('step_update', '✅ Trade ' + tradeNumber + ' confirmed (OK clicked)');
                        await sleep(900);
                        return true;
                      }
                      if (/order.*(placed|executed|done)|deal.*(done|executed)|request.*(executed|accepted|done)|position.*(opened|modified)/i.test(tail)) {
                        sendMessage('step_update', '✅ Trade ' + tradeNumber + ' accepted by terminal');
                        return true;
                      }
                      await sleep(350);
                    }
                    sendMessage('step_update', '⚠️ Trade ' + tradeNumber + ' — no terminal confirmation (will retry)');
                    return false;
                  };
                  
                  if (actionLower === 'buy' && buyButton) {
                    forceTradeClick(buyButton);
                    sendMessage('step_update', '🚀 Trade ' + tradeNumber + '/' + totalTrades + ': BUY submitted');
                  } else if (actionLower === 'sell' && sellButton) {
                    forceTradeClick(sellButton);
                    sendMessage('step_update', '🚀 Trade ' + tradeNumber + '/' + totalTrades + ': SELL submitted');
                  } else {
                    sendMessage('error', '❌ Trade button not found for action: ' + action);
                    return false;
                  }
                  
                  await sleep(600);
                  var accepted = await waitOrderAccepted(tradeNumber);
                  if (!accepted) {
                    var retryBtn = actionLower === 'sell' ? sellButton : buyButton;
                    if (retryBtn) {
                      sendMessage('step_update', 'Retrying ' + actionLower.toUpperCase() + ' click...');
                      forceTradeClick(retryBtn);
                      await sleep(500);
                      accepted = await waitOrderAccepted(tradeNumber);
                    }
                  }
                  if (!accepted) {
                    sendMessage('error', '❌ Trade ' + tradeNumber + ' was not confirmed by the terminal');
                    return false;
                  }
                  return true;
""".trimIndent()

    // 2) Replace buyButton.click() / sellButton.click() + "order executed" + sleep with EA Trade path.
    val staleClick =
      Regex(
        """(?:const|var|let)\s+actionLower\s*=\s*\(action\s*\|\|\s*''\)\.toLowerCase\(\)\s*;[\s\S]*?if\s*\(\s*actionLower\s*===\s*'buy'\s*&&\s*buyButton\s*\)\s*\{[\s\S]*?BUY order executed[\s\S]*?SELL order executed[\s\S]*?Trade button not found[\s\S]*?return false\s*;\s*\}[\s\S]*?await sleep\(\s*\d+\s*\)\s*;[\s\S]*?return true\s*;""",
        setOf(RegexOption.MULTILINE, RegexOption.IGNORE_CASE)
      )

    if (staleClick.containsMatchIn(out)) {
      out = staleClick.replace(out, eaClickBlock)
    } else if (out.contains("BUY order executed") || out.contains("SELL order executed")) {
      // Fallback: rewrite each .click() line, then inject helpers + wait after sell branch.
      out =
        out.replace(
          Regex("""buyButton\.click\(\)\s*;\s*sendMessage\(\s*'step_update'\s*,[\s\S]{0,220}?BUY order executed[\s\S]{0,80}?\)\s*;"""),
          """forceTradeClick(buyButton);
                    sendMessage('step_update', '🚀 Trade ' + tradeNumber + '/' + totalTrades + ': BUY submitted');"""
        )
      out =
        out.replace(
          Regex("""sellButton\.click\(\)\s*;\s*sendMessage\(\s*'step_update'\s*,[\s\S]{0,220}?SELL order executed[\s\S]{0,80}?\)\s*;"""),
          """forceTradeClick(sellButton);
                    sendMessage('step_update', '🚀 Trade ' + tradeNumber + '/' + totalTrades + ': SELL submitted');"""
        )
      out =
        out.replace(
          Regex("""await sleep\(\s*1500\s*\)\s*;\s*return true\s*;"""),
          """await sleep(600);
                  var accepted = await waitOrderAccepted(tradeNumber);
                  if (!accepted) {
                    var retryBtn = actionLower === 'sell' ? sellButton : buyButton;
                    if (retryBtn) {
                      sendMessage('step_update', 'Retrying ' + actionLower.toUpperCase() + ' click...');
                      forceTradeClick(retryBtn);
                      await sleep(500);
                      accepted = await waitOrderAccepted(tradeNumber);
                    }
                  }
                  if (!accepted) {
                    sendMessage('error', '❌ Trade ' + tradeNumber + ' was not confirmed by the terminal');
                    return false;
                  }
                  return true;"""
        )
      if (!out.contains("const forceTradeClick")) {
        val helpers =
          eaClickBlock.substringBefore("if (actionLower === 'buy' && buyButton)")
        out =
          out.replaceFirst(
            Regex("""(?:const|var|let)\s+actionLower\s*=\s*\(action\s*\|\|\s*''\)\.toLowerCase\(\)\s*;"""),
            helpers
          )
      }
    }

    if (
      (out.contains("BUY order executed") || out.contains("SELL order executed") || out.contains("auto-confirmed")) &&
        !out.contains("waitOrderAccepted")
    ) {
      Log.w(TAG, "Sanitizer could not fully rewrite stale trade click path")
    }

    return out
  }

  private class Mt5MessageBridge(private val onMessage: (String) -> Unit) {
    @JavascriptInterface
    fun postMessage(message: String) {
      onMessage(message)
    }
  }
}
