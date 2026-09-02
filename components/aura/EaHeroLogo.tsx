import React, { useEffect, useMemo, useState } from 'react';
import { StyleSheet } from 'react-native';
import { Image } from 'expo-image';
import {
  EA_BRAND_CDN_HEADERS,
  EA_BRAND_HERO_LOCAL,
  resolveEaOwnerProfileLogoUrl,
} from '@/utils/ea-brand-image';

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
 */
export function EaHeroLogo({ ownerLogo, imageUrl, size, testID }: Props) {
  const rawLogo = ownerLogo ?? imageUrl;
  const remoteUrl = useMemo(() => resolveEaOwnerProfileLogoUrl(rawLogo), [rawLogo]);
  const [useFallback, setUseFallback] = useState(() => !remoteUrl);

  useEffect(() => {
    setUseFallback(!remoteUrl);
  }, [remoteUrl]);

  const source =
    !useFallback && remoteUrl
      ? { uri: remoteUrl, headers: EA_BRAND_CDN_HEADERS }
      : EA_BRAND_HERO_LOCAL;
  const contentFit = !useFallback && remoteUrl ? 'cover' : 'contain';
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
      transition={180}
      onError={() => setUseFallback(true)}
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
