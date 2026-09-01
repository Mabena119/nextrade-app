import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image, Platform } from 'react-native';
import { BlurView } from 'expo-blur';
import { LuxPulse } from '@/components/aura';
import { lux } from '@/constants/aura-ui';
import { useTheme } from '@/providers/theme-provider';

type Props = {
  name: string;
  imageUri?: string | null;
  /** Mentor / owner display name (replaces legacy "AI xx%" badge). */
  ownerName?: string | null;
  index?: number;
  onPress: () => void;
  testID?: string;
};

/** Independent floating bot module for workspace fleet */
export function BotModuleCard({
  name,
  imageUri,
  ownerName,
  index = 0,
  onPress,
  testID,
}: Props) {
  const { theme } = useTheme();
  const stagger = (index % 3) * 6;
  const accent = theme.colors.accent;

  return (
    <TouchableOpacity
      testID={testID}
      onPress={onPress}
      activeOpacity={0.82}
      style={[
        styles.card,
        lux.shadow.float,
        {
          marginLeft: stagger,
          borderColor: theme.colors.borderColor,
          backgroundColor: theme.colors.backgroundSecondary,
          shadowColor: theme.colors.glowColor,
        },
      ]}
    >
      {Platform.OS === 'ios' && (
        <BlurView intensity={24} tint="dark" style={StyleSheet.absoluteFill} />
      )}
      <View style={styles.row}>
        <View style={[styles.avatarShell, { borderColor: `${accent}44` }]}>
          {imageUri ? (
            <Image source={{ uri: imageUri }} style={styles.avatar} />
          ) : (
            <View style={styles.avatarFallback}>
              <View style={[styles.eye, { backgroundColor: accent }]} />
              <View style={[styles.eye, { backgroundColor: accent }]} />
            </View>
          )}
        </View>
        <View style={styles.meta}>
          <Text style={[styles.name, { color: theme.colors.textPrimary }]} numberOfLines={1}>
            {name}
          </Text>
          <View style={styles.metrics}>
            <Text style={[styles.confidence, { color: theme.colors.textSecondary }]} numberOfLines={1}>
              {(ownerName || '').trim() || 'Aura mentor'}
            </Text>
            <View style={styles.live}>
              <LuxPulse active />
              <Text style={[styles.liveText, { color: theme.colors.success }]}>Live</Text>
            </View>
          </View>
        </View>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: lux.radius.lg,
    borderWidth: 1,
    marginBottom: 12,
    overflow: 'hidden',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 18,
    gap: 14,
  },
  avatarShell: {
    width: 48,
    height: 48,
    borderRadius: 999,
    overflow: 'hidden',
    borderWidth: 1.5,
    backgroundColor: '#0A0D14',
  },
  avatar: { width: 48, height: 48 },
  avatarFallback: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  eye: {
    width: 4,
    height: 4,
    borderRadius: 2,
  },
  meta: { flex: 1, minWidth: 0 },
  name: {
    fontSize: 16,
    fontWeight: '600',
    letterSpacing: -0.2,
  },
  metrics: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 6,
  },
  confidence: {
    fontSize: 12,
    fontWeight: '500',
  },
  live: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  liveText: {
    fontSize: 11,
    fontWeight: '600',
  },
});
