import { HouseholdRecord, ResidentRecord } from '../types';
import { normalizeBirthDateInput } from './format';

export type ResidentFormMode = 'new' | 'correction' | 'localRequest';
export const RESIDENT_PROFILE_FIELDS = ['occupation', 'employment_status', 'highest_education_level', 'education_status',
  'is_pwd', 'disability_type', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip', 'ethnicity'] as const;
export const RESIDENT_PROFILE_FLAGS = ['is_pwd', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip'] as const;
export const RESIDENT_EDITABLE_FIELDS = ['philsys_card_no', 'last_name', 'first_name', 'middle_name', 'suffix', 'birth_date',
  'birth_place', 'sex', 'civil_status', 'citizenship', 'religion', 'contact_number', 'email_address', 'relationship_to_head', ...RESIDENT_PROFILE_FIELDS] as const;

export function residentFormMode(record?: ResidentRecord | null): ResidentFormMode {
  return record ? record.server_id != null ? 'correction' : 'localRequest' : 'new';
}

export function residentEditBlocked(record?: ResidentRecord | null) {
  return Boolean(record && (record.server_id == null && (record.verification_status === 'rejected' ||
    record.verification_status === 'approved' && record.sync_status === 'synced') ||
    record.server_id != null && record.verification_status === 'submitted' && record.sync_status === 'synced'));
}

export function residentRequestLabel(record: ResidentRecord) {
  if (record.sync_status !== 'synced') return 'Awaiting upload';
  if (record.verification_status === 'submitted') return 'Submitted for verification';
  if (record.verification_status === 'rejected') return 'Not approved';
  return 'Approved';
}

export function residentSnapshot(record: Partial<ResidentRecord>): Record<string, unknown> {
  return Object.fromEntries([...RESIDENT_EDITABLE_FIELDS.map(field => [field, record[field] ?? null]),
    ['household_id', record.household_server_id ?? null], ['philsys_card_no', record.philsys_card_no ?? null],
    ['is_active', Boolean(record.is_active)], ['resident_status', record.resident_status ?? 'active'], ['deleted_at', record.deleted_at ?? null]]);
}

export function residentChanges(record: Partial<ResidentRecord>, base: Record<string, unknown>) {
  const values = residentSnapshot(record);
  return Object.fromEntries([...RESIDENT_EDITABLE_FIELDS, 'household_id'].filter(field =>
    Object.hasOwn(base, field) && values[field] !== base[field]).map(field => [field, values[field]]));
}

export function validateResidentInput(values: Partial<ResidentRecord>, today = new Date()) {
  const required: [keyof ResidentRecord, number][] = [['first_name', 100], ['last_name', 100], ['birth_place', 255],
    ['civil_status', 50], ['citizenship', 100], ['relationship_to_head', 100]];
  if (required.some(([field, max]) => typeof values[field] !== 'string' || !String(values[field]).trim() || String(values[field]).length > max)) {
    return 'Please complete the required resident fields within their allowed lengths.';
  }
  if (!['Male', 'Female'].includes(values.sex ?? '')) return 'Please select the resident sex.';
  const date = normalizeBirthDateInput(values.birth_date);
  const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  if (!date || date > todayKey) return 'Enter a valid birth date that is not in the future.';
  for (const [field, max] of [['philsys_card_no', 50], ['middle_name', 100], ['suffix', 20], ['religion', 100], ['contact_number', 20], ['email_address', 100],
    ['occupation', 150], ['disability_type', 150], ['ethnicity', 100]] as const) {
    if ((values[field]?.length ?? 0) > max) return `Please shorten ${field.replaceAll('_', ' ')}.`;
  }
  if (values.email_address && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email_address)) return 'Enter a valid email address or leave it blank.';
  return null;
}

export function eligibleResidentHousehold(household: HouseholdRecord, mode: ResidentFormMode, purokId: number, legacy?: Partial<ResidentRecord> | null) {
  if (household.purok_id !== purokId) return false;
  if (household.server_id != null) return true;
  return mode === 'localRequest' && legacy?.server_id == null && Boolean(legacy?.mobile_uuid) &&
    Boolean(legacy?.household_mobile_uuid) && legacy?.household_mobile_uuid === household.mobile_uuid && Boolean(household.mobile_uuid?.trim()) &&
    household.verification_status !== 'rejected' &&
    !(household.verification_status === 'approved' && household.sync_status === 'synced') && household.is_active;
}

export const RESIDENT_WIZARD_STEPS = [
  ['first_name', 'last_name', 'middle_name', 'suffix', 'philsys_card_no', 'birth_date', 'birth_place', 'sex', 'relationship_to_head'],
  ['civil_status', 'citizenship', 'religion', 'contact_number', 'email_address'],
  ['occupation', 'employment_status', 'highest_education_level', 'education_status'],
  ['is_pwd', 'disability_type', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip', 'ethnicity'],
] as const;

export function validateResidentStep(values: Partial<ResidentRecord>, step: number, choices: Record<string, string[]>) {
  const fields = RESIDENT_WIZARD_STEPS[step];
  if (!fields) return 'Reopen the resident form.';
  const complete = { first_name: 'Valid', last_name: 'Valid', birth_place: 'Valid', birth_date: '2000-01-01',
    sex: 'Female' as const, civil_status: 'Single', citizenship: 'Filipino', relationship_to_head: 'Other' };
  const validation = validateResidentInput({ ...complete, ...Object.fromEntries(fields.map(field => [field, values[field]])) });
  if (validation) return validation;
  for (const field of ['employment_status', 'highest_education_level', 'education_status'] as const) {
    if (step === 2 && values[field] != null && !choices[field]?.includes(values[field]!)) return 'Sync to load valid profile choices.';
  }
  return null;
}

export function normalizeHouseholdSearch(value: string) { return value.trim().replace(/\s+/g, ' ').toLowerCase(); }

// Token comparison keeps numeric parts natural without changing stored identifiers.
export function compareHouseholds(a: HouseholdRecord, b: HouseholdRecord) {
  const left = a.household_no.toLowerCase().match(/\d+|\D+/g) ?? [];
  const right = b.household_no.toLowerCase().match(/\d+|\D+/g) ?? [];
  for (let i = 0; i < Math.max(left.length, right.length); i++) {
    if (left[i] === undefined || right[i] === undefined) return left.length - right.length;
    const x = left[i], y = right[i];
    const comparison = /^\d+$/.test(x) && /^\d+$/.test(y) ? Number(x) - Number(y) : x < y ? -1 : x > y ? 1 : 0;
    if (comparison) return comparison;
  }
  return (a.local_id ?? 0) - (b.local_id ?? 0);
}

export function createSaveGuard() {
  let busy = false;
  return { acquire() { if (busy) return false; busy = true; return true; }, release() { busy = false; } };
}
