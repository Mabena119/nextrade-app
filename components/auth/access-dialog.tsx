import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Modal } from 'react-native';
import { useTheme } from '@/providers/theme-provider';
import { type } from '@/constants/typography';

type Props = {
  visible: boolean;
  title: string;
  message?: string;
  onClose: () => void;
};

/** Minimal dialog — matches NexTrade poster auth screens. */
export function AccessDialog({ visible, title, message, onClose }: Props) {
  const { theme } = useTheme();

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <TouchableOpacity style={StyleSheet.absoluteFill} activeOpacity={1} onPress={onClose} />
        <View style={[styles.sheet, { borderColor: theme.colors.borderColor }]}>
          <Text style={[styles.title, { color: theme.colors.textPrimary }]}>{title}</Text>
          {message ? (
            <Text style={[styles.message, { color: theme.colors.textSecondary }]}>{message}</Text>
          ) : null}
          <TouchableOpacity
            style={[styles.action, { backgroundColor: theme.colors.accent }]}
            onPress={onClose}
            activeOpacity={0.85}
          >
            <Text style={[styles.actionText, { color: theme.colors.onAccent }]}>Dismiss</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.82)',
    justifyContent: 'center',
    paddingHorizontal: 28,
  },
  sheet: {
    backgroundColor: '#070708',
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 24,
    paddingVertical: 28,
  },
  title: {
    ...type.title,
    fontSize: 20,
    marginBottom: 10,
  },
  message: {
    ...type.body,
    marginBottom: 22,
  },
  action: {
    height: 48,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionText: {
    ...type.button,
    fontSize: 15,
  },
});
