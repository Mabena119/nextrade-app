import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { Platform, Vibration } from 'react-native';
import { Accelerometer } from 'expo-sensors';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  getStoredMotionGranted,
  needsWebMotionPermission,
  subscribeWebDeviceMotion,
} from '@/utils/web-motion-permission';

export interface ThemeColors {
  background: string;
  backgroundSecondary: string;
  cardBackground: string;

  primaryGradient: string[];
  cardGradient: string[];
  glowGradient: string[];

  textPrimary: string;
  textSecondary: string;
  textMuted: string;

  accent: string;
  onAccent: string;
  accentSecondary: string;
  success: string;
  error: string;
  warning: string;

  borderColor: string;
  glowColor: string;
  overlayColor: string;

  statusActive: string;
  statusInactive: string;

  navBackground: string;
  navActiveColor: string;
  navInactiveColor: string;
}

export interface Theme {
  name: string;
  colors: ThemeColors;
  isDark: boolean;
  /** Minimal hero scrims + optional looping profile video on Home. */
  minimalHero?: boolean;
}

/** Default — matches marketing site: void black, electric blue accent, Outfit ink/muted text. */
export const nextradeTheme: Theme = {
  name: 'nextrade',
  isDark: true,
  colors: {
    background: '#000000',
    backgroundSecondary: '#070708',
    cardBackground: 'rgba(255, 255, 255, 0.04)',

    primaryGradient: ['#070708', '#041018', '#000000'],
    cardGradient: ['rgba(7, 7, 8, 0.94)', 'rgba(7, 7, 8, 0.78)', 'rgba(0, 0, 0, 0.88)'],
    glowGradient: ['rgba(0, 168, 255, 0.28)', 'rgba(0, 168, 255, 0.1)', 'transparent'],

    textPrimary: '#F2F4F7',
    textSecondary: '#8B95A5',
    textMuted: 'rgba(139, 149, 165, 0.72)',

    accent: '#00A8FF',
    onAccent: '#000000',
    accentSecondary: '#5EF6FF',
    success: '#3AE374',
    error: '#FF5C7A',
    warning: '#FBBF24',

    borderColor: 'rgba(255, 255, 255, 0.08)',
    glowColor: 'rgba(0, 168, 255, 0.35)',
    overlayColor: 'rgba(0, 0, 0, 0.88)',

    statusActive: '#3AE374',
    statusInactive: 'rgba(139, 149, 165, 0.45)',

    navBackground: 'rgba(12, 12, 14, 0.92)',
    navActiveColor: '#00A8FF',
    navInactiveColor: 'rgba(139, 149, 165, 0.55)',
  },
};

/** Quiet — same site palette with white CTAs and charcoal hero scrims (AMOLED-friendly). */
export const quietTheme: Theme = {
  name: 'quiet',
  isDark: true,
  minimalHero: true,
  colors: {
    background: '#000000',
    backgroundSecondary: '#070708',
    cardBackground: 'rgba(255, 255, 255, 0.05)',

    primaryGradient: ['#111111', '#070707', '#000000'],
    cardGradient: ['rgba(255, 255, 255, 0.08)', 'rgba(255, 255, 255, 0.04)', 'rgba(255, 255, 255, 0.02)'],
    glowGradient: ['rgba(255, 255, 255, 0.12)', 'rgba(255, 255, 255, 0.05)', 'transparent'],

    textPrimary: '#F2F4F7',
    textSecondary: '#8B95A5',
    textMuted: 'rgba(139, 149, 165, 0.65)',

    accent: '#F2F4F7',
    onAccent: '#000000',
    accentSecondary: '#FFFFFF',
    success: '#3AE374',
    error: '#FF5C7A',
    warning: '#FBBF24',

    borderColor: 'rgba(255, 255, 255, 0.08)',
    glowColor: 'rgba(255, 255, 255, 0.2)',
    overlayColor: 'rgba(0, 0, 0, 0.9)',

    statusActive: '#3AE374',
    statusInactive: 'rgba(139, 149, 165, 0.4)',

    navBackground: 'rgba(0, 0, 0, 0.96)',
    navActiveColor: '#F2F4F7',
    navInactiveColor: 'rgba(139, 149, 165, 0.45)',
  },
};

/** @deprecated use `nextradeTheme` */
export const auraTheme = nextradeTheme;

export const ALL_THEMES: Theme[] = [nextradeTheme, quietTheme];

export type ThemeName = 'nextrade' | 'quiet';

const LEGACY_THEME_MAP: Record<string, ThemeName> = {
  aura: 'nextrade',
  cyber: 'nextrade',
  purple: 'nextrade',
  red: 'nextrade',
  matrixRed: 'nextrade',
  matrixYellow: 'nextrade',
  glass: 'nextrade',
  matrix: 'nextrade',
  matrixLogo: 'nextrade',
  eaBrand: 'nextrade',
  sunrise: 'nextrade',
  ocean: 'nextrade',
  mint: 'nextrade',
  lime: 'nextrade',
  white: 'quiet',
  pink: 'nextrade',
  black: 'quiet',
};

export function normalizeThemeName(raw: string | null | undefined): ThemeName {
  if (!raw) return 'nextrade';
  const mapped = LEGACY_THEME_MAP[raw] ?? raw;
  return ALL_THEMES.some((t) => t.name === mapped) ? (mapped as ThemeName) : 'nextrade';
}

/** @deprecated matrix themes removed — always false. */
export function isMatrixStyleTheme(_themeName: string): boolean {
  return false;
}

/** @deprecated */
export const MATRIX_WINDOW_BG = '#000000';

export function getScreenBackgroundColor(theme: Theme, _themeName?: ThemeName): string {
  return theme.colors.background || '#000000';
}

interface ThemeContextType {
  theme: Theme;
  themeName: ThemeName;
  toggleTheme: () => void;
  setTheme: (themeName: ThemeName) => void;
  isShakeEnabled: boolean;
  setShakeEnabled: (enabled: boolean) => void;
  allThemes: Theme[];
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

const SHAKE_THRESHOLD = 2.5;
const SHAKE_TIMEOUT = 300;
const REQUIRED_SHAKES = 3;

interface ThemeProviderProps {
  children: React.ReactNode;
}

const THEME_STORAGE_KEY = '@ea_trade_theme';

export const ThemeProvider: React.FC<ThemeProviderProps> = ({ children }) => {
  const [currentTheme, setCurrentTheme] = useState<Theme>(nextradeTheme);
  const [isShakeEnabled, setShakeEnabled] = useState(true);

  const lastShakeTime = useRef<number>(0);
  const shakeCount = useRef<number>(0);
  const subscription = useRef<any>(null);

  useEffect(() => {
    const loadSavedTheme = async () => {
      try {
        const rawSaved = await AsyncStorage.getItem(THEME_STORAGE_KEY);
        const savedThemeName = normalizeThemeName(rawSaved);
        const savedTheme = ALL_THEMES.find((t) => t.name === savedThemeName) ?? nextradeTheme;
        setCurrentTheme(savedTheme);
        console.log(`🎨 Loaded saved theme: ${savedTheme.name}`);
      } catch (error) {
        console.error('Error loading saved theme:', error);
      }
    };
    loadSavedTheme();
  }, []);

  const toggleTheme = useCallback(async () => {
    setCurrentTheme((prev) => {
      const currentIndex = ALL_THEMES.findIndex((t) => t.name === prev.name);
      const nextIndex = (currentIndex + 1) % ALL_THEMES.length;
      const newTheme = ALL_THEMES[nextIndex];

      console.log(`🎨 Theme switched to: ${newTheme.name} (${nextIndex + 1}/${ALL_THEMES.length})`);

      AsyncStorage.setItem(THEME_STORAGE_KEY, newTheme.name).catch((err) => {
        console.error('Error saving theme:', err);
      });

      if (Platform.OS !== 'web') {
        Vibration.vibrate([0, 50, 50, 50]);
      }
      return newTheme;
    });
  }, []);

  const setTheme = useCallback(async (themeName: ThemeName) => {
    const normalized = normalizeThemeName(themeName);
    const newTheme = ALL_THEMES.find((t) => t.name === normalized) || nextradeTheme;
    setCurrentTheme(newTheme);
    try {
      await AsyncStorage.setItem(THEME_STORAGE_KEY, normalized);
    } catch (error) {
      console.error('Error saving theme:', error);
    }
  }, []);

  useEffect(() => {
    if (Platform.OS !== 'web') return;

    const handleKeyPress = (event: KeyboardEvent) => {
      if (event.shiftKey && event.key === 'T') {
        console.log('⌨️ Keyboard shortcut detected! Toggling theme...');
        toggleTheme();
      }
    };

    if (typeof window !== 'undefined') {
      window.addEventListener('keydown', handleKeyPress);
      return () => window.removeEventListener('keydown', handleKeyPress);
    }
  }, [toggleTheme]);

  useEffect(() => {
    if (Platform.OS !== 'web' || !isShakeEnabled) return;
    if (typeof window === 'undefined') return;

    const WEB_SHAKE_THRESHOLD = 22;
    const WEB_SHAKE_TIMEOUT = 500;
    const WEB_REQUIRED_SHAKES = 3;

    let webLastShakeTime = 0;
    let webShakeCount = 0;
    let lastX = 0;
    let lastY = 0;
    let lastZ = 0;
    let unsub: (() => void) | null = null;

    const onSample = (sample: { gx: number; gy: number; gz: number }) => {
      const x = sample.gx;
      const y = sample.gy;
      const z = sample.gz;
      const deltaX = Math.abs(x - lastX);
      const deltaY = Math.abs(y - lastY);
      const deltaZ = Math.abs(z - lastZ);
      lastX = x;
      lastY = y;
      lastZ = z;

      const totalDelta = deltaX + deltaY + deltaZ;
      const now = Date.now();
      if (totalDelta <= WEB_SHAKE_THRESHOLD) return;

      if (now - webLastShakeTime > WEB_SHAKE_TIMEOUT) webShakeCount = 0;
      webShakeCount += 1;
      webLastShakeTime = now;

      if (webShakeCount >= WEB_REQUIRED_SHAKES) {
        toggleTheme();
        webShakeCount = 0;
      }
    };

    const start = () => {
      if (unsub) return;
      if (needsWebMotionPermission() && !getStoredMotionGranted()) return;
      unsub = subscribeWebDeviceMotion(onSample);
    };

    start();
    const onGranted = () => start();
    window.addEventListener('aura-motion-granted', onGranted);

    return () => {
      unsub?.();
      window.removeEventListener('aura-motion-granted', onGranted);
    };
  }, [isShakeEnabled, toggleTheme]);

  useEffect(() => {
    if (!isShakeEnabled || Platform.OS === 'web') {
      return;
    }

    const handleShake = ({ x, y, z }: { x: number; y: number; z: number }) => {
      const acceleration = Math.sqrt(x * x + y * y + z * z);
      const now = Date.now();

      if (acceleration > SHAKE_THRESHOLD) {
        if (now - lastShakeTime.current > SHAKE_TIMEOUT) {
          shakeCount.current = 0;
        }

        shakeCount.current += 1;
        lastShakeTime.current = now;

        if (shakeCount.current >= REQUIRED_SHAKES) {
          console.log('📱 Shake detected! Toggling theme...');
          toggleTheme();
          shakeCount.current = 0;
        }
      }
    };

    Accelerometer.setUpdateInterval(100);
    subscription.current = Accelerometer.addListener(handleShake);

    return () => {
      if (subscription.current) {
        subscription.current.remove();
        subscription.current = null;
      }
    };
  }, [isShakeEnabled, toggleTheme]);

  const value: ThemeContextType = {
    theme: currentTheme,
    themeName: currentTheme.name as ThemeName,
    toggleTheme,
    setTheme,
    isShakeEnabled,
    setShakeEnabled,
    allThemes: ALL_THEMES,
  };

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
};

export const useTheme = (): ThemeContextType => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
};

export default ThemeProvider;
