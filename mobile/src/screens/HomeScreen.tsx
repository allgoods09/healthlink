import React from 'react';
import { Image, ScrollView, Text, View } from 'react-native';

import { CompactActionRow, compactStyles } from '../components/CompactUi';
import { RootHeader } from '../components/RootHeader';
import { useAppContext, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { confirmLogout } from '../lib/confirmLogout';
import { formatFriendlyDateTime } from '../lib/format';

export function HomeScreen({ navigation }: any) {
  const styles = useThemedStyles(compactStyles);
  const {
    assignment,
    lastSyncAt,
    pendingSyncCount,
    requestConfirmation,
    signOut,
    unreadNotificationCount,
    user,
  } = useAppContext();

  return (
    <View style={styles.screen}>
      <RootHeader
        title={i18n.t('home')}
        unreadCount={unreadNotificationCount}
        onNotificationPress={() => navigation.navigate('Notifications')}
        onLogoutPress={() => void confirmLogout({ pendingSyncCount, requestConfirmation, signOut })}
      />

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.banner}>
          <View style={styles.bannerContext}>
            <Text accessibilityRole="header" style={[styles.welcome, styles.bannerText]}>
              {i18n.t('homeWelcome', { name: user?.name ?? i18n.t('home') })}
            </Text>
            <Text style={[styles.body, styles.bannerText]}>{i18n.t('homeRole')}</Text>
            <Text style={styles.bannerLabel}>{i18n.t('assignmentTitle')}</Text>
            <Text style={[styles.secondary, styles.bannerText]}>
              {assignment?.barangay?.name ?? 'Barangay'} · {assignment?.purok?.display_name ?? 'Unassigned Purok'}
            </Text>
          </View>
          <View style={styles.bannerBrand}>
            <Image accessible={false} source={require('../../assets/tubigon-logo.png')} resizeMode="contain" style={styles.bannerLogo} />
            <Text style={styles.bannerBrandName}>{i18n.t('appTitle')}</Text>
          </View>
        </View>

        <View style={styles.section}>
          <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t('quickActions')}</Text>
          <CompactActionRow label={i18n.t('openDirectory')} onPress={() => navigation.navigate('DirectoryTab')} />
          <CompactActionRow label={i18n.t('recordVisitAction')} onPress={() => navigation.navigate('VisitForm')} />
          <CompactActionRow label={i18n.t('newHouseholdDraft')} onPress={() => navigation.navigate('HouseholdForm')} />
          <CompactActionRow label={i18n.t('newResidentDraft')} onPress={() => navigation.navigate('ResidentForm')} />
        </View>

        <View style={styles.section}>
          <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t('sync')}</Text>
          <View style={styles.factRow}>
            <Text style={styles.factLabel}>{i18n.t('homePendingSync')}</Text>
            <Text style={styles.factValue}>{pendingSyncCount}</Text>
          </View>
          <View style={styles.factRow}>
            <Text style={styles.factLabel}>{i18n.t('lastSync')}</Text>
            <Text style={styles.factValue}>
              {lastSyncAt ? formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt : i18n.t('bootstrapPending')}
            </Text>
          </View>
          <CompactActionRow textAction label={i18n.t('sync')} onPress={() => navigation.navigate('SyncTab')} />
        </View>
      </ScrollView>
    </View>
  );
}
