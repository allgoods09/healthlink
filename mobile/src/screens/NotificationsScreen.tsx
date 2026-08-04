import React from 'react';
import {
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { TopHeader } from '../components/TopHeader';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { AppTheme } from '../theme';

export function NotificationsScreen() {
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const {
    isOnline,
    markAllNotificationsRead,
    markNotificationRead,
    notifications,
    refreshNotifications,
    showToast,
    unreadNotificationCount,
  } = useAppContext();

  async function handleOpenAction(
    notificationId: string,
    actionUrl?: string | null
  ) {
    await markNotificationRead(notificationId);

    if (!actionUrl) {
      return;
    }

    if (!isOnline) {
      showToast(i18n.t('notificationOpenOnlineOnly'), 'warning');
      return;
    }

    try {
      await Linking.openURL(actionUrl);
    } catch (error) {
      showToast(
        error instanceof Error ? error.message : i18n.t('notificationOpenFailed'),
        'error'
      );
    }
  }

  return (
    <View style={styles.screen}>
      <TopHeader
        title={i18n.t('notifications')}
        actionIcon="refresh-outline"
        onActionPress={() => void refreshNotifications()}
      />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Text style={styles.heroKicker}>{i18n.t('notificationCenter')}</Text>
          <Text style={styles.heroTitle}>{i18n.t('notifications')}</Text>
          <Text style={styles.heroBody}>{i18n.t('notificationsBody')}</Text>
          <View style={styles.heroMeta}>
            <Text style={styles.heroMetaText}>
              {i18n.t('unreadNotificationsLabel', {
                count: unreadNotificationCount,
              })}
            </Text>
            {unreadNotificationCount > 0 ? (
              <Pressable
                onPress={() => void markAllNotificationsRead()}
                style={styles.markAllButton}
              >
                <Text style={styles.markAllButtonText}>
                  {i18n.t('markAllRead')}
                </Text>
              </Pressable>
            ) : null}
          </View>
        </View>

        {notifications.length === 0 ? (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyTitle}>{i18n.t('noNotifications')}</Text>
            <Text style={styles.emptyBody}>
              {i18n.t('noNotificationsBody')}
            </Text>
          </View>
        ) : (
          notifications.map((notification) => {
            const unread = !notification.read_at;

            return (
              <View
                key={notification.id}
                style={[styles.card, unread && styles.cardUnread]}
              >
                <View style={styles.cardHeader}>
                  <View style={styles.cardTitleWrap}>
                    <View
                      style={[
                        styles.dot,
                        { backgroundColor: levelColor(notification.level, theme) },
                      ]}
                    />
                    <Text style={styles.cardTitle}>{notification.title}</Text>
                  </View>
                  {unread ? (
                    <Text style={styles.unreadBadge}>{i18n.t('newLabel')}</Text>
                  ) : null}
                </View>

                <Text style={styles.cardBody}>{notification.body}</Text>
                {notification.sender_name ? (
                  <Text style={styles.cardMeta}>
                    {i18n.t('fromLabel')}: {notification.sender_name}
                  </Text>
                ) : null}
                <Text style={styles.cardMeta}>
                  {formatFriendlyDateTime(notification.created_at) ??
                    notification.created_at ??
                    'N/A'}
                </Text>

                <View style={styles.cardActions}>
                  {unread ? (
                    <Pressable
                      onPress={() => void markNotificationRead(notification.id)}
                      style={styles.secondaryButton}
                    >
                      <Text style={styles.secondaryButtonText}>
                        {i18n.t('markRead')}
                      </Text>
                    </Pressable>
                  ) : null}

                  {notification.action_url ? (
                    <Pressable
                      onPress={() =>
                        void handleOpenAction(
                          notification.id,
                          notification.action_url
                        )
                      }
                      style={styles.primaryButton}
                    >
                      <Text style={styles.primaryButtonText}>
                        {notification.action_label ?? i18n.t('openAction')}
                      </Text>
                    </Pressable>
                  ) : null}
                </View>
              </View>
            );
          })
        )}
      </ScrollView>
    </View>
  );
}

function levelColor(
  level: 'info' | 'success' | 'warning' | 'error',
  theme: AppTheme
) {
  switch (level) {
    case 'success':
      return theme.colors.success;
    case 'warning':
      return theme.colors.warning;
    case 'error':
      return theme.colors.danger;
    default:
      return theme.colors.accent;
  }
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
  hero: {
    backgroundColor: theme.colors.surface,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.md,
  },
  heroKicker: {
    color: theme.colors.primary,
    textTransform: 'uppercase',
    letterSpacing: 1,
    fontSize: 12,
    fontWeight: '700',
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 26,
    fontWeight: '700',
    marginTop: 8,
  },
  heroBody: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    marginTop: 10,
  },
  heroMeta: {
    marginTop: theme.spacing.md,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: theme.spacing.sm,
  },
  heroMetaText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  markAllButton: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  markAllButtonText: {
    color: theme.colors.primary,
    fontWeight: '700',
  },
  emptyCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: '700',
  },
  emptyBody: {
    marginTop: 10,
    color: theme.colors.textMuted,
    lineHeight: 22,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
  },
  cardUnread: {
    backgroundColor: theme.colors.infoSoft,
    borderColor: theme.colors.infoBorder,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: theme.spacing.sm,
  },
  cardTitleWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 999,
  },
  cardTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontWeight: '700',
    flex: 1,
  },
  unreadBadge: {
    color: theme.colors.primary,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    fontSize: 11,
    fontWeight: '700',
  },
  cardBody: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    marginTop: 10,
  },
  cardMeta: {
    color: theme.colors.textMuted,
    marginTop: 8,
  },
  cardActions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  primaryButtonText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '700',
  },
  secondaryButton: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
});
