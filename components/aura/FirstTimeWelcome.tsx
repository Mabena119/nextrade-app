import React from 'react';
import { View, Text, StyleSheet, Platform } from 'react-native';
import { AuraButton, AuraAtmosphere } from '@/components/aura';
import { BrandLogo } from '@/components/brand-logo';
import { IOSAddToHomeBanner } from '@/components/ios-add-to-home-banner';
import { lux } from '@/constants/aura-ui';

type Props = {
  onStart: () => void;
  androidOverlayGranted: boolean;
  androidNotificationGranted: boolean;
};

export function FirstTimeWelcome({
  onStart,
  androidOverlayGranted,
  androidNotificationGranted,
}: Props) {
  return (
    <View style={styles.root}>
      <AuraAtmosphere />

      <View style={styles.content}>
        <BrandLogo size={240} testID="splash-app-icon" />

        <Text style={styles.title}>NexTradeAI</Text>
        <Text style={styles.lead}>NextGen AI Automations</Text>

        {Platform.OS === 'android' && (
          <View style={styles.permPanel}>
            <Text style={styles.permHint}>Turn on overlay and notifications to keep signals running.</Text>
            <Text style={[styles.permLabel, { color: androidOverlayGranted ? lux.color.success : lux.color.textSecondary }]}>
              {androidOverlayGranted ? 'On' : 'Off'}  ·  Draw over other apps
            </Text>
            <Text style={[styles.permLabel, { color: androidNotificationGranted ? lux.color.success : lux.color.textSecondary }]}>
              {androidNotificationGranted ? 'On' : 'Off'}  ·  Notifications
            </Text>
          </View>
        )}

        <View style={styles.ctaWrap}>
          <AuraButton label="Get started" onPress={onStart} />
        </View>
      </View>

      <IOSAddToHomeBanner accentColor={lux.color.accent} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: lux.color.bg },
  content: {
    flex: 1,
    paddingHorizontal: 28,
    paddingTop: 48,
    paddingBottom: 24,
    justifyContent: 'center',
    alignItems: 'center',
  },
  title: {
    color: lux.color.text,
    fontSize: 36,
    fontWeight: '600',
    letterSpacing: -1.1,
    lineHeight: 42,
    marginTop: 20,
    marginBottom: 12,
    textAlign: 'center',
  },
  lead: {
    color: lux.color.textSecondary,
    fontSize: 16,
    lineHeight: 24,
    fontWeight: '400',
    textAlign: 'center',
    maxWidth: 320,
  },
  permPanel: {
    marginTop: 28,
    alignSelf: 'stretch',
    gap: 6,
  },
  permHint: {
    color: lux.color.textSecondary,
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 6,
    textAlign: 'center',
  },
  permLabel: { fontSize: 13, fontWeight: '500', textAlign: 'center' },
  ctaWrap: { marginTop: 36, alignSelf: 'stretch' },
});
