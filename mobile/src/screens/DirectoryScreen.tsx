import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { compactStyles } from '../components/CompactUi';
import { RootHeader } from '../components/RootHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { getCurrentOfficialResidentCount, getCurrentOfficialHouseholdCount, hasBootstrapData } from '../lib/storage';
import { ResidentAction } from '../components/ResidentUi';
import { confirmLogout } from '../lib/confirmLogout';
import { AppTheme } from '../theme';

export function DirectoryScreen({ navigation }: any) {
  const { assignment, dataVersion, pendingSyncCount, requestConfirmation, signOut, unreadNotificationCount } = useAppContext();
  const focused = useIsFocused();
  const styles = useThemedStyles(directoryStyles);
  const [counts, setCounts] = useState<{ residents: number; households: number; key: string } | null>(null);
  const [state, setState] = useState('loading');
  const [retry, setRetry] = useState(0);
  const key = `${assignment?.barangay?.id}:${assignment?.purok?.id}:${dataVersion}`;
  useEffect(() => {
    if (!focused) return;
    let applicable = true;
    setState('loading');
    async function load() {
      try {
        if (!await hasBootstrapData()) { if (applicable) { setCounts(null); setState('assignment'); } return; }
        const [residents, households] = await Promise.all([getCurrentOfficialResidentCount(), getCurrentOfficialHouseholdCount()]);
        if (!await hasBootstrapData()) { if (applicable) { setCounts(null); setState('assignment'); } return; }
        if (applicable) { setCounts({ residents, households, key }); setState('ready'); }
      } catch { if (applicable) { setCounts(null); setState('error'); } }
    }
    void load();
    return () => { applicable = false; };
  }, [key, focused, retry]);
  return <View style={styles.screen}>
    <RootHeader title={i18n.t('directory')} unreadCount={unreadNotificationCount}
      onNotificationPress={() => navigation.navigate('Notifications')}
      onLogoutPress={() => void confirmLogout({ pendingSyncCount, requestConfirmation, signOut })} />
    <ScrollView contentContainerStyle={styles.content}>
    {state === 'loading' || counts && counts.key !== key ? <ActivityIndicator accessibilityLabel={i18n.t('loading')} /> : null}
    {state === 'assignment' ? <Text accessibilityRole="alert" style={styles.muted}>{i18n.t('assignmentUnavailable')}</Text> : null}
    {state === 'error' ? <><Text accessibilityRole="alert" style={styles.muted}>{i18n.t('savedRecordsError')}</Text>
      <ResidentAction label={i18n.t('retry')} onPress={() => setRetry(value => value + 1)} /></> : null}
    {state === 'ready' && counts?.key === key ? <>
      {assignment?.barangay?.name && assignment?.purok?.display_name ?
        <Text style={styles.scope}>{assignment.barangay.name} · {assignment.purok.display_name}</Text> : null}
      <Pressable accessibilityRole="button" accessibilityLabel={`${i18n.t('directoryResidents')}: ${counts.residents}`}
        style={[styles.action, styles.directoryRow]} onPress={() => navigation.navigate('Residents')}>
        <Text style={[styles.actionLabel, styles.directoryLabel]}>{i18n.t('directoryResidents')}</Text>
        <Text style={styles.directoryCount}>{counts.residents}</Text>
      </Pressable>
      <Pressable accessibilityRole="button" accessibilityLabel={`${i18n.t('directoryHouseholds')}: ${counts.households}`}
        style={[styles.action, styles.directoryRow, styles.followingRow]} onPress={() => navigation.navigate('Households')}>
        <Text style={[styles.actionLabel, styles.directoryLabel]}>{i18n.t('directoryHouseholds')}</Text>
        <Text style={styles.directoryCount}>{counts.households}</Text>
      </Pressable>
    </> : null}
    </ScrollView>
  </View>;
}

const directoryStyles = (theme: AppTheme) => StyleSheet.create({
  ...compactStyles(theme),
  content: { padding: 16, paddingBottom: 32, gap: 10 },
  muted: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 20 },
  scope: { color: theme.colors.textMuted, fontSize: 13, lineHeight: 19, fontWeight: '400' },
  directoryRow: { minHeight: 66, paddingVertical: 20, flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 12 },
  followingRow: { marginTop: 6 },
  directoryLabel: { flexGrow: 1, flexShrink: 1, flexBasis: '60%', minWidth: 0 },
  directoryCount: { color: theme.colors.textMuted, fontSize: 16, lineHeight: 22,
    flexShrink: 1, maxWidth: '100%', marginLeft: 'auto', textAlign: 'right' },
});
