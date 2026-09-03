package app.auraai.app.overlay

import android.app.Service
import android.content.Intent
import android.os.IBinder
import android.util.Log

/**
 * Keeps draw-on-top lifecycle tied to the app task. When the user removes NexTradeAI from
 * recents or the task is cleared, [onTaskRemoved] tears down the overlay (same as bot stop).
 */
class OverlayLifecycleService : Service() {

  companion object {
    private const val TAG = "OverlayLifecycle"
  }

  override fun onBind(intent: Intent?): IBinder? = null

  override fun onTaskRemoved(rootIntent: Intent?) {
    Log.i(TAG, "App task removed — tearing down draw-on-top overlay")
    OverlayWindowModule.teardownGlobal(applicationContext)
    stopSelf()
    super.onTaskRemoved(rootIntent)
  }

  override fun onDestroy() {
    OverlayWindowModule.teardownGlobal(applicationContext)
    super.onDestroy()
  }
}
