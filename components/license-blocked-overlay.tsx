import React from 'react';
import { View, Text, StyleSheet, Platform } from 'react-native';
import { BlurView } from 'expo-blur';

interface LicenseBlockedOverlayProps {
  label?: string;
  /** Leave the bottom-right Remove control tappable (home robot hero). */
  preserveRemoveButton?: boolean;
}

/** Height of the bottom action row (Start / Quotes / Remove). */
const CONTROL_ROW_HEIGHT = 96;
/** Right slot width ratio for the Remove button (3 equal flex buttons). */
const REMOVE_SLOT_RATIO = 1 / 3;

/** Blur + label when the active robot licence is expired (blocks interaction). */
export function LicenseBlockedOverlay({
  label = 'EXPIRED',
  preserveRemoveButton = false,
}: LicenseBlockedOverlayProps) {
  return (
    <View style={styles.overlay} pointerEvents="box-none">
      {Platform.OS === 'ios' ? (
        <BlurView intensity={85} tint="dark" style={StyleSheet.absoluteFill} pointerEvents="none" />
      ) : (
        <View style={[StyleSheet.absoluteFill, styles.androidScrim]} pointerEvents="none" />
      )}
      <Text style={styles.label} pointerEvents="none">
        {label}
      </Text>
      {preserveRemoveButton ? (
        <>
          <View style={styles.blockAboveControls} pointerEvents="auto" />
          <View style={styles.blockStartAndQuotes} pointerEvents="auto" />
        </>
      ) : (
        <View style={StyleSheet.absoluteFill} pointerEvents="auto" />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  overlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 30,
    overflow: 'hidden',
  },
  androidScrim: {
    backgroundColor: 'rgba(0,0,0,0.62)',
  },
  label: {
    color: '#FF5252',
    fontSize: 26,
    fontWeight: '800',
    letterSpacing: 3,
    zIndex: 1,
    textAlign: 'center',
  },
  blockAboveControls: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: CONTROL_ROW_HEIGHT,
  },
  blockStartAndQuotes: {
    position: 'absolute',
    left: 0,
    bottom: 0,
    height: CONTROL_ROW_HEIGHT,
    width: `${(1 - REMOVE_SLOT_RATIO) * 100}%`,
  },
});
