import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Pressable,
  ActivityIndicator,
  Image,
  ScrollView,
  Platform,
  Alert,
  InteractionManager,
  AppState,
} from 'react-native';
import {
  Scan,
  Upload,
  TrendingUp,
  TrendingDown,
  Minus,
  Trash2,
  History,
  Zap,
} from 'lucide-react-native';
import * as ImagePicker from 'expo-image-picker';
import * as ImageManipulator from 'expo-image-manipulator';
import * as FileSystem from 'expo-file-system';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { router } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { getScreenBackgroundColor, useTheme } from '@/providers/theme-provider';
import { AuraHeader, AuraAtmosphere } from '@/components/aura';
import { auraUi } from '@/constants/aura-ui';
import {
  useApp,
  type MT5TradeMode,
  type SignalLog,
} from '@/providers/app-provider';
import { apiService, type ChartAnalysisResult } from '@/services/api';
import { stripNumericPrice, computeFallbackSlTp, ensureMinRewardRisk } from '@/utils/trade-mode-levels';
import { getTradeModeForAnalysis, resolveConfiguredMt5QuotesSymbol, quoteSetNotFoundMessage } from '@/utils/trade-symbol-match';

const SCANNER_HISTORY_KEY = 'ai-scanner-history';
/** Daily scan quota: `{ date: 'YYYY-MM-DD', count: number }` */
const SCANNER_DAILY_USAGE_KEY = 'ai-scanner-daily-usage';
/** Legacy key from paid-batch unlock era — migrated once into daily usage. */
const SCANNER_UPLOAD_COUNT_KEY = 'ai-scanner-upload-count';
const MAX_HISTORY = 5;
/** Free open scanner — no unlock / payment. Resets each calendar day (local). */
const MAX_SCANS_PER_DAY = 10;

function todayLocalDateKey(): string {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

async function loadDailyScanUsage(): Promise<{ date: string; count: number }> {
  const today = todayLocalDateKey();
  try {
    const raw = await AsyncStorage.getItem(SCANNER_DAILY_USAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as { date?: string; count?: number };
      if (parsed?.date === today) {
        return { date: today, count: Math.max(0, Number(parsed.count) || 0) };
      }
    }
    // One-time migrate old batch counter into today's usage (capped).
    const legacyRaw = await AsyncStorage.getItem(SCANNER_UPLOAD_COUNT_KEY);
    if (legacyRaw != null) {
      const legacy = Math.max(0, parseInt(legacyRaw, 10) || 0);
      const count = Math.min(legacy, MAX_SCANS_PER_DAY);
      const next = { date: today, count };
      await AsyncStorage.setItem(SCANNER_DAILY_USAGE_KEY, JSON.stringify(next));
      await AsyncStorage.removeItem(SCANNER_UPLOAD_COUNT_KEY);
      return next;
    }
  } catch {
    /* ignore */
  }
  return { date: today, count: 0 };
}

async function bumpDailyScanUsage(): Promise<number> {
  const today = todayLocalDateKey();
  const current = await loadDailyScanUsage();
  const count = (current.date === today ? current.count : 0) + 1;
  await AsyncStorage.setItem(SCANNER_DAILY_USAGE_KEY, JSON.stringify({ date: today, count }));
  return count;
}

/** Builds the same `SignalLog` shape the signal monitor uses, so MT5 execution runs the same path. */
function buildSignalFromScanner(
  result: ChartAnalysisResult,
  asset: string,
  levelOverrides?: { sl?: string; tp?: string }
): SignalLog {
  const tp = stripNumericPrice(levelOverrides?.tp ?? result.takeProfit1 ?? '');
  const price = stripNumericPrice(result.entryPrice || result.currentPrice);
  const direction = result.signal === 'BUY' ? 'buy' : 'sell';
  return {
    id: `ai-scan-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
    asset,
    action: direction,
    price: price || '0',
    tp,
    sl: stripNumericPrice(levelOverrides?.sl ?? result.stopLoss),
    time: new Date().toISOString(),
    type: 'AI_SCANNER',
    source: 'ai_scanner',
  };
}

export interface ScannerHistoryItem {
  id: string;
  timestamp: number;
  imageUri: string;
  imageBase64?: string; // persisted in AsyncStorage for display after app restart
  result: ChartAnalysisResult;
}

export default function AIScannerScreen() {
  const { theme } = useTheme();
  const screenBg = getScreenBackgroundColor(theme);
  const {
    mt5Account,
    mt5Symbols,
    mt4Symbols,
    activeSymbols,
    isSymbolConfiguredForTrading,
    pausePolling,
    setMT5Signal,
    setMT5TradeOverlayMessage,
    setShowMT5SignalWebView,
  } = useApp();
  const [imageUri, setImageUri] = useState<string | null>(null);
  const [imageBase64, setImageBase64] = useState<string | null>(null);
  const [mimeType, setMimeType] = useState<string>('image/jpeg');
  const [analyzing, setAnalyzing] = useState(false);
  const [result, setResult] = useState<ChartAnalysisResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [history, setHistory] = useState<ScannerHistoryItem[]>([]);
  const [uploadCount, setUploadCount] = useState(0);
  const scrollRef = useRef<ScrollView>(null);

  const scrollToHistory = useCallback(() => {
    scrollRef.current?.scrollToEnd({ animated: true });
  }, []);

  const resetScannerSessionState = useCallback(() => {
    setImageUri(null);
    setImageBase64(null);
    setResult(null);
    setError(null);
    setAnalyzing(false);
  }, []);

  const loadHistory = useCallback(async () => {
    try {
      const raw = await AsyncStorage.getItem(SCANNER_HISTORY_KEY);
      const items = raw ? (JSON.parse(raw) as ScannerHistoryItem[]) : [];
      setHistory(Array.isArray(items) ? items.slice(0, MAX_HISTORY) : []);
    } catch {
      setHistory([]);
    }
  }, []);

  const saveToHistory = useCallback(
    async (imageUri: string, imageBase64: string | null, result: ChartAnalysisResult): Promise<number> => {
      const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      const item: ScannerHistoryItem = {
        id,
        timestamp: Date.now(),
        imageUri,
        imageBase64: imageBase64 || undefined,
        result,
      };
      const raw = await AsyncStorage.getItem(SCANNER_HISTORY_KEY);
      const current = raw ? (JSON.parse(raw) as ScannerHistoryItem[]) : [];
      const next = [item, ...(Array.isArray(current) ? current : [])].slice(0, MAX_HISTORY);
      setHistory(next);
      await AsyncStorage.setItem(SCANNER_HISTORY_KEY, JSON.stringify(next));
      const count = await bumpDailyScanUsage();
      setUploadCount(count);
      return count;
    },
    []
  );

  const clearAllHistory = useCallback(async () => {
    await AsyncStorage.removeItem(SCANNER_HISTORY_KEY);
    setHistory([]);
  }, []);

  const loadHistoryItem = useCallback((item: ScannerHistoryItem) => {
    const uri = item.imageBase64
      ? `data:image/jpeg;base64,${item.imageBase64}`
      : item.imageUri;
    if (uri) setImageUri(uri);
    setResult(item.result);
    setError(null);
    setImageBase64(item.imageBase64 || null);
    scrollRef.current?.scrollTo({ y: 0, animated: true });
  }, []);

  const removeHistoryItem = useCallback(
    async (id: string) => {
      const next = history.filter((h) => h.id !== id);
      setHistory(next);
      await AsyncStorage.setItem(SCANNER_HISTORY_KEY, JSON.stringify(next));
    },
    [history]
  );

  const handleBack = () => router.back();

  const refreshDailyQuota = useCallback(async () => {
    const usage = await loadDailyScanUsage();
    setUploadCount(usage.count);
  }, []);

  const handleDailyLimitReached = useCallback(() => {
    resetScannerSessionState();
    setError(null);
    Alert.alert(
      'Daily limit reached',
      `You have used all ${MAX_SCANS_PER_DAY} free chart scans for today. Come back tomorrow for another ${MAX_SCANS_PER_DAY}.`
    );
  }, [resetScannerSessionState]);

  useEffect(() => {
    void (async () => {
      await refreshDailyQuota();
      await loadHistory();
    })();
  }, [refreshDailyQuota, loadHistory]);

  useFocusEffect(
    useCallback(() => {
      void (async () => {
        await refreshDailyQuota();
        await loadHistory();
      })();
    }, [refreshDailyQuota, loadHistory])
  );

  useEffect(() => {
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        void refreshDailyQuota();
      }
    });
    return () => sub.remove();
  }, [refreshDailyQuota]);

  /** Android returns content:// from the gallery; copy to app cache with correct extension so the manipulator can read it. */
  const ensureReadableImageUri = async (uri: string, mimeType?: string | null): Promise<string> => {
    if (Platform.OS !== 'android') return uri;
    if (!uri.startsWith('content://')) return uri;
    const dir = FileSystem.cacheDirectory;
    if (!dir) return uri;
    const mt = (mimeType || '').toLowerCase();
    const ext = mt.includes('png') ? 'png' : mt.includes('webp') ? 'webp' : 'jpg';
    const dest = `${dir}scanner-pick-${Date.now()}.${ext}`;
    try {
      await FileSystem.copyAsync({ from: uri, to: dest });
      return dest;
    } catch (e) {
      console.warn('[AI Scanner] copy content URI failed:', e);
      return uri;
    }
  };

  // Resize and compress to keep payload small (avoids Render 502 with large requests)
  const compressForAnalysis = async (
    uri: string,
    existingBase64: string | undefined,
    existingMime: string | undefined
  ): Promise<{ uri: string; base64: string | null; mimeType: string }> => {
    const readBase64FromFile = async (fileUri: string): Promise<string | null> => {
      try {
        return await FileSystem.readAsStringAsync(fileUri, {
          encoding: FileSystem.EncodingType.Base64,
        });
      } catch {
        return null;
      }
    };

    // Smaller frames keep payloads small; 600px matches EA Trade chart readability.
    const jpegOut = {
      compress: 0.4,
      format: ImageManipulator.SaveFormat.JPEG,
      base64: true as const,
    };

    // 1) Resize + compress (ideal)
    try {
      const manipulated = await ImageManipulator.manipulateAsync(
        uri,
        [{ resize: { width: 600 } }],
        jpegOut
      );
      let base64 = manipulated.base64 ?? existingBase64 ?? null;
      if (!base64 && manipulated.uri) {
        base64 = await readBase64FromFile(manipulated.uri);
      }
      if (base64) {
        return { uri: manipulated.uri, base64, mimeType: 'image/jpeg' };
      }
    } catch (e) {
      console.warn('[AI Scanner] resize step failed:', e);
    }

    // 2) Re-encode only (some Android images fail resize but work as full-frame JPEG)
    try {
      const encoded = await ImageManipulator.manipulateAsync(uri, [], {
        compress: 0.45,
        format: ImageManipulator.SaveFormat.JPEG,
        base64: true,
      });
      let base64 = encoded.base64 ?? null;
      if (!base64 && encoded.uri) {
        base64 = await readBase64FromFile(encoded.uri);
      }
      if (base64) {
        return { uri: encoded.uri, base64, mimeType: 'image/jpeg' };
      }
    } catch (e) {
      console.warn('[AI Scanner] re-encode step failed:', e);
    }

    // 3) Raw base64 from file or content URI (Android SAF)
    let base64 = existingBase64 ?? (await readBase64FromFile(uri));
    return {
      uri,
      base64,
      mimeType: existingMime || 'image/jpeg',
    };
  };

  const pickImage = async () => {
    if (uploadCount >= MAX_SCANS_PER_DAY) {
      handleDailyLimitReached();
      return;
    }
    setError(null);
    setResult(null);
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert('Permission needed', 'Please allow access to your photo library to upload charts.');
      return;
    }
    // Android: legacy picker + full quality avoids broken URIs / double compression; we compress in JS.
    // iOS: quality 0.4 is fine; native base64 optional.
    const pickerResult = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: false,
      quality: Platform.OS === 'android' ? 1 : 0.4,
      base64: Platform.OS === 'ios',
      ...(Platform.OS === 'android'
        ? { legacy: true, defaultTab: 'photos' as ImagePicker.DefaultTab }
        : {}),
    });
    if (pickerResult.canceled) return;
    const asset = pickerResult.assets?.[0];
    if (!asset?.uri) {
      setError('No image was selected.');
      return;
    }
    const readableUri = await ensureReadableImageUri(asset.uri, asset.mimeType);
    const { uri, base64, mimeType } = await compressForAnalysis(readableUri, asset.base64, asset.mimeType);
    if (!base64) {
      setError('Could not read this image. Try another photo or take a new screenshot.');
      return;
    }
    setImageUri(uri);
    setImageBase64(base64);
    setMimeType(mimeType || 'image/jpeg');
  };

  const takePhoto = async () => {
    if (uploadCount >= MAX_SCANS_PER_DAY) {
      handleDailyLimitReached();
      return;
    }
    setError(null);
    setResult(null);
    const { status } = await ImagePicker.requestCameraPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert('Permission needed', 'Please allow camera access to take a chart photo.');
      return;
    }
    const pickerResult = await ImagePicker.launchCameraAsync({
      allowsEditing: false,
      quality: Platform.OS === 'android' ? 1 : 0.4,
      base64: Platform.OS === 'ios',
    });
    if (pickerResult.canceled) return;
    const asset = pickerResult.assets?.[0];
    if (!asset?.uri) {
      setError('No photo was captured.');
      return;
    }
    const readableUri = await ensureReadableImageUri(asset.uri, asset.mimeType);
    const { uri, base64, mimeType } = await compressForAnalysis(readableUri, asset.base64, asset.mimeType);
    if (!base64) {
      setError('Could not read this photo. Please try again.');
      return;
    }
    setImageUri(uri);
    setImageBase64(base64);
    setMimeType(mimeType || 'image/jpeg');
  };

  const analyzeChart = async () => {
    if (!imageBase64) {
      setError('Please upload a chart image first.');
      return;
    }
    if (uploadCount >= MAX_SCANS_PER_DAY) {
      handleDailyLimitReached();
      return;
    }
    // Client-side size check to avoid 502 (Render limits)
    if (imageBase64.length > 1_000_000) {
      setError('Image too large. Tap Change and use a smaller screenshot or crop the chart.');
      return;
    }
    setAnalyzing(true);
    setError(null);
    setResult(null);
    const maxAttempts = 4;
    const tradeModeOpt = { tradeMode: getTradeModeForAnalysis(undefined, mt5Symbols) };
    try {
      let lastErr = 'Analysis failed. Please try again.';
      for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        if (attempt > 1) {
          await new Promise((r) => setTimeout(r, 600 + attempt * 350));
        }
        const response = await apiService.analyzeChart(imageBase64, mimeType, tradeModeOpt);
        if (response.message === 'accept' && response.data) {
          setResult(response.data);
          if (imageUri) {
            const newCount = await saveToHistory(imageUri, imageBase64, response.data);
            if (newCount >= MAX_SCANS_PER_DAY) {
              handleDailyLimitReached();
            }
          }
          return;
        }
        lastErr = response.error || lastErr;
      }
      setError(lastErr);
    } catch (e) {
      setError('Something went wrong. Please try again.');
    } finally {
      setAnalyzing(false);
    }
  };

  const clearImage = () => {
    setImageUri(null);
    setImageBase64(null);
    setResult(null);
    setError(null);
  };

  const handleTakeTrade = useCallback(async () => {
    if (!result) return;
    if (result.signal === 'NEUTRAL') {
      Alert.alert(
        'No trade direction',
        'The analysis is neutral. Choose a chart with a clear buy or sell suggestion before taking a trade.'
      );
      return;
    }
    const openTradingNotice = (message: string) => {
      setMT5Signal(null);
      setMT5TradeOverlayMessage(message);
      setShowMT5SignalWebView(true);
    };

    const hasMt5Creds =
      Boolean(mt5Account?.login?.trim()) &&
      Boolean(String(mt5Account?.password ?? '').length > 0);
    if (!hasMt5Creds) {
      router.push('/(tabs)/metatrader');
      return;
    }

    const resolved = resolveConfiguredMt5QuotesSymbol(
      result.symbol,
      mt5Symbols,
      activeSymbols
    );
    if (!resolved || !isSymbolConfiguredForTrading(resolved.symbol)) {
      openTradingNotice(quoteSetNotFoundMessage(result.symbol || ''));
      return;
    }

    const symCfg = mt5Symbols.find((s) => s.symbol === resolved.symbol);
    const tradeMode: MT5TradeMode = symCfg?.tradeMode === 'scalper' ? 'scalper' : 'swing';

    const dir = result.signal === 'SELL' ? 'SELL' : 'BUY';
    let sl = stripNumericPrice(result.stopLoss);
    let tp = stripNumericPrice(result.takeProfit1 || '');
    const entryStr = stripNumericPrice(result.entryPrice || result.currentPrice);
    const entryNum = parseFloat(entryStr);
    if ((!sl || !tp) && entryNum && Number.isFinite(entryNum)) {
      const fb = computeFallbackSlTp(dir, entryNum, tradeMode);
      if (fb) {
        if (!sl) sl = fb.sl;
        if (!tp) tp = fb.tp;
      }
    }
    if (!sl || !tp) {
      openTradingNotice('Unable to read prices — add SL and TP to the analysis or retake the scan.');
      return;
    }
    if (entryNum && Number.isFinite(entryNum)) {
      const slN = parseFloat(String(sl).replace(/,/g, ''));
      const tpN = parseFloat(String(tp).replace(/,/g, ''));
      if (Number.isFinite(slN) && Number.isFinite(tpN)) {
        tp = ensureMinRewardRisk(dir, entryNum, slN, tpN);
      }
    }

    const signal = buildSignalFromScanner(result, resolved.symbol, { sl, tp });
    await pausePolling();

    const openExecution = () => {
      setMT5TradeOverlayMessage(null);
      setMT5Signal(signal);
      setShowMT5SignalWebView(true);
    };
    if (Platform.OS === 'web') {
      requestAnimationFrame(openExecution);
    } else {
      InteractionManager.runAfterInteractions(() => {
        requestAnimationFrame(openExecution);
      });
    }
  }, [
    result,
    mt5Account,
    mt5Symbols,
    activeSymbols,
    isSymbolConfiguredForTrading,
    pausePolling,
    setMT5Signal,
    setMT5TradeOverlayMessage,
    setShowMT5SignalWebView,
  ]);

  const SignalIcon = result?.signal === 'BUY' ? TrendingUp : result?.signal === 'SELL' ? TrendingDown : Minus;
  const signalColor =
    result?.signal === 'BUY'
      ? theme.colors.success
      : result?.signal === 'SELL'
        ? theme.colors.error
        : theme.colors.textMuted;

  const dailyLimitReached = uploadCount >= MAX_SCANS_PER_DAY;
  const scansRemaining = Math.max(0, MAX_SCANS_PER_DAY - uploadCount);

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: screenBg }]}>
      <AuraAtmosphere />
      <AuraHeader
        kicker="AI"
        title="Chart scanner"
        subtitle="Upload a chart → get BUY/SELL, entry, SL, and TP"
        onBack={handleBack}
        right={
          history.length > 0 ? (
            <TouchableOpacity
              style={[
                styles.historyHeaderBtn,
                { borderColor: theme.colors.borderColor },
              ]}
              onPress={scrollToHistory}
              activeOpacity={0.75}
            >
              <History color={theme.colors.accent} size={18} strokeWidth={2.2} />
            </TouchableOpacity>
          ) : undefined
        }
      />

      <View style={[styles.contentWrapper, { backgroundColor: 'transparent' }]}>
        <ScrollView
          ref={scrollRef}
          style={[styles.scroll, { backgroundColor: 'transparent' }]}
          contentContainerStyle={[styles.scrollContent, { paddingBottom: 120 }]}
          showsVerticalScrollIndicator={false}
        >
        <Text
          style={[
            styles.sectionEyebrow,
            { color: theme.colors.textMuted },
          ]}
        >
          1. CAPTURE
        </Text>
        <View style={styles.captureRow}>
          <TouchableOpacity
            style={[
              styles.uploadCard,
              styles.uploadCardFlex,
              {
                borderColor: `${theme.colors.accent}44`,
                backgroundColor: theme.colors.backgroundSecondary,
                shadowColor: theme.colors.glowColor,
              },
            ]}
            onPress={pickImage}
            activeOpacity={0.8}
          >
            {imageUri ? (
              <View style={styles.previewContainer}>
                <Image source={{ uri: imageUri }} style={styles.previewImage} resizeMode="contain" />
                <TouchableOpacity
                  style={[
                    styles.clearButton,
                    { backgroundColor: `${theme.colors.accent}44`, borderColor: `${theme.colors.accent}66` },
                  ]}
                  onPress={clearImage}
                  activeOpacity={0.8}
                >
                  <Text style={[styles.clearButtonText, { color: theme.colors.textPrimary }]}>Change</Text>
                </TouchableOpacity>
              </View>
            ) : (
              <View style={styles.uploadPlaceholder}>
                <Upload color={theme.colors.textMuted} size={36} strokeWidth={2} />
                <Text style={[styles.uploadText, { color: theme.colors.textPrimary }]}>
                  Upload chart
                </Text>
                <Text style={[styles.uploadHint, { color: theme.colors.textMuted }]}>
                  MT5 / TradingView screenshot
                </Text>
              </View>
            )}
          </TouchableOpacity>

          {!imageUri && (
            <TouchableOpacity
              style={[
                styles.cameraSideBtn,
                {
                  borderColor: theme.colors.borderColor,
                  backgroundColor: theme.colors.backgroundSecondary,
                },
              ]}
              onPress={takePhoto}
              activeOpacity={0.7}
            >
              <Scan color={theme.colors.accent} size={22} strokeWidth={2} />
              <Text style={[styles.cameraSideText, { color: theme.colors.textPrimary }]}>
                Camera
              </Text>
            </TouchableOpacity>
          )}
        </View>

        {imageUri && (
          <>
            <Text style={[styles.sectionEyebrow, { color: theme.colors.textMuted }]}>2. ANALYZE</Text>
            {!dailyLimitReached && (
              <Text style={[styles.uploadCountText, { color: theme.colors.textMuted }]}>
                {scansRemaining} of {MAX_SCANS_PER_DAY} free scans left today
              </Text>
            )}
            {dailyLimitReached && (
              <View style={[styles.limitBanner, { backgroundColor: `${theme.colors.warning}22`, borderColor: theme.colors.warning }]}>
                <Text style={[styles.limitBannerText, { color: theme.colors.warning }]}>
                  Daily limit reached ({MAX_SCANS_PER_DAY} scans). Come back tomorrow.
                </Text>
              </View>
            )}
            <TouchableOpacity
              style={[
                styles.analyzeButton,
                {
                  backgroundColor: theme.colors.accent,
                  borderColor: theme.colors.accent,
                  shadowColor: theme.colors.accent,
                },
                dailyLimitReached && styles.analyzeButtonDisabled,
              ]}
              onPress={analyzeChart}
              disabled={analyzing || dailyLimitReached}
              activeOpacity={0.85}
            >
              {analyzing ? (
                <ActivityIndicator color={theme.colors.onAccent} size="small" />
              ) : (
                <>
                  <Scan color={theme.colors.onAccent} size={20} strokeWidth={2} />
                  <Text style={[styles.analyzeButtonText, { color: theme.colors.onAccent }]}>
                    Analyze chart
                  </Text>
                </>
              )}
            </TouchableOpacity>
          </>
        )}

        {error && (
          <View
            style={[
              styles.resultCard,
              styles.errorCard,
              { borderColor: theme.colors.error },
            ]}
          >
            <Text style={[styles.errorText, { color: theme.colors.error }]}>{error}</Text>
          </View>
        )}

        {result && (
          <>
            <Text style={[styles.sectionEyebrow, { color: theme.colors.textMuted }]}>3. RESULT</Text>
            <View
              style={[
                styles.resultCard,
                {
                  borderColor: signalColor,
                  backgroundColor: theme.colors.backgroundSecondary,
                },
              ]}
            >
              {(result.symbol || result.timeframe || result.currentPrice) ? (
                <View style={[styles.chartMeta, { borderBottomColor: theme.colors.borderColor }]}>
                  {result.symbol ? (
                    <Text style={[styles.chartMetaText, { color: theme.colors.textPrimary }]}>
                      {result.symbol}
                    </Text>
                  ) : null}
                  {result.timeframe ? (
                    <Text style={[styles.chartMetaText, { color: theme.colors.textMuted }]}>
                      {result.timeframe}
                    </Text>
                  ) : null}
                  {result.currentPrice ? (
                    <Text style={[styles.chartMetaText, styles.chartMetaPrice, { color: theme.colors.accent }]}>
                      {result.currentPrice}
                    </Text>
                  ) : null}
                </View>
              ) : null}
              <View style={styles.signalHeader}>
                <SignalIcon color={signalColor} size={28} strokeWidth={2.5} />
                <View style={{ flex: 1 }}>
                  <Text style={[styles.signalLabel, { color: theme.colors.textMuted }]}>SIGNAL</Text>
                  <Text style={[styles.signalValue, { color: signalColor }]}>
                    {result.signal === 'NEUTRAL' ? '—' : result.signal}
                  </Text>
                </View>
                <View style={[styles.confidenceBadge, { backgroundColor: `${signalColor}22` }]}>
                  <Text style={[styles.confidenceText, { color: signalColor }]}>
                    {result.confidence}
                  </Text>
                </View>
              </View>
              <Text style={[styles.summaryText, { color: theme.colors.textPrimary }]}>{result.summary}</Text>

              <View style={[styles.levelsGrid, { borderColor: theme.colors.borderColor }]}>
                <View style={styles.levelCell}>
                  <Text style={[styles.tradeLabel, { color: theme.colors.textMuted }]}>Entry</Text>
                  <Text style={[styles.tradeValue, { color: theme.colors.textPrimary }]}>
                    {result.entryPrice || '—'}
                  </Text>
                </View>
                <View style={styles.levelCell}>
                  <Text style={[styles.tradeLabel, { color: theme.colors.textMuted }]}>Stop loss</Text>
                  <Text style={[styles.tradeValue, { color: theme.colors.error }]}>
                    {result.stopLoss || '—'}
                  </Text>
                </View>
                <View style={[styles.levelCell, styles.levelCellWide]}>
                  <Text style={[styles.tradeLabel, { color: theme.colors.textMuted }]}>Take profit</Text>
                  <Text style={[styles.tradeValue, { color: theme.colors.success }]}>
                    {result.takeProfit1 || result.takeProfit2 || result.takeProfit3
                      ? [result.takeProfit1, result.takeProfit2, result.takeProfit3].filter(Boolean).join(' / ')
                      : '—'}
                  </Text>
                </View>
              </View>

              <TouchableOpacity
                style={[
                  styles.takeTradeButton,
                  { backgroundColor: theme.colors.success },
                  result.signal === 'NEUTRAL' && styles.takeTradeButtonDisabled,
                ]}
                onPress={handleTakeTrade}
                disabled={result.signal === 'NEUTRAL'}
                activeOpacity={0.85}
              >
                <Zap color={theme.colors.onAccent} size={20} strokeWidth={2.5} />
                <Text style={[styles.takeTradeButtonText, { color: theme.colors.onAccent }]}>Take trade</Text>
              </TouchableOpacity>

              <Text style={[styles.reasoningLabel, { color: theme.colors.textMuted }]}>Why</Text>
              <Text style={[styles.reasoningText, { color: theme.colors.textSecondary }]}>
                {result.reasoning || 'No analysis provided.'}
              </Text>
              <Text style={[styles.suggestionLabel, { color: theme.colors.textMuted }]}>Next step</Text>
              <Text style={[styles.suggestionText, { color: theme.colors.textPrimary }]}>
                {result.suggestion || 'Review entry, stop, and targets above.'}
              </Text>
            </View>
          </>
        )}

        {history.length > 0 && (
          <View
            style={[
              styles.historySection,
              { borderColor: theme.colors.borderColor },
            ]}
          >
            <View style={styles.historyHeader}>
              <History color={theme.colors.textMuted} size={18} strokeWidth={2} />
              <Text style={[styles.historyTitle, { color: theme.colors.textPrimary }]}>Recent scans</Text>
              <Pressable
                style={[styles.clearAllButton, { backgroundColor: `${theme.colors.error}22` }]}
                onPress={() => {
                  clearAllHistory();
                }}
                hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
              >
                <Trash2 color={theme.colors.error} size={16} strokeWidth={2} />
                <Text style={[styles.clearAllText, { color: theme.colors.error }]}>Clear</Text>
              </Pressable>
            </View>
            {history.map((item, idx) => {
              const itemSignalColor =
                item.result.signal === 'BUY'
                  ? theme.colors.success
                  : item.result.signal === 'SELL'
                    ? theme.colors.error
                    : theme.colors.textMuted;
              const ItemIcon =
                item.result.signal === 'BUY' ? TrendingUp : item.result.signal === 'SELL' ? TrendingDown : Minus;
              return (
                <View
                  key={item.id}
                  style={[
                    styles.historyItem,
                    { borderColor: theme.colors.borderColor },
                    idx === history.length - 1 && { marginBottom: 0 },
                  ]}
                >
                  <TouchableOpacity
                    style={styles.historyItemTouchable}
                    onPress={() => loadHistoryItem(item)}
                    activeOpacity={0.7}
                  >
                    <Image
                      source={{
                        uri: item.imageBase64
                          ? `data:image/jpeg;base64,${item.imageBase64}`
                          : item.imageUri,
                      }}
                      style={[
                        styles.historyThumb,
                      ]}
                      resizeMode="cover"
                    />
                    <View style={styles.historyItemContent}>
                      <View style={styles.historyItemRow}>
                        <Text style={[styles.historySymbol, { color: theme.colors.textPrimary }]}>
                          {item.result.symbol || 'Chart'}
                        </Text>
                        <Text style={[styles.historyTimeframe, { color: theme.colors.textMuted }]}>
                          {item.result.timeframe || ''}
                        </Text>
                      </View>
                      <View style={styles.historyItemRow}>
                        <ItemIcon color={itemSignalColor} size={18} strokeWidth={2.5} />
                        <Text style={[styles.historySignal, { color: itemSignalColor }]}>
                          {item.result.signal === 'NEUTRAL' ? '—' : item.result.signal}
                        </Text>
                        <Text style={[styles.historyDate, { color: theme.colors.textMuted }]}>
                          {new Date(item.timestamp).toLocaleDateString()}
                        </Text>
                      </View>
                    </View>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.historyDeleteBtn, { backgroundColor: `${theme.colors.error}22` }]}
                    onPress={() => removeHistoryItem(item.id)}
                    activeOpacity={0.7}
                  >
                    <Trash2 color={theme.colors.error} size={18} strokeWidth={2} />
                  </TouchableOpacity>
                </View>
              );
            })}
          </View>
        )}

        {/* Disclaimer */}
        <Text style={[styles.disclaimer, { color: theme.colors.textMuted }]}>
          AI analysis is for educational purposes only. Not financial advice. Always do your own research.
        </Text>
      </ScrollView>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 0.3,
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
  historyHeaderBtn: {
    width: 42,
    height: 42,
    borderRadius: auraUi.radius.sm,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  contentWrapper: {
    flex: 1,
    position: 'relative',
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    padding: 20,
    paddingBottom: 40,
  },
  uploadCard: {
    borderRadius: 22,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    overflow: 'hidden',
    minHeight: 200,
    marginBottom: 0,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 18,
    elevation: 8,
  },
  uploadCardFlex: {
    flex: 1,
  },
  sectionEyebrow: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
    marginBottom: 10,
    marginTop: 6,
  },
  matrixReadableText: {
    textShadowColor: 'rgba(0, 0, 0, 0.9)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 8,
  },
  captureRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 18,
    alignItems: 'stretch',
  },
  cameraSideBtn: {
    width: 88,
    borderRadius: 22,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 16,
  },
  cameraSideText: {
    fontSize: 12,
    fontWeight: '700',
  },
  levelsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    borderWidth: 1,
    borderRadius: 16,
    overflow: 'hidden',
    marginTop: 14,
    marginBottom: 14,
  },
  levelCell: {
    width: '50%',
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRightWidth: StyleSheet.hairlineWidth,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  levelCellWide: {
    width: '100%',
    borderRightWidth: 0,
  },
  uploadGradient: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    opacity: 0.5,
  },
  uploadPlaceholder: {
    flex: 1,
    minHeight: 200,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  uploadText: {
    fontSize: 16,
    fontWeight: '700',
    marginTop: 10,
  },
  uploadHint: {
    fontSize: 13,
    marginTop: 6,
    textAlign: 'center',
  },
  previewContainer: {
    flex: 1,
    minHeight: 220,
    padding: 12,
  },
  previewImage: {
    width: '100%',
    flex: 1,
    borderRadius: 16,
  },
  clearButton: {
    alignSelf: 'center',
    marginTop: 12,
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
  },
  clearButtonText: {
    fontSize: 14,
    fontWeight: '600',
  },
  cameraButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    borderRadius: 20,
    borderWidth: 1.5,
    marginBottom: 20,
    gap: 10,
  },
  cameraButtonText: {
    fontSize: 16,
    fontWeight: '600',
  },
  uploadCountText: {
    fontSize: 12,
    fontWeight: '600',
    marginBottom: 8,
    textAlign: 'center',
  },
  limitBanner: {
    padding: 12,
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 12,
  },
  limitBannerText: {
    fontSize: 13,
    fontWeight: '600',
    textAlign: 'center',
  },
  analyzeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 15,
    paddingHorizontal: 22,
    borderRadius: 999,
    borderWidth: 1.5,
    marginBottom: 24,
    gap: 10,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.35,
    shadowRadius: 16,
    elevation: 8,
  },
  analyzeButtonDisabled: {
    opacity: 0.5,
  },
  analyzeButtonText: {
    fontSize: 15,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
  resultCard: {
    borderRadius: 26,
    borderWidth: 1,
    padding: 20,
    marginBottom: 16,
    overflow: 'hidden',
    position: 'relative',
  },
  errorCard: {
    backgroundColor: 'rgba(239, 68, 68, 0.1)',
  },
  chartMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingBottom: 12,
    marginBottom: 12,
    borderBottomWidth: 1,
  },
  chartMetaText: {
    fontSize: 14,
    fontWeight: '600',
  },
  chartMetaPrice: {
    marginLeft: 'auto',
    fontFamily: 'monospace',
  },
  errorText: {
    fontSize: 15,
    textAlign: 'center',
  },
  signalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    gap: 16,
  },
  signalLabel: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 1,
  },
  signalValue: {
    fontSize: 24,
    fontWeight: '900',
    letterSpacing: 1,
  },
  confidenceBadge: {
    marginLeft: 'auto',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  confidenceText: {
    fontSize: 12,
    fontWeight: '600',
  },
  summaryText: {
    fontSize: 16,
    fontWeight: '600',
    lineHeight: 24,
    marginBottom: 12,
  },
  reasoningLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 6,
  },
  reasoningText: {
    fontSize: 14,
    lineHeight: 22,
    marginBottom: 12,
  },
  suggestionLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 6,
  },
  suggestionText: {
    fontSize: 15,
    fontWeight: '600',
    lineHeight: 22,
  },
  tradeLevels: {
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
  },
  tradeLevelsTitle: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 12,
  },
  tradeRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 6,
  },
  tradeLabel: {
    fontSize: 13,
    fontWeight: '600',
  },
  tradeValue: {
    fontSize: 15,
    fontWeight: '800',
    fontFamily: 'monospace',
  },
  takeTradeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    marginTop: 16,
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderRadius: 16,
  },
  takeTradeButtonDisabled: {
    opacity: 0.45,
  },
  takeTradeButtonText: {
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  historySection: {
    marginTop: 24,
    marginBottom: 16,
    padding: 16,
    borderRadius: 20,
    borderWidth: 1,
  },
  historyHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    gap: 8,
  },
  historyTitle: {
    fontSize: 14,
    fontWeight: '700',
    flex: 1,
  },
  clearAllButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 12,
    gap: 6,
    minHeight: 44,
  },
  clearAllText: {
    fontSize: 12,
    fontWeight: '600',
  },
  historyItem: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 16,
    borderWidth: 1,
    marginBottom: 8,
    overflow: 'hidden',
  },
  historyItemTouchable: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
  },
  historyThumb: {
    width: 56,
    height: 56,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.05)',
  },
  historyItemContent: {
    flex: 1,
    marginLeft: 12,
  },
  historyItemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  historySymbol: {
    fontSize: 15,
    fontWeight: '700',
  },
  historyTimeframe: {
    fontSize: 12,
    fontWeight: '600',
  },
  historySignal: {
    fontSize: 14,
    fontWeight: '800',
  },
  historyDate: {
    fontSize: 11,
    marginLeft: 'auto',
  },
  historyDeleteBtn: {
    padding: 10,
    borderRadius: 12,
  },
  disclaimer: {
    fontSize: 11,
    textAlign: 'center',
    lineHeight: 18,
    marginTop: 8,
  },
});
