import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Platform,
  useWindowDimensions,
} from 'react-native';
import { Activity, Play, Square, Trash2 } from 'lucide-react-native';
import { LuxPulse } from '@/components/aura';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';
import { EaHeroLogo } from '@/components/aura/EaHeroLogo';
import { LicenseBlockedOverlay } from '@/components/license-blocked-overlay';

type Props = {
  name: string;
  ownerName?: string | null;
  imageUrl?: string | null;
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
  isBotActive,
  licenseExpired,
  onLogoTap,
  onToggleBot,
  onQuotes,
  onRemove,
}: Props) {
  const { theme } = useTheme();
  const { width } = useWindowDimensions();
  const accent = theme.colors.accent;
  const ownerLabel = ((ownerName || '').trim() || 'Mentor').toUpperCase();
  const logoSize = Math.min(176, Math.max(132, Math.round(width * 0.38)));

  return (
    <View style={styles.wrap}>
      <View style={styles.heroTop}>
        <TouchableOpacity
          style={[styles.logoFrame, { width: logoSize, height: logoSize, borderColor: `${accent}44` }]}
          onPress={onLogoTap}
          activeOpacity={0.9}
          accessibilityRole="button"
          accessibilityLabel="Automation logo, triple-tap to change theme"
        >
          <EaHeroLogo imageUrl={imageUrl} size={logoSize} testID="ea-logo-hero" />
        </TouchableOpacity>

        <View style={[styles.statusPill, { borderColor: authColors.cardBorder, backgroundColor: authColors.card }]}>
          <LuxPulse active={isBotActive} tone={isBotActive ? 'success' : 'muted'} />
          <Text style={[styles.statusText, { color: theme.colors.textPrimary }]}>
            {isBotActive ? 'Running' : 'Standby'}
          </Text>
        </View>

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
          Autonomous execution
        </Text>
      </View>

      <View style={[styles.mainCard, { borderColor: authColors.cardBorder, backgroundColor: authColors.card }]}>
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
    paddingTop: 4,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
    position: 'relative',
  },
  heroTop: {
    alignItems: 'center',
    marginBottom: 18,
  },
  logoFrame: {
    borderRadius: 28,
    overflow: 'hidden',
    borderWidth: 1,
    backgroundColor: '#0A0A0B',
    marginBottom: 14,
    alignItems: 'center',
    justifyContent: 'center',
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.35,
        shadowRadius: 20,
      },
      android: { elevation: 8 },
      default: {},
    }),
  },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    marginBottom: 12,
  },
  statusText: {
    ...type.caption,
    fontFamily: type.bodyMedium.fontFamily,
  },
  botName: {
    ...type.title,
    fontSize: 26,
    lineHeight: 32,
    letterSpacing: -0.5,
    textAlign: 'center',
    paddingHorizontal: 8,
  },
  ownerBadge: {
    marginTop: 10,
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
    textAlign: 'center',
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
