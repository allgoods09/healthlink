import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { AppTheme } from '../theme';

export function ChildHeader({ title, onBack }: { title: string; onBack: () => void }) {
  const theme = useAppTheme();
  const styles = useThemedStyles(headerStyles);
  const insets = useSafeAreaInsets();
  return <View style={[styles.wrapper, { paddingTop: insets.top }]}>
    <View style={styles.header}>
      <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('back')} onPress={onBack} style={styles.back}>
        <Ionicons accessible={false} name="arrow-back" size={22} color={theme.colors.primary} />
      </Pressable>
      <Text accessibilityRole="header" style={styles.title}>{title}</Text>
      <View accessible={false} importantForAccessibility="no-hide-descendants" style={styles.back} />
    </View>
  </View>;
}
const headerStyles = (theme: AppTheme) => StyleSheet.create({
  wrapper: { backgroundColor: theme.colors.surface, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: theme.colors.border },
  header: { minHeight: 56, paddingHorizontal: 16, paddingVertical: 4, flexDirection: 'row', alignItems: 'center', gap: 8 },
  back: { width: 48, minHeight: 48, flexShrink: 0, alignItems: 'center', justifyContent: 'center' },
  title: { flex: 1, minWidth: 0, flexShrink: 1, textAlign: 'center', fontSize: 21, lineHeight: 27, fontWeight: '400', color: theme.colors.text },
});

// Opt-in styles for the four More children; existing inner screens remain unchanged.
export const childPageStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: 16, paddingBottom: 32, gap: 18 },
  fact: { gap: 4 },
  label: { fontSize: 14, lineHeight: 20, color: theme.colors.textMuted },
  value: { fontSize: 16, lineHeight: 24, color: theme.colors.text },
  name: { fontSize: 22, lineHeight: 28, fontWeight: '600', color: theme.colors.text },
  row: { minHeight: 60, borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface, paddingHorizontal: 16, paddingVertical: 16,
    flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowLabel: { fontSize: 16, lineHeight: 24, color: theme.colors.text, flex: 1, minWidth: 0 },
  selected: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primarySoft },
  selectedLabel: { color: theme.colors.primaryDark },
});
