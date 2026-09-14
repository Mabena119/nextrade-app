import React, { useEffect, useMemo, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Image } from 'expo-image';
import { ChevronRight } from 'lucide-react-native';
import { authColors } from '@/constants/auth-layout';
import { type } from '@/constants/typography';
import { useTheme } from '@/providers/theme-provider';
import {
  EA_BRAND_CDN_HEADERS,
  EA_BRAND_HERO_LOCAL,
  resolveEaOwnerProfileLogoUrl,
} from '@/utils/ea-brand-image';
import {
  deriveEaBrandLogoCacheStem,
  ensureEaBrandLogoCached,
  getCachedEaBrandLogoUriSync,
} from '@/utils/ea-brand-logo-cache';

type Props = {
  name: string;
  /** Raw `owner.logo` from licence auth. */
  ownerLogo?: string | null;
  /** @deprecated use ownerLogo */
  imageUri?: string | null;
  ownerName?: string | null;
  index?: number;
  onPress: () => void;
  testID?: string;
};

export function BotModuleCard({
  name,
  ownerLogo,
  imageUri,
  ownerName,
  onPress,
  testID,
}: Props) {
  const { theme } = useTheme();
  const rawLogo = ownerLogo ?? imageUri;
  const remoteUrl = useMemo(() => resolveEaOwnerProfileLogoUrl(rawLogo), [rawLogo]);
  const stem = useMemo(
    () => deriveEaBrandLogoCacheStem(rawLogo, remoteUrl),
    [rawLogo, remoteUrl]
  );
  const [cachedUri, setCachedUri] = useState<string | null>(() =>
    stem ? getCachedEaBrandLogoUriSync(stem) : null
  );

  useEffect(() => {
    if (!remoteUrl || !stem) {
      setCachedUri(null);
      return;
    }
    const syncHit = getCachedEaBrandLogoUriSync(stem);
    if (syncHit) setCachedUri(syncHit);

    let cancelled = false;
    void ensureEaBrandLogoCached(remoteUrl, stem)
      .then((uri) => {
        if (!cancelled) setCachedUri(uri);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [remoteUrl, stem]);

  const showingMentor = Boolean(cachedUri);
  const source = showingMentor
    ? cachedUri!.startsWith('file://') || cachedUri!.startsWith('/')
      ? { uri: cachedUri! }
      : { uri: cachedUri!, headers: EA_BRAND_CDN_HEADERS }
    : EA_BRAND_HERO_LOCAL;

  return (
    <TouchableOpacity
      testID={testID}
      onPress={onPress}
      activeOpacity={0.82}
      style={[styles.card, { borderColor: authColors.cardBorder, backgroundColor: authColors.card }]}
    >
      <View style={[styles.avatarShell, { borderColor: authColors.cardBorder }]}>
        <Image
          source={source}
          style={styles.avatar}
          contentFit="cover"
          transition={0}
          cachePolicy="memory-disk"
          cacheKey={stem ?? 'fallback'}
          placeholder={EA_BRAND_HERO_LOCAL}
          recyclingKey={stem ?? 'fallback'}
        />
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
  avatar: {
    width: '100%',
    height: '100%',
  },
  meta: {
    flex: 1,
    minWidth: 0,
  },
  name: {
    ...type.bodyStrong,
    marginBottom: 2,
  },
  owner: {
    ...type.caption,
  },
});
