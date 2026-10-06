import React, { useEffect, useRef, useState } from 'react';
import { usePreventRemove } from '@react-navigation/native';
import { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { FlatList, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { useKeyboardAwareScroll } from '../hooks/useKeyboardAwareScroll';
import { i18n } from '../i18n';
import { birthDateInputFromServer, dateInputFromPicker, datePickerValueFromInput, formatBirthDateInput, normalizeBirthDateInput } from '../lib/format';
import { findHouseholdByReference } from '../lib/householdIdentity';
import { getHouseholds, getResidentHouseholdOptions, getResidentRelationshipChoices, getResidentProfileChoices,
  getResidentByLocalId, getResidentRequestByLocalId, saveResident } from '../lib/storage';
import { createSaveGuard, residentEditBlocked, residentFormMode, residentRequestLabel, validateResidentStep,
  RESIDENT_EDITABLE_FIELDS, RESIDENT_PROFILE_FLAGS, RESIDENT_WIZARD_STEPS } from '../lib/residentWorkflow';
import { useLocalEditor } from '../lib/useLocalEditor';
import { AppTheme } from '../theme';
import { HouseholdRecord, ResidentRecord } from '../types';

const LABELS: Record<string, string> = {
  first_name: 'firstName', last_name: 'lastName', middle_name: 'middleName', suffix: 'suffix', philsys_card_no: 'philsysCardNumber',
  birth_date: 'birthDate', birth_place: 'birthPlace', sex: 'sex', relationship_to_head: 'relationshipToHead',
  civil_status: 'civilStatus', citizenship: 'citizenship', religion: 'religion', contact_number: 'contactNumber', email_address: 'emailAddress',
  occupation: 'residentOccupation', employment_status: 'residentEmployment', highest_education_level: 'residentEducationLevel',
  education_status: 'residentEducationStatus', is_pwd: 'residentPwd', disability_type: 'residentDisability', is_ofw: 'residentOfw',
  is_solo_parent: 'residentSoloParent', is_osy: 'residentOsy', is_osc: 'residentOsc', is_ip: 'residentIp', ethnicity: 'residentEthnicity',
};
const TITLES = ['residentStepIdentity', 'residentStepPersonal', 'residentStepEducation', 'residentStepSocio'];
function formValues(record?: ResidentRecord | null): Partial<ResidentRecord> {
  return Object.fromEntries(RESIDENT_EDITABLE_FIELDS.map(field => [field, field === 'birth_date'
    ? birthDateInputFromServer(record?.birth_date) : record ? record[field] ?? null :
      (RESIDENT_PROFILE_FLAGS as readonly string[]).includes(field) ? false :
      field === 'citizenship' ? 'Filipino' : field === 'employment_status' || field === 'education_status' ? 'N/A' :
      field === 'highest_education_level' ? 'None' : null]));
}

export function ResidentFormScreen({ route, navigation }: any) {
  useLocalEditor();
  const theme = useAppTheme(); const styles = useThemedStyles(createStyles);
  const { user, assignment, dataVersion, bumpDataVersion, requestConfirmation } = useAppContext();
  const { handleInputFocus, handleScroll, keyboardInset, scrollRef } = useKeyboardAwareScroll();
  const [values, setValues] = useState<Partial<ResidentRecord>>(() => formValues());
  const [existing, setExisting] = useState<ResidentRecord | null>(null);
  const [household, setHousehold] = useState<HouseholdRecord | null>(null);
  const [households, setHouseholds] = useState<HouseholdRecord[]>([]);
  const [relationships, setRelationships] = useState<string[]>([]);
  const [choices, setChoices] = useState<Record<string, string[]>>({});
  const [chooser, setChooser] = useState<string | null>(null); const [search, setSearch] = useState('');
  const [loadingHomes, setLoadingHomes] = useState(true); const [homesError, setHomesError] = useState(false);
  const [loaded, setLoaded] = useState(!route.params?.localId); const [step, setStep] = useState(0);
  const [review, setReview] = useState(false); const [saving, setSaving] = useState(false); const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null); const guard = useRef(createSaveGuard());
  const mode = residentFormMode(existing); const blocked = residentEditBlocked(existing);
  const fingerprint = JSON.stringify([values, household?.server_id ?? null, household?.mobile_uuid ?? null]);
  const original = useRef(fingerprint); const dirty = loaded && fingerprint !== original.current;
  const purokId = assignment?.purok?.id; const purokLabel = assignment?.purok?.display_name ?? i18n.t('assignedPurokOnly');
  usePreventRemove(dirty && !saved, ({ data }) => {
    void requestConfirmation({ title: i18n.t('residentLeaveTitle'), message: i18n.t('residentLeaveMessage'), confirmLabel: i18n.t('residentLeave') })
      .then(confirmed => { if (confirmed) navigation.dispatch(data.action); });
  });
  useEffect(() => { if (saved) navigation.goBack(); }, [saved, navigation]);
  useEffect(() => { navigation.setOptions({ title: i18n.t(mode === 'correction' ? 'requestResidentUpdate' : 'addResidentRequest') }); }, [mode, navigation]);
  useEffect(() => {
    let applicable = true;
    void Promise.all([getResidentRelationshipChoices(), getResidentProfileChoices()]).then(([relations, options]) => {
      if (applicable) { setRelationships(relations); setChoices(options); }
    }).catch(() => { if (applicable) setError(i18n.t('residentChoicesUnavailable')); });
    return () => { applicable = false; };
  }, [dataVersion]);
  useEffect(() => {
    let applicable = true; setLoadingHomes(true); setHomesError(false);
    void getResidentHouseholdOptions(mode, search, existing).then(rows => { if (applicable) setHouseholds(rows); })
      .catch(() => { if (applicable) setHomesError(true); }).finally(() => { if (applicable) setLoadingHomes(false); });
    return () => { applicable = false; };
  }, [purokId, dataVersion, mode, search, chooser, existing]);
  useEffect(() => {
    if (!route.params?.localId) return;
    let applicable = true;
    async function load() {
      try {
        const record = await getResidentByLocalId(route.params.localId) ?? await getResidentRequestByLocalId(route.params.localId);
        if (!record) { if (applicable) setError(i18n.t('residentProfileRefreshRequired')); return; }
        const home = findHouseholdByReference(await getHouseholds(), record) ?? null;
        if (!applicable) return;
        const initial = formValues(record); original.current = JSON.stringify([initial, home?.server_id ?? null, home?.mobile_uuid ?? null]);
        setExisting(record); setValues(initial); setHousehold(home); setLoaded(true);
      } catch { if (applicable) setError(i18n.t('savedRecordsError')); }
    }
    void load(); return () => { applicable = false; };
  }, [route.params?.localId]);
  function change(field: keyof ResidentRecord, value: string | boolean | null) {
    setValues(previous => ({ ...previous, [field]: value, ...(field === 'is_pwd' && value === false ? { disability_type: null } : {}) })); setError(null);
  }
  function validate(index: number) {
    if (!loaded || blocked || !purokId) return i18n.t('residentUnavailable');
    if (index === 0 && !household) return i18n.t('householdRequiredMessage');
    return validateResidentStep({ ...values, birth_date: normalizeBirthDateInput(values.birth_date) ?? undefined }, index, choices);
  }
  function next() {
    const message = validate(step); if (message) { setError(message); return; } setError(null);
    if (step === 3) setReview(true); else { setStep(step + 1); scrollRef.current?.scrollTo({ y: 0 }); }
  }
  async function save() {
    if (!guard.current.acquire()) return; setSaving(true);
    try {
      for (let index = 0; index < 4; index++) { const message = validate(index); if (message) { setStep(index); setReview(false); setError(message); return; } }
      await saveResident({ ...values, local_id: existing?.local_id, server_id: existing?.server_id ?? null,
        mobile_uuid: existing?.mobile_uuid, household_server_id: household!.server_id ?? null, household_mobile_uuid: household!.mobile_uuid ?? null,
        birth_date: normalizeBirthDateInput(values.birth_date)!, is_active: existing?.is_active ?? true,
        propose_household_head: mode === 'localRequest' && household!.server_id == null && Boolean(existing?.propose_household_head),
      } as Omit<ResidentRecord, 'sync_status'>, user?.id);
      bumpDataVersion(); setSaved(true);
    } catch { setError(i18n.t('residentSaveFailed')); } finally { setSaving(false); guard.current.release(); }
  }
  function input(field: keyof ResidentRecord) {
    if (field === 'disability_type' && !values.is_pwd) return null;
    const flag = (RESIDENT_PROFILE_FLAGS as readonly string[]).includes(field);
    const options = field === 'relationship_to_head' ? relationships : choices[field]; const label = i18n.t(LABELS[field]);
    return <View key={field}><Text style={styles.label}>{label}</Text>
      {flag || field === 'sex' ? <View style={styles.row}>{(flag ? [false, true] : ['Female', 'Male']).map(option =>
        <Pressable key={String(option)} accessibilityRole="button" accessibilityLabel={`${label}: ${flag ? i18n.t(option ? 'yes' : 'no') : option}`}
          accessibilityState={{ selected: values[field] === option }} disabled={blocked} onPress={() => change(field, option)}
          style={[styles.button, values[field] === option && styles.selected]}><Text style={styles.text}>{flag ? i18n.t(option ? 'yes' : 'no') : option}</Text></Pressable>)}
        {flag && values[field] == null ? <Text style={styles.helper}>{i18n.t('notRecorded')}</Text> : null}</View>
        : options || ['relationship_to_head', 'employment_status', 'highest_education_level', 'education_status'].includes(field) ? <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={() => setChooser(field)} disabled={blocked} style={styles.button}>
          <Text style={styles.text}>{String(values[field] ?? '') || i18n.t('notRecorded')}</Text></Pressable>
        : <TextInput accessibilityLabel={label} editable={!blocked} style={styles.input} value={String(values[field] ?? '')} onFocus={handleInputFocus}
          keyboardType={field === 'contact_number' ? 'phone-pad' : field === 'email_address' ? 'email-address' : 'default'} autoCapitalize={field === 'email_address' ? 'none' : 'sentences'}
          maxLength={field === 'birth_date' ? 10 : field === 'philsys_card_no' ? 50 : undefined} placeholder={field === 'birth_date' ? i18n.t('birthDatePlaceholder') : undefined}
          onChangeText={value => change(field, field === 'birth_date' ? formatBirthDateInput(value) : value || null)} />}
      {field === 'birth_date' ? <Pressable accessibilityRole="button" disabled={blocked} style={styles.button} onPress={() => DateTimePickerAndroid.open({
        value: datePickerValueFromInput(values.birth_date ?? ''), mode: 'date', maximumDate: new Date(),
        onChange: (event, date) => { if (event.type === 'set' && date) change('birth_date', dateInputFromPicker(date)); },
      })}><Text style={styles.text}>{i18n.t('openCalendar')}</Text></Pressable> : null}</View>;
  }
  const chooserOptions = chooser === 'relationship_to_head' ? existing?.relationship_to_head && !relationships.includes(existing.relationship_to_head)
    ? [existing.relationship_to_head, ...relationships] : relationships : choices[chooser ?? ''] ?? [];
  const closeChooser = () => setChooser(null);
  return <KeyboardShiftView style={styles.screen}><ScrollView ref={scrollRef} style={styles.screen}
    contentContainerStyle={[styles.content, { paddingBottom: theme.spacing.xl + keyboardInset }]} keyboardShouldPersistTaps="handled" onScroll={handleScroll} scrollEventThrottle={16}>
    <Text accessibilityRole="header" style={styles.title}>{i18n.t('residentStepProgress', { step: step + 1 })}: {i18n.t(TITLES[step])}</Text>
    <View style={styles.row}>{TITLES.map((title, index) => <View key={title} style={[styles.progress, index <= step && styles.selected]} />)}</View>
    {existing ? <Text style={styles.helper}>{residentRequestLabel(existing)}{existing.verification_notes ? `: ${existing.verification_notes}` : ''}</Text> : null}
    {blocked ? <Text style={styles.error}>{i18n.t('residentUnavailable')}</Text> : null}
    <View style={styles.card}>{step === 0 ? <><Text style={styles.label}>{i18n.t('chooseHousehold')}</Text>
      <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('chooseHousehold')} disabled={blocked} style={styles.button} onPress={() => setChooser('household')}>
        <Text style={styles.text}>{household?.household_no ?? i18n.t('chooseHousehold')}</Text></Pressable><Text style={styles.helper}>{purokLabel}</Text>
      {household ? <Text style={styles.helper}>{household.household_address}{'\n'}{household.server_id == null ? i18n.t('residentLegacyHousehold')
        : household.current_head_name ?? i18n.t(household.is_vacant ? 'vacantHousehold' : 'noDesignatedHead')}</Text> : null}
      {!loadingHomes && !homesError && !households.length && !search ? <Text style={styles.helper}>{i18n.t('residentHouseholdFirst')}</Text> : null}</> : null}
      {RESIDENT_WIZARD_STEPS[step].map(field => input(field))}</View>
    {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
    <View style={styles.row}>{step > 0 ? <Pressable accessibilityRole="button" style={styles.button} disabled={saving} onPress={() => { setStep(step - 1); setError(null); scrollRef.current?.scrollTo({ y: 0 }); }}>
      <Text style={styles.text}>{i18n.t('back')}</Text></Pressable> : null}
      <Pressable accessibilityRole="button" disabled={blocked || !loaded || saving} accessibilityState={{ disabled: blocked || !loaded || saving }} style={[styles.button, styles.selected]} onPress={next}>
        <Text style={styles.text}>{i18n.t(step === 3 ? 'residentReviewSave' : 'next')}</Text></Pressable></View>
  </ScrollView>
  <Modal visible={Boolean(chooser)} transparent animationType="none" onRequestClose={closeChooser}><Pressable style={styles.backdrop} onPress={closeChooser}>
    <Pressable accessibilityViewIsModal style={styles.modal} onPress={() => {}}><Text accessibilityRole="header" style={styles.title}>{chooser === 'household' ? i18n.t('chooseHousehold') : i18n.t(LABELS[chooser ?? ''])}</Text>
      {chooser === 'household' ? <><TextInput accessibilityLabel={i18n.t('residentHouseholdSearch')} style={styles.input} value={search} onChangeText={setSearch} placeholder={i18n.t('residentHouseholdSearch')} />
        {loadingHomes ? <Text style={styles.helper}>{i18n.t('loading')}</Text> : null}{homesError ? <Text accessibilityRole="alert" style={styles.error}>{i18n.t('savedRecordsError')}</Text> : null}
        <FlatList data={homesError || loadingHomes ? [] : households} keyboardShouldPersistTaps="handled" keyExtractor={item => String(item.local_id)}
          ListEmptyComponent={<Text style={styles.helper}>{loadingHomes || homesError ? '' : search ? i18n.t('noMatchingRecords') : i18n.t('residentHouseholdFirst')}</Text>}
          renderItem={({ item }) => <Pressable accessibilityRole="button" style={styles.button} onPress={() => { setHousehold(item); closeChooser(); setError(null); }}>
            <Text style={styles.text}>Household #{item.household_no}</Text><Text style={styles.helper}>{item.household_address}</Text></Pressable>} /></>
        : <FlatList data={chooserOptions} keyExtractor={item => item} renderItem={({ item }) => <Pressable accessibilityRole="button" style={styles.button}
          onPress={() => { change(chooser as keyof ResidentRecord, item); closeChooser(); }}><Text style={styles.text}>{item}</Text></Pressable>} />}
      <Pressable accessibilityRole="button" style={styles.button} onPress={closeChooser}><Text style={styles.text}>{i18n.t('cancel')}</Text></Pressable>
    </Pressable></Pressable></Modal>
  <Modal visible={review} transparent animationType="none" onRequestClose={() => { if (!saving) setReview(false); }}><Pressable style={styles.backdrop} onPress={() => { if (!saving) setReview(false); }}>
    <Pressable accessibilityViewIsModal style={styles.modal} onPress={() => {}}><ScrollView keyboardShouldPersistTaps="handled">
      <Text accessibilityRole="header" style={styles.title}>{i18n.t('residentReviewSave')}</Text><Text style={styles.helper}>{i18n.t('residentSaveDeviceNote')}</Text>
      <Text style={styles.text}>Household #{household?.household_no}{'\n'}{household?.household_address}{'\n'}{purokLabel}</Text>
      {TITLES.map((title, index) => <View key={title} style={styles.card}><Text accessibilityRole="header" style={styles.label}>{i18n.t(title)}</Text>
        {RESIDENT_WIZARD_STEPS[index].filter(field => field !== 'disability_type' || values.is_pwd).map(field => <Text key={field} style={styles.text}>{i18n.t(LABELS[field])}: {typeof values[field] === 'boolean'
          ? i18n.t(values[field] ? 'yes' : 'no') : values[field] || i18n.t('notRecorded')}</Text>)}</View>)}
      {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
      <Pressable accessibilityRole="button" disabled={saving} accessibilityState={{ disabled: saving }} style={[styles.button, styles.selected]} onPress={save}>
        <Text style={styles.text}>{i18n.t(saving ? 'residentSaving' : 'residentSaveDevice')}</Text></Pressable>
      <Pressable accessibilityRole="button" disabled={saving} style={styles.button} onPress={() => setReview(false)}><Text style={styles.text}>{i18n.t('back')}</Text></Pressable>
    </ScrollView></Pressable></Pressable></Modal></KeyboardShiftView>;
}
const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background }, content: { padding: theme.spacing.md, gap: theme.spacing.md },
  card: { padding: theme.spacing.md, gap: 10, backgroundColor: theme.colors.surface, borderRadius: theme.radius.lg, borderWidth: 1, borderColor: theme.colors.border },
  title: { color: theme.colors.text, fontSize: 19, fontWeight: '700' }, label: { color: theme.colors.text, fontWeight: '600', marginTop: 8, marginBottom: 6 },
  text: { color: theme.colors.text }, helper: { color: theme.colors.textMuted }, error: { color: theme.colors.danger },
  input: { minHeight: 48, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, padding: 12, color: theme.colors.text },
  button: { minHeight: 48, padding: 12, justifyContent: 'center', borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md },
  selected: { backgroundColor: theme.colors.primarySoft }, row: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  progress: { height: 5, flex: 1, backgroundColor: theme.colors.border, borderRadius: 3 },
  backdrop: { flex: 1, justifyContent: 'center', padding: 18, backgroundColor: 'rgba(15, 23, 42, 0.35)' },
  modal: { maxHeight: '85%', gap: 12, padding: 18, borderRadius: theme.radius.lg, backgroundColor: theme.colors.surface },
});
