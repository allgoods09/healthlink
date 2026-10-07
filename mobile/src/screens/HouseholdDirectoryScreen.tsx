import React, { useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useIsFocused } from '@react-navigation/native';
import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { MenuCard } from '../components/MenuCard';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatPurokLabel } from '../lib/format';
import { getHouseholds, getHouseholdRequests, getHouseholdLookup } from '../lib/storage';
import { householdEditBlocked } from '../lib/householdWorkflow';
import { AppTheme } from '../theme';
import { HouseholdRecord } from '../types';

// Existing household directory presentation/workflow, extracted from the old Directory tab.
export function HouseholdDirectoryScreen({ navigation }: any) {
  const { assignment, dataVersion } = useAppContext();
  const isFocused = useIsFocused();
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const [search, setSearch] = useState('');
  const [households, setHouseholds] = useState<HouseholdRecord[]>([]);
  const [error, setError] = useState(false);
  const assignedPurokId = assignment?.purok?.id ?? null;
  useEffect(() => {
    if (!isFocused) return;
    let applicable = true;
    Promise.all([getHouseholds(search), getHouseholdRequests(search), getHouseholdLookup(search)]).then(groups => { if (applicable) { setHouseholds(groups.flat()); setError(false); } })
      .catch(() => { if (applicable) setError(true); });
    return () => { applicable = false; };
  }, [search, dataVersion, isFocused]);
  function renderHouseholdCard(item: HouseholdRecord) {
    const canEdit = assignedPurokId != null && item.purok_id === assignedPurokId && item.access_mode !== 'lookup' && !householdEditBlocked(item);
    const canVisit = item.access_mode === 'operational' && item.server_id != null;
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
          {item.access_mode === 'lookup' ? i18n.t('readOnly') : item.is_social_aid_beneficiary ? 'Social aid' : 'Standard'}
        </Text>
        <View style={styles.inlineActionsRow}>
          <Pressable
            onPress={() => navigation.navigate('HouseholdDetails', { localId: item.local_id })}
            style={styles.inlineAction}
          >
            <Text style={styles.inlineActionText}>{i18n.t('viewDetails')}</Text>
          </Pressable>

          {canEdit || canVisit ? (
            <>
              {canEdit ? <Pressable
                onPress={() => navigation.navigate('HouseholdForm', { localId: item.local_id })}
                style={styles.inlineAction}
              >
                <Text style={styles.inlineActionText}>{i18n.t('edit')}</Text>
              </Pressable> : null}
              {canVisit ? <Pressable
                onPress={() =>
                  navigation.navigate('VisitForm', {
                    householdLocalId: item.local_id,
                  })
                }
                style={styles.inlineAction}
              >
                <Text style={styles.inlineActionText}>{i18n.t('createVisit')}</Text>
              </Pressable> : null}
            </>
          ) : null}
        </View>
        {!canEdit ? (
          <Text style={styles.readOnlyNote}>{i18n.t('otherPurokReadOnly')}</Text>
        ) : null}
      </View>
    );
  }

  return <KeyboardShiftView style={styles.screen}>
    <FlatList data={households} keyExtractor={item => String(item.local_id)} keyboardShouldPersistTaps="handled"
      contentContainerStyle={styles.listContent} renderItem={({ item }) => renderHouseholdCard(item)}
      ListHeaderComponent={<View>
        <TextInput accessibilityLabel={i18n.t('householdNo')} value={search} onChangeText={setSearch}
          placeholder={i18n.t('searchDirectoryPlaceholder')} placeholderTextColor={theme.colors.placeholder} style={styles.search} />
        <MenuCard title={i18n.t('newHouseholdDraft')} subtitle={i18n.t('newHouseholdDraftBody')}
          icon="home-outline" onPress={() => navigation.navigate('HouseholdForm')} />
        {error ? <Text accessibilityRole="alert">{i18n.t('savedRecordsError')}</Text> : null}
      </View>}
      ListEmptyComponent={<Text style={styles.emptyText}>{i18n.t('noMatchingRecords')}</Text>} />
  </KeyboardShiftView>;
}
const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  listContent: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
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
  emptyText: {
    color: theme.colors.textMuted,
    lineHeight: 21,
  },
});
