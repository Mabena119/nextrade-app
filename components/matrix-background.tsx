import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Animated,
  Easing,
  Platform,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';

/** Half-width katakana used for classic Matrix-style rain. */
const KATAKANA =
  'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝﾞﾟ012345789';

function randomKatakana(): string {
  return KATAKANA[Math.floor(Math.random() * KATAKANA.length)] ?? 'ｱ';
}

/** Minimum lines per stream tile; real count scales with window height to fill the full screen. */
const MIN_CHAR_ROWS = 24;
const CHAR_LINE_HEIGHT = 15;
const SEGMENT_EXTRA_PAD = 64;
/** Pixels of vertical travel per second so motion stays “continuous” on tall phones. */
const SCROLL_PX_PER_SEC = 44;

type ColumnProps = {
  left: number;
  width: number;
  screenHeight: number;
  speedMs: number;
  charStream: string[];
  delayMs: number;
  digitColor: string;
  textShadowColor: string;
};

function MatrixColumn({
  left,
  width,
  screenHeight,
  speedMs,
  charStream,
  delayMs,
  digitColor,
  textShadowColor,
}: ColumnProps) {
  const shift = useRef(new Animated.Value(0)).current;
  const segmentHeight = charStream.length * CHAR_LINE_HEIGHT;
  const loopRef = useRef<Animated.CompositeAnimation | null>(null);
  /** Glyphs flip over time so the rain feels alive (scroll animation stays smooth). */
  const [glyphs, setGlyphs] = useState(() => charStream);

  useEffect(() => {
    setGlyphs(charStream);
  }, [charStream]);

  useEffect(() => {
    const period = 4500 + (delayMs % 5) * 200;
    const t = setInterval(() => {
      setGlyphs((prev) =>
        prev.map((c) => (Math.random() < 0.08 ? randomKatakana() : c))
      );
    }, period);
    return () => clearInterval(t);
  }, [delayMs, charStream.length]);

  useEffect(() => {
    let cancelled = false;
    shift.setValue(0);
    // Chain timings on completion (more reliable than Animated.loop on some devices / RN versions).
    const runSegment = () => {
      if (cancelled) return;
      shift.setValue(0);
      const timing = Animated.timing(shift, {
        toValue: 1,
        duration: speedMs,
        easing: Easing.linear,
        useNativeDriver: true,
        isInteraction: false,
      });
      loopRef.current = timing;
      timing.start(({ finished }) => {
        if (cancelled) return;
        if (finished) {
          runSegment();
        } else {
          // Interrupted elsewhere — resume next frame so motion does not stay stopped
          requestAnimationFrame(() => {
            if (!cancelled) runSegment();
          });
        }
      });
    };
    const timer = setTimeout(runSegment, delayMs);
    return () => {
      cancelled = true;
      clearTimeout(timer);
      try {
        shift.stopAnimation();
      } catch {
        /* noop */
      }
      if (loopRef.current) {
        try {
          loopRef.current.stop?.();
        } catch {
          /* noop */
        }
        loopRef.current = null;
      }
    };
  }, [shift, speedMs, delayMs, segmentHeight]);

  const translateY = shift.interpolate({
    inputRange: [0, 1],
    outputRange: [0, -segmentHeight],
  });

  return (
    <View style={[styles.column, { left, width, height: screenHeight }]} pointerEvents="none">
      <Animated.View style={{ transform: [{ translateY }] }}>
        {glyphs.map((c, i) => (
          <Text
            key={`a-${i}`}
            style={[
              styles.digit,
              {
                color: digitColor,
                textShadowColor,
                opacity: 0.12 + (i / glyphs.length) * 0.78,
              },
            ]}
            maxFontSizeMultiplier={1.2}
          >
            {c}
          </Text>
        ))}
        {glyphs.map((c, i) => (
          <Text
            key={`b-${i}`}
            style={[
              styles.digit,
              {
                color: digitColor,
                textShadowColor,
                opacity: 0.12 + (i / glyphs.length) * 0.78,
              },
            ]}
            maxFontSizeMultiplier={1.2}
          >
            {c}
          </Text>
        ))}
      </Animated.View>
    </View>
  );
}

export type MatrixBackgroundVariant = 'overlay' | 'sheet';

export type MatrixRainTint = 'green' | 'red' | 'purple' | 'rainbow';

const MATRIX_RAIN_TINT: Record<
  Exclude<MatrixRainTint, 'rainbow'>,
  { digit: string; textShadow: string }
> = {
  green: { digit: '#00FF66', textShadow: 'rgba(0, 255, 102, 0.35)' },
  red: { digit: '#B80000', textShadow: 'rgba(184, 0, 0, 0.42)' },
  purple: { digit: '#C084FC', textShadow: 'rgba(192, 132, 252, 0.42)' },
};

const RAINBOW_HUES = [
  { digit: '#C084FC', textShadow: 'rgba(192, 132, 252, 0.5)' },
  { digit: '#FFFFFF', textShadow: 'rgba(255, 255, 255, 0.35)' },
  { digit: '#A855F7', textShadow: 'rgba(168, 85, 247, 0.48)' },
  { digit: '#F2F2F2', textShadow: 'rgba(242, 242, 242, 0.32)' },
  { digit: '#E9D5FF', textShadow: 'rgba(233, 213, 255, 0.42)' },
  { digit: '#E8E8E8', textShadow: 'rgba(232, 232, 232, 0.3)' },
  { digit: '#7C3AED', textShadow: 'rgba(124, 58, 237, 0.45)' },
  { digit: '#FAFAFA', textShadow: 'rgba(250, 250, 250, 0.28)' },
];

type MatrixBackgroundProps = {
  /**
   * `overlay` — transparent root, only green glyphs; draw on top of opaque black UI (pointerEvents none).
   * `sheet` — includes black fill (legacy / standalone).
   */
  variant?: MatrixBackgroundVariant;
  /** `rainbow` = purple + white katakana rain; `red` / `green` = solid tint. */
  rainTint?: MatrixRainTint;
};

/**
 * Matrix rain — scrolling half-width katakana. Used inside `MatrixSceneRain`.
 */
export function MatrixBackground({ variant = 'overlay', rainTint = 'green' }: MatrixBackgroundProps) {
  const { width, height } = useWindowDimensions();
  const colCount = Math.max(10, Math.min(28, Math.floor(width / 14)));
  const colW = width / colCount;

  /** One segment must be at least full viewport so rain covers the entire screen; two copies loop seamlessly. */
  const rowsPerSegment = useMemo(
    () => Math.max(MIN_CHAR_ROWS, Math.ceil((height + SEGMENT_EXTRA_PAD) / CHAR_LINE_HEIGHT) + 1),
    [height]
  );
  const segmentPx = rowsPerSegment * CHAR_LINE_HEIGHT;
  const baseScrollMs = useMemo(
    () => Math.max(2200, Math.round((segmentPx / SCROLL_PX_PER_SEC) * 1000)),
    [segmentPx]
  );

  const columns = useMemo(() => {
    return Array.from({ length: colCount }, (_, i) => {
      const stream = Array.from({ length: rowsPerSegment }, () => randomKatakana());
      const speedMs = Math.round(
        baseScrollMs * (0.9 + (i % 8) * 0.02 + (i * 7) / 2000)
      );
      const delayMs = (i * 37) % 800 + (i % 4) * 90;
      const tint =
        rainTint === 'rainbow'
          ? RAINBOW_HUES[i % RAINBOW_HUES.length]
          : MATRIX_RAIN_TINT[rainTint];
      return { stream, speedMs, delayMs, key: i, tint };
    });
  }, [colCount, rowsPerSegment, baseScrollMs, rainTint]);

  const rootStyle = variant === 'sheet' ? styles.rootSheet : styles.rootOverlay;

  return (
    <View
      style={[StyleSheet.absoluteFill, rootStyle]}
      pointerEvents="none"
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
    >
      {columns.map((c) => {
        const left = c.key * colW;
        const columnWidth = c.key === colCount - 1 ? width - left : colW;
        return (
          <MatrixColumn
            key={c.key}
            left={left}
            width={columnWidth}
            screenHeight={height}
            speedMs={c.speedMs}
            charStream={c.stream}
            delayMs={c.delayMs}
            digitColor={c.tint.digit}
            textShadowColor={c.tint.textShadow}
          />
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  rootOverlay: {
    zIndex: 0,
    backgroundColor: 'transparent',
  },
  rootSheet: {
    zIndex: 0,
    backgroundColor: '#000000',
  },
  column: {
    position: 'absolute',
    top: 0,
    overflow: 'hidden',
  },
  digit: {
    fontSize: 12,
    lineHeight: CHAR_LINE_HEIGHT,
    textAlign: 'center',
    fontWeight: '600',
    fontFamily: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 3,
  },
});
