import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image } from 'react-native';
import { ChevronRight } from 'lucide-react-native';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';

type Props = {
  name: string;
  imageUri?: string | null;
  ownerName?: string | null;
  index?: number;
  onPress: () => void;
  testID?: string;
};

export function BotModuleCard({
  name,
  imageUri,
  ownerName,
  onPress,
  testID,
}: Props) {
  const { theme } = useTheme();

  return (
    <TouchableOpacity
      testID={testID}
      onPress={onPress}
      activeOpacity={0.82}
      style={[styles.card, { borderColor: authColors.cardBorder, backgroundColor: authColors.card }]}
    >
      <View style={[styles.avatarShell, { borderColor: authColors.cardBorder }]}>
        {imageUri ? (
          <Image source={{ uri: imageUri }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatarFallback, { backgroundColor: authColors.inputBg }]}>
            <Text style={[styles.avatarInitial, { color: theme.colors.accent }]}>
              {name.trim().charAt(0).toUpperCase() || 'A'}
            </Text>
          </View>
        )}
      </View>
      <View style={styles.meta}>
        <Text style={[styles.name, { color: theme.colors.textPrimary }]} numberOfLines={1}>
          {name}
        </Text>
        <Text style={[styles.owner, { color: theme.colors.textMuted }]} numberOfLines={1}>
          {(ownerName || '').trim() || 'Mentor'}
        </Text>
      </View>
      <ChevronRight color={theme.colors.textMuted} size={18} strokeWidth={1.8} />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 18,
    borderWidth: 1,
    marginBottom: 10,
    paddingVertical: 14,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  avatarShell: {
    width: 44,
    height: 44,
    borderRadius: 14,
    overflow: 'hidden',
    borderWidth: 1,
  },
  avatar: { width: 44, height: 44 },
  avatarFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarInitial: {
    ...type.title,
    fontSize: 18,
  },
  meta: { flex: 1, minWidth: 0 },
  name: {
    ...type.bodyMedium,
    fontSize: 16,
    letterSpacing: -0.2,
  },
  owner: {
    ...type.caption,
    marginTop: 2,
  },
});
