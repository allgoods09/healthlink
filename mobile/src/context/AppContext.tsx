import NetInfo from '@react-native-community/netinfo';
import React, {
  createContext,
  ReactNode,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { Platform } from 'react-native';

import { setLocale, SupportedLocale } from '../i18n';
import {
  mobileBootstrap,
  mobileForgotPassword,
  mobileLogin,
  mobileLogout,
  mobileSync,
  mobileVerify,
} from '../lib/api';
import {
  applyResolvedRecords,
  clearToken,
  clearOperationalData,
  getAppState,
  getPendingSyncPayload,
  hasBootstrapData,
  hasPendingChanges,
  initializeStorage,
  loadToken,
  replaceBootstrapData,
  setAppState,
  storeToken,
} from '../lib/storage';
import { MobileAssignment, MobileUser } from '../types';

type AppContextValue = {
  isReady: boolean;
  isAuthenticated: boolean;
  isOnline: boolean;
  isSyncing: boolean;
  bootstrapCompleted: boolean;
  token: string | null;
  user: MobileUser | null;
  assignment: MobileAssignment | null;
  language: SupportedLocale;
  apiBaseUrl: string;
  lastSyncAt: string | null;
  statusMessage: string | null;
  dataVersion: number;
  signIn: (params: { email: string; password: string; apiBaseUrl: string }) => Promise<void>;
  requestPasswordReset: (params: { email: string; apiBaseUrl: string }) => Promise<string>;
  signOut: () => Promise<void>;
  syncNow: (mode?: 'manual' | 'background') => Promise<void>;
  setLanguagePreference: (locale: SupportedLocale) => Promise<void>;
  setServerUrl: (baseUrl: string) => Promise<void>;
  bumpDataVersion: () => void;
};

const AppContext = createContext<AppContextValue | null>(null);

const APP_VERSION = '1.0.0';

export function AppProvider({ children }: { children: ReactNode }) {
  const [isReady, setIsReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<MobileUser | null>(null);
  const [assignment, setAssignment] = useState<MobileAssignment | null>(null);
  const [language, setLanguageState] = useState<SupportedLocale>('en');
  const [apiBaseUrl, setApiBaseUrlState] = useState('http://localhost:8000');
  const [isOnline, setIsOnline] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [bootstrapCompleted, setBootstrapCompleted] = useState(false);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(null);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [dataVersion, setDataVersion] = useState(0);

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      setIsOnline(Boolean(state.isConnected && state.isInternetReachable !== false));
    });

    return unsubscribe;
  }, []);

  useEffect(() => {
    async function boot() {
      await initializeStorage();

      const [storedToken, storedLanguage, storedApiBaseUrl, storedLastSyncAt, bootstrapped] =
        await Promise.all([
          loadToken(),
          getAppState('language'),
          getAppState('api_base_url'),
          getAppState('last_sync_at'),
          hasBootstrapData(),
        ]);
      const storedUser = await getAppState('session_user');
      const storedAssignment = await getAppState('session_assignment');

      const nextLanguage =
        storedLanguage === 'ceb' || storedLanguage === 'en'
          ? storedLanguage
          : 'en';

      setLocale(nextLanguage);
      setLanguageState(nextLanguage);
      setApiBaseUrlState(storedApiBaseUrl ?? 'http://localhost:8000');
      setLastSyncAt(storedLastSyncAt || null);
      setBootstrapCompleted(bootstrapped);

      if (storedUser) {
        setUser(JSON.parse(storedUser) as MobileUser);
      }

      if (storedAssignment) {
        setAssignment(JSON.parse(storedAssignment) as MobileAssignment);
      }

      if (storedToken) {
        setToken(storedToken);
      }

      setIsReady(true);
    }

    void boot();
  }, []);

  useEffect(() => {
    if (!token || !isOnline) {
      return;
    }

    void mobileVerify(apiBaseUrl, token).catch(async () => {
      await signOut(true);
    });
  }, [apiBaseUrl, isOnline, token]);

  useEffect(() => {
    if (!isReady || !token || !isOnline || isSyncing) {
      return;
    }

    void syncNow('background');
  }, [apiBaseUrl, dataVersion, isOnline, isReady, isSyncing, token]);

  async function signIn({
    email,
    password,
    apiBaseUrl: nextApiBaseUrl,
  }: {
    email: string;
    password: string;
    apiBaseUrl: string;
  }) {
    const response = await mobileLogin(nextApiBaseUrl, {
      email,
      password,
      device_name: `BHW ${Platform.OS === 'ios' ? 'iPhone' : 'Android'} Device`,
      device_platform: Platform.OS,
      app_version: APP_VERSION,
    });

    await storeToken(response.token);
    await setAppState('api_base_url', nextApiBaseUrl);
    await setAppState('session_user', JSON.stringify(response.user));

    setApiBaseUrlState(nextApiBaseUrl);
    setToken(response.token);
    setUser(response.user);
    setStatusMessage(null);

    if (isOnline) {
      try {
        await clearOperationalData();
        const bootstrap = await mobileBootstrap(nextApiBaseUrl, response.token);
        await replaceBootstrapData(bootstrap);
        await setAppState('session_assignment', JSON.stringify(bootstrap.assignment));
        setAssignment(bootstrap.assignment);
        setBootstrapCompleted(true);
        setLastSyncAt(bootstrap.server_time);
        setDataVersion((current) => current + 1);
      } catch (error) {
        setBootstrapCompleted(false);
        setStatusMessage(
          error instanceof Error
            ? error.message
            : 'Login worked, but the initial sync did not finish.'
        );
      }
    } else {
      setBootstrapCompleted(false);
      setStatusMessage('You are offline. Connect to the internet to download your assigned records.');
    }
  }

  async function requestPasswordReset({
    email,
    apiBaseUrl: nextApiBaseUrl,
  }: {
    email: string;
    apiBaseUrl: string;
  }) {
    const response = await mobileForgotPassword(nextApiBaseUrl, email);
    await setAppState('api_base_url', nextApiBaseUrl);
    setApiBaseUrlState(nextApiBaseUrl);

    return response.message;
  }

  async function signOut(silent = false) {
    if (token && !silent) {
      try {
        await mobileLogout(apiBaseUrl, token);
      } catch {
        // Keep local logout reliable even if the remote token is already gone.
      }
    }

    await clearToken();
    await clearOperationalData();
    setToken(null);
    setUser(null);
    setAssignment(null);
    setBootstrapCompleted(false);
    setLastSyncAt(null);
    setStatusMessage(null);
    setDataVersion((current) => current + 1);
  }

  async function syncNow(mode: 'manual' | 'background' = 'manual') {
    if (!token || !isOnline || isSyncing) {
      return;
    }

    setIsSyncing(true);

    try {
      const pending = await hasPendingChanges();

      if (pending) {
        const payload = await getPendingSyncPayload();
        const syncResponse = await mobileSync(apiBaseUrl, token, {
          ...payload,
          device_name: `BHW ${Platform.OS === 'ios' ? 'iPhone' : 'Android'} Device`,
          app_version: APP_VERSION,
        });

        await applyResolvedRecords(syncResponse.resolved_records);
        setLastSyncAt(syncResponse.synced_at);

        if (syncResponse.status !== 'success') {
          setStatusMessage(
            syncResponse.failed_records[0]?.message ??
              'Some records could not be uploaded yet.'
          );
          setDataVersion((current) => current + 1);

          return;
        }
      }

      const bootstrap = await mobileBootstrap(apiBaseUrl, token);
      await replaceBootstrapData(bootstrap);
      await setAppState('session_assignment', JSON.stringify(bootstrap.assignment));
      setAssignment(bootstrap.assignment);
      setBootstrapCompleted(true);
      setLastSyncAt(bootstrap.server_time);
      setStatusMessage(
        mode === 'manual' ? 'Sync complete. Local records are up to date.' : null
      );
      setDataVersion((current) => current + 1);
    } catch (error) {
      setStatusMessage(
        error instanceof Error ? error.message : 'Sync failed. Please try again.'
      );
    } finally {
      setIsSyncing(false);
    }
  }

  async function setLanguagePreference(locale: SupportedLocale) {
    setLocale(locale);
    setLanguageState(locale);
    await setAppState('language', locale);
  }

  async function setServerUrl(baseUrl: string) {
    await setAppState('api_base_url', baseUrl);
    setApiBaseUrlState(baseUrl);
  }

  const value = useMemo<AppContextValue>(
    () => ({
      isReady,
      isAuthenticated: Boolean(token),
      isOnline,
      isSyncing,
      bootstrapCompleted,
      token,
      user,
      assignment,
      language,
      apiBaseUrl,
      lastSyncAt,
      statusMessage,
      dataVersion,
      signIn,
      requestPasswordReset,
      signOut: () => signOut(false),
      syncNow,
      setLanguagePreference,
      setServerUrl,
      bumpDataVersion: () => setDataVersion((current) => current + 1),
    }),
    [
      apiBaseUrl,
      assignment,
      bootstrapCompleted,
      dataVersion,
      isOnline,
      isReady,
      isSyncing,
      language,
      lastSyncAt,
      statusMessage,
      token,
      user,
    ]
  );

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useAppContext() {
  const context = useContext(AppContext);

  if (!context) {
    throw new Error('useAppContext must be used within AppProvider');
  }

  return context;
}
