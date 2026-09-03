package app.auraai.app

import android.content.Intent
import android.graphics.Color
import android.os.Build
import android.os.Bundle
import android.view.WindowManager
import expo.modules.splashscreen.SplashScreenManager
import com.facebook.react.ReactActivity
import com.facebook.react.ReactActivityDelegate
import com.facebook.react.defaults.DefaultNewArchitectureEntryPoint.fabricEnabled
import com.facebook.react.defaults.DefaultReactActivityDelegate
import app.auraai.app.overlay.OverlayWindowModule
import expo.modules.ReactActivityDelegateWrapper

class MainActivity : ReactActivity() {

  companion object {
    const val EXTRA_HEADLESS_TRADE = "headless_trade"
    @JvmField
    var headlessTradeActive: Boolean = false
  }

  override fun onCreate(savedInstanceState: Bundle?) {
    if (intent?.getBooleanExtra(EXTRA_HEADLESS_TRADE, false) == true) {
      setTheme(R.style.Theme_HeadlessTrade)
    }
    SplashScreenManager.registerOnActivity(this)
    super.onCreate(null)
    applyHeadlessModeIfNeeded(intent)
  }

  override fun onNewIntent(intent: Intent) {
    super.onNewIntent(intent)
    setIntent(intent)
    applyHeadlessModeIfNeeded(intent)
  }

  override fun onResume() {
    super.onResume()
    if (headlessTradeActive) {
      applyHeadlessWindowChrome()
    }
  }

  private fun applyHeadlessModeIfNeeded(intent: Intent?) {
    if (intent?.getBooleanExtra(EXTRA_HEADLESS_TRADE, false) != true) {
      return
    }
    headlessTradeActive = true
    applyHeadlessWindowChrome()
  }

  private fun applyHeadlessWindowChrome() {
    window.setBackgroundDrawableResource(android.R.color.transparent)
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
      window.statusBarColor = Color.TRANSPARENT
      window.navigationBarColor = Color.TRANSPARENT
    }
    window.addFlags(WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS)
    window.decorView.setBackgroundColor(Color.TRANSPARENT)
    window.decorView.alpha = 0f
    // Keep activity resumed for WebView automation while minimizing visible takeover.
    window.setLayout(1, 1)
    overridePendingTransition(0, 0)
  }

  fun exitHeadlessTrade() {
    headlessTradeActive = false
    window.decorView.alpha = 1f
    window.setLayout(
      WindowManager.LayoutParams.MATCH_PARENT,
      WindowManager.LayoutParams.MATCH_PARENT
    )
    moveTaskToBack(true)
  }

  override fun getMainComponentName(): String = "main"

  override fun createReactActivityDelegate(): ReactActivityDelegate {
    return ReactActivityDelegateWrapper(
      this,
      BuildConfig.IS_NEW_ARCHITECTURE_ENABLED,
      object : DefaultReactActivityDelegate(
        this,
        mainComponentName,
        fabricEnabled
      ) {}
    )
  }

  override fun onDestroy() {
    if (isFinishing) {
      OverlayWindowModule.teardownGlobal(applicationContext)
    }
    super.onDestroy()
  }

  override fun invokeDefaultOnBackPressed() {
    if (headlessTradeActive) {
      exitHeadlessTrade()
      return
    }
    if (Build.VERSION.SDK_INT <= Build.VERSION_CODES.R) {
      if (!moveTaskToBack(false)) {
        super.invokeDefaultOnBackPressed()
      }
      return
    }
    super.invokeDefaultOnBackPressed()
  }
}
