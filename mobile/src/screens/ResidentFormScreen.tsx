import React, { useEffect, useState } from 'react';
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

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import {
  getHouseholdByLocalId,
  getHouseholds,
  getResidentByLocalId,
  saveResident,
} from '../lib/storage';
import { theme } from '../theme';
import { HouseholdRecord } from '../types';

export function ResidentFormScreen({ route, navigation }: any) {
  const { bumpDataVersion } = useAppContext();
  const [households, setHouseholds] = useState<HouseholdRecord[]>([]);
  const [chooserVisible, setChooserVisible] = useState(false);
  const [selectedHousehold, setSelectedHousehold] = useState<HouseholdRecord | null>(null);
  const [localId, setLocalId] = useState<number | null>(null);
  const [serverId, setServerId] = useState<number | null>(null);
  const [mobileUuid, setMobileUuid] = useState<string | null>(null);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [birthDate, setBirthDate] = useState('');
  const [birthPlace, setBirthPlace] = useState('');
  const [sex, setSex] = useState<'Male' | 'Female'>('Female');
  const [civilStatus, setCivilStatus] = useState('Single');
  const [citizenship, setCitizenship] = useState('Filipino');
  const [religion, setReligion] = useState('Catholic');
  const [contactNumber, setContactNumber] = useState('');
  const [emailAddress, setEmailAddress] = useState('');
  const [relationshipToHead, setRelationshipToHead] = useState('Head');
  const [active, setActive] = useState(true);

  useEffect(() => {
    void getHouseholds().then(setHouseholds);
  }, []);

  useEffect(() => {
    async function loadExisting() {
      if (!route.params?.localId) return;

      const existing = await getResidentByLocalId(route.params.localId);

      if (!existing) return;

      setLocalId(existing.local_id ?? null);
      setServerId(existing.server_id ?? null);
      setMobileUuid(existing.mobile_uuid ?? null);
      setFirstName(existing.first_name);
      setLastName(existing.last_name);
      setMiddleName(existing.middle_name ?? '');
      setBirthDate(existing.birth_date);
      setBirthPlace(existing.birth_place);
      setSex(existing.sex);
      setCivilStatus(existing.civil_status);
      setCitizenship(existing.citizenship);
      setReligion(existing.religion ?? '');
      setContactNumber(existing.contact_number ?? '');
      setEmailAddress(existing.email_address ?? '');
      setRelationshipToHead(existing.relationship_to_head);
      setActive(existing.is_active);

      if (existing.household_server_id || existing.household_mobile_uuid) {
        const existingHousehold = (await getHouseholds()).find(
          (household) =>
            household.server_id === existing.household_server_id ||
            household.mobile_uuid === existing.household_mobile_uuid
        );

        if (existingHousehold) {
          setSelectedHousehold(existingHousehold);
        }
      }
    }

    void loadExisting();
  }, [route.params?.localId]);

  async function handleSave() {
    if (!selectedHousehold) return;

    await saveResident({
      local_id: localId ?? undefined,
      server_id: serverId,
      mobile_uuid: mobileUuid,
      household_server_id: selectedHousehold.server_id ?? null,
      household_mobile_uuid: selectedHousehold.mobile_uuid ?? null,
      last_name: lastName,
      first_name: firstName,
      middle_name: middleName || null,
      birth_date: birthDate,
      birth_place: birthPlace,
      sex,
      civil_status: civilStatus,
      citizenship,
      religion: religion || null,
      contact_number: contactNumber || null,
      email_address: emailAddress || null,
      relationship_to_head: relationshipToHead,
      is_active: active,
    });
    bumpDataVersion();
    navigation.goBack();
  }

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.label}>{i18n.t('chooseHousehold')}</Text>
        <Pressable onPress={() => setChooserVisible(true)} style={styles.pickerButton}>
          <Text style={styles.pickerLabel}>
            {selectedHousehold?.household_no ?? i18n.t('chooseHousehold')}
          </Text>
        </Pressable>

        <Text style={styles.label}>{i18n.t('firstName')}</Text>
        <TextInput value={firstName} onChangeText={setFirstName} style={styles.input} />

        <Text style={styles.label}>{i18n.t('lastName')}</Text>
        <TextInput value={lastName} onChangeText={setLastName} style={styles.input} />

        <Text style={styles.label}>{i18n.t('middleName')}</Text>
        <TextInput value={middleName} onChangeText={setMiddleName} style={styles.input} />

        <Text style={styles.label}>{i18n.t('birthDate')}</Text>
        <TextInput value={birthDate} onChangeText={setBirthDate} style={styles.input} />

        <Text style={styles.label}>{i18n.t('birthPlace')}</Text>
        <TextInput value={birthPlace} onChangeText={setBirthPlace} style={styles.input} />

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
        <TextInput value={civilStatus} onChangeText={setCivilStatus} style={styles.input} />

        <Text style={styles.label}>{i18n.t('citizenship')}</Text>
        <TextInput value={citizenship} onChangeText={setCitizenship} style={styles.input} />

        <Text style={styles.label}>{i18n.t('religion')}</Text>
        <TextInput value={religion} onChangeText={setReligion} style={styles.input} />

        <Text style={styles.label}>{i18n.t('contactNumber')}</Text>
        <TextInput value={contactNumber} onChangeText={setContactNumber} style={styles.input} />

        <Text style={styles.label}>{i18n.t('emailAddress')}</Text>
        <TextInput value={emailAddress} onChangeText={setEmailAddress} style={styles.input} />

        <Text style={styles.label}>{i18n.t('relationshipToHead')}</Text>
        <TextInput
          value={relationshipToHead}
          onChangeText={setRelationshipToHead}
          style={styles.input}
        />

        <View style={styles.switchRow}>
          <Text style={styles.switchLabel}>{i18n.t('active')}</Text>
          <Switch value={active} onValueChange={setActive} />
        </View>
      </View>

      <Pressable onPress={handleSave} style={styles.primaryButton}>
        <Text style={styles.primaryButtonText}>{i18n.t('save')}</Text>
      </Pressable>

      <Modal visible={chooserVisible} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <FlatList
              data={households}
              keyExtractor={(item) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
              ListHeaderComponent={<Text style={styles.modalTitle}>{i18n.t('chooseHousehold')}</Text>}
              renderItem={({ item }) => (
                <Pressable
                  onPress={() => {
                    setSelectedHousehold(item);
                    setChooserVisible(false);
                  }}
                  style={styles.modalItem}
                >
                  <Text style={styles.modalItemTitle}>{item.household_no}</Text>
                  <Text style={styles.modalItemText}>{item.household_address}</Text>
                </Pressable>
              )}
            />
            <Pressable onPress={() => setChooserVisible(false)} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>{i18n.t('cancel')}</Text>
            </Pressable>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
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
    backgroundColor: '#FAFBFA',
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  pickerButton: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: '#FAFBFA',
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  pickerLabel: {
    color: theme.colors.text,
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
    color: '#fff',
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
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 16,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(16, 24, 40, 0.28)',
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
