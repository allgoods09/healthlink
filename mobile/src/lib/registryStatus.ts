import { SyncStatus, VerificationStatus } from '../types';
import { i18n } from '../i18n';

export function registryStatusLabel(record: {
  sync_status: SyncStatus;
  verification_status?: VerificationStatus;
}) {
  if (record.sync_status !== 'synced') return i18n.t('registryPendingUpload');
  if (record.verification_status === 'submitted') return i18n.t('registryPendingReview');
  if (record.verification_status === 'rejected') return i18n.t('registryNotApproved');
  return i18n.t('registryVerified');
}
