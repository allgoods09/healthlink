import { Ionicons } from '@expo/vector-icons';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChildHeader, childPageStyles } from '../components/ChildHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { AppTheme } from '../theme';

export function accountInitials(name?: string): string {
  const words = name?.trim().split(/\s+/).filter((word) => /[\p{L}\p{N}]/u.test(word)) ?? [];
  const initial = (word: string) => Array.from(word).find((char) => /[\p{L}\p{N}]/u.test(char)) ?? '';
  return Array.from((words.length > 1 ? initial(words[0]!) + initial(words[words.length - 1]!) : initial(words[0] ?? '')).toUpperCase()).slice(0, 2).join('');
}

export function AccountScreen({ navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const { user, assignment } = useAppContext();
  const role = user?.role === 'bhw' ? i18n.t('accountBhwRole') : null;
  const initials = accountInitials(user?.name);
  const sections = [
    ['accountInformation', [
    ['accountName', user?.name],
    ['email', user?.email],
    ]],
    ['accountAssignedArea', [
    ['accountBarangay', assignment?.barangay?.name],
    ['accountPurok', assignment?.purok?.display_name],
    ]],
  ] as const;
  return <View style={styles.screen}>
    <ChildHeader title={i18n.t('accountTitle')} onBack={() => navigation.goBack()} />
    <ScrollView contentContainerStyle={styles.content}>
      <View style={styles.identity}>
        <View accessible={false} importantForAccessibility="no-hide-descendants" style={styles.avatar}>
          {initials ? <Text style={styles.initials}>{initials}</Text>
            : <Ionicons name="person-outline" size={32} color={styles.initials.color} />}
        </View>
        {user?.name ? <Text style={styles.displayName}>{user.name}</Text> : null}
        {role ? <Text style={styles.role}>{role}</Text> : null}
      </View>
      {sections.map(([title, facts]) => {
        const available = facts.filter(([, value]) => value);
        return available.length ? <View key={title} style={styles.section}>
          <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t(title)}</Text>
          {available.map(([label, value]) => <View key={label} style={styles.fact}>
            <Text style={styles.label}>{i18n.t(label)}</Text>
            <Text style={styles.value}>{value}</Text>
          </View>)}
        </View> : null;
      })}
    </ScrollView>
  </View>;
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  ...childPageStyles(theme),
  content: { padding: 16, paddingTop: 24, paddingBottom: 32, gap: 24 },
  identity: { alignItems: 'center', gap: 8, marginBottom: 8 },
  avatar: { width: 76, height: 76, borderRadius: 38, backgroundColor: theme.colors.primarySoft,
    alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
  initials: { fontSize: 24, fontWeight: '600', color: theme.colors.primaryDark },
  displayName: { fontSize: 22, lineHeight: 28, fontWeight: '600', color: theme.colors.text, textAlign: 'center', maxWidth: '100%' },
  role: { fontSize: 14, lineHeight: 20, color: theme.colors.textMuted, textAlign: 'center', maxWidth: '100%' },
  section: { gap: 18 },
  sectionTitle: { fontSize: 13, lineHeight: 19, fontWeight: '500', textTransform: 'uppercase', color: theme.colors.textMuted },
});
