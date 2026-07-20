import React from 'react';
import {
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { MenuCard } from '../components/MenuCard';
import { TopHeader } from '../components/TopHeader';
import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { theme } from '../theme';

export function SyncScreen() {
  const {
    assignment,
    bootstrapCompleted,
    isOnline,
    isSyncing,
    lastSyncAt,
    pendingSyncCount,
    releaseCheck,
    statusMessage,
    syncNow,
  } = useAppContext();

  return (
    <View style={styles.screen}>
      <TopHeader title={i18n.t('sync')} />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Text style={styles.heroKicker}>{i18n.t('currentStatus')}</Text>
          <Text style={styles.heroTitle}>{i18n.t('syncWorkspaceTitle')}</Text>
          <Text style={styles.heroBody}>{i18n.t('syncWorkspaceBody')}</Text>
          <View style={styles.heroMetaRow}>
            <View style={styles.heroMetaCard}>
              <Text style={styles.heroMetaLabel}>{i18n.t('pendingUploads')}</Text>
              <Text style={styles.heroMetaValue}>{pendingSyncCount}</Text>
            </View>
            <View style={styles.heroMetaCard}>
              <Text style={styles.heroMetaLabel}>{i18n.t('assignment')}</Text>
              <Text style={styles.heroMetaValueSmall}>
                {assignment?.purok?.display_name ?? 'Unassigned'}
              </Text>
            </View>
          </View>
        </View>

        <MenuCard
          title={isSyncing ? i18n.t('syncing') : i18n.t('syncNow')}
          subtitle={statusMessage ?? i18n.t('syncWorkspaceBody')}
          icon="cloud-upload-outline"
          onPress={syncNow}
          tone="primary"
          badge={isOnline ? i18n.t('online') : i18n.t('offline')}
        />

        {releaseCheck?.update.available ? (
          <MenuCard
            title={releaseCheck.update.required ? i18n.t('updateRequiredTitle') : i18n.t('updateAvailableTitle')}
            subtitle={releaseCheck.update.message ?? i18n.t('updateAvailableBody')}
            icon="cloud-download-outline"
          />
        ) : null}

        {releaseCheck?.maintenance.maintenance_message ? (
          <MenuCard
            title={i18n.t('mobileMaintenanceTitle')}
            subtitle={releaseCheck.maintenance.maintenance_message}
            icon="build-outline"
          />
        ) : null}

        <MenuCard
          title={i18n.t('dataProtectionTitle')}
          subtitle={i18n.t('dataProtectionBody')}
          icon="shield-checkmark-outline"
        />

        <MenuCard
          title={i18n.t('devicePolicyTitle')}
          subtitle={i18n.t('devicePolicyBody')}
          icon="phone-portrait-outline"
        />

        <View style={styles.infoCard}>
          <Text style={styles.infoTitle}>{i18n.t('lastSync')}</Text>
          <Text style={styles.infoValue}>
            {bootstrapCompleted && lastSyncAt
              ? formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt
              : i18n.t('bootstrapPending')}
          </Text>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
  },
  hero: {
    backgroundColor: theme.colors.primary,
    borderRadius: 28,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
  },
  heroKicker: {
    color: '#D9E7FA',
    fontSize: 12,
    letterSpacing: 1.1,
    textTransform: 'uppercase',
    fontWeight: '700',
  },
  heroTitle: {
    color: '#FFFFFF',
    fontSize: 26,
    fontWeight: '700',
    marginTop: 10,
  },
  heroBody: {
    color: '#D9E7FA',
    lineHeight: 21,
    marginTop: 10,
  },
  heroMetaRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  heroMetaCard: {
    flex: 1,
    borderRadius: theme.radius.lg,
    backgroundColor: 'rgba(255,255,255,0.12)',
    padding: theme.spacing.md,
  },
  heroMetaLabel: {
    color: '#D9E7FA',
    fontSize: 12,
    fontWeight: '600',
  },
  heroMetaValue: {
    color: '#FFFFFF',
    fontSize: 28,
    fontWeight: '700',
    marginTop: 8,
  },
  heroMetaValueSmall: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
    marginTop: 8,
    lineHeight: 20,
  },
  infoCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  infoTitle: {
    color: theme.colors.textMuted,
    fontWeight: '600',
    marginBottom: 8,
  },
  infoValue: {
    color: theme.colors.text,
    lineHeight: 22,
  },
});
