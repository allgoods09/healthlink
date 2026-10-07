import React, { useEffect, useRef, useState } from 'react';
import { usePreventRemove } from '@react-navigation/native';
import { ScrollView, Switch, Text, TextInput, View } from 'react-native';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { HouseholdAction, HouseholdState, HouseholdStatus, householdUiStyles } from '../components/HouseholdUi';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { useKeyboardAwareScroll } from '../hooks/useKeyboardAwareScroll';
import { i18n } from '../i18n';
import { getHouseholdByLocalId, getHouseholdRequestByLocalId, getHouseholdLookupByLocalId, hasHouseholdData, saveHousehold } from '../lib/storage';
import { householdFormMode, householdCanEdit, householdValidationKey } from '../lib/householdPresentation';
import { createSaveGuard } from '../lib/residentWorkflow';
import { useLocalEditor } from '../lib/useLocalEditor';
import { HouseholdRecord } from '../types';

export function HouseholdFormScreen({ route, navigation }: any) {
  useLocalEditor();
  const theme = useAppTheme(); const styles = useThemedStyles(householdUiStyles);
  const { user, assignment, dataVersion, bumpDataVersion, requestConfirmation } = useAppContext();
  const { handleInputFocus, handleScroll, keyboardInset, scrollRef } = useKeyboardAwareScroll();
  const [householdNo, setHouseholdNo] = useState(''); const [address, setAddress] = useState(''); const [socialAid, setSocialAid] = useState(false);
  const [existing, setExisting] = useState<HouseholdRecord | null>(null);
  const [ready, setReady] = useState(false); const [saving, setSaving] = useState(false); const [saved, setSaved] = useState(false);
  const [formError, setFormError] = useState<string | null>(null); const [retry, setRetry] = useState(0); const [loadError, setLoadError] = useState(false);
  const guard = useRef(createSaveGuard()); const original = useRef(JSON.stringify(['', '', false]));
  const savedRef = useRef(false);
  const fingerprint = JSON.stringify([householdNo, address, socialAid]); const dirty = fingerprint !== original.current;
  const dirtyRef = useRef(dirty); dirtyRef.current = dirty;
  const scope = `${user?.id}:${assignment?.barangay?.id}:${assignment?.purok?.id}`;
  const loadedScope = useRef(''); const applicable = useRef(true); const generation = useRef(0);
  const mode = householdFormMode(existing);
  const editable = ready && loadedScope.current === scope && householdCanEdit(existing);
  usePreventRemove((dirty && householdCanEdit(existing) || saving) && !saved, ({ data }) => {
    if (saving) return;
    void requestConfirmation({ title: i18n.t('hhLeaveTitle'), message: i18n.t('hhLeaveBody'), confirmLabel: i18n.t('hhLeave') })
      .then(confirmed => { if (confirmed && applicable.current) navigation.dispatch(data.action); });
  });
  useEffect(() => { if (saved) navigation.goBack(); }, [saved, navigation]);
  useEffect(() => { navigation.setOptions({ title: i18n.t(mode === 'correction' ? 'hhRequestUpdate' : mode === 'unsent' ? 'hhEditRequest' : mode === 'new' ? 'hhNew' : mode === 'lookup' ? 'hhLookup' : `hhStatus_${mode}`) }); }, [mode, navigation]);
  useEffect(() => {
    applicable.current = true; const token = ++generation.current; let live = true;
    setSaving(false);
    // Never replace a user's unsaved fields with an asynchronous refresh.
    if (dirtyRef.current) { setReady(false); setFormError(i18n.t('hhRefresh')); return () => { live = false; applicable.current = false; }; }
    setReady(false); setFormError(null); setLoadError(false);
    void (async () => {
      try {
        if (!await hasHouseholdData({ userId: user?.id, barangayId: assignment?.barangay?.id, purokId: assignment?.purok?.id })) { if (live) setFormError(i18n.t('hhRefresh')); return; }
        const id = route.params?.localId;
        const row = id != null ? await getHouseholdByLocalId(id) ?? await getHouseholdRequestByLocalId(id) ?? await getHouseholdLookupByLocalId(id) : null;
        if (!live || token !== generation.current) return;
        if (id != null && !row) { setFormError(i18n.t('hhUnavailable')); return; }
        const values = [row?.household_no ?? '', row?.household_address ?? '', row?.is_social_aid_beneficiary ?? false] as const;
        original.current = JSON.stringify(values); loadedScope.current = scope;
        setExisting(row); setHouseholdNo(values[0]); setAddress(values[1]); setSocialAid(values[2]); setReady(true);
      } catch { if (live) { setFormError(i18n.t('savedRecordsError')); setLoadError(true); } }
    })();
    return () => { live = false; applicable.current = false; };
  }, [route.params?.localId, scope, dataVersion, retry]);

  async function handleSave() {
    if (!editable || saved || savedRef.current) return;
    const invalid = householdValidationKey(householdNo, address, socialAid);
    if (invalid) { setFormError(i18n.t(invalid)); return; }
    if (!guard.current.acquire()) return;
    const token = generation.current; setSaving(true); setFormError(null);
    try {
      const confirmed = await requestConfirmation({ title: i18n.t('hhSaveTitle'), message: i18n.t('hhFormHint'), confirmLabel: i18n.t('save') });
      if (!confirmed || !applicable.current || token !== generation.current) return;
      await saveHousehold({ local_id: existing?.local_id, server_id: existing?.server_id ?? null, mobile_uuid: existing?.mobile_uuid ?? null,
        purok_id: existing?.purok_id ?? assignment?.purok?.id ?? null, purok_display_name: existing?.purok_display_name ?? assignment?.purok?.display_name ?? null,
        household_no: householdNo.trim(), household_address: address.trim(), is_social_aid_beneficiary: socialAid,
        is_active: existing?.is_active ?? true }, user?.id);
      if (!applicable.current || token !== generation.current) return;
      savedRef.current = true; original.current = fingerprint; setSaved(true); bumpDataVersion();
    } catch { if (applicable.current && token === generation.current) setFormError(i18n.t('hhSaveError')); }
    finally { guard.current.release(); if (applicable.current && token === generation.current) setSaving(false); }
  }
  if (loadedScope.current && loadedScope.current !== scope) return <KeyboardShiftView style={styles.screen}>
    <ScrollView contentContainerStyle={styles.content}><HouseholdState message={i18n.t('hhRefresh')} /></ScrollView>
  </KeyboardShiftView>;
  return <KeyboardShiftView style={styles.screen}><ScrollView ref={scrollRef} style={styles.screen}
    contentContainerStyle={[styles.content, { paddingBottom: theme.spacing.xl + keyboardInset }]} keyboardShouldPersistTaps="handled"
    onScroll={handleScroll} scrollEventThrottle={16}>
    {!ready ? <HouseholdState message={formError ?? i18n.t('loading')} error={loadError} retry={loadError ? () => setRetry(value => value + 1) : undefined} /> : null}
    <View style={styles.card}>
      {existing ? <HouseholdStatus row={existing} /> : null}
      {editable ? <Text style={styles.helper}>{i18n.t('hhFormHint')}</Text> : null}
      {mode === 'correction' ? <Text style={styles.helper}>{i18n.t('hhCorrectionHint')}</Text> : null}
      <Text style={styles.helper}>{existing ? existing.purok_display_name ?? i18n.t('purokNotAvailable') : assignment?.purok?.display_name ?? i18n.t('purokNotAvailable')}</Text>
      {existing?.access_mode === 'operational' ? <Text style={styles.helper}>{i18n.t('hhAvailability')}: {i18n.t(existing.is_active ? 'active' : 'inactive')}</Text> : null}
      <Text nativeID="household-number-label" style={styles.text}>{i18n.t('householdNo')}</Text>
      <TextInput accessibilityLabel={i18n.t('householdNo')} accessibilityLabelledBy="household-number-label" editable={editable && !saving && !saved}
        value={householdNo} onChangeText={setHouseholdNo} onFocus={handleInputFocus} style={styles.input} />
      <Text nativeID="household-address-label" style={styles.text}>{i18n.t('householdAddress')}</Text>
      <TextInput accessibilityLabel={i18n.t('householdAddress')} accessibilityLabelledBy="household-address-label" editable={editable && !saving && !saved}
        value={address} onChangeText={setAddress} onFocus={handleInputFocus} style={[styles.input, { minHeight: 110, textAlignVertical: 'top' }]} multiline />
      {existing?.access_mode !== 'lookup' ? <View style={styles.actions}><Text style={[styles.text, { flex: 1 }]} nativeID="household-social-label">{i18n.t('socialAid')}</Text>
        <Switch accessibilityLabel={i18n.t('socialAid')} accessibilityLabelledBy="household-social-label" accessibilityRole="switch"
          accessibilityState={{ checked: socialAid, disabled: !editable || saving || saved }} disabled={!editable || saving || saved}
          style={{ minHeight: 48, minWidth: 48 }} value={socialAid} onValueChange={setSocialAid}
          trackColor={{ false: theme.colors.inactiveSoft, true: theme.colors.primary }} thumbColor={theme.colors.surfaceElevated} />
      </View> : null}
    </View>
    {ready && formError ? <HouseholdState error message={formError} /> : null}
    {editable || !ready && !existing ? <HouseholdAction primary disabled={!editable || saving || saved} label={i18n.t(saving ? 'loading' : 'save')} onPress={handleSave} /> : null}
  </ScrollView></KeyboardShiftView>;
}
