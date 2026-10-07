import { HouseholdRecord } from '../types';

export const HOUSEHOLD_CONTRACT_VERSION = 1;
export const HOUSEHOLD_EDITABLE = ['household_no', 'household_address', 'is_social_aid_beneficiary'] as const;

export function householdChanges(values: HouseholdRecord | Omit<HouseholdRecord, 'sync_status'>, base: Record<string, unknown>) {
  return Object.fromEntries(HOUSEHOLD_EDITABLE.filter(field => values[field] !== base[field])
    .map(field => [field, values[field]]));
}

export function householdEditBlocked(record: HouseholdRecord) {
  return Boolean(record.protection_reason) || record.verification_status === 'submitted' ||
    (record.server_id == null && (record.verification_status === 'rejected' || record.sync_status === 'synced'));
}

export function validateHouseholdInput(values: Omit<HouseholdRecord, 'sync_status'>) {
  if (!values.household_no.trim() || values.household_no.length > 50) return 'Enter a household number (up to 50 characters).';
  if (!values.household_address?.trim()) return 'Enter the household address.';
  if (typeof values.is_social_aid_beneficiary !== 'boolean') return 'Confirm the social-aid information.';
  return null;
}
