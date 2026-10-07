import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { householdFormMode, householdOccupancy, householdStatusKey } from '../lib/householdPresentation';
import { AppTheme } from '../theme';
import { HouseholdRecord } from '../types';

export function HouseholdAction({ label, onPress, disabled = false, primary = false }: {
  label: string; onPress: () => void; disabled?: boolean; primary?: boolean;
}) {
  const styles = useThemedStyles(householdUiStyles);
  return <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled }} disabled={disabled}
    onPress={onPress} style={[styles.action, primary && styles.primary, disabled && styles.disabled]}>
    <Text style={[styles.actionText, primary && styles.primaryText]}>{label}</Text>
  </Pressable>;
}

export function HouseholdState({ message, error = false, retry }: { message: string; error?: boolean; retry?: () => void }) {
  const styles = useThemedStyles(householdUiStyles);
  return <View style={styles.card}><Text accessibilityRole={error ? 'alert' : undefined} accessibilityLiveRegion="polite"
    style={error ? styles.error : styles.helper}>{message}</Text>
    {retry ? <HouseholdAction label={i18n.t('retry')} onPress={retry} /> : null}</View>;
}

export function HouseholdStatus({ row }: { row: HouseholdRecord }) {
  const styles = useThemedStyles(householdUiStyles);
  const mode = householdFormMode(row);
  return <View style={styles.status}><Text style={styles.statusText}>{i18n.t(householdStatusKey(row))}</Text>
    {row.verification_notes ? <Text style={styles.text}>{row.verification_notes}</Text> : null}
    {['submitted', 'rejected', 'approved', 'protected'].includes(mode) ? <Text style={styles.helper}>{i18n.t(`hhHelp_${mode}`)}</Text> : null}
  </View>;
}

export function HouseholdOccupancy({ row }: { row: HouseholdRecord }) {
  const styles = useThemedStyles(householdUiStyles);
  const state = householdOccupancy(row);
  return <View><Text style={styles.text}>{state === 'headed' ? i18n.t('hhHead', { name: row.current_head_name }) : i18n.t(`hhOccupancy_${state}`)}</Text>
    {state !== 'unknown' ? <Text style={styles.helper}>{i18n.t('hhMembersCount', { count: row.current_member_count ?? undefined })}</Text> : null}</View>;
}

export const householdUiStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: theme.spacing.md, paddingBottom: theme.spacing.xl, gap: theme.spacing.md },
  card: { backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border,
    borderRadius: theme.radius.lg, padding: theme.spacing.md, gap: theme.spacing.sm, marginBottom: theme.spacing.sm },
  title: { fontSize: 20, fontWeight: '700', color: theme.colors.text, flexShrink: 1 },
  text: { color: theme.colors.text, fontSize: 16, flexShrink: 1 },
  helper: { color: theme.colors.textMuted, lineHeight: 22, flexShrink: 1 },
  error: { color: theme.colors.danger, lineHeight: 22 },
  status: { backgroundColor: theme.colors.primarySoft, borderRadius: theme.radius.md, padding: theme.spacing.sm, gap: theme.spacing.xs },
  statusText: { color: theme.colors.primary, fontWeight: '600', flexShrink: 1 },
  action: { minHeight: 48, maxWidth: '100%', borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md,
    padding: theme.spacing.sm, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.surface },
  primary: { backgroundColor: theme.colors.primary, borderColor: theme.colors.primary },
  actionText: { color: theme.colors.primary, fontWeight: '600', textAlign: 'center', flexShrink: 1 },
  primaryText: { color: theme.colors.textOnPrimary },
  disabled: { opacity: 0.55 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.spacing.sm },
  input: { minHeight: 48, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.inputBackground,
    borderRadius: theme.radius.md, color: theme.colors.text, padding: theme.spacing.md },
});
