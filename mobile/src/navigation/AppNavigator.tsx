import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import React from 'react';
import {
  ActivityIndicator,
  Text,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { theme } from '../theme';
import { ForgotPasswordScreen } from '../screens/ForgotPasswordScreen';
import { HouseholdFormScreen } from '../screens/HouseholdFormScreen';
import { HouseholdsScreen } from '../screens/HouseholdsScreen';
import { LoginScreen } from '../screens/LoginScreen';
import { ResidentFormScreen } from '../screens/ResidentFormScreen';
import { ResidentsScreen } from '../screens/ResidentsScreen';
import { SettingsScreen } from '../screens/SettingsScreen';
import { VisitFormScreen } from '../screens/VisitFormScreen';
import { VisitsScreen } from '../screens/VisitsScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.textMuted,
        tabBarStyle: {
          height: 68,
          paddingBottom: 10,
          paddingTop: 10,
          backgroundColor: theme.colors.surface,
          borderTopColor: theme.colors.border,
        },
      }}
    >
      <Tab.Screen name="HouseholdsTab" component={HouseholdsScreen} options={{ title: i18n.t('households') }} />
      <Tab.Screen name="ResidentsTab" component={ResidentsScreen} options={{ title: i18n.t('residents') }} />
      <Tab.Screen name="VisitsTab" component={VisitsScreen} options={{ title: i18n.t('visits') }} />
      <Tab.Screen name="SettingsTab" component={SettingsScreen} options={{ title: i18n.t('settings') }} />
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
  const { isReady, isAuthenticated } = useAppContext();

  if (!isReady) {
    return (
      <View
        style={{
          flex: 1,
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: theme.colors.background,
          padding: 24,
        }}
      >
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={{ marginTop: 16, color: theme.colors.textMuted }}>
          {i18n.t('loading')}
        </Text>
      </View>
    );
  }

  return (
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
              name="VisitForm"
              component={VisitFormScreen}
              options={{ title: i18n.t('createVisit') }}
            />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
