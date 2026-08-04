import React from 'react';
import { ActivityIndicator, StatusBar, StyleSheet, Text, View } from 'react-native';

import { useAppTheme, useThemedStyles } from '../context/AppContext';
import { AppTheme } from '../theme';
import { BrandMark } from './BrandMark';

type BrandSplashProps = {
  loadingLabel?: string;
  showSpinner?: boolean;
};

export function BrandSplash({
  loadingLabel,
  showSpinner = true,
}: BrandSplashProps) {
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  return (
    <View style={styles.screen}>
      <StatusBar
        animated
        backgroundColor={theme.colors.brandBackground}
        barStyle="light-content"
      />

      <View style={styles.content}>
        <BrandMark />

        {showSpinner ? (
          <ActivityIndicator
            size="small"
            color={theme.colors.textOnBrand}
            style={styles.spinner}
          />
        ) : null}

        {loadingLabel ? <Text style={styles.label}>{loadingLabel}</Text> : null}
      </View>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.brandBackground,
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
    color: theme.colors.heroTextMuted,
    fontSize: 15,
    letterSpacing: 0.3,
  },
});
