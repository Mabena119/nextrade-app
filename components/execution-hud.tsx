import React, { useEffect, useMemo, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Platform,
  Animated,
  Easing,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ArrowDownRight, ArrowUpRight, Radar, ShieldAlert, X, Zap } from 'lucide-react-native';

export type ExecutionHudVariant = 'copy' | 'chart-warmup' | 'blocked';

type Props = {
  variant: ExecutionHudVariant;
  symbol?: string;
  action?: string;
  statusLine: string;
  loading?: boolean;
  robotName?: string;
  blockMessage?: string;
  accent: string;
  accentSoft: string;
  onClose: () => void;
};

function inferPhase(status: string): number {
  const s = status.toLowerCase();
  if (/order|placing|executing|confirm|trade\s*\d|volume|completed|buy order|sell order/.test(s)) {
    return 2;
  }
  if (/chart|terminal|login|signing|waiting|removing|linking|snapshot|analys/.test(s)) {
    return 1;
  }
  return 0;
}

const PHASES = ['Connect', 'Terminal', 'Execute'] as const;

export function ExecutionHud({
  variant,
  symbol,
  action,
  statusLine,
  loading = true,
  robotName,
  blockMessage,
  accent,
  accentSoft,
  onClose,
}: Props) {
  const pulse = useRef(new Animated.Value(0)).current;
  const shimmer = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const pulseLoop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 1400, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0, duration: 1400, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
      ])
    );
    const shimmerLoop = Animated.loop(
      Animated.timing(shimmer, { toValue: 1, duration: 1800, easing: Easing.linear, useNativeDriver: true })
    );
    pulseLoop.start();
    if (loading && variant !== 'blocked') shimmerLoop.start();
    return () => {
      pulseLoop.stop();
      shimmerLoop.stop();
    };
  }, [loading, pulse, shimmer, variant]);

  const ringScale = pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 1.08] });
  const ringOpacity = pulse.interpolate({ inputRange: [0, 1], outputRange: [0.35, 0.9] });
  const shimmerX = shimmer.interpolate({ inputRange: [0, 1], outputRange: [-120, 280] });

  const isSell = (action || '').toLowerCase() === 'sell';
  const isBuy = (action || '').toLowerCase() === 'buy';
  const tradeColor = isSell ? '#FB7185' : isBuy ? '#34D399' : accent;
  const tradeBg = isSell ? 'rgba(251,113,133,0.16)' : isBuy ? 'rgba(52,211,153,0.16)' : `${accent}22`;
  const tradeBorder = isSell ? 'rgba(251,113,133,0.45)' : isBuy ? 'rgba(52,211,153,0.45)' : `${accent}55`;

  const phase = useMemo(() => inferPhase(statusLine), [statusLine]);
  const displaySymbol = (symbol || 'MARKET').toUpperCase();
  const headline =
    variant === 'blocked'
      ? 'Trade held'
      : variant === 'chart-warmup'
        ? robotName || 'Chart scan'
        : displaySymbol;

  const subhead =
    variant === 'blocked'
      ? blockMessage || 'Execution paused'
      : variant === 'chart-warmup'
        ? 'AI chart intelligence'
        : isSell
          ? 'Sell order'
          : isBuy
            ? 'Buy order'
            : 'Copy signal';

  const hudAccent = variant === 'chart-warmup' ? accentSoft : variant === 'blocked' ? '#FBBF24' : accent;

  return (
    <View style={styles.root} pointerEvents="box-none">
      <View style={[styles.card, { borderColor: `${hudAccent}40`, shadowColor: hudAccent }]} pointerEvents="auto">
        <LinearGradient
          colors={['rgba(6,8,14,0.98)', 'rgba(10,14,24,0.96)', 'rgba(4,6,10,0.99)']}
          style={StyleSheet.absoluteFill}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
        />
        <LinearGradient
          colors={[`${hudAccent}18`, 'transparent', `${tradeColor}10`]}
          style={StyleSheet.absoluteFill}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
        />

        <View style={styles.topRow}>
          <View style={styles.badgeRow}>
            {variant === 'blocked' ? (
              <View style={[styles.dirBadge, { backgroundColor: 'rgba(245,158,11,0.14)', borderColor: 'rgba(245,158,11,0.4)' }]}>
                <ShieldAlert color="#FBBF24" size={14} strokeWidth={2.2} />
                <Text style={[styles.dirText, { color: '#FCD34D' }]}>HOLD</Text>
              </View>
            ) : variant === 'chart-warmup' ? (
              <View style={[styles.dirBadge, { backgroundColor: `${accentSoft}18`, borderColor: `${accentSoft}44` }]}>
                <Radar color={accentSoft} size={14} strokeWidth={2.2} />
                <Text style={[styles.dirText, { color: accentSoft }]}>SCAN</Text>
              </View>
            ) : (
              <View style={[styles.dirBadge, { backgroundColor: tradeBg, borderColor: tradeBorder }]}>
                {isSell ? (
                  <ArrowDownRight color={tradeColor} size={14} strokeWidth={2.4} />
                ) : (
                  <ArrowUpRight color={tradeColor} size={14} strokeWidth={2.4} />
                )}
                <Text style={[styles.dirText, { color: tradeColor }]}>
                  {(action || 'TRADE').toUpperCase()}
                </Text>
              </View>
            )}
            <View style={styles.livePill}>
              <Animated.View style={[styles.liveDot, { opacity: ringOpacity, backgroundColor: hudAccent }]} />
              <Text style={[styles.liveText, { color: hudAccent }]}>
                {variant === 'blocked' ? 'BLOCKED' : loading ? 'LIVE' : 'READY'}
              </Text>
            </View>
          </View>

          <TouchableOpacity
            style={styles.closeBtn}
            onPress={onClose}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Close"
          >
            <X color="rgba(248,250,252,0.85)" size={16} strokeWidth={2.5} />
          </TouchableOpacity>
        </View>

        <View style={styles.mainRow}>
          <View style={styles.copyBlock}>
            <Text style={styles.headline} numberOfLines={1}>
              {headline}
            </Text>
            <Text style={styles.subhead} numberOfLines={1}>
              {subhead}
            </Text>
          </View>

          {variant !== 'blocked' && (
            <Animated.View
              style={[
                styles.ringWrap,
                { borderColor: `${hudAccent}55`, transform: [{ scale: ringScale }] },
              ]}
            >
              <LinearGradient
                colors={[`${hudAccent}30`, `${hudAccent}05`]}
                style={StyleSheet.absoluteFill}
              />
              <Zap color={hudAccent} size={20} strokeWidth={2.2} />
            </Animated.View>
          )}
        </View>

        <Text style={styles.statusLine} numberOfLines={2}>
          {statusLine}
        </Text>

        {variant !== 'blocked' && (
          <View style={styles.phaseRow}>
            {PHASES.map((label, i) => {
              const active = i <= phase;
              const current = i === phase;
              return (
                <React.Fragment key={label}>
                  <View style={styles.phaseStep}>
                    <View
                      style={[
                        styles.phaseDot,
                        active && { backgroundColor: hudAccent, borderColor: hudAccent },
                        current && styles.phaseDotCurrent,
                      ]}
                    />
                    <Text style={[styles.phaseLabel, active && { color: 'rgba(248,250,252,0.9)' }]}>
                      {label}
                    </Text>
                  </View>
                  {i < PHASES.length - 1 && (
                    <View style={[styles.phaseConnector, active && { backgroundColor: `${hudAccent}55` }]} />
                  )}
                </React.Fragment>
              );
            })}
          </View>
        )}

        {loading && variant !== 'blocked' && (
          <View style={styles.shimmerTrack}>
            <Animated.View style={[styles.shimmerBar, { transform: [{ translateX: shimmerX }] }]}>
              <LinearGradient
                colors={['transparent', `${hudAccent}88`, 'transparent']}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 0 }}
                style={StyleSheet.absoluteFill}
              />
            </Animated.View>
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'flex-end',
    paddingHorizontal: 16,
    paddingBottom: Platform.OS === 'ios' ? 98 : 86,
    zIndex: 10000,
    elevation: 10000,
  },
  card: {
    borderRadius: 24,
    borderWidth: 1,
    overflow: 'hidden',
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 12,
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.35,
    shadowRadius: 24,
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  dirBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
  },
  dirText: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.8,
  },
  livePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  liveDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  liveText: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.1,
  },
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
  },
  mainRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 8,
  },
  copyBlock: {
    flex: 1,
    minWidth: 0,
  },
  headline: {
    color: '#F8FAFC',
    fontSize: 22,
    fontWeight: '800',
    letterSpacing: -0.5,
    lineHeight: 26,
  },
  subhead: {
    marginTop: 2,
    color: 'rgba(148,163,184,0.95)',
    fontSize: 13,
    fontWeight: '600',
  },
  ringWrap: {
    width: 48,
    height: 48,
    borderRadius: 24,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  statusLine: {
    color: 'rgba(203,213,225,0.88)',
    fontSize: 12,
    fontWeight: '500',
    lineHeight: 17,
    marginBottom: 10,
  },
  phaseRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  phaseStep: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  phaseDot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: 'rgba(148,163,184,0.35)',
    backgroundColor: 'rgba(148,163,184,0.15)',
  },
  phaseDotCurrent: {
    shadowColor: '#fff',
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.5,
    shadowRadius: 6,
  },
  phaseLabel: {
    fontSize: 10,
    fontWeight: '600',
    color: 'rgba(148,163,184,0.55)',
    letterSpacing: 0.2,
  },
  phaseConnector: {
    flex: 1,
    height: 1,
    marginHorizontal: 8,
    backgroundColor: 'rgba(148,163,184,0.2)',
  },
  shimmerTrack: {
    height: 3,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.06)',
    overflow: 'hidden',
    marginTop: 2,
  },
  shimmerBar: {
    width: 120,
    height: '100%',
  },
});
