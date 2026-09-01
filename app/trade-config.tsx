import React, { useMemo, useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, TextInput } from 'react-native';
import { Trash2 } from 'lucide-react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useApp, type MT5TradeMode } from '@/providers/app-provider';
import { useTheme } from '@/providers/theme-provider';
import { AuraScreen, AuraHeader, AuraCard, AuraButton } from '@/components/aura';
import { auraUi } from '@/constants/aura-ui';
import { getEquityBasedMT5Preset, sanitizeManualLotSize, sanitizeManualTradesCount } from '@/utils/equity-trade-preset';
import {
  isMartingaleEa,
  MARTINGALE_PLACEHOLDER_LOT,
  MARTINGALE_SIGNAL_LOT_LABEL,
  type MartingaleLotSource,
} from '@/utils/trading-features';

export default function TradeConfigScreen() {
  const { symbol: symbolParam } = useLocalSearchParams<{ symbol?: string | string[] }>();
  const symbol = useMemo(() => {
    const raw = symbolParam == null ? '' : Array.isArray(symbolParam) ? symbolParam[0] : symbolParam;
    const s = String(raw ?? '').trim();
    return s.length > 0 ? s : undefined;
  }, [symbolParam]);

  const {
    eas,
    activeSymbols,
    deactivateSymbol,
    mt5Symbols,
    activateMT5Symbol,
    deactivateMT5Symbol,
    mt5Account,
    mt5LotSizingMode,
    setMt5LotSizingMode,
    martingaleLotSource,
    setMartingaleLotSource,
  } = useApp();
  const { theme } = useTheme();
  const isMartingale = isMartingaleEa(eas);
  const useOwnMartingaleLot = isMartingale && martingaleLotSource === 'own';

  const preset = useMemo(
    () => getEquityBasedMT5Preset(mt5Account?.equity, symbol),
    [mt5Account?.equity, symbol]
  );

  /** Saved row for this symbol — same source as Quotes list (always show live stored values in Auto). */
  const savedMt5 = useMemo(
    () => (symbol ? mt5Symbols.find((s) => s.symbol === symbol) : undefined),
    [symbol, mt5Symbols]
  );
  const autoLotDisplay = savedMt5 ? savedMt5.lotSize : preset.lotSize;
  const autoTradesDisplay = savedMt5 ? savedMt5.numberOfTrades : preset.numberOfTrades;

  const [tradeMode, setTradeMode] = useState<MT5TradeMode>('swing');
  const [manualLot, setManualLot] = useState('0.01');
  const [manualTrades, setManualTrades] = useState('1');

  useEffect(() => {
    if (!symbol) return;
    const existing = mt5Symbols.find((s) => s.symbol === symbol);
    setTradeMode(existing?.tradeMode === 'scalper' ? 'scalper' : 'swing');
  }, [symbol, mt5Symbols]);

  useEffect(() => {
    if (!symbol) return;
    const existing = mt5Symbols.find((s) => s.symbol === symbol);
    const fb = getEquityBasedMT5Preset(mt5Account?.equity, symbol);
    if (existing) {
      setManualLot(existing.lotSize === MARTINGALE_PLACEHOLDER_LOT && !useOwnMartingaleLot ? fb.lotSize : existing.lotSize);
      setManualTrades(existing.numberOfTrades);
    } else {
      setManualLot(fb.lotSize);
      setManualTrades(fb.numberOfTrades);
    }
  }, [symbol, mt5Symbols, mt5Account?.equity, mt5LotSizingMode, useOwnMartingaleLot]);

  const isSymbolActive =
    mt5Symbols.some(s => s.symbol === symbol) || activeSymbols.some(s => s.symbol === symbol);

  const handleBack = () => {
    router.back();
  };

  const handleSetSymbol = () => {
    if (!symbol) return;
    const lot = isMartingale
      ? useOwnMartingaleLot
        ? sanitizeManualLotSize(manualLot)
        : MARTINGALE_PLACEHOLDER_LOT
      : mt5LotSizingMode === 'manual'
        ? sanitizeManualLotSize(manualLot)
        : preset.lotSize;
    const numberOfTrades = isMartingale
      ? sanitizeManualTradesCount(useOwnMartingaleLot ? manualTrades : autoTradesDisplay)
      : mt5LotSizingMode === 'manual'
        ? sanitizeManualTradesCount(manualTrades)
        : preset.numberOfTrades;
    activateMT5Symbol({
      symbol,
      lotSize: lot,
      direction: 'BOTH',
      numberOfTrades,
      tradeMode,
    });
    router.back();
  };

  const handleRemoveSymbol = () => {
    if (!symbol) return;
    deactivateSymbol(symbol);
    deactivateMT5Symbol(symbol);
    router.back();
  };

  return (
    <AuraScreen scroll>
      <AuraHeader
        kicker="Symbol setup"
        title={symbol ?? 'Configure trade'}
        subtitle={
          isMartingale
            ? 'Martingale automation — choose lot from signal or your own'
            : 'Lots: Auto/Manual · Scalper/Swing = execution style'
        }
        onBack={handleBack}
      />

      {!mt5Account?.connected && (
        <Text style={[styles.warn, { color: theme.colors.warning }]}>
          Connect MetaTrader for live equity in Auto mode.
        </Text>
      )}

      <AuraCard accent>
        <View style={styles.cardContent}>
          <View style={styles.configSection}>
            {isMartingale ? (
              <>
                <Text style={[styles.sectionTitle, { color: theme.colors.textMuted }]}>LOT SOURCE</Text>
                <View style={styles.modeRow}>
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
                          styles.modeChip,
                          {
                            borderColor: selected ? theme.colors.accent : theme.colors.borderColor,
                            backgroundColor: selected
                              ? `${theme.colors.accent}22`
                              : 'rgba(255,255,255,0.04)',
                          },
                        ]}
                      >
                        <Text
                          style={[
                            styles.modeChipText,
                            {
                              color: theme.colors.textPrimary,
                              fontWeight: selected ? '800' : '600',
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
                <Text style={[styles.sectionTitle, { color: theme.colors.textMuted }]}>LOT SIZING</Text>
                <View style={styles.modeRow}>
                  {(['auto', 'manual'] as const).map((m) => {
                    const selected = mt5LotSizingMode === m;
                    return (
                      <TouchableOpacity
                        key={m}
                        onPress={() => void setMt5LotSizingMode(m)}
                        activeOpacity={0.75}
                        style={[
                          styles.modeChip,
                          {
                            borderColor: selected ? theme.colors.accent : theme.colors.borderColor,
                            backgroundColor: selected
                              ? `${theme.colors.accent}22`
                              : 'rgba(255,255,255,0.04)',
                          },
                        ]}
                      >
                        <Text
                          style={[
                            styles.modeChipText,
                            {
                              color: theme.colors.textPrimary,
                              fontWeight: selected ? '800' : '600',
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

          <View style={styles.configSection}>
            <Text style={[styles.sectionTitle, { color: theme.colors.textMuted }]}>TRADE MODE</Text>
            <View style={styles.modeRow}>
              {(['scalper', 'swing'] as const).map((m) => {
                const selected = tradeMode === m;
                return (
                  <TouchableOpacity
                    key={m}
                    onPress={() => setTradeMode(m)}
                    activeOpacity={0.75}
                    style={[
                      styles.modeChip,
                      {
                        borderColor: selected ? theme.colors.accent : theme.colors.borderColor,
                        backgroundColor: selected
                          ? `${theme.colors.accent}22`
                          : 'rgba(255,255,255,0.04)',
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.modeChipText,
                        {
                          color: theme.colors.textPrimary,
                          fontWeight: selected ? '800' : '600',
                        },
                      ]}
                    >
                      {m === 'scalper' ? 'Scalper' : 'Swing'}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>

          {isMartingale && !useOwnMartingaleLot ? (
            <>
              <ReadOnlyRow label="LOT SIZE" value={MARTINGALE_SIGNAL_LOT_LABEL} />
              <ReadOnlyRow label="NUMBER OF TRADES" value={String(autoTradesDisplay)} />
            </>
          ) : isMartingale && useOwnMartingaleLot ? (
            <>
              <ManualFieldRow
                label="LOT SIZE"
                value={manualLot}
                onChangeText={setManualLot}
                keyboardType="decimal-pad"
              />
              <ManualFieldRow
                label="NUMBER OF TRADES"
                value={manualTrades}
                onChangeText={setManualTrades}
                keyboardType="number-pad"
              />
            </>
          ) : mt5LotSizingMode === 'auto' ? (
            <>
              <ReadOnlyRow label="LOT SIZE" value={autoLotDisplay} />
              <ReadOnlyRow label="NUMBER OF TRADES" value={String(autoTradesDisplay)} />
            </>
          ) : (
            <>
              <ManualFieldRow
                label="LOT SIZE"
                value={manualLot}
                onChangeText={setManualLot}
                keyboardType="decimal-pad"
              />
              <ManualFieldRow
                label="NUMBER OF TRADES"
                value={manualTrades}
                onChangeText={setManualTrades}
                keyboardType="number-pad"
              />
            </>
          )}
          <ReadOnlyRow label="DIRECTION" value="BOTH" />
          <ReadOnlyRow label="PLATFORM" value="MT5" />
          {mt5Account?.equity != null && mt5Account.equity !== '' && (
            <ReadOnlyRow label="ACCOUNT EQUITY (REFERENCE)" value={String(mt5Account.equity)} />
          )}

          <View style={styles.buttonContainer}>
            <AuraButton
              label={isSymbolActive ? 'Confirm / re-sync symbol' : 'Set symbol'}
              onPress={handleSetSymbol}
            />
            {isSymbolActive && (
              <AuraButton
                label="Remove symbol"
                onPress={handleRemoveSymbol}
                variant="ghost"
                icon={<Trash2 color={theme.colors.error} size={18} strokeWidth={2.2} />}
              />
            )}
          </View>
        </View>
      </AuraCard>
    </AuraScreen>
  );
}

function ManualFieldRow({
  label,
  value,
  onChangeText,
  keyboardType,
}: {
  label: string;
  value: string;
  onChangeText: (t: string) => void;
  keyboardType: 'decimal-pad' | 'number-pad';
}) {
  const { theme } = useTheme();
  return (
    <View style={styles.configSection}>
      <Text style={[styles.sectionTitle, { color: theme.colors.textMuted }]}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        keyboardType={keyboardType}
        placeholderTextColor={theme.colors.textMuted}
        style={[
          styles.manualInput,
          {
            color: theme.colors.textPrimary,
            borderColor: theme.colors.borderColor,
            backgroundColor: 'rgba(8, 10, 15, 0.55)',
          },
        ]}
      />
    </View>
  );
}

function ReadOnlyRow({ label, value }: { label: string; value: string }) {
  const { theme } = useTheme();
  return (
    <View style={styles.configSection}>
      <Text style={[styles.sectionTitle, { color: theme.colors.textMuted }]}>{label}</Text>
      <View
        style={[
          styles.readOnlyBox,
          {
            backgroundColor: 'rgba(8, 10, 15, 0.55)',
            borderColor: theme.colors.borderColor,
          },
        ]}
      >
        <Text style={[styles.readOnlyText, { color: theme.colors.textPrimary }]}>{value}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  warn: {
    fontSize: 13,
    lineHeight: 19,
    marginBottom: auraUi.space.md,
    fontWeight: '600',
  },
  cardContent: {
    padding: 0,
  },
  configSection: {
    marginBottom: 18,
  },
  sectionTitle: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1.1,
    marginBottom: 10,
    textTransform: 'uppercase',
  },
  readOnlyBox: {
    borderRadius: 16,
    overflow: 'hidden',
    position: 'relative',
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  readOnlyText: {
    fontSize: 17,
    fontWeight: '700',
    zIndex: 1,
  },
  manualInput: {
    borderRadius: 16,
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 14,
    fontSize: 17,
    fontWeight: '700',
  },
  modeRow: {
    flexDirection: 'row',
    gap: 10,
    flexWrap: 'wrap',
  },
  modeChip: {
    flex: 1,
    minWidth: 120,
    paddingVertical: 13,
    paddingHorizontal: 14,
    borderRadius: 14,
    borderWidth: 1.5,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modeChipText: {
    fontSize: 15,
    letterSpacing: 0.2,
  },
  buttonContainer: {
    marginTop: 20,
    marginBottom: 8,
    gap: 12,
  },
});
