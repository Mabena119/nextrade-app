import { Stack, router } from "expo-router";
import * as SplashScreen from "expo-splash-screen";
import {
  useFonts,
  Outfit_400Regular,
  Outfit_500Medium,
  Outfit_600SemiBold,
  Outfit_700Bold,
} from "@expo-google-fonts/outfit";
import React, { useEffect, useState, useRef, Component, ReactNode } from "react";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { apiService } from "@/services/api";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { StatusBar } from "expo-status-bar";
import { AppProvider, useApp } from "@/providers/app-provider";
import { ThemeProvider, useTheme } from "@/providers/theme-provider";
import { View, Platform, Text, TouchableOpacity, StyleSheet, AppState, Linking } from "react-native";
import { LinearGradient } from "expo-linear-gradient";
import * as SystemUI from "expo-system-ui";
import * as Notifications from "expo-notifications";
import { RobotLogo } from "@/components/robot-logo";
import { MT5SignalWebView } from "@/components/mt5-signal-webview";
import colors from "@/constants/colors";
import { isIOSPWA } from "@/utils/pwa-detection";
import { captureAffiliateRefFromUrl, syncStoredAffiliateAttribution } from "@/utils/affiliate-ref";

// Configure notifications to show when app is in background
if (Platform.OS !== 'web') {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldPlaySound: true,
      shouldSetBadge: true,
      shouldShowBanner: true,
      shouldShowList: true,
    }),
  });
}

// Early console suppression - must be at the very top
if (typeof window !== 'undefined' && Platform.OS === 'web') {
  const originalWarn = console.warn;
  const originalError = console.error;
  const originalLog = console.log;

  function shouldSuppress(message) {
    return message.includes('interactive-widget') ||
      message.includes('viewport') ||
      message.includes('Viewport argument key') ||
      message.includes('AES-CBC') ||
      message.includes('AES-CTR') ||
      message.includes('AES-GCM') ||
      message.includes('chosen-ciphertext') ||
      message.includes('authentication by default') ||
      message.includes('not recognized and ignored') ||
      message.includes('We recommended using authenticated encryption') ||
      message.includes('implementing it manually can result in minor') ||
      message.includes('serious mistakes') ||
      message.includes('protect against chosen-ciphertext attacks') ||
      message.includes('do not provide authentication by default') ||
      message.includes('can result in minor, but serious mistakes') ||
      message.includes('We recommended using') ||
      message.includes('authenticated encryption like AES-GCM');
  }

  console.warn = function (...args) {
    const message = args.join(' ');
    if (shouldSuppress(message)) return;
    originalWarn.apply(console, args);
  };

  console.error = function (...args) {
    const message = args.join(' ');
    if (shouldSuppress(message)) return;
    originalError.apply(console, args);
  };

  console.log = function (...args) {
    const message = args.join(' ');
    if (shouldSuppress(message)) return;
    originalLog.apply(console, args);
  };
}

// Error Boundary Component
class ErrorBoundary extends Component<
  { children: ReactNode },
  { hasError: boolean; error?: Error }
> {
  constructor(props: { children: ReactNode }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error) {
    console.error('ErrorBoundary caught an error:', error);
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: any) {
    console.error('ErrorBoundary componentDidCatch:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <View style={errorStyles.container}>
          <RobotLogo size={80} />
          <Text style={errorStyles.title}>Something went wrong</Text>
          <Text style={errorStyles.message}>
            The app encountered an error. Please restart the app.
          </Text>
          <TouchableOpacity
            style={errorStyles.button}
            onPress={() => this.setState({ hasError: false, error: undefined })}
          >
            <Text style={errorStyles.buttonText}>Try Again</Text>
          </TouchableOpacity>
        </View>
      );
    }

    return this.props.children;
  }
}

const errorStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 24,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 20,
    marginBottom: 12,
  },
  message: {
    fontSize: 16,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 40,
  },
  button: {
    backgroundColor: colors.error,
    paddingHorizontal: 32,
    paddingVertical: 12,
    borderRadius: 16,
  },
  buttonText: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '600',
  },
});

function RootLayoutNav() {
  const {
    isFirstTime,
    eas,
    isBotActive,
    showMT5SignalWebView,
    mt5Signal,
    setShowMT5SignalWebView,
  } = useApp();
  const [appState, setAppState] = useState<string>(AppState.currentState);

  // Session revalidation: on every app open/foreground, confirm the stored email still
  // exists (and is paid) in the database — same /api/check-email used by the login page.
  // If the account was removed or unpaid, clear the session and send the user back to login.
  const revalidatingSessionRef = useRef<boolean>(false);
  useEffect(() => {
    const revalidateStoredEmail = async () => {
      if (revalidatingSessionRef.current) return;
      revalidatingSessionRef.current = true;
      try {
        const [emailAuthenticated, userRaw] = await Promise.all([
          AsyncStorage.getItem('emailAuthenticated'),
          AsyncStorage.getItem('user'),
        ]);
        if (emailAuthenticated !== 'true' || !userRaw) return;
        let email = '';
        try {
          const parsed = JSON.parse(userRaw);
          email = String(parsed?.email || '').trim();
        } catch {
          return;
        }
        if (!email || !email.includes('@')) return;

        const account = await apiService.authenticate({ email, mentor: '' });
        if (account.degraded) {
          console.log('Session revalidation skipped (server unavailable)');
          return;
        }
        if (account.status === 'not_found' || !account.paid) {
          console.log('Session email no longer valid in database - logging out:', email);
          await AsyncStorage.multiRemove(['emailAuthenticated', 'user']);
          router.replace('/login');
        }
      } catch (error) {
        // Network/server errors: keep the session (only a definitive "not found / not paid"
        // response from the API kicks the user out).
        console.log('Session revalidation skipped (network error):', error);
      } finally {
        revalidatingSessionRef.current = false;
      }
    };

    void revalidateStoredEmail();

    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void revalidateStoredEmail();
    });
    return () => subscription.remove();
  }, []);

  // Persist affiliate ref from auraai-vps.com links (shared before app install / checkout).
  useEffect(() => {
    const handleAffiliateUrl = (url: string | null | undefined) => {
      if (!url) return;
      captureAffiliateRefFromUrl(url).then((ref) => {
        if (ref) console.log('Affiliate ref captured:', ref);
      });
    };

    Linking.getInitialURL()
      .then(handleAffiliateUrl)
      .catch(() => {});

    const subscription = Linking.addEventListener('url', (event) => {
      handleAffiliateUrl(event.url);
    });

    return () => subscription.remove();
  }, []);

  // Keep IP + visitor attribution fresh on iOS, Android, and web.
  useEffect(() => {
    void syncStoredAffiliateAttribution();
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        void syncStoredAffiliateAttribution();
      }
    });
    return () => subscription.remove();
  }, []);
  
  // Trigger native widget creation when bot becomes active on iOS PWA
  useEffect(() => {
    if (Platform.OS === 'web' && isIOSPWA() && !isFirstTime && eas.length > 0 && isBotActive) {
      const triggerNativeWidget = async () => {
        try {
          const primaryEA = eas[0];
          const botName = primaryEA?.name || 'NexTradeAI';
          
          // Get bot image URL
          let botImageURL: string | null = null;
          if (primaryEA?.userData?.owner?.logo) {
            const raw = primaryEA.userData.owner.logo.toString().trim();
            if (raw) {
              if (/^https?:\/\//i.test(raw)) {
                botImageURL = raw;
              } else {
                const filename = raw.replace(/^\/+/, '');
                botImageURL = `https://auraai-vps.com/admin/uploads/${filename}`;
              }
            }
          }
          
          // Trigger native app to create widgets
          const { widgetService } = await import('@/services/widget-service');
          await widgetService.updateWidget(botName, isBotActive, false, botImageURL);
          console.log('Triggered native widget creation from iOS PWA');
        } catch (error) {
          console.error('Error triggering native widget from PWA:', error);
        }
      };
      
      triggerNativeWidget();
    }
  }, [isBotActive, isFirstTime, eas, Platform.OS]);


  // Register service worker for Web Push (iOS PWA background notifications)
  useEffect(() => {
    if (Platform.OS === 'web' && isIOSPWA()) {
      import('@/services/pwa-push-service').then(({ registerServiceWorker }) => {
        registerServiceWorker().then((reg) => {
          if (reg) console.log('[PWA Push] Service worker registered');
        });
      });
    }
  }, [Platform.OS]);

  // Request notification permission for iOS PWA on app load
  useEffect(() => {
    if (Platform.OS === 'web' && isIOSPWA()) {
      const requestNotificationPermission = async () => {
        try {
          const { pwaNotificationService } = await import('@/services/pwa-notification-service');
          const hasPermission = pwaNotificationService.hasPermission();
          
          if (!hasPermission) {
            console.log('[Notifications] Requesting notification permission...');
            // Note: requestPermission() must be called in response to user gesture
            // We'll request it when user first activates the bot instead
            // For now, just log that we'll request it later
          } else {
            console.log('[Notifications] ✅ Permission already granted');
          }
        } catch (error) {
          console.error('[Notifications] Error checking notification permission:', error);
        }
      };
      
      requestNotificationPermission();
    }
  }, [Platform.OS]);

  // Handle app state changes for overlay persistence
  useEffect(() => {
    const handleAppStateChange = (nextAppState: string) => {
      console.log('Root Layout: App state changed from', appState, 'to', nextAppState);
      setAppState(nextAppState);
    };

    const subscription = AppState.addEventListener('change', handleAppStateChange);
    return () => subscription?.remove();
  }, [appState]);

  // Handle deep links from PWA for widget updates (iOS only)
  useEffect(() => {
    if (Platform.OS !== 'ios') return;

    const handleDeepLink = async (url: string) => {
      try {
        console.log('Received deep link:', url);
        
        // Parse URL manually (works on both web and native)
        // Format: myapp://widget?action=updateWidget&botName=...&isActive=true&...
        if (!url.includes('widget')) return;
        
        // Extract query parameters
        const urlParts = url.split('?');
        if (urlParts.length < 2) return;
        
        const queryString = urlParts[1];
        const params = new Map<string, string>();
        queryString.split('&').forEach(param => {
          const [key, value] = param.split('=');
          if (key && value) {
            params.set(key, decodeURIComponent(value));
          }
        });
        
        const action = params.get('action');
        if (action === 'updateWidget') {
          let botName = params.get('botName') || '';
          let isActive = params.get('isActive') === 'true';
          let isPaused = params.get('isPaused') === 'true';
          let botImageURL = params.get('botImageURL') || null;

          // If botName is missing, try to get it from app state
          if (!botName && eas.length > 0) {
            const primaryEA = eas[0];
            botName = primaryEA?.name || 'NexTradeAI';
            
            // Get bot image URL from EA data
            if (!botImageURL && primaryEA?.userData?.owner?.logo) {
              const raw = primaryEA.userData.owner.logo.toString().trim();
              if (raw) {
                if (/^https?:\/\//i.test(raw)) {
                  botImageURL = raw;
                } else {
                  const filename = raw.replace(/^\/+/, '');
                  botImageURL = `https://auraai-vps.com/admin/uploads/${filename}`;
                }
              }
            }
            
            // Use current bot active state if not provided
            if (params.get('isActive') === null) {
              isActive = isBotActive;
            }
          }

          console.log('Received widget update from PWA:', { botName, isActive, isPaused, botImageURL });

          // Update widget via native module
          const { widgetService } = await import('@/services/widget-service');
          await widgetService.updateWidget(botName, isActive, isPaused, botImageURL);
          console.log('Widget updated successfully from deep link');
        }
      } catch (error) {
        console.error('Error handling deep link for widget update:', error);
      }
    };

    // Handle initial URL (if app was opened via deep link)
    Linking.getInitialURL().then((url) => {
      if (url) {
        console.log('App opened with initial URL:', url);
        handleDeepLink(url);
      }
    }).catch(err => {
      console.error('Error getting initial URL:', err);
    });

    // Listen for deep links while app is running
    const subscription = Linking.addEventListener('url', (event) => {
      console.log('Deep link received while app running:', event.url);
      handleDeepLink(event.url);
    });

    return () => subscription.remove();
  }, [eas, isBotActive]);

  const { theme } = useTheme();

  // Native window / edge area — never flash system white on dark themes
  useEffect(() => {
    if (Platform.OS === "web") return;
    const run = async () => {
      try {
        await SystemUI.setBackgroundColorAsync("#000000");
      } catch {
        // ignore
      }
    };
    void run();
  }, [theme.colors.background]);

  return (
    <View style={{ flex: 1, backgroundColor: "#000000" }}>
      <LinearGradient
        colors={[theme.colors.background, theme.colors.backgroundSecondary, theme.colors.background]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={{ position: "absolute", left: 0, right: 0, top: 0, bottom: 0 }}
      />
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: {
            backgroundColor: theme.colors.background,
          },
        }}
      >
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="login" />
        <Stack.Screen name="license" />
        <Stack.Screen name="trade-config" options={{ presentation: "modal" }} />
        <Stack.Screen name="ai-payment" options={{ presentation: "modal" }} />
      </Stack>

      <MT5SignalWebView
        visible={showMT5SignalWebView}
        signal={mt5Signal}
        onClose={() => {
          setShowMT5SignalWebView(false);
        }}
      />
    </View>
  );
}

function ThemedAppShell({ children }: { children: ReactNode }) {
  const rootSurface = "#000000";

  useEffect(() => {
    if (Platform.OS !== "web" || typeof document === "undefined") return;
    const prevBody = document.body.style.backgroundColor;
    const prevHtml = document.documentElement.style.backgroundColor;
    document.body.style.backgroundColor = "#000000";
    document.documentElement.style.backgroundColor = "#000000";
    const root = document.getElementById("root");
    const prevRoot = root?.style.backgroundColor;
    if (root) root.style.backgroundColor = "#000000";
    return () => {
      document.body.style.backgroundColor = prevBody;
      document.documentElement.style.backgroundColor = prevHtml;
      if (root && prevRoot != null) root.style.backgroundColor = prevRoot;
    };
  }, []);

  return (
    <GestureHandlerRootView style={{ flex: 1, backgroundColor: rootSurface }}>
      <StatusBar
        style="light"
        backgroundColor="#000000"
        translucent={Platform.OS === "android"}
      />
      {children}
    </GestureHandlerRootView>
  );
}

export default function RootLayout() {
  const [appIsReady, setAppIsReady] = useState<boolean>(false);
  const [fontsLoaded] = useFonts({
    Outfit_400Regular,
    Outfit_500Medium,
    Outfit_600SemiBold,
    Outfit_700Bold,
  });

  useEffect(() => {
    // Set up comprehensive console warning filter for external warnings
    if (Platform.OS === 'web') {
      const originalWarn = console.warn;
      const originalError = console.error;
      const originalLog = console.log;

      // Filter console.warn
      console.warn = (...args) => {
        const message = args.join(' ');
        // Suppress warnings from external terminals and dependencies
        if (message.includes('interactive-widget') ||
          message.includes('viewport') ||
          message.includes('Viewport argument key') ||
          message.includes('AES-CBC') ||
          message.includes('AES-CTR') ||
          message.includes('AES-GCM') ||
          message.includes('chosen-ciphertext') ||
          message.includes('authentication by default') ||
          message.includes('not recognized and ignored') ||
          message.includes('We recommended using authenticated encryption') ||
          message.includes('implementing it manually can result in minor') ||
          message.includes('serious mistakes') ||
          message.includes('protect against chosen-ciphertext attacks') ||
          message.includes('do not provide authentication by default') ||
          message.includes('can result in minor, but serious mistakes') ||
          message.includes('We recommended using') ||
          message.includes('authenticated encryption like AES-GCM')) {
          return;
        }
        originalWarn.apply(console, args);
      };

      // Filter console.error for the same warnings
      console.error = (...args) => {
        const message = args.join(' ');
        // Suppress error messages from external terminals and dependencies
        if (message.includes('interactive-widget') ||
          message.includes('viewport') ||
          message.includes('Viewport argument key') ||
          message.includes('AES-CBC') ||
          message.includes('AES-CTR') ||
          message.includes('AES-GCM') ||
          message.includes('chosen-ciphertext') ||
          message.includes('authentication by default') ||
          message.includes('not recognized and ignored') ||
          message.includes('We recommended using authenticated encryption') ||
          message.includes('implementing it manually can result in minor') ||
          message.includes('serious mistakes') ||
          message.includes('protect against chosen-ciphertext attacks') ||
          message.includes('do not provide authentication by default') ||
          message.includes('can result in minor, but serious mistakes') ||
          message.includes('We recommended using') ||
          message.includes('authenticated encryption like AES-GCM')) {
          return;
        }
        originalError.apply(console, args);
      };

      // Filter console.log for terminal warnings
      console.log = (...args) => {
        const message = args.join(' ');
        // Suppress log messages from external terminals and dependencies
        if (message.includes('interactive-widget') ||
          message.includes('viewport') ||
          message.includes('Viewport argument key') ||
          message.includes('AES-CBC') ||
          message.includes('AES-CTR') ||
          message.includes('AES-GCM') ||
          message.includes('chosen-ciphertext') ||
          message.includes('authentication by default') ||
          message.includes('not recognized and ignored') ||
          message.includes('We recommended using authenticated encryption') ||
          message.includes('implementing it manually can result in minor') ||
          message.includes('serious mistakes') ||
          message.includes('protect against chosen-ciphertext attacks') ||
          message.includes('do not provide authentication by default') ||
          message.includes('can result in minor, but serious mistakes') ||
          message.includes('We recommended using') ||
          message.includes('authenticated encryption like AES-GCM')) {
          return;
        }
        originalLog.apply(console, args);
      };
    }

    async function prepare() {
      try {
        // Keep the splash screen visible while we fetch resources
        await SplashScreen.preventAutoHideAsync();

        // Pre-load any resources or data here if needed
        await new Promise(resolve => setTimeout(resolve, 200));

      } catch (e) {
        console.warn('Error during app preparation:', e);
      } finally {
        // Tell the application to render
        setAppIsReady(true);
        try {
          await SplashScreen.hideAsync();
        } catch (hideError) {
          console.warn('Error hiding splash screen:', hideError);
        }
      }
    }

    prepare();
  }, []);

  if (!appIsReady || !fontsLoaded) {
    return <View style={{ flex: 1, backgroundColor: colors.background }} />;
  }

  return (
    <ErrorBoundary>
      <ThemeProvider>
        <AppProvider>
          <ThemedAppShell>
            <RootLayoutNav />
          </ThemedAppShell>
        </AppProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}