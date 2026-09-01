import { StyleSheet } from 'react-native';
import { type } from '@/constants/typography';

/** NexTrade auth / form screens — matches login redesign. */
export const authColors = {
  bg: '#000000',
  card: '#070708',
  cardBorder: 'rgba(255,255,255,0.08)',
  inputBg: 'rgba(255,255,255,0.03)',
} as const;

export const authLayout = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: authColors.bg,
  },
  scroll: {
    flexGrow: 1,
    paddingHorizontal: 24,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  pageHeader: {
    paddingHorizontal: 24,
    paddingTop: 8,
    paddingBottom: 12,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  copyBlock: {
    alignItems: 'center',
    marginBottom: 18,
  },
  eyebrow: {
    ...type.eyebrow,
    marginBottom: 8,
  },
  headline: {
    ...type.display,
    textAlign: 'center',
  },
  pageTitle: {
    ...type.title,
    letterSpacing: -0.5,
  },
  pageSubtitle: {
    ...type.body,
    marginTop: 8,
    lineHeight: 22,
  },
  formCard: {
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 20,
    paddingVertical: 22,
    gap: 14,
    backgroundColor: authColors.card,
    borderColor: authColors.cardBorder,
  },
  formCardWide: {
    marginHorizontal: 24,
    marginTop: 12,
    marginBottom: 24,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
  },
  statusCard: {
    marginHorizontal: 24,
    marginTop: 4,
    marginBottom: 12,
    maxWidth: 440,
    width: '100%',
    alignSelf: 'center',
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 14,
    backgroundColor: authColors.card,
    borderColor: authColors.cardBorder,
  },
  fieldLabel: {
    ...type.label,
    marginBottom: 8,
  },
  fieldInput: {
    ...type.input,
    paddingVertical: 14,
    borderBottomWidth: 1,
    textAlign: 'left',
  },
  fieldInputBox: {
    ...type.input,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderWidth: 1,
    borderRadius: 14,
    backgroundColor: authColors.inputBg,
  },
  primaryBtn: {
    height: 52,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 8,
  },
  primaryBtnText: {
    ...type.button,
  },
  listCard: {
    borderWidth: 1,
    borderRadius: 20,
    paddingHorizontal: 18,
    paddingVertical: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    backgroundColor: authColors.card,
    borderColor: authColors.cardBorder,
  },
  modalCard: {
    width: '100%',
    maxWidth: 360,
    borderRadius: 20,
    padding: 22,
    borderWidth: 1,
    backgroundColor: authColors.card,
    borderColor: authColors.cardBorder,
  },
});
