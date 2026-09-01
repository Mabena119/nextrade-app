import React from 'react';
import { View, Text, StyleSheet, useWindowDimensions } from 'react-native';
import { BrandLogo } from '@/components/brand-logo';
import { BRAND } from '@/constants/brand';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';

type Props = {
  logoSize?: number;
  step?: number;
  total?: number;
  subtitle?: string;
  compact?: boolean;
};

function resolveLogoSize(explicit: number | undefined, height: number, compact: boolean): number {
  if (explicit != null) return explicit;
  if (compact) return Math.min(128, Math.max(96, Math.round(height * 0.14)));
  return Math.min(168, Math.max(112, Math.round(height * 0.17)));
}

/** Centered brand mark for login / license flows. */
export function AuthHero({ logoSize, step, total, subtitle, compact = true }: Props) {
  const { theme } = useTheme();
  const { height } = useWindowDimensions();
  const size = resolveLogoSize(logoSize, height, compact);

  return (
    <View style={[styles.wrap, compact && styles.wrapCompact]}>
      <BrandLogo size={size} />
      <Text style={[styles.brand, compact && styles.brandCompact, { color: theme.colors.textPrimary }]}>
        {BRAND.shortName}
      </Text>
      {step != null && total != null ? (
        <Text style={[styles.step, { color: theme.colors.textMuted }]}>
          Step {String(step).padStart(2, '0')} · {String(total).padStart(2, '0')}
        </Text>
      ) : null}
      {subtitle ? (
        <Text style={[styles.subtitle, compact && styles.subtitleCompact, { color: theme.colors.textSecondary }]}>
          {subtitle}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    paddingTop: 4,
    paddingBottom: 20,
  },
  wrapCompact: {
    paddingTop: 0,
    paddingBottom: 14,
  },
  brand: {
    ...type.brand,
    marginTop: 12,
  },
  brandCompact: {
    marginTop: 8,
    fontSize: 22,
  },
  step: {
    ...type.step,
    marginTop: 6,
    textTransform: 'uppercase',
  },
  subtitle: {
    ...type.body,
    textAlign: 'center',
    marginTop: 8,
    maxWidth: 300,
    paddingHorizontal: 4,
  },
  subtitleCompact: {
    fontSize: 14,
    lineHeight: 20,
    marginTop: 6,
  },
});
