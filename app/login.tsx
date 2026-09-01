import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Platform,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  KeyboardAvoidingView,
  ScrollView,
  Linking,
} from 'react-native';
import { router } from 'expo-router';
import { ArrowLeft, X } from 'lucide-react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useApp } from '@/providers/app-provider';
import { useTheme } from '@/providers/theme-provider';
import { apiService } from '@/services/api';
import { AccessDialog } from '@/components/auth/access-dialog';
import { AuthHero } from '@/components/auth/auth-hero';
import { type } from '@/constants/typography';
import {
  buildShopPaymentUrl,
  buildPaystackCheckoutUrl,
  confirmPaymentAffiliation,
  getOrCreateVisitorId,
  getStoredAffiliateRef,
  pingAffiliateAttribution,
} from '@/utils/affiliate-ref';

export default function LoginScreen() {
  const { theme } = useTheme();
  const insets = useSafeAreaInsets();
  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [modalTitle, setModalTitle] = useState('');
  const [modalMessage, setModalMessage] = useState('');
  const [paymentVisible, setPaymentVisible] = useState(false);
  const [paymentUrl, setPaymentUrl] = useState('');
  const [paymentEmail, setPaymentEmail] = useState('');
  const { setUser, setIsFirstTime } = useApp();

  const showDialog = (title: string, message: string) => {
    setModalTitle(title);
    setModalMessage(message);
    setModalVisible(true);
  };

  const handleBackToStart = async () => {
    await AsyncStorage.removeItem('emailAuthenticated');
    await setIsFirstTime(true);
    router.replace('/(tabs)');
  };

  const handleProceed = async () => {
    const trimmed = email.trim();
    if (!trimmed || !trimmed.includes('@')) {
      showDialog('Invalid email', 'Enter a valid email address to continue.');
      return;
    }

    setIsLoading(true);
    try {
      const account = await apiService.authenticate({ email: trimmed, mentor: '' });
      if (account.degraded) {
        showDialog('Connection issue', 'Could not reach the server. Check your network and try again.');
        return;
      }

      if (account.status === 'not_found' || !account.paid) {
        const affiliateRef = await getStoredAffiliateRef();
        const visitorId = await getOrCreateVisitorId();
        if (affiliateRef) await pingAffiliateAttribution(affiliateRef, trimmed);
        setPaymentEmail(trimmed);
        const paystackUrl = buildPaystackCheckoutUrl(trimmed, affiliateRef);
        const shopUrl = buildShopPaymentUrl(trimmed, affiliateRef, visitorId);
        setPaymentUrl(Platform.OS === 'web' ? shopUrl : paystackUrl);
        if (Platform.OS === 'web') {
          setPaymentVisible(true);
        } else {
          await Linking.openURL(paystackUrl);
          setPaymentVisible(true);
        }
        return;
      }

      if ((account as { invalidMentor?: number }).invalidMentor === 1) {
        showDialog('Invalid mentor', 'The mentor ID does not match our records for this email.');
        return;
      }

      if (account.used) {
        showDialog('Account in use', 'This email is already active on another device.');
        return;
      }

      await AsyncStorage.setItem('emailAuthenticated', 'true');
      await setIsFirstTime(false);
      setUser({ mentorId: '', email: account.email });
      router.replace('/license');
    } catch (error) {
      showDialog('Something went wrong', error instanceof Error ? error.message : 'Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const closePaymentModal = async () => {
    setPaymentVisible(false);
    const confirmEmail = (paymentEmail || email).trim();
    if (!confirmEmail.includes('@')) return;
    await confirmPaymentAffiliation(confirmEmail);
    try {
      const account = await apiService.authenticate({ email: confirmEmail, mentor: '' });
      if (account.degraded || account.status === 'not_found' || !account.paid || account.used) return;
      await AsyncStorage.setItem('emailAuthenticated', 'true');
      await setIsFirstTime(false);
      setUser({ mentorId: '', email: account.email });
      router.replace('/license');
    } catch {
      /* ignore */
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
          <View style={styles.topRow}>
            <TouchableOpacity
              onPress={() => void handleBackToStart()}
              style={styles.backLink}
              activeOpacity={0.7}
              accessibilityRole="button"
              accessibilityLabel="Back to start"
            >
              <ArrowLeft size={18} color={theme.colors.textSecondary} strokeWidth={2} />
              <Text style={[styles.backText, { color: theme.colors.textSecondary }]}>Start</Text>
            </TouchableOpacity>
          </View>

          <AuthHero
            step={1}
            total={2}
            subtitle="Verify the email you registered with before linking your automation."
          />

          <View style={styles.copyBlock}>
            <Text style={[styles.eyebrow, { color: theme.colors.accent }]}>Sign in</Text>
            <Text style={[styles.headline, { color: theme.colors.textPrimary }]}>
              Verify your email
            </Text>
          </View>

          <View style={[styles.formCard, { borderColor: theme.colors.borderColor }]}>
            <Text style={[styles.fieldLabel, { color: theme.colors.textMuted }]}>Email address</Text>
            <TextInput
              value={email}
              onChangeText={setEmail}
              placeholder="you@example.com"
              placeholderTextColor={theme.colors.textMuted}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              autoCorrect={false}
              style={[styles.fieldInput, { color: theme.colors.textPrimary, borderColor: theme.colors.borderColor }]}
            />

            <TouchableOpacity
              style={[
                styles.primaryBtn,
                { backgroundColor: theme.colors.accent },
                isLoading && styles.primaryBtnDisabled,
              ]}
              onPress={() => void handleProceed()}
              disabled={isLoading}
              activeOpacity={0.88}
            >
              {isLoading ? (
                <ActivityIndicator color={theme.colors.onAccent} size="small" />
              ) : (
                <Text style={[styles.primaryBtnText, { color: theme.colors.onAccent }]}>
                  Verify
                </Text>
              )}
            </TouchableOpacity>
          </View>

          <Text style={[styles.formFoot, { color: theme.colors.textMuted }]}>
            Next step: paste your automation key on the following screen.
          </Text>
        </ScrollView>
      </KeyboardAvoidingView>

      <AccessDialog
        visible={modalVisible}
        title={modalTitle}
        message={modalMessage}
        onClose={() => setModalVisible(false)}
      />

      {paymentVisible && (
        <View style={[styles.payOverlay, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 12 }]}>
          <View style={[styles.paySheet, { borderColor: theme.colors.borderColor }]}>
            <View style={styles.payHead}>
              <View style={styles.payHeadCopy}>
                <Text style={[styles.payEyebrow, { color: theme.colors.accent }]}>Checkout</Text>
                <Text style={[styles.payTitle, { color: theme.colors.textPrimary }]}>
                  {Platform.OS === 'web' ? 'Complete payment' : 'Finish in your browser'}
                </Text>
                {Platform.OS !== 'web' ? (
                  <Text style={[styles.payHint, { color: theme.colors.textMuted }]}>
                    Paystack opened in your browser. Return here when done — we will verify your email automatically.
                  </Text>
                ) : null}
              </View>
              <TouchableOpacity onPress={() => void closePaymentModal()} style={styles.payClose}>
                <X color={theme.colors.textPrimary} size={22} />
              </TouchableOpacity>
            </View>
            {Platform.OS === 'web' ? (
              <View style={styles.payFrame}>
                <iframe
                  src={paymentUrl}
                  style={{ width: '100%', height: '100%', border: 0, borderRadius: 12 } as never}
                  allow="payment *; clipboard-write;"
                />
              </View>
            ) : (
              <View style={styles.payNativeActions}>
                <TouchableOpacity
                  style={[styles.primaryBtn, { backgroundColor: theme.colors.accent }]}
                  onPress={() => void Linking.openURL(paymentUrl)}
                  activeOpacity={0.88}
                >
                  <Text style={[styles.primaryBtnText, { color: theme.colors.onAccent }]}>Open Paystack checkout</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.payDoneBtn}
                  onPress={() => void closePaymentModal()}
                  activeOpacity={0.88}
                >
                  <Text style={[styles.payDoneText, { color: theme.colors.textSecondary }]}>I have paid</Text>
                </TouchableOpacity>
              </View>
            )}
          </View>
        </View>
      )}
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
    gap: 14,
    backgroundColor: '#070708',
  },
  fieldLabel: {
    ...type.label,
  },
  fieldInput: {
    ...type.input,
    paddingVertical: 14,
    borderBottomWidth: 1,
    textAlign: 'center',
  },
  primaryBtn: {
    height: 52,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 8,
  },
  primaryBtnDisabled: { opacity: 0.55 },
  primaryBtnText: {
    ...type.button,
  },
  formFoot: {
    ...type.caption,
    textAlign: 'center',
    marginTop: 16,
    marginBottom: 8,
    paddingHorizontal: 8,
  },
  payOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.94)',
    paddingHorizontal: 16,
    zIndex: 200,
  },
  paySheet: {
    flex: 1,
    maxHeight: '100%',
    borderWidth: 1,
    borderRadius: 20,
    overflow: 'hidden',
    backgroundColor: '#070708',
    padding: 16,
  },
  payHead: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 12,
    gap: 12,
  },
  payHeadCopy: {
    flex: 1,
    minWidth: 0,
  },
  payHint: {
    ...type.caption,
    marginTop: 8,
    lineHeight: 18,
  },
  payFrame: {
    flex: 1,
    minHeight: 420,
    borderRadius: 12,
    overflow: 'hidden',
    backgroundColor: '#000',
  },
  payNativeActions: {
    flex: 1,
    justifyContent: 'center',
    gap: 12,
    paddingVertical: 12,
  },
  payDoneBtn: {
    alignItems: 'center',
    paddingVertical: 12,
  },
  payDoneText: {
    ...type.bodyMedium,
    fontSize: 15,
  },
  payEyebrow: {
    ...type.eyebrow,
    marginBottom: 4,
  },
  payTitle: {
    ...type.title,
  },
  payClose: { padding: 8 },
});
