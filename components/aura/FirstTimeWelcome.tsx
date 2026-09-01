import React from 'react';
import { View, Text, StyleSheet, Platform, TouchableOpacity } from 'react-native';
import { AuthHero } from '@/components/auth/auth-hero';
import { IOSAddToHomeBanner } from '@/components/ios-add-to-home-banner';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';

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
  const { theme } = useTheme();

  return (
    <View style={styles.root}>
      <View style={styles.content}>
        <AuthHero
          compact={false}
          subtitle="NextGen AI automations for your trading workflow."
        />

        {Platform.OS === 'android' && (
          <View style={[styles.permPanel, { borderColor: theme.colors.borderColor }]}>
            <Text style={[styles.permHint, { color: theme.colors.textMuted }]}>
              Turn on overlay and notifications to keep signals running.
            </Text>
            <Text
              style={[
                styles.permLabel,
                { color: androidOverlayGranted ? theme.colors.success : theme.colors.textMuted },
              ]}
            >
              {androidOverlayGranted ? 'On' : 'Off'} · Draw over other apps
            </Text>
            <Text
              style={[
                styles.permLabel,
                { color: androidNotificationGranted ? theme.colors.success : theme.colors.textMuted },
              ]}
            >
              {androidNotificationGranted ? 'On' : 'Off'} · Notifications
            </Text>
          </View>
        )}

        <TouchableOpacity
          style={[styles.primaryBtn, { backgroundColor: theme.colors.accent }]}
          onPress={onStart}
          activeOpacity={0.88}
          accessibilityRole="button"
        >
          <Text style={[styles.primaryBtnText, { color: theme.colors.onAccent }]}>Get started</Text>
        </TouchableOpacity>
      </View>

      <IOSAddToHomeBanner accentColor={theme.colors.accent} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: authColors.bg,
  },
  content: {
    flex: 1,
    paddingHorizontal: 24,
    paddingTop: 40,
    paddingBottom: 24,
    justifyContent: 'center',
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  permPanel: {
    marginTop: 8,
    marginBottom: 20,
    alignSelf: 'stretch',
    gap: 6,
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 18,
    paddingVertical: 16,
    backgroundColor: authColors.card,
  },
  permHint: {
    ...type.caption,
    textAlign: 'center',
    marginBottom: 4,
  },
  permLabel: {
    ...type.caption,
    textAlign: 'center',
    fontFamily: type.body.fontFamily,
  },
  primaryBtn: {
    height: 52,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 8,
  },
  primaryBtnText: {
    ...type.button,
  },
});
