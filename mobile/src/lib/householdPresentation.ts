import { HouseholdRecord, FieldVisitRecord } from '../types';
import { householdEditBlocked } from './householdWorkflow';

export type HouseholdDirectoryMode = 'operational' | 'request' | 'lookup';
export type HouseholdFormMode = 'new' | 'unsent' | 'correction' | 'submitted' | 'rejected' | 'approved' | 'protected' | 'lookup';
export const HOUSEHOLD_PAGE_SIZE = 30;
export const normalizeHouseholdQuery = (text: string) => text.trim().replace(/\s+/g, ' ').toLocaleLowerCase();

export function householdFormMode(row: HouseholdRecord | null): HouseholdFormMode {
  if (!row) return 'new';
  if (row.access_mode === 'lookup') return 'lookup';
  if (row.verification_status === 'rejected') return 'rejected';
  if (row.access_mode === 'request' && row.verification_status === 'approved') return 'approved';
  if (row.protection_reason) return 'protected';
  if (row.verification_status === 'submitted') return 'submitted';
  if (row.access_mode === 'request') return householdEditBlocked(row) ? 'protected' : 'unsent';
  return row.base_snapshot && !householdEditBlocked(row) ? 'correction' : 'protected';
}

export const householdCanEdit = (row: HouseholdRecord | null) =>
  ['new', 'unsent', 'correction'].includes(householdFormMode(row));

export function householdOfficialProfile(row: HouseholdRecord) {
  const base = row.access_mode === 'operational' ? row.base_snapshot : null;
  return base ? { ...row, household_no: String(base.household_no ?? row.household_no),
    household_address: String(base.household_address ?? row.household_address),
    is_social_aid_beneficiary: typeof base.is_social_aid_beneficiary === 'boolean' ? base.is_social_aid_beneficiary : row.is_social_aid_beneficiary } : row;
}

export function householdOccupancy(row: HouseholdRecord) {
  if (row.access_mode !== 'operational' || row.member_coverage !== 'complete' ||
    row.current_member_count == null || !Number.isInteger(row.current_member_count) || row.current_member_count < 0) return 'unknown';
  if (row.current_member_count === 0) return 'vacant';
  return row.current_head_name?.trim() ? 'headed' : 'headless';
}

export function householdStatusKey(row: HouseholdRecord) {
  const mode = householdFormMode(row);
  if (['submitted', 'rejected', 'approved', 'protected'].includes(mode)) return `hhStatus_${mode}`;
  if (row.sync_status !== 'synced') return row.access_mode === 'operational' ? 'hhCorrectionSaved' : 'hhStatus_unsent';
  return row.access_mode === 'lookup' ? 'hhLookup' : 'hhOfficial';
}

export function householdPage(rows: HouseholdRecord[], mode: HouseholdDirectoryMode, search: string, limit = HOUSEHOLD_PAGE_SIZE) {
  const query = normalizeHouseholdQuery(search);
  const matches = rows.filter(row => {
    const profile = householdOfficialProfile(row);
    return row.access_mode === mode && normalizeHouseholdQuery([profile.household_no, profile.household_address,
      mode === 'operational' ? row.current_head_name : mode === 'lookup' ? row.purok_display_name : '',
    ].filter(Boolean).join(' ')).includes(query);
  });
  matches.sort((a, b) => {
    const first = householdOfficialProfile(a).household_no; const second = householdOfficialProfile(b).household_no;
    return first.localeCompare(second, undefined, { numeric: true, sensitivity: 'base' }) || first.localeCompare(second) ||
      (a.purok_id ?? 0) - (b.purok_id ?? 0) || (a.server_id ?? a.local_id ?? 0) - (b.server_id ?? b.local_id ?? 0) ||
      (a.mobile_uuid ?? '').localeCompare(b.mobile_uuid ?? '');
  });
  return { rows: matches.slice(0, limit), total: matches.length };
}

export function visitBelongsToHousehold(visit: FieldVisitRecord, home: HouseholdRecord) {
  if (visit.household_server_id != null) return visit.household_server_id === home.server_id;
  return Boolean(visit.household_mobile_uuid?.trim()) && visit.household_mobile_uuid === home.mobile_uuid;
}

export function householdValidationKey(number: string, address: string, socialAid: unknown) {
  if (!number.trim() || number.trim().length > 50) return 'hhNumberInvalid';
  if (!address.trim()) return 'hhAddressInvalid';
  if (typeof socialAid !== 'boolean') return 'hhSocialInvalid';
  return null;
}
