import React, { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { MenuCard } from '../components/MenuCard';
import { TopHeader } from '../components/TopHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { getCurrentOfficialResidentCount, getCurrentOfficialHouseholdCount, hasBootstrapData } from '../lib/storage';
import { residentStyles, ResidentAction } from '../components/ResidentUi';

export function DirectoryScreen({ navigation }: any) {
  const { assignment, dataVersion, isOnline } = useAppContext();
  const focused = useIsFocused();
  const styles = useThemedStyles(residentStyles);
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
    <TopHeader title={i18n.t('directory')} actionAccessibilityLabel={i18n.t('sync')}
      onActionPress={() => navigation.navigate('SyncTab')} />
    <ScrollView contentContainerStyle={styles.content}>
    <Text style={styles.muted}>{assignment?.purok?.display_name ?? i18n.t('assignmentUnavailable')}</Text>
    {!isOnline ? <Text style={styles.muted}>{i18n.t('cachedResidentNote')}</Text> : null}
    {state === 'loading' || counts && counts.key !== key ? <ActivityIndicator accessibilityLabel={i18n.t('loading')} /> : null}
    {state === 'assignment' ? <Text accessibilityRole="alert" style={styles.muted}>{i18n.t('assignmentUnavailable')}</Text> : null}
    {state === 'error' ? <><Text accessibilityRole="alert" style={styles.muted}>{i18n.t('savedRecordsError')}</Text>
      <ResidentAction label={i18n.t('retry')} onPress={() => setRetry(value => value + 1)} /></> : null}
    {state === 'ready' && counts?.key === key ? <>
      <MenuCard title={i18n.t('directoryResidents')} badge={String(counts.residents)} subtitle={i18n.t('viewResidentRecords')}
        icon="people-outline" onPress={() => navigation.navigate('Residents')} />
      <MenuCard title={i18n.t('directoryHouseholds')} badge={String(counts.households)} subtitle={i18n.t('viewHouseholdRecords')}
        icon="home-outline" onPress={() => navigation.navigate('Households')} />
    </> : null}
    </ScrollView>
  </View>;
}
