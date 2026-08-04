import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';

import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import {
  calculateAgeFromBirthDate,
  daysSinceDate,
  formatFriendlyDate,
  formatPurokLabel,
  formatResidentFormalName,
} from '../lib/format';
import {
  getLatestRiskAssessmentForResident,
  getResidentByLocalId,
  getRiskAssessmentsForResident,
} from '../lib/storage';
import { AppTheme } from '../theme';
import { ResidentRecord, RiskAssessmentRecord } from '../types';

export function ResidentDetailsScreen({ route, navigation }: any) {
  const styles = useThemedStyles(createStyles);
  const isFocused = useIsFocused();
  const { assignment, dataVersion } = useAppContext();
  const [resident, setResident] = useState<ResidentRecord | null>(null);
  const [latestRiskAssessment, setLatestRiskAssessment] =
    useState<RiskAssessmentRecord | null>(null);
  const [editableRiskDraft, setEditableRiskDraft] =
    useState<RiskAssessmentRecord | null>(null);

  useEffect(() => {
    if (!isFocused) {
      return;
    }

    async function loadResident() {
      const nextResident = await getResidentByLocalId(route.params?.localId);
      setResident(nextResident);

      if (!nextResident?.server_id) {
        setLatestRiskAssessment(null);
        setEditableRiskDraft(null);
        return;
      }

      const [latestAssessment, assessments] = await Promise.all([
        getLatestRiskAssessmentForResident(nextResident.server_id),
        getRiskAssessmentsForResident(nextResident.server_id),
      ]);

      setLatestRiskAssessment(latestAssessment);
      setEditableRiskDraft(
        assessments.find((assessment) => assessment.sync_status !== 'synced') ?? null
      );
    }

    void loadResident();
  }, [dataVersion, isFocused, route.params?.localId]);

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
  const residentAge = calculateAgeFromBirthDate(resident.birth_date);
  const canCreateRiskAssessment =
    Boolean(resident.server_id) && residentAge !== null && residentAge >= 20;
  const latestAssessmentDays = daysSinceDate(latestRiskAssessment?.assessment_date);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.hero}>
        <Text style={styles.heroName}>{formatResidentFormalName(resident)}</Text>
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

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>PhilPEN Risk Assessment</Text>
        {canCreateRiskAssessment ? (
          <>
            <DetailRow
              label="Latest Assessment"
              value={
                latestRiskAssessment?.assessment_date
                  ? formatFriendlyDate(latestRiskAssessment.assessment_date) ??
                    latestRiskAssessment.assessment_date
                  : 'No assessment record'
              }
            />
            <DetailRow
              label="Screening Status"
              value={
                latestRiskAssessment?.assessment_date
                  ? latestAssessmentDays !== null && latestAssessmentDays <= 30
                    ? `Assessed ${latestAssessmentDays} day${latestAssessmentDays === 1 ? '' : 's'} ago`
                    : `Due for follow-up${latestAssessmentDays !== null ? ` (${latestAssessmentDays} days ago)` : ''}`
                  : 'Pending assessment'
              }
            />
            <DetailRow
              label="BMI / Waist"
              value={
                latestRiskAssessment
                  ? `${latestRiskAssessment.body_mass_index ?? 'N/A'} BMI · ${latestRiskAssessment.waist_circumference_cm ?? 'N/A'} cm`
                  : 'No screening values yet'
              }
            />
            <DetailRow
              label="Immediate Referral Flag"
              value={
                latestRiskAssessment?.requires_immediate_referral
                  ? 'Yes - urgent referral noted'
                  : 'No immediate red flag logged'
              }
            />

            <Pressable
              onPress={() =>
                navigation.navigate('RiskAssessmentForm', {
                  residentLocalId: resident.local_id,
                  riskAssessmentLocalId: editableRiskDraft?.local_id,
                })
              }
              style={[styles.primaryButton, styles.secondaryActionSpacing]}
            >
              <Text style={styles.primaryButtonText}>
                {editableRiskDraft ? 'Continue Unsynced Assessment' : 'New Risk Assessment'}
              </Text>
            </Pressable>
          </>
        ) : (
          <View style={styles.readOnlyCard}>
            <Text style={styles.readOnlyText}>
              PhilPEN is only available for verified adult residents aged 20 years old and above.
            </Text>
          </View>
        )}
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
  readOnlyCard: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.md,
  },
  readOnlyText: {
    color: theme.colors.textMuted,
    lineHeight: 21,
  },
  secondaryActionSpacing: {
    marginTop: theme.spacing.md,
  },
});
