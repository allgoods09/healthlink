import NetInfo from '@react-native-community/netinfo';
import React, {
  createContext,
  ReactNode,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { ActivityIndicator, AppState, Modal, Platform, Text, View, useColorScheme } from 'react-native';
import { AccountSwitchBlockedError, AssignmentChangedError, DatasetOwnershipError, hasLocalEditor, RefreshDeferredError } from '../lib/syncGuard';

import { i18n, setLocale, SupportedLocale } from '../i18n';
import {
  mobileCheckRelease,
  mobileBootstrap,
  mobileForgotPassword,
  mobileLogin,
  mobileLogout,
  mobileNotifications,
  mobileReadAllNotifications,
  mobileReadNotification,
  mobileSync,
  mobileVerify,
} from '../lib/api';
import { MOBILE_API_BASE_URL } from '../lib/config';
import {
  applyResolvedRecords,
  assertDatasetOwner,
  clearLocalSession,
  prepareDatasetForUser,
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
  verifyDatasetAssignment,
} from '../lib/storage';
import {
  MobileAssignment,
  MobileConfirmationRequest,
  MobileNotification,
  MobileReleaseCheck,
  MobileToast,
  MobileToastLevel,
  MobileUser,
} from '../types';
import {
  AppTheme,
  AppearancePreference,
  resolveTheme,
} from '../theme';

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
  appearancePreference: AppearancePreference;
  appTheme: AppTheme;
  appVersion: string;
  appVersionCode: number;
  lastSyncAt: string | null;
  statusMessage: string | null;
  toast: MobileToast | null;
  notifications: MobileNotification[];
  unreadNotificationCount: number;
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
  refreshNotifications: () => Promise<void>;
  markNotificationRead: (notificationId: string) => Promise<void>;
  markAllNotificationsRead: () => Promise<void>;
  showToast: (message: string | null | undefined, level?: MobileToastLevel) => void;
  clearToast: () => void;
  confirmation: MobileConfirmationRequest | null;
  requestConfirmation: (request: MobileConfirmationRequest) => Promise<boolean>;
  resolveConfirmation: (confirmed: boolean) => void;
  setLanguagePreference: (locale: SupportedLocale) => Promise<void>;
  setAppearancePreference: (preference: AppearancePreference) => Promise<void>;
  bumpDataVersion: () => void;
};

const AppContext = createContext<AppContextValue | null>(null);

const appConfig = require('../../app.json');
const APP_VERSION = appConfig.expo?.version ?? '1.0.0';
const APP_VERSION_CODE = Number(appConfig.expo?.android?.versionCode ?? 1);
const MINIMUM_STARTUP_SPLASH_MS = 1000;

export function AppProvider({ children }: { children: ReactNode }) {
  const systemColorScheme = useColorScheme();
  const [isReady, setIsReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<MobileUser | null>(null);
  const [assignment, setAssignment] = useState<MobileAssignment | null>(null);
  const [language, setLanguageState] = useState<SupportedLocale>('en');
  const [appearancePreference, setAppearancePreferenceState] =
    useState<AppearancePreference>('system');
  const [isOnline, setIsOnline] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const syncInFlight = useRef(false);
  const sessionEpoch = useRef(0);
  const verificationSequence = useRef(0);
  const authTransition = useRef(false);
  const [isApplyingRefresh, setIsApplyingRefresh] = useState(false);
  const [bootstrapCompleted, setBootstrapCompleted] = useState(false);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(null);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [toast, setToast] = useState<MobileToast | null>(null);
  const [notifications, setNotifications] = useState<MobileNotification[]>([]);
  const [unreadNotificationCount, setUnreadNotificationCount] = useState(0);
  const [dataVersion, setDataVersion] = useState(0);
  const [initialSyncInProgress, setInitialSyncInProgress] = useState(false);
  const [pendingSyncCount, setPendingSyncCount] = useState(0);
  const [releaseCheck, setReleaseCheck] = useState<MobileReleaseCheck | null>(null);
  const [confirmation, setConfirmation] =
    useState<MobileConfirmationRequest | null>(null);
  const confirmationResolver = useRef<((confirmed: boolean) => void) | null>(null);

  function requestConfirmation(request: MobileConfirmationRequest) {
    return new Promise<boolean>((resolve) => {
      confirmationResolver.current = resolve;
      setConfirmation(request);
    });
  }

  function resolveConfirmation(confirmed: boolean) {
    confirmationResolver.current?.(confirmed);
    confirmationResolver.current = null;
    setConfirmation(null);
  }

  async function refreshPendingSyncCount() {
    const summary = await getPendingChangeSummary();
    setPendingSyncCount(summary.total);

    return summary;
  }

  function showToast(
    message: string | null | undefined,
    level: MobileToastLevel = 'info'
  ) {
    if (!message || !message.trim()) {
      return;
    }

    setToast({
      id: Date.now(),
      message,
      level,
    });
  }

  function clearToast() {
    setToast(null);
  }

  async function hydrateBootstrapSession(bootstrap: Awaited<ReturnType<typeof mobileBootstrap>>, epoch: number) {
    if (epoch !== sessionEpoch.current) return;
    if (hasLocalEditor()) throw new RefreshDeferredError();
    setIsApplyingRefresh(true);
    try {
      await replaceBootstrapData(bootstrap, () => epoch === sessionEpoch.current);
    } finally {
      setIsApplyingRefresh(false);
    }
    if (epoch !== sessionEpoch.current) return;
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

    const epoch = sessionEpoch.current;
    try {
      const nextReleaseCheck = await mobileCheckRelease(
        nextBaseUrl,
        APP_VERSION_CODE
      );
      if (epoch !== sessionEpoch.current) return;
      setReleaseCheck(nextReleaseCheck);
    } catch {
      // Keep mobile boot resilient when the release endpoint is not reachable.
    }
  }

  async function refreshNotificationsWithToken(nextToken: string | null) {
    if (!nextToken || !isOnline) {
      return;
    }

    const epoch = sessionEpoch.current;
    try {
      const response = await mobileNotifications(MOBILE_API_BASE_URL, nextToken);
      if (epoch !== sessionEpoch.current) return;
      setNotifications(response.notifications);
      setUnreadNotificationCount(response.unread_count);
    } catch {
      // Keep notifications non-blocking when the endpoint is temporarily unavailable.
    }
  }

  async function performInitialSync(
    nextApiBaseUrl: string,
    nextToken: string,
    epoch: number,
    fallbackMessage = i18n.t('initialSyncFailed'),
    loginAssignment?: Pick<MobileUser, 'id' | 'assigned_barangay_id' | 'assigned_purok_id'>
  ) {
    setBootstrapCompleted(false);

    if (!isOnline) {
      setInitialSyncInProgress(false);
      setStatusMessage(i18n.t('initialSyncOffline'));
      showToast(i18n.t('initialSyncOffline'), 'warning');
      return;
    }

    setInitialSyncInProgress(true);
    setStatusMessage(i18n.t('initialSyncing'));

    try {
      const verification = loginAssignment ? { user: loginAssignment, sequence: undefined } : await fetchVerifiedAssignment(nextToken, epoch);
      if (!verification) return;
      if (epoch !== sessionEpoch.current) return;
      await checkAssignment(verification.user, epoch, verification.sequence);
      if (epoch !== sessionEpoch.current) return;
      if (await getAppState('resident_workspace_blocked') === 'assignment' &&
          ((await getPendingChangeSummary()).total > 0 || hasLocalEditor())) throw new AssignmentChangedError();
      const bootstrap = await mobileBootstrap(nextApiBaseUrl, nextToken);
      if (epoch !== sessionEpoch.current) return;
      await hydrateBootstrapSession(bootstrap, epoch);
    } catch (error) {
      if (epoch !== sessionEpoch.current) return;
      setBootstrapCompleted(false);
      const message = error instanceof Error ? error.message : fallbackMessage;
      setStatusMessage(message);
      showToast(message, 'error');
    } finally {
      if (epoch === sessionEpoch.current) setInitialSyncInProgress(false);
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
        storedAppearancePreference,
        storedLastSyncAt,
        bootstrapped,
        storedSessionUser,
        storedSessionAssignment,
      ] =
        await Promise.all([
          loadToken(),
          getAppState('language'),
          getAppState('appearance_preference'),
          getAppState('last_sync_at'),
          hasBootstrapData(),
          getAppState('session_user'),
          getAppState('session_assignment'),
        ]);

      const nextLanguage =
        storedLanguage === 'ceb' || storedLanguage === 'en'
          ? storedLanguage
          : 'en';
      const nextAppearancePreference: AppearancePreference =
        storedAppearancePreference === 'light' ||
        storedAppearancePreference === 'dark' ||
        storedAppearancePreference === 'system'
          ? storedAppearancePreference
          : 'system';

      setLocale(nextLanguage);
      setLanguageState(nextLanguage);
      setAppearancePreferenceState(nextAppearancePreference);
      setLastSyncAt(storedLastSyncAt || null);
      setBootstrapCompleted(bootstrapped);

      if (storedToken) {
        try {
          const sessionUser = JSON.parse(storedSessionUser || 'null') as MobileUser | null;
          await assertDatasetOwner(sessionUser?.id);
          setUser(sessionUser);
          setToken(storedToken);
          if (storedSessionAssignment && bootstrapped) {
            setAssignment(JSON.parse(storedSessionAssignment) as MobileAssignment);
          }
          if (!bootstrapped) setStatusMessage(i18n.t(
            await getAppState('resident_workspace_blocked') === 'assignment' ? 'assignmentChangedWorkProtected' : 'residentRefreshRequired'));
        } catch {
          await clearLocalSession();
          setBootstrapCompleted(false);
          setStatusMessage(i18n.t('accountRecordsProtected'));
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

    const epoch = sessionEpoch.current;
    void fetchVerifiedAssignment(token, epoch).then(async verification => {
      if (!verification || epoch !== sessionEpoch.current) return;
      const compatible = await checkAssignment(verification.user, epoch, verification.sequence);
      if (epoch !== sessionEpoch.current || compatible || initialSyncInProgress) return;
      if ((await getPendingChangeSummary()).total > 0 || hasLocalEditor()) return;
      await performInitialSync(MOBILE_API_BASE_URL, token, epoch, i18n.t('initialSyncFailed'), verification.user);
    }, async () => {
      if (epoch === sessionEpoch.current) await signOut(true);
    }).catch(error => {
      if (epoch === sessionEpoch.current) setStatusMessage(error instanceof Error ? error.message : i18n.t('initialSyncFailed'));
    });
  }, [isOnline, token]);

  async function fetchVerifiedAssignment(nextToken: string, epoch: number) {
    const sequence = ++verificationSequence.current;
    try {
      const response = await mobileVerify(MOBILE_API_BASE_URL, nextToken);
      return epoch === sessionEpoch.current && sequence === verificationSequence.current ? { user: response.user, sequence } : null;
    } catch (error) {
      if (epoch !== sessionEpoch.current || sequence !== verificationSequence.current) return null;
      throw error;
    }
  }

  async function checkAssignment(authoritative: { id: number; assigned_barangay_id: number; assigned_purok_id: number }, epoch: number, sequence?: number) {
    if (epoch !== sessionEpoch.current) return false;
    const compatible = await verifyDatasetAssignment(authoritative?.id, authoritative?.assigned_barangay_id, authoritative?.assigned_purok_id,
      () => epoch === sessionEpoch.current && (sequence === undefined || sequence === verificationSequence.current));
    if (epoch !== sessionEpoch.current) return false;
    if (!compatible) {
      setBootstrapCompleted(false);
      setAssignment(null);
      setDataVersion(current => current + 1);
      const changed = await getAppState('resident_workspace_blocked') === 'assignment';
      setStatusMessage(changed ? i18n.t('assignmentChangedWorkProtected') : i18n.t('residentRefreshRequired'));
    }
    return compatible;
  }

  useEffect(() => {
    if (!isReady || !isOnline || !token) {
      return;
    }

    void refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL);
    void refreshNotificationsWithToken(token);
  }, [isOnline, isReady, token]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active' && isReady && isOnline) {
        void refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL);
        void refreshNotificationsWithToken(token);
      }
    });

    return () => {
      subscription.remove();
    };
  }, [isOnline, isReady, token]);

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
    if (authTransition.current) return;
    authTransition.current = true;
    const epoch = ++sessionEpoch.current;
    try {
      const response = await mobileLogin(MOBILE_API_BASE_URL, {
        email,
        password,
        device_name: `BHW ${Platform.OS === 'ios' ? 'iPhone' : 'Android'} Device`,
        device_platform: Platform.OS,
        app_version: APP_VERSION,
      });
      if (epoch !== sessionEpoch.current) {
        void mobileLogout(MOBILE_API_BASE_URL, response.token).catch(() => {});
        return;
      }

      try {
        await prepareDatasetForUser(response.user.id);
      } catch (error) {
        // The rejected account must never become the local authenticated session.
        void mobileLogout(MOBILE_API_BASE_URL, response.token).catch(() => {});
        if (error instanceof AccountSwitchBlockedError) {
          throw new Error(i18n.t('accountSwitchBlocked'));
        }
        throw error;
      }
      if (epoch !== sessionEpoch.current) return;

      await checkAssignment(response.user, epoch);
      if (epoch !== sessionEpoch.current) return;

      const [existingDatasetOwnerUserId, existingDatasetAssignment, bootstrapped, storedLastSyncAt] =
        await Promise.all([
          getDatasetOwnerUserId(),
          getDatasetAssignment(),
          hasBootstrapData(),
          getAppState('last_sync_at'),
        ]);

      const nextUserId = String(response.user.id);
      const canReuseCachedData =
        existingDatasetOwnerUserId === nextUserId && bootstrapped;

      await storeToken(response.token);
      await setAppState('session_user', JSON.stringify(response.user));

      setUser(response.user);
      setStatusMessage(null);
      setInitialSyncInProgress(false);
      await refreshReleaseStatusWithBaseUrl(MOBILE_API_BASE_URL);
      await refreshNotificationsWithToken(response.token);
      if (epoch !== sessionEpoch.current) return;

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
      authTransition.current = false;
      await performInitialSync(MOBILE_API_BASE_URL, response.token, epoch, i18n.t('initialSyncFailed'), response.user);
    } finally {
      if (epoch === sessionEpoch.current) authTransition.current = false;
    }
  }

  async function requestPasswordReset(email: string) {
    const response = await mobileForgotPassword(MOBILE_API_BASE_URL, email);

    return response.message;
  }

  async function signOut(silent = false) {
    authTransition.current = true;
    ++sessionEpoch.current;
    syncInFlight.current = false;
    setIsSyncing(false);
    resolveConfirmation(false);
    await clearLocalSession();
    setToken(null);
    setUser(null);
    setAssignment(null);
    setBootstrapCompleted(false);
    setInitialSyncInProgress(false);
    setLastSyncAt(null);
    setStatusMessage(null);
    setNotifications([]);
    setUnreadNotificationCount(0);
    setReleaseCheck(null);
    clearToast();
    await refreshPendingSyncCount();
    authTransition.current = false;
    // Local logout never waits for connectivity and never uploads pending work.
    if (token && !silent) void mobileLogout(MOBILE_API_BASE_URL, token).catch(() => {});
  }

  async function syncNow() {
    if (!token || !user || !isOnline || syncInFlight.current) {
      return;
    }

    const activeToken = token;
    const epoch = sessionEpoch.current;

    if (releaseCheck?.update.available && releaseCheck.update.required) {
      const message =
        releaseCheck.update.message ?? i18n.t('syncBlockedUpdateRequired');
      setStatusMessage(message);
      showToast(message, 'warning');
      return;
    }

    syncInFlight.current = true;
    setIsSyncing(true);

    try {
      await assertDatasetOwner(user.id);
      if (epoch !== sessionEpoch.current) return;
      const verification = await fetchVerifiedAssignment(activeToken, epoch);
      if (!verification) return;
      if (epoch !== sessionEpoch.current) return;
      await checkAssignment(verification.user, epoch, verification.sequence);
      if (epoch !== sessionEpoch.current) return;
      if (await getAppState('resident_workspace_blocked') === 'assignment' &&
          ((await getPendingChangeSummary()).total > 0 || hasLocalEditor())) throw new AssignmentChangedError();
      const pendingSummary = await refreshPendingSyncCount();
      let registrySubmitted = false;

      if (pendingSummary.total > 0) {
        setStatusMessage(i18n.t('uploadingChanges'));
        const { payload, snapshot } = await getPendingSyncPayload(user.id);
        if (epoch !== sessionEpoch.current) return;
        const syncResponse = await mobileSync(MOBILE_API_BASE_URL, activeToken, {
          ...payload,
          device_name: `BHW ${Platform.OS === 'ios' ? 'iPhone' : 'Android'} Device`,
          app_version: APP_VERSION,
        });
        registrySubmitted = [...syncResponse.resolved_records.households,
          ...syncResponse.resolved_records.residents]
          .some((record) => record.verification_status === 'submitted');

        if (epoch !== sessionEpoch.current) return;
        await applyResolvedRecords(syncResponse.resolved_records, snapshot);
        if (epoch !== sessionEpoch.current) return;
        await setAppState('last_sync_at', syncResponse.synced_at);
        setLastSyncAt(syncResponse.synced_at);
        const remainingSummary = await refreshPendingSyncCount();

        if (syncResponse.status !== 'success' || remainingSummary.total > 0) {
          const message =
            syncResponse.failed_records[0]?.message ??
            i18n.t('syncUploadIncomplete');
          setStatusMessage(message);
          showToast(message, 'warning');
          if (registrySubmitted) showToast(i18n.t('registrySubmitted'), 'info');
          setDataVersion((current) => current + 1);

          return;
        }
      }

      const downloadGuard = await refreshPendingSyncCount();
      if (downloadGuard.total > 0) {
        setStatusMessage(i18n.t('syncDownloadSkipped'));
        showToast(i18n.t('syncDownloadSkipped'), 'warning');
        return;
      }

      setStatusMessage(i18n.t('downloadingLatest'));
      const bootstrap = await mobileBootstrap(MOBILE_API_BASE_URL, activeToken);
      if (epoch !== sessionEpoch.current) return;
      await hydrateBootstrapSession(bootstrap, epoch);
      if (epoch !== sessionEpoch.current) return;
      const message = pendingSummary.total > 0 && registrySubmitted ? i18n.t('registrySubmitted') : i18n.t('syncComplete');
      setStatusMessage(message);
      showToast(message, 'success');
      await refreshNotificationsWithToken(activeToken);
    } catch (error) {
      if (epoch !== sessionEpoch.current) return;
      if (error instanceof AssignmentChangedError) {
        setBootstrapCompleted(false);
        setAssignment(null);
        const message = i18n.t('assignmentChangedWorkProtected');
        setStatusMessage(message);
        showToast(message, 'warning');
        return;
      }
      if (error instanceof DatasetOwnershipError) {
        const message = i18n.t('accountRecordsProtected');
        setStatusMessage(message);
        showToast(message, 'warning');
        return;
      }
      if (error instanceof RefreshDeferredError) {
        const message = i18n.t('syncRefreshDeferred');
        setStatusMessage(message);
        showToast(message, 'warning');
        await refreshPendingSyncCount();
        return;
      }
      const message =
        error instanceof Error ? error.message : i18n.t('syncFailed');
      setStatusMessage(message);
      showToast(message, 'error');
    } finally {
      if (epoch === sessionEpoch.current) {
        syncInFlight.current = false;
        setIsSyncing(false);
      }
    }
  }

  async function retryInitialSync() {
    if (!token || initialSyncInProgress) {
      return;
    }

    // This is an explicit manual retry, not automatic upload on verification.
    if ((await getPendingChangeSummary()).total > 0) {
      await syncNow();
      return;
    }
    await performInitialSync(MOBILE_API_BASE_URL, token, sessionEpoch.current);
  }

  async function setLanguagePreference(locale: SupportedLocale) {
    setLocale(locale);
    setLanguageState(locale);
    await setAppState('language', locale);
  }

  async function setAppearancePreference(preference: AppearancePreference) {
    setAppearancePreferenceState(preference);
    await setAppState('appearance_preference', preference);
  }

  const appTheme = useMemo(
    () => resolveTheme(appearancePreference, systemColorScheme),
    [appearancePreference, systemColorScheme]
  );

  async function markNotificationRead(notificationId: string) {
    if (!token) {
      return;
    }

    try {
      const response = await mobileReadNotification(
        MOBILE_API_BASE_URL,
        token,
        notificationId
      );

      setNotifications((current) =>
        current.map((notification) =>
          notification.id === notificationId
            ? response.notification
            : notification
        )
      );
      setUnreadNotificationCount(response.unread_count);
    } catch (error) {
      showToast(
        error instanceof Error ? error.message : i18n.t('notificationReadFailed'),
        'error'
      );
    }
  }

  async function markAllNotificationsRead() {
    if (!token) {
      return;
    }

    try {
      await mobileReadAllNotifications(MOBILE_API_BASE_URL, token);
      setNotifications((current) =>
        current.map((notification) => ({
          ...notification,
          read_at: notification.read_at ?? new Date().toISOString(),
        }))
      );
      setUnreadNotificationCount(0);
      showToast(i18n.t('notificationsMarkedRead'), 'success');
    } catch (error) {
      showToast(
        error instanceof Error ? error.message : i18n.t('notificationReadFailed'),
        'error'
      );
    }
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
      appearancePreference,
      appTheme,
      appVersion: APP_VERSION,
      appVersionCode: APP_VERSION_CODE,
      lastSyncAt,
      statusMessage,
      toast,
      notifications,
      unreadNotificationCount,
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
      refreshNotifications: () => refreshNotificationsWithToken(token),
      markNotificationRead,
      markAllNotificationsRead,
      showToast,
      clearToast,
      confirmation,
      requestConfirmation,
      resolveConfirmation,
      setLanguagePreference,
      setAppearancePreference,
      bumpDataVersion: () => setDataVersion((current) => current + 1),
    }),
    [
      assignment,
      appearancePreference,
      appTheme,
      bootstrapCompleted,
      confirmation,
      dataVersion,
      releaseCheck,
      isOnline,
      isReady,
      isSyncing,
      initialSyncInProgress,
      language,
      lastSyncAt,
      notifications,
      pendingSyncCount,
      statusMessage,
      toast,
      token,
      unreadNotificationCount,
      user,
      systemColorScheme,
    ]
  );

  return (
    <AppContext.Provider value={value}>
      {children}
      <Modal visible={isApplyingRefresh} transparent animationType="none" onRequestClose={() => {}}>
        <View style={{ flex: 1, justifyContent: 'center', padding: 32, backgroundColor: '#00000066' }}>
          <View style={{ padding: 24, borderRadius: 16, gap: 16, backgroundColor: appTheme.colors.surface }}>
            <ActivityIndicator />
            <Text accessibilityRole="alert" style={{ color: appTheme.colors.text }}>
              {i18n.t('syncApplyingRefresh')}
            </Text>
          </View>
        </View>
      </Modal>
    </AppContext.Provider>
  );
}

export function useAppContext() {
  const context = useContext(AppContext);

  if (!context) {
    throw new Error('useAppContext must be used within AppProvider');
  }

  return context;
}

export function useAppTheme() {
  return useAppContext().appTheme;
}

export function useThemedStyles<T>(factory: (theme: AppTheme) => T) {
  const appTheme = useAppTheme();

  return useMemo(() => factory(appTheme), [appTheme, factory]);
}
