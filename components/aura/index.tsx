import React, { useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Platform,
  ScrollView,
  Animated,
  Easing,
  Keyboard,
  type ViewStyle,
  type TextInputProps,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { BlurView } from 'expo-blur';
import { ArrowLeft } from 'lucide-react-native';
import { useTheme } from '@/providers/theme-provider';
import { lux, auraUi } from '@/constants/aura-ui';
import { type } from '@/constants/typography';
import { authColors } from '@/constants/auth-layout';

export { auraUi, lux };

export function AuraAtmosphere() {
  const { theme } = useTheme();
  return (
    <View style={[StyleSheet.absoluteFill, { backgroundColor: theme.colors.background }]} pointerEvents="none" />
  );
}

/* ── Screen shell ── */
type AuraScreenProps = {
  children: React.ReactNode;
  scroll?: boolean;
  keyboard?: boolean;
  style?: ViewStyle;
};

export function AuraScreen({ children, scroll, keyboard, style }: AuraScreenProps) {
  const { theme } = useTheme();
  const insets = useSafeAreaInsets();
  const scrollRef = useRef<ScrollView>(null);
  const [keyboardHeight, setKeyboardHeight] = useState(0);
  /** iOS can report insets.bottom ≈ keyboard height and leave it stuck — freeze a sane home-indicator value. */
  const stableBottomInset = useRef(Math.min(Math.max(insets.bottom, 0), 48));
  const screenBg = theme.colors.background || '#000000';

  useEffect(() => {
    if (keyboardHeight > 0) return;
    if (insets.bottom > 0 && insets.bottom <= 48) {
      stableBottomInset.current = insets.bottom;
    }
  }, [insets.bottom, keyboardHeight]);

  useEffect(() => {
    if (!keyboard) return;

    if (Platform.OS === 'web' && typeof window !== 'undefined') {
      const vv = window.visualViewport;
      if (!vv) return;
      const sync = () => {
        const overlap = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
        setKeyboardHeight(overlap > 60 ? overlap : 0);
        if (overlap <= 60) {
          requestAnimationFrame(() => {
            scrollRef.current?.scrollTo({ y: 0, animated: false });
            try {
              window.scrollTo(0, 0);
            } catch {
              /* noop */
            }
          });
        }
      };
      vv.addEventListener('resize', sync);
      vv.addEventListener('scroll', sync);
      return () => {
        vv.removeEventListener('resize', sync);
        vv.removeEventListener('scroll', sync);
      };
    }

    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const onShow = Keyboard.addListener(showEvent, (e) => {
      setKeyboardHeight(e.endCoordinates?.height ?? 0);
    });
    const onHide = Keyboard.addListener(hideEvent, () => {
      setKeyboardHeight(0);
      // Padding shrinks on hide — reset scroll or content stays shifted up with a dead band.
      requestAnimationFrame(() => {
        scrollRef.current?.scrollTo({ y: 0, animated: false });
      });
    });

    return () => {
      onShow.remove();
      onHide.remove();
    };
  }, [keyboard]);

  /**
   * Avoid KeyboardAvoidingView / automaticallyAdjustKeyboardInsets (both stick after dismiss).
   * Avoid SafeArea bottom edge on keyboard screens (iOS often poisons insets.bottom with keyboard height).
   * Use stable home-indicator padding + temporary keyboard padding on the ScrollView only.
   */
  const bottomPad =
    stableBottomInset.current + lux.space.xxl + (keyboard ? keyboardHeight : 0);

  const body = scroll ? (
    <ScrollView
      ref={scrollRef}
      contentContainerStyle={[styles.scrollGrow, { paddingBottom: bottomPad }, style]}
      keyboardShouldPersistTaps="handled"
      keyboardDismissMode="on-drag"
      showsVerticalScrollIndicator={false}
      automaticallyAdjustKeyboardInsets={false}
      contentInsetAdjustmentBehavior="never"
      bounces={Platform.OS === 'ios'}
    >
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.flex, { paddingBottom: bottomPad }, style]}>{children}</View>
  );

  return (
    <SafeAreaView
      style={[styles.safe, { backgroundColor: screenBg }]}
      edges={
        keyboard
          ? (['top', 'left', 'right'] as const)
          : (['top', 'right', 'bottom', 'left'] as const)
      }
    >
      <AuraAtmosphere />
      {body}
    </SafeAreaView>
  );
}

/* ── Header ── */
type AuraHeaderProps = {
  kicker?: string;
  title: string;
  subtitle?: string;
  onBack?: () => void;
  right?: React.ReactNode;
};

export function AuraHeader({ kicker, title, subtitle, onBack, right }: AuraHeaderProps) {
  const { theme } = useTheme();

  return (
    <View style={styles.headerRow}>
      {onBack ? (
        <TouchableOpacity
          onPress={onBack}
          style={[styles.iconBtn, { borderColor: theme.colors.borderColor }]}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel="Go back"
        >
          {Platform.OS === 'ios' && (
            <BlurView intensity={20} tint="dark" style={StyleSheet.absoluteFill} />
          )}
          <ArrowLeft size={20} color={theme.colors.textPrimary} strokeWidth={1.8} />
        </TouchableOpacity>
      ) : (
        <View style={styles.iconBtnSpacer} />
      )}
      <View style={styles.headerText}>
        {kicker ? (
          <Text style={[styles.kicker, { color: theme.colors.accent }]}>{kicker}</Text>
        ) : null}
        <Text style={[styles.headerTitle, { color: theme.colors.textPrimary }]}>
          {title}
        </Text>
        {subtitle ? (
          <Text style={[styles.headerSub, { color: theme.colors.textMuted }]}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      {right ?? <View style={styles.iconBtnSpacer} />}
    </View>
  );
}

/* ── Floating glass card ── */
type AuraCardProps = {
  children: React.ReactNode;
  style?: ViewStyle;
  accent?: boolean;
};

export function AuraCard({ children, style, accent }: AuraCardProps) {
  const { theme } = useTheme();
  return (
    <View
      style={[
        styles.card,
        {
          borderColor: accent ? `${theme.colors.accent}55` : 'rgba(255,255,255,0.08)',
          backgroundColor: '#000000',
        },
        style,
      ]}
    >
      <View style={styles.cardInner}>{children}</View>
    </View>
  );
}

/* ── Input ── */
type AuraInputProps = TextInputProps & { label?: string };

export function AuraInput({ label, style, ...props }: AuraInputProps) {
  return (
    <View style={styles.field}>
      {label ? <Text style={styles.fieldLabel}>{label}</Text> : null}
      <TextInput
        placeholderTextColor={lux.color.textMuted}
        style={[styles.input, style]}
        {...props}
      />
    </View>
  );
}

/* ── Pill buttons ── */
type AuraButtonProps = {
  label: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  variant?: 'primary' | 'ghost' | 'danger' | 'outline';
  icon?: React.ReactNode;
};

export function AuraButton({
  label,
  onPress,
  loading,
  disabled,
  variant = 'primary',
  icon,
}: AuraButtonProps) {
  const { theme } = useTheme();
  const isPrimary = variant === 'primary';
  const isOutline = variant === 'outline';
  const isGhost = variant === 'ghost';
  const isDanger = variant === 'danger';

  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
      style={[
        styles.btn,
        isPrimary && {
          backgroundColor: theme.colors.accent,
        },
        isOutline && {
          backgroundColor: 'transparent',
          borderWidth: 1,
          borderColor: theme.colors.accent,
        },
        isGhost && {
          backgroundColor: 'transparent',
          borderWidth: 1,
          borderColor: 'rgba(255,255,255,0.1)',
        },
        isDanger && {
          backgroundColor: 'transparent',
          borderWidth: 1,
          borderColor: `${theme.colors.error}55`,
        },
        (disabled || loading) && { opacity: 0.45 },
      ]}
    >
      {loading ? (
        <ActivityIndicator color={isPrimary ? '#000' : theme.colors.accent} size="small" />
      ) : (
        <>
          {icon}
          <Text
            style={[
              styles.btnText,
              {
                color: isDanger
                  ? theme.colors.error
                  : isPrimary
                    ? '#000000'
                    : isOutline
                      ? theme.colors.accent
                      : theme.colors.textPrimary,
              },
            ]}
          >
            {label}
          </Text>
        </>
      )}
    </TouchableOpacity>
  );
}

/* ── Modal ── */
type AuraModalProps = {
  visible: boolean;
  title: string;
  message?: string;
  onClose: () => void;
  children?: React.ReactNode;
};

export function AuraModal({ visible, title, message, onClose, children }: AuraModalProps) {
  if (!visible) return null;
  return (
    <View style={styles.modalOverlay}>
      <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={onClose} />
      <AuraCard style={styles.modalCard}>
        <Text style={styles.modalTitle}>{title}</Text>
        {message ? <Text style={styles.modalMsg}>{message}</Text> : null}
        {children}
        <AuraButton label="Got it" onPress={onClose} />
      </AuraCard>
    </View>
  );
}

export function AuraStep({ step, total, label }: { step: number; total: number; label: string }) {
  return (
    <View style={styles.stepRow}>
      <Text style={styles.stepNum}>
        {step}/{total}
      </Text>
      <Text style={styles.stepLabel}>{label}</Text>
    </View>
  );
}

export function AuraFeatureRow({ items }: { items: string[] }) {
  return (
    <View style={styles.chipRow}>
      {items.map((item) => (
        <View key={item} style={styles.chip}>
          <Text style={styles.chipText}>{item}</Text>
        </View>
      ))}
    </View>
  );
}

/** Pulsing online indicator */
export function LuxPulse({
  active = true,
  tone,
}: {
  active?: boolean;
  /** Override color tone when not using default success/muted. */
  tone?: 'success' | 'error' | 'muted';
}) {
  const { theme } = useTheme();
  const scale = useRef(new Animated.Value(1)).current;
  useEffect(() => {
    if (!active) return;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(scale, {
          toValue: 1.55,
          duration: 900,
          easing: Easing.out(Easing.quad),
          useNativeDriver: true,
        }),
        Animated.timing(scale, {
          toValue: 1,
          duration: 900,
          easing: Easing.in(Easing.quad),
          useNativeDriver: true,
        }),
      ])
    );
    loop.start();
    return () => loop.stop();
  }, [active, scale]);

  const color =
    tone === 'error'
      ? theme.colors.error
      : tone === 'muted'
        ? theme.colors.textMuted
        : tone === 'success' || active
          ? theme.colors.success
          : theme.colors.textMuted;
  return (
    <View style={styles.pulseWrap} testID="connection-status-dot">
      {active ? (
        <Animated.View
          style={[styles.pulseRing, { borderColor: color, transform: [{ scale }], opacity: 0.45 }]}
        />
      ) : null}
      <View style={[styles.pulseCore, { backgroundColor: color }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: lux.color.bg },
  flex: { flex: 1 },
  scrollGrow: {
    flexGrow: 1,
    paddingHorizontal: lux.space.lg,
    paddingBottom: lux.space.xxl,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: lux.space.sm,
    paddingHorizontal: lux.space.lg,
    paddingTop: lux.space.sm,
    paddingBottom: lux.space.lg,
  },
  headerRowMatrix: {
    paddingBottom: lux.space.md,
    marginBottom: lux.space.xs,
  },
  headerText: { flex: 1, minWidth: 0 },
  iconBtn: {
    width: 40,
    height: 40,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    backgroundColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  iconBtnSpacer: { width: 44 },
  kicker: {
    ...type.eyebrow,
    color: lux.color.accent,
    marginBottom: 6,
  },
  headerTitle: {
    ...type.title,
    fontSize: 28,
    letterSpacing: -0.6,
    lineHeight: 34,
    color: lux.color.text,
  },
  headerSub: {
    ...type.body,
    marginTop: 8,
    fontSize: 14,
    lineHeight: 21,
    color: lux.color.textSecondary,
  },
  card: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: authColors.cardBorder,
    backgroundColor: authColors.card,
  },
  cardAccent: {
    borderColor: 'rgba(49, 197, 255, 0.22)',
  },
  cardReflection: {
    ...StyleSheet.absoluteFillObject,
  },
  cardInner: {
    padding: lux.space.lg,
  },
  field: { marginBottom: lux.space.md },
  fieldLabel: {
    ...type.label,
    color: lux.color.textSecondary,
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderRadius: 14,
    paddingHorizontal: 16,
    paddingVertical: 14,
    ...type.input,
    fontSize: 16,
    color: lux.color.text,
    borderColor: authColors.cardBorder,
    backgroundColor: authColors.inputBg,
  },
  btn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'stretch',
    gap: 8,
    height: 52,
    paddingHorizontal: 22,
    borderRadius: 999,
  },
  btnText: {
    ...type.button,
    color: '#000',
  },
  modalOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(8, 10, 15, 0.82)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: lux.space.lg,
    zIndex: 100,
  },
  modalCard: { width: '100%', maxWidth: 360 },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    letterSpacing: -0.3,
    color: lux.color.text,
    marginBottom: 8,
  },
  modalMsg: {
    fontSize: 14,
    lineHeight: 21,
    color: lux.color.textSecondary,
    marginBottom: 16,
  },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    alignSelf: 'flex-start',
    marginBottom: lux.space.md,
  },
  stepNum: { fontSize: 12, fontWeight: '700', color: lux.color.accent },
  stepLabel: { fontSize: 12, fontWeight: '500', color: lux.color.textSecondary },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginVertical: lux.space.md },
  chip: {
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: lux.radius.pill,
    borderWidth: 1,
    borderColor: lux.color.glassBorder,
    backgroundColor: lux.color.glass,
  },
  chipText: { fontSize: 12, fontWeight: '500', color: lux.color.textSecondary },
  pulseWrap: {
    width: 14,
    height: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pulseRing: {
    position: 'absolute',
    width: 14,
    height: 14,
    borderRadius: 999,
    borderWidth: 1.5,
  },
  pulseCore: {
    width: 7,
    height: 7,
    borderRadius: 999,
  },
});
