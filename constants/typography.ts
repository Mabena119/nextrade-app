/** Outfit — matches marketing site typography. */
export const fonts = {
  regular: 'Outfit_400Regular',
  medium: 'Outfit_500Medium',
  semibold: 'Outfit_600SemiBold',
  bold: 'Outfit_700Bold',
} as const;

export const type = {
  eyebrow: {
    fontFamily: fonts.semibold,
    fontSize: 11,
    letterSpacing: 2.4,
    textTransform: 'uppercase' as const,
  },
  display: {
    fontFamily: fonts.bold,
    fontSize: 32,
    letterSpacing: -0.8,
    lineHeight: 38,
  },
  title: {
    fontFamily: fonts.bold,
    fontSize: 24,
    letterSpacing: -0.4,
    lineHeight: 30,
  },
  body: {
    fontFamily: fonts.regular,
    fontSize: 15,
    lineHeight: 23,
  },
  bodyMedium: {
    fontFamily: fonts.medium,
    fontSize: 15,
    lineHeight: 23,
  },
  label: {
    fontFamily: fonts.semibold,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase' as const,
  },
  input: {
    fontFamily: fonts.medium,
    fontSize: 17,
    letterSpacing: 0.1,
  },
  button: {
    fontFamily: fonts.bold,
    fontSize: 16,
    letterSpacing: 0.2,
  },
  caption: {
    fontFamily: fonts.regular,
    fontSize: 13,
    lineHeight: 19,
  },
  brand: {
    fontFamily: fonts.semibold,
    fontSize: 20,
    letterSpacing: -0.3,
  },
  step: {
    fontFamily: fonts.medium,
    fontSize: 13,
    letterSpacing: 0.8,
  },
} as const;
