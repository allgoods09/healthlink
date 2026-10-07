import React from 'react';
import { Pressable, StyleSheet, Text } from 'react-native';

import { useThemedStyles } from '../context/AppContext';
import { AppTheme } from '../theme';

export function CompactActionRow({ label, onPress, textAction = false }: {
  label: string;
  onPress: () => void;
  textAction?: boolean;
}) {
  const styles = useThemedStyles(compactStyles);
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress}
      style={[styles.action, textAction && styles.textAction]}>
      <Text style={[styles.actionLabel, textAction && styles.textActionLabel]}>{label}</Text>
    </Pressable>
  );
}

// Opt-in presentation: existing theme radii and other screens stay unchanged.
export const compactStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: 16, paddingBottom: 32, gap: 24 },
  section: { gap: 12 },
  banner: { backgroundColor: theme.colors.brandBackground, borderRadius: 6, padding: 16,
    flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 16 },
  bannerContext: { flexBasis: 180, flexGrow: 1, flexShrink: 1, minWidth: 0, gap: 8 },
  bannerText: { color: theme.colors.textOnBrand },
  bannerLabel: { color: theme.colors.textOnBrand, fontSize: 13, lineHeight: 19, marginTop: 4 },
  bannerBrand: { alignItems: 'center', gap: 6, maxWidth: '100%' },
  bannerLogo: { width: 48, height: 48 },
  bannerBrandName: { color: theme.colors.textOnBrand, fontSize: 14, lineHeight: 20 },
  welcome: { fontSize: 24, lineHeight: 30, fontWeight: '600', color: theme.colors.text, flexShrink: 1 },
  body: { fontSize: 16, lineHeight: 24, color: theme.colors.text, flexShrink: 1 },
  secondary: { fontSize: 14, lineHeight: 20, color: theme.colors.textMuted, flexShrink: 1 },
  sectionTitle: { fontSize: 20, lineHeight: 26, fontWeight: '600', color: theme.colors.text },
  action: { minHeight: 50, minWidth: 48, maxWidth: '100%', justifyContent: 'center',
    paddingHorizontal: 16, paddingVertical: 12, borderRadius: 6, borderWidth: 1,
    borderColor: theme.colors.border, backgroundColor: theme.colors.surface },
  actionLabel: { fontSize: 16, lineHeight: 22, color: theme.colors.text, flexShrink: 1 },
  textAction: { alignSelf: 'flex-start', borderColor: theme.colors.primary },
  textActionLabel: { color: theme.colors.primary, fontWeight: '600' },
  factRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'baseline', gap: 8 },
  factLabel: { fontSize: 14, lineHeight: 22, color: theme.colors.textMuted, flexBasis: 120, flexGrow: 1, minWidth: 0 },
  factValue: { fontSize: 16, lineHeight: 22, color: theme.colors.text, flexBasis: 150, flexGrow: 1, flexShrink: 1, minWidth: 0 },
});
