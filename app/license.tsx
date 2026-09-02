import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  BackHandler,
  Platform,
  KeyboardAvoidingView,
  ScrollView,
} from 'react-native';
import { router } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { ArrowLeft, ShieldCheck } from 'lucide-react-native';
import { useApp } from '@/providers/app-provider';
import { useTheme } from '@/providers/theme-provider';
import { apiService } from '@/services/api';
import { AccessDialog } from '@/components/auth/access-dialog';
import { AuthHero } from '@/components/auth/auth-hero';
import { type } from '@/constants/typography';
import { isLicenseExpired } from '@/utils/license-status';
import {
  getCachedLicenseDeviceSecret,
  setCachedLicenseDeviceSecret,
} from '@/utils/license-device-secrets';

export default function LicenseScreen() {
  const { theme } = useTheme();
  const insets = useSafeAreaInsets();
  const [licenseKey, setLicenseKey] = useState('');
  const [isActivating, setIsActivating] = useState(false);
  const { addEA, eas, setIsFirstTime } = useApp();
  const hasActiveBots = eas.length > 0;
  const [modalVisible, setModalVisible] = useState(false);
  const [modalTitle, setModalTitle] = useState('');
  const [modalMessage, setModalMessage] = useState('');

  useEffect(() => {
    const check = async () => {
      const ok = await AsyncStorage.getItem('emailAuthenticated');
      if (ok !== 'true') {
        await setIsFirstTime(true);
        router.replace('/login');
      }
    };
    void check();
  }, [setIsFirstTime]);

  useEffect(() => {
    if (hasActiveBots) return;
    const sub = BackHandler.addEventListener('hardwareBackPress', () => true);
    return () => sub.remove();
  }, [hasActiveBots]);

  const showDialog = (title: string, message: string) => {
    setModalTitle(title);
    setModalMessage(message);
    setModalVisible(true);
  };

  const handleBack = async () => {
    const ok = await AsyncStorage.getItem('emailAuthenticated');
    if (ok !== 'true') {
      await setIsFirstTime(true);
      router.replace('/login');
    } else {
      router.back();
    }
  };

  const handleActivate = async () => {
    const key = licenseKey.trim();
    if (!key) {
      showDialog('Key required', 'Paste your automation key from your mentor or dashboard.');
      return;
    }

    if (eas.some((ea) => ea.licenseKey.toLowerCase().trim() === key.toLowerCase())) {
      showDialog('Already linked', 'This key is already on this device.');
      return;
    }

    setIsActivating(true);
    try {
      const cachedSecret = await getCachedLicenseDeviceSecret(key);
      const authResponse = await apiService.authenticateLicense({
        licence: key,
        ...(cachedSecret ? { phone_secret: cachedSecret } : {}),
      });
      if (authResponse.degraded) {
        showDialog('Connection issue', 'Could not reach the server. Try again.');
        return;
      }
      if (authResponse.message === 'used') {
        showDialog(
          'Key in use',
          cachedSecret
            ? 'This key is registered to another device. Ask your mentor to unbind it from the admin console, then try again.'
            : 'This key is already active on another device. Ask your mentor to tap “Unbind device” on the code details page, or use Restore access to reset it.'
        );
        return;
      }
      if (authResponse.message !== 'accept' || !authResponse.data) {
        showDialog('Invalid key', 'This key is incorrect or already used.');
        return;
      }

      const data = authResponse.data;
      if (isLicenseExpired(data.status, data.expires)) {
        showDialog('Expired', 'This key has expired. Renew or request a new one.');
        return;
      }

      const uniqueId = `ea_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
      const success = await addEA({
        id: uniqueId,
        name: data.ea_name || 'NexTrade automation',
        licenseKey: key,
        status: 'connected',
        description: data.owner?.name || 'NexTradeAI',
        phoneSecretKey: data.phone_secret_key,
        userData: data,
      });

      if (success) {
        if (data.phone_secret_key) {
          await setCachedLicenseDeviceSecret(key, data.phone_secret_key);
        }
        await new Promise((r) => setTimeout(r, 500));
        router.replace('/(tabs)');
      } else {
        showDialog('Save failed', 'Could not store this key on the device.');
      }
    } catch {
      showDialog('Network error', 'Could not reach the server. Try again.');
    } finally {
      setIsActivating(false);
    }
  };

  return (
    <SafeAreaView style={styles.root} edges={['top', 'left', 'right']}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 8 : 0}
      >
        <ScrollView
          contentContainerStyle={[
            styles.scroll,
            {
              paddingTop: Math.max(insets.top > 0 ? 0 : 8, 4),
              paddingBottom: insets.bottom + 32,
            },
          ]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {hasActiveBots ? (
            <View style={styles.topRow}>
              <TouchableOpacity
                onPress={() => void handleBack()}
                style={styles.backLink}
                activeOpacity={0.7}
                accessibilityRole="button"
                accessibilityLabel="Go back"
              >
                <ArrowLeft size={18} color={theme.colors.textSecondary} strokeWidth={2} />
                <Text style={[styles.backText, { color: theme.colors.textSecondary }]}>Back</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.topRow} />
          )}

          <AuthHero
            step={2}
            total={2}
            subtitle="Paste the secret key from your mentor console. One key per device."
          />

          <View style={styles.copyBlock}>
            <Text style={[styles.eyebrow, { color: theme.colors.accent }]}>Activation</Text>
            <Text style={[styles.headline, { color: theme.colors.textPrimary }]}>
              Link automation key
            </Text>
          </View>

          <View style={[styles.formCard, { borderColor: theme.colors.borderColor }]}>
            <Text style={[styles.fieldLabel, { color: theme.colors.textMuted }]}>Automation key</Text>
            <View style={[styles.keySlot, { borderColor: `${theme.colors.accent}44` }]}>
              <TextInput
                value={licenseKey}
                onChangeText={setLicenseKey}
                placeholder="Paste key here"
                placeholderTextColor={theme.colors.textMuted}
                autoCapitalize="characters"
                autoCorrect={false}
                multiline={Platform.OS === 'web'}
                numberOfLines={Platform.OS === 'web' ? 2 : 1}
                style={[styles.keyInput, { color: theme.colors.textPrimary }]}
              />
            </View>

            <View style={styles.secureRow}>
              <ShieldCheck size={14} color={theme.colors.textMuted} strokeWidth={2} />
              <Text style={[styles.secureText, { color: theme.colors.textMuted }]}>
                Device-bound · encrypted locally
              </Text>
            </View>

            <TouchableOpacity
              style={[
                styles.primaryBtn,
                { backgroundColor: theme.colors.accent },
                isActivating && styles.primaryBtnDisabled,
              ]}
              onPress={() => void handleActivate()}
              disabled={isActivating}
              activeOpacity={0.88}
            >
              {isActivating ? (
                <ActivityIndicator color={theme.colors.onAccent} size="small" />
              ) : (
                <Text style={[styles.primaryBtnText, { color: theme.colors.onAccent }]}>
                  Activate & open workspace
                </Text>
              )}
            </TouchableOpacity>
          </View>

          <Text style={[styles.footerNote, { color: theme.colors.textMuted }]}>
            You'll land on your automation dashboard once the key validates.
          </Text>
        </ScrollView>
      </KeyboardAvoidingView>

      <AccessDialog
        visible={modalVisible}
        title={modalTitle}
        message={modalMessage}
        onClose={() => setModalVisible(false)}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#000000',
  },
  flex: { flex: 1 },
  scroll: {
    flexGrow: 1,
    paddingHorizontal: 24,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 0,
  },
  backLink: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingRight: 12,
  },
  backText: {
    ...type.bodyMedium,
    fontSize: 14,
  },
  copyBlock: {
    alignItems: 'center',
    marginBottom: 18,
  },
  eyebrow: {
    ...type.eyebrow,
    marginBottom: 10,
  },
  headline: {
    ...type.display,
    textAlign: 'center',
  },
  formCard: {
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 20,
    paddingVertical: 22,
    gap: 16,
    backgroundColor: '#070708',
  },
  fieldLabel: {
    ...type.label,
    textAlign: 'center',
  },
  keySlot: {
    borderWidth: 1,
    borderStyle: 'dashed',
    borderRadius: 16,
    paddingHorizontal: 16,
    paddingVertical: Platform.OS === 'web' ? 18 : 14,
    backgroundColor: 'rgba(255,255,255,0.02)',
  },
  keyInput: {
    ...type.input,
    letterSpacing: 1,
    textAlign: 'center',
    minHeight: 28,
  },
  secureRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  secureText: {
    ...type.caption,
    fontSize: 12,
  },
  primaryBtn: {
    height: 52,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  primaryBtnDisabled: { opacity: 0.55 },
  primaryBtnText: {
    ...type.button,
  },
  footerNote: {
    ...type.caption,
    textAlign: 'center',
    marginTop: 20,
  },
});
