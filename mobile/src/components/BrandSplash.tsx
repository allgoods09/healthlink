import React from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';

import { theme } from '../theme';
import { BrandMark } from './BrandMark';

type BrandSplashProps = {
  loadingLabel?: string;
  showSpinner?: boolean;
};

export function BrandSplash({
  loadingLabel,
  showSpinner = true,
}: BrandSplashProps) {
  return (
    <View style={styles.screen}>
      <StatusBar style="light" />

      <View style={styles.content}>
        <BrandMark />

        {showSpinner ? (
          <ActivityIndicator
            size="small"
            color="rgba(255, 255, 255, 0.92)"
            style={styles.spinner}
          />
        ) : null}

        {loadingLabel ? <Text style={styles.label}>{loadingLabel}</Text> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    padding: theme.spacing.xl,
  },
  content: {
    alignItems: 'center',
  },
  spinner: {
    marginTop: 42,
  },
  label: {
    marginTop: 16,
    color: 'rgba(255, 255, 255, 0.8)',
    fontSize: 15,
    letterSpacing: 0.3,
  },
});
