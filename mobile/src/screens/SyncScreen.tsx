import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { RootHeader } from '../components/RootHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { confirmLogout } from '../lib/confirmLogout';
import { formatFriendlyDateTime } from '../lib/format';
import { AppTheme } from '../theme';

export function SyncScreen({ navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const {
    assignment, bootstrapCompleted, isOnline, isSyncing, lastSyncAt,
    pendingSyncCount, releaseCheck, statusMessage, syncNow,
    unreadNotificationCount, requestConfirmation, signOut,
  } = useAppContext();
  const actionLabel = isSyncing ? i18n.t('syncing') : i18n.t('syncNow');

  return (
    <View style={styles.screen}>
      <RootHeader title={i18n.t('sync')} unreadCount={unreadNotificationCount}
        onNotificationPress={() => navigation.navigate('Notifications')}
        onLogoutPress={() => void confirmLogout({ pendingSyncCount, requestConfirmation, signOut })} />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.facts}>
          <View style={styles.fact}>
            <Text style={styles.label}>{i18n.t('syncPendingChanges')}</Text>
            <Text style={styles.value}>{pendingSyncCount}</Text>
          </View>
          <View style={styles.fact}>
            <Text style={styles.label}>{i18n.t('lastSync')}</Text>
            <Text style={styles.value}>
              {bootstrapCompleted && lastSyncAt
                ? formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt
                : i18n.t('bootstrapPending')}
            </Text>
          </View>
          <View style={styles.fact}>
            <Text style={styles.label}>{i18n.t('syncConnection')}</Text>
            <Text style={styles.value}>{isOnline ? i18n.t('online') : i18n.t('offline')}</Text>
          </View>
          <View style={styles.fact}>
            <Text style={styles.label}>{i18n.t('assignment')}</Text>
            <Text style={styles.value}>
              {assignment?.barangay?.name ? `${assignment.barangay.name} · ` : ''}
              {assignment?.purok?.display_name ?? 'Unassigned'}
            </Text>
          </View>
        </View>

        <Pressable accessibilityRole="button" accessibilityLabel={actionLabel}
          accessibilityState={{ disabled: isSyncing, busy: isSyncing }}
          disabled={isSyncing} onPress={syncNow}
          style={[styles.syncAction, isSyncing && styles.syncActionDisabled]}>
          <Text style={styles.syncActionLabel}>{actionLabel}</Text>
        </Pressable>

        {statusMessage ? (
          <View style={styles.alert}>
            <Text accessibilityLiveRegion="polite" style={styles.value}>{statusMessage}</Text>
          </View>
        ) : null}

        {releaseCheck?.update.available ? (
          <View style={styles.alert}>
            <Text accessibilityRole="header" style={styles.alertTitle}>
              {releaseCheck.update.required ? i18n.t('updateRequiredTitle') : i18n.t('updateAvailableTitle')}
            </Text>
            <Text style={styles.value}>{releaseCheck.update.message ?? i18n.t('updateAvailableBody')}</Text>
          </View>
        ) : null}

        {releaseCheck?.maintenance.maintenance_message ? (
          <View style={styles.alert}>
            <Text accessibilityRole="header" style={styles.alertTitle}>{i18n.t('mobileMaintenanceTitle')}</Text>
            <Text style={styles.value}>{releaseCheck.maintenance.maintenance_message}</Text>
          </View>
        ) : null}

        <View style={styles.fact}>
          <Text style={styles.policyTitle}>{i18n.t('dataProtectionTitle')}</Text>
          <Text style={styles.policyBody}>{i18n.t('dataProtectionBody')}</Text>
        </View>
        <View style={styles.fact}>
          <Text style={styles.policyTitle}>{i18n.t('devicePolicyTitle')}</Text>
          <Text style={styles.policyBody}>{i18n.t('devicePolicyBody')}</Text>
        </View>
      </ScrollView>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: 16, paddingBottom: 32, gap: 24 },
  facts: { gap: 18 },
  fact: { gap: 4 },
  label: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 20, fontWeight: '400' },
  value: { color: theme.colors.text, fontSize: 16, lineHeight: 24 },
  syncAction: { backgroundColor: theme.colors.primary, borderRadius: 6, minHeight: 50,
    paddingHorizontal: 16, paddingVertical: 12, alignItems: 'center', justifyContent: 'center' },
  syncActionDisabled: { opacity: 0.6 },
  syncActionLabel: { color: theme.colors.textOnPrimary, fontSize: 16, lineHeight: 24, fontWeight: '600', textAlign: 'center' },
  alert: { backgroundColor: theme.colors.infoSoft, borderColor: theme.colors.infoBorder,
    borderWidth: 1, borderRadius: 6, padding: 16, gap: 6 },
  alertTitle: { color: theme.colors.text, fontSize: 16, lineHeight: 24, fontWeight: '600' },
  policyTitle: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 20, fontWeight: '600' },
  policyBody: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 20 },
});
