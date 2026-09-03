import React, { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Platform, Dimensions, AppState, Modal, ActivityIndicator } from 'react-native';
import { Plus, ChevronRight } from 'lucide-react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Notifications from 'expo-notifications';
import { router, useFocusEffect } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';

import { useApp, type EA } from '@/providers/app-provider';
import { getScreenBackgroundColor, useTheme } from '@/providers/theme-provider';
import { FirstTimeWelcome } from '@/components/aura/FirstTimeWelcome';
import { HomeWorkspaceHero } from '@/components/aura/HomeWorkspaceHero';
import { getHeroCardMinHeight } from '@/utils/app-viewport';
import { BotModuleCard } from '@/components/aura/BotModuleCard';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { overlayService } from '@/services/overlay-service';

// Must match NATIVE_TAB_BAR_HEIGHT in _layout.tsx
const TAB_BAR_HEIGHT = 56;

export default function HomeScreen() {
  const { eas, isFirstTime, setIsFirstTime, removeEA, isBotActive, setBotActive, setActiveEA, mt5Account, primaryLicenseStatus, refreshPrimaryEaProfile } = useApp();
  const { theme, toggleTheme } = useTheme();
  const scrollRef = useRef<ScrollView>(null);
  const insets = useSafeAreaInsets();

  // Safely get the primary EA (first one in the list)
  const primaryEA = Array.isArray(eas) && eas.length > 0 ? eas[0] : null;
  const licenseExpired = primaryLicenseStatus === 'expired';
  const otherEAs = Array.isArray(eas) ? eas.slice(1) : []; // All EAs except the first one

  console.log('HomeScreen render - EAs count:', eas?.length || 0, 'Primary EA:', primaryEA?.name || 'none');

  const [hasCheckedAuth, setHasCheckedAuth] = useState<boolean>(false);
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);

  /** Android start screen: draw-over-apps + notifications required before START. */
  const [androidOverlayGranted, setAndroidOverlayGranted] = useState<boolean>(() => Platform.OS !== 'android');
  const [androidNotificationGranted, setAndroidNotificationGranted] = useState<boolean>(() => Platform.OS !== 'android');
  const [removeConfirmVisible, setRemoveConfirmVisible] = useState<boolean>(false);
  const [isRemovingBot, setIsRemovingBot] = useState<boolean>(false);
  const [removeError, setRemoveError] = useState<string | null>(null);

  const refreshAndroidStartPermissions = useCallback(async (): Promise<{
    overlay: boolean;
    notification: boolean;
  }> => {
    if (Platform.OS !== 'android') {
      return { overlay: true, notification: true };
    }
    try {
      const { status } = await Notifications.getPermissionsAsync();
      const notificationOk = status === 'granted';
      const { overlayService } = await import('@/services/overlay-service');
      const overlayOk = await overlayService.checkOverlayPermission();
      setAndroidNotificationGranted(notificationOk);
      setAndroidOverlayGranted(overlayOk);
      return { overlay: overlayOk, notification: notificationOk };
    } catch (e) {
      console.warn('[Start] permission sync:', e);
      setAndroidNotificationGranted(false);
      setAndroidOverlayGranted(false);
      return { overlay: false, notification: false };
    }
  }, []);

  useEffect(() => {
    if (!isFirstTime || Platform.OS !== 'android') return;
    void refreshAndroidStartPermissions();
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') void refreshAndroidStartPermissions();
    });
    return () => sub.remove();
  }, [isFirstTime, refreshAndroidStartPermissions]);

  // Triple-tap to toggle theme (backup if motion permission is denied on iOS)
  const tapCountRef = useRef<number>(0);
  const lastTapTimeRef = useRef<number>(0);
  const TRIPLE_TAP_DELAY = 400; // ms between taps

  const handleLogoTap = useCallback(() => {
    const now = Date.now();
    if (now - lastTapTimeRef.current < TRIPLE_TAP_DELAY) {
      tapCountRef.current += 1;
      if (tapCountRef.current >= 3) {
        console.log('🎨 Triple-tap detected! Toggling theme...');
        toggleTheme();
        tapCountRef.current = 0;
      }
    } else {
      tapCountRef.current = 1;
    }
    lastTapTimeRef.current = now;
  }, [toggleTheme]);

  // STRICT authentication check - runs on every mount
  useEffect(() => {
    const checkAuthenticationStatus = async () => {
      try {
        const emailAuthenticated = await AsyncStorage.getItem('emailAuthenticated');

        // If first time, show start page (don't redirect)
        if (isFirstTime) {
          console.log('First time user - showing start page');
          setIsAuthenticated(true); // Allow start page to render
          setHasCheckedAuth(true);
          return;
        }

        // If not authenticated and not first time, redirect to login
        if (!emailAuthenticated || emailAuthenticated !== 'true') {
          console.log('❌ Not authenticated - redirecting to login');
          setIsAuthenticated(false);
          router.replace('/login');
          return;
        }

        // Authenticated
        console.log('✅ Authenticated - checking EA status');
        setIsAuthenticated(true);

        // If authenticated but no EAs, redirect to license immediately
        if (eas.length === 0) {
          console.log('Authenticated but no EA added, redirecting to license...');
          // Don't render home screen, go straight to license
          router.replace('/license');
          return; // Stop here, don't set hasCheckedAuth
        }

        setHasCheckedAuth(true);
      } catch (error) {
        console.error('Error checking authentication status:', error);
        // On error, show start page if first time, otherwise redirect to login
        if (isFirstTime) {
          setIsAuthenticated(true);
        } else {
          setIsAuthenticated(false);
          router.replace('/login');
        }
      }
    };

    checkAuthenticationStatus();
  }, [isFirstTime, eas.length]); // Re-run when isFirstTime or eas changes

  useFocusEffect(
    useCallback(() => {
      void refreshPrimaryEaProfile();
      requestAnimationFrame(() => {
        scrollRef.current?.scrollTo({ y: 0, animated: false });
      });
    }, [refreshPrimaryEaProfile])
  );

  const primaryEAOwnerLogo = useMemo(
    () => (primaryEA?.userData?.owner?.logo ?? '').trim() || null,
    [primaryEA?.userData?.owner?.logo]
  );

  const getEAOwnerLogo = useCallback((ea: EA | null): string | null => {
    if (!ea?.userData?.owner) return null;
    return (ea.userData.owner.logo ?? '').trim() || null;
  }, []);

  const handleStartNow = async () => {
    try {
      if (Platform.OS === 'android') {
        const { overlay, notification } = await refreshAndroidStartPermissions();
        if (!overlay) {
          await overlayService.requestOverlayPermission();
          return;
        }
        if (!notification) {
          await overlayService.openAppNotificationSettings();
          return;
        }
      }
      console.log('Start Now pressed, navigating to login...');
      // Clear email authentication flag when starting fresh
      await AsyncStorage.removeItem('emailAuthenticated');
      router.replace('/login');
    } catch (error) {
      console.error('Error navigating to login:', error);
    }
  };

  const handleAddNewEA = () => {
    router.push('/license');
  };

  const performRemoveActiveBot = async () => {
    if (!primaryEA?.id || isRemovingBot) return;

    setIsRemovingBot(true);
    setRemoveError(null);
    try {
      console.log('Removing EA:', primaryEA.name, primaryEA.id);
      const success = await removeEA(primaryEA.id);
      if (success) {
        console.log('EA removed successfully, navigating to license screen');
        setRemoveConfirmVisible(false);
        router.push('/license');
      } else {
        console.error('Failed to remove EA');
        setRemoveError('Could not remove this automation. Please try again.');
      }
    } catch (error) {
      console.error('Error removing EA:', error);
      setRemoveError('Something went wrong while removing this automation.');
    } finally {
      setIsRemovingBot(false);
    }
  };

  const handleRemoveActiveBot = () => {
    if (!primaryEA?.id) return;
    setRemoveError(null);
    setRemoveConfirmVisible(true);
  };

  const closeRemoveConfirm = () => {
    if (isRemovingBot) return;
    setRemoveConfirmVisible(false);
    setRemoveError(null);
  };

  const handleAIScanner = () => {
    router.push('/(tabs)/ai-scanner');
  };

  const handleQuotes = () => {
    const hasMt5Linked = Boolean(
      mt5Account &&
      typeof mt5Account.login === 'string' &&
      mt5Account.login.trim().length > 0 &&
      mt5Account.password
    );
    if (!hasMt5Linked) {
      router.push('/(tabs)/metatrader');
      return;
    }
    router.push('/(tabs)/quotes');
  };



  const screenBg = getScreenBackgroundColor(theme);
  /** Opaque dark fallback — never flash system white. */
  const safeScreenBg =
    screenBg === 'transparent' || !screenBg ? '#000000' : screenBg;

  // Block rendering if not authenticated
  if (!isAuthenticated) {
    return (
      <View style={[styles.splashContainer, { backgroundColor: safeScreenBg }]}>
        <View style={styles.splashContent}>
          <Text style={[styles.title, { color: theme.colors.textPrimary }]}>Checking authentication...</Text>
        </View>
      </View>
    );
  }

  if (isFirstTime) {
    return (
      <FirstTimeWelcome
        onStart={handleStartNow}
        androidOverlayGranted={androidOverlayGranted}
        androidNotificationGranted={androidNotificationGranted}
      />
    );
  }

  // If no EA, don't render (should have been redirected to license)
  if (!primaryEA) {
    return (
      <View style={[styles.splashContainer, { backgroundColor: safeScreenBg }]}>
        <View style={styles.splashContent}>
          <Text style={[styles.title, { color: theme.colors.textPrimary }]}>Loading...</Text>
        </View>
      </View>
    );
  }

  return (
    <SafeAreaView
      style={[styles.container, { backgroundColor: safeScreenBg }]}
      edges={Platform.OS === 'android' ? ['top', 'right', 'left'] : ['top', 'right', 'left']}
    >
      <ScrollView
        ref={scrollRef}
        style={styles.scroll}
        contentContainerStyle={[
          styles.scrollContent,
          // Leave room for the fixed homeFooter + tab bar so last card isn't hidden
          Platform.OS !== 'web' ? { paddingBottom: insets.bottom + TAB_BAR_HEIGHT + 90 } : null,
        ]}
        showsVerticalScrollIndicator={false}
        bounces
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
        automaticallyAdjustKeyboardInsets={false}
        automaticallyAdjustContentInsets={false}
        contentInsetAdjustmentBehavior="never"
      >
        <HomeWorkspaceHero
          name={primaryEA.name}
          ownerName={
            (primaryEA.userData?.owner as { name?: string } | undefined)?.name ||
            primaryEA.description ||
            null
          }
          ownerLogo={primaryEAOwnerLogo}
          isBotActive={isBotActive}
          licenseExpired={licenseExpired}
          onLogoTap={handleLogoTap}
          onToggleBot={() => {
            try {
              setBotActive(!isBotActive);
            } catch (error) {
              console.error('Error changing bot state:', error);
            }
          }}
          onQuotes={handleQuotes}
          onRemove={handleRemoveActiveBot}
        />

        <View style={styles.listSection}>
          {otherEAs.length > 0 && (
            <>
              <View testID="connected-bots-header" style={styles.sectionHeader}>
                <Text style={[styles.sectionTitle, { color: theme.colors.textMuted }]}>Switch automation</Text>
                <View testID="connected-bots-count" style={[styles.sectionBadge, { borderColor: authColors.cardBorder }]}>
                  <Text style={[styles.sectionBadgeText, { color: theme.colors.textPrimary }]}>{eas.length}</Text>
                </View>
              </View>
              {otherEAs.map((ea, index) => (
                <BotModuleCard
                  key={`${ea.id}-${index}`}
                  testID={`ea-module-${index}`}
                  name={ea.name}
                  ownerLogo={getEAOwnerLogo(ea as unknown as EA)}
                  ownerName={
                    (ea.userData?.owner as { name?: string } | undefined)?.name ||
                    ea.description ||
                    null
                  }
                  index={index}
                  onPress={async () => {
                    try {
                      await setActiveEA(ea.id);
                    } catch (error) {
                      console.error('Failed to switch active EA:', error);
                    }
                  }}
                />
              ))}
            </>
          )}
        </View>
      </ScrollView>

      <View style={[styles.homeFooter, Platform.OS !== 'web' && { paddingBottom: insets.bottom + TAB_BAR_HEIGHT + 24 }]}>
        <TouchableOpacity
          testID="action-link-automation"
          style={[
            styles.addEAButton,
            {
              borderColor: theme.colors.accent,
              backgroundColor: theme.colors.accent,
            },
          ]}
          onPress={handleAddNewEA}
          activeOpacity={0.88}
        >
          <View style={[styles.addIconWrap, { backgroundColor: `${theme.colors.onAccent}18` }]}>
            <Plus color={theme.colors.onAccent} size={18} strokeWidth={2.2} />
          </View>
          <View style={styles.addEATextContainer}>
            <Text style={[styles.addEATitle, { color: theme.colors.onAccent }]}>Link automation</Text>
            <Text style={[styles.addEASubtitle, { color: `${theme.colors.onAccent}B8` }]}>
              Add another automation key
            </Text>
          </View>
          <ChevronRight color={`${theme.colors.onAccent}CC`} size={18} strokeWidth={1.8} />
        </TouchableOpacity>
      </View>

      <Modal
        visible={removeConfirmVisible}
        transparent
        animationType="fade"
        onRequestClose={closeRemoveConfirm}
      >
        <View style={styles.removeModalOverlay}>
          <View style={styles.removeModalCard}>
            <Text style={styles.removeModalTitle}>Remove automation?</Text>
            <Text style={styles.removeModalMessage}>
              Remove {primaryEA?.name?.trim() || 'this automation'}? You will need the license key to add it back.
            </Text>
            {removeError ? <Text style={styles.removeModalError}>{removeError}</Text> : null}
            <View style={styles.removeModalActions}>
              <TouchableOpacity
                style={[styles.removeModalButton, styles.removeModalCancelButton]}
                onPress={closeRemoveConfirm}
                disabled={isRemovingBot}
                activeOpacity={0.8}
              >
                <Text style={styles.removeModalButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.removeModalButton, styles.removeModalDeleteButton]}
                onPress={() => {
                  void performRemoveActiveBot();
                }}
                disabled={isRemovingBot}
                activeOpacity={0.8}
              >
                {isRemovingBot ? (
                  <ActivityIndicator size="small" color="#FFFFFF" />
                ) : (
                  <Text style={styles.removeModalDeleteText}>Remove</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const { width: windowWidth, height: windowHeight } = Dimensions.get('window');
const width = windowWidth;

/** sRGB luminance for #RRGGBB; null if not a 6-digit hex (e.g. `transparent`). */
function hexBackgroundLuminance(hex: string): number | null {
  const h = hex.replace(/^#/, '').trim();
  if (h.length !== 6 || !/^[0-9a-fA-F]{6}$/.test(h)) return null;
  const r = parseInt(h.slice(0, 2), 16) / 255;
  const g = parseInt(h.slice(2, 4), 16) / 255;
  const b = parseInt(h.slice(4, 6), 16) / 255;
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Floor for how tall the black hero poster card should feel (ratio of screen width). */
const BLACK_HERO_CARD_MIN_HEIGHT = getHeroCardMinHeight(windowWidth, windowHeight);
/** Thin strip so content never kisses the rounded top edge on rotation / large text */
const BLACK_HERO_TOP_ART_FLOOR = Math.round(windowWidth * 0.04);

const styles = StyleSheet.create({
  splashContainer: {
    flex: 1,
    backgroundColor: '#000000',
  },
  splashContent: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 24,
  },
  logoContainer: {
    alignItems: 'center',
    marginBottom: 28,
  },
  startPermissionPanel: {
    width: '100%',
    maxWidth: 340,
    marginBottom: 28,
  },
  startPermissionHint: {
    fontSize: 13,
    lineHeight: 18,
    textAlign: 'center',
    paddingHorizontal: 8,
    marginBottom: 4,
  },
  startPermissionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 14,
    borderWidth: 1,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  startPermissionMark: {
    fontSize: 18,
    fontWeight: '800',
    width: 28,
  },
  startPermissionLabel: {
    flex: 1,
    fontSize: 15,
    fontWeight: '600',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#FFFFFF',
    marginTop: 16,
    letterSpacing: 2,
  },
  description: {
    fontSize: 14,
    color: '#CCCCCC',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 60,
    paddingHorizontal: 20,
  },
  splashStartButton: {
    paddingHorizontal: 64,
    paddingVertical: 18,
    borderRadius: 28,
    minWidth: 220,
    borderWidth: 1,
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.5,
    shadowRadius: 20,
    elevation: 12,
  },
  startButtonText: {
    color: '#FFFFFF',
    fontSize: 17,
    fontWeight: '800',
    textAlign: 'center',
    letterSpacing: 0.8,
  },
  container: {
    flex: 1,
    backgroundColor: authColors.bg,
    position: 'relative',
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    paddingTop: Platform.OS === 'android' ? 8 : 12,
    paddingBottom: 16,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  homeFooter: {
    paddingHorizontal: 24,
    paddingTop: 8,
    paddingBottom: 8,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  listSection: {
    paddingHorizontal: 24,
    paddingTop: 4,
  },
  glassHomeVideo: {
    width: '100%',
    height: '100%',
  },
  pageGlossTop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 200,
    zIndex: 100,
  },
  pageGlossBottom: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    height: 100,
    zIndex: 100,
  },
  content: {
    flex: 1,
    backgroundColor: 'transparent',
  },
  mainEAContainer: {
    paddingTop: 0,
    paddingBottom: 16,
    backgroundColor: 'transparent',
  },
  heroLicenseShell: {
    position: 'relative',
  },
  /** Softens hero → scroll transition on very dark themes (no white gloss in that case). */
  chromeFalloffStrip: {
    height: 48,
    width: '100%',
    overflow: 'hidden',
    zIndex: 5,
  },
  gradientBackground: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    borderRadius: 40,
    zIndex: 0,
    opacity: 0.9,
  },
  glossShine: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 120,
    borderTopLeftRadius: 40,
    borderTopRightRadius: 40,
    zIndex: 2,
  },
  heroContentBlackFullBleed: {
    paddingTop: 0,
    paddingHorizontal: 0,
  },
  /** Full-card cover art; parent height follows in-flow foreground. */
  blackHeroFullBleedMedia: {
    ...StyleSheet.absoluteFillObject,
    zIndex: 3,
    overflow: 'hidden',
  },
  blackHeroControlsScrim: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '46%',
  },
  blackHeroFullBleedImage: {
    ...StyleSheet.absoluteFillObject,
    width: '100%',
    height: '100%',
  },
  /** Scrim: lower portion of card (image spans full hero). */
  blackHeroBloomHost: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    top: '8%',
  },
  blackHeroBloomGradient: {
    flex: 1,
  },
  blackHeroTopVeil: {
    position: 'absolute',
    left: 0,
    right: 0,
    top: 0,
    height: '44%',
  },
  blackHeroEdgeWhisper: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.42,
  },
  blackHeroBloomHighlight: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '22%',
    opacity: 0.32,
  },
  blackHeroForeground: {
    flexDirection: 'column',
    position: 'relative',
    zIndex: 8,
    width: '100%',
    paddingBottom: 10,
  },
  /** Eats leftover height so robot name + controls sit toward the bottom of the card */
  blackHeroTopFlexSpacer: {
    flex: 1,
    minHeight: BLACK_HERO_TOP_ART_FLOOR,
    width: '100%',
  },
  blackHeroTitleWrap: {
    flexShrink: 0,
    marginBottom: 8,
    paddingHorizontal: 20,
  },
  blackHeroBottomActions: {
    flexShrink: 0,
    paddingHorizontal: 20,
    paddingTop: 2,
    marginTop: 0,
    marginBottom: 2,
  },
  botMainNameBlackHero: {
    paddingHorizontal: 8,
    lineHeight: 31,
    textShadowColor: 'rgba(0,0,0,0.75)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 16,
  },
  circularLogoContainer: {
    width: 180,
    height: 180,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  circularLogoRing: {
    width: 165,
    height: 165,
    borderRadius: 82.5,
    overflow: 'hidden',
    backgroundColor: 'rgba(255, 255, 255, 0.15)',
    borderWidth: 3,
    borderColor: 'rgba(255, 255, 255, 0.3)',
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.3,
    shadowRadius: 24,
    elevation: 12,
  },
  circularLogo: {
    width: 140,
    height: 140,
    borderRadius: 70,
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
  },
  /** Image+video stack clips to circle / rounded square */
  eaProfileMediaClip: {
    overflow: 'hidden',
    position: 'relative',
  },
  botInfoContainer: {
    alignItems: 'center',
    marginTop: 20,
    marginBottom: 40,
  },
  heroContent: {
    marginHorizontal: 16,
    marginTop: 12,
    borderRadius: 28,
    overflow: 'hidden',
    justifyContent: 'space-between',
    paddingTop: 24,
    paddingBottom: 22,
    zIndex: 10,
    shadowOffset: { width: 0, height: 16 },
    shadowOpacity: 0.45,
    shadowRadius: 40,
    elevation: 24,
    borderWidth: 1,
    borderTopWidth: 1.5,
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderTopColor: 'rgba(255, 255, 255, 0.28)',
  },
  topSection: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingTop: 12,
    zIndex: 4,
  },

  titleBlock: {
    alignItems: 'center',
  },
  botNameContainer: {
    position: 'relative',
    alignItems: 'center',
    justifyContent: 'center',
  },
  botMainName: {
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: '800',
    letterSpacing: 0.5,
    marginBottom: 6,
    textShadowColor: 'rgba(0, 0, 0, 0.3)',
    textShadowOffset: { width: 0, height: 2 },
    textShadowRadius: 8,
    textAlign: 'center',
    flexWrap: 'wrap',
    paddingHorizontal: 24,
    lineHeight: 30,
  },
  botStatusDot: {
    position: 'absolute',
    top: -8,
    right: -12,
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 3,
    borderColor: '#FFFFFF',
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 6,
  },
  botStatusDotActive: {
    backgroundColor: '#25D366',
    shadowColor: '#25D366',
  },
  botStatusDotInactive: {
    backgroundColor: '#DC2626',
    shadowColor: '#DC2626',
  },
  botDescription: {
    color: '#CCCCCC',
    fontSize: 14,
    fontWeight: '500',
    letterSpacing: 0.5,
  },

  connectedCountBadge: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.3)',
    marginBottom: 12,
  },
  connectedCountText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '600',
    letterSpacing: 0.5,
  },
  bottomActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    gap: 8,
    marginTop: 12,
    zIndex: 4,
  },
  expiredRemoveButton: {
    zIndex: 40,
    elevation: 40,
  },
  removeModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.72)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  removeModalCard: {
    width: '100%',
    maxWidth: 360,
    borderRadius: 20,
    padding: 22,
    borderWidth: 1,
    borderColor: authColors.cardBorder,
    backgroundColor: authColors.card,
  },
  removeModalTitle: {
    ...type.title,
    fontSize: 20,
    marginBottom: 10,
    textAlign: 'center',
    color: '#FFFFFF',
  },
  removeModalMessage: {
    ...type.body,
    fontSize: 14,
    lineHeight: 21,
    textAlign: 'center',
    marginBottom: 8,
    color: '#9AA7B5',
  },
  removeModalError: {
    color: '#FF5C7A',
    fontSize: 13,
    textAlign: 'center',
    marginBottom: 8,
  },
  removeModalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 16,
  },
  removeModalButton: {
    flex: 1,
    minHeight: 48,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 12,
  },
  removeModalCancelButton: {
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  removeModalDeleteButton: {
    backgroundColor: 'rgba(255, 92, 122, 0.2)',
    borderWidth: 1,
    borderColor: 'rgba(255, 92, 122, 0.4)',
  },
  removeModalButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  removeModalDeleteText: {
    color: '#FF5C7A',
    fontSize: 15,
    fontWeight: '600',
  },
  actionButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'column',
    gap: 4,
    overflow: 'hidden',
    borderWidth: 0,
  },
  tradeButton: {
    flex: 1,
    backgroundColor: 'transparent',
    paddingVertical: 16,
    position: 'relative',
  },
  tradeButtonContent: {
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    zIndex: 1,
    position: 'relative',
  },
  secondaryButton: {
    backgroundColor: 'transparent',
    position: 'relative',
  },
  secondaryButtonContent: {
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    zIndex: 1,
    position: 'relative',
  },
  tradeButtonText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.5,
    textTransform: 'uppercase',
  },
  secondaryButtonText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '600',
    letterSpacing: 0.5,
    textTransform: 'uppercase',
  },
  connectedBotsWrapper: {
    flex: 1,
    position: 'relative',
    backgroundColor: 'transparent',
  },
  sectionGloss: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 150,
    zIndex: 1,
  },
  connectedBotsScrollView: {
    flex: 1,
    backgroundColor: 'transparent',
  },
  connectedBotsScrollContent: {
    paddingBottom: 100,
    backgroundColor: 'transparent',
  },
  connectedBotsSection: {
    paddingHorizontal: 20,
    paddingTop: 16,
    backgroundColor: 'transparent',
    overflow: 'hidden',
    zIndex: 10,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  sectionTitle: {
    ...type.label,
  },
  sectionBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
    backgroundColor: authColors.inputBg,
    minWidth: 32,
    alignItems: 'center',
  },
  sectionBadgeText: {
    ...type.caption,
    fontFamily: type.bodyMedium.fontFamily,
  },
  botCard: {
    borderRadius: 20,
    marginBottom: 14,
    overflow: 'hidden',
    borderWidth: 1,
    borderTopWidth: 1.5,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.28,
    shadowRadius: 18,
    elevation: 8,
  },
  botCardContent: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 20,
    paddingHorizontal: 24,
  },
  botIcon: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 16,
    overflow: 'hidden',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 4,
  },
  smallLogo: {
    width: 56,
    height: 56,
    borderRadius: 16,
  },
  robotFace: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  robotEye: {
    width: 4,
    height: 4,
    borderRadius: 2,
    backgroundColor: '#000000',
    marginHorizontal: 2,
  },
  botName: {
    color: '#FFFFFF',
    fontSize: 17,
    fontWeight: '700',
    flex: 1,
    flexWrap: 'wrap',
    numberOfLines: 2,
    textAlign: 'left',
    letterSpacing: 0.3,
  },
  addEAButton: {
    borderRadius: 24,
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderWidth: 1.5,
    gap: 12,
  },
  addIconWrap: {
    width: 40,
    height: 40,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addEATextContainer: {
    flex: 1,
  },
  addEATitle: {
    ...type.title,
    fontSize: 16,
    letterSpacing: -0.2,
  },
  addEASubtitle: {
    ...type.caption,
    marginTop: 3,
  },
});
