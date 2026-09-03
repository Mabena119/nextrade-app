import { NativeModules, Platform, Linking, NativeEventEmitter, EmitterSubscription } from 'react-native';

/** Circular draw-on-top EA logo diameter (px) on Android. */
export const ANDROID_OVERLAY_LOGO_SIZE_PX = 350;

interface OverlayWindowModuleInterface {
  checkOverlayPermission(): Promise<boolean>;
  requestOverlayPermission(): Promise<boolean>;
  openAppNotificationSettings(): Promise<boolean>;
  showOverlay(x: number, y: number, width: number, height: number): Promise<boolean>;
  updateOverlayPosition(x: number, y: number): Promise<boolean>;
  updateOverlaySize(width: number, height: number): Promise<boolean>;
  hideOverlay(): Promise<boolean>;
  getOverlayViewTag(): Promise<number>;
  updateOverlayData(botName: string, isActive: boolean, isPaused: boolean, botImageURL: string | null): Promise<boolean>;
  startNativeBackgroundPolling(licenseKey: string, apiBaseUrl: string, chartWarmupEnabled?: boolean): Promise<boolean>;
  stopNativeBackgroundPolling(): Promise<boolean>;
  consumePendingForegroundAction(): Promise<{ type: string; payload?: string } | null>;
  setLastChartWarmupAt(ms: number): Promise<boolean>;
  showTradeOverlay(symbol: string, action: string, status: string): Promise<boolean>;
  updateTradeOverlayStatus(status: string): Promise<boolean>;
  hideTradeOverlay(): Promise<boolean>;
  isHeadlessTradeActive(): Promise<boolean>;
  startHeadlessTrade(): Promise<boolean>;
  finishHeadlessTrade(): Promise<boolean>;
  setOverlayTradeConfig(configJson: string): Promise<boolean>;
  clearOverlayTradeConfig(): Promise<boolean>;
  executeOverlayTradeFromSignal(payload: string): Promise<boolean>;
  isOverlayTradeActive(): Promise<boolean>;
  markOverlaySignalProcessed(signalId: string): Promise<boolean>;
}

const { OverlayWindowModule } = NativeModules as {
  OverlayWindowModule?: OverlayWindowModuleInterface;
};

interface OverlayService {
  checkOverlayPermission(): Promise<boolean>;
  requestOverlayPermission(): Promise<boolean>;
  openAppNotificationSettings(): Promise<boolean>;
  showOverlay(x: number, y: number, width: number, height: number): Promise<boolean>;
  updateOverlayPosition(x: number, y: number): Promise<boolean>;
  updateOverlaySize(width: number, height: number): Promise<boolean>;
  hideOverlay(): Promise<boolean>;
  getOverlayViewTag(): Promise<number>;
  updateOverlayData(botName: string, isActive: boolean, isPaused: boolean, botImageURL: string | null): Promise<boolean>;
  startNativeBackgroundPolling(licenseKey: string, apiBaseUrl: string, chartWarmupEnabled?: boolean): Promise<boolean>;
  stopNativeBackgroundPolling(): Promise<boolean>;
  consumePendingForegroundAction(): Promise<{ type: string; payload?: string } | null>;
  setLastChartWarmupAt(ms: number): Promise<boolean>;
  showTradeOverlay(symbol: string, action: string, status: string): Promise<boolean>;
  updateTradeOverlayStatus(status: string): Promise<boolean>;
  hideTradeOverlay(): Promise<boolean>;
  isHeadlessTradeActive(): Promise<boolean>;
  startHeadlessTrade(): Promise<boolean>;
  finishHeadlessTrade(): Promise<boolean>;
  setOverlayTradeConfig(configJson: string): Promise<boolean>;
  clearOverlayTradeConfig(): Promise<boolean>;
  executeOverlayTradeFromSignal(payload: string): Promise<boolean>;
  isOverlayTradeActive(): Promise<boolean>;
  markOverlaySignalProcessed(signalId: string): Promise<boolean>;
}

class OverlayService implements OverlayService {
  async checkOverlayPermission(): Promise<boolean> {
    // iOS / web: no Android overlay permission
    if (Platform.OS !== 'android') {
      return true;
    }
    // Native module must report Settings.canDrawOverlays; if missing, do not assume granted
    if (!OverlayWindowModule) {
      console.warn('[OverlayService] OverlayWindowModule not linked — treating draw-on-top as denied');
      return false;
    }
    try {
      return await OverlayWindowModule.checkOverlayPermission();
    } catch (error) {
      console.error('Error checking overlay permission:', error);
      return false;
    }
  }

  async requestOverlayPermission(): Promise<boolean> {
    if (Platform.OS !== 'android') {
      return true;
    }
    if (!OverlayWindowModule) {
      Linking.openSettings();
      return false;
    }

    const hasPermission = await this.checkOverlayPermission();
    if (hasPermission) {
      return true;
    }

    try {
      await OverlayWindowModule.requestOverlayPermission();
      return false;
    } catch (error) {
      console.error('Error requesting overlay permission:', error);
      Linking.openSettings();
      return false;
    }
  }

  /**
   * Android: opens Settings on this app's notification page (not the generic app settings list).
   */
  async openAppNotificationSettings(): Promise<boolean> {
    if (Platform.OS !== 'android') {
      return true;
    }
    if (!OverlayWindowModule) {
      Linking.openSettings();
      return false;
    }
    try {
      await OverlayWindowModule.openAppNotificationSettings();
      return true;
    } catch (e) {
      console.error('[OverlayService] openAppNotificationSettings:', e);
      Linking.openSettings();
      return false;
    }
  }

  async showOverlay(x: number, y: number, width: number, height: number): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) {
      return false;
    }

    const hasPermission = await this.checkOverlayPermission();
    if (!hasPermission) {
      console.log('[OverlayService] Permission not granted, attempting to request...');
      // Silently request permission (opens settings) but don't block
      this.requestOverlayPermission().catch(err => {
        console.error('[OverlayService] Error requesting permission:', err);
      });
      // Still try to show overlay - it might work if user just granted permission
    }

    try {
      console.log('[OverlayService] Calling native showOverlay with:', { x, y, width, height });
      const result = await OverlayWindowModule.showOverlay(x, y, width, height);
      console.log('[OverlayService] Native showOverlay result:', result);
      return result;
    } catch (error) {
      console.error('[OverlayService] Error showing overlay:', error);
      return false;
    }
  }

  async updateOverlayPosition(x: number, y: number): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) {
      return false;
    }
    try {
      return await OverlayWindowModule.updateOverlayPosition(x, y);
    } catch (error) {
      console.error('Error updating overlay position:', error);
      return false;
    }
  }

  async updateOverlaySize(width: number, height: number): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) {
      return false;
    }
    try {
      return await OverlayWindowModule.updateOverlaySize(width, height);
    } catch (error) {
      console.error('Error updating overlay size:', error);
      return false;
    }
  }

  async hideOverlay(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) {
      return false;
    }
    try {
      return await OverlayWindowModule.hideOverlay();
    } catch (error) {
      console.error('Error hiding overlay:', error);
      return false;
    }
  }

  async getOverlayViewTag(): Promise<number> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) {
      return -1;
    }
    try {
      return await OverlayWindowModule.getOverlayViewTag();
    } catch (error) {
      console.error('Error getting overlay view tag:', error);
      return -1;
    }
  }

  async updateOverlayData(botName: string, isActive: boolean, isPaused: boolean, botImageURL: string | null): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) {
      return false;
    }
    try {
      return await OverlayWindowModule.updateOverlayData(botName, isActive, isPaused, botImageURL);
    } catch (error) {
      console.error('Error updating overlay data:', error);
      return false;
    }
  }

  /**
   * Android: while the activity is backgrounded, JS timers may not run — native polls the same APIs.
   * Call stop when returning to foreground; then consumePendingForegroundAction().
   */
  async startNativeBackgroundPolling(
    licenseKey: string,
    apiBaseUrl: string,
    chartWarmupEnabled = true
  ): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) return false;
    try {
      return await OverlayWindowModule.startNativeBackgroundPolling(
        licenseKey,
        apiBaseUrl,
        chartWarmupEnabled
      );
    } catch (e) {
      console.error('[OverlayService] startNativeBackgroundPolling', e);
      return false;
    }
  }

  async stopNativeBackgroundPolling(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) return false;
    try {
      return await OverlayWindowModule.stopNativeBackgroundPolling();
    } catch (e) {
      console.error('[OverlayService] stopNativeBackgroundPolling', e);
      return false;
    }
  }

  async consumePendingForegroundAction(): Promise<{ type: string; payload?: string } | null> {
    if (Platform.OS !== 'android' || !OverlayWindowModule) return null;
    try {
      const r = await OverlayWindowModule.consumePendingForegroundAction();
      if (!r || typeof r !== 'object') return null;
      const type = (r as { type?: string }).type;
      if (!type) return null;
      const payload = (r as { payload?: string }).payload;
      return { type, ...(payload ? { payload } : {}) };
    } catch (e) {
      console.error('[OverlayService] consumePendingForegroundAction', e);
      return null;
    }
  }

  /** Sync JS chart-AI cooldown clock into native bg poll (45 min gate). */
  async setLastChartWarmupAt(ms: number): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.setLastChartWarmupAt) return false;
    try {
      return await OverlayWindowModule.setLastChartWarmupAt(ms);
    } catch (e) {
      console.error('[OverlayService] setLastChartWarmupAt', e);
      return false;
    }
  }

  async showTradeOverlay(symbol: string, action: string, status: string): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.showTradeOverlay) return false;
    try {
      return await OverlayWindowModule.showTradeOverlay(symbol, action, status);
    } catch (e) {
      console.error('[OverlayService] showTradeOverlay', e);
      return false;
    }
  }

  async updateTradeOverlayStatus(status: string): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.updateTradeOverlayStatus) return false;
    try {
      return await OverlayWindowModule.updateTradeOverlayStatus(status);
    } catch (e) {
      console.error('[OverlayService] updateTradeOverlayStatus', e);
      return false;
    }
  }

  async hideTradeOverlay(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.hideTradeOverlay) return false;
    try {
      return await OverlayWindowModule.hideTradeOverlay();
    } catch (e) {
      console.error('[OverlayService] hideTradeOverlay', e);
      return false;
    }
  }

  async isHeadlessTradeActive(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.isHeadlessTradeActive) return false;
    try {
      return await OverlayWindowModule.isHeadlessTradeActive();
    } catch (e) {
      console.error('[OverlayService] isHeadlessTradeActive', e);
      return false;
    }
  }

  async startHeadlessTrade(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.startHeadlessTrade) return false;
    try {
      return await OverlayWindowModule.startHeadlessTrade();
    } catch (e) {
      console.error('[OverlayService] startHeadlessTrade', e);
      return false;
    }
  }

  async finishHeadlessTrade(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.finishHeadlessTrade) return false;
    try {
      return await OverlayWindowModule.finishHeadlessTrade();
    } catch (e) {
      console.error('[OverlayService] finishHeadlessTrade', e);
      return false;
    }
  }

  async setOverlayTradeConfig(config: OverlayTradeConfigPayload): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.setOverlayTradeConfig) return false;
    try {
      return await OverlayWindowModule.setOverlayTradeConfig(JSON.stringify(config));
    } catch (e) {
      console.error('[OverlayService] setOverlayTradeConfig', e);
      return false;
    }
  }

  async clearOverlayTradeConfig(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.clearOverlayTradeConfig) return false;
    try {
      return await OverlayWindowModule.clearOverlayTradeConfig();
    } catch (e) {
      console.error('[OverlayService] clearOverlayTradeConfig', e);
      return false;
    }
  }

  async executeOverlayTradeFromSignal(payload: string): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.executeOverlayTradeFromSignal) return false;
    try {
      try {
        const parsed = JSON.parse(payload) as Array<{ id?: string | number }>;
        const signalId = parsed?.[0]?.id;
        if (signalId != null && String(signalId).trim() !== '') {
          await this.markOverlaySignalProcessed(String(signalId));
        }
      } catch {
        // payload parse optional — native also dedupes by id
      }
      return await OverlayWindowModule.executeOverlayTradeFromSignal(payload);
    } catch (e) {
      console.error('[OverlayService] executeOverlayTradeFromSignal', e);
      return false;
    }
  }

  async markOverlaySignalProcessed(signalId: string): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.markOverlaySignalProcessed) return false;
    const id = signalId.trim();
    if (!id) return false;
    try {
      return await OverlayWindowModule.markOverlaySignalProcessed(id);
    } catch (e) {
      console.error('[OverlayService] markOverlaySignalProcessed', e);
      return false;
    }
  }

  async isOverlayTradeActive(): Promise<boolean> {
    if (Platform.OS !== 'android' || !OverlayWindowModule?.isOverlayTradeActive) return false;
    try {
      return await OverlayWindowModule.isOverlayTradeActive();
    } catch (e) {
      console.error('[OverlayService] isOverlayTradeActive', e);
      return false;
    }
  }
}

export const overlayService = new OverlayService();

export type OverlayTradeConfigPayload = {
  mt5Login: string;
  mt5Password: string;
  mt5Server: string;
  terminalUrl: string;
  brokerKey: string;
  proxyBaseUrl: string;
  robotName: string;
  volume: string;
  numberOfTrades: string;
  symbolMapJson: string;
};

/** Android: native bg poll emits copy-trade signals without bringing the app forward. */
export function addOverlayExecuteListener(
  onPayload: (payload: string) => void
): () => void {
  if (Platform.OS !== 'android' || !OverlayWindowModule) {
    return () => {};
  }
  const emitter = new NativeEventEmitter(OverlayWindowModule);
  const sub: EmitterSubscription = emitter.addListener(
    'EaOverlayExecuteSignal',
    (event?: { payload?: string }) => {
      const payload = event?.payload;
      if (typeof payload === 'string' && payload.trim()) {
        onPayload(payload);
      }
    }
  );
  return () => sub.remove();
}

/** Android: overlay WebView trade finished in background (mark executed + resume polling). */
export function addOverlayTradeCompletedListener(
  onResult: (result: { signalId: string; asset: string; success: boolean; message: string }) => void
): () => void {
  if (Platform.OS !== 'android' || !OverlayWindowModule) {
    return () => {};
  }
  const emitter = new NativeEventEmitter(OverlayWindowModule);
  const sub: EmitterSubscription = emitter.addListener(
    'EaOverlayTradeCompleted',
    (event?: { signalId?: string; asset?: string; success?: boolean; message?: string }) => {
      onResult({
        signalId: String(event?.signalId ?? ''),
        asset: String(event?.asset ?? ''),
        success: Boolean(event?.success),
        message: String(event?.message ?? ''),
      });
    }
  );
  return () => sub.remove();
}

/** Android: native overlay WebView started a background trade — pause JS polling. */
export function addOverlayTradeStartedListener(onStarted: () => void): () => void {
  if (Platform.OS !== 'android' || !OverlayWindowModule) {
    return () => {};
  }
  const emitter = new NativeEventEmitter(OverlayWindowModule);
  const sub: EmitterSubscription = emitter.addListener('EaOverlayTradeStarted', () => {
    onStarted();
  });
  return () => sub.remove();
}

