import React, { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { ResidentAction, residentStyles } from '../components/ResidentUi';
import { i18n } from '../i18n';
import { formatFriendlyDate, formatPurokLabel, formatResidentFormalName } from '../lib/format';
import { getResidentByLocalId, getResidentRequestByLocalId, getCurrentOfficialHouseholds, getRiskAssessmentsForResident } from '../lib/storage';
import { residentDetailActions, residentDirectoryAge, residentStatusKey } from '../lib/residentPresentation';
import { residentEditBlocked } from '../lib/residentWorkflow';
import { RESIDENT_PROFILE_FIELDS, RESIDENT_PROFILE_FLAGS } from '../lib/residentWorkflow';
import { HouseholdRecord, ResidentRecord, RiskAssessmentRecord } from '../types';

export function ResidentDetailsScreen({ route, navigation }: any) {
  const styles = useThemedStyles(residentStyles);
  const focused = useIsFocused();
  const { assignment, dataVersion, isOnline } = useAppContext();
  const [resident, setResident] = useState<ResidentRecord | null>(null);
  const [home, setHome] = useState<HouseholdRecord | null>(null);
  const [draft, setDraft] = useState<RiskAssessmentRecord | null>(null);
  const [state, setState] = useState('loading');
  const [loadedKey, setLoadedKey] = useState('');
  const [retry, setRetry] = useState(0);
  const key = JSON.stringify([route.params, assignment, dataVersion, retry]);
  useEffect(() => {
    if (!focused) return;
    let applicable = true;
    setResident(null); setState('loading'); setDraft(null); setHome(null);
    async function load() {
      try {
        const loadScopedRecord = async () => route.params?.request
          ? await getResidentRequestByLocalId(route.params?.localId) ?? await getResidentByLocalId(route.params?.localId)
          : await getResidentByLocalId(route.params?.localId);
        const record = await loadScopedRecord();
        if (!record) { if (applicable) setState('unavailable'); return; }
        const [homes, assessments] = await Promise.all([getCurrentOfficialHouseholds(), record.server_id
          ? getRiskAssessmentsForResident(record.server_id) : Promise.resolve([])]);
        const stillAvailable = await loadScopedRecord();
        if (!stillAvailable) { if (applicable) setState('unavailable'); return; }
        if (!applicable) return;
        setResident(record); setHome(homes.find(h => h.server_id === record.household_server_id) ?? null);
        setDraft(assessments.find(a => a.sync_status !== 'synced') ?? null);
        setState('ready'); setLoadedKey(key);
      } catch { if (applicable) setState('error'); }
    }
    void load();
    return () => { applicable = false; };
  }, [key, focused]);

  if (!resident || loadedKey !== key) return <View style={[styles.screen, styles.content]}>
    {state === 'loading' ? <ActivityIndicator accessibilityLabel={i18n.t('loading')} />
      : <Text accessibilityRole="alert" style={styles.muted}>{i18n.t(state === 'error' ? 'savedRecordsError' : 'residentUnavailable')}</Text>}
    {state === 'error' ? <ResidentAction label={i18n.t('retry')} onPress={() => setRetry(value => value + 1)} /> : null}
  </View>;

  const age = residentDirectoryAge(resident.birth_date);
  const actions = residentDetailActions(resident, assignment?.purok?.id, age);
  const name = formatResidentFormalName(resident);
  const purok = formatPurokLabel(resident.household_purok_display_name, resident.household_purok_id);
  const value = (text?: string | null): string => text?.trim() || String(i18n.t('notRecorded'));
  return <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
    <Text accessibilityRole="header" style={styles.title}>{name}</Text>
    <Text style={styles.muted}>{i18n.t(resident.server_id ? 'officialResident' : 'newResidentRequest')} · {purok}</Text>
    {!isOnline ? <Text style={styles.muted}>{i18n.t('cachedResidentNote')}</Text> : null}
    <View style={styles.row}>
      {actions.update ? <ResidentAction primary label={i18n.t('requestResidentUpdate')}
        onPress={() => navigation.navigate('ResidentForm', { localId: resident.local_id })} /> : null}
      {resident.server_id && resident.verification_status === 'submitted' && residentEditBlocked(resident)
        ? <ResidentAction disabled label={i18n.t('updateUnderReview')} onPress={() => {}} /> : null}
      {actions.assess ? <ResidentAction label={i18n.t(draft ? 'continueAssessment' : 'assessPhilpen')}
        onPress={() => navigation.navigate('RiskAssessmentForm', { residentLocalId: resident.local_id,
          riskAssessmentLocalId: draft?.local_id })} /> : null}
      {!resident.server_id && !residentEditBlocked(resident) && resident.sync_status !== 'synced'
        ? <ResidentAction label={i18n.t('editSavedRequest')} onPress={() => navigation.navigate('ResidentForm', { localId: resident.local_id })} /> : null}
    </View>
    {resident.server_id && !actions.assess ? <Text style={styles.muted}>{i18n.t('assessmentAgeNote')}</Text> : null}
    <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.section}>{i18n.t('identitySection')}</Text>
      <DetailRow label={i18n.t('birthDate')} value={value(formatFriendlyDate(resident.birth_date))} />
      <DetailRow label={i18n.t('age')} value={age == null ? i18n.t('unknownAge') : String(age)} />
      <DetailRow label={i18n.t('birthPlace')} value={value(resident.birth_place)} />
      <DetailRow label={i18n.t('sex')} value={resident.sex} />
      {resident.philsys_card_no ? <DetailRow label={i18n.t('philsysCardNumber')} value={resident.philsys_card_no} /> : null}
    </View>
    <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.section}>{i18n.t('householdProfile')}</Text>
      <DetailRow label={i18n.t('householdNo')} value={value(resident.household_no)} />
      <DetailRow label={i18n.t('sourcePurok')} value={purok} />
      <DetailRow label={i18n.t('relationshipToHead')} value={value(resident.relationship_to_head)} />
      {home ? <DetailRow label={i18n.t('householdHead')} value={home.current_head_name ?? i18n.t(home.is_vacant ? 'vacantHousehold' : 'noDesignatedHead')} /> : null}
    </View>
    <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.section}>{i18n.t('contactSection')}</Text>
      <DetailRow label={i18n.t('contactNumber')} value={value(resident.contact_number)} />
      <DetailRow label={i18n.t('emailAddress')} value={value(resident.email_address)} />
    </View>
    <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.section}>{i18n.t('demographicsSection')}</Text>
      <DetailRow label={i18n.t('civilStatus')} value={value(resident.civil_status)} />
      <DetailRow label={i18n.t('citizenship')} value={value(resident.citizenship)} />
      <DetailRow label={i18n.t('religion')} value={value(resident.religion)} />
    </View>
    {['residentStepEducation', 'residentStepSocio'].map((title, index) => <View key={title} style={styles.card}>
      <Text accessibilityRole="header" style={styles.section}>{i18n.t(title)}</Text>
      {(index === 0 ? RESIDENT_PROFILE_FIELDS.slice(0, 4) : RESIDENT_PROFILE_FIELDS.slice(4)).every(field => resident[field] == null)
        ? <Text style={styles.muted}>{i18n.t('notRecorded')}</Text> : null}
      {(index === 0 ? RESIDENT_PROFILE_FIELDS.slice(0, 4) : RESIDENT_PROFILE_FIELDS.slice(4)).filter(field =>
        resident[field] != null && resident[field] !== '' && (field !== 'disability_type' || resident.is_pwd)).map(field => {
          const labels: Record<string, string> = { occupation: 'residentOccupation', employment_status: 'residentEmployment',
            highest_education_level: 'residentEducationLevel', education_status: 'residentEducationStatus', is_pwd: 'residentPwd',
            disability_type: 'residentDisability', is_ofw: 'residentOfw', is_solo_parent: 'residentSoloParent',
            is_osy: 'residentOsy', is_osc: 'residentOsc', is_ip: 'residentIp', ethnicity: 'residentEthnicity' };
          return <DetailRow key={field} label={i18n.t(labels[field])} value={(RESIDENT_PROFILE_FLAGS as readonly string[]).includes(field)
            ? resident[field] == null ? i18n.t('notRecorded') : i18n.t(resident[field] ? 'yes' : 'no') : value(resident[field] as string | null)} />;
        })}
    </View>)}
    <View style={styles.card}>
      <Text accessibilityRole="header" style={styles.section}>{i18n.t('requestStatus')}</Text>
      <Text style={styles.text}>{resident.server_id && resident.verification_status === 'approved' && resident.sync_status === 'synced'
        && !resident.verification_notes ? i18n.t('noOpenResidentUpdate') : i18n.t(residentStatusKey(resident))}</Text>
      {resident.verification_notes ? <Text style={styles.text}>{i18n.t('reviewReason')}: {resident.verification_notes}</Text> : null}
      <ResidentAction label={i18n.t('residentRequests')} onPress={() => navigation.navigate('ResidentRequests')} />
    </View>
  </ScrollView>;
}

function DetailRow({ label, value }: { label: string; value: string }) {
  const styles = useThemedStyles(residentStyles);
  return <View><Text style={styles.muted}>{label}</Text><Text style={styles.text}>{value}</Text></View>;
}
