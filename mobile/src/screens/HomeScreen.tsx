import React from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { RootHeader } from '../components/RootHeader';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { confirmLogout } from '../lib/confirmLogout';
import { formatFriendlyDateTime } from '../lib/format';
import { AppTheme } from '../theme';

export function HomeScreen({ navigation }: any) {
  const styles = useThemedStyles(homeStyles);
  const theme = useAppTheme();
  const { width, fontScale } = useWindowDimensions();
  const reflow = width < 380 || fontScale > 1.2;
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
        <View style={[styles.banner, reflow && styles.bannerReflow]}>
          <View pointerEvents="none" accessible={false} importantForAccessibility="no-hide-descendants" style={styles.wave} />
          <View style={[styles.bannerContext, reflow && styles.contextReflow]}>
            <Text accessibilityRole="header" style={[styles.welcome, styles.bannerText]}>
              {i18n.t('homeWelcome', { name: user?.name?.trim() || 'BHW' })}
            </Text>
            <Text style={[styles.body, styles.bannerText]}>{i18n.t('homeRole')}</Text>
            <View accessible={false} style={styles.separator} />
            <Text style={styles.bannerLabel}>{i18n.t('assignmentTitle')}</Text>
            <Text style={[styles.secondary, styles.bannerText]}>
              {assignment?.barangay?.name ?? 'Barangay'} · {assignment?.purok?.display_name ?? 'Unassigned Purok'}
            </Text>
          </View>
          <View style={[styles.bannerBrand, reflow && styles.brandReflow]}>
            <Image accessible={false} source={require('../../assets/tubigon-logo.png')} resizeMode="contain" style={styles.bannerLogo} />
            <Text style={styles.bannerBrandName}>{i18n.t('appTitle')}</Text>
          </View>
        </View>

        <View style={styles.section}>
          <Text accessibilityRole="header" style={styles.sectionTitle}>{i18n.t('quickActions')}</Text>
          {([
            ['openDirectory', 'people-outline', 'DirectoryTab'],
            ['recordVisitAction', 'clipboard-outline', 'VisitForm'],
            ['newHouseholdDraft', 'document-text-outline', 'HouseholdForm'],
            ['newResidentDraft', 'person-add-outline', 'ResidentForm'],
          ] as const).map(([label, icon, destination]) => <Pressable key={label}
            accessibilityRole="button" accessibilityLabel={i18n.t(label)} style={styles.action}
            onPress={() => navigation.navigate(destination)}>
            <View accessible={false} importantForAccessibility="no-hide-descendants" style={styles.actionIcon}>
              <Ionicons accessible={false} name={icon} size={24} color={theme.colors.primary} />
            </View>
            <Text style={styles.actionLabel}>{i18n.t(label)}</Text>
            <Ionicons accessible={false} name="chevron-forward" size={20} color={theme.colors.textMuted} />
          </Pressable>)}
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
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('sync')} style={styles.syncAction}
            onPress={() => navigation.navigate('SyncTab')}>
            <Ionicons accessible={false} name="sync-outline" size={24} color={theme.colors.primary} />
            <Text style={styles.syncLabel}>{i18n.t('sync')}</Text>
          </Pressable>
        </View>
      </ScrollView>
    </View>
  );
}

const homeStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: 16, paddingTop: 12, paddingBottom: 24, gap: 24 },
  banner: { backgroundColor: theme.colors.brandBackground, borderRadius: 8, padding: 18,
    minHeight: 196, flexDirection: 'row', alignItems: 'center', gap: 16, overflow: 'hidden' },
  bannerReflow: { flexDirection: 'column', alignItems: 'stretch' },
  // A clipped tonal curve adds depth without a new asset or gradient dependency.
  wave: { position: 'absolute', width: 400, height: 260, borderRadius: 200, right: -150, bottom: -180,
    backgroundColor: theme.colors.accent, opacity: 0.2, transform: [{ rotate: '-25deg' }] },
  bannerContext: { flex: 1, minWidth: 0, gap: 8 },
  contextReflow: { flex: 0 },
  bannerText: { color: theme.colors.textOnBrand },
  welcome: { fontSize: 26, lineHeight: 32, fontWeight: '700', flexShrink: 1 },
  body: { fontSize: 15, lineHeight: 22, flexShrink: 1 },
  separator: { width: 32, height: 2, borderRadius: 1, backgroundColor: theme.colors.heroTextMuted, marginVertical: 6 },
  bannerLabel: { color: theme.colors.heroTextMuted, fontSize: 13, lineHeight: 19 },
  secondary: { fontSize: 16, lineHeight: 24, fontWeight: '600', flexShrink: 1 },
  bannerBrand: { width: 112, maxWidth: '100%', flexShrink: 0, alignItems: 'center', gap: 8 },
  brandReflow: { alignSelf: 'center', width: '100%' },
  bannerLogo: { width: 104, height: 104 },
  bannerBrandName: { color: theme.colors.textOnBrand, fontSize: 15, lineHeight: 22, fontWeight: '700', textAlign: 'center' },
  section: { gap: 10 },
  sectionTitle: { fontSize: 20, lineHeight: 26, fontWeight: '700', color: theme.colors.text, marginBottom: 4 },
  action: { minHeight: 64, borderRadius: 6, borderWidth: 1, borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface, paddingHorizontal: 12, paddingVertical: 10,
    flexDirection: 'row', alignItems: 'center', gap: 12 },
  actionIcon: { width: 40, height: 40, borderRadius: 20, flexShrink: 0,
    backgroundColor: theme.colors.infoSoft, alignItems: 'center', justifyContent: 'center' },
  actionLabel: { fontSize: 16, lineHeight: 23, fontWeight: '600', color: theme.colors.text, flex: 1, minWidth: 0 },
  factRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'baseline', gap: 8 },
  factLabel: { fontSize: 14, lineHeight: 22, color: theme.colors.textMuted, flexBasis: 120, flexGrow: 1, minWidth: 0 },
  factValue: { fontSize: 16, lineHeight: 22, color: theme.colors.text, flexBasis: 150, flexGrow: 1, flexShrink: 1, minWidth: 0 },
  syncAction: { alignSelf: 'flex-start', minHeight: 48, maxWidth: '100%', borderRadius: 6, borderWidth: 1,
    borderColor: theme.colors.primary, backgroundColor: theme.colors.surface, paddingHorizontal: 18, paddingVertical: 10,
    flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  syncLabel: { color: theme.colors.primary, fontSize: 16, lineHeight: 24, fontWeight: '600', flexShrink: 1 },
});
