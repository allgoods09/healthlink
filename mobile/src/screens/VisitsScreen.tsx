import { useIsFocused } from '@react-navigation/native';
import React, { useEffect, useState } from 'react';
import { FlatList, Text, TextInput, View } from 'react-native';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { TopHeader } from '../components/TopHeader';
import { HouseholdAction, HouseholdState, householdUiStyles } from '../components/HouseholdUi';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { getVisits, getWaitingHouseholdVisits, hasHouseholdData } from '../lib/storage';
import { normalizeHouseholdQuery } from '../lib/householdPresentation';
import { FieldVisitRecord } from '../types';

type WaitingVisit = Awaited<ReturnType<typeof getWaitingHouseholdVisits>>[number];

export function VisitsScreen({ navigation }: any) {
  const styles = useThemedStyles(householdUiStyles); const theme = useAppTheme(); const focused = useIsFocused();
  const { user, assignment, dataVersion } = useAppContext();
  const [search, setSearch] = useState(''); const [query, setQuery] = useState('');
  const [records, setRecords] = useState<FieldVisitRecord[]>([]); const [waiting, setWaiting] = useState<WaitingVisit[]>([]);
  const [state, setState] = useState('loading'); const [loadedKey, setLoadedKey] = useState(''); const [retry, setRetry] = useState(0);
  const [limit, setLimit] = useState(30); const [waitingLimit, setWaitingLimit] = useState(5);
  const key = `${user?.id}:${assignment?.barangay?.id}:${assignment?.purok?.id}:${dataVersion}:${retry}`;
  useEffect(() => { const timer = setTimeout(() => { setQuery(search); setLimit(30); setWaitingLimit(5); }, 300); return () => clearTimeout(timer); }, [search]);
  useEffect(() => {
    if (!focused) return;
    let applicable = true; setState('loading'); setRecords([]); setWaiting([]);
    void (async () => {
      try {
        const compatible = await hasHouseholdData({ userId: user?.id, barangayId: assignment?.barangay?.id, purokId: assignment?.purok?.id });
        const [history, retained] = compatible ? await Promise.all([getVisits(), getWaitingHouseholdVisits()]) : [[], []];
        if (applicable) { setRecords(history); setWaiting(retained); setState(compatible ? 'ready' : 'refresh'); setLoadedKey(key); }
      } catch { if (applicable) { setState('error'); setLoadedKey(key); } }
    })();
    return () => { applicable = false; };
  }, [key, focused]);
  const visible = focused && loadedKey === key && state === 'ready';
  const matches = (row: FieldVisitRecord) => normalizeHouseholdQuery(`${row.household_no ?? ''} ${row.notes ?? ''}`).includes(normalizeHouseholdQuery(query));
  const history = visible ? records.filter(matches) : []; const retained = visible ? waiting.filter(matches) : [];
  return <KeyboardShiftView style={styles.screen}>
    <TopHeader title={i18n.t('visits')} onActionPress={() => navigation.navigate('SyncTab')} />
    <FlatList data={history.slice(0, limit)} keyExtractor={item => String(item.local_id)} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}
      ListHeaderComponent={<View style={{ gap: theme.spacing.md }}>
        {visible ? <HouseholdAction primary label={i18n.t('startVisitNow')} onPress={() => navigation.navigate('VisitForm')} /> : null}
        <TextInput accessibilityLabel={i18n.t('hhVisitSearch')} value={search} onChangeText={setSearch} placeholder={i18n.t('hhVisitSearch')}
          placeholderTextColor={theme.colors.placeholder} style={styles.input} />
        {retained.length ? <View><Text accessibilityRole="header" style={styles.title}>{i18n.t('hhRetainedVisits')}</Text>
          {retained.slice(0, waitingLimit).map(visit => <View key={visit.local_id} style={styles.card}>
            <Text style={styles.text}>{visit.household_no}</Text><Text style={styles.helper}>{formatFriendlyDateTime(visit.visited_at) ?? visit.visited_at}</Text>
            <Text style={styles.statusText}>{i18n.t(visit.waiting_status === 'rejected' ? 'hhVisitRejected' : visit.waiting_status === 'approved' ? 'hhVisitApproved' : visit.waiting_status === 'protected' ? 'hhVisitProtected' : 'hhVisitWaiting')}</Text>
            {visit.verification_notes ? <Text style={styles.helper}>{visit.verification_notes}</Text> : null}
            <Text style={styles.helper}>{visit.notes ? visit.notes.slice(0, 180) : i18n.t('noNotesSaved')}</Text>
            <Text style={styles.helper}>{i18n.t('photoCountLabel', { count: visit.photos.length })}</Text>
          </View>)}
          {waitingLimit < retained.length ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setWaitingLimit(value => value + 5)} /> : null}
        </View> : null}
        <Text accessibilityRole="header" style={styles.title}>{i18n.t('visitHistoryTitle')}</Text>
      </View>}
      ListEmptyComponent={<HouseholdState message={i18n.t(!focused || loadedKey !== key || state === 'loading' ? 'loading' : state === 'error' ? 'savedRecordsError' : state === 'refresh' ? 'hhRefresh' : query.trim() ? 'noMatchingRecords' : 'noRecentVisits')}
        error={state === 'error'} retry={state === 'error' ? () => setRetry(value => value + 1) : undefined} />}
      ListFooterComponent={limit < history.length ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setLimit(value => value + 30)} /> : null}
      renderItem={({ item }) => <View style={styles.card}>
        <Text style={styles.title}>{item.household_no ?? i18n.t('noHouseholdNumber')}</Text>
        <Text style={styles.statusText}>{i18n.t(item.sync_status === 'synced' ? 'hhVisitSynced' : 'hhVisitPending')}</Text>
        <Text style={styles.text}>{formatFriendlyDateTime(item.visited_at) ?? item.visited_at}</Text>
        <Text style={styles.helper}>{item.recorded_by_name ? i18n.t('hhRecorder', { name: item.recorded_by_name }) : i18n.t('hhRecorderUnknown')}</Text>
        <Text style={styles.helper}>{item.notes ? item.notes.slice(0, 180) : i18n.t('noNotesSaved')}</Text>
        <Text style={styles.helper}>{i18n.t('photoCountLabel', { count: item.photos.length })}</Text>
        <HouseholdAction label={i18n.t('viewDetails')} onPress={() => navigation.navigate('VisitForm', { localId: item.local_id })} />
      </View>} />
  </KeyboardShiftView>;
}
