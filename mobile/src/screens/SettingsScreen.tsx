import {
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { AppTheme } from '../theme';

export function SettingsScreen() {
  const styles = useThemedStyles(createStyles);
  const {
    appearancePreference,
    assignment,
    bootstrapCompleted,
    isOnline,
    isSyncing,
    language,
    lastSyncAt,
    pendingSyncCount,
    requestConfirmation,
    setLanguagePreference,
    setAppearancePreference,
    signOut,
    syncNow,
    user,
  } = useAppContext();
  async function handleLogout() {
    const hasPendingDrafts = pendingSyncCount > 0;
    const confirmed = await requestConfirmation({
      title: hasPendingDrafts
        ? i18n.t('logoutWarningTitle')
        : i18n.t('logoutConfirmationTitle'),
      message: hasPendingDrafts
        ? i18n.t('logoutWarningBody', { count: pendingSyncCount })
        : i18n.t('logoutConfirmationBody'),
      confirmLabel: hasPendingDrafts ? i18n.t('logoutAnyway') : i18n.t('logout'),
      tone: hasPendingDrafts ? 'warning' : 'danger',
    });

    if (!confirmed) {
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

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{i18n.t('appearance')}</Text>
        <View style={styles.appearanceRow}>
          {([
            ['system', i18n.t('systemDefault')],
            ['light', i18n.t('lightMode')],
            ['dark', i18n.t('darkMode')],
          ] as const).map(([preference, label]) => (
            <Pressable
              key={preference}
              onPress={() => void setAppearancePreference(preference)}
              style={[
                styles.appearanceOption,
                appearancePreference === preference && styles.segmentActive,
              ]}
            >
              <Text
                numberOfLines={1}
                style={[
                  styles.appearanceOptionText,
                  appearancePreference === preference && styles.segmentTextActive,
                ]}
              >
                {label}
              </Text>
            </Pressable>
          ))}
        </View>
      </View>

      <Pressable onPress={syncNow} style={styles.primaryButton}>
        <Text style={styles.primaryButtonText}>
          {isSyncing ? i18n.t('syncing') : i18n.t('syncNow')}
        </Text>
      </Pressable>

      <Pressable onPress={() => void handleLogout()} style={styles.secondaryButton}>
        <Text style={styles.secondaryButtonText}>{i18n.t('logout')}</Text>
      </Pressable>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
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
    color: theme.colors.textOnPrimary,
  },
  appearanceRow: {
    flexDirection: 'row',
    gap: 6,
    marginTop: 8,
  },
  appearanceOption: {
    flex: 1,
    minHeight: 44,
    borderRadius: theme.radius.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  appearanceOptionText: {
    color: theme.colors.text,
    fontSize: 12,
    fontWeight: '600',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: theme.colors.textOnPrimary,
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
});
