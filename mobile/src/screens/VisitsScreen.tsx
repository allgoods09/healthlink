import { useIsFocused } from '@react-navigation/native';
import React, { useEffect, useRef, useState } from 'react';
import { FlatList, Keyboard, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { RootHeader } from '../components/RootHeader';
import { HouseholdAction, HouseholdState, householdUiStyles } from '../components/HouseholdUi';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { dateInputFromPicker, formatFriendlyDateTime } from '../lib/format';
import { confirmLogout } from '../lib/confirmLogout';
import { getVisits, getWaitingHouseholdVisits, hasHouseholdData } from '../lib/storage';
import { normalizeHouseholdQuery } from '../lib/householdPresentation';
import { FieldVisitRecord } from '../types';
import { AppTheme } from '../theme';

type WaitingVisit = Awaited<ReturnType<typeof getWaitingHouseholdVisits>>[number];
type DateRange = { from: Date | null; to: Date | null };
const emptyRange = (): DateRange => ({ from: null, to: null });
const calendarDay = (date: Date) => date.getFullYear() * 10000 + (date.getMonth() + 1) * 100 + date.getDate();

export function matchesVisitDateRange(value: string | null | undefined, range: DateRange) {
  if (!range.from && !range.to) return true;
  // Stored ISO/SQLite dates must be complete and real; never normalize an overflowed day.
  const parts = value?.match(/^(\d{4})-(\d{2})-(\d{2})(?:$|[T ])/);
  if (!parts) return false;
  const [, year, month, day] = parts.map(Number);
  const check = new Date(0);
  check.setUTCFullYear(year, month - 1, day);
  if (check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day) return false;
  // Match the existing displayed timestamp's device-local date, including its UTC offset.
  const timestamp = new Date(value!);
  if (Number.isNaN(timestamp.getTime())) return false;
  const visitDay = calendarDay(timestamp);
  return (!range.from || visitDay >= calendarDay(range.from)) && (!range.to || visitDay <= calendarDay(range.to));
}

export function VisitsScreen({ navigation }: any) {
  const styles = useThemedStyles(visitStyles); const theme = useAppTheme(); const focused = useIsFocused();
  const { user, assignment, dataVersion, unreadNotificationCount, pendingSyncCount, requestConfirmation, signOut } = useAppContext();
  const [search, setSearch] = useState(''); const [query, setQuery] = useState('');
  const [records, setRecords] = useState<FieldVisitRecord[]>([]); const [waiting, setWaiting] = useState<WaitingVisit[]>([]);
  const [state, setState] = useState('loading'); const [loadedKey, setLoadedKey] = useState(''); const [retry, setRetry] = useState(0);
  const [limit, setLimit] = useState(30); const [waitingLimit, setWaitingLimit] = useState(5);
  const [appliedRange, setAppliedRange] = useState<DateRange>(emptyRange);
  const [draftRange, setDraftRange] = useState<DateRange>(emptyRange);
  const [filterVisible, setFilterVisible] = useState(false);
  const [rangeError, setRangeError] = useState(false);
  const [pickerField, setPickerField] = useState<keyof DateRange | null>(null);
  const filterGeneration = useRef(0); const androidPickerOpen = useRef(false);
  const insets = useSafeAreaInsets();
  const key = `${user?.id}:${assignment?.barangay?.id}:${assignment?.purok?.id}:${dataVersion}:${retry}`;
  useEffect(() => { const timer = setTimeout(() => { setQuery(search); setLimit(30); setWaitingLimit(5); }, 300); return () => clearTimeout(timer); }, [search]);
  function closeFilter() {
    filterGeneration.current++;
    if (androidPickerOpen.current) { androidPickerOpen.current = false; void DateTimePickerAndroid.dismiss('date'); }
    setPickerField(null); setFilterVisible(false); setRangeError(false);
  }
  useEffect(() => {
    closeFilter();
    return () => {
      filterGeneration.current++;
      if (androidPickerOpen.current) { androidPickerOpen.current = false; void DateTimePickerAndroid.dismiss('date'); }
    };
  }, [key, focused]);
  function openFilter() {
    Keyboard.dismiss();
    filterGeneration.current++; setDraftRange({ ...appliedRange }); setRangeError(false); setFilterVisible(true);
  }
  function selectDate(field: keyof DateRange) {
    if (Platform.OS !== 'android') { setPickerField(field); return; }
    const generation = filterGeneration.current;
    androidPickerOpen.current = true;
    DateTimePickerAndroid.open({ value: draftRange[field] ?? new Date(), mode: 'date',
      onChange: (event, date) => {
        if (generation !== filterGeneration.current) return;
        androidPickerOpen.current = false;
        if (event.type === 'set' && date && !Number.isNaN(date.getTime())) {
          setDraftRange(current => ({ ...current, [field]: date })); setRangeError(false);
        }
      } });
  }
  function applyFilter() {
    if (draftRange.from && draftRange.to && calendarDay(draftRange.from) > calendarDay(draftRange.to)) {
      setRangeError(true); return;
    }
    setAppliedRange({ ...draftRange }); setLimit(30); setWaitingLimit(5); closeFilter();
  }
  function clearFilter() { setAppliedRange(emptyRange()); setDraftRange(emptyRange()); setLimit(30); setWaitingLimit(5); closeFilter(); }
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
  const pickerGeneration = filterGeneration.current;
  const filterActive = Boolean(appliedRange.from || appliedRange.to);
  const matches = (row: FieldVisitRecord) => normalizeHouseholdQuery(`${row.household_no ?? ''} ${row.notes ?? ''}`).includes(normalizeHouseholdQuery(query))
    && matchesVisitDateRange(row.visited_at, appliedRange);
  const history = visible ? records.filter(matches) : []; const retained = visible ? waiting.filter(matches) : [];
  return <KeyboardShiftView style={styles.screen}>
    <View style={styles.screen} accessibilityElementsHidden={filterVisible} importantForAccessibility={filterVisible ? 'no-hide-descendants' : 'auto'}>
    <RootHeader title={i18n.t('visits')} unreadCount={unreadNotificationCount}
      onNotificationPress={() => navigation.navigate('Notifications')}
      onLogoutPress={() => void confirmLogout({ pendingSyncCount, requestConfirmation, signOut })} />
    <FlatList data={history.slice(0, limit)} keyExtractor={item => String(item.local_id)} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}
      ListHeaderComponent={<View style={{ gap: theme.spacing.md }}>
        {visible ? <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('startVisitNow')}
          style={styles.newVisit} onPress={() => navigation.navigate('VisitForm')}>
          <Text style={styles.newVisitText}>{i18n.t('startVisitNow')}</Text>
        </Pressable> : null}
        <View style={styles.searchRow}>
          <TextInput accessibilityLabel={i18n.t('hhVisitSearch')} value={search} onChangeText={setSearch} placeholder={i18n.t('hhVisitSearch')}
            placeholderTextColor={theme.colors.placeholder} style={[styles.input, styles.searchInput]} />
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t(filterActive ? 'hhVisitFilterActive' : 'hhVisitFilter')}
            accessibilityState={{ selected: filterActive }} style={[styles.filterButton, filterActive && styles.filterActive]} onPress={openFilter}>
            <Ionicons accessible={false} name="filter-outline" size={22} color={filterActive ? theme.colors.primary : theme.colors.textMuted} />
            {filterActive ? <View accessible={false} style={styles.filterDot} /> : null}
          </Pressable>
        </View>
        {retained.length ? <View style={styles.retained}><Text accessibilityRole="header" style={styles.title}>{i18n.t('hhRetainedVisits')}</Text>
          {retained.slice(0, waitingLimit).map(visit => <View key={visit.local_id} style={styles.card}>
            <Text style={styles.recordTitle}>{visit.household_no}</Text>
            <Text style={styles.helper}>{i18n.t('hhVisitDateLabel')} {formatFriendlyDateTime(visit.visited_at) ?? visit.visited_at}</Text>
            <Text style={styles.helper}>{i18n.t('hhVisitBhwLabel')} {visit.recorded_by_name || i18n.t('hhRecorderUnknown')}</Text>
            <Text style={styles.statusText}>{i18n.t(visit.waiting_status === 'rejected' ? 'hhVisitRejected' : visit.waiting_status === 'approved' ? 'hhVisitApproved' : visit.waiting_status === 'protected' ? 'hhVisitProtected' : 'hhVisitWaiting')}</Text>
            {visit.verification_notes ? <Text style={styles.helper}>{visit.verification_notes}</Text> : null}
            <Text style={styles.helper}>{visit.notes ? visit.notes.slice(0, 180) : i18n.t('noNotesSaved')}</Text>
            <Text style={styles.helper}>{i18n.t('photoCountLabel', { count: visit.photos.length })}</Text>
          </View>)}
          {waitingLimit < retained.length ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setWaitingLimit(value => value + 5)} /> : null}
        </View> : null}
        <View style={styles.sectionRow}>
          <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t('visitHistoryTitle')}</Text>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('sync')} style={styles.inlineAction}
            onPress={() => navigation.navigate('SyncTab')}><Text style={styles.actionText}>{i18n.t('sync')}</Text></Pressable>
        </View>
      </View>}
      ListEmptyComponent={filterActive && retained.length ? null : <HouseholdState message={i18n.t(!focused || loadedKey !== key || state === 'loading' ? 'loading' : state === 'error' ? 'savedRecordsError' : state === 'refresh' ? 'hhRefresh' : filterActive && !records.length && !waiting.length ? 'noRecentVisits' : filterActive ? query.trim() ? 'hhVisitNoSearchDateMatches' : 'hhVisitNoDateMatches' : query.trim() ? 'noMatchingRecords' : 'noRecentVisits')}
        error={state === 'error'} retry={state === 'error' ? () => setRetry(value => value + 1) : undefined} />}
      ListFooterComponent={limit < history.length ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setLimit(value => value + 30)} /> : null}
      renderItem={({ item }) => <View style={styles.card}>
        <View style={styles.recordHeading}>
          <Text style={styles.recordTitle}>{item.household_no ?? i18n.t('noHouseholdNumber')}</Text>
          <Text style={styles.recordStatus}>{i18n.t(item.sync_status === 'synced' ? 'hhVisitSynced' : 'hhVisitPending')}</Text>
        </View>
        <Text style={styles.helper}>{i18n.t('hhVisitDateLabel')} {formatFriendlyDateTime(item.visited_at) ?? item.visited_at}</Text>
        <Text style={styles.helper}>{i18n.t('hhVisitBhwLabel')} {item.recorded_by_name || i18n.t('hhRecorderUnknown')}</Text>
        <Text style={styles.helper}>{item.notes ? item.notes.slice(0, 180) : i18n.t('noNotesSaved')}</Text>
        <View style={styles.recordFooter}>
          <Text style={styles.photoCount}>{i18n.t('photoCountLabel', { count: item.photos.length })}</Text>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('viewDetails')} style={styles.inlineAction}
            onPress={() => navigation.navigate('VisitForm', { localId: item.local_id })}>
            <Text style={styles.actionText}>{i18n.t('viewDetails')} {'>'}</Text>
          </Pressable>
        </View>
      </View>} />
    </View>
    {filterVisible ? <Modal transparent visible animationType="none" onRequestClose={closeFilter}>
      <View style={[styles.filterOverlay, { paddingTop: Math.max(16, insets.top), paddingBottom: Math.max(16, insets.bottom) }]}>
        <Pressable style={StyleSheet.absoluteFill} accessibilityRole="button" accessibilityLabel={i18n.t('cancel')} importantForAccessibility="no" onPress={closeFilter} />
        <View style={styles.filterPanel} accessibilityViewIsModal onAccessibilityEscape={closeFilter}>
          <View style={styles.sectionRow}>
            <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t('hhVisitFilter')}</Text>
            <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('close')} style={styles.inlineAction} onPress={closeFilter}>
              <Ionicons accessible={false} name="close-outline" size={22} color={theme.colors.text} />
            </Pressable>
          </View>
          <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.filterFields}>
            <Text style={styles.helper}>{i18n.t('hhVisitDateRange')}</Text>
            {(['from', 'to'] as const).map(field => <View key={field} style={styles.filterFields}>
              <Text style={styles.helper}>{i18n.t(field === 'from' ? 'hhVisitFrom' : 'hhVisitTo')}</Text>
              <Pressable accessibilityRole="button" accessibilityLabel={`${i18n.t(field === 'from' ? 'hhVisitFrom' : 'hhVisitTo')}: ${draftRange[field] ? dateInputFromPicker(draftRange[field]!) : i18n.t('hhVisitSelectDate')}`}
                style={styles.input} onPress={() => selectDate(field)}>
                <Text style={styles.dateText}>{draftRange[field] ? dateInputFromPicker(draftRange[field]!) : i18n.t('hhVisitSelectDate')}</Text>
              </Pressable>
            </View>)}
            {pickerField && Platform.OS === 'ios' ? <DateTimePicker value={draftRange[pickerField] ?? new Date()} mode="date" display="spinner"
              themeVariant={theme.mode} onChange={(event, date) => {
                if (filterVisible && pickerGeneration === filterGeneration.current && event.type === 'set' && date && !Number.isNaN(date.getTime())) {
                  setDraftRange(current => ({ ...current, [pickerField]: date })); setRangeError(false);
                }
              }} /> : null}
            {rangeError ? <Text accessibilityRole="alert" accessibilityLiveRegion="polite" style={styles.error}>{i18n.t('hhVisitInvalidRange')}</Text> : null}
          </ScrollView>
          <View style={styles.filterActions}>
            <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('clearFilters')} style={styles.filterAction} onPress={clearFilter}>
              <Text style={styles.actionText}>{i18n.t('clearFilters')}</Text>
            </Pressable>
            <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('hhVisitApply')} style={[styles.filterAction, styles.applyAction]} onPress={applyFilter}>
              <Text style={styles.newVisitText}>{i18n.t('hhVisitApply')}</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal> : null}
  </KeyboardShiftView>;
}

const visitStyles = (theme: AppTheme) => StyleSheet.create({
  ...householdUiStyles(theme),
  content: { padding: 16, paddingBottom: 32, gap: 12 },
  newVisit: { minHeight: 50, paddingHorizontal: 16, paddingVertical: 12, borderRadius: 6,
    backgroundColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center' },
  newVisitText: { color: theme.colors.textOnPrimary, fontSize: 16, lineHeight: 24, fontWeight: '600', textAlign: 'center' },
  input: { minHeight: 50, paddingHorizontal: 14, paddingVertical: 12, borderRadius: 6, borderWidth: 1,
    borderColor: theme.colors.border, backgroundColor: theme.colors.inputBackground, color: theme.colors.text, fontSize: 16 },
  card: { backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border,
    borderRadius: 8, padding: 14, gap: 6 },
  title: { color: theme.colors.text, fontSize: 17, lineHeight: 24, fontWeight: '600' },
  retained: { gap: 12 },
  sectionRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 },
  sectionTitle: { color: theme.colors.text, fontSize: 17, lineHeight: 24, fontWeight: '600', flexGrow: 1, flexShrink: 1 },
  recordHeading: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'flex-start', gap: 8 },
  recordTitle: { color: theme.colors.text, fontSize: 17, lineHeight: 24, fontWeight: '600', flexGrow: 1, flexShrink: 1, minWidth: 0 },
  recordStatus: { color: theme.colors.primary, fontSize: 13, lineHeight: 20, fontWeight: '600', flexShrink: 1, maxWidth: '100%' },
  helper: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 22, flexShrink: 1 },
  recordFooter: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 },
  photoCount: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 22, flexGrow: 1, flexShrink: 1 },
  inlineAction: { minHeight: 48, minWidth: 48, maxWidth: '100%', paddingHorizontal: 4,
    paddingVertical: 12, alignItems: 'center', justifyContent: 'center', marginLeft: 'auto' },
  actionText: { color: theme.colors.primary, fontSize: 14, lineHeight: 22, fontWeight: '600', flexShrink: 1 },
  searchRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  searchInput: { flex: 1, minWidth: 0 },
  filterButton: { width: 50, height: 50, borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface, alignItems: 'center', justifyContent: 'center' },
  filterActive: { borderColor: theme.colors.primary },
  filterDot: { position: 'absolute', top: 5, right: 5, width: 5, height: 5, borderRadius: 3, backgroundColor: theme.colors.primary },
  filterOverlay: { flex: 1, paddingHorizontal: 16, justifyContent: 'center', backgroundColor: theme.colors.overlay },
  filterPanel: { maxHeight: '100%', borderRadius: 8, padding: 16, gap: 12, backgroundColor: theme.colors.surface },
  filterFields: { gap: 8 },
  dateText: { color: theme.colors.text, fontSize: 16, lineHeight: 24 },
  filterActions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  filterAction: { flexGrow: 1, minHeight: 48, paddingHorizontal: 16, paddingVertical: 12, alignItems: 'center', justifyContent: 'center',
    borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface },
  applyAction: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primary },
});
