import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Platform,
  type ViewStyle,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ChevronRight } from 'lucide-react-native';
import { useTheme } from '@/providers/theme-provider';
import { type } from '@/constants/typography';

type ActionProps = {
  label: string;
  icon: React.ReactNode;
  onPress: () => void;
  active?: boolean;
  testID?: string;
};

/** Primary hero CTA — gradient fill with accent glow (Start / Stop automation). */
export function PremiumActionButton({ label, icon, onPress, active = false, testID }: ActionProps) {
  const { theme } = useTheme();
  const accent = theme.colors.accent;
  const accentSoft = theme.colors.accentSecondary;
  const onAccent = theme.colors.onAccent;

  return (
    <TouchableOpacity
      testID={testID}
      onPress={onPress}
      activeOpacity={0.9}
      style={[
        styles.actionOuter,
        {
          shadowColor: accent,
          borderColor: active ? `${accent}88` : `${accent}55`,
        },
      ]}
    >
      {active ? (
        <View style={[styles.actionFill, { backgroundColor: 'rgba(255,255,255,0.03)', borderColor: `${accent}66` }]} />
      ) : (
        <>
          <LinearGradient
            colors={[accentSoft, accent]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={StyleSheet.absoluteFill}
          />
          <LinearGradient
            colors={['rgba(255,255,255,0.35)', 'rgba(255,255,255,0.06)', 'rgba(255,255,255,0)']}
            start={{ x: 0.5, y: 0 }}
            end={{ x: 0.5, y: 1 }}
            style={[StyleSheet.absoluteFill, styles.actionSheen]}
          />
        </>
      )}
      <View style={styles.actionContent}>
        {icon}
        <Text style={[styles.actionLabel, { color: active ? accent : onAccent }]}>{label}</Text>
      </View>
    </TouchableOpacity>
  );
}

type LinkBannerProps = {
  title: string;
  subtitle: string;
  onPress: () => void;
  leadingIcon: React.ReactNode;
  style?: ViewStyle;
};

/** Full-width link row — premium gradient banner (Link automation). */
export function PremiumLinkBanner({ title, subtitle, onPress, leadingIcon, style }: LinkBannerProps) {
  const { theme } = useTheme();
  const accent = theme.colors.accent;
  const accentSoft = theme.colors.accentSecondary;
  const onAccent = theme.colors.onAccent;

  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.9}
      style={[styles.linkOuter, { shadowColor: accent }, style]}
    >
      <LinearGradient
        colors={[accentSoft, accent]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
      <LinearGradient
        colors={['rgba(255,255,255,0.28)', 'rgba(255,255,255,0.05)', 'rgba(0,0,0,0.08)']}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
      <View style={styles.linkContent}>
        <View style={[styles.linkIconWrap, { backgroundColor: `${onAccent}22`, borderColor: `${onAccent}33` }]}>
          {leadingIcon}
        </View>
        <View style={styles.linkTextCol}>
          <Text style={[styles.linkTitle, { color: onAccent }]}>{title}</Text>
          <Text style={[styles.linkSubtitle, { color: `${onAccent}C8` }]}>{subtitle}</Text>
        </View>
        <ChevronRight color={`${onAccent}CC`} size={20} strokeWidth={2} />
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  actionOuter: {
    height: 56,
    borderRadius: 999,
    borderWidth: 1,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.45,
        shadowRadius: 16,
      },
      android: { elevation: 10 },
      default: {},
    }),
  },
  actionFill: {
    ...StyleSheet.absoluteFillObject,
    borderWidth: 1.5,
    borderRadius: 999,
  },
  actionSheen: {
    borderRadius: 999,
  },
  actionContent: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    paddingHorizontal: 22,
  },
  actionLabel: {
    ...type.button,
    fontSize: 16,
    letterSpacing: 0.3,
  },
  linkOuter: {
    borderRadius: 22,
    overflow: 'hidden',
    marginBottom: 24,
    marginTop: 16,
    ...Platform.select({
      ios: {
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.4,
        shadowRadius: 18,
      },
      android: { elevation: 12 },
      default: {},
    }),
  },
  linkContent: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 16,
    gap: 12,
  },
  linkIconWrap: {
    width: 44,
    height: 44,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  linkTextCol: {
    flex: 1,
  },
  linkTitle: {
    ...type.title,
    fontSize: 16,
    letterSpacing: -0.2,
  },
  linkSubtitle: {
    ...type.caption,
    marginTop: 3,
  },
});
