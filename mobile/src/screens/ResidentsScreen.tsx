import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Text, TextInput, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { ResidentAction, ResidentSheet, residentStyles } from '../components/ResidentUi';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { i18n } from '../i18n';
import { formatPurokLabel, formatResidentFormalName } from '../lib/format';
import { CurrentResidentCriteria, getCurrentOfficialResidentsPage, getCurrentOfficialHouseholds,
  getCurrentOfficialResidentCount, getResidentWorkflowItems, hasBootstrapData, ResidentContinuation } from '../lib/storage';
import { HouseholdRecord, ResidentRecord } from '../types';
import { isOpenResidentWorkflowItem, residentDirectoryAge } from '../lib/residentPresentation';

export function ResidentsScreen({ navigation }: any) {
  const { assignment, dataVersion, isOnline } = useAppContext();
  const focused = useIsFocused();
  const styles = useThemedStyles(residentStyles);
  const theme = useAppTheme();
  const [search, setSearch] = useState('');
  const [debounced, setDebounced] = useState('');
  const [filters, setFilters] = useState<CurrentResidentCriteria>({});
  const [sort, setSort] = useState<CurrentResidentCriteria['sort']>('nameAsc');
  const [sheet, setSheet] = useState<'filter' | 'sort' | null>(null);
  const [homes, setHomes] = useState<HouseholdRecord[]>([]);
  const [rows, setRows] = useState<ResidentRecord[]>([]);
  const [next, setNext] = useState<ResidentContinuation | null>(null);
  const [total, setTotal] = useState(0);
  const [officialCount, setOfficialCount] = useState(0);
  const [requests, setRequests] = useState(0);
  const [state, setState] = useState('loading');
  const [pageError, setPageError] = useState(false);
  const [pageBusy, setPageBusy] = useState(false);
  const [retry, setRetry] = useState(0);
  const [loadedKey, setLoadedKey] = useState('');
  const generation = useRef(0);
  const paging = useRef<number | null>(null);
  const today = new Date();
  const asOf = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  const criteria = { ...filters, search: debounced, sort, asOf };
  const searchPending = search.trim().replace(/\s+/g, ' ') !== debounced;
  const key = JSON.stringify([criteria, search.trim().replace(/\s+/g, ' '), assignment, dataVersion, retry]);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(search.trim().replace(/\s+/g, ' ')), 275);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    if (!focused) return;
    const token = ++generation.current;
    paging.current = null;
    setState('loading'); setRows([]); setNext(null); setPageBusy(false); setPageError(false);
    if (searchPending) return () => { generation.current++; };
    async function load() {
      try {
        if (!await hasBootstrapData()) { if (token === generation.current) setState('assignment'); return; }
        const [page, options, count, workflow] = await Promise.all([getCurrentOfficialResidentsPage(criteria),
          getCurrentOfficialHouseholds(), getCurrentOfficialResidentCount(), getResidentWorkflowItems()]);
        if (token !== generation.current) return;
        if (page.invalidated) { setState('assignment'); return; }
        setRows(page.rows); setNext(page.next); setTotal(page.total); setHomes(options); setOfficialCount(count);
        setRequests(workflow.filter(isOpenResidentWorkflowItem).length); setState('ready'); setLoadedKey(key);
      } catch { if (token === generation.current) setState('error'); }
    }
    void load();
    return () => { generation.current++; };
  }, [key, focused]);

  async function loadMore() {
    if (!next || paging.current !== null || state !== 'ready' || loadedKey !== key || searchPending) return;
    const token = generation.current;
    paging.current = token; setPageBusy(true); setPageError(false);
    try {
      const page = await getCurrentOfficialResidentsPage(criteria, next);
      if (token !== generation.current) return;
      if (page.invalidated) { setRetry(value => value + 1); return; }
      setRows(current => {
        const existing = new Set(current.map(row => row.local_id));
        return [...current, ...page.rows.filter(row => !existing.has(row.local_id))];
      });
      setNext(page.next);
    } catch { if (token === generation.current) setPageError(true); }
    finally { if (paging.current === token) { paging.current = null; setPageBusy(false); } }
  }

  function changeSearch(value: string) {
    // Invalidate immediately, before debounce: even an old page completing now cannot append.
    if (value.trim().replace(/\s+/g, ' ') !== debounced) generation.current++;
    setSearch(value);
  }
  const filterLabels = [filters.sex, filters.ageGroup, filters.householdLocalId
    ? `${i18n.t('householdNo')}: ${homes.find(home => home.local_id === filters.householdLocalId)?.household_no ?? ''}` : null].filter(Boolean);
  const sortKeys = ['nameAsc', 'nameDesc', 'youngest', 'oldest', 'household'] as const;
  const waiting = state === 'loading' || state === 'ready' && (loadedKey !== key || searchPending);

  return <KeyboardShiftView style={styles.screen}>
    <View style={styles.content}>
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('directoryResidents')}</Text>
      <ResidentAction primary label={i18n.t('addResidentRequest')} onPress={() => navigation.navigate('ResidentForm')} />
      <TextInput value={search} onChangeText={changeSearch} placeholder={i18n.t('searchResidents')}
        placeholderTextColor={theme.colors.placeholder} accessibilityLabel={i18n.t('searchResidents')}
        accessibilityHint={i18n.t('residentSearchHint')} autoCorrect={false} style={styles.search} />
      <View style={styles.row}>
        <ResidentAction label={`${i18n.t('filter')}${filterLabels.length ? ` (${filterLabels.length})` : ''}`}
          selected={filterLabels.length > 0} onPress={() => setSheet('filter')} />
        <ResidentAction label={`${i18n.t('sort')}: ${i18n.t(sort ?? 'nameAsc')}`}
          selected={sort !== 'nameAsc'} onPress={() => setSheet('sort')} />
        <ResidentAction label={`${i18n.t('residentRequests')} (${requests})`} onPress={() => navigation.navigate('ResidentRequests')} />
      </View>
      {filterLabels.length ? <Text style={styles.muted}>{i18n.t('activeFilters')}: {filterLabels.join(' · ')}</Text> : null}
      {!isOnline ? <Text style={styles.muted}>{i18n.t('cachedResidentNote')}</Text> : null}
      {state === 'ready' && !waiting ? <Text accessibilityLiveRegion="polite" style={styles.muted}>{total} {i18n.t('matchingResidents')}</Text> : null}
    </View>
    <FlatList data={!waiting && loadedKey === key ? rows : []} keyExtractor={item => String(item.local_id)}
      contentContainerStyle={styles.content} onEndReached={() => { void loadMore(); }} onEndReachedThreshold={0.5}
      keyboardShouldPersistTaps="handled" accessibilityState={{ busy: waiting }}
      renderItem={({ item }) => <View style={styles.card}>
        <Text accessibilityRole="header" style={styles.section}>{formatResidentFormalName(item)}</Text>
        <Text style={styles.text}>{item.sex} · {residentDirectoryAge(item.birth_date) ?? i18n.t('unknownAge')}</Text>
        <Text style={styles.muted}>{formatPurokLabel(item.household_purok_display_name, item.household_purok_id)}</Text>
        <Text style={styles.muted}>{item.household_no ? `${i18n.t('householdNo')}: ${item.household_no}` : i18n.t('noHouseholdNumber')}</Text>
        {item.verification_status === 'submitted' ? <Text style={styles.muted}>{i18n.t('updateUnderReview')}</Text> : null}
        <ResidentAction label={i18n.t('view')} accessibilityLabel={`${i18n.t('view')}: ${formatResidentFormalName(item)}`}
          onPress={() => navigation.navigate('ResidentDetails', { localId: item.local_id })} />
      </View>}
      ListEmptyComponent={<View style={styles.stack}>
        {waiting ? <ActivityIndicator accessibilityLabel={i18n.t('loading')} /> : <Text
          accessibilityRole={state === 'error' || state === 'assignment' ? 'alert' : 'text'} style={styles.muted}>
          {i18n.t(state === 'error' ? 'savedRecordsError' : state === 'assignment' ? 'assignmentUnavailable'
            : officialCount === 0 ? 'noAssignedResidents' : 'noResidentMatches')}</Text>}
        {state === 'error' ? <ResidentAction label={i18n.t('retry')} onPress={() => setRetry(value => value + 1)} /> : null}
      </View>}
      ListFooterComponent={<View>{pageBusy ? <ActivityIndicator accessibilityLabel={i18n.t('loading')} /> : null}
        {pageError ? <><Text accessibilityRole="alert" style={styles.muted}>{i18n.t('savedRecordsError')}</Text>
          <ResidentAction label={i18n.t('retry')} onPress={() => { void loadMore(); }} /></> : null}</View>} />
    <ResidentSheet visible={sheet === 'filter'} title={i18n.t('filter')} close={() => setSheet(null)}>
      <Text style={styles.section}>{i18n.t('sex')}</Text>
      {(['Male', 'Female'] as const).map(sex => <ResidentAction key={sex} label={sex} selected={filters.sex === sex}
        onPress={() => setFilters(current => ({ ...current, sex: current.sex === sex ? undefined : sex }))} />)}
      <Text style={styles.section}>{i18n.t('ageGroup')}</Text>
      {(['0-5', '6-12', '13-17', '18-59', '60+'] as const).map(ageGroup => <ResidentAction key={ageGroup}
        label={ageGroup} selected={filters.ageGroup === ageGroup}
        onPress={() => setFilters(current => ({ ...current, ageGroup: current.ageGroup === ageGroup ? undefined : ageGroup }))} />)}
      <Text style={styles.section}>{i18n.t('householdNo')}</Text>
      {homes.map(home => <ResidentAction key={home.local_id} label={`${home.household_no} · ${home.current_head_name ?? i18n.t('noDesignatedHead')}`}
        selected={filters.householdLocalId === home.local_id}
        onPress={() => setFilters(current => ({ ...current, householdLocalId: current.householdLocalId === home.local_id ? undefined : home.local_id }))} />)}
      <ResidentAction label={i18n.t('clearFilters')} onPress={() => setFilters({})} />
    </ResidentSheet>
    <ResidentSheet visible={sheet === 'sort'} title={i18n.t('sort')} close={() => setSheet(null)}>
      {sortKeys.map(value => <ResidentAction key={value} label={i18n.t(value)} selected={sort === value}
        onPress={() => { setSort(value); setSheet(null); }} />)}
    </ResidentSheet>
  </KeyboardShiftView>;
}
