import React, { useState, useEffect, useCallback, useRef } from 'react';
import { View, Text, StyleSheet, SafeAreaView, ScrollView, TouchableOpacity, ActivityIndicator, Alert, Animated, Platform } from 'react-native';
import { BlurView } from 'expo-blur';
import { ArrowLeft, Circle, RotateCw } from 'lucide-react-native';
import { router } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { useApp } from '@/providers/app-provider';
import { getScreenBackgroundColor, useTheme } from '@/providers/theme-provider';
import { AuraAtmosphere } from '@/components/aura';
import { auraUi } from '@/constants/aura-ui';
import { Symbol as ApiSymbol, apiService } from '@/services/api';
import colors from '@/constants/colors';
import { formatLotSizeForDisplay, getEquityBasedMT5Preset } from '@/utils/equity-trade-preset';
import { isMartingaleEa, MARTINGALE_SIGNAL_LOT_LABEL, type MartingaleLotSource } from '@/utils/trading-features';
import { LicenseBlockedOverlay } from '@/components/license-blocked-overlay';

interface Quote {
  symbol: string;
  lotSize: number;
  numberOfTrades: number;
  platform: string;
  direction: 'BUY' | 'SELL' | 'BOTH';
  isActive?: boolean;
}



export default function QuotesScreen() {
  const { eas, activeSymbols, mt4Symbols, mt5Symbols, mt5Account, mt5LotSizingMode, setMt5LotSizingMode, martingaleLotSource, setMartingaleLotSource, primaryLicenseStatus } = useApp();
  const { theme } = useTheme();
  const screenBg = getScreenBackgroundColor(theme);

  const hasMt5Linked = Boolean(
    mt5Account &&
    typeof mt5Account.login === 'string' &&
    mt5Account.login.trim().length > 0 &&
    mt5Account.password
  );
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [apiSymbols, setApiSymbols] = useState<ApiSymbol[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const rotateAnim = useRef(new Animated.Value(0)).current;
  const [error, setError] = useState<string | null>(null);
  const previousBotIdRef = useRef<string | undefined>(undefined);

  const primaryEA = eas.length > 0 ? eas[0] : null;
  const isMartingaleBot = isMartingaleEa(eas);
  const licenseExpired = primaryLicenseStatus === 'expired';
  const hasActiveQuotes = activeSymbols.length > 0 || mt4Symbols.length > 0 || mt5Symbols.length > 0;
  const hasConnectedEA = primaryEA && primaryEA.status === 'connected' && primaryEA.phoneSecretKey;

  // Merge quotes with active symbol status
  const quotesWithActiveStatus = quotes.map(quote => ({
    ...quote,
    isActive: activeSymbols.some(activeSymbol => activeSymbol.symbol === quote.symbol) ||
      mt4Symbols.some(mt4Symbol => mt4Symbol.symbol === quote.symbol) ||
      mt5Symbols.some(mt5Symbol => mt5Symbol.symbol === quote.symbol)
  }));

  // Fetch symbols from API - only show symbols from connected robot
  const fetchSymbols = useCallback(async (showRefreshIndicator = false) => {
    if (licenseExpired) {
      setLoading(false);
      setRefreshing(false);
      return;
    }
    if (!hasMt5Linked) {
      setLoading(false);
      setRefreshing(false);
      return;
    }
    try {
      console.log('Fetching symbols for active bot:', {
        botId: primaryEA?.id,
        botName: primaryEA?.name,
        licenseKey: primaryEA?.licenseKey,
        hasConnectedEA,
        hasPhoneSecret: !!primaryEA?.phoneSecretKey
      });

      if (showRefreshIndicator) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }
      setError(null);

      // Only fetch from API if we have a connected EA with phone secret
      let response: { data: ApiSymbol[] } = { data: [] };
      if (hasConnectedEA && primaryEA?.phoneSecretKey) {
        console.log('Fetching symbols from API for bot:', primaryEA.name);
        const apiRes = await apiService.getSymbols(primaryEA.phoneSecretKey);
        if (apiRes.message === 'accept' && Array.isArray(apiRes.data)) {
          response = { data: apiRes.data };
          console.log(`API returned ${apiRes.data.length} symbols for bot:`, primaryEA.name);
        } else {
          console.log('API returned no symbols or error for bot:', primaryEA.name);
        }
      } else {
        console.log('No connected EA or missing phone secret - quotes will be empty');
      }
      // If no connected EA or API returns empty, keep quotes empty

      setApiSymbols(response.data);
      // Convert API symbols to quotes with actual saved data or defaults
      const newQuotes: Quote[] = response.data.map(apiSymbol => {
        const symbolName = apiSymbol.name;

        // Consolidate configs across legacy, MT4 and MT5 and pick the most recently activated
        const legacyConfig = activeSymbols.find(s => s.symbol === symbolName);
        const mt4Config = mt4Symbols.find(s => s.symbol === symbolName);
        const mt5Config = mt5Symbols.find(s => s.symbol === symbolName);

        type Unified = {
          platform: 'MT4' | 'MT5';
          lotSize: number;
          numberOfTrades: number;
          direction: 'BUY' | 'SELL' | 'BOTH';
          activatedAt: Date;
        };

        const candidates: Unified[] = [];

        if (legacyConfig) {
          const lot = Number.parseFloat(legacyConfig.lotSize ?? '0.01');
          const nt = Number.parseInt(String(legacyConfig.numberOfTrades ?? '1'), 10);
          const act = legacyConfig.activatedAt instanceof Date ? legacyConfig.activatedAt : new Date(legacyConfig.activatedAt as unknown as string);
          candidates.push({
            platform: legacyConfig.platform,
            lotSize: Number.isFinite(lot) ? lot : 0.01,
            numberOfTrades: Number.isFinite(nt) && nt >= 1 ? nt : 1,
            direction: legacyConfig.direction,
            activatedAt: act,
          });
        }
        if (mt4Config) {
          const lot = Number.parseFloat(mt4Config.lotSize ?? '0.01');
          const nt = Number.parseInt(String(mt4Config.numberOfTrades ?? '1'), 10);
          const act = mt4Config.activatedAt instanceof Date ? mt4Config.activatedAt : new Date(mt4Config.activatedAt as unknown as string);
          candidates.push({
            platform: 'MT4',
            lotSize: Number.isFinite(lot) ? lot : 0.01,
            numberOfTrades: Number.isFinite(nt) && nt >= 1 ? nt : 1,
            direction: mt4Config.direction,
            activatedAt: act,
          });
        }
        if (mt5Config) {
          const lot = Number.parseFloat(mt5Config.lotSize ?? '0.01');
          const nt = Number.parseInt(String(mt5Config.numberOfTrades ?? '1'), 10);
          const act = mt5Config.activatedAt instanceof Date ? mt5Config.activatedAt : new Date(mt5Config.activatedAt as unknown as string);
          candidates.push({
            platform: 'MT5',
            lotSize: Number.isFinite(lot) ? lot : 0.01,
            numberOfTrades: Number.isFinite(nt) && nt >= 1 ? nt : 1,
            direction: mt5Config.direction,
            activatedAt: act,
          });
        }

        if (candidates.length > 0) {
          const latest = candidates.sort((a, b) => (b.activatedAt?.getTime?.() ?? 0) - (a.activatedAt?.getTime?.() ?? 0))[0];
          console.log('Using latest config for symbol', symbolName, latest);
          return {
            symbol: symbolName,
            lotSize: latest.lotSize,
            numberOfTrades: latest.numberOfTrades,
            platform: latest.platform,
            direction: latest.direction,
          };
        }

        // Default preview: equity-based MT5 preset (or manual preview uses same suggestion until symbol is set)
        const fb = getEquityBasedMT5Preset(mt5Account?.equity, symbolName);
        return {
          symbol: symbolName,
          lotSize: Number.parseFloat(fb.lotSize) || 0.01,
          numberOfTrades: Number.parseInt(String(fb.numberOfTrades), 10) || 1,
          platform: 'MT5' as const,
          direction: fb.direction,
        };
      });

      setQuotes(newQuotes);
      console.log(`Quotes updated for bot "${primaryEA?.name}":`, {
        quotesCount: newQuotes.length,
        symbols: newQuotes.map(q => q.symbol)
      });
    } catch (error) {
      console.error('Error fetching symbols:', error);
      setError('Failed to load symbols (offline)');

      // Keep quotes empty if API fails - don't fallback to mock data
      console.log(`API failed for bot "${primaryEA?.name}", keeping quotes empty`);
      setQuotes([]);
    } finally {
      // Add a small delay to make the refresh feel more natural
      setTimeout(() => {
        setLoading(false);
        setRefreshing(false);
      }, showRefreshIndicator ? 300 : 0);
    }
  }, [
    hasMt5Linked,
    hasConnectedEA,
    primaryEA?.id,
    primaryEA?.phoneSecretKey,
    primaryEA?.name,
    activeSymbols,
    mt4Symbols,
    mt5Symbols,
    mt5Account?.equity,
    mt5LotSizingMode,
    licenseExpired,
  ]);

  // Initial load and refresh when symbols change or active bot switches
  useEffect(() => {
    if (!hasMt5Linked) return;
    const currentBotId = primaryEA?.id;
    const previousBotId = previousBotIdRef.current;
    const botSwitched = previousBotId !== undefined && currentBotId !== previousBotId;

    console.log('Bot or symbols changed, refreshing quotes...', {
      botId: currentBotId,
      botName: primaryEA?.name,
      hasConnectedEA,
      previousBotId,
      botSwitched,
      activeSymbols: activeSymbols.length,
      mt4Symbols: mt4Symbols.length,
      mt5Symbols: mt5Symbols.length
    });

    if (botSwitched) {
      setQuotes([]);
      setApiSymbols([]);
      setError(null);
      setLoading(true);
      previousBotIdRef.current = currentBotId;
      console.log('Active EA switched — clearing Quotes and full refresh');
      fetchSymbols(false);
      return;
    }

    previousBotIdRef.current = currentBotId;

    if (quotes.length === 0) {
      console.log('First load or empty quotes — full refresh');
      fetchSymbols(false);
    } else {
      console.log('Only symbols changed — gentle refresh');
      fetchSymbols(true);
    }
  }, [
    hasMt5Linked,
    hasConnectedEA,
    primaryEA?.id,
    primaryEA?.phoneSecretKey,
    /** Full arrays (not .length) so lot/trade edits re-merge without adding/removing symbols */
    activeSymbols,
    mt4Symbols,
    mt5Symbols,
    mt5LotSizingMode,
    fetchSymbols,
  ]);

  // Smooth rotation animation for refresh button
  useEffect(() => {
    if (refreshing) {
      const rotateAnimation = Animated.loop(
        Animated.timing(rotateAnim, {
          toValue: 1,
          duration: 800,
          useNativeDriver: true,
        })
      );
      rotateAnimation.start();
      return () => {
        rotateAnimation.stop();
        // Smoothly reset to 0 when stopping
        Animated.timing(rotateAnim, {
          toValue: 0,
          duration: 200,
          useNativeDriver: true,
        }).start();
      };
    }
  }, [refreshing, rotateAnim]);

  // Redirect if MT5 not linked; otherwise refresh when screen focuses (e.g. returning from trade-config)
  useFocusEffect(
    useCallback(() => {
      if (!hasMt5Linked) {
        router.replace('/(tabs)/metatrader');
        return;
      }
      console.log('Quotes screen focused, refreshing to sync active bot symbols...');
      setTimeout(() => fetchSymbols(true), 100);
    }, [hasMt5Linked, fetchSymbols])
  );

  // Refresh function
  const handleRefresh = () => {
    console.log('Manual refresh triggered');
    fetchSymbols(true);
  };



  const handleBack = () => {
    router.back();
  };

  const handleRetry = () => {
    fetchSymbols();
  };

  const formatLotSize = (lotSize: number) => formatLotSizeForDisplay(lotSize);





  const handleQuoteTap = (symbol: string) => {
    router.push(`/trade-config?symbol=${symbol}`);
  };

  if (!hasMt5Linked) {
    return null;
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: screenBg }]}>
        <AuraAtmosphere />

        <View style={styles.topHero}>
          <View style={styles.topHeroRow}>
            <TouchableOpacity
              style={[styles.topIconBtn, { borderColor: theme.colors.borderColor }]}
              onPress={handleBack}
              activeOpacity={0.75}
              accessibilityRole="button"
              accessibilityLabel="Go back"
            >
              <ArrowLeft color={theme.colors.textPrimary} size={20} strokeWidth={2} />
            </TouchableOpacity>
            <View style={styles.topHeroTitles}>
              <Text style={[styles.topKicker, { color: theme.colors.accent }]}>MARKETS</Text>
              <Text style={[styles.topTitle, { color: theme.colors.textPrimary }]}>Quotes</Text>
            </View>
            {hasConnectedEA ? (
              <TouchableOpacity
                style={[
                  styles.topIconBtn,
                  { borderColor: theme.colors.borderColor, backgroundColor: 'rgba(255,255,255,0.04)' },
                  refreshing && styles.refreshButtonDisabled,
                ]}
                onPress={handleRefresh}
                disabled={refreshing}
                activeOpacity={refreshing ? 1 : 0.75}
              >
                <Animated.View
                  style={{
                    transform: [{
                      rotate: rotateAnim.interpolate({
                        inputRange: [0, 1],
                        outputRange: ['0deg', '360deg'],
                      }),
                    }],
                  }}
                >
                  <RotateCw
                    color={refreshing ? theme.colors.statusInactive : theme.colors.accent}
                    size={18}
                    strokeWidth={2.2}
                  />
                </Animated.View>
              </TouchableOpacity>
            ) : (
              <View style={styles.topIconSpacer} />
            )}
          </View>

          <Text style={[styles.topSubtitle, { color: theme.colors.textSecondary }]}>
            {primaryEA
              ? `${primaryEA.name} · ${quotesWithActiveStatus.length} symbols${hasActiveQuotes ? ' · live' : ''}`
              : 'Symbols from your linked automation'}
          </Text>

          <View
            style={[
              styles.controlCard,
              {
                borderColor: theme.colors.borderColor,
                backgroundColor: theme.colors.backgroundSecondary,
              },
            ]}
          >
            {isMartingaleBot ? (
              <>
                <View style={styles.controlCardHead}>
                  <Text style={[styles.sizingModeLabel, { color: theme.colors.textMuted }]}>
                    LOT SOURCE
                  </Text>
                  <Text style={[styles.controlHint, { color: theme.colors.textMuted }]}>
                    Martingale automation
                  </Text>
                </View>
                <View style={styles.sizingModeChips}>
                  {([
                    { key: 'signal' as MartingaleLotSource, label: 'From signal' },
                    { key: 'own' as MartingaleLotSource, label: 'My own lot' },
                  ]).map((opt) => {
                    const selected = martingaleLotSource === opt.key;
                    return (
                      <TouchableOpacity
                        key={opt.key}
                        onPress={() => void setMartingaleLotSource(opt.key)}
                        activeOpacity={0.75}
                        style={[
                          styles.sizingModeChip,
                          {
                            borderColor: selected ? theme.colors.accent : theme.colors.borderColor,
                            backgroundColor: selected
                              ? `${theme.colors.accent}20`
                              : 'rgba(255,255,255,0.03)',
                          },
                        ]}
                      >
                        <Text
                          style={[
                            styles.sizingModeChipText,
                            {
                              color: theme.colors.textPrimary,
                              fontWeight: selected ? '700' : '500',
                            },
                          ]}
                        >
                          {opt.label}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </>
            ) : (
              <>
                <View style={styles.controlCardHead}>
                  <Text style={[styles.sizingModeLabel, { color: theme.colors.textMuted }]}>
                    LOT SIZING
                  </Text>
                  <Text style={[styles.controlHint, { color: theme.colors.textMuted }]}>
                    Applies to new symbols
                  </Text>
                </View>
                <View style={styles.sizingModeChips}>
                  {(['auto', 'manual'] as const).map((m) => {
                    const selected = mt5LotSizingMode === m;
                    return (
                      <TouchableOpacity
                        key={m}
                        onPress={() => void setMt5LotSizingMode(m)}
                        activeOpacity={0.75}
                        style={[
                          styles.sizingModeChip,
                          {
                            borderColor: selected ? theme.colors.accent : theme.colors.borderColor,
                            backgroundColor: selected
                              ? `${theme.colors.accent}20`
                              : 'rgba(255,255,255,0.03)',
                          },
                        ]}
                      >
                        <Text
                          style={[
                            styles.sizingModeChipText,
                            {
                              color: theme.colors.textPrimary,
                              fontWeight: selected ? '700' : '500',
                            },
                          ]}
                        >
                          {m === 'auto' ? 'Auto (AI)' : 'Manual'}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </>
            )}
          </View>
        </View>

        <View style={styles.quotesLicenseShell}>
          <View style={styles.content}>
            {loading && !refreshing ? (
              <View style={styles.loadingContainer}>
                <ActivityIndicator testID="quotes-loading" size="large" color={theme.colors.accent} />
                <Text style={styles.loadingText}>Loading symbols...</Text>
              </View>
            ) : error ? (
              <View style={styles.errorContainer}>
                <Text style={styles.errorText}>{error}</Text>
                {hasConnectedEA ? (
                  <TouchableOpacity
                    style={[
                      styles.retryButton,
                      {
                        backgroundColor: `${theme.colors.accent}40`,
                        borderColor: `${theme.colors.accent}66`,
                        shadowColor: theme.colors.glowColor,
                      },
                    ]}
                    onPress={handleRetry}
                    activeOpacity={0.7}
                  >
                    {Platform.OS === 'ios' && (
                      <BlurView intensity={60} tint="light" style={StyleSheet.absoluteFill} />
                    )}
                    <Text style={styles.retryButtonText}>Retry</Text>
                  </TouchableOpacity>
                ) : (
                  <TouchableOpacity
                    style={[
                      styles.retryButton,
                      {
                        backgroundColor: `${theme.colors.accent}40`,
                        borderColor: `${theme.colors.accent}66`,
                        shadowColor: theme.colors.glowColor,
                      },
                    ]}
                    onPress={() => router.push('/license')}
                    activeOpacity={0.7}
                  >
                    {Platform.OS === 'ios' && (
                      <BlurView intensity={60} tint="light" style={StyleSheet.absoluteFill} />
                    )}
                    <Text style={styles.retryButtonText}>Link automation</Text>
                  </TouchableOpacity>
                )}
              </View>
            ) : (
              <ScrollView showsVerticalScrollIndicator={false}>
                {quotesWithActiveStatus.length === 0 ? (
                  <View style={styles.emptyContainer}>
                    <Text style={styles.emptyText}>No symbols configured</Text>
                    <Text style={styles.emptySubtext}>Configure symbols in your linked automation to see them here</Text>
                  </View>
                ) : (
                  quotesWithActiveStatus.map((quote) => (
                    <TouchableOpacity
                      testID={`quote-item-${quote.symbol}`}
                      key={quote.symbol}
                      style={[
                        styles.quoteCard,
                        {
                          borderColor: quote.isActive
                            ? `${theme.colors.accent}88`
                            : theme.colors.borderColor,
                          backgroundColor: theme.colors.backgroundSecondary,
                          shadowColor: quote.isActive ? theme.colors.accent : theme.colors.glowColor,
                        },
                        quote.isActive && styles.activeQuoteCard,
                      ]}
                      onPress={() => handleQuoteTap(quote.symbol)}
                      activeOpacity={0.78}
                    >
                      {Platform.OS === 'ios' && (
                        <BlurView intensity={22} tint="dark" style={StyleSheet.absoluteFill} pointerEvents="none" />
                      )}
                      <View style={styles.quoteHeader}>
                        <View style={styles.symbolContainer}>
                          <Text style={[styles.symbol, { color: theme.colors.textPrimary }]}>
                            {quote.symbol}
                          </Text>
                          {quote.isActive && (
                            <Circle
                              color={theme.colors.success}
                              fill={theme.colors.success}
                              size={9}
                              style={styles.activeIndicator}
                            />
                          )}
                        </View>
                        <Text
                          style={[
                            styles.quoteStatus,
                            { color: quote.isActive ? theme.colors.success : theme.colors.textMuted },
                          ]}
                        >
                          {quote.isActive ? 'Active' : 'Tap to set'}
                        </Text>
                      </View>

                      <View style={styles.priceContainer}>
                        <View style={styles.priceColumn}>
                          <Text style={[styles.priceLabel, { color: theme.colors.textMuted }]}>LOT SIZE</Text>
                          <Text style={[styles.priceValue, { color: theme.colors.textPrimary }]}>
                            {isMartingaleBot && martingaleLotSource === 'signal'
                              ? MARTINGALE_SIGNAL_LOT_LABEL
                              : formatLotSize(quote.lotSize)}
                          </Text>
                        </View>
                        <View style={styles.priceColumn}>
                          <Text style={[styles.priceLabel, { color: theme.colors.textMuted }]}>TRADES</Text>
                          <Text style={[styles.priceValue, { color: theme.colors.textPrimary }]}>
                            {quote.numberOfTrades}
                          </Text>
                        </View>
                        <View style={styles.priceColumn}>
                          <Text style={[styles.priceLabel, { color: theme.colors.textMuted }]}>PLATFORM</Text>
                          <Text style={[styles.platformValue, { color: theme.colors.textSecondary }]}>
                            {quote.platform}
                          </Text>
                        </View>
                        <View style={styles.priceColumn}>
                          <Text style={[styles.priceLabel, { color: theme.colors.textMuted }]}>DIRECTION</Text>
                          <Text
                            style={[
                              styles.directionValue,
                              {
                                color:
                                  quote.direction === 'BUY'
                                    ? theme.colors.success
                                    : quote.direction === 'SELL'
                                      ? theme.colors.error
                                      : theme.colors.warning,
                              },
                            ]}
                          >
                            {quote.direction}
                          </Text>
                        </View>
                      </View>
                    </TouchableOpacity>
                  ))
                )}
              </ScrollView>
            )}
          </View>
          {licenseExpired && <LicenseBlockedOverlay label="EXPIRED" />}
        </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: 'transparent',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 0.3,
    borderBottomColor: 'rgba(255, 255, 255, 0.08)',
    backgroundColor: Platform.OS === 'ios' ? 'transparent' : colors.glass.background,
  },
  backButton: {
    marginRight: 16,
    padding: 10,
    borderRadius: 24,
    borderWidth: 1,
    overflow: 'hidden',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 12,
    elevation: 6,
  },
  headerContent: {
    flex: 1,
  },
  titleContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    letterSpacing: 1,
    marginRight: 12,
  },
  statusContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginLeft: 8,
  },
  botName: {
    fontSize: 12,
    fontWeight: '500',
    marginLeft: 8,
  },
  sizingModeBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 12,
  },
  topHero: {
    paddingHorizontal: 20,
    paddingTop: 4,
    paddingBottom: 8,
  },
  topHeroRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  topHeroTitles: {
    flex: 1,
  },
  topKicker: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1.6,
  },
  topTitle: {
    fontSize: 28,
    fontWeight: '800',
    letterSpacing: -0.6,
    marginTop: 2,
  },
  topSubtitle: {
    fontSize: 13,
    fontWeight: '500',
    marginTop: 10,
    marginBottom: 14,
    lineHeight: 18,
  },
  topIconBtn: {
    width: 42,
    height: 42,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  topIconSpacer: {
    width: 42,
    height: 42,
  },
  controlCard: {
    borderRadius: 18,
    borderWidth: 1,
    padding: 14,
  },
  controlCardHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  controlHint: {
    fontSize: 11,
    fontWeight: '500',
  },
  sizingModeLabel: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1,
  },
  martingaleNotice: {
    fontSize: 13,
    lineHeight: 18,
    fontWeight: '600',
  },
  sizingModeChips: {
    flexDirection: 'row',
    gap: 8,
  },
  sizingModeChip: {
    flex: 1,
    paddingVertical: 11,
    paddingHorizontal: 14,
    borderRadius: 14,
    borderWidth: 1.5,
    alignItems: 'center',
  },
  sizingModeChipText: {
    fontSize: 13,
    letterSpacing: 0.3,
  },
  content: {
    flex: 1,
    paddingHorizontal: 20,
    paddingTop: 8,
  },
  quotesLicenseShell: {
    flex: 1,
    position: 'relative',
  },
  refreshButton: {
    width: 42,
    height: 42,
    borderRadius: auraUi.radius.sm,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  refreshButtonDisabled: {
    opacity: 0.5,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 60,
  },
  loadingText: {
    color: '#CCCCCC',
    fontSize: 16,
    marginTop: 16,
    fontWeight: '500',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 60,
    paddingHorizontal: 20,
  },
  errorText: {
    color: '#FF4444',
    fontSize: 16,
    textAlign: 'center',
    marginBottom: 20,
    lineHeight: 24,
  },
  retryButton: {
    paddingHorizontal: 32,
    paddingVertical: 16,
    borderRadius: 24,
    borderWidth: 1,
    overflow: 'hidden',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.4,
    shadowRadius: 16,
    elevation: 10,
  },
  retryButtonText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    color: '#CCCCCC',
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 8,
  },
  emptySubtext: {
    color: '#888888',
    fontSize: 14,
    textAlign: 'center',
  },
  quoteCard: {
    borderRadius: 20,
    padding: 16,
    marginBottom: 10,
    borderWidth: 1,
    overflow: 'hidden',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.22,
    shadowRadius: 16,
    elevation: 8,
    position: 'relative',
  },
  quoteHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 14,
    zIndex: 3,
  },
  symbolContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  symbol: {
    fontSize: 18,
    fontWeight: '800',
    letterSpacing: 0.4,
  },
  quoteStatus: {
    fontSize: 12,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
  activeIndicator: {
    marginLeft: 8,
  },
  activeQuoteCard: {
    borderWidth: 1.5,
    shadowOpacity: 0.35,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 10 },
    elevation: 12,
  },
  priceContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    zIndex: 3,
    gap: 4,
  },
  priceColumn: {
    alignItems: 'flex-start',
    flex: 1,
  },
  priceLabel: {
    fontSize: 9,
    fontWeight: '700',
    marginBottom: 5,
    letterSpacing: 0.8,
    textTransform: 'uppercase',
  },
  priceValue: {
    fontSize: 15,
    fontWeight: '700',
    fontFamily: 'monospace',
  },
  platformValue: {
    fontSize: 14,
    fontWeight: '700',
  },
  directionValue: {
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 0.3,
  },

});