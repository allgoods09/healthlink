import React, { useEffect, useState } from 'react';
import { FlatList, Pressable, Text, TextInput, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { HouseholdAction, HouseholdOccupancy, HouseholdState, HouseholdStatus, householdUiStyles } from '../components/HouseholdUi';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatPurokLabel } from '../lib/format';
import { getHouseholds, getHouseholdRequests, getHouseholdLookup, hasHouseholdData } from '../lib/storage';
import { HouseholdDirectoryMode, householdOfficialProfile, householdPage, HOUSEHOLD_PAGE_SIZE } from '../lib/householdPresentation';
import { HouseholdRecord } from '../types';

const labels = { operational: 'hhOfficial', request: 'hhRequests', lookup: 'hhLookup' };
const hints = { operational: 'hhOfficialHint', request: 'hhRequestHint', lookup: 'hhLookupHint' };
const readers = { operational: getHouseholds, request: getHouseholdRequests, lookup: getHouseholdLookup };

export function HouseholdDirectoryScreen({ navigation }: any) {
  const { user, assignment, dataVersion } = useAppContext();
  const isFocused = useIsFocused();
  const theme = useAppTheme(); const styles = useThemedStyles(householdUiStyles);
  const [mode, setMode] = useState<HouseholdDirectoryMode>('operational');
  const [search, setSearch] = useState(''); const [query, setQuery] = useState('');
  const [limit, setLimit] = useState(HOUSEHOLD_PAGE_SIZE);
  const [records, setRecords] = useState<HouseholdRecord[]>([]);
  const [state, setState] = useState('loading'); const [retry, setRetry] = useState(0);
  const scope = `${user?.id}:${assignment?.barangay?.id}:${assignment?.purok?.id}`;
  const key = `${scope}:${dataVersion}:${mode}:${retry}`;
  const [loadedKey, setLoadedKey] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => { setQuery(search); setLimit(HOUSEHOLD_PAGE_SIZE); }, 300);
    return () => clearTimeout(timer);
  }, [search]);
  useEffect(() => {
    if (!isFocused) return;
    let applicable = true; setState('loading'); setRecords([]); setLimit(HOUSEHOLD_PAGE_SIZE);
    void (async () => {
      try {
        const compatible = await hasHouseholdData({ userId: user?.id, barangayId: assignment?.barangay?.id, purokId: assignment?.purok?.id });
        const rows = compatible ? await readers[mode]() : [];
        if (applicable) { setRecords(rows); setState(compatible ? 'ready' : 'refresh'); setLoadedKey(key); }
      } catch { if (applicable) { setState('error'); setLoadedKey(key); } }
    })();
    return () => { applicable = false; };
  }, [key, isFocused, mode]);
  const visible = isFocused && loadedKey === key;
  const page = householdPage(visible && state === 'ready' ? records : [], mode, query, limit);
  return <KeyboardShiftView style={styles.screen}>
    <FlatList data={page.rows} keyExtractor={row => String(row.local_id)} keyboardShouldPersistTaps="handled"
      contentContainerStyle={styles.content}
      ListHeaderComponent={<View style={{ gap: theme.spacing.md }}>
        <View style={styles.actions}>{(Object.keys(labels) as HouseholdDirectoryMode[]).map(value =>
          <Pressable key={value} accessibilityRole="tab" accessibilityLabel={i18n.t(labels[value])} accessibilityState={{ selected: mode === value }}
            onPress={() => { setMode(value); setSearch(''); setQuery(''); }} style={[styles.action, mode === value && styles.primary]}>
            <Text style={[styles.actionText, mode === value && styles.primaryText]}>{i18n.t(labels[value])}</Text>
          </Pressable>)}</View>
        <Text accessibilityRole="header" style={styles.title}>{i18n.t(labels[mode])}</Text>
        <Text style={styles.helper}>{i18n.t(hints[mode])}</Text>
        {mode !== 'lookup' && state === 'ready' && visible ? <HouseholdAction label={i18n.t('hhNew')} onPress={() => navigation.navigate('HouseholdForm')} /> : null}
        <TextInput accessibilityLabel={i18n.t('hhSearch')} value={search} onChangeText={setSearch}
          placeholder={i18n.t(mode === 'lookup' ? 'hhLookupSearchHint' : mode === 'request' ? 'hhSearch' : 'hhSearchHint')}
          placeholderTextColor={theme.colors.placeholder} style={styles.input} />
        {state === 'ready' && visible ? <Text accessibilityLiveRegion="polite" style={styles.helper}>{i18n.t('hhCount', { count: page.total })}</Text> : null}
      </View>}
      ListEmptyComponent={state === 'error' && visible ? <HouseholdState error message={i18n.t('savedRecordsError')} retry={() => setRetry(value => value + 1)} /> :
        <HouseholdState message={i18n.t(!visible || state === 'loading' ? 'loading' : state === 'refresh' ? 'hhRefresh' : query.trim() ? 'hhNoMatches' : `hhEmpty_${mode}`)} />}
      ListFooterComponent={page.rows.length < page.total ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setLimit(value => value + HOUSEHOLD_PAGE_SIZE)} /> : null}
      renderItem={({ item }) => <View style={styles.card}>
        <Text style={styles.title}>{householdOfficialProfile(item).household_no}</Text><Text style={styles.text}>{householdOfficialProfile(item).household_address}</Text>
        <Text style={styles.helper}>{formatPurokLabel(item.purok_display_name, item.purok_id, i18n.t('purokNotAvailable'))}</Text>
        {mode === 'operational' ? <HouseholdOccupancy row={item} /> : null}
        {mode === 'request' || item.sync_status !== 'synced' || item.verification_status === 'submitted' || item.verification_status === 'rejected' ? <HouseholdStatus row={item} /> : null}
        {mode !== 'request' ? <Text style={styles.helper}>{i18n.t('hhAvailability')}: {i18n.t(item.is_active ? 'active' : 'inactive')}</Text> : null}
        {mode === 'request' && item.updated_at ? <Text style={styles.helper}>{item.updated_at}</Text> : null}
        <HouseholdAction label={i18n.t('viewDetails')} onPress={() => navigation.navigate('HouseholdDetails', { localId: item.local_id })} />
      </View>} />
  </KeyboardShiftView>;
}
