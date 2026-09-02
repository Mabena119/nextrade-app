import { Tabs } from "expo-router";
import { Home, Wallet, Scan } from "lucide-react-native";
import React from "react";
import { useApp } from "@/providers/app-provider";
import { getScreenBackgroundColor, useTheme } from "@/providers/theme-provider";
import { Platform, View, StyleSheet, useWindowDimensions } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { isDesktopWebLayout } from "@/utils/app-viewport";

const NATIVE_TAB_BAR_HEIGHT = 56;

export default function TabLayout() {
  const { isFirstTime } = useApp();
  const { theme } = useTheme();
  const accent = theme.colors.accent;
  const muted = theme.colors.navInactiveColor;
  const sceneBg = getScreenBackgroundColor(theme);
  const insets = useSafeAreaInsets();

  const { width } = useWindowDimensions();
  const desktop = isDesktopWebLayout(width);
  const nativeTabBarBg =
    sceneBg === "transparent" || !sceneBg ? "#000000" : sceneBg;

  return (
    <View style={[tabScreenStyles.layoutRoot, { backgroundColor: sceneBg }]}>
      <View style={tabScreenStyles.tabSceneSlotDefault}>
        <Tabs
          style={[tabScreenStyles.tabsFill, { backgroundColor: sceneBg }]}
          screenOptions={{
            headerShown: false,
            sceneContainerStyle: {
              backgroundColor: sceneBg,
            },
            tabBarShowLabel: false,
            tabBarStyle: isFirstTime
              ? { display: "none" }
              : desktop
                ? {
                    position: "absolute",
                    bottom: 18,
                    left: Math.max(24, (width - 420) / 2),
                    right: Math.max(24, (width - 420) / 2),
                    height: 56,
                    borderRadius: 999,
                    backgroundColor: "rgba(12, 12, 14, 0.92)",
                    borderTopWidth: 0,
                    elevation: 0,
                    shadowOpacity: 0,
                    paddingBottom: 8,
                    paddingTop: 8,
                  }
                : Platform.OS === "web"
                  ? {
                      position: "absolute",
                      bottom: 10,
                      left: 0,
                      right: 0,
                      height: 56,
                      backgroundColor: "transparent",
                      borderTopWidth: 0,
                      elevation: 0,
                      shadowOpacity: 0,
                      paddingBottom: 8,
                      paddingTop: 8,
                    }
                  : {
                      height: NATIVE_TAB_BAR_HEIGHT + insets.bottom,
                      paddingBottom: Math.max(insets.bottom, 8),
                      paddingTop: 8,
                      backgroundColor: nativeTabBarBg,
                      borderTopWidth: 1,
                      borderTopColor: "rgba(255, 255, 255, 0.08)",
                      elevation: 0,
                      shadowOpacity: 0,
                    },
            tabBarBackground: () => null,
            tabBarActiveTintColor: accent,
            tabBarInactiveTintColor: muted,
          }}
        >
          <Tabs.Screen
            name="index"
            options={{
              title: "Home",
              tabBarIcon: ({ focused }) => (
                <Home
                  color={focused ? accent : muted}
                  size={22}
                  strokeWidth={focused ? 2.2 : 1.5}
                />
              ),
            }}
          />
          <Tabs.Screen
            name="metatrader"
            options={{
              title: "Trade",
              tabBarIcon: ({ focused }) => (
                <Wallet
                  color={focused ? accent : muted}
                  size={22}
                  strokeWidth={focused ? 2.2 : 1.5}
                />
              ),
            }}
          />
          <Tabs.Screen
            name="ai-scanner"
            options={{
              title: "Scanner",
              tabBarIcon: ({ focused }) => (
                <Scan
                  color={focused ? accent : muted}
                  size={22}
                  strokeWidth={focused ? 2.2 : 1.5}
                />
              ),
            }}
          />
          <Tabs.Screen
            name="quotes"
            options={{
              href: null,
            }}
          />
        </Tabs>
      </View>
    </View>
  );
}

const tabScreenStyles = StyleSheet.create({
  layoutRoot: { flex: 1 },
  tabSceneSlotDefault: { flex: 1 },
  tabsFill: { flex: 1 },
});
