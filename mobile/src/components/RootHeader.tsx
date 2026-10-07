import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { AppTheme } from '../theme';

export function RootHeader({ title, onNotificationPress, onLogoutPress, unreadCount = 0 }: {
  title: string;
  onNotificationPress: () => void;
  onLogoutPress: () => void;
  unreadCount?: number;
}) {
  const theme = useAppTheme();
  const styles = useThemedStyles(rootHeaderStyles);
  const insets = useSafeAreaInsets();
  const notificationLabel = unreadCount > 0
    ? `${i18n.t('notifications')}. ${i18n.t('unreadNotificationsLabel', { count: unreadCount })}`
    : i18n.t('notifications');

  return (
    <View style={[styles.wrapper, { paddingTop: insets.top }]}>
      <View style={styles.header}>
        <View accessible={false} importantForAccessibility="no-hide-descendants" style={styles.actionGroup} />
        <Text accessibilityRole="header" style={styles.title}>{title}</Text>
        <View style={styles.actionGroup}>
          <Pressable accessibilityRole="button" accessibilityLabel={notificationLabel}
            onPress={onNotificationPress} style={styles.action}>
            <View style={styles.notificationIcon}>
              <Ionicons accessible={false} name="notifications-outline" size={22} color={theme.colors.primary} />
              {unreadCount > 0 ? <Text accessible={false} importantForAccessibility="no" style={styles.badge}>
                {unreadCount > 99 ? '99+' : unreadCount}
              </Text> : null}
            </View>
          </Pressable>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('logout')}
            onPress={onLogoutPress} style={styles.action}>
            <Ionicons accessible={false} name="log-out-outline" size={22} color={theme.colors.primary} />
          </Pressable>
        </View>
      </View>
    </View>
  );
}

export const rootHeaderStyles = (theme: AppTheme) => StyleSheet.create({
  wrapper: { backgroundColor: theme.colors.surface, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  header: { minHeight: 56, paddingHorizontal: 16, paddingVertical: 4, flexDirection: 'row', alignItems: 'center', gap: 8 },
  // Equal side tracks center the title on the screen without overlapping actions.
  actionGroup: { width: 104, flexShrink: 0, flexDirection: 'row', gap: 8 },
  title: { flex: 1, minWidth: 0, flexShrink: 1, textAlign: 'center', fontSize: 21, lineHeight: 27, fontWeight: '400', color: theme.colors.text },
  action: { minWidth: 48, minHeight: 48, flexShrink: 0, alignItems: 'center', justifyContent: 'center', gap: 2 },
  notificationIcon: { position: 'relative' },
  badge: { position: 'absolute', top: -8, left: 12, backgroundColor: theme.colors.danger, color: theme.colors.textOnPrimary, borderRadius: 999,
    fontSize: 11, fontWeight: '600', textAlign: 'center', paddingHorizontal: 4, minWidth: 18, minHeight: 18 },
});
