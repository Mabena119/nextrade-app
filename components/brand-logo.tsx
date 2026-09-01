import React from 'react';
import { Image, View, type StyleProp, type ViewStyle } from 'react-native';

type Props = {
  size?: number;
  glow?: boolean;
  style?: StyleProp<ViewStyle>;
  testID?: string;
};

/** Robot N-mark — no extra chrome. */
export function BrandLogo({ size = 240, style, testID }: Props) {
  return (
    <View style={[{ width: size, height: size, alignItems: 'center', justifyContent: 'center' }, style]}>
      <Image
        source={require('@/assets/images/nextrade-logo.png')}
        style={{ width: size, height: size }}
        resizeMode="contain"
        accessibilityLabel="NexTradeAI"
        testID={testID}
      />
    </View>
  );
}
