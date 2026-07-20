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

import { MenuCard } from '../components/MenuCard';
import { TopHeader } from '../components/TopHeader';
import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { getVisits } from '../lib/storage';
import { theme } from '../theme';
import { FieldVisitRecord } from '../types';

export function VisitsScreen({ navigation }: any) {
  const isFocused = useIsFocused();
  const { bootstrapCompleted, dataVersion, statusMessage, syncNow } = useAppContext();
  const [search, setSearch] = useState('');
  const [records, setRecords] = useState<FieldVisitRecord[]>([]);

  useEffect(() => {
    if (!isFocused) return;

    void getVisits(search).then(setRecords);
  }, [dataVersion, isFocused, search]);

  return (
    <View style={styles.screen}>
      <TopHeader
        title={i18n.t('visits')}
        onActionPress={() => navigation.navigate('SyncTab')}
      />

      <FlatList
        data={records}
        keyExtractor={(item) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.headerBlock}>
            <MenuCard
              title={i18n.t('startVisitNow')}
              subtitle={i18n.t('startVisitNowBody')}
              icon="clipboard-outline"
              onPress={() => navigation.navigate('VisitForm')}
              tone="primary"
            />

            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder={i18n.t('visits')}
              style={styles.search}
            />

            <Text style={styles.sectionTitle}>{i18n.t('visitHistoryTitle')}</Text>

            {!bootstrapCompleted && records.length === 0 ? (
              <View style={styles.emptyState}>
                <Text style={styles.emptyTitle}>{i18n.t('syncRequiredTitle')}</Text>
                <Text style={styles.emptyBody}>{i18n.t('noDataSyncPrompt')}</Text>
                <Pressable onPress={syncNow} style={styles.primaryButton}>
                  <Text style={styles.primaryButtonText}>{i18n.t('syncNow')}</Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        }
        ListFooterComponent={
          statusMessage ? <Text style={styles.statusText}>{statusMessage}</Text> : null
        }
        ListEmptyComponent={
          bootstrapCompleted ? (
            <View style={styles.emptyHistoryCard}>
              <Text style={styles.emptyBody}>{i18n.t('noRecentVisits')}</Text>
            </View>
          ) : null
        }
        renderItem={({ item }) => (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>{item.household_no ?? 'Household'}</Text>
              <Text style={styles.badge}>{item.sync_status}</Text>
            </View>
            <Text style={styles.cardText}>{item.visited_at}</Text>
            <Text style={styles.cardText}>{item.notes || 'No notes'}</Text>
            <Text style={styles.cardMeta}>{item.photos.length} photo(s)</Text>
            <Pressable
              onPress={() => navigation.navigate('VisitForm', { localId: item.local_id })}
              style={styles.textAction}
            >
              <Text style={styles.textActionLabel}>{i18n.t('edit')}</Text>
            </Pressable>
          </View>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  headerBlock: {
    padding: theme.spacing.md,
  },
  search: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    paddingHorizontal: 16,
    paddingVertical: 15,
    color: theme.colors.text,
    marginBottom: theme.spacing.md,
  },
  list: {
    paddingBottom: theme.spacing.xl,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: theme.spacing.sm,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.md,
    marginHorizontal: theme.spacing.md,
    marginBottom: theme.spacing.md,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  cardTitle: {
    color: theme.colors.text,
    fontSize: 17,
    fontWeight: '700',
  },
  badge: {
    color: theme.colors.primary,
    fontWeight: '700',
    textTransform: 'capitalize',
  },
  cardText: {
    marginTop: 8,
    color: theme.colors.text,
  },
  cardMeta: {
    marginTop: 8,
    color: theme.colors.textMuted,
  },
  textAction: {
    marginTop: 12,
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
    paddingHorizontal: theme.spacing.md,
  },
  emptyHistoryCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
    marginHorizontal: theme.spacing.md,
  },
});
