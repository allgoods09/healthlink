import { Ionicons } from '@expo/vector-icons';
import { Image, Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChildHeader, childPageStyles } from '../components/ChildHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { MOBILE_API_BASE_URL } from '../lib/config';
import { bhwUpdatePageUrl } from '../lib/updatePage';
import { AppTheme } from '../theme';

export function AboutAppScreen({ navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const { appVersion, showToast } = useAppContext();
  async function openUpdates() {
    try {
      const url = bhwUpdatePageUrl(MOBILE_API_BASE_URL);
      if (!url) throw new Error('Update URL unavailable');
      await Linking.openURL(url);
    } catch {
      showToast(i18n.t('updatePageUnavailable'), 'warning');
    }
  }
  return <View style={styles.screen}>
    <ChildHeader title={i18n.t('aboutApp')} onBack={() => navigation.goBack()} />
    <ScrollView contentContainerStyle={styles.content}>
      <View style={styles.identity}>
        <Image accessible={false} source={require('../../assets/apk-logo-icon.png')} resizeMode="contain" style={styles.logo} />
        <Text accessibilityRole="header" style={styles.appName}>{i18n.t('aboutAppName')}</Text>
        <Text style={styles.version}>{i18n.t('appVersionLabel')} {appVersion}</Text>
      </View>
      <Text style={styles.description}>{i18n.t('aboutAppPurpose')}</Text>
      <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('checkForUpdates')} onPress={() => void openUpdates()} style={styles.row}>
        <Text style={styles.rowLabel}>{i18n.t('checkForUpdates')}</Text>
        <Ionicons accessible={false} name="open-outline" size={20} color={styles.version.color} />
      </Pressable>
    </ScrollView>
  </View>;
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  ...childPageStyles(theme),
  content: { padding: 16, paddingTop: 32, paddingBottom: 32, gap: 24 },
  identity: { alignItems: 'center', gap: 8 },
  logo: { width: 88, height: 88, marginBottom: 8 },
  appName: { fontSize: 24, lineHeight: 32, fontWeight: '600', color: theme.colors.text, textAlign: 'center', maxWidth: '100%' },
  version: { fontSize: 14, lineHeight: 20, color: theme.colors.textMuted, textAlign: 'center' },
  description: { fontSize: 15, lineHeight: 23, color: theme.colors.textMuted, textAlign: 'center' },
  row: { ...childPageStyles(theme).row, minHeight: 66 },
});
