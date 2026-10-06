import React from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useThemedStyles } from '../context/AppContext';
import { AppTheme } from '../theme';
import { i18n } from '../i18n';

export function ResidentAction({ label, onPress, primary = false, disabled = false, selected = false, accessibilityLabel }: {
  label: string; onPress: () => void; primary?: boolean; disabled?: boolean; selected?: boolean; accessibilityLabel?: string;
}) {
  const styles = useThemedStyles(residentStyles);
  return <Pressable accessibilityRole="button" accessibilityLabel={accessibilityLabel ?? label}
    accessibilityState={{ disabled, selected }} disabled={disabled} onPress={onPress}
    style={[styles.action, primary && styles.primary, disabled && styles.disabled]}>
    <Text style={[styles.actionText, primary && styles.onPrimary]}>{label}</Text>
  </Pressable>;
}

export function ResidentSheet({ title, visible, close, children }: {
  title: string; visible: boolean; close: () => void; children: React.ReactNode;
}) {
  const styles = useThemedStyles(residentStyles);
  return <Modal visible={visible} transparent animationType="none" onRequestClose={close}>
    <View style={styles.overlay}>
      <Pressable style={StyleSheet.absoluteFill} accessibilityRole="button" accessibilityLabel={i18n.t('close')}
        onPress={close} importantForAccessibility="no" />
      <View style={styles.sheet} accessibilityViewIsModal>
        <Text accessibilityRole="header" style={styles.section}>{title}</Text>
        <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.stack}>{children}</ScrollView>
        <ResidentAction label={i18n.t('close')} onPress={close} />
      </View>
    </View>
  </Modal>;
}

export const residentStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: theme.spacing.md, paddingBottom: theme.spacing.xl, gap: theme.spacing.sm },
  title: { fontSize: 26, fontWeight: '700', color: theme.colors.text },
  section: { fontSize: 18, fontWeight: '700', color: theme.colors.text },
  text: { color: theme.colors.text, fontSize: 16, lineHeight: 24 },
  muted: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 22 },
  card: { backgroundColor: theme.colors.surface, borderRadius: theme.radius.lg, borderWidth: 1,
    borderColor: theme.colors.border, padding: theme.spacing.md, gap: theme.spacing.sm, marginBottom: theme.spacing.sm },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.sm, alignItems: 'center' },
  stack: { gap: theme.spacing.sm },
  search: { minHeight: 48, color: theme.colors.text, backgroundColor: theme.colors.surface,
    borderColor: theme.colors.border, borderWidth: 1, borderRadius: theme.radius.md, padding: theme.spacing.sm },
  action: { minHeight: 48, justifyContent: 'center', alignItems: 'center', borderRadius: theme.radius.md,
    borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface,
    paddingHorizontal: theme.spacing.md, paddingVertical: theme.spacing.sm },
  primary: { backgroundColor: theme.colors.primary, borderColor: theme.colors.primary },
  disabled: { opacity: 0.65 },
  actionText: { color: theme.colors.primary, fontSize: 15, fontWeight: '700', textAlign: 'center', flexShrink: 1 },
  onPrimary: { color: theme.colors.textOnPrimary },
  overlay: { flex: 1, justifyContent: 'center', padding: theme.spacing.md, backgroundColor: theme.colors.overlay },
  sheet: { maxHeight: '85%', borderRadius: theme.radius.lg, padding: theme.spacing.md,
    backgroundColor: theme.colors.surface, gap: theme.spacing.sm },
});
