import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { RootHeader } from '../components/RootHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { confirmLogout } from '../lib/confirmLogout';
import { AppTheme } from '../theme';

export function MoreScreen({ navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const { pendingSyncCount, requestConfirmation, signOut, unreadNotificationCount } = useAppContext();
  return <View style={styles.screen}>
    <RootHeader title={i18n.t('more')} unreadCount={unreadNotificationCount}
      onNotificationPress={() => navigation.navigate('Notifications')}
      onLogoutPress={() => void confirmLogout({ pendingSyncCount, requestConfirmation, signOut })} />
    <ScrollView contentContainerStyle={styles.content}>
      {([['Account', 'accountTitle'], ['Language', 'languageTitle'], ['Appearance', 'appearanceTitle'], ['AboutApp', 'aboutApp']] as const)
        .map(([route, label]) => <Pressable key={route} accessibilityRole="button" accessibilityLabel={i18n.t(label)}
          onPress={() => navigation.navigate(route)} style={styles.row}>
          <Text style={styles.label}>{i18n.t(label)}</Text>
        </Pressable>)}
    </ScrollView>
  </View>;
}
const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: 16, paddingBottom: 32, gap: 16 },
  row: { minHeight: 66, borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface, paddingHorizontal: 16, paddingVertical: 20, justifyContent: 'center' },
  label: { fontSize: 16, lineHeight: 24, color: theme.colors.text },
});
