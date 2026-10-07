import { i18n } from '../i18n';
import { MobileConfirmationRequest } from '../types';

export async function confirmLogout({ pendingSyncCount, requestConfirmation, signOut }: {
  pendingSyncCount: number;
  requestConfirmation: (request: MobileConfirmationRequest) => Promise<boolean>;
  signOut: () => Promise<void>;
}) {
  const hasPendingDrafts = pendingSyncCount > 0;
  const confirmed = await requestConfirmation({
    title: hasPendingDrafts
      ? i18n.t('logoutWarningTitle')
      : i18n.t('logoutConfirmationTitle'),
    message: hasPendingDrafts
      ? i18n.t('logoutWarningBody', { count: pendingSyncCount })
      : i18n.t('logoutConfirmationBody'),
    confirmLabel: hasPendingDrafts ? i18n.t('logoutAnyway') : i18n.t('logout'),
    tone: hasPendingDrafts ? 'warning' : 'danger',
  });

  if (!confirmed) {
    return;
  }

  await signOut();
}
