import React, { useEffect, useRef, useState } from 'react';
import { usePreventRemove } from '@react-navigation/native';
import { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import {
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';

import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { useKeyboardAwareScroll } from '../hooks/useKeyboardAwareScroll';
import { i18n } from '../i18n';
import {
  birthDateInputFromServer,
  dateInputFromPicker,
  datePickerValueFromInput,
  formatBirthDateInput,
  normalizeBirthDateInput,
} from '../lib/format';
import { findHouseholdByReference } from '../lib/householdIdentity';
import {
  getHouseholds,
  getResidentHouseholdOptions,
  getResidentRelationshipChoices,
  getResidentByLocalId,
  getResidentRequestByLocalId,
  saveResident,
} from '../lib/storage';
import { AppTheme } from '../theme';
import { HouseholdRecord, ResidentRecord } from '../types';
import { createSaveGuard, residentEditBlocked, residentFormMode, residentRequestLabel, validateResidentInput } from '../lib/residentWorkflow';

import { useLocalEditor } from '../lib/useLocalEditor';

export function ResidentFormScreen({ route, navigation }: any) {
  useLocalEditor();
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const { user, assignment, dataVersion, bumpDataVersion, requestConfirmation } = useAppContext();
  const { handleInputFocus, handleScroll, keyboardInset, scrollRef } =
    useKeyboardAwareScroll();
  const [households, setHouseholds] = useState<HouseholdRecord[]>([]);
  const [chooserVisible, setChooserVisible] = useState(false);
  const [householdSearch, setHouseholdSearch] = useState('');
  const [householdsLoading, setHouseholdsLoading] = useState(true);
  const [householdsError, setHouseholdsError] = useState(false);
  const [existingRecord, setExistingRecord] = useState<ResidentRecord | null>(null);
  const [relationships, setRelationships] = useState<string[]>([]);
  const [relationshipChooserVisible, setRelationshipChooserVisible] = useState(false);
  const [proposeHead, setProposeHead] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const saveGuard = useRef(createSaveGuard());
  const [dirty, setDirty] = useState(false);
  const [loaded, setLoaded] = useState(!route.params?.localId);
  const mode = residentFormMode(existingRecord);
  const blocked = residentEditBlocked(existingRecord);
  const [selectedHousehold, setSelectedHousehold] = useState<HouseholdRecord | null>(null);
  const [localId, setLocalId] = useState<number | null>(null);
  const [serverId, setServerId] = useState<number | null>(null);
  const [mobileUuid, setMobileUuid] = useState<string | null>(null);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [suffix, setSuffix] = useState('');
  const [birthDate, setBirthDate] = useState('');
  const [birthPlace, setBirthPlace] = useState('');
  const [sex, setSex] = useState<'Male' | 'Female' | ''>('');
  const [civilStatus, setCivilStatus] = useState('');
  const [citizenship, setCitizenship] = useState('Filipino');
  const [religion, setReligion] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [emailAddress, setEmailAddress] = useState('');
  const [relationshipToHead, setRelationshipToHead] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const assignedPurokId = assignment?.purok?.id ?? null;
  const assignedPurokLabel =
    assignment?.purok?.display_name ?? i18n.t('assignedPurokOnly');
  const fingerprint = JSON.stringify([firstName, lastName, middleName, suffix, birthDate, birthPlace, sex,
    civilStatus, citizenship, religion, contactNumber, emailAddress, relationshipToHead,
    selectedHousehold?.server_id ?? null, selectedHousehold?.mobile_uuid ?? null, proposeHead]);
  const originalFingerprint = useRef(fingerprint);
  useEffect(() => { if (loaded) setDirty(fingerprint !== originalFingerprint.current); }, [fingerprint, loaded]);

  usePreventRemove(dirty && !saved, ({ data }) => {
    void requestConfirmation({ title: 'Leave resident request?', message: 'Unsaved changes will be lost.', confirmLabel: 'Leave' })
      .then(confirmed => { if (confirmed) navigation.dispatch(data.action); });
  });
  useEffect(() => { if (saved) navigation.goBack(); }, [saved, navigation]);
  useEffect(() => { navigation.setOptions({ title: mode === 'correction' ? 'Request Update' : 'Add Resident Request' }); }, [mode, navigation]);
  useEffect(() => {
    let applicable = true;
    void getResidentRelationshipChoices().then(values => { if (applicable) setRelationships(values); })
      .catch(() => { if (applicable) setFormError('Unable to load relationship choices. Reopen this form to retry.'); });
    return () => { applicable = false; };
  }, [dataVersion]);

  useEffect(() => {
    let applicable = true;
    async function loadWritableHouseholds() {
      setHouseholdsLoading(true);
      setHouseholdsError(false);
      try {
        const records = await getResidentHouseholdOptions(mode, householdSearch);
        if (applicable) setHouseholds(records);
      } catch { if (applicable) setHouseholdsError(true); }
      finally { if (applicable) setHouseholdsLoading(false); }
    }

    void loadWritableHouseholds();
    return () => { applicable = false; };
  }, [assignedPurokId, dataVersion, mode, householdSearch, chooserVisible]);

  useEffect(() => {
    const id = route.params?.createdHouseholdLocalId;
    if (!id || mode === 'correction') return;
    void getResidentHouseholdOptions(mode).then(records => {
      const created = records.find(h => h.local_id === id);
      if (created) { setSelectedHousehold(created); setDirty(true); }
      else setFormError('The new household is not available. Choose an eligible household.');
      navigation.setParams({ createdHouseholdLocalId: undefined });
    });
  }, [route.params?.createdHouseholdLocalId, mode, assignedPurokId]);

  useEffect(() => {
    async function loadExisting() {
      if (!route.params?.localId) return;

      const existing = await getResidentByLocalId(route.params.localId) ?? await getResidentRequestByLocalId(route.params.localId);

      if (!existing) {
        setFormError(i18n.t('noMatchingRecords'));
        return;
      }

      setExistingRecord(existing);
      originalFingerprint.current = JSON.stringify([existing.first_name, existing.last_name, existing.middle_name ?? '',
        existing.suffix ?? '', birthDateInputFromServer(existing.birth_date), existing.birth_place, existing.sex,
        existing.civil_status, existing.citizenship, existing.religion ?? '', existing.contact_number ?? '',
        existing.email_address ?? '', existing.relationship_to_head, existing.household_server_id ?? null,
        existing.household_mobile_uuid ?? null, existing.propose_household_head ?? false]);

      setLocalId(existing.local_id ?? null);
      setServerId(existing.server_id ?? null);
      setMobileUuid(existing.mobile_uuid ?? null);
      setFirstName(existing.first_name);
      setLastName(existing.last_name);
      setMiddleName(existing.middle_name ?? '');
      setSuffix(existing.suffix ?? '');
      setBirthDate(birthDateInputFromServer(existing.birth_date));
      setBirthPlace(existing.birth_place);
      setSex(existing.sex);
      setCivilStatus(existing.civil_status);
      setCitizenship(existing.citizenship);
      setReligion(existing.religion ?? '');
      setContactNumber(existing.contact_number ?? '');
      setEmailAddress(existing.email_address ?? '');
      setRelationshipToHead(existing.relationship_to_head);
      setProposeHead(existing.propose_household_head ?? false);
      setLoaded(true);

      const existingHousehold = findHouseholdByReference(await getHouseholds(), existing);

      if (existingHousehold) {
        setSelectedHousehold(existingHousehold);
      }
    }

    void loadExisting();
  }, [route.params?.localId]);

  function openBirthDatePicker() {
    DateTimePickerAndroid.open({
      value: datePickerValueFromInput(birthDate),
      mode: 'date',
      maximumDate: new Date(),
      onChange: (event, selectedDate) => {
        if (event.type === 'set' && selectedDate) {
          setBirthDate(dateInputFromPicker(selectedDate));
          setFormError(null);
        }
      },
    });
  }

  async function handleSave() {
    if (!saveGuard.current.acquire()) return;
    setSaving(true);
    try {
    if (!loaded || blocked) {
      setFormError('This request cannot be edited while under review or after rejection.');
      return;
    }
    if (!assignedPurokId || (route.params?.localId && !localId)) {
      setFormError(i18n.t('noMatchingRecords'));
      return;
    }
    const normalizedBirthDate = normalizeBirthDateInput(birthDate);

    if (!selectedHousehold) {
      setFormError(i18n.t('householdRequiredMessage'));
      return;
    }

    if (!firstName.trim() || !lastName.trim() || !birthPlace.trim()) {
      setFormError(i18n.t('residentRequiredMessage'));
      return;
    }

    if (!normalizedBirthDate) {
      setFormError(i18n.t('invalidBirthDate'));
      return;
    }
    const validation = validateResidentInput({ first_name: firstName, last_name: lastName, middle_name: middleName,
      suffix, birth_date: normalizedBirthDate, birth_place: birthPlace, sex: sex || undefined,
      civil_status: civilStatus, citizenship, religion, contact_number: contactNumber, email_address: emailAddress,
      relationship_to_head: relationshipToHead });
    if (validation) { setFormError(validation); return; }

    setFormError(null);

    const confirmed = await requestConfirmation({
      title: i18n.t('saveResidentConfirmationTitle'),
      message: i18n.t('saveResidentConfirmationBody'),
      confirmLabel: i18n.t('save'),
    });

    if (!confirmed) {
      return;
    }

    await saveResident({
      local_id: localId ?? undefined,
      server_id: serverId,
      mobile_uuid: mobileUuid,
      household_server_id: selectedHousehold.server_id ?? null,
      household_mobile_uuid: selectedHousehold.mobile_uuid ?? null,
      last_name: lastName,
      first_name: firstName,
      middle_name: middleName || null,
      suffix: suffix || null,
      birth_date: normalizedBirthDate,
      birth_place: birthPlace,
      sex: sex as 'Male' | 'Female',
      civil_status: civilStatus,
      citizenship,
      religion: religion || null,
      contact_number: contactNumber || null,
      email_address: emailAddress || null,
      relationship_to_head: relationshipToHead,
      is_active: existingRecord?.is_active ?? true,
      propose_household_head: mode !== 'correction' && selectedHousehold.server_id == null && proposeHead,
    }, user?.id);
    bumpDataVersion();
    setSaved(true);
    } catch (error) {
      setFormError(error instanceof Error ? error.message : 'Unable to save. Your entered information remains here; try again.');
    } finally { setSaving(false); saveGuard.current.release(); }
  }

  const relationshipOptions = existingRecord?.relationship_to_head && !relationships.includes(existingRecord.relationship_to_head)
    ? [existingRecord.relationship_to_head, ...relationships] : relationships;
  function createHousehold() {
    setChooserVisible(false);
    navigation.navigate('HouseholdForm', { returnToResident: true });
  }

  return (
    <KeyboardShiftView style={styles.screen}>
      <ScrollView
        ref={scrollRef}
        style={styles.screen}
        contentContainerStyle={[
          styles.content,
          { paddingBottom: theme.spacing.xl + keyboardInset },
        ]}
        keyboardShouldPersistTaps="handled"
        onScroll={handleScroll}
        scrollEventThrottle={16}
      >
        <View style={styles.card}>
          {existingRecord ? <Text style={styles.helperText}>{residentRequestLabel(existingRecord)}{existingRecord.verification_notes ? `: ${existingRecord.verification_notes}` : ''}</Text> : null}
          {blocked ? <Text style={styles.errorText}>{existingRecord?.server_id == null ? 'This rejection is preserved. Linked resubmission is not yet available.' : 'Update under review. Wait for the Secretary decision.'}</Text> : null}
          <Text style={styles.label}>{i18n.t('chooseHousehold')}</Text>
          <Pressable onPress={() => setChooserVisible(true)} style={styles.pickerButton}>
            <Text style={styles.pickerLabel}>
              {selectedHousehold?.household_no ?? i18n.t('chooseHousehold')}
            </Text>
          </Pressable>
          <Text style={styles.helperText}>
            {i18n.t('chooseHouseholdAssigned', { purok: assignedPurokLabel })}
          </Text>

          {mode !== 'correction' ? <Pressable onPress={createHousehold} style={styles.secondaryButton}>
            <Text style={styles.secondaryButtonText}>Create Household Request</Text>
          </Pressable> : null}

        <Text style={styles.label}>{i18n.t('firstName')}</Text>
        <TextInput value={firstName} onFocus={handleInputFocus} onChangeText={(value) => {
          setFirstName(value);
          if (formError) setFormError(null);
        }} style={styles.input} />

        <Text style={styles.label}>{i18n.t('lastName')}</Text>
        <TextInput value={lastName} onFocus={handleInputFocus} onChangeText={(value) => {
          setLastName(value);
          if (formError) setFormError(null);
        }} style={styles.input} />

        <Text style={styles.label}>{i18n.t('middleName')}</Text>
        <TextInput
          value={middleName}
          onChangeText={setMiddleName}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('suffix')}</Text>
        <TextInput
          value={suffix}
          onChangeText={setSuffix}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('birthDate')}</Text>
        <View style={styles.dateRow}>
          <TextInput
            value={birthDate}
            onChangeText={(value) => {
              setBirthDate(formatBirthDateInput(value));
              if (formError) setFormError(null);
            }}
            onFocus={handleInputFocus}
            style={[styles.input, styles.dateInput]}
            placeholder={i18n.t('birthDatePlaceholder')}
            placeholderTextColor={theme.colors.placeholder}
            maxLength={10}
          />
          <Pressable onPress={openBirthDatePicker} style={styles.calendarButton}>
            <Text style={styles.calendarButtonText}>{i18n.t('openCalendar')}</Text>
          </Pressable>
        </View>
        <Text style={styles.helperText}>{i18n.t('birthDateHelper')}</Text>

        <Text style={styles.label}>{i18n.t('birthPlace')}</Text>
        <TextInput value={birthPlace} onFocus={handleInputFocus} onChangeText={(value) => {
          setBirthPlace(value);
          if (formError) setFormError(null);
        }} style={styles.input} />

        <Text style={styles.label}>{i18n.t('sex')}</Text>
        <View style={styles.segmentRow}>
          {(['Female', 'Male'] as const).map((option) => (
            <Pressable
              key={option}
              onPress={() => setSex(option)}
              style={[styles.segment, sex === option && styles.segmentActive]}
            >
              <Text style={[styles.segmentText, sex === option && styles.segmentTextActive]}>
                {option}
              </Text>
            </Pressable>
          ))}
        </View>

        <Text style={styles.label}>{i18n.t('civilStatus')}</Text>
        <TextInput
          value={civilStatus}
          onChangeText={setCivilStatus}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('citizenship')}</Text>
        <TextInput
          value={citizenship}
          onChangeText={setCitizenship}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('religion')}</Text>
        <TextInput
          value={religion}
          onChangeText={setReligion}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('contactNumber')}</Text>
        <TextInput
          value={contactNumber}
          keyboardType="phone-pad"
          onChangeText={setContactNumber}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('emailAddress')}</Text>
        <TextInput
          value={emailAddress}
          keyboardType="email-address"
          autoCapitalize="none"
          onChangeText={setEmailAddress}
          onFocus={handleInputFocus}
          style={styles.input}
        />

        <Text style={styles.label}>{i18n.t('relationshipToHead')}</Text>
        <Pressable onPress={() => setRelationshipChooserVisible(true)} style={styles.pickerButton}>
          <Text style={styles.pickerLabel}>{relationshipToHead || 'Choose relationship'}</Text>
        </Pressable>
        {!relationships.length ? <Text style={styles.helperText}>Sync to download current relationship choices. Existing values remain preserved.</Text> : null}

        {mode !== 'correction' && selectedHousehold?.server_id == null && selectedHousehold ? <View style={styles.switchRow}>
          <Text style={styles.switchLabel}>Propose as household head</Text>
          <Switch
            value={proposeHead}
            onValueChange={value => { setProposeHead(value); setDirty(true); }}
            trackColor={{ false: theme.colors.inactiveSoft, true: theme.colors.primary }}
            thumbColor={theme.colors.surfaceElevated}
          />
        </View> : null}

          {formError ? (
            <View style={styles.errorBox}>
              <Text style={styles.errorText}>{formError}</Text>
            </View>
          ) : null}
        </View>

        <Pressable disabled={saving || blocked || !loaded} onPress={handleSave} style={[styles.primaryButton, (saving || blocked || !loaded) && { opacity: 0.5 }]}>
          <Text style={styles.primaryButtonText}>{saving ? 'Saving...' : i18n.t('save')}</Text>
        </Pressable>

        <Modal visible={relationshipChooserVisible} transparent animationType="slide" onRequestClose={() => setRelationshipChooserVisible(false)}>
          <View style={styles.modalBackdrop}><View style={styles.modalCard}>
            <FlatList data={relationshipOptions} keyExtractor={item => item} renderItem={({ item }) =>
              <Pressable style={styles.modalItem} onPress={() => { setRelationshipToHead(item); setDirty(true); setRelationshipChooserVisible(false); }}><Text style={styles.modalItemTitle}>{item}</Text></Pressable>} />
            <Pressable style={styles.secondaryButton} onPress={() => setRelationshipChooserVisible(false)}><Text style={styles.secondaryButtonText}>{i18n.t('cancel')}</Text></Pressable>
          </View></View>
        </Modal>
        <Modal visible={chooserVisible} transparent animationType="slide" onRequestClose={() => setChooserVisible(false)}>
          <View style={styles.modalBackdrop}>
            <View style={styles.modalCard}>
              <TextInput placeholder="Search household number, head or address" value={householdSearch} onChangeText={setHouseholdSearch} style={styles.input} />
              {householdsLoading ? <Text style={styles.helperText}>Loading households...</Text> : null}
              {householdsError ? <Text style={styles.errorText}>Unable to load households. Reopen this selector to retry.</Text> : null}
              <FlatList
                data={householdsError || householdsLoading ? [] : households}
                ListEmptyComponent={<Text style={styles.helperText}>{householdsLoading || householdsError ? '' : householdSearch.trim() ? 'No matching households.' : 'No eligible households.'}</Text>}
                keyExtractor={(item) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
                keyboardShouldPersistTaps="handled"
                ListHeaderComponent={
                  <Text style={styles.modalTitle}>
                    {i18n.t('chooseHouseholdAssigned', { purok: assignedPurokLabel })}
                  </Text>
                }
                renderItem={({ item }) => (
                  <Pressable
                    onPress={() => {
                      setSelectedHousehold(item);
                      setChooserVisible(false);
                      setFormError(null);
                      setDirty(true);
                      if (item.server_id != null) setProposeHead(false);
                    }}
                    style={styles.modalItem}
                  >
                    <Text style={styles.modalItemTitle}>Household #{item.household_no}</Text>
                    <Text style={styles.modalItemText}>{item.server_id == null ? 'Household request' : item.current_head_name ?? (item.is_vacant ? 'Vacant' : 'No designated head')}</Text>
                    <Text style={styles.modalItemText}>{item.household_address}</Text>
                  </Pressable>
                )}
              />
              {mode !== 'correction' ? <Pressable onPress={createHousehold} style={styles.secondaryButton}><Text style={styles.secondaryButtonText}>Create Household Request</Text></Pressable> : null}
              <Pressable onPress={() => setChooserVisible(false)} style={styles.secondaryButton}>
                <Text style={styles.secondaryButtonText}>{i18n.t('cancel')}</Text>
              </Pressable>
            </View>
          </View>
        </Modal>
      </ScrollView>
    </KeyboardShiftView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: theme.spacing.md, gap: theme.spacing.md },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  label: {
    color: theme.colors.text,
    fontWeight: '600',
    marginBottom: 8,
    marginTop: 10,
  },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.inputBackground,
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  helperText: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    marginTop: 8,
  },
  pickerButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.inputBackground,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  pickerLabel: {
    color: theme.colors.text,
  },
  dateRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    alignItems: 'center',
  },
  dateInput: {
    flex: 1,
  },
  calendarButton: {
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.primarySoft,
    paddingHorizontal: 14,
    paddingVertical: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  calendarButtonText: {
    color: theme.colors.primary,
    fontWeight: '700',
  },
  segmentRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  segment: {
    flex: 1,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    paddingVertical: 12,
    alignItems: 'center',
  },
  segmentActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  segmentText: {
    color: theme.colors.text,
    fontWeight: '600',
  },
  segmentTextActive: {
    color: theme.colors.textOnPrimary,
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 18,
  },
  switchLabel: {
    color: theme.colors.text,
    fontWeight: '600',
  },
  errorBox: {
    marginTop: theme.spacing.md,
    backgroundColor: theme.colors.dangerSoft,
    borderRadius: theme.radius.md,
    padding: theme.spacing.md,
  },
  errorText: {
    color: theme.colors.danger,
    lineHeight: 21,
    fontWeight: '600',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '700',
    fontSize: 16,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: theme.colors.overlay,
    justifyContent: 'flex-end',
  },
  modalCard: {
    backgroundColor: theme.colors.surface,
    borderTopLeftRadius: theme.radius.lg,
    borderTopRightRadius: theme.radius.lg,
    padding: theme.spacing.md,
    maxHeight: '70%',
  },
  modalTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: '700',
    marginBottom: theme.spacing.md,
  },
  modalItem: {
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalItemTitle: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  modalItemText: {
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  secondaryButton: {
    marginTop: theme.spacing.md,
    backgroundColor: theme.colors.surfaceMuted,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
});
