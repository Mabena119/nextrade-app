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
  title = 'Trading Status',
  type = 'loading',
  duration = 0,
  onHide,
}: ToastProps) {
  const translateY = useRef(new Animated.Value(-100)).current;
  const opacity = useRef(new Animated.Value(0)).current;

  const hideToast = () => {
    Animated.parallel([
      Animated.timing(translateY, {
        toValue: -100,
        duration: 300,
        useNativeDriver: true,
      }),
      Animated.timing(opacity, {
        toValue: 0,
        duration: 300,
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
          duration: 300,
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

  const getIndicator = () => {
    switch (type) {
      case 'success':
        return <CheckCircle2 color="#16A34A" size={28} />;
      case 'error':
        return <XCircle color="#DC2626" size={28} />;
      case 'loading':
      default:
        return <ActivityIndicator size="small" color="#16A34A" />;
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
        },
      ]}
    >
      <View style={styles.content}>
        <View style={styles.indicatorContainer}>{getIndicator()}</View>
        <View style={styles.textContainer}>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.message} numberOfLines={2}>
            {message}
          </Text>
        </View>
        <TouchableOpacity style={styles.closeButton} onPress={hideToast}>
          <X color="#999999" size={20} />
        </TouchableOpacity>
      </View>
    </Animated.View>
  );
}

const { width } = Dimensions.get('window');

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    top: Platform.OS === 'ios' ? 60 : 20,
    left: 20,
    right: 20,
    maxWidth: width - 40,
    alignSelf: 'center',
    backgroundColor: '#1A1A1A',
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.4,
    shadowRadius: 12,
    elevation: 10,
    zIndex: 9999,
    borderWidth: 1,
    borderColor: '#333333',
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 16,
  },
  indicatorContainer: {
    marginRight: 12,
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
  },
  textContainer: {
    flex: 1,
  },
  title: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.3,
    marginBottom: 2,
  },
  message: {
    color: '#999999',
    fontSize: 13,
    fontWeight: '500',
    letterSpacing: 0.2,
  },
  closeButton: {
    marginLeft: 12,
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
