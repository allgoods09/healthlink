import React from 'react';
import {
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { ChildHeader } from '../components/ChildHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { AppTheme } from '../theme';

export function NotificationsScreen({ navigation }: any) {
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
      <ChildHeader
        title={i18n.t('notifications')}
        onBack={() => navigation.goBack()}
      />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.summary}>
          <Text style={styles.summaryText}>
            {i18n.t('unreadNotificationsLabel', {
              count: unreadNotificationCount,
            })}
          </Text>
          {unreadNotificationCount > 0 ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={i18n.t('markAllRead')}
              onPress={() => void markAllNotificationsRead()}
              style={styles.inlineAction}
            >
              <Text style={styles.actionText}>
                {i18n.t('markAllRead')}
              </Text>
            </Pressable>
          ) : null}
        </View>

        <View style={styles.sectionRow}>
          <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t('recentNotifications')}</Text>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('refreshNotifications')}
            onPress={() => void refreshNotifications()} style={styles.inlineAction}>
            <Text style={styles.actionText}>{i18n.t('refreshNotifications')}</Text>
          </Pressable>
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
                      accessibilityRole="button"
                      accessibilityLabel={i18n.t('markRead')}
                      onPress={() => void markNotificationRead(notification.id)}
                      style={styles.inlineAction}
                    >
                      <Text style={styles.actionText}>
                        {i18n.t('markRead')}
                      </Text>
                    </Pressable>
                  ) : null}

                  {notification.action_url ? (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityLabel={notification.action_label ?? i18n.t('openAction')}
                      onPress={() =>
                        void handleOpenAction(
                          notification.id,
                          notification.action_url
                        )
                      }
                      style={styles.inlineAction}
                    >
                      <Text style={styles.actionText}>
                        {notification.action_label ?? i18n.t('openAction')} {'>'}
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

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: 16,
    paddingBottom: 32,
    gap: 12,
  },
  summary: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
  },
  summaryText: {
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 22,
    flexGrow: 1,
    flexShrink: 1,
  },
  sectionRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 17,
    lineHeight: 24,
    fontWeight: '600',
    flexGrow: 1,
    flexShrink: 1,
  },
  emptyCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 14,
    gap: 6,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontSize: 17,
    lineHeight: 24,
    fontWeight: '600',
  },
  emptyBody: {
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 22,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 14,
    gap: 6,
  },
  cardUnread: {
    borderColor: theme.colors.infoBorder,
  },
  cardHeader: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: theme.spacing.sm,
  },
  cardTitleWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    minWidth: 0,
  },
  cardTitle: {
    color: theme.colors.text,
    fontSize: 16,
    lineHeight: 24,
    fontWeight: '600',
    flex: 1,
  },
  unreadBadge: {
    color: theme.colors.primary,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    fontSize: 11,
    fontWeight: '700',
    flexShrink: 1,
  },
  cardBody: {
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 22,
  },
  cardMeta: {
    color: theme.colors.textMuted,
    fontSize: 14,
    lineHeight: 22,
  },
  cardActions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'flex-end',
    gap: 8,
  },
  inlineAction: {
    minHeight: 48,
    minWidth: 48,
    maxWidth: '100%',
    paddingHorizontal: 4,
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionText: {
    color: theme.colors.primary,
    fontSize: 14,
    lineHeight: 22,
    fontWeight: '600',
    flexShrink: 1,
  },
});
