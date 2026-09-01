import React, { createElement, useEffect, useRef } from 'react';
import { Platform, StyleSheet, useWindowDimensions, View } from 'react-native';

/** Same half-width set as classic Matrix rain. */
const KATAKANA =
  'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝﾞﾟ012345789';

const RAINBOW = [
  { digit: '#C084FC', glow: 'rgba(192, 132, 252, 0.55)' },
  { digit: '#FFFFFF', glow: 'rgba(255, 255, 255, 0.4)' },
  { digit: '#A855F7', glow: 'rgba(168, 85, 247, 0.5)' },
  { digit: '#F2F2F2', glow: 'rgba(242, 242, 242, 0.35)' },
  { digit: '#E9D5FF', glow: 'rgba(233, 213, 255, 0.45)' },
  { digit: '#E8E8E8', glow: 'rgba(232, 232, 232, 0.32)' },
  { digit: '#7C3AED', glow: 'rgba(124, 58, 237, 0.48)' },
  { digit: '#FAFAFA', glow: 'rgba(250, 250, 250, 0.3)' },
];

const COL_W = 14;
const LINE_H = 15;
const REPEL_RADIUS = 92;
const REPEL_FORCE = 3.2;
const SPRING = 0.014;
const DAMP = 0.935;
const DRIFT = 0.038;

function hash(n: number): number {
  const x = Math.sin(n * 127.1) * 43758.5453;
  return x - Math.floor(x);
}

type Floater = {
  char: string;
  homeX: number;
  homeY: number;
  x: number;
  y: number;
  vx: number;
  vy: number;
  phase: number;
  color: string;
  glow: string;
  opacity: number;
};

type TouchPoint = { x: number; y: number };

function buildColumnMatrix(width: number, height: number): Floater[] {
  const colCount = Math.max(10, Math.min(28, Math.floor(width / COL_W)));
  const colW = width / colCount;
  const rows = Math.max(24, Math.ceil((height + 40) / LINE_H) + 1);
  const out: Floater[] = [];

  for (let c = 0; c < colCount; c++) {
    const tint = RAINBOW[c % RAINBOW.length]!;
    const left = c * colW + colW * 0.5;
    for (let r = 0; r < rows; r++) {
      const hy = r * LINE_H;
      const i = c * 1000 + r;
      out.push({
        char: KATAKANA[Math.floor(hash(i * 7.1) * KATAKANA.length)] ?? 'ｱ',
        homeX: left,
        homeY: hy,
        x: left,
        y: hy,
        vx: (hash(i * 2.2) - 0.5) * 0.25,
        vy: (hash(i * 5.5) - 0.5) * 0.25,
        phase: hash(i * 11.1) * Math.PI * 2,
        color: tint.digit,
        glow: tint.glow,
        opacity: 0.12 + (r / Math.max(1, rows - 1)) * 0.78,
      });
    }
  }
  return out;
}

/**
 * iOS web/PWA: classic Matrix rain columns that float like water;
 * finger touch scatters glyphs.
 */
export function MatrixFloatWater() {
  const { width, height } = useWindowDimensions();
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const floatersRef = useRef<Floater[]>([]);
  const touchRef = useRef<TouchPoint | null>(null);

  useEffect(() => {
    if (Platform.OS !== 'web') return;
    const canvas = canvasRef.current;
    if (!canvas) return;

    const dpr = Math.min(typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1, 2);
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    floatersRef.current = buildColumnMatrix(width, height);

    let raf = 0;
    let alive = true;
    let t = 0;

    const step = () => {
      if (!alive) return;
      t += 0.016;
      ctx.clearRect(0, 0, width, height);
      // Transparent canvas — black comes from the scene underlay so UI can see rain through cards.

      const touch = touchRef.current;
      const list = floatersRef.current;
      ctx.font = '600 13px Menlo, Monaco, Consolas, monospace';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';

      for (let i = 0; i < list.length; i++) {
        const f = list[i]!;
        f.vx += Math.sin(t * 0.65 + f.phase) * DRIFT * 0.35;
        f.vy += Math.cos(t * 0.5 + f.phase * 1.2) * DRIFT * 0.35;
        f.vx += (f.homeX - f.x) * SPRING;
        f.vy += (f.homeY - f.y) * SPRING;

        if (touch) {
          const dx = f.x - touch.x;
          const dy = f.y - touch.y;
          const d2 = dx * dx + dy * dy;
          if (d2 < REPEL_RADIUS * REPEL_RADIUS && d2 > 0.25) {
            const d = Math.sqrt(d2);
            const push = (1 - d / REPEL_RADIUS) * REPEL_FORCE;
            f.vx += (dx / d) * push;
            f.vy += (dy / d) * push;
          }
        }

        f.vx *= DAMP;
        f.vy *= DAMP;
        f.x += f.vx;
        f.y += f.vy;

        ctx.globalAlpha = f.opacity;
        ctx.shadowColor = f.glow;
        ctx.shadowBlur = 6;
        ctx.fillStyle = f.color;
        ctx.fillText(f.char, f.x, f.y);
      }

      ctx.globalAlpha = 1;
      ctx.shadowBlur = 0;
      // Soft purple wash (transparent, not opaque fill)
      ctx.fillStyle = 'rgba(168, 85, 247, 0.05)';
      ctx.fillRect(0, 0, width, height);

      raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);

    const readTouch = (e: TouchEvent) => {
      const touch = e.touches[0];
      if (!touch) return;
      touchRef.current = { x: touch.clientX, y: touch.clientY };
    };
    const clear = () => {
      touchRef.current = null;
    };
    window.addEventListener('touchstart', readTouch, { passive: true });
    window.addEventListener('touchmove', readTouch, { passive: true });
    window.addEventListener('touchend', clear, { passive: true });
    window.addEventListener('touchcancel', clear, { passive: true });

    return () => {
      alive = false;
      cancelAnimationFrame(raf);
      window.removeEventListener('touchstart', readTouch);
      window.removeEventListener('touchmove', readTouch);
      window.removeEventListener('touchend', clear);
      window.removeEventListener('touchcancel', clear);
    };
  }, [width, height]);

  if (Platform.OS !== 'web') {
    return <View style={styles.root} />;
  }

  return createElement('canvas', {
    ref: canvasRef,
    style: {
      position: 'absolute',
      left: 0,
      top: 0,
      width: '100%',
      height: '100%',
      display: 'block',
      backgroundColor: 'transparent',
      pointerEvents: 'none',
    },
  });
}

/** Water float rain on all web/PWA (touch-repel). Native uses classic columns. */
export function shouldUseMatrixFloatWater(): boolean {
  return Platform.OS === 'web';
}

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'transparent',
  },
});
