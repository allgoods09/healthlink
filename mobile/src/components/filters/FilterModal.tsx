import React, { ReactNode } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAppTheme, useThemedStyles } from '../../context/AppContext';
import { i18n } from '../../i18n';
import { AppTheme } from '../../theme';

type FilterModalProps = {
  visible: boolean;
  title: string;
  children: ReactNode;
  onClose: () => void;
  onClear: () => void;
  onApply: () => void;
  clearLabel: string;
  applyLabel: string;
  error?: string | null;
};

// The consumer owns filter state and hides its background accessibility tree while visible.
export function FilterModal({ visible, title, children, onClose, onClear, onApply, clearLabel, applyLabel, error }: FilterModalProps) {
  const theme = useAppTheme();
  const styles = useThemedStyles(filterModalStyles);
  const insets = useSafeAreaInsets();
  if (!visible) return null;
  return <Modal transparent visible animationType="none" onRequestClose={onClose}>
    <View style={[styles.overlay, { paddingTop: Math.max(16, insets.top), paddingBottom: Math.max(16, insets.bottom) }]}>
      <Pressable style={StyleSheet.absoluteFill} accessibilityRole="button" accessibilityLabel={i18n.t('cancel')} importantForAccessibility="no" onPress={onClose} />
      <View style={styles.panel} accessibilityViewIsModal onAccessibilityEscape={onClose}>
        <View style={styles.header}>
          <Text accessibilityRole="header" style={styles.title}>{title}</Text>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('close')} style={styles.close} onPress={onClose}>
            <Ionicons accessible={false} name="close-outline" size={22} color={theme.colors.text} />
          </Pressable>
        </View>
        <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.fields}>
          {children}
          {error ? <Text accessibilityRole="alert" accessibilityLiveRegion="polite" style={styles.error}>{error}</Text> : null}
        </ScrollView>
        <View style={styles.actions}>
          <Pressable accessibilityRole="button" accessibilityLabel={clearLabel} style={styles.action} onPress={onClear}>
            <Text style={styles.actionText}>{clearLabel}</Text>
          </Pressable>
          <Pressable accessibilityRole="button" accessibilityLabel={applyLabel} style={[styles.action, styles.apply]} onPress={onApply}>
            <Text style={styles.applyText}>{applyLabel}</Text>
          </Pressable>
        </View>
      </View>
    </View>
  </Modal>;
}

const filterModalStyles = (theme: AppTheme) => StyleSheet.create({
  overlay: { flex: 1, paddingHorizontal: 16, justifyContent: 'center', backgroundColor: theme.colors.overlay },
  panel: { maxHeight: '100%', borderRadius: 8, padding: 16, gap: 12, backgroundColor: theme.colors.surface },
  header: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 },
  title: { color: theme.colors.text, fontSize: 17, lineHeight: 24, fontWeight: '600', flexGrow: 1, flexShrink: 1 },
  close: { minHeight: 48, minWidth: 48, maxWidth: '100%', paddingHorizontal: 4,
    paddingVertical: 12, alignItems: 'center', justifyContent: 'center', marginLeft: 'auto' },
  fields: { gap: 8 },
  error: { color: theme.colors.danger, lineHeight: 22 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  action: { flexGrow: 1, minHeight: 48, paddingHorizontal: 16, paddingVertical: 12, alignItems: 'center', justifyContent: 'center',
    borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface },
  apply: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primary },
  actionText: { color: theme.colors.primary, fontSize: 14, lineHeight: 22, fontWeight: '600', flexShrink: 1 },
  applyText: { color: theme.colors.textOnPrimary, fontSize: 16, lineHeight: 24, fontWeight: '600', textAlign: 'center' },
});
