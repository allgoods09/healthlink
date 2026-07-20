import React, { useEffect, useState } from 'react';
import {
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useIsFocused } from '@react-navigation/native';

import { MenuCard } from '../components/MenuCard';
import { TopHeader } from '../components/TopHeader';
import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { formatFriendlyDateTime } from '../lib/format';
import { getHouseholds, getResidents, getVisits } from '../lib/storage';
import { theme } from '../theme';
import { FieldVisitRecord } from '../types';

export function HomeScreen({ navigation }: any) {
  const isFocused = useIsFocused();
  const {
    assignment,
    dataVersion,
    lastSyncAt,
    pendingSyncCount,
    user,
  } = useAppContext();
  const [counts, setCounts] = useState({
    households: 0,
    residents: 0,
    visits: 0,
  });
  const [recentVisits, setRecentVisits] = useState<FieldVisitRecord[]>([]);

  useEffect(() => {
    if (!isFocused) {
      return;
    }

    async function loadDashboard() {
      const [households, residents, visits] = await Promise.all([
        getHouseholds(),
        getResidents(),
        getVisits(),
      ]);

      setCounts({
        households: households.length,
        residents: residents.length,
        visits: visits.length,
      });
      setRecentVisits(visits.slice(0, 3));
    }

    void loadDashboard();
  }, [dataVersion, isFocused]);

  return (
    <View style={styles.screen}>
      <TopHeader
        title={i18n.t('home')}
        onActionPress={() => navigation.navigate('SyncTab')}
      />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Text style={styles.heroKicker}>{i18n.t('appTitle')}</Text>
          <Text style={styles.heroTitle}>
            {user?.name ?? i18n.t('home')}
          </Text>
          <Text style={styles.heroSubtitle}>
            {assignment?.barangay?.name ?? 'Barangay'} · {assignment?.purok?.display_name ?? 'Unassigned Purok'}
          </Text>
          <View style={styles.heroMetaRow}>
            <View style={styles.heroPill}>
              <Text style={styles.heroPillText}>
                {i18n.t('pendingUploads')}: {pendingSyncCount}
              </Text>
            </View>
            <View style={styles.heroPill}>
              <Text style={styles.heroPillText}>
                {lastSyncAt
                  ? `${i18n.t('lastSync')}: ${formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt}`
                  : i18n.t('bootstrapPending')}
              </Text>
            </View>
          </View>
        </View>

        <Text style={styles.sectionTitle}>{i18n.t('quickActions')}</Text>

        <MenuCard
          title={i18n.t('openDirectory')}
          subtitle={i18n.t('openDirectoryBody')}
          icon="search-outline"
          onPress={() => navigation.navigate('DirectoryTab')}
        />
        <MenuCard
          title={i18n.t('recordVisitAction')}
          subtitle={i18n.t('recordVisitActionBody')}
          icon="clipboard-outline"
          onPress={() => navigation.navigate('VisitForm')}
        />
        <MenuCard
          title={i18n.t('newHouseholdDraft')}
          subtitle={i18n.t('newHouseholdDraftBody')}
          icon="home-outline"
          onPress={() => navigation.navigate('HouseholdForm')}
        />
        <MenuCard
          title={i18n.t('newResidentDraft')}
          subtitle={i18n.t('newResidentDraftBody')}
          icon="person-add-outline"
          onPress={() => navigation.navigate('ResidentForm')}
        />

        <Text style={styles.sectionTitle}>{i18n.t('offlineData')}</Text>

        <View style={styles.metricRow}>
          <View style={styles.metricCard}>
            <Text style={styles.metricNumber}>{counts.households}</Text>
            <Text style={styles.metricLabel}>{i18n.t('householdsOnDevice')}</Text>
          </View>
          <View style={styles.metricCard}>
            <Text style={styles.metricNumber}>{counts.residents}</Text>
            <Text style={styles.metricLabel}>{i18n.t('residentsOnDevice')}</Text>
          </View>
        </View>
        <View style={styles.metricRow}>
          <View style={styles.metricCardFull}>
            <Text style={styles.metricNumber}>{counts.visits}</Text>
            <Text style={styles.metricLabel}>{i18n.t('visitsOnDevice')}</Text>
          </View>
        </View>

        <Text style={styles.sectionTitle}>{i18n.t('recentVisits')}</Text>

        {recentVisits.length > 0 ? (
          recentVisits.map((visit) => (
            <MenuCard
              key={String(visit.local_id ?? visit.server_id ?? visit.mobile_uuid)}
              title={visit.household_no ?? 'Household Visit'}
              subtitle={[
                formatFriendlyDateTime(visit.visited_at) ?? visit.visited_at,
                visit.notes,
              ]
                .filter(Boolean)
                .join(' · ')}
              icon="time-outline"
              badge={String(visit.photos.length)}
            />
          ))
        ) : (
          <View style={styles.emptyCard}>
            <Text style={styles.emptyText}>{i18n.t('noRecentVisits')}</Text>
          </View>
        )}
      </ScrollView>
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
  },
  hero: {
    backgroundColor: theme.colors.primary,
    borderRadius: 28,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
  },
  heroKicker: {
    color: '#D9E7FA',
    fontSize: 12,
    letterSpacing: 1.1,
    textTransform: 'uppercase',
    fontWeight: '700',
  },
  heroTitle: {
    color: '#FFFFFF',
    fontSize: 28,
    fontWeight: '700',
    marginTop: 12,
  },
  heroSubtitle: {
    color: '#D9E7FA',
    marginTop: 8,
    lineHeight: 21,
  },
  heroMetaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  heroPill: {
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.14)',
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  heroPillText: {
    color: '#FFFFFF',
    fontWeight: '600',
    fontSize: 12,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: theme.spacing.sm,
    marginTop: theme.spacing.xs,
  },
  metricRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.sm,
  },
  metricCard: {
    flex: 1,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  metricCardFull: {
    flex: 1,
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
  metricNumber: {
    color: theme.colors.primary,
    fontSize: 28,
    fontWeight: '700',
  },
  metricLabel: {
    color: theme.colors.textMuted,
    marginTop: 6,
    lineHeight: 20,
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
