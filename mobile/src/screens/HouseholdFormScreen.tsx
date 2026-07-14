import React, { useEffect, useState } from 'react';
import {
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
import { getHouseholdByLocalId, saveHousehold } from '../lib/storage';
import { theme } from '../theme';

export function HouseholdFormScreen({ route, navigation }: any) {
  const { bumpDataVersion } = useAppContext();
  const [householdNo, setHouseholdNo] = useState('');
  const [address, setAddress] = useState('');
  const [socialAid, setSocialAid] = useState(false);
  const [active, setActive] = useState(true);
  const [serverId, setServerId] = useState<number | null>(null);
  const [mobileUuid, setMobileUuid] = useState<string | null>(null);
  const [localId, setLocalId] = useState<number | null>(null);

  useEffect(() => {
    async function loadExisting() {
      if (!route.params?.localId) return;

      const existing = await getHouseholdByLocalId(route.params.localId);

      if (!existing) return;

      setLocalId(existing.local_id ?? null);
      setServerId(existing.server_id ?? null);
      setMobileUuid(existing.mobile_uuid ?? null);
      setHouseholdNo(existing.household_no);
      setAddress(existing.household_address);
      setSocialAid(existing.is_social_aid_beneficiary);
      setActive(existing.is_active);
    }

    void loadExisting();
  }, [route.params?.localId]);

  async function handleSave() {
    await saveHousehold({
      local_id: localId ?? undefined,
      server_id: serverId,
      mobile_uuid: mobileUuid,
      household_no: householdNo,
      household_address: address,
      is_social_aid_beneficiary: socialAid,
      is_active: active,
    });
    bumpDataVersion();
    navigation.goBack();
  }

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.label}>{i18n.t('householdNo')}</Text>
        <TextInput value={householdNo} onChangeText={setHouseholdNo} style={styles.input} />

        <Text style={styles.label}>{i18n.t('householdAddress')}</Text>
        <TextInput
          value={address}
          onChangeText={setAddress}
          style={[styles.input, styles.multiline]}
          multiline
        />

        <View style={styles.switchRow}>
          <Text style={styles.switchLabel}>{i18n.t('socialAid')}</Text>
          <Switch value={socialAid} onValueChange={setSocialAid} />
        </View>

        <View style={styles.switchRow}>
          <Text style={styles.switchLabel}>{i18n.t('active')}</Text>
          <Switch value={active} onValueChange={setActive} />
        </View>
      </View>

      <Pressable onPress={handleSave} style={styles.primaryButton}>
        <Text style={styles.primaryButtonText}>{i18n.t('save')}</Text>
      </Pressable>
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
  multiline: {
    minHeight: 110,
    textAlignVertical: 'top',
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
});
