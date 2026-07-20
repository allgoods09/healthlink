import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import {
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { theme } from '../theme';

type MenuCardProps = {
  title: string;
  subtitle?: string;
  icon: React.ComponentProps<typeof Ionicons>['name'];
  onPress?: () => void;
  tone?: 'default' | 'primary' | 'danger';
  badge?: string;
};

export function MenuCard({
  title,
  subtitle,
  icon,
  onPress,
  tone = 'default',
  badge,
}: MenuCardProps) {
  const palette =
    tone === 'primary'
      ? {
          iconBg: theme.colors.primarySoft,
          iconColor: theme.colors.primary,
          titleColor: theme.colors.text,
        }
      : tone === 'danger'
        ? {
            iconBg: theme.colors.dangerSoft,
            iconColor: theme.colors.danger,
            titleColor: theme.colors.text,
          }
        : {
            iconBg: theme.colors.surfaceMuted,
            iconColor: theme.colors.primaryDark,
            titleColor: theme.colors.text,
          };

  const content = (
    <View style={styles.card}>
      <View style={[styles.iconWrap, { backgroundColor: palette.iconBg }]}>
        <Ionicons name={icon} size={22} color={palette.iconColor} />
      </View>
      <View style={styles.body}>
        <Text style={[styles.title, { color: palette.titleColor }]}>{title}</Text>
        {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
      </View>
      {badge ? (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{badge}</Text>
        </View>
      ) : null}
    </View>
  );

  if (!onPress) {
    return content;
  }

  return (
    <Pressable onPress={onPress} style={styles.pressable}>
      {content}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pressable: {
    marginBottom: theme.spacing.md,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
    minHeight: 94,
    flexDirection: 'row',
    alignItems: 'center',
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  iconWrap: {
    width: 48,
    height: 48,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: {
    flex: 1,
    marginLeft: theme.spacing.md,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    lineHeight: 22,
  },
  subtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
    lineHeight: 20,
    fontSize: 13,
  },
  badge: {
    minWidth: 34,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: theme.colors.primarySoft,
    alignItems: 'center',
  },
  badgeText: {
    color: theme.colors.primary,
    fontWeight: '700',
    fontSize: 12,
  },
});
