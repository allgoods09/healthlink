import { ResidentRecord } from '../types';
import { residentEditBlocked } from './residentWorkflow';
import { calculateAgeFromBirthDate, normalizeBirthDateInput } from './format';

export function residentDirectoryAge(birthDate?: string | null) {
  const date = normalizeBirthDateInput(birthDate?.slice(0, 10));
  return date ? calculateAgeFromBirthDate(date) : null;
}

export function isOpenResidentWorkflowItem(record: ResidentRecord) {
  return record.sync_status !== 'synced' || record.verification_status === 'submitted';
}

export function residentStatusKey(record: ResidentRecord) {
  if (record.sync_status !== 'synced') return 'awaitingResidentUpload';
  if (record.verification_status === 'submitted') return 'submittedResident';
  if (record.verification_status === 'rejected') return 'notApprovedResident';
  return 'approvedResident';
}

export function residentDetailActions(record: ResidentRecord | null, assignedPurokId?: number | null, age?: number | null) {
  const eligible = Boolean(record?.server_id && assignedPurokId && record.household_purok_id === assignedPurokId);
  return { update: eligible && !residentEditBlocked(record), assess: eligible && age != null && age >= 20 };
}
