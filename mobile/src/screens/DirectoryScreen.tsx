import React, { useEffect, useMemo, useState } from 'react';
import {
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useIsFocused } from '@react-navigation/native';

import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { MenuCard } from '../components/MenuCard';
import { TopHeader } from '../components/TopHeader';
import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import {
  calculateAgeFromBirthDate,
  daysSinceDate,
  formatFriendlyDate,
  formatPurokLabel,
} from '../lib/format';
import { getHouseholds, getResidents } from '../lib/storage';
import { theme } from '../theme';
import { HouseholdRecord, ResidentRecord } from '../types';

type DirectoryMode = 'residents' | 'households';
type ResidentScreeningFilter = 'due30' | 'allAdults' | 'assessed' | 'allResidents';

export function DirectoryScreen({ navigation }: any) {
  const isFocused = useIsFocused();
  const { assignment, dataVersion } = useAppContext();
  const [mode, setMode] = useState<DirectoryMode>('residents');
  const [residentFilter, setResidentFilter] = useState<ResidentScreeningFilter>('due30');
  const [search, setSearch] = useState('');
  const [households, setHouseholds] = useState<HouseholdRecord[]>([]);
  const [residents, setResidents] = useState<ResidentRecord[]>([]);

  const assignedPurokId = assignment?.purok?.id ?? null;

  useEffect(() => {
    if (!isFocused) {
      return;
    }

    async function loadDirectory() {
      const [nextResidents, nextHouseholds] = await Promise.all([
        getResidents(search),
        getHouseholds(search),
      ]);

      setResidents(nextResidents);
      setHouseholds(nextHouseholds);
    }

    void loadDirectory();
  }, [dataVersion, isFocused, search]);

  const filteredResidents = useMemo(() => {
    return residents.filter((resident) => {
      const age = calculateAgeFromBirthDate(resident.birth_date);
      const isAdult = age !== null && age >= 20;
      const daysSinceAssessment = daysSinceDate(resident.latest_risk_assessment_date);
      const hasAssessment = Boolean(resident.latest_risk_assessment_date);

      switch (residentFilter) {
        case 'due30':
          return isAdult && (!hasAssessment || daysSinceAssessment === null || daysSinceAssessment > 30);
        case 'allAdults':
          return isAdult;
        case 'assessed':
          return isAdult && hasAssessment;
        case 'allResidents':
        default:
          return true;
      }
    });
  }, [residentFilter, residents]);

  const currentData = useMemo(
    () => (mode === 'residents' ? filteredResidents : households),
    [filteredResidents, households, mode]
  );

  function renderResidentCard(item: ResidentRecord) {
    const canEdit = assignedPurokId === null || item.household_purok_id === assignedPurokId;
    const purokLabel = formatPurokLabel(
      item.household_purok_display_name,
      item.household_purok_id,
      i18n.t('purokNotAvailable')
    );
    const residentAge = calculateAgeFromBirthDate(item.birth_date);
    const isAdult = residentAge !== null && residentAge >= 20;
    const latestAssessmentDays = daysSinceDate(item.latest_risk_assessment_date);
    const hasAssessment = Boolean(item.latest_risk_assessment_date);

    let riskBadgeLabel = 'Under 20';
    let riskBadgeStyle = styles.riskBadgeNeutral;
    let riskBadgeTextStyle = styles.riskBadgeNeutralText;

    if (isAdult && !hasAssessment) {
      riskBadgeLabel = 'Pending Assessment';
      riskBadgeStyle = styles.riskBadgeDanger;
      riskBadgeTextStyle = styles.riskBadgeDangerText;
    } else if (isAdult && latestAssessmentDays !== null && latestAssessmentDays <= 30) {
      riskBadgeLabel = `Assessed ${latestAssessmentDays} day${latestAssessmentDays === 1 ? '' : 's'} ago`;
      riskBadgeStyle = styles.riskBadgeSuccess;
      riskBadgeTextStyle = styles.riskBadgeSuccessText;
    } else if (isAdult && hasAssessment) {
      riskBadgeLabel = latestAssessmentDays !== null
        ? `Assessed ${latestAssessmentDays} days ago`
        : 'Assessment Recorded';
      riskBadgeStyle = styles.riskBadgeWarning;
      riskBadgeTextStyle = styles.riskBadgeWarningText;
    }

    return (
      <View style={styles.dataCard}>
        <View style={styles.dataCardHeader}>
          <Text style={styles.dataTitle}>
            {item.last_name}, {item.first_name}
          </Text>
          <Text style={[styles.scopePill, canEdit ? styles.scopeEditable : styles.scopeReadOnly]}>
            {canEdit ? i18n.t('editable') : i18n.t('readOnly')}
          </Text>
        </View>
        <Text style={styles.dataSubtitle}>
          {item.household_no ? `${item.household_no} · ${item.relationship_to_head}` : item.relationship_to_head}
        </Text>
        <Text style={styles.dataMeta}>
          {purokLabel}
        </Text>
        <Text style={styles.dataMeta}>
          {item.sex} · {residentAge !== null ? `Age ${residentAge}` : 'Age unavailable'} · {formatFriendlyDate(item.birth_date) ?? item.birth_date}
        </Text>
        <View style={styles.badgeRow}>
          <View style={[styles.riskBadge, riskBadgeStyle]}>
            <Text style={[styles.riskBadgeText, riskBadgeTextStyle]}>{riskBadgeLabel}</Text>
          </View>
          {isAdult && item.latest_risk_assessment_date ? (
            <View style={styles.dateBadge}>
              <Text style={styles.dateBadgeText}>
                {formatFriendlyDate(item.latest_risk_assessment_date) ?? item.latest_risk_assessment_date}
              </Text>
            </View>
          ) : null}
        </View>
        <View style={styles.inlineActionsRow}>
          <Pressable
            onPress={() => navigation.navigate('ResidentDetails', { localId: item.local_id })}
            style={styles.inlineAction}
          >
            <Text style={styles.inlineActionText}>{i18n.t('viewDetails')}</Text>
          </Pressable>

          {canEdit ? (
            <Pressable
              onPress={() => navigation.navigate('ResidentForm', { localId: item.local_id })}
              style={styles.inlineAction}
            >
              <Text style={styles.inlineActionText}>{i18n.t('edit')}</Text>
            </Pressable>
          ) : null}
        </View>
        {!canEdit ? (
          <Text style={styles.readOnlyNote}>{i18n.t('otherPurokReadOnly')}</Text>
        ) : null}
      </View>
    );
  }

  function renderHouseholdCard(item: HouseholdRecord) {
    const canEdit = assignedPurokId === null || item.purok_id === assignedPurokId;
    const purokLabel = formatPurokLabel(
      item.purok_display_name,
      item.purok_id,
      i18n.t('purokNotAvailable')
    );

    return (
      <View style={styles.dataCard}>
        <View style={styles.dataCardHeader}>
          <Text style={styles.dataTitle}>{item.household_no}</Text>
          <Text style={[styles.scopePill, canEdit ? styles.scopeEditable : styles.scopeReadOnly]}>
            {canEdit ? i18n.t('editable') : i18n.t('readOnly')}
          </Text>
        </View>
        <Text style={styles.dataSubtitle}>{item.household_address}</Text>
        <Text style={styles.dataMeta}>{purokLabel}</Text>
        <Text style={styles.dataMeta}>
          {item.is_active ? i18n.t('active') : i18n.t('inactive')} ·{' '}
          {item.is_social_aid_beneficiary ? 'Social aid' : 'Standard'}
        </Text>
        <View style={styles.inlineActionsRow}>
          <Pressable
            onPress={() => navigation.navigate('HouseholdDetails', { localId: item.local_id })}
            style={styles.inlineAction}
          >
            <Text style={styles.inlineActionText}>{i18n.t('viewDetails')}</Text>
          </Pressable>

          {canEdit ? (
            <>
              <Pressable
                onPress={() => navigation.navigate('HouseholdForm', { localId: item.local_id })}
                style={styles.inlineAction}
              >
                <Text style={styles.inlineActionText}>{i18n.t('edit')}</Text>
              </Pressable>
              <Pressable
                onPress={() =>
                  navigation.navigate('VisitForm', {
                    householdLocalId: item.local_id,
                  })
                }
                style={styles.inlineAction}
              >
                <Text style={styles.inlineActionText}>{i18n.t('createVisit')}</Text>
              </Pressable>
            </>
          ) : null}
        </View>
        {!canEdit ? (
          <Text style={styles.readOnlyNote}>{i18n.t('otherPurokReadOnly')}</Text>
        ) : null}
      </View>
    );
  }

  return (
    <KeyboardShiftView style={styles.screen}>
      <TopHeader
        title={i18n.t('directory')}
        onActionPress={() => navigation.navigate('SyncTab')}
      />

      <FlatList
        data={currentData}
        key={mode}
        keyExtractor={(item: any) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={styles.listContent}
        ListHeaderComponent={
          <View>
            <View style={styles.infoCard}>
              <Text style={styles.infoTitle}>{i18n.t('directoryIntroTitle')}</Text>
              <Text style={styles.infoBody}>{i18n.t('directoryIntroBody')}</Text>
              <Text style={styles.infoFootnote}>
                {i18n.t('searchBarangayOffline')} · {i18n.t('draftScopeNote')}
              </Text>
            </View>

            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder={i18n.t('searchDirectoryPlaceholder')}
              style={styles.search}
            />

            <View style={styles.segmentRow}>
              <Pressable
                onPress={() => setMode('residents')}
                style={[styles.segment, mode === 'residents' && styles.segmentActive]}
              >
                <Text style={[styles.segmentText, mode === 'residents' && styles.segmentTextActive]}>
                  {i18n.t('directoryResidents')}
                </Text>
              </Pressable>
              <Pressable
                onPress={() => setMode('households')}
                style={[styles.segment, mode === 'households' && styles.segmentActive]}
              >
                <Text style={[styles.segmentText, mode === 'households' && styles.segmentTextActive]}>
                  {i18n.t('directoryHouseholds')}
                </Text>
              </Pressable>
            </View>

            {mode === 'residents' ? (
              <View style={styles.filterWrap}>
                <Text style={styles.filterLabel}>PhilPEN Screening View</Text>
                <View style={styles.filterRow}>
                  <FilterChip
                    label="Pending 30 Days"
                    active={residentFilter === 'due30'}
                    onPress={() => setResidentFilter('due30')}
                  />
                  <FilterChip
                    label="All Adults"
                    active={residentFilter === 'allAdults'}
                    onPress={() => setResidentFilter('allAdults')}
                  />
                  <FilterChip
                    label="Assessed"
                    active={residentFilter === 'assessed'}
                    onPress={() => setResidentFilter('assessed')}
                  />
                  <FilterChip
                    label="All Residents"
                    active={residentFilter === 'allResidents'}
                    onPress={() => setResidentFilter('allResidents')}
                  />
                </View>
              </View>
            ) : null}

            {mode === 'residents' ? (
              <MenuCard
                title={i18n.t('newResidentDraft')}
                subtitle={i18n.t('newResidentDraftBody')}
                icon="person-add-outline"
                onPress={() => navigation.navigate('ResidentForm')}
              />
            ) : (
              <MenuCard
                title={i18n.t('newHouseholdDraft')}
                subtitle={i18n.t('newHouseholdDraftBody')}
                icon="home-outline"
                onPress={() => navigation.navigate('HouseholdForm')}
              />
            )}
          </View>
        }
        ListEmptyComponent={
          <View style={styles.emptyCard}>
            <Text style={styles.emptyText}>{i18n.t('noMatchingRecords')}</Text>
          </View>
        }
        renderItem={({ item }) =>
          mode === 'residents'
            ? renderResidentCard(item as ResidentRecord)
            : renderHouseholdCard(item as HouseholdRecord)
        }
      />
    </KeyboardShiftView>
  );
}

function FilterChip({
  label,
  active,
  onPress,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.filterChip, active && styles.filterChipActive]}
    >
      <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  listContent: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
  },
  infoCard: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: 24,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
  },
  infoTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: '700',
  },
  infoBody: {
    color: theme.colors.textMuted,
    lineHeight: 21,
    marginTop: 8,
  },
  infoFootnote: {
    color: theme.colors.primary,
    lineHeight: 20,
    marginTop: 12,
    fontWeight: '600',
  },
  search: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    paddingHorizontal: 16,
    paddingVertical: 15,
    marginBottom: theme.spacing.md,
    color: theme.colors.text,
  },
  segmentRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.md,
  },
  segment: {
    flex: 1,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    paddingVertical: 13,
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
    color: '#FFFFFF',
  },
  filterWrap: {
    marginBottom: theme.spacing.md,
  },
  filterLabel: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontWeight: '700',
    marginBottom: 10,
  },
  filterRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  filterChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  filterChipActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  filterChipText: {
    color: theme.colors.text,
    fontSize: 12,
    fontWeight: '700',
  },
  filterChipTextActive: {
    color: '#FFFFFF',
  },
  dataCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  dataCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: theme.spacing.sm,
  },
  dataTitle: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: '600',
  },
  dataSubtitle: {
    color: theme.colors.text,
    lineHeight: 20,
    marginTop: 8,
  },
  dataMeta: {
    color: theme.colors.textMuted,
    lineHeight: 20,
    marginTop: 6,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: 10,
  },
  riskBadge: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  riskBadgeText: {
    fontSize: 12,
    fontWeight: '700',
  },
  riskBadgeSuccess: {
    backgroundColor: '#DCFCE7',
  },
  riskBadgeSuccessText: {
    color: '#166534',
  },
  riskBadgeWarning: {
    backgroundColor: '#FEF3C7',
  },
  riskBadgeWarningText: {
    color: '#92400E',
  },
  riskBadgeDanger: {
    backgroundColor: '#FEE2E2',
  },
  riskBadgeDangerText: {
    color: '#B91C1C',
  },
  riskBadgeNeutral: {
    backgroundColor: theme.colors.surfaceMuted,
  },
  riskBadgeNeutralText: {
    color: theme.colors.textMuted,
  },
  dateBadge: {
    borderRadius: 999,
    backgroundColor: theme.colors.primarySoft,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  dateBadgeText: {
    color: theme.colors.primaryDark,
    fontSize: 12,
    fontWeight: '700',
  },
  scopePill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
    overflow: 'hidden',
    fontSize: 12,
    fontWeight: '700',
  },
  scopeEditable: {
    color: theme.colors.primary,
    backgroundColor: theme.colors.primarySoft,
  },
  scopeReadOnly: {
    color: theme.colors.textMuted,
    backgroundColor: theme.colors.surfaceMuted,
  },
  inlineActionsRow: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginTop: 14,
  },
  inlineAction: {
    marginTop: 14,
  },
  inlineActionText: {
    color: theme.colors.primary,
    fontWeight: '700',
  },
  readOnlyNote: {
    color: theme.colors.textMuted,
    marginTop: 14,
    fontWeight: '600',
  },
  emptyCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
  },
  emptyText: {
    color: theme.colors.textMuted,
    lineHeight: 21,
  },
});
