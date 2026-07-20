import React from 'react';
import { StatusBar } from 'expo-status-bar';
import {
  ActivityIndicator,
  ImageBackground,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { theme } from '../theme';
import { authBackgroundImage, BrandMark } from '../components/BrandMark';

export function InitialSyncScreen() {
  const {
    initialSyncInProgress,
    isOnline,
    retryInitialSync,
    signOut,
    statusMessage,
  } = useAppContext();

  return (
    <ImageBackground
      source={authBackgroundImage}
      style={styles.screen}
      imageStyle={styles.backgroundImage}
    >
      <StatusBar style="light" />
      <View style={styles.overlay} />

      <ScrollView contentContainerStyle={styles.scroll}>
        <View style={styles.brandWrap}>
          <BrandMark logoSize={110} titleSize={32} subtitleSize={26} />
        </View>

        <View style={styles.card}>
          <View style={styles.heroBadge}>
            <Text style={styles.heroBadgeText}>{i18n.t('appTitle')}</Text>
          </View>

          <Text style={styles.title}>{i18n.t('initialSyncTitle')}</Text>
          <Text style={styles.body}>{i18n.t('initialSyncBody')}</Text>

          <View style={styles.statusCard}>
            {initialSyncInProgress ? (
              <ActivityIndicator size="large" color={theme.colors.primary} />
            ) : null}
            <Text style={styles.statusTitle}>
              {initialSyncInProgress
                ? i18n.t('initialSyncing')
                : i18n.t('bootstrapPending')}
            </Text>
            <Text style={styles.statusBody}>
              {statusMessage ?? i18n.t('initialSyncPendingMessage')}
            </Text>
            <Text style={styles.connectionText}>
              {isOnline ? i18n.t('online') : i18n.t('offline')}
            </Text>
          </View>

          {!initialSyncInProgress ? (
            <View style={styles.actions}>
              <Pressable onPress={retryInitialSync} style={styles.primaryButton}>
                <Text style={styles.primaryButtonText}>{i18n.t('retry')}</Text>
              </Pressable>

              <Pressable onPress={signOut} style={styles.secondaryButton}>
                <Text style={styles.secondaryButtonText}>{i18n.t('logout')}</Text>
              </Pressable>
            </View>
          ) : null}
        </View>
      </ScrollView>
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.primary,
  },
  backgroundImage: {
    resizeMode: 'cover',
  },
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(11, 84, 165, 0.62)',
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: 44,
  },
  brandWrap: {
    alignItems: 'center',
    marginBottom: theme.spacing.xl,
  },
  card: {
    backgroundColor: 'rgba(255, 255, 255, 0.96)',
    borderRadius: 28,
    borderWidth: 1,
    borderColor: 'rgba(23, 76, 142, 0.12)',
    padding: theme.spacing.lg,
    shadowColor: '#0A366A',
    shadowOpacity: 0.16,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 10 },
    elevation: 4,
  },
  heroBadge: {
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.primarySoft,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginBottom: theme.spacing.md,
  },
  heroBadgeText: {
    color: theme.colors.primary,
    fontWeight: '700',
    fontSize: 12,
  },
  title: {
    color: theme.colors.text,
    fontSize: 28,
    fontWeight: '700',
  },
  body: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    marginTop: 10,
  },
  statusCard: {
    marginTop: theme.spacing.lg,
    backgroundColor: 'rgba(219, 232, 250, 0.92)',
    borderRadius: 22,
    padding: theme.spacing.lg,
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  statusTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: '700',
    textAlign: 'center',
  },
  statusBody: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    textAlign: 'center',
  },
  connectionText: {
    color: theme.colors.primary,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  actions: {
    gap: theme.spacing.sm,
    marginTop: theme.spacing.lg,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: 18,
    alignItems: 'center',
    paddingVertical: 18,
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 16,
  },
  secondaryButton: {
    backgroundColor: theme.colors.surface,
    borderRadius: 18,
    alignItems: 'center',
    paddingVertical: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
    fontSize: 16,
  },
});
