import React, { useEffect, useMemo, useState } from 'react';
import { StyleSheet } from 'react-native';
import { Image } from 'expo-image';
import { EA_BRAND_HERO_LOCAL, resolveEaOwnerLogoUrl } from '@/utils/ea-brand-image';

type Props = {
  imageUrl?: string | null;
  size: number;
  testID?: string;
};

/**
 * Home hero logo — mentor upload when available, otherwise NexTrade mark.
 * Plain image (no video crossfade) so the logo never disappears on a black frame.
 */
export function EaHeroLogo({ imageUrl, size, testID }: Props) {
  const remoteUrl = useMemo(() => resolveEaOwnerLogoUrl(imageUrl), [imageUrl]);
  const [useFallback, setUseFallback] = useState(() => !remoteUrl);

  useEffect(() => {
    setUseFallback(!remoteUrl);
  }, [remoteUrl]);

  const source = !useFallback && remoteUrl ? { uri: remoteUrl } : EA_BRAND_HERO_LOCAL;
  const contentFit = !useFallback && remoteUrl ? 'cover' : 'contain';
  const displayScale = contentFit === 'contain' ? 1.2 : 1.06;

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
