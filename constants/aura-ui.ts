/** NexTradeAI design tokens — void black, electric blue glow, circular chrome */
export const lux = {
  color: {
    bg: '#000000',
    surface: '#07090F',
    surfaceElevated: 'rgba(10, 14, 22, 0.82)',
    glass: 'rgba(255, 255, 255, 0.045)',
    glassBorder: 'rgba(0, 168, 255, 0.28)',
    accent: '#00A8FF',
    accentSoft: '#5EF6FF',
    success: '#3AE374',
    text: '#F2F4F7',
    textSecondary: '#8B95A5',
    textMuted: 'rgba(139, 149, 165, 0.72)',
    danger: '#FF5C7A',
  },
  radius: {
    sm: 14,
    md: 20,
    lg: 26,
    xl: 30,
    pill: 999,
  },
  space: {
    xs: 6,
    sm: 10,
    md: 16,
    lg: 24,
    xl: 32,
    xxl: 40,
  },
  shadow: {
    float: {
      shadowColor: '#000000',
      shadowOffset: { width: 0, height: 18 },
      shadowOpacity: 0.45,
      shadowRadius: 28,
      elevation: 18,
    },
    glow: {
      shadowColor: '#00A8FF',
      shadowOffset: { width: 0, height: 0 },
      shadowOpacity: 0.55,
      shadowRadius: 18,
      elevation: 8,
    },
  },
  duration: 300,
} as const;

/** @deprecated use `lux` — kept for gradual migration */
export const auraUi = {
  radius: lux.radius,
  space: lux.space,
  font: {
    display: '700' as const,
    body: '500' as const,
    label: '600' as const,
  },
  maxContentWidth: 420,
} as const;
