import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import React from 'react';
import {
  Linking,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { theme } from '../theme';
import { BrandSplash } from '../components/BrandSplash';
import { DirectoryScreen } from '../screens/DirectoryScreen';
import { ForgotPasswordScreen } from '../screens/ForgotPasswordScreen';
import { HouseholdFormScreen } from '../screens/HouseholdFormScreen';
import { HouseholdDetailsScreen } from '../screens/HouseholdDetailsScreen';
import { HomeScreen } from '../screens/HomeScreen';
import { InitialSyncScreen } from '../screens/InitialSyncScreen';
import { LoginScreen } from '../screens/LoginScreen';
import { MoreScreen } from '../screens/MoreScreen';
import { ResidentDetailsScreen } from '../screens/ResidentDetailsScreen';
import { ResidentFormScreen } from '../screens/ResidentFormScreen';
import { SyncScreen } from '../screens/SyncScreen';
import { VisitFormScreen } from '../screens/VisitFormScreen';
import { VisitsScreen } from '../screens/VisitsScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.tabMuted,
        tabBarStyle: {
          height: 78,
          paddingBottom: 12,
          paddingTop: 10,
          backgroundColor: theme.colors.surface,
          borderTopColor: theme.colors.border,
          borderTopWidth: 1,
        },
        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: '600',
        },
        tabBarIcon: ({ color, focused, size }) => {
          const iconMap: Record<string, React.ComponentProps<typeof Ionicons>['name']> = {
            HomeTab: focused ? 'home' : 'home-outline',
            DirectoryTab: focused ? 'search' : 'search-outline',
            VisitsTab: focused ? 'clipboard' : 'clipboard-outline',
            SyncTab: focused ? 'sync-circle' : 'sync-circle-outline',
            MoreTab: focused ? 'ellipsis-horizontal-circle' : 'ellipsis-horizontal-circle-outline',
          };

          return <Ionicons name={iconMap[route.name]} size={size ?? 22} color={color} />;
        },
      })}
    >
      <Tab.Screen name="HomeTab" component={HomeScreen} options={{ title: i18n.t('home') }} />
      <Tab.Screen name="DirectoryTab" component={DirectoryScreen} options={{ title: i18n.t('directory') }} />
      <Tab.Screen name="VisitsTab" component={VisitsScreen} options={{ title: i18n.t('visits') }} />
      <Tab.Screen name="SyncTab" component={SyncScreen} options={{ title: i18n.t('sync') }} />
      <Tab.Screen name="MoreTab" component={MoreScreen} options={{ title: i18n.t('more') }} />
    </Tab.Navigator>
  );
}

const navTheme = {
  ...DefaultTheme,
  colors: {
    ...DefaultTheme.colors,
    background: theme.colors.background,
    card: theme.colors.surface,
    text: theme.colors.text,
    border: theme.colors.border,
    primary: theme.colors.primary,
  },
};

export function AppNavigator() {
  const {
    appVersion,
    bootstrapCompleted,
    isReady,
    isAuthenticated,
    releaseCheck,
  } = useAppContext();
  const [dismissedUpdateVersionCode, setDismissedUpdateVersionCode] = React.useState<number | null>(null);

  React.useEffect(() => {
    if (!releaseCheck?.update.available) {
      setDismissedUpdateVersionCode(null);
    }
  }, [releaseCheck?.update.available]);

  if (!isReady) {
    return <BrandSplash loadingLabel={i18n.t('loading')} />;
  }

  const availableVersionCode = releaseCheck?.release?.version_code ?? null;
  const showUpdatePrompt = Boolean(
    releaseCheck?.update.available &&
      availableVersionCode &&
      dismissedUpdateVersionCode !== availableVersionCode
  );

  async function handleOpenUpdatePage() {
    const targetUrl =
      releaseCheck?.release?.update_page_url ?? releaseCheck?.release?.download_url;

    if (!targetUrl) {
      return;
    }

    try {
      await Linking.openURL(targetUrl);
      if (availableVersionCode) {
        setDismissedUpdateVersionCode(availableVersionCode);
      }
    } catch {
      // Keep the prompt visible if the update page cannot be opened.
    }
  }

  return (
    <>
      <NavigationContainer theme={navTheme}>
        <Stack.Navigator
          screenOptions={{
            headerShadowVisible: false,
            headerStyle: { backgroundColor: theme.colors.surface },
            headerTintColor: theme.colors.text,
            contentStyle: { backgroundColor: theme.colors.background },
          }}
        >
          {!isAuthenticated ? (
            <>
              <Stack.Screen
                name="Login"
                component={LoginScreen}
                options={{ headerShown: false }}
              />
              <Stack.Screen
                name="ForgotPassword"
                component={ForgotPasswordScreen}
                options={{ title: i18n.t('forgotPassword') }}
              />
            </>
          ) : !bootstrapCompleted ? (
            <Stack.Screen
              name="InitialSync"
              component={InitialSyncScreen}
              options={{ headerShown: false }}
            />
          ) : (
            <>
              <Stack.Screen
                name="MainTabs"
                component={MainTabs}
                options={{ headerShown: false }}
              />
              <Stack.Screen
                name="HouseholdForm"
                component={HouseholdFormScreen}
                options={{ title: i18n.t('createHousehold') }}
              />
              <Stack.Screen
                name="ResidentForm"
                component={ResidentFormScreen}
                options={{ title: i18n.t('createResident') }}
              />
              <Stack.Screen
                name="ResidentDetails"
                component={ResidentDetailsScreen}
                options={{ title: i18n.t('residentProfile') }}
              />
              <Stack.Screen
                name="HouseholdDetails"
                component={HouseholdDetailsScreen}
                options={{ title: i18n.t('householdProfile') }}
              />
              <Stack.Screen
                name="VisitForm"
                component={VisitFormScreen}
                options={{ title: i18n.t('createVisit') }}
              />
            </>
          )}
        </Stack.Navigator>
      </NavigationContainer>

      <Modal
        transparent
        animationType="fade"
        visible={showUpdatePrompt}
        onRequestClose={() => {
          if (availableVersionCode) {
            setDismissedUpdateVersionCode(availableVersionCode);
          }
        }}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalEyebrow}>
              {releaseCheck?.update.required
                ? i18n.t('updateRequiredTitle')
                : i18n.t('updateAvailableTitle')}
            </Text>
            <Text style={styles.modalTitle}>
              {releaseCheck?.release?.release_title ?? i18n.t('appTitle')}
            </Text>
            <Text style={styles.modalBody}>
              {releaseCheck?.update.message ?? i18n.t('updateAvailableBody')}
            </Text>

            <View style={styles.versionPanel}>
              <Text style={styles.versionLine}>
                {i18n.t('currentAppVersion')}: {appVersion}
              </Text>
              <Text style={styles.versionLine}>
                {i18n.t('latestAvailableVersion')}: {releaseCheck?.release?.version_name ?? 'N/A'}
              </Text>
            </View>

            <View style={styles.modalActions}>
              <Pressable onPress={() => void handleOpenUpdatePage()} style={styles.primaryButton}>
                <Text style={styles.primaryButtonText}>{i18n.t('updateNow')}</Text>
              </Pressable>
              <Pressable
                onPress={() => {
                  if (availableVersionCode) {
                    setDismissedUpdateVersionCode(availableVersionCode);
                  }
                }}
                style={styles.secondaryButton}
              >
                <Text style={styles.secondaryButtonText}>{i18n.t('continueOffline')}</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(7, 22, 43, 0.48)',
    justifyContent: 'center',
    padding: theme.spacing.lg,
  },
  modalCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
  },
  modalEyebrow: {
    color: theme.colors.primary,
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  modalTitle: {
    color: theme.colors.text,
    fontSize: 22,
    fontWeight: '700',
    marginTop: 10,
  },
  modalBody: {
    color: theme.colors.textMuted,
    lineHeight: 22,
    marginTop: 10,
  },
  versionPanel: {
    marginTop: theme.spacing.md,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.surfaceMuted,
    padding: theme.spacing.md,
    gap: 6,
  },
  versionLine: {
    color: theme.colors.text,
    fontSize: 13,
    fontWeight: '600',
  },
  modalActions: {
    gap: theme.spacing.sm,
    marginTop: theme.spacing.lg,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
    borderRadius: 18,
    alignItems: 'center',
    paddingVertical: 16,
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '700',
  },
  secondaryButton: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    alignItems: 'center',
    paddingVertical: 16,
    backgroundColor: theme.colors.surface,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontSize: 16,
    fontWeight: '700',
  },
});
