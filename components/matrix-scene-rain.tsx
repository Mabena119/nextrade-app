import React from 'react';
import { View, StyleSheet, type StyleProp, type ViewStyle } from 'react-native';
import { isMatrixStyleTheme, type ThemeName, useTheme } from '@/providers/theme-provider';
import { MatrixBackground } from '@/components/matrix-background';
import {
  MatrixFloatWater,
  shouldUseMatrixFloatWater,
} from '@/components/matrix-float-water';

type Props = {
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  /** When true, skip rain canvas so MT5/auth WebViews are not starved. */
  pauseRain?: boolean;
};

/**
 * Matrix theme: floating water rain (web) or classic purple/white columns.
 * Scene stays black; rain shows through transparent UI cards.
 */
export function MatrixSceneRain({ children, style, pauseRain = false }: Props) {
  const { themeName } = useTheme();
  if (!isMatrixStyleTheme(themeName as ThemeName)) {
    return <>{children}</>;
  }

  const useWater = shouldUseMatrixFloatWater();

  return (
    <View style={[styles.fill, style]}>
      {!pauseRain && (
        <View style={styles.rainLayer} pointerEvents="none">
          {useWater ? (
            <MatrixFloatWater />
          ) : (
            <MatrixBackground variant="overlay" rainTint="rainbow" />
          )}
        </View>
      )}
      <View style={styles.uiLayer} pointerEvents="box-none">
        {children}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
    backgroundColor: '#000000',
    position: 'relative',
  },
  rainLayer: {
    ...StyleSheet.absoluteFillObject,
    zIndex: 0,
    backgroundColor: 'transparent',
  },
  uiLayer: {
    flex: 1,
    zIndex: 1,
    backgroundColor: 'transparent',
  },
});
