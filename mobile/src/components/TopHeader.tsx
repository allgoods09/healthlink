import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import {
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { theme } from '../theme';

type TopHeaderProps = {
  title: string;
  actionIcon?: React.ComponentProps<typeof Ionicons>['name'];
  onActionPress?: () => void;
};

export function TopHeader({
  title,
  actionIcon = 'sync-outline',
  onActionPress,
}: TopHeaderProps) {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.wrapper, { paddingTop: Math.max(insets.top, 14) }]}>
      <View style={styles.header}>
        <View style={styles.sideSlot} />
        <Text style={styles.title}>{title}</Text>
        <View style={styles.sideSlot}>
          {onActionPress ? (
            <Pressable onPress={onActionPress} style={styles.iconButton}>
              <Ionicons name={actionIcon} size={21} color={theme.colors.primary} />
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    backgroundColor: theme.colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 4 },
    elevation: 4,
  },
  header: {
    minHeight: 66,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: theme.spacing.md,
    position: 'relative',
  },
  sideSlot: {
    width: 44,
    alignItems: 'flex-end',
    justifyContent: 'center',
  },
  title: {
    position: 'absolute',
    left: 72,
    right: 72,
    textAlign: 'center',
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
  iconButton: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primarySoft,
  },
});
