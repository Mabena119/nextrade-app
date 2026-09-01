import React, { useCallback, useMemo, useState } from 'react';
import {
  StyleSheet,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import type { EA } from '@/providers/app-provider';
import { EABrandProfileMedia } from '@/components/ea-brand-profile-media';
import { resolveEaOwnerLogoUrl, EA_BRAND_HERO_LOCAL } from '@/utils/ea-brand-image';

interface EABrandBackdropProps {
  children: React.ReactNode;
  primaryEA: EA | null;
  accentColor: string;
  glowColor: string;
  style?: StyleProp<ViewStyle>;
}

/**
 * Still logo splash for brand-shell themes (Aura).
 * Glass / Black video is mounted on Home like EA Trade — not under the navigator.
 */
export function EABrandBackdrop({
  children,
  primaryEA,
  accentColor,
  glowColor,
  style,
}: EABrandBackdropProps) {
  const rawLogo = primaryEA?.userData?.owner?.logo;
  const [forceFallback, setForceFallback] = useState(false);

  const brandImageUrl = useMemo(() => {
    if (forceFallback) return null;
    return resolveEaOwnerLogoUrl(rawLogo);
  }, [rawLogo, forceFallback]);

  const onError = useCallback(() => {
    setForceFallback(true);
  }, []);

  return (
    <View style={[styles.root, style]}>
      <View style={StyleSheet.absoluteFill} pointerEvents="none">
        <EABrandProfileMedia
          key="shell-still-bg"
          fillParent
          brandImageUrl={brandImageUrl}
          photoUnavailable={forceFallback || !brandImageUrl}
          preferLoopingVideo={false}
          contentFit="cover"
          fallbackContentFit="cover"
          mediaStyle={styles.media}
          onPhotoError={onError}
          fallbackSource={EA_BRAND_HERO_LOCAL}
          testIDPhoto="glass-backdrop-still"
          testIDVideo="glass-backdrop-video"
        />
        <LinearGradient
          colors={['rgba(0, 0, 0, 0.22)', 'rgba(0, 0, 0, 0.55)', 'rgba(0, 0, 0, 0.80)']}
          locations={[0, 0.5, 1]}
          style={StyleSheet.absoluteFill}
          start={{ x: 0.5, y: 0 }}
          end={{ x: 0.5, y: 1 }}
        />
        <LinearGradient
          colors={[`${accentColor}18`, 'transparent', `${glowColor}14`]}
          style={StyleSheet.absoluteFill}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          pointerEvents="none"
        />
      </View>
      <View style={styles.content} pointerEvents="box-none">
        {children}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#000000',
  },
  media: {
    width: '100%',
    height: '100%',
  },
  content: {
    flex: 1,
  },
});
