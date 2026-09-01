import React, { useEffect, useMemo, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Animated,
  Easing,
  Platform,
  useWindowDimensions,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import { Play, Square, Activity, Trash2 } from 'lucide-react-native';
import { LuxPulse } from '@/components/aura';
import { lux } from '@/constants/aura-ui';
import { useTheme } from '@/providers/theme-provider';
import { EABrandProfileMedia } from '@/components/ea-brand-profile-media';
import { LicenseBlockedOverlay } from '@/components/license-blocked-overlay';
import { getHeroFullBleedFade } from '@/utils/theme-hero-fades';
import {
  getHeroCardMinHeight,
  getHeroSpacerMinHeight,
  isDesktopWebLayout,
} from '@/utils/app-viewport';

type Props = {
  name: string;
  ownerName?: string | null;
  imageUrl?: string | null;
  logoError: boolean;
  onPhotoError: () => void;
  isBotActive: boolean;
  licenseExpired: boolean;
  onLogoTap: () => void;
  onToggleBot: () => void;
  onQuotes: () => void;
  onRemove: () => void;
};

export function HomeWorkspaceHero({
  name,
  ownerName,
  imageUrl,
  logoError,
  onPhotoError,
  isBotActive,
  licenseExpired,
  onLogoTap,
  onToggleBot,
  onQuotes,
  onRemove,
}: Props) {
  const { width: windowWidth, height: windowHeight } = useWindowDimensions();
  const desktop = isDesktopWebLayout(windowWidth);
  const cardMinH = getHeroCardMinHeight(windowWidth, windowHeight);
  const spacerMinH = getHeroSpacerMinHeight(windowWidth, windowHeight);
  const heroFit = desktop ? 'contain' : 'cover';
  const { theme } = useTheme();
  const accent = theme.colors.accent;
  const accentSoft = theme.colors.accentSecondary;
  const isMinimal = theme.minimalHero === true;
  const heroBleedFade = useMemo(
    () => getHeroFullBleedFade(theme, { isMinimal }),
    [theme, isMinimal]
  );

  const floatY = useRef(new Animated.Value(0)).current;
  const borderGlow = useRef(new Animated.Value(0.35)).current;

  useEffect(() => {
    const float = Animated.loop(
      Animated.sequence([
        Animated.timing(floatY, {
          toValue: -5,
          duration: 2600,
          easing: Easing.inOut(Easing.sin),
          useNativeDriver: true,
        }),
        Animated.timing(floatY, {
          toValue: 0,
          duration: 2600,
          easing: Easing.inOut(Easing.sin),
          useNativeDriver: true,
        }),
      ])
    );
    const glow = Animated.loop(
      Animated.sequence([
        Animated.timing(borderGlow, {
          toValue: 0.7,
          duration: 1900,
          easing: Easing.inOut(Easing.sin),
          useNativeDriver: false,
          // Continuous glow must not hold InteractionManager — otherwise
          // scheduleOpenMT5ExecutionOverlay's runAfterInteractions never fires (PWA trade overlay stuck).
          isInteraction: false,
        }),
        Animated.timing(borderGlow, {
          toValue: 0.28,
          duration: 1900,
          easing: Easing.inOut(Easing.sin),
          useNativeDriver: false,
          isInteraction: false,
        }),
      ])
    );
    float.start();
    glow.start();
    return () => {
      float.stop();
      glow.stop();
    };
  }, [borderGlow, floatY]);

  const borderColor = borderGlow.interpolate({
    inputRange: [0, 1],
    outputRange: [`${accent}28`, `${accentSoft}88`],
  });

  return (
    <View style={styles.wrap}>
      <View style={styles.topBar}>
        <View style={{ width: 1 }} />
        <View style={[styles.statusPill, { borderColor: theme.colors.borderColor }]}>
          <LuxPulse active={isBotActive} />
          <Text style={[styles.statusText, { color: theme.colors.textPrimary }]}>
            {isBotActive ? 'Online' : 'Standby'}
          </Text>
        </View>
      </View>

      <Animated.View style={[styles.floatHost, { transform: [{ translateY: floatY }] }]}>
        <Animated.View
          style={[
            styles.card,
            lux.shadow.float,
            desktop && styles.cardDesktop,
            {
              borderColor,
              backgroundColor: theme.colors.backgroundSecondary,
              shadowColor: theme.colors.glowColor,
              minHeight: cardMinH,
              height: desktop ? cardMinH : undefined,
            },
          ]}
        >
          <TouchableOpacity
            style={styles.mediaFill}
            onPress={onLogoTap}
            activeOpacity={1}
            accessibilityRole="button"
            accessibilityLabel="Automation logo, triple-tap to change theme"
          >
            <EABrandProfileMedia
              fillParent
              brandImageUrl={imageUrl}
              photoUnavailable={logoError}
              preferLoopingVideo={isMinimal}
              contentFit={heroFit}
              fallbackContentFit={heroFit}
              mediaStyle={styles.mediaImage}
              onPhotoError={onPhotoError}
              fallbackSource={require('@/assets/images/icon.png')}
              testIDPhoto="ea-logo-hero-fade"
              testIDVideo="ea-logo-hero-video"
            />
            <View style={styles.bloomHost} pointerEvents="none">
              <LinearGradient
                colors={heroBleedFade.bloom as [string, string, ...string[]]}
                locations={[...heroBleedFade.bloomLocations]}
                style={StyleSheet.absoluteFill}
                start={{ x: 0.5, y: 0 }}
                end={{ x: 0.5, y: 1 }}
              />
            </View>
            <LinearGradient
              pointerEvents="none"
              colors={heroBleedFade.controlsScrim as [string, string, ...string[]]}
              locations={[0, 0.35, 0.78, 1]}
              style={styles.controlsScrim}
              start={{ x: 0.5, y: 0 }}
              end={{ x: 0.5, y: 1 }}
            />
            <LinearGradient
              pointerEvents="none"
              colors={heroBleedFade.topVeil as [string, string, ...string[]]}
              locations={[0, 0.5, 1]}
              style={styles.topVeil}
              start={{ x: 0.5, y: 0 }}
              end={{ x: 0.5, y: 1 }}
            />
            <LinearGradient
              pointerEvents="none"
              colors={heroBleedFade.edgeWhisper as [string, string, ...string[]]}
              locations={[0, 0.11, 0.89, 1]}
              style={StyleSheet.absoluteFill}
              start={{ x: 0, y: 0.5 }}
              end={{ x: 1, y: 0.5 }}
            />
          </TouchableOpacity>

          {Platform.OS === 'ios' && (
            <BlurView intensity={12} tint="dark" style={styles.softGlass} pointerEvents="none" />
          )}

          <View style={[styles.foreground, { minHeight: cardMinH }]} pointerEvents="box-none">
            <View style={[styles.spacer, { minHeight: spacerMinH }]} pointerEvents="none" />
            <Text
              testID="ea-title"
              style={[styles.botName, { color: theme.colors.textPrimary }]}
              numberOfLines={2}
            >
              {name}
            </Text>
            <View
              style={[
                styles.badge,
                {
                  backgroundColor: `${theme.colors.success}14`,
                  borderColor: `${theme.colors.success}40`,
                },
              ]}
            >
              <Text style={[styles.badgeValue, { color: theme.colors.success }]} numberOfLines={1}>
                {((ownerName || '').trim() || 'Aura mentor').toUpperCase()}
              </Text>
            </View>
            <Text style={[styles.subtitle, { color: theme.colors.textSecondary }]}>
              Autonomous execution
            </Text>

            <View style={styles.actions}>
              <TouchableOpacity
                testID="action-start"
                style={[
                  styles.pillPrimary,
                  {
                    borderColor: accent,
                    backgroundColor: `${accent}18`,
                    shadowColor: accent,
                  },
                ]}
                onPress={onToggleBot}
                activeOpacity={0.75}
              >
                {isBotActive ? (
                  <Square color={accentSoft} size={18} strokeWidth={2} />
                ) : (
                  <Play color={accentSoft} size={18} strokeWidth={2} fill={accentSoft} />
                )}
                <Text style={[styles.pillPrimaryText, { color: accentSoft }]}>
                  {isBotActive ? 'Stop' : 'Start'}
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                testID="action-quotes"
                style={[styles.pillGhost, { borderColor: 'rgba(255,255,255,0.2)' }]}
                onPress={onQuotes}
                activeOpacity={0.75}
              >
                <Activity color="#FFFFFF" size={17} strokeWidth={1.8} />
                <Text style={styles.pillGhostText}>Quotes</Text>
              </TouchableOpacity>

              <TouchableOpacity
                testID="action-remove"
                style={[
                  styles.pillGhost,
                  {
                    borderColor: licenseExpired ? `${theme.colors.error}55` : 'rgba(255,255,255,0.2)',
                  },
                ]}
                onPress={onRemove}
                activeOpacity={0.75}
              >
                <Trash2
                  color={licenseExpired ? theme.colors.error : 'rgba(255,255,255,0.85)'}
                  size={17}
                  strokeWidth={1.8}
                />
              </TouchableOpacity>
            </View>
          </View>
        </Animated.View>
      </Animated.View>

      {licenseExpired && <LicenseBlockedOverlay label="Expired" preserveRemoveButton />}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: lux.space.lg,
    paddingTop: lux.space.sm,
    position: 'relative',
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    marginBottom: lux.space.sm,
  },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: lux.radius.pill,
    borderWidth: 1,
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  floatHost: {
    marginBottom: 4,
  },
  card: {
    borderRadius: lux.radius.xl,
    borderWidth: 1,
    overflow: 'hidden',
    position: 'relative',
  },
  cardDesktop: {
    borderRadius: 36,
  },
  mediaFill: {
    ...StyleSheet.absoluteFillObject,
  },
  mediaImage: {
    width: '100%',
    height: '100%',
  },
  bloomHost: {
    ...StyleSheet.absoluteFillObject,
  },
  controlsScrim: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    height: '48%',
  },
  topVeil: {
    position: 'absolute',
    left: 0,
    right: 0,
    top: 0,
    height: '22%',
  },
  softGlass: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.15,
  },
  foreground: {
    flex: 1,
    paddingHorizontal: lux.space.lg,
    paddingBottom: lux.space.lg,
    paddingTop: lux.space.md,
    justifyContent: 'flex-end',
    zIndex: 5,
  },
  spacer: {
    flex: 1,
  },
  botName: {
    fontSize: 26,
    fontWeight: '700',
    letterSpacing: -0.5,
    lineHeight: 32,
    textAlign: 'center',
  },
  subtitle: {
    marginTop: 8,
    marginBottom: lux.space.md,
    fontSize: 13,
    fontWeight: '500',
    textAlign: 'center',
  },
  badge: {
    alignSelf: 'center',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 10,
    paddingVertical: 7,
    paddingHorizontal: 16,
    borderRadius: lux.radius.pill,
    borderWidth: 1,
  },
  badgeValue: {
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 1.6,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    width: '100%',
  },
  pillPrimary: {
    flex: 1.25,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 13,
    borderRadius: lux.radius.pill,
    borderWidth: 1.5,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.35,
    shadowRadius: 14,
    elevation: 6,
  },
  pillPrimaryText: {
    fontSize: 14,
    fontWeight: '600',
  },
  pillGhost: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 13,
    paddingHorizontal: 10,
    borderRadius: lux.radius.pill,
    borderWidth: 1,
    backgroundColor: 'rgba(0,0,0,0.28)',
  },
  pillGhostText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#FFFFFF',
  },
});
