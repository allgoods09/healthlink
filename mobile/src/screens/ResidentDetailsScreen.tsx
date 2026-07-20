import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDate, formatPurokLabel } from '../lib/format';
import { getResidentByLocalId } from '../lib/storage';
import { theme } from '../theme';
import { ResidentRecord } from '../types';

export function ResidentDetailsScreen({ route, navigation }: any) {
  const { assignment } = useAppContext();
  const [resident, setResident] = useState<ResidentRecord | null>(null);

  useEffect(() => {
    async function loadResident() {
      const nextResident = await getResidentByLocalId(route.params?.localId);
      setResident(nextResident);
    }

    void loadResident();
  }, [route.params?.localId]);

  if (!resident) {
    return (
      <View style={styles.emptyScreen}>
        <Text style={styles.emptyText}>{i18n.t('noMatchingRecords')}</Text>
      </View>
    );
  }

  const canEdit =
    assignment?.purok?.id === null ||
    assignment?.purok?.id === undefined ||
    resident.household_purok_id === assignment?.purok?.id;
  const purokLabel = formatPurokLabel(
    resident.household_purok_display_name,
    resident.household_purok_id,
    i18n.t('purokNotAvailable')
  );

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.hero}>
        <Text style={styles.heroName}>
          {resident.last_name}, {resident.first_name}
        </Text>
        {resident.middle_name ? (
          <Text style={styles.heroSubline}>{resident.middle_name}</Text>
        ) : null}
        <View style={styles.badgeRow}>
          <View style={[styles.badge, canEdit ? styles.badgeEditable : styles.badgeReadOnly]}>
            <Text style={[styles.badgeText, canEdit ? styles.badgeEditableText : styles.badgeReadOnlyText]}>
              {canEdit ? i18n.t('editable') : i18n.t('readOnly')}
            </Text>
          </View>
          <View style={styles.badge}>
            <Text style={styles.badgePlainText}>{purokLabel}</Text>
          </View>
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{i18n.t('residentProfile')}</Text>
        <DetailRow label={i18n.t('birthDate')} value={formatFriendlyDate(resident.birth_date) ?? resident.birth_date} />
        <DetailRow label={i18n.t('birthPlace')} value={resident.birth_place} />
        <DetailRow label={i18n.t('sex')} value={resident.sex} />
        <DetailRow label={i18n.t('civilStatus')} value={resident.civil_status} />
        <DetailRow label={i18n.t('citizenship')} value={resident.citizenship} />
        <DetailRow label={i18n.t('religion')} value={resident.religion ?? 'N/A'} />
        <DetailRow label={i18n.t('contactNumber')} value={resident.contact_number ?? 'N/A'} />
        <DetailRow label={i18n.t('emailAddress')} value={resident.email_address ?? 'N/A'} />
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{i18n.t('householdProfile')}</Text>
        <DetailRow label={i18n.t('householdNo')} value={resident.household_no ?? 'N/A'} />
        <DetailRow label={i18n.t('relationshipToHead')} value={resident.relationship_to_head} />
        <DetailRow label={i18n.t('sourcePurok')} value={purokLabel} />
        <DetailRow
          label={i18n.t('active')}
          value={resident.is_active ? i18n.t('active') : i18n.t('inactive')}
        />
      </View>

      {canEdit ? (
        <Pressable
          onPress={() => navigation.navigate('ResidentForm', { localId: resident.local_id })}
          style={styles.primaryButton}
        >
          <Text style={styles.primaryButtonText}>{i18n.t('edit')}</Text>
        </Pressable>
      ) : (
        <View style={styles.readOnlyCard}>
          <Text style={styles.readOnlyText}>{i18n.t('otherPurokReadOnly')}</Text>
        </View>
      )}
    </ScrollView>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
    gap: theme.spacing.md,
  },
  emptyScreen: {
    flex: 1,
    backgroundColor: theme.colors.background,
    alignItems: 'center',
    justifyContent: 'center',
    padding: theme.spacing.lg,
  },
  emptyText: {
    color: theme.colors.textMuted,
    textAlign: 'center',
  },
  hero: {
    backgroundColor: theme.colors.primary,
    borderRadius: 28,
    padding: theme.spacing.lg,
  },
  heroName: {
    color: '#FFFFFF',
    fontSize: 28,
    fontWeight: '700',
  },
  heroSubline: {
    color: '#D9E7FA',
    marginTop: 6,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  badge: {
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.14)',
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  badgeEditable: {
    backgroundColor: 'rgba(218, 252, 231, 0.18)',
  },
  badgeReadOnly: {
    backgroundColor: 'rgba(255, 255, 255, 0.14)',
  },
  badgeText: {
    fontWeight: '700',
    fontSize: 12,
  },
  badgeEditableText: {
    color: '#DDFBE7',
  },
  badgeReadOnlyText: {
    color: '#FFFFFF',
  },
  badgePlainText: {
    color: '#FFFFFF',
    fontWeight: '600',
    fontSize: 12,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
  },
  detailRow: {
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  detailLabel: {
    color: theme.colors.textMuted,
    fontSize: 13,
    marginBottom: 4,
  },
  detailValue: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '600',
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 16,
  },
  readOnlyCard: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.md,
  },
  readOnlyText: {
    color: theme.colors.textMuted,
    lineHeight: 21,
  },
});
