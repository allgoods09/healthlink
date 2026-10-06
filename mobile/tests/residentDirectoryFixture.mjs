import { storageHarness } from './storageHarness.mjs';

export const home = (id, number = String(id), purok = 1) => ({ id, purok_id: purok, household_no: number,
  household_address: 'Synthetic', is_active: true, is_social_aid_beneficiary: false });
export const person = (id, changes = {}) => ({ id, household_id: 1, first_name: 'Juan', middle_name: 'Peña',
  last_name: 'Ybañez', suffix: 'Jr.', birth_date: '1960-01-01', birth_place: 'Tubigon', sex: 'Male',
  civil_status: 'Single', citizenship: 'Filipino', relationship_to_head: 'Son', is_active: true,
  resident_status: 'active', deleted_at: null, ...changes });
export const data = () => ({ resident_contract_version: 2, user: { id: 1 },
  assignment: { barangay: { id: 1 }, purok: { id: 1, display_name: 'Purok 1' } }, server_time: '2026-10-06',
  households: [home(1, 'A10'), home(2, 'A2'), home(3, '10'), home(4, '2'), home(5, '99', 2)],
  residents: [person(1), person(2, { first_name: 'Ana', household_id: 2, sex: 'Female', birth_date: '1990-02-01' }),
    person(3, { first_name: 'Jose', last_name: 'AAA', household_id: 3, birth_date: '2020-10-06' }),
    person(4, { first_name: 'Mark', last_name: 'BBB', household_id: 4, birth_date: '2026-10-06' }),
    person(5, { household_id: 5 }), person(6, { resident_status: 'deceased' }),
    person(7, { resident_status: 'moved_out' }), person(8, { resident_status: 'relocated' }),
    person(9, { deleted_at: '2026-10-01' }), person(10, { first_name: 'Literal%_', is_active: false }),
    person(null, { mobile_uuid: '00000000-0000-4000-8000-000000000010', verification_status: 'rejected', verification_notes: 'Check name' })],
  field_visits: [], risk_assessments: [] });
export async function fixture(t, payload = data()) {
  const h = await storageHarness(); t.after(h.close);
  await h.storage.prepareDatasetForUser(1); await h.storage.replaceBootstrapData(payload); return h;
}
