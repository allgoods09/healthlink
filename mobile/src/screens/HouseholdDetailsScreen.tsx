import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDate, formatPurokLabel, formatResidentFormalName } from '../lib/format';
import { getHouseholdByLocalId, getHouseholdRequestByLocalId, getHouseholdLookupByLocalId, getResidentsForHousehold } from '../lib/storage';
import { householdEditBlocked } from '../lib/householdWorkflow';
import { AppTheme } from '../theme';
import { HouseholdRecord, ResidentRecord } from '../types';

export function HouseholdDetailsScreen({ route, navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const { assignment, dataVersion } = useAppContext();
  const [household, setHousehold] = useState<HouseholdRecord | null>(null);
  const [members, setMembers] = useState<ResidentRecord[]>([]);

  useEffect(() => {
    async function loadHousehold() {
      const nextHousehold = await getHouseholdByLocalId(route.params?.localId) ?? await getHouseholdRequestByLocalId(route.params?.localId) ?? await getHouseholdLookupByLocalId(route.params?.localId);
      setHousehold(nextHousehold);

      if (nextHousehold?.member_coverage === 'complete') {
        const nextMembers = await getResidentsForHousehold(nextHousehold);
        setMembers(nextMembers);
      } else setMembers([]);
    }

    void loadHousehold();
  }, [route.params?.localId, dataVersion]);

  if (!household) {
    return (
      <View style={styles.emptyScreen}>
        <Text style={styles.emptyText}>{i18n.t('noMatchingRecords')}</Text>
      </View>
    );
  }

  const canEdit = assignment?.purok?.id != null && household.purok_id === assignment.purok.id &&
    household.access_mode !== 'lookup' && !householdEditBlocked(household);
  const canVisit = household.access_mode === 'operational' && household.server_id != null;
  const purokLabel = formatPurokLabel(
    household.purok_display_name,
    household.purok_id,
    i18n.t('purokNotAvailable')
  );

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.hero}>
        <Text style={styles.heroName}>{household.household_no}</Text>
        <Text style={styles.heroSubline}>{purokLabel}</Text>
        <View style={styles.badgeRow}>
          <View style={[styles.badge, canEdit ? styles.badgeEditable : styles.badgeReadOnly]}>
            <Text style={[styles.badgeText, canEdit ? styles.badgeEditableText : styles.badgeReadOnlyText]}>
              {canEdit ? i18n.t('editable') : i18n.t('readOnly')}
            </Text>
          </View>
          <View style={styles.badge}>
            <Text style={styles.badgePlainText}>{household.member_coverage === 'complete' ? `${household.current_member_count ?? members.length} ${i18n.t('residents')}` : i18n.t('readOnly')}</Text>
          </View>
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{i18n.t('householdProfile')}</Text>
        <DetailRow label={i18n.t('householdAddress')} value={household.household_address} />
        <DetailRow label={i18n.t('sourcePurok')} value={purokLabel} />
        <DetailRow
          label={i18n.t('active')}
          value={household.is_active ? i18n.t('active') : i18n.t('inactive')}
        />
        {household.access_mode !== 'lookup' ? <DetailRow
          label={i18n.t('socialAid')}
          value={household.is_social_aid_beneficiary ? 'Yes' : 'No'}
        /> : null}
      </View>

      {household.member_coverage === 'complete' ? <View style={styles.card}>
        <Text style={styles.sectionTitle}>{i18n.t('householdMembers')}</Text>

        {members.length > 0 ? (
          members.map((member) => (
            <View key={String(member.local_id ?? member.server_id ?? member.mobile_uuid)} style={styles.memberRow}>
              <Text style={styles.memberName}>
                {formatResidentFormalName(member)}
              </Text>
              <Text style={styles.memberMeta}>
                {member.relationship_to_head} · {formatFriendlyDate(member.birth_date) ?? member.birth_date}
              </Text>
            </View>
          ))
        ) : (
          <Text style={styles.emptyText}>{i18n.t('noHouseholdMembers')}</Text>
        )}
      </View> : null}

      {household.verification_status && household.access_mode === 'request' ? <Text style={styles.emptyText}>{household.verification_status} {household.verification_notes ?? ''}</Text> : null}
      {household.protection_reason ? <Text style={styles.emptyText}>{household.protection_reason}</Text> : null}

      {canEdit || canVisit ? (
        <View style={styles.actionRow}>
          {canEdit ? <Pressable
            onPress={() => navigation.navigate('HouseholdForm', { localId: household.local_id })}
            style={styles.secondaryButton}
          >
            <Text style={styles.secondaryButtonText}>{i18n.t('edit')}</Text>
          </Pressable> : null}

          {canVisit ? <Pressable
            onPress={() =>
              navigation.navigate('VisitForm', {
                householdLocalId: household.local_id,
              })
            }
            style={styles.primaryButton}
          >
            <Text style={styles.primaryButtonText}>{i18n.t('createVisit')}</Text>
          </Pressable> : null}
        </View>
      ) : (
        <View style={styles.readOnlyCard}>
          <Text style={styles.readOnlyText}>{i18n.t('otherPurokReadOnly')}</Text>
        </View>
      )}
    </ScrollView>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  const styles = useThemedStyles(createStyles);
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
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
    lineHeight: 21,
    textAlign: 'center',
  },
  hero: {
    backgroundColor: theme.colors.primary,
    borderRadius: 28,
    padding: theme.spacing.lg,
  },
  heroName: {
    color: theme.colors.textOnPrimary,
    fontSize: 28,
    fontWeight: '700',
  },
  heroSubline: {
    color: theme.colors.heroTextMuted,
    marginTop: 8,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  badge: {
    borderRadius: 999,
    backgroundColor: theme.colors.heroSurface,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  badgeEditable: {
    backgroundColor: theme.colors.heroSuccessSurface,
  },
  badgeReadOnly: {
    backgroundColor: theme.colors.heroSurface,
  },
  badgeText: {
    fontWeight: '700',
    fontSize: 12,
  },
  badgeEditableText: {
    color: theme.colors.textOnBrand,
  },
  badgeReadOnlyText: {
    color: theme.colors.textOnPrimary,
  },
  badgePlainText: {
    color: theme.colors.textOnPrimary,
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
  memberRow: {
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  memberName: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '700',
  },
  memberMeta: {
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  actionRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  primaryButton: {
    flex: 1,
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
  secondaryButton: {
    flex: 1,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  secondaryButtonText: {
    color: theme.colors.text,
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
