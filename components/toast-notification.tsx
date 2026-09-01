import React, { useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Animated,
  Platform,
  Dimensions,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import { CheckCircle2, XCircle, X } from 'lucide-react-native';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';

interface ToastProps {
  visible: boolean;
  message: string;
  title?: string;
  type?: 'success' | 'error' | 'info' | 'loading';
  duration?: number;
  onHide?: () => void;
}

export function Toast({
  visible,
  message,
  title = 'Notice',
  type = 'loading',
  duration = 0,
  onHide,
}: ToastProps) {
  const { theme } = useTheme();
  const translateY = useRef(new Animated.Value(-100)).current;
  const opacity = useRef(new Animated.Value(0)).current;

  const hideToast = () => {
    Animated.parallel([
      Animated.timing(translateY, {
        toValue: -100,
        duration: 280,
        useNativeDriver: true,
      }),
      Animated.timing(opacity, {
        toValue: 0,
        duration: 280,
        useNativeDriver: true,
      }),
    ]).start(() => {
      onHide?.();
    });
  };

  useEffect(() => {
    if (visible) {
      Animated.parallel([
        Animated.spring(translateY, {
          toValue: 0,
          useNativeDriver: true,
          tension: 100,
          friction: 8,
        }),
        Animated.timing(opacity, {
          toValue: 1,
          duration: 280,
          useNativeDriver: true,
        }),
      ]).start();

      if (duration > 0) {
        const timer = setTimeout(() => hideToast(), duration);
        return () => clearTimeout(timer);
      }
    } else {
      hideToast();
    }
  }, [visible, duration]);

  const accent =
    type === 'success'
      ? theme.colors.success
      : type === 'error'
        ? theme.colors.error
        : theme.colors.accent;

  const getIndicator = () => {
    switch (type) {
      case 'success':
        return <CheckCircle2 color={theme.colors.success} size={22} strokeWidth={2} />;
      case 'error':
        return <XCircle color={theme.colors.error} size={22} strokeWidth={2} />;
      case 'loading':
      default:
        return <ActivityIndicator size="small" color={theme.colors.accent} />;
    }
  };

  const opacityInternal = opacity as { _value?: number };
  if (!visible && (opacityInternal._value ?? 0) === 0) {
    return null;
  }

  return (
    <Animated.View
      style={[
        styles.container,
        {
          transform: [{ translateY }],
          opacity,
          backgroundColor: authColors.card,
          borderColor: `${accent}44`,
        },
      ]}
    >
      <View style={styles.content}>
        <View style={[styles.indicatorContainer, { backgroundColor: `${accent}14` }]}>
          {getIndicator()}
        </View>
        <View style={styles.textContainer}>
          <Text style={[styles.title, { color: theme.colors.textPrimary }]}>{title}</Text>
          <Text style={[styles.message, { color: theme.colors.textMuted }]} numberOfLines={3}>
            {message}
          </Text>
        </View>
        <TouchableOpacity style={styles.closeButton} onPress={hideToast} hitSlop={8}>
          <X color={theme.colors.textMuted} size={18} strokeWidth={2} />
        </TouchableOpacity>
      </View>
    </Animated.View>
  );
}

const { width } = Dimensions.get('window');

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    top: Platform.OS === 'ios' ? 56 : 16,
    left: 24,
    right: 24,
    maxWidth: Math.min(440, width - 48),
    alignSelf: 'center',
    borderRadius: 20,
    borderWidth: 1,
    zIndex: 9999,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.35,
        shadowRadius: 20,
      },
      android: { elevation: 12 },
      default: {},
    }),
  },
  content: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 14,
    paddingHorizontal: 14,
    gap: 12,
  },
  indicatorContainer: {
    width: 36,
    height: 36,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  textContainer: {
    flex: 1,
    minWidth: 0,
  },
  title: {
    ...type.bodyMedium,
    fontSize: 15,
    marginBottom: 2,
  },
  message: {
    ...type.caption,
    lineHeight: 18,
  },
  closeButton: {
    width: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
