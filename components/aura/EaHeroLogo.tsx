import React, { useEffect, useMemo, useState } from 'react';
import { StyleSheet } from 'react-native';
import { Image } from 'expo-image';
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
  /** Raw `owner.logo` from licence auth (basename, path, or full URL). */
  ownerLogo?: string | null;
  /** @deprecated pass `ownerLogo` instead */
  imageUrl?: string | null;
  size: number;
  testID?: string;
};

/**
 * Home hero logo — mentor profile photo when set, otherwise NexTrade app logo.
 * Pulls the remote once into app cache (`file://`) so focus/refresh never blanks the frame.
 */
export function EaHeroLogo({ ownerLogo, imageUrl, size, testID }: Props) {
  const rawLogo = ownerLogo ?? imageUrl;
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
    if (syncHit) {
      setCachedUri(syncHit);
    }

    let cancelled = false;
    void ensureEaBrandLogoCached(remoteUrl, stem)
      .then((uri) => {
        if (!cancelled) setCachedUri(uri);
      })
      .catch(() => {
        // Keep prior cached/local — never clear to empty on network failure.
      });

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
  const contentFit = showingMentor ? 'cover' : 'contain';
  const displayScale = contentFit === 'contain' ? 1.42 : 1;

  return (
    <Image
      testID={testID}
      source={source}
      style={[
        styles.image,
        {
          width: size,
          height: size,
          transform: [{ scale: displayScale }],
        },
      ]}
      contentFit={contentFit}
      transition={0}
      cachePolicy="memory-disk"
      cacheKey={stem ?? 'fallback'}
      placeholder={EA_BRAND_HERO_LOCAL}
      placeholderContentFit="contain"
      recyclingKey={stem ?? 'fallback'}
      accessibilityLabel="Automation logo"
    />
  );
}

const styles = StyleSheet.create({
  image: {
    width: '100%',
    height: '100%',
  },
});
