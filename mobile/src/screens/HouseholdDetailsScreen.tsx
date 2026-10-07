import React, { useEffect, useState } from 'react';
import { useIsFocused } from '@react-navigation/native';
import { ScrollView, Text, View } from 'react-native';
import { HouseholdAction, HouseholdOccupancy, HouseholdState, HouseholdStatus, householdUiStyles } from '../components/HouseholdUi';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDate, formatFriendlyDateTime, formatPurokLabel, formatResidentFormalName } from '../lib/format';
import { getHouseholdByLocalId, getHouseholdRequestByLocalId, getHouseholdLookupByLocalId, getResidentsForHousehold, getVisits, hasHouseholdData } from '../lib/storage';
import { householdCanEdit, householdFormMode, householdOccupancy, householdOfficialProfile, visitBelongsToHousehold } from '../lib/householdPresentation';
import { HouseholdRecord, ResidentRecord, FieldVisitRecord } from '../types';

export function HouseholdDetailsScreen({ route, navigation }: any) {
  const styles = useThemedStyles(householdUiStyles);
  const { user, assignment, dataVersion } = useAppContext(); const focused = useIsFocused();
  const [household, setHousehold] = useState<HouseholdRecord | null>(null);
  const [members, setMembers] = useState<ResidentRecord[]>([]); const [visits, setVisits] = useState<FieldVisitRecord[]>([]);
  const [state, setState] = useState('loading'); const [retry, setRetry] = useState(0); const [loadedKey, setLoadedKey] = useState('');
  const [memberLimit, setMemberLimit] = useState(30); const [visitLimit, setVisitLimit] = useState(5);
  const key = `${route.params?.localId}:${user?.id}:${assignment?.barangay?.id}:${assignment?.purok?.id}:${dataVersion}:${retry}`;
  useEffect(() => {
    if (!focused) return;
    let applicable = true; setState('loading'); setHousehold(null); setMembers([]); setVisits([]); setMemberLimit(30); setVisitLimit(5);
    void (async () => {
      try {
        if (!await hasHouseholdData({ userId: user?.id, barangayId: assignment?.barangay?.id, purokId: assignment?.purok?.id })) { if (applicable) { setState('refresh'); setLoadedKey(key); } return; }
        const id = route.params?.localId;
        const row = await getHouseholdByLocalId(id) ?? await getHouseholdRequestByLocalId(id) ?? await getHouseholdLookupByLocalId(id);
        const operational = row?.access_mode === 'operational';
        const [people, history] = row && operational ? await Promise.all([
          row.member_coverage === 'complete' ? getResidentsForHousehold(row) : Promise.resolve([]), getVisits(),
        ]) : [[], []];
        if (!applicable) return;
        setHousehold(row); setMembers(people); setVisits(row ? history.filter(visit => visitBelongsToHousehold(visit, row)) : []);
        setState(row ? 'ready' : 'unavailable'); setLoadedKey(key);
      } catch { if (applicable) { setState('error'); setLoadedKey(key); } }
    })();
    return () => { applicable = false; };
  }, [key, focused]);
  if (!focused || loadedKey !== key || state !== 'ready' || !household) return <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
    <HouseholdState message={i18n.t(!focused || loadedKey !== key || state === 'loading' ? 'loading' : state === 'error' ? 'savedRecordsError' : state === 'refresh' ? 'hhRefresh' : 'hhUnavailable')}
      error={state === 'error'} retry={state === 'error' ? () => setRetry(value => value + 1) : undefined} />
  </ScrollView>;
  const operational = household.access_mode === 'operational'; const lookup = household.access_mode === 'lookup';
  const mode = householdFormMode(household); const occupancy = householdOccupancy(household);
  const profile = householdOfficialProfile(household);
  const hasProposal = operational && ['household_no', 'household_address', 'is_social_aid_beneficiary'].some(field =>
    household[field as keyof HouseholdRecord] !== profile[field as keyof HouseholdRecord]);
  const canEdit = household.purok_id === assignment?.purok?.id && householdCanEdit(household);
  return <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
    <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.title}>{profile.household_no}</Text>
      <Text style={styles.text}>{profile.household_address}</Text>
      <Text style={styles.helper}>{formatPurokLabel(household.purok_display_name, household.purok_id, i18n.t('purokNotAvailable'))}</Text>
      <HouseholdStatus row={household} />
      {lookup ? <Text style={styles.helper}>{i18n.t('hhLookupHint')}</Text> : null}
      {operational || lookup ? <Text style={styles.helper}>{i18n.t('hhAvailability')}: {i18n.t(household.is_active ? 'active' : 'inactive')}</Text> : null}
      {!lookup ? <Text style={styles.text}>{i18n.t('socialAid')}: {i18n.t(profile.is_social_aid_beneficiary === true ? 'yes' : 'no')}</Text> : null}
      {operational && household.sync_status !== 'synced' ? <Text style={styles.helper}>{i18n.t('hhCorrectionHint')}</Text> : null}
    </View>
    {hasProposal ? <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('hhProposedValues')}</Text>
      <Text style={styles.text}>{household.household_no}</Text><Text style={styles.text}>{household.household_address}</Text>
      <Text style={styles.text}>{i18n.t('socialAid')}: {i18n.t(household.is_social_aid_beneficiary ? 'yes' : 'no')}</Text>
      <Text style={styles.helper}>{i18n.t('hhCorrectionHint')}</Text>
    </View> : null}
    {operational ? <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('hhCurrentStatus')}</Text><HouseholdOccupancy row={household} />
    </View> : !lookup ? <HouseholdState message={i18n.t('hhOccupancy_unknown')} /> : null}
    {operational && occupancy !== 'unknown' ? <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('hhCurrentMembers')}</Text>
      {members.slice(0, memberLimit).map(member => <View key={member.local_id}>
        <Text style={styles.text}>{formatResidentFormalName(member)}</Text>
        <Text style={styles.helper}>{member.relationship_to_head} - {formatFriendlyDate(member.birth_date) ?? member.birth_date}</Text>
      </View>)}
      {!members.length ? <Text style={styles.helper}>{i18n.t(household.current_member_count === 0 ? 'noHouseholdMembers' : 'hhRefresh')}</Text> : null}
      {memberLimit < members.length ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setMemberLimit(value => value + 30)} /> : null}
    </View> : null}
    {operational ? <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('hhRecentVisits')}</Text>
      {visits.slice(0, visitLimit).map(visit => <View key={visit.local_id}>
        <Text style={styles.text}>{formatFriendlyDateTime(visit.visited_at) ?? visit.visited_at}</Text>
        <Text style={styles.helper}>{visit.recorded_by_name ? i18n.t('hhRecorder', { name: visit.recorded_by_name }) : i18n.t('hhRecorderUnknown')}</Text>
        <Text style={styles.helper}>{visit.notes ? visit.notes.slice(0, 180) : i18n.t('noNotesSaved')}</Text>
        <Text style={styles.helper}>{i18n.t('photoCountLabel', { count: visit.photos.length })}</Text>
        <HouseholdAction label={i18n.t('viewDetails')} onPress={() => navigation.navigate('VisitForm', { localId: visit.local_id })} />
      </View>)}
      {!visits.length ? <Text style={styles.helper}>{i18n.t('noRecentVisits')}</Text> : null}
      {visitLimit < visits.length ? <HouseholdAction label={i18n.t('hhShowMore')} onPress={() => setVisitLimit(value => value + 5)} /> : null}
    </View> : null}
    <View style={styles.actions}>
      {canEdit ? <HouseholdAction label={i18n.t(mode === 'unsent' ? 'hhEditRequest' : 'hhRequestUpdate')}
        onPress={() => navigation.navigate('HouseholdForm', { localId: household.local_id })} /> : null}
      {operational ? <HouseholdAction primary label={i18n.t('createVisit')} onPress={() => navigation.navigate('VisitForm', { householdLocalId: household.local_id })} /> : null}
      <HouseholdAction label={i18n.t('back')} onPress={() => navigation.goBack()} />
    </View>
  </ScrollView>;
}
