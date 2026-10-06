import React, { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { ResidentAction, residentStyles } from '../components/ResidentUi';
import { i18n } from '../i18n';
import { formatFriendlyDateTime, formatResidentFormalName } from '../lib/format';
import { getResidentWorkflowItems, hasBootstrapData } from '../lib/storage';
import { residentStatusKey } from '../lib/residentPresentation';
import { residentEditBlocked } from '../lib/residentWorkflow';
import { ResidentRecord } from '../types';

export function ResidentRequestsScreen({ navigation }: any) {
  const { assignment, dataVersion } = useAppContext();
  const focused = useIsFocused();
  const styles = useThemedStyles(residentStyles);
  const [items, setItems] = useState<ResidentRecord[]>([]);
  const [state, setState] = useState('loading');
  const [retry, setRetry] = useState(0);
  const [loadedKey, setLoadedKey] = useState('');
  const key = JSON.stringify([assignment, dataVersion, retry]);
  useEffect(() => {
    if (!focused) return;
    let applicable = true;
    setState('loading'); setItems([]);
    async function load() {
      try {
        if (!await hasBootstrapData()) { if (applicable) setState('assignment'); return; }
        const rows = await getResidentWorkflowItems();
        if (!await hasBootstrapData()) { if (applicable) setState('assignment'); return; }
        if (applicable) { setItems(rows); setState('ready'); setLoadedKey(key); }
      } catch { if (applicable) setState('error'); }
    }
    void load();
    return () => { applicable = false; };
  }, [key, focused]);
  return <View style={styles.screen}>
    <View style={styles.content}>
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('residentRequests')}</Text>
      <Text style={styles.muted}>{i18n.t('requestsContext')}</Text>
    </View>
    <FlatList data={loadedKey === key ? items : []} keyExtractor={item => String(item.local_id)}
      contentContainerStyle={styles.content} renderItem={({ item }) => <View style={styles.card}>
        <Text style={styles.section}>{formatResidentFormalName(item)}</Text>
        <Text style={styles.text}>{i18n.t(item.server_id ? 'residentUpdateRequest' : 'newResidentRequest')}</Text>
        <Text style={styles.text}>{i18n.t(residentStatusKey(item))}</Text>
        <Text style={styles.muted}>{i18n.t('householdNo')}: {item.household_no ?? i18n.t('noHouseholdNumber')}</Text>
        {item.verification_notes ? <Text style={styles.text}>{i18n.t('reviewReason')}: {item.verification_notes}</Text> : null}
        {formatFriendlyDateTime(item.updated_at) ? <Text style={styles.muted}>{i18n.t('lastSavedRecord')}: {formatFriendlyDateTime(item.updated_at)}</Text> : null}
        <ResidentAction label={i18n.t('viewStatus')} onPress={() => navigation.navigate('ResidentDetails', { localId: item.local_id,
          request: item.server_id == null })} />
        {!residentEditBlocked(item) && item.sync_status !== 'synced' ? <ResidentAction label={i18n.t('editSavedRequest')}
          onPress={() => navigation.navigate('ResidentForm', { localId: item.local_id })} /> : null}
      </View>}
      ListEmptyComponent={<View style={styles.stack}>{state === 'loading' ? <ActivityIndicator accessibilityLabel={i18n.t('loading')} />
        : <Text accessibilityRole={state === 'error' || state === 'assignment' ? 'alert' : 'text'} style={styles.muted}>
          {i18n.t(state === 'error' ? 'savedRecordsError' : state === 'assignment' ? 'assignmentUnavailable' : 'noResidentRequests')}</Text>}
        {state === 'error' ? <ResidentAction label={i18n.t('retry')} onPress={() => setRetry(value => value + 1)} /> : null}</View>} />
  </View>;
}
