/** Lock-screen / Notification Center copy for the persistent bot status alert. */

export const BOT_STATUS_NOTIFICATION_TAG = 'nextrade-bot-status';

export type BotStatusNotificationState = 'live' | 'paused' | 'standby';

export function getBotStatusNotificationState(
  isActive: boolean,
  isPaused: boolean
): BotStatusNotificationState {
  if (!isActive) return 'standby';
  if (isPaused) return 'paused';
  return 'live';
}

export interface BotStatusNotificationContent {
  title: string;
  body: string;
}

export function buildBotStatusNotification(params: {
  botName: string;
  isActive: boolean;
  isPaused: boolean;
}): BotStatusNotificationContent {
  const name = (params.botName || 'Automation').trim();
  const state = getBotStatusNotificationState(params.isActive, params.isPaused);

  switch (state) {
    case 'live':
      return {
        title: name,
        body: 'LIVE · Waiting for active signal',
      };
    case 'paused':
      return {
        title: name,
        body: 'PAUSED · Open NexTradeAI to resume',
      };
    default:
      return {
        title: name,
        body: 'STANDBY · Start automation in the app',
      };
  }
}
