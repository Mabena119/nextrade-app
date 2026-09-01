import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { BrandLogo } from '@/components/brand-logo';
import { BRAND } from '@/constants/brand';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';

type Props = {
  logoSize?: number;
  step?: number;
  total?: number;
  subtitle?: string;
};

/** Centered brand mark for login / license flows. */
export function AuthHero({ logoSize = 220, step, total, subtitle }: Props) {
  const { theme } = useTheme();

  return (
    <View style={styles.wrap}>
      <BrandLogo size={logoSize} />
      <Text style={[styles.brand, { color: theme.colors.textPrimary }]}>{BRAND.shortName}</Text>
      {step != null && total != null ? (
        <Text style={[styles.step, { color: theme.colors.textMuted }]}>
          Step {String(step).padStart(2, '0')} · {String(total).padStart(2, '0')}
        </Text>
      ) : null}
      {subtitle ? (
        <Text style={[styles.subtitle, { color: theme.colors.textSecondary }]}>{subtitle}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    paddingTop: 8,
    paddingBottom: 28,
  },
  brand: {
    ...type.brand,
    marginTop: 16,
  },
  step: {
    ...type.step,
    marginTop: 10,
    textTransform: 'uppercase',
  },
  subtitle: {
    ...type.body,
    textAlign: 'center',
    marginTop: 12,
    maxWidth: 300,
  },
});
