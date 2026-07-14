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
      <TextInput
        value={search}
        onChangeText={setSearch}
        placeholder={i18n.t('visits')}
        style={styles.search}
      />

      {!bootstrapCompleted && records.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>{i18n.t('syncRequiredTitle')}</Text>
          <Text style={styles.emptyBody}>{i18n.t('noDataSyncPrompt')}</Text>
          <Pressable onPress={() => syncNow('manual')} style={styles.primaryButton}>
            <Text style={styles.primaryButtonText}>{i18n.t('syncNow')}</Text>
          </Pressable>
        </View>
      ) : (
        <FlatList
          data={records}
          keyExtractor={(item) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <Pressable
              onPress={() => navigation.navigate('VisitForm')}
              style={styles.secondaryButton}
            >
              <Text style={styles.secondaryButtonText}>{i18n.t('createVisit')}</Text>
            </Pressable>
          }
          ListFooterComponent={
            statusMessage ? <Text style={styles.statusText}>{statusMessage}</Text> : null
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
                <Text style={styles.textActionLabel}>Edit</Text>
              </Pressable>
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
    backgroundColor: theme.colors.background,
    padding: theme.spacing.md,
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
  },
  secondaryButton: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: theme.spacing.md,
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
  },
});
