import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Platform,
} from 'react-native';
import { Activity, ChevronRight, Play, Square, Trash2 } from 'lucide-react-native';
import { LuxPulse } from '@/components/aura';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';
import { EABrandProfileMedia } from '@/components/ea-brand-profile-media';
import { LicenseBlockedOverlay } from '@/components/license-blocked-overlay';

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
  const { theme } = useTheme();
  const accent = theme.colors.accent;
  const ownerLabel = ((ownerName || '').trim() || 'Mentor').toUpperCase();

  return (
    <View style={styles.wrap}>
      <View style={styles.pageHead}>
        <View>
          <Text style={[styles.eyebrow, { color: accent }]}>Workspace</Text>
          <Text style={[styles.pageTitle, { color: theme.colors.textPrimary }]}>
            Your automation
          </Text>
        </View>
        <View style={[styles.statusPill, { borderColor: authColors.cardBorder, backgroundColor: authColors.card }]}>
          <LuxPulse
            active={isBotActive}
            tone={isBotActive ? 'success' : 'muted'}
          />
          <Text style={[styles.statusText, { color: theme.colors.textPrimary }]}>
            {isBotActive ? 'Running' : 'Standby'}
          </Text>
        </View>
      </View>

      <View style={[styles.mainCard, { borderColor: authColors.cardBorder, backgroundColor: authColors.card }]}>
        <TouchableOpacity
          style={styles.identityRow}
          onPress={onLogoTap}
          activeOpacity={0.85}
          accessibilityRole="button"
          accessibilityLabel="Automation profile, triple-tap to change theme"
        >
          <View style={[styles.avatarFrame, { borderColor: `${accent}55` }]}>
            <EABrandProfileMedia
              fillParent
              brandImageUrl={imageUrl}
              photoUnavailable={logoError}
              preferLoopingVideo={false}
              contentFit="cover"
              fallbackContentFit="cover"
              mediaStyle={styles.avatarMedia}
              onPhotoError={onPhotoError}
              fallbackSource={require('@/assets/images/icon.png')}
              testIDPhoto="ea-logo-hero-fade"
              testIDVideo="ea-logo-hero-video"
            />
          </View>
          <View style={styles.identityCopy}>
            <Text testID="ea-title" style={[styles.botName, { color: theme.colors.textPrimary }]} numberOfLines={2}>
              {name}
            </Text>
            <View
              style={[
                styles.ownerBadge,
                {
                  backgroundColor: `${theme.colors.success}12`,
                  borderColor: `${theme.colors.success}35`,
                },
              ]}
            >
              <Text style={[styles.ownerBadgeText, { color: theme.colors.success }]} numberOfLines={1}>
                {ownerLabel}
              </Text>
            </View>
            <Text style={[styles.subtitle, { color: theme.colors.textMuted }]}>
              Autonomous execution · tap logo to switch theme
            </Text>
          </View>
        </TouchableOpacity>

        <View style={[styles.divider, { backgroundColor: authColors.cardBorder }]} />

        <TouchableOpacity
          testID="action-start"
          style={[
            styles.primaryCta,
            {
              backgroundColor: isBotActive ? 'transparent' : accent,
              borderColor: accent,
            },
            isBotActive && styles.primaryCtaActive,
          ]}
          onPress={onToggleBot}
          activeOpacity={0.88}
        >
          {isBotActive ? (
            <Square color={accent} size={18} strokeWidth={2.2} />
          ) : (
            <Play color={theme.colors.onAccent} size={18} strokeWidth={2.2} fill={theme.colors.onAccent} />
          )}
          <Text
            style={[
              styles.primaryCtaText,
              { color: isBotActive ? accent : theme.colors.onAccent },
            ]}
          >
            {isBotActive ? 'Stop automation' : 'Start automation'}
          </Text>
        </TouchableOpacity>

        <View style={styles.secondaryRow}>
          <TouchableOpacity
            testID="action-quotes"
            style={[styles.secondaryBtn, { borderColor: authColors.cardBorder, backgroundColor: authColors.inputBg }]}
            onPress={onQuotes}
            activeOpacity={0.85}
          >
            <Activity color={theme.colors.textPrimary} size={17} strokeWidth={1.8} />
            <Text style={[styles.secondaryBtnText, { color: theme.colors.textPrimary }]}>Quotes</Text>
          </TouchableOpacity>

          <TouchableOpacity
            testID="action-remove"
            style={[
              styles.secondaryBtn,
              {
                borderColor: licenseExpired ? `${theme.colors.error}44` : authColors.cardBorder,
                backgroundColor: licenseExpired ? `${theme.colors.error}10` : authColors.inputBg,
              },
            ]}
            onPress={onRemove}
            activeOpacity={0.85}
          >
            <Trash2
              color={licenseExpired ? theme.colors.error : theme.colors.textMuted}
              size={17}
              strokeWidth={1.8}
            />
            <Text
              style={[
                styles.secondaryBtnText,
                { color: licenseExpired ? theme.colors.error : theme.colors.textMuted },
              ]}
            >
              Remove
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {licenseExpired && <LicenseBlockedOverlay label="Expired" preserveRemoveButton />}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: 24,
    paddingTop: 8,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
    position: 'relative',
  },
  pageHead: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 16,
    gap: 12,
  },
  eyebrow: {
    ...type.eyebrow,
    marginBottom: 6,
  },
  pageTitle: {
    ...type.title,
    fontSize: 22,
    letterSpacing: -0.5,
  },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    marginTop: 4,
  },
  statusText: {
    ...type.caption,
    fontFamily: type.bodyMedium.fontFamily,
  },
  mainCard: {
    borderWidth: 1,
    borderRadius: 24,
    padding: 18,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 12 },
        shadowOpacity: 0.35,
        shadowRadius: 24,
      },
      android: { elevation: 8 },
      default: {},
    }),
  },
  identityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  avatarFrame: {
    width: 72,
    height: 72,
    borderRadius: 20,
    overflow: 'hidden',
    borderWidth: 1,
    backgroundColor: '#0A0A0B',
  },
  avatarMedia: {
    width: '100%',
    height: '100%',
  },
  identityCopy: {
    flex: 1,
    minWidth: 0,
  },
  botName: {
    ...type.title,
    fontSize: 22,
    lineHeight: 28,
    letterSpacing: -0.4,
  },
  ownerBadge: {
    alignSelf: 'flex-start',
    marginTop: 8,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
  },
  ownerBadgeText: {
    ...type.label,
    fontSize: 10,
    letterSpacing: 1.4,
  },
  subtitle: {
    ...type.caption,
    marginTop: 8,
    lineHeight: 18,
  },
  divider: {
    height: 1,
    marginVertical: 18,
  },
  primaryCta: {
    height: 52,
    borderRadius: 999,
    borderWidth: 1.5,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  primaryCtaActive: {
    backgroundColor: 'transparent',
  },
  primaryCtaText: {
    ...type.button,
    fontSize: 16,
  },
  secondaryRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 12,
  },
  secondaryBtn: {
    flex: 1,
    minHeight: 48,
    borderRadius: 16,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  secondaryBtnText: {
    ...type.bodyMedium,
    fontSize: 14,
  },
});
