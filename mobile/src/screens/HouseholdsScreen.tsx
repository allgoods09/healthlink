import { useIsFocused } from '@react-navigation/native';
import React, { useEffect, useState } from 'react';
import {
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { getHouseholds } from '../lib/storage';
import { theme } from '../theme';
import { HouseholdRecord } from '../types';

export function HouseholdsScreen({ navigation }: any) {
  const isFocused = useIsFocused();
  const {
    assignment,
    bootstrapCompleted,
    dataVersion,
    isOnline,
    isSyncing,
    statusMessage,
    syncNow,
  } = useAppContext();
  const [search, setSearch] = useState('');
  const [records, setRecords] = useState<HouseholdRecord[]>([]);

  useEffect(() => {
    if (!isFocused) return;

    void getHouseholds(search).then(setRecords);
  }, [dataVersion, isFocused, search]);

  return (
    <View style={styles.screen}>
      <View style={styles.headerCard}>
        <Text style={styles.headerTitle}>{assignment?.purok?.display_name ?? i18n.t('households')}</Text>
        <Text style={styles.headerSubtitle}>
          {assignment?.barangay?.name ?? i18n.t('syncRequiredBody')}
        </Text>
      </View>

      <TextInput
        value={search}
        onChangeText={setSearch}
        placeholder={i18n.t('households')}
        style={styles.search}
      />

      {!bootstrapCompleted && records.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>{i18n.t('syncRequiredTitle')}</Text>
          <Text style={styles.emptyBody}>{i18n.t('noDataSyncPrompt')}</Text>
          <Pressable
            onPress={syncNow}
            style={styles.primaryButton}
            disabled={!isOnline || isSyncing}
          >
            <Text style={styles.primaryButtonText}>
              {isSyncing ? i18n.t('syncing') : i18n.t('syncNow')}
            </Text>
          </Pressable>
        </View>
      ) : (
        <FlatList
          data={records}
          keyExtractor={(item) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <View style={styles.toolbar}>
              <Pressable
                onPress={() => navigation.navigate('HouseholdForm')}
                style={styles.secondaryButton}
              >
                <Text style={styles.secondaryButtonText}>{i18n.t('createHousehold')}</Text>
              </Pressable>
              <Pressable onPress={syncNow} style={styles.secondaryButton}>
                <Text style={styles.secondaryButtonText}>{i18n.t('syncNow')}</Text>
              </Pressable>
            </View>
          }
          ListFooterComponent={
            statusMessage ? (
              <Text style={styles.statusText}>{statusMessage}</Text>
            ) : null
          }
          renderItem={({ item }) => (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.cardTitle}>{item.household_no}</Text>
                <Text style={styles.badge}>{item.sync_status}</Text>
              </View>
              <Text style={styles.cardText}>{item.household_address}</Text>
              <Text style={styles.cardMeta}>
                {item.is_social_aid_beneficiary ? 'Social aid' : 'Standard'} •{' '}
                {item.is_active ? 'Active' : 'Inactive'}
              </Text>
              <View style={styles.cardActions}>
                <Pressable
                  onPress={() => navigation.navigate('HouseholdForm', { localId: item.local_id })}
                  style={styles.textAction}
                >
                  <Text style={styles.textActionLabel}>Edit</Text>
                </Pressable>
                <Pressable
                  onPress={() =>
                    navigation.navigate('VisitForm', {
                      householdLocalId: item.local_id,
                    })
                  }
                  style={styles.textAction}
                >
                  <Text style={styles.textActionLabel}>{i18n.t('createVisit')}</Text>
                </Pressable>
              </View>
            </View>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.background,
  },
  headerCard: {
    backgroundColor: theme.colors.primarySoft,
    padding: theme.spacing.md,
    borderRadius: theme.radius.lg,
    marginBottom: theme.spacing.md,
  },
  headerTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.text,
  },
  headerSubtitle: {
    marginTop: 6,
    color: theme.colors.textMuted,
  },
  search: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  list: {
    paddingTop: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
    gap: theme.spacing.md,
  },
  toolbar: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.sm,
  },
  secondaryButton: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '600',
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  badge: {
    color: theme.colors.primary,
    fontWeight: '700',
    textTransform: 'capitalize',
  },
  cardText: {
    marginTop: 8,
    color: theme.colors.text,
    lineHeight: 20,
  },
  cardMeta: {
    marginTop: 10,
    color: theme.colors.textMuted,
  },
  cardActions: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginTop: 14,
  },
  textAction: {
    paddingVertical: 4,
  },
  textActionLabel: {
    color: theme.colors.primary,
    fontWeight: '700',
  },
  emptyState: {
    marginTop: theme.spacing.lg,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
  },
  emptyTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: '700',
  },
  emptyBody: {
    marginTop: 10,
    color: theme.colors.textMuted,
    lineHeight: 22,
  },
  primaryButton: {
    marginTop: 16,
    backgroundColor: theme.colors.primary,
    paddingVertical: 14,
    borderRadius: theme.radius.md,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
  statusText: {
    color: theme.colors.textMuted,
    textAlign: 'center',
  },
});
