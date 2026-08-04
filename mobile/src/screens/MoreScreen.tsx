import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { MenuCard } from '../components/MenuCard';
import { TopHeader } from '../components/TopHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { AppTheme } from '../theme';

export function MoreScreen({ navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const {
    appearancePreference,
    appVersion,
    assignment,
    bootstrapCompleted,
    isOnline,
    language,
    lastSyncAt,
    notifications,
    pendingSyncCount,
    refreshReleaseStatus,
    releaseCheck,
    requestConfirmation,
    setLanguagePreference,
    setAppearancePreference,
    signOut,
    unreadNotificationCount,
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
      <TopHeader
        title={i18n.t('more')}
        actionIcon="notifications-outline"
        onActionPress={() => navigation.navigate('Notifications')}
      />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.profileCard}>
          <Text style={styles.profileName}>{user?.name ?? i18n.t('accountTitle')}</Text>
          <Text style={styles.profileEmail}>{user?.email}</Text>
          <View style={styles.profileMetaRow}>
            <Text style={styles.profileMetaText}>
              {assignment?.barangay?.name ?? 'Barangay'}
            </Text>
            <Text style={styles.profileMetaDivider}>·</Text>
            <Text style={styles.profileMetaText}>
              {assignment?.purok?.display_name ?? 'Unassigned'}
            </Text>
          </View>
        </View>

        <MenuCard
          title={i18n.t('assignmentTitle')}
          subtitle={`${assignment?.barangay?.name ?? 'N/A'} · ${assignment?.purok?.display_name ?? 'N/A'}`}
          icon="location-outline"
          badge={isOnline ? i18n.t('online') : i18n.t('offline')}
        />

        <MenuCard
          title={i18n.t('accountTitle')}
          subtitle={
            bootstrapCompleted && lastSyncAt
              ? `${i18n.t('lastSync')}: ${formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt}`
              : i18n.t('bootstrapPending')
          }
          icon="person-circle-outline"
          badge={String(pendingSyncCount)}
        />

        <MenuCard
          title={i18n.t('notifications')}
          subtitle={
            notifications[0]?.title
              ? notifications[0].title
              : i18n.t('noNotificationsBody')
          }
          icon="notifications-outline"
          badge={String(unreadNotificationCount)}
          onPress={() => navigation.navigate('Notifications')}
        />

        <MenuCard
          title={i18n.t('updateReady')}
          subtitle={
            releaseCheck?.update.available
              ? `${i18n.t('latestAvailableVersion')}: ${releaseCheck.release?.version_name ?? 'N/A'}`
              : `${i18n.t('currentAppVersion')}: ${appVersion}`
          }
          icon="cloud-download-outline"
          badge={releaseCheck?.update.available ? i18n.t('online') : undefined}
          onPress={() => void refreshReleaseStatus()}
        />

        {releaseCheck?.maintenance.maintenance_message ? (
          <MenuCard
            title={i18n.t('mobileMaintenanceTitle')}
            subtitle={releaseCheck.maintenance.maintenance_message}
            icon="build-outline"
          />
        ) : null}

        <Text style={styles.sectionTitle}>{i18n.t('languageTitle')}</Text>
        <View style={styles.languageCard}>
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

        <Text style={styles.sectionTitle}>{i18n.t('appearanceTitle')}</Text>
        <View style={styles.languageCard}>
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

        <MenuCard
          title={i18n.t('logout')}
          subtitle={i18n.t('logoutDescription')}
          icon="log-out-outline"
          onPress={() => void handleLogout()}
          tone="danger"
        />
      </ScrollView>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
  },
  profileCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: 28,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  profileName: {
    color: theme.colors.text,
    fontSize: 28,
    fontWeight: '700',
  },
  profileEmail: {
    color: theme.colors.textMuted,
    marginTop: 8,
  },
  profileMetaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 6,
    marginTop: 12,
  },
  profileMetaText: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  profileMetaDivider: {
    color: theme.colors.textMuted,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: theme.spacing.sm,
  },
  languageCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
  },
  segmentRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  appearanceRow: {
    flexDirection: 'row',
    gap: 6,
  },
  segment: {
    flex: 1,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    paddingVertical: 13,
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
  appearanceOption: {
    flex: 1,
    minHeight: 46,
    borderRadius: theme.radius.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  appearanceOptionText: {
    color: theme.colors.text,
    fontWeight: '600',
    fontSize: 12,
  },
});
