import NetInfo from '@react-native-community/netinfo';
import React, {
  createContext,
  ReactNode,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { AppState, Platform } from 'react-native';

import { i18n, setLocale, SupportedLocale } from '../i18n';
import {
  mobileCheckRelease,
  mobileBootstrap,
  mobileForgotPassword,
  mobileLogin,
  mobileLogout,
  mobileSync,
  mobileVerify,
} from '../lib/api';
import { MOBILE_API_BASE_URL } from '../lib/config';
import {
  applyResolvedRecords,
  clearToken,
  clearOperationalData,
  getAppState,
  getDatasetAssignment,
  getDatasetOwnerUserId,
  getPendingChangeSummary,
  getPendingSyncPayload,
  hasBootstrapData,
  initializeStorage,
  loadToken,
  replaceBootstrapData,
  setAppState,
  storeToken,
} from '../lib/storage';
import { MobileAssignment, MobileReleaseCheck, MobileUser } from '../types';

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
  appVersion: string;
  appVersionCode: number;
  lastSyncAt: string | null;
  statusMessage: string | null;
  dataVersion: number;
  initialSyncInProgress: boolean;
  pendingSyncCount: number;
  releaseCheck: MobileReleaseCheck | null;
  signIn: (params: { email: string; password: string }) => Promise<void>;
  requestPasswordReset: (email: string) => Promise<string>;
  signOut: () => Promise<void>;
  syncNow: () => Promise<void>;
  retryInitialSync: () => Promise<void>;
  refreshReleaseStatus: () => Promise<void>;
  setLanguagePreference: (locale: SupportedLocale) => Promise<void>;
  bumpDataVersion: () => void;
};

const AppContext = createContext<AppContextValue | null>(null);

const appConfig = require('../../app.json');
const APP_VERSION = appConfig.expo?.version ?? '1.0.0';
const APP_VERSION_CODE = Number(appConfig.expo?.android?.versionCode ?? 1);
const MINIMUM_STARTUP_SPLASH_MS = 1000;

export function AppProvider({ children }: { children: ReactNode }) {
  const [isReady, setIsReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<MobileUser | null>(null);
  const [assignment, setAssignment] = useState<MobileAssignment | null>(null);
  const [language, setLanguageState] = useState<SupportedLocale>('en');
  const [isOnline, setIsOnline] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [bootstrapCompleted, setBootstrapCompleted] = useState(false);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(null);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [dataVersion, setDataVersion] = useState(0);
  const [initialSyncInProgress, setInitialSyncInProgress] = useState(false);
  const [pendingSyncCount, setPendingSyncCount] = useState(0);
  const [releaseCheck, setReleaseCheck] = useState<MobileReleaseCheck | null>(null);

  async function refreshPendingSyncCount() {
    const summary = await getPendingChangeSummary();
    setPendingSyncCount(summary.total);

    return summary;
  }

  async function hydrateBootstrapSession(bootstrap: Awaited<ReturnType<typeof mobileBootstrap>>) {
    await replaceBootstrapData(bootstrap);
    await setAppState('session_assignment', JSON.stringify(bootstrap.assignment));

    setAssignment(bootstrap.assignment);
    setBootstrapCompleted(true);
    setLastSyncAt(bootstrap.server_time);
    setStatusMessage(null);
    await refreshPendingSyncCount();
    setDataVersion((current) => current + 1);
  }

  async function refreshReleaseStatusWithBaseUrl(nextBaseUrl: string) {
    if (!nextBaseUrl.trim()) {
      return;
    }

    try {
      const nextReleaseCheck = await mobileCheckRelease(
        nextBaseUrl,
        APP_VERSION_CODE
      );
      setReleaseCheck(nextReleaseCheck);
    } catch {
      // Keep mobile boot resilient when the release endpoint is not reachable.
    }
  }

  async function performInitialSync(
    nextApiBaseUrl: string,
    nextToken: string,
    fallbackMessage = i18n.t('initialSyncFailed')
  ) {
    setBootstrapCompleted(false);

    if (!isOnline) {
      setInitialSyncInProgress(false);
      setStatusMessage(i18n.t('initialSyncOffline'));
      return;
    }

    setInitialSyncInProgress(true);
    setStatusMessage(i18n.t('initialSyncing'));

    try {
      const bootstrap = await mobileBootstrap(nextApiBaseUrl, nextToken);
      await hydrateBootstrapSession(bootstrap);
    } catch (error) {
      setBootstrapCompleted(false);
      setStatusMessage(error instanceof Error ? error.message : fallbackMessage);
    } finally {
      setInitialSyncInProgress(false);
    }
  }

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      setIsOnline(Boolean(state.isConnected && state.isInternetReachable !== false));
    });

    return unsubscribe;
  }, []);

  useEffect(() => {
    async function boot() {
      const bootStartedAt = Date.now();

      await initializeStorage();

      const [
        storedToken,
        storedLanguage,
        storedLastSyncAt,
        bootstrapped,
        storedSessionUser,
        storedSessionAssignment,
      ] =
        await Promise.all([
          loadToken(),
          getAppState('language'),
          getAppState('last_sync_at'),
          hasBootstrapData(),
          getAppState('session_user'),
          getAppState('session_assignment'),
        ]);

      const nextLanguage =
        storedLanguage === 'ceb' || storedLanguage === 'en'
          ? storedLanguage
          : 'en';

      setLocale(nextLanguage);
      setLanguageState(nextLanguage);
      setLastSyncAt(storedLastSyncAt || null);
      setBootstrapCompleted(bootstrapped);

      if (storedToken) {
        setToken(storedToken);
        if (storedSessionUser) {
          setUser(JSON.parse(storedSessionUser) as MobileUser);
        }
        if (storedSessionAssignment) {
          setAssignment(JSON.parse(storedSessionAssignment) as MobileAssignment);
        }
        if (!bootstrapped) {
          setStatusMessage(i18n.t('initialSyncPendingMessage'));
        }
      }

      await refreshPendingSyncCount();

      const remainingSplashTime =
        MINIMUM_STARTUP_SPLASH_MS - (Date.now() - bootStartedAt);

      if (remainingSplashTime > 0) {
        await new Promise((resolve) => {
          setTimeout(resolve, remainingSplashTime);
        });
      }

      setIsReady(true);
    }

    void boot();
  }, []);

  useEffect(() => {
    if (!token || !isOnline) {
      return;
    }

    void mobileVerify(MOBILE_API_BASE_URL, token).catch(async () => {
      await signOut(true);
    });
  }, [isOnline, token]);

  useEffect(() => {
    if (!isReady || !isOnline) {
      return;
    }

    void refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL);
  }, [isOnline, isReady]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active' && isReady && isOnline) {
        void refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL);
      }
    });

    return () => {
      subscription.remove();
    };
  }, [isOnline, isReady]);

  useEffect(() => {
    if (!isReady) {
      return;
    }

    void refreshPendingSyncCount();
  }, [dataVersion, isReady]);

  async function signIn({
    email,
    password,
  }: {
    email: string;
    password: string;
  }) {
    const response = await mobileLogin(MOBILE_API_BASE_URL, {
      email,
      password,
      device_name: `BHW ${Platform.OS === 'ios' ? 'iPhone' : 'Android'} Device`,
      device_platform: Platform.OS,
      app_version: APP_VERSION,
    });

    const [existingDatasetOwnerUserId, existingDatasetAssignment, bootstrapped, storedLastSyncAt] =
      await Promise.all([
        getDatasetOwnerUserId(),
        getDatasetAssignment(),
        hasBootstrapData(),
        getAppState('last_sync_at'),
      ]);

    const nextUserId = String(response.user.id);
    const isDifferentDatasetOwner =
      Boolean(existingDatasetOwnerUserId) && existingDatasetOwnerUserId !== nextUserId;
    const canReuseCachedData =
      existingDatasetOwnerUserId === nextUserId && bootstrapped;

    if (isDifferentDatasetOwner) {
      await clearOperationalData();
    }

    await storeToken(response.token);
    await setAppState('session_user', JSON.stringify(response.user));

    setUser(response.user);
    setStatusMessage(null);
    setInitialSyncInProgress(false);
    await refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL);

    if (canReuseCachedData) {
      if (existingDatasetAssignment) {
        const cachedAssignment = JSON.parse(existingDatasetAssignment) as MobileAssignment;
        await setAppState('session_assignment', existingDatasetAssignment);
        setAssignment(cachedAssignment);
      } else {
        setAssignment(null);
      }

      setBootstrapCompleted(true);
      setLastSyncAt(storedLastSyncAt || null);
      setToken(response.token);
      await refreshPendingSyncCount();

      return;
    }

    await setAppState('session_assignment', '');
    setAssignment(null);
    setBootstrapCompleted(false);
    setToken(response.token);
    await refreshPendingSyncCount();
    await performInitialSync(MOBILE_API_BASE_URL, response.token);
  }

  async function requestPasswordReset(email: string) {
    const response = await mobileForgotPassword(MOBILE_API_BASE_URL, email);

    return response.message;
  }

  async function signOut(silent = false) {
    if (token && !silent) {
      try {
        await mobileLogout(MOBILE_API_BASE_URL, token);
      } catch {
        // Keep local logout reliable even if the remote token is already gone.
      }
    }

    await clearToken();
    await setAppState('session_user', '');
    await setAppState('session_assignment', '');
    setToken(null);
    setUser(null);
    setAssignment(null);
    setBootstrapCompleted(false);
    setInitialSyncInProgress(false);
    setLastSyncAt(null);
    setStatusMessage(null);
    await refreshPendingSyncCount();
  }

  async function syncNow() {
    if (!token || !isOnline || isSyncing) {
      return;
    }

    if (releaseCheck?.update.available && releaseCheck.update.required) {
      setStatusMessage(
        releaseCheck.update.message ?? i18n.t('syncBlockedUpdateRequired')
      );
      return;
    }

    setIsSyncing(true);

    try {
      const pendingSummary = await refreshPendingSyncCount();

      if (pendingSummary.total > 0) {
        setStatusMessage(i18n.t('uploadingChanges'));
        const payload = await getPendingSyncPayload();
        const syncResponse = await mobileSync(MOBILE_API_BASE_URL, token, {
          ...payload,
          device_name: `BHW ${Platform.OS === 'ios' ? 'iPhone' : 'Android'} Device`,
          app_version: APP_VERSION,
        });

        await applyResolvedRecords(syncResponse.resolved_records);
        await setAppState('last_sync_at', syncResponse.synced_at);
        setLastSyncAt(syncResponse.synced_at);
        const remainingSummary = await refreshPendingSyncCount();

        if (syncResponse.status !== 'success' || remainingSummary.total > 0) {
          setStatusMessage(
            syncResponse.failed_records[0]?.message ??
              i18n.t('syncUploadIncomplete')
          );
          setDataVersion((current) => current + 1);

          return;
        }
      }

      const downloadGuard = await refreshPendingSyncCount();
      if (downloadGuard.total > 0) {
        setStatusMessage(i18n.t('syncDownloadSkipped'));
        return;
      }

      setStatusMessage(i18n.t('downloadingLatest'));
      const bootstrap = await mobileBootstrap(MOBILE_API_BASE_URL, token);
      await hydrateBootstrapSession(bootstrap);
      setStatusMessage(i18n.t('syncComplete'));
    } catch (error) {
      setStatusMessage(
        error instanceof Error ? error.message : i18n.t('syncFailed')
      );
    } finally {
      setIsSyncing(false);
    }
  }

  async function retryInitialSync() {
    if (!token || initialSyncInProgress) {
      return;
    }

    await performInitialSync(MOBILE_API_BASE_URL, token);
  }

  async function setLanguagePreference(locale: SupportedLocale) {
    setLocale(locale);
    setLanguageState(locale);
    await setAppState('language', locale);
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
      appVersion: APP_VERSION,
      appVersionCode: APP_VERSION_CODE,
      lastSyncAt,
      statusMessage,
      dataVersion,
      initialSyncInProgress,
      pendingSyncCount,
      releaseCheck,
      signIn,
      requestPasswordReset,
      signOut: () => signOut(false),
      syncNow,
      retryInitialSync,
      refreshReleaseStatus: () => refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL),
      setLanguagePreference,
      bumpDataVersion: () => setDataVersion((current) => current + 1),
    }),
    [
      assignment,
      bootstrapCompleted,
      dataVersion,
      releaseCheck,
      isOnline,
      isReady,
      isSyncing,
      initialSyncInProgress,
      language,
      lastSyncAt,
      pendingSyncCount,
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
