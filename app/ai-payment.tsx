import React, { useCallback, useState } from 'react';
import { View, Text, StyleSheet, Linking } from 'react-native';
import { Scan, Sparkles, Shield } from 'lucide-react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useTheme } from '@/providers/theme-provider';
import { useApp } from '@/providers/app-provider';
import { apiService } from '@/services/api';
import {
  AuraScreen,
  AuraHeader,
  AuraCard,
  AuraButton,
  AuraFeatureRow,
} from '@/components/aura';
import { auraUi } from '@/constants/aura-ui';

const PAYSTACK_CHECKOUT_URL = 'https://paystack.shop/pay/204p1hwqij';
const AI_SCANNER_ROUTE = '/(tabs)/ai-scanner';

export default function AIPaymentScreen() {
  const { theme } = useTheme();
  const { user } = useApp();
  const params = useLocalSearchParams<{ email?: string }>();
  const email = (params.email || user?.email || '').trim().toLowerCase();
  const [checkoutError, setCheckoutError] = useState<string | null>(null);

  const resolveEmail = useCallback(async (): Promise<string> => {
    if (email) return email;
    try {
      const stored = await AsyncStorage.getItem('user');
      if (stored) {
        const parsed = JSON.parse(stored) as { email?: string };
        return (parsed?.email || '').trim().toLowerCase();
      }
    } catch {
      /* ignore */
    }
    return '';
  }, [email]);

  const returnToScanner = useCallback(() => {
    if (router.canGoBack()) router.back();
    else router.replace(AI_SCANNER_ROUTE);
  }, []);

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      const checkUnlock = async () => {
        const resolvedEmail = await resolveEmail();
        if (!resolvedEmail || cancelled) return;
        const { scanner } = await apiService.getScannerStatus(resolvedEmail);
        if (!cancelled && scanner) returnToScanner();
      };
      void checkUnlock();
      const interval = setInterval(() => void checkUnlock(), 3000);
      return () => {
        cancelled = true;
        clearInterval(interval);
      };
    }, [resolveEmail, returnToScanner])
  );

  const openPaystackCheckout = async () => {
    setCheckoutError(null);
    try {
      const resolvedEmail = await resolveEmail();
      const url = resolvedEmail
        ? `${PAYSTACK_CHECKOUT_URL}?email=${encodeURIComponent(resolvedEmail)}`
        : PAYSTACK_CHECKOUT_URL;
      await Linking.openURL(url);
    } catch {
      setCheckoutError('Could not open checkout. Please try again.');
    }
  };

  return (
    <AuraScreen scroll>
      <AuraHeader
        kicker="AI Scanner"
        title="Unlock chart AI"
        subtitle="Pay once. Scan charts. Get BUY/SELL with entry, SL, and TP."
        onBack={returnToScanner}
      />

      <AuraFeatureRow items={['Upload chart', 'BUY / SELL', 'Entry · SL · TP']} />

      <AuraCard accent style={styles.heroCard}>
        <View style={[styles.iconWrap, { backgroundColor: `${theme.colors.accent}18` }]}>
          <Scan color={theme.colors.accent} size={32} strokeWidth={2} />
        </View>
        <Text style={[styles.cardTitle, { color: theme.colors.textPrimary }]}>
          What you get
        </Text>
        <Text style={[styles.cardBody, { color: theme.colors.textSecondary }]}>
          Send a MetaTrader or TradingView screenshot. Aura returns a clear signal and levels you can trade.
        </Text>
        <View style={styles.perks}>
          <View style={styles.perkRow}>
            <Sparkles size={16} color={theme.colors.accent} />
            <Text style={[styles.perkText, { color: theme.colors.textMuted }]}>Results in seconds</Text>
          </View>
          <View style={styles.perkRow}>
            <Shield size={16} color={theme.colors.accent} />
            <Text style={[styles.perkText, { color: theme.colors.textMuted }]}>Pay securely with Paystack</Text>
          </View>
        </View>
      </AuraCard>

      <AuraCard>
        <Text style={[styles.priceLabel, { color: theme.colors.textMuted }]}>One-time unlock</Text>
        <Text style={[styles.priceHint, { color: theme.colors.textSecondary }]}>
          After payment, return here — the scanner unlocks automatically.
        </Text>
        <View style={{ marginTop: auraUi.space.md }}>
          <AuraButton label="Pay & unlock" onPress={openPaystackCheckout} />
        </View>
        {checkoutError ? (
          <Text style={[styles.errorText, { color: theme.colors.error }]}>{checkoutError}</Text>
        ) : null}
      </AuraCard>
    </AuraScreen>
  );
}

const styles = StyleSheet.create({
  heroCard: { marginBottom: auraUi.space.md },
  iconWrap: {
    width: 56,
    height: 56,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: auraUi.space.md,
  },
  cardTitle: { fontSize: 20, fontWeight: '700', letterSpacing: -0.3, marginBottom: 8 },
  cardBody: { fontSize: 14, lineHeight: 21 },
  perks: { marginTop: auraUi.space.md, gap: 8 },
  perkRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  perkText: { fontSize: 13, fontWeight: '600' },
  priceLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  priceHint: { fontSize: 14, lineHeight: 21 },
  errorText: { marginTop: 10, fontSize: 13, textAlign: 'center' },
});
