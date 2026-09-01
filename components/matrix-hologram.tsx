import React, { useEffect, useMemo, useRef } from 'react';
import {
  Animated,
  AppState,
  type AppStateStatus,
  Platform,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import { Accelerometer, DeviceMotion } from 'expo-sensors';
import { useTheme } from '@/providers/theme-provider';
import {
  getStoredMotionGranted,
  needsWebMotionPermission,
  subscribeWebDeviceMotion,
} from '@/utils/web-motion-permission';

/** Half-width katakana — same set as classic Matrix rain. */
const KATAKANA =
  'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝﾞﾟ012345789';

const CHAR_LINE_HEIGHT = 15;
const COL_WIDTH = 14;
const SAMPLE_MS = 16;
const G = 9.80665;

/**
 * World-lock feel: physical meters of phone travel → pixels of wallpaper pan.
 * ~1 screen width per meter so a long slide clearly travels through the field.
 */
const PX_PER_METER = 900;
/** Extra boost — phone IMUs under-report casual slides. */
const ACCEL_BOOST = 3.4;
const STILL_G = 0.025;
const VEL_FRICTION_MOVING = 0.998;
const VEL_FRICTION_COAST = 0.9992;
const VEL_FRICTION_STILL = 0.78;
const MAX_VEL_MPS = 5.5;
const COAST_SEC = 0.55; // keep sliding through Matrix after accel drops (constant-speed portion)
const GRAV_LP = 0.1;

const RAINBOW = [
  { digit: '#C084FC', shadow: 'rgba(192, 132, 252, 0.5)' },
  { digit: '#FFFFFF', shadow: 'rgba(255, 255, 255, 0.35)' },
  { digit: '#A855F7', shadow: 'rgba(168, 85, 247, 0.45)' },
  { digit: '#F2F2F2', shadow: 'rgba(242, 242, 242, 0.32)' },
  { digit: '#E9D5FF', shadow: 'rgba(233, 213, 255, 0.4)' },
  { digit: '#E8E8E8', shadow: 'rgba(232, 232, 232, 0.3)' },
  { digit: '#7C3AED', shadow: 'rgba(124, 58, 237, 0.42)' },
  { digit: '#FAFAFA', shadow: 'rgba(250, 250, 250, 0.28)' },
];

function hash(n: number): number {
  const x = Math.sin(n * 127.1) * 43758.5453;
  return x - Math.floor(x);
}

function glyphAt(seed: number): string {
  return KATAKANA[Math.floor(hash(seed) * KATAKANA.length)] ?? 'ｱ';
}

function wrap(n: number, period: number) {
  if (period <= 0) return 0;
  return ((n % period) + period) % period;
}

type Column = {
  id: string;
  left: number;
  stream: string;
  tint: (typeof RAINBOW)[number];
  opacity: number;
};

/**
 * Static full Matrix rain tiled into an infinite wallpaper.
 * Integrates real phone translation so the field stays "stuck" in the room —
 * slide left → travel left through the Matrix; stop → it stays put.
 */
export function MatrixHologram() {
  const { theme } = useTheme();
  const { width, height } = useWindowDimensions();

  // One tile ≈ 1.6 screens — 2×2 copies for seamless infinite wrap
  const tileW = Math.ceil((width * 1.6) / COL_WIDTH) * COL_WIDTH;
  const tileH = Math.ceil((height * 1.6) / CHAR_LINE_HEIGHT) * CHAR_LINE_HEIGHT;
  const rows = Math.ceil(tileH / CHAR_LINE_HEIGHT);
  const colCount = Math.ceil(tileW / COL_WIDTH);

  const panX = useRef(new Animated.Value(0)).current;
  const panY = useRef(new Animated.Value(0)).current;

  const columns: Column[] = useMemo(() => {
    return Array.from({ length: colCount }, (_, i) => {
      const chars = Array.from({ length: rows }, (_, r) => glyphAt(i * 997 + r * 13 + 42));
      return {
        id: `c-${i}`,
        left: i * COL_WIDTH,
        stream: chars.join('\n'),
        tint: RAINBOW[i % RAINBOW.length]!,
        opacity: 0.35 + hash(i * 3.7) * 0.55,
      };
    });
  }, [colCount, rows]);

  // 2×2 tile grid for seamless wrap while traveling any distance
  const tiles = useMemo(
    () =>
      [
        { id: 't00', ox: 0, oy: 0 },
        { id: 't10', ox: tileW, oy: 0 },
        { id: 't01', ox: 0, oy: tileH },
        { id: 't11', ox: tileW, oy: tileH },
      ] as const,
    [tileW, tileH]
  );

  const wash = theme.colors.accent ? `${theme.colors.accent}10` : 'rgba(168,85,247,0.06)';
  const pxPerM = Math.max(PX_PER_METER, width * 1.15);

  useEffect(() => {
    let sub: { remove: () => void } | null = null;
    let active = true;
    let raf = 0;
    let lastTs = 0;

    // World position of the phone (meters) — Matrix is fixed at origin
    let posXM = 0;
    let posYM = 0;
    let velXM = 0;
    let velYM = 0;

    // Gravity estimate for high-pass (tilt does not accumulate travel)
    let gravX = 0;
    let gravY = 0;
    let gravZ = 0;
    let gravReady = false;
    let stillFrames = 0;
    let coastLeft = 0;

    const paint = () => {
      if (!active) return;
      // Phone +X (right) → camera right → wallpaper shifts left (world stays stuck)
      const rawX = -posXM * pxPerM;
      const rawY = posYM * pxPerM;
      panX.setValue(wrap(rawX, tileW));
      panY.setValue(wrap(rawY, tileH));
      raf = requestAnimationFrame(paint);
    };
    raf = requestAnimationFrame(paint);

    const integrate = (uxG: number, uyG: number, ts?: number) => {
      if (!active) return;
      const now = ts ?? Date.now();
      const dt = lastTs ? Math.min(0.05, Math.max(0.008, (now - lastTs) / 1000)) : SAMPLE_MS / 1000;
      lastTs = now;

      const ax = uxG * G * ACCEL_BOOST;
      const ay = uyG * G * ACCEL_BOOST;
      const moving = Math.hypot(uxG, uyG) > STILL_G;

      if (moving) {
        stillFrames = 0;
        coastLeft = COAST_SEC;
        velXM += ax * dt;
        velYM += ay * dt;
        velXM *= VEL_FRICTION_MOVING;
        velYM *= VEL_FRICTION_MOVING;
      } else if (coastLeft > 0) {
        // Constant-speed part of a slide — IMU is quiet, keep traveling
        coastLeft -= dt;
        velXM *= VEL_FRICTION_COAST;
        velYM *= VEL_FRICTION_COAST;
      } else {
        stillFrames += 1;
        velXM *= VEL_FRICTION_STILL;
        velYM *= VEL_FRICTION_STILL;
        if (stillFrames > 10) {
          velXM = 0;
          velYM = 0;
        }
      }

      const speed = Math.hypot(velXM, velYM);
      if (speed > MAX_VEL_MPS) {
        const s = MAX_VEL_MPS / speed;
        velXM *= s;
        velYM *= s;
      }

      posXM += velXM * dt;
      posYM += velYM * dt;
    };

    const onUserAccelG = (x: number, y: number, z: number, ts?: number) => {
      integrate(x, y, ts);
    };

    const onDeviceMotion = (data: {
      acceleration?: { x: number; y: number; z: number } | null;
      accelerationIncludingGravity?: { x: number; y: number; z: number };
    }) => {
      const a = data.acceleration;
      if (a && a.x != null && a.y != null) {
        // Already gravity-stripped (m/s²) → convert to g
        onUserAccelG(a.x / G, a.y / G, (a.z ?? 0) / G);
        return;
      }
      const g = data.accelerationIncludingGravity;
      if (!g) return;
      const gx = g.x / G;
      const gy = g.y / G;
      const gz = g.z / G;
      if (!gravReady) {
        gravX = gx;
        gravY = gy;
        gravZ = gz;
        gravReady = true;
        return;
      }
      gravX += (gx - gravX) * GRAV_LP;
      gravY += (gy - gravY) * GRAV_LP;
      gravZ += (gz - gravZ) * GRAV_LP;
      onUserAccelG(gx - gravX, gy - gravY, gz - gravZ);
    };

    const onAccelerometer = ({ x, y, z }: { x: number; y: number; z: number }) => {
      // Units already in g
      if (!gravReady) {
        gravX = x;
        gravY = y;
        gravZ = z;
        gravReady = true;
        return;
      }
      gravX += (x - gravX) * GRAV_LP;
      gravY += (y - gravY) * GRAV_LP;
      gravZ += (z - gravZ) * GRAV_LP;
      onUserAccelG(x - gravX, y - gravY, z - gravZ);
    };

    let webUnsub: (() => void) | null = null;

    const stop = () => {
      sub?.remove();
      sub = null;
      webUnsub?.();
      webUnsub = null;
      lastTs = 0;
    };

    const onWebSample = (sample: {
      ax: number | null;
      ay: number | null;
      az: number | null;
      gx: number;
      gy: number;
      gz: number;
    }) => {
      if (sample.ax != null && sample.ay != null) {
        onUserAccelG(sample.ax / G, sample.ay / G, (sample.az ?? 0) / G);
        return;
      }
      const gx = sample.gx / G;
      const gy = sample.gy / G;
      const gz = sample.gz / G;
      if (!gravReady) {
        gravX = gx;
        gravY = gy;
        gravZ = gz;
        gravReady = true;
        return;
      }
      gravX += (gx - gravX) * GRAV_LP;
      gravY += (gy - gravY) * GRAV_LP;
      gravZ += (gz - gravZ) * GRAV_LP;
      onUserAccelG(gx - gravX, gy - gravY, gz - gravZ);
    };

    const start = async () => {
      stop();
      gravReady = false;
      try {
        // iOS Safari/PWA: use native DeviceMotion after Enable Motion tap
        if (Platform.OS === 'web') {
          if (needsWebMotionPermission() && !getStoredMotionGranted()) return;
          webUnsub = subscribeWebDeviceMotion(onWebSample);
          return;
        }

        await DeviceMotion.requestPermissionsAsync().catch(() => null);
        await Accelerometer.requestPermissionsAsync().catch(() => null);

        const dmOk = await DeviceMotion.isAvailableAsync().catch(() => false);
        if (dmOk) {
          DeviceMotion.setUpdateInterval(SAMPLE_MS);
          sub = DeviceMotion.addListener(onDeviceMotion);
          return;
        }
        const accOk = await Accelerometer.isAvailableAsync().catch(() => false);
        if (accOk) {
          Accelerometer.setUpdateInterval(SAMPLE_MS);
          sub = Accelerometer.addListener(onAccelerometer);
        }
      } catch {
        /* no sensors */
      }
    };

    void start();

    const onMotionGranted = () => {
      void start();
    };
    if (Platform.OS === 'web' && typeof window !== 'undefined') {
      window.addEventListener('aura-motion-granted', onMotionGranted);
    }

    const onApp = (next: AppStateStatus) => {
      if (next === 'active') {
        active = true;
        velXM = 0;
        velYM = 0;
        void start();
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(paint);
      } else {
        active = false;
        stop();
        cancelAnimationFrame(raf);
      }
    };
    const appSub = AppState.addEventListener('change', onApp);

    return () => {
      active = false;
      stop();
      appSub.remove();
      cancelAnimationFrame(raf);
      if (Platform.OS === 'web' && typeof window !== 'undefined') {
        window.removeEventListener('aura-motion-granted', onMotionGranted);
      }
    };
  }, [panX, panY, tileW, tileH, pxPerM]);

  return (
    <View style={styles.root} pointerEvents="none">
      <View style={styles.void} />

      <Animated.View
        style={[
          styles.world,
          {
            width: tileW * 2,
            height: tileH * 2,
            // Center the 2×2 tile pack; wrap offset slides the lens through it
            left: -tileW * 0.5,
            top: -tileH * 0.5,
            transform: [{ translateX: panX }, { translateY: panY }],
          },
        ]}
      >
        {tiles.map((tile) => (
          <View
            key={tile.id}
            style={[styles.tile, { left: tile.ox, top: tile.oy, width: tileW, height: tileH }]}
          >
            {columns.map((col) => (
              <Text
                key={`${tile.id}-${col.id}`}
                style={[
                  styles.columnText,
                  {
                    left: col.left,
                    width: COL_WIDTH,
                    color: col.tint.digit,
                    textShadowColor: col.tint.shadow,
                    opacity: col.opacity,
                  },
                ]}
                maxFontSizeMultiplier={1.2}
              >
                {col.stream}
              </Text>
            ))}
          </View>
        ))}
      </Animated.View>

      <View style={[styles.wash, { backgroundColor: wash }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    overflow: 'hidden',
    backgroundColor: '#000000',
  },
  void: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#000000',
  },
  world: {
    position: 'absolute',
  },
  tile: {
    position: 'absolute',
    overflow: 'hidden',
  },
  columnText: {
    position: 'absolute',
    top: 0,
    fontSize: 13,
    lineHeight: CHAR_LINE_HEIGHT,
    fontWeight: '600',
    textAlign: 'center',
    fontFamily: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 6,
  },
  wash: {
    ...StyleSheet.absoluteFillObject,
  },
});
