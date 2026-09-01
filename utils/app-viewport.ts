import { Platform } from 'react-native';

/** Wide web / desktop PWA (Safari Dock, Chrome install-as-app). */
export const DESKTOP_WEB_BREAKPOINT = 768;

export function isDesktopWebLayout(windowWidth: number): boolean {
  return Platform.OS === 'web' && windowWidth >= DESKTOP_WEB_BREAKPOINT;
}

/** Phone-ratio height for native + mobile web. */
export function getAppLayoutWidth(windowWidth: number): number {
  return windowWidth;
}

/**
 * EA Trade-style desktop banner: tall enough to show a portrait figure
 * centered in a full-width card (contain), without cropping the face.
 */
export function getHeroCardMinHeight(windowWidth: number, windowHeight: number): number {
  if (isDesktopWebLayout(windowWidth)) {
    return Math.round(Math.min(Math.max(windowHeight * 0.62, 460), 720));
  }
  return Math.round(windowWidth * 1.05);
}

export function getHeroSpacerMinHeight(windowWidth: number, windowHeight: number): number {
  if (isDesktopWebLayout(windowWidth)) {
    return Math.round(getHeroCardMinHeight(windowWidth, windowHeight) * 0.36);
  }
  return Math.round(windowWidth * 0.42);
}
