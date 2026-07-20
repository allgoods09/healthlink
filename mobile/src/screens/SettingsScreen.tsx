import React from 'react';
import {
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { theme } from '../theme';

export function SettingsScreen() {
  const {
    assignment,
    bootstrapCompleted,
    isOnline,
    isSyncing,
    language,
    lastSyncAt,
    pendingSyncCount,
    setLanguagePreference,
    signOut,
    statusMessage,
    syncNow,
    user,
  } = useAppContext();
  const [showLogoutWarning, setShowLogoutWarning] = React.useState(false);

  async function handleLogout() {
    if (pendingSyncCount > 0) {
      setShowLogoutWarning(true);
      return;
    }

    await signOut();
  }

  return (
    <View style={styles.screen}>
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{user?.name ?? i18n.t('settings')}</Text>
        <Text style={styles.sectionText}>{user?.email}</Text>
        <Text style={styles.sectionText}>
          {i18n.t('assignment')}: {assignment?.purok?.display_name ?? 'Unassigned'}
        </Text>
        <Text style={styles.sectionText}>
          {isOnline ? i18n.t('online') : i18n.t('offline')}
        </Text>
        <Text style={styles.sectionText}>
          {bootstrapCompleted
            ? `${i18n.t('lastSync')}: ${lastSyncAt ? formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt : 'N/A'}`
            : i18n.t('bootstrapPending')}
        </Text>
        <Text style={styles.sectionText}>
          {i18n.t('pendingUploads')}: {pendingSyncCount}
        </Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{i18n.t('language')}</Text>
        <View style={styles.segmentRow}>
          <Pressable
            onPress={() => setLanguagePreference('en')}
            style={[styles.segment, language === 'en' && styles.segmentActive]}
          >
            <Text style={[styles.segmentText, language === 'en' && styles.segmentTextActive]}>
              {i18n.t('english')}
            </Text>
          </Pressable>
          <Pressable
            onPress={() => setLanguagePreference('ceb')}
            style={[styles.segment, language === 'ceb' && styles.segmentActive]}
          >
            <Text style={[styles.segmentText, language === 'ceb' && styles.segmentTextActive]}>
              {i18n.t('cebuano')}
            </Text>
          </Pressable>
        </View>
      </View>

      {statusMessage ? (
        <View style={styles.card}>
          <Text style={styles.sectionText}>{statusMessage}</Text>
        </View>
      ) : null}

      <Pressable onPress={syncNow} style={styles.primaryButton}>
        <Text style={styles.primaryButtonText}>
          {isSyncing ? i18n.t('syncing') : i18n.t('syncNow')}
        </Text>
      </Pressable>

      <Pressable onPress={() => void handleLogout()} style={styles.secondaryButton}>
        <Text style={styles.secondaryButtonText}>{i18n.t('logout')}</Text>
      </Pressable>

      <Modal
        animationType="fade"
        transparent
        visible={showLogoutWarning}
        onRequestClose={() => setShowLogoutWarning(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>{i18n.t('logoutWarningTitle')}</Text>
            <Text style={styles.modalBody}>
              {i18n.t('logoutWarningBody', { count: pendingSyncCount })}
            </Text>
            <View style={styles.modalActions}>
              <Pressable
                onPress={() => setShowLogoutWarning(false)}
                style={styles.modalSecondaryButton}
              >
                <Text style={styles.modalSecondaryButtonText}>{i18n.t('staySignedIn')}</Text>
              </Pressable>
              <Pressable
                onPress={async () => {
                  setShowLogoutWarning(false);
                  await signOut();
                }}
                style={styles.modalPrimaryButton}
              >
                <Text style={styles.modalPrimaryButtonText}>{i18n.t('logoutAnyway')}</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
    padding: theme.spacing.md,
    gap: theme.spacing.md,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
  },
  sectionText: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    marginBottom: 4,
  },
  segmentRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginTop: 8,
  },
  segment: {
    flex: 1,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    paddingVertical: 12,
    alignItems: 'center',
  },
  segmentActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  segmentText: {
    color: theme.colors.text,
    fontWeight: '600',
  },
  segmentTextActive: {
    color: '#fff',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 16,
  },
  secondaryButton: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  secondaryButtonText: {
    color: theme.colors.danger,
    fontWeight: '700',
    fontSize: 16,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(12, 24, 22, 0.45)',
    justifyContent: 'center',
    padding: theme.spacing.lg,
  },
  modalCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  modalTitle: {
    color: theme.colors.text,
    fontSize: 22,
    fontWeight: '700',
  },
  modalBody: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    marginTop: 12,
  },
  modalActions: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.lg,
  },
  modalSecondaryButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingVertical: 14,
  },
  modalSecondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  modalPrimaryButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.danger,
    paddingVertical: 14,
  },
  modalPrimaryButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
});
