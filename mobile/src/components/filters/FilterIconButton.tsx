import React from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAppTheme, useThemedStyles } from '../../context/AppContext';
import { AppTheme } from '../../theme';

type FilterIconButtonProps = {
  onPress: () => void;
  active: boolean;
  accessibilityLabel: string;
};

export function FilterIconButton({ onPress, active, accessibilityLabel }: FilterIconButtonProps) {
  const theme = useAppTheme();
  const styles = useThemedStyles(filterButtonStyles);
  return <Pressable accessibilityRole="button" accessibilityLabel={accessibilityLabel}
    accessibilityState={{ selected: active }} style={[styles.button, active && styles.active]} onPress={onPress}>
    <Ionicons accessible={false} name="filter-outline" size={22} color={active ? theme.colors.primary : theme.colors.textMuted} />
    {active ? <View accessible={false} style={styles.dot} /> : null}
  </Pressable>;
}

const filterButtonStyles = (theme: AppTheme) => StyleSheet.create({
  button: { width: 50, height: 50, borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface, alignItems: 'center', justifyContent: 'center' },
  active: { borderColor: theme.colors.primary },
  dot: { position: 'absolute', top: 5, right: 5, width: 5, height: 5, borderRadius: 3, backgroundColor: theme.colors.primary },
});
