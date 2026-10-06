import * as SecureStore from 'expo-secure-store';
import * as SQLite from 'expo-sqlite';
import { compareHouseholds, eligibleResidentHousehold, normalizeHouseholdSearch, residentChanges, residentEditBlocked,
  residentFormMode, residentSnapshot, ResidentFormMode, validateResidentInput } from './residentWorkflow';
import { AccountSwitchBlockedError, AssignmentChangedError, DatasetOwnershipError, hasLocalEditor, RefreshDeferredError, serializeLocalWrite } from './syncGuard';

import {
  BootstrapPayload,
  FieldVisitRecord,
  HouseholdRecord,
  RiskAssessmentRecord,
  ResidentRecord,
  MobileAssignment,
  SyncStatus,
  SyncResponse,
  VisitPhoto,
} from '../types';

const TOKEN_KEY = 'healthlink_mobile_token';
const DB_NAME = 'healthlink_bhw.db';
export const RESIDENT_CONTRACT_VERSION = 1;
export const CURRENT_RESIDENT_PAGE_SIZE = 50;
const SYNC_TABLES = ['households', 'residents', 'field_visits', 'risk_assessments'] as const;
type SyncTable = typeof SYNC_TABLES[number];
type SnapshotRows = Record<SyncTable, Record<string, any>[]>;
type UploadSnapshot = { ownerUserId: number; rows: SnapshotRows };

// Public mutators share one queue; their internal helpers must not re-enter it.
export const saveHousehold = (values: Parameters<typeof saveHouseholdInternal>[0], userId: number | undefined) =>
  ownedWrite(userId, () => saveHouseholdInternal(values));
export const saveResident = (values: Parameters<typeof saveResidentInternal>[0], userId: number | undefined) =>
  ownedWrite(userId, async () => {
    const scope = await currentResidentScope();
    if (!scope) throw new AssignmentChangedError();
    const db = await getDatabase();
    const existing = values.local_id ? await db.getFirstAsync<any>('SELECT * FROM residents WHERE local_id = ?', [values.local_id]) : null;
    if (existing?.server_id != null && !await getResidentByLocalId(values.local_id!)) throw new AssignmentChangedError();
    if (existing && existing.server_id == null && !await getResidentRequestByLocalId(values.local_id!)) throw new AssignmentChangedError();
    if (!existing && values.server_id != null) throw new AssignmentChangedError();
    if (existing && residentEditBlocked({ ...existing, is_active: intToBool(existing.is_active) })) {
      throw new Error(existing.server_id == null ? 'This request was not approved. Its history is preserved; linked resubmission is not yet available.' :
        'This resident has an update under review. Wait for the Secretary decision.');
    }
    const household = await db.getFirstAsync<any>(
      values.household_server_id != null ? 'SELECT * FROM households WHERE server_id = ?' :
        "SELECT * FROM households WHERE mobile_uuid IS NOT NULL AND TRIM(mobile_uuid) <> '' AND mobile_uuid = ?",
      [values.household_server_id ?? values.household_mobile_uuid ?? null]);
    if (household?.purok_id !== scope.purokId) throw new AssignmentChangedError();
    if (!eligibleResidentHousehold({ ...household, is_active: intToBool(household.is_active) },
      existing?.server_id != null ? 'correction' : 'new', scope.purokId)) throw new Error('Choose an eligible household in your assigned purok.');
    if (values.propose_household_head && household.server_id != null) throw new Error('A head may only be proposed for a new household request.');
    let savedId: number | undefined;
    await db.withExclusiveTransactionAsync(async txn => { savedId = await saveResidentInternal(values, txn); });
    return savedId;
  });
export const saveVisit = (values: Parameters<typeof saveVisitInternal>[0], userId: number | undefined) =>
  ownedWrite(userId, () => saveVisitInternal(values));
export const saveRiskAssessment = (values: Parameters<typeof saveRiskAssessmentInternal>[0], userId: number | undefined) =>
  ownedWrite(userId, () => saveRiskAssessmentInternal(values));
export const getPendingSyncPayload = (userId: number) => ownedWrite(userId, async () => {
  const { payload, snapshot } = await getPendingSyncPayloadInternal();
  return { payload, snapshot: { ownerUserId: userId, rows: snapshot } };
});
export const replaceBootstrapData = (payload: BootstrapPayload, applicable = () => true) =>
  ownedWrite(payload.user.id, () => replaceBootstrapDataInternal(payload, applicable), true);
export const applyResolvedRecords = (resolved: SyncResponse['resolved_records'], snapshot: UploadSnapshot) =>
  ownedWrite(snapshot.ownerUserId, () => applyResolvedRecordsInternal(resolved, snapshot.rows));
const UUID_REGEX =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

type PendingChangeSummary = {
  households: number;
  residents: number;
  visits: number;
  riskAssessments: number;
  total: number;
};

let databasePromise: Promise<SQLite.SQLiteDatabase> | null = null;

function getDatabase() {
  if (!databasePromise) {
    databasePromise = SQLite.openDatabaseAsync(DB_NAME);
  }

  return databasePromise;
}

function boolToInt(value: boolean) {
  return value ? 1 : 0;
}

function intToBool(value: number | null | undefined) {
  return value === 1;
}

async function hasColumn(
  db: SQLite.SQLiteDatabase,
  table: string,
  column: string
) {
  const rows = await db.getAllAsync<{ name: string }>(`PRAGMA table_info(${table})`);

  return rows.some((row) => row.name === column);
}

async function ensureColumn(
  db: SQLite.SQLiteDatabase,
  table: string,
  column: string,
  definition: string
) {
  if (await hasColumn(db, table, column)) {
    return;
  }

  await db.execAsync(`ALTER TABLE ${table} ADD COLUMN ${column} ${definition};`);
}

function createUuid() {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID();
  }

  const bytes = new Uint8Array(16);

  if (globalThis.crypto?.getRandomValues) {
    globalThis.crypto.getRandomValues(bytes);
  } else {
    for (let index = 0; index < bytes.length; index += 1) {
      bytes[index] = Math.floor(Math.random() * 256);
    }
  }

  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;

  const hex = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');

  return [
    hex.slice(0, 8),
    hex.slice(8, 12),
    hex.slice(12, 16),
    hex.slice(16, 20),
    hex.slice(20, 32),
  ].join('-');
}

function isValidUuid(value: string | null | undefined) {
  return Boolean(value && UUID_REGEX.test(value));
}

function parsePhotos(raw: string | null | undefined): VisitPhoto[] {
  if (!raw) {
    return [];
  }

  try {
    return JSON.parse(raw) as VisitPhoto[];
  } catch {
    return [];
  }
}

function parseJsonRecord(
  raw: string | null | undefined
): Record<string, boolean> {
  if (!raw) {
    return {};
  }

  try {
    return JSON.parse(raw) as Record<string, boolean>;
  } catch {
    return {};
  }
}

function parseJsonValue<T>(raw: string | null | undefined, fallback: T): T {
  if (!raw) {
    return fallback;
  }

  try {
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}

function toNullableNumber(value: unknown) {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const nextValue = Number(value);

  return Number.isFinite(nextValue) ? nextValue : null;
}

export async function initializeStorage() {
  const db = await getDatabase();

  await db.execAsync(`
    PRAGMA journal_mode = WAL;

    CREATE TABLE IF NOT EXISTS app_state (
      key TEXT PRIMARY KEY NOT NULL,
      value TEXT
    );

    CREATE TABLE IF NOT EXISTS households (
      local_id INTEGER PRIMARY KEY AUTOINCREMENT,
      server_id INTEGER UNIQUE,
      mobile_uuid TEXT UNIQUE,
      purok_id INTEGER,
      purok_display_name TEXT,
      household_no TEXT NOT NULL,
      household_address TEXT NOT NULL,
      is_social_aid_beneficiary INTEGER NOT NULL DEFAULT 0,
      is_active INTEGER NOT NULL DEFAULT 1,
      sync_status TEXT NOT NULL DEFAULT 'synced',
      updated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS residents (
      local_id INTEGER PRIMARY KEY AUTOINCREMENT,
      server_id INTEGER UNIQUE,
      mobile_uuid TEXT UNIQUE,
      household_server_id INTEGER,
      household_mobile_uuid TEXT,
      philsys_card_no TEXT,
      last_name TEXT NOT NULL,
      first_name TEXT NOT NULL,
      middle_name TEXT,
      suffix TEXT,
      birth_date TEXT NOT NULL,
      birth_place TEXT NOT NULL,
      sex TEXT NOT NULL,
      civil_status TEXT NOT NULL,
      citizenship TEXT NOT NULL,
      religion TEXT,
      contact_number TEXT,
      email_address TEXT,
      relationship_to_head TEXT NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      sync_status TEXT NOT NULL DEFAULT 'synced',
      updated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS field_visits (
      local_id INTEGER PRIMARY KEY AUTOINCREMENT,
      server_id INTEGER UNIQUE,
      mobile_uuid TEXT UNIQUE,
      household_server_id INTEGER,
      household_mobile_uuid TEXT,
      visited_at TEXT NOT NULL,
      notes TEXT,
      photos_json TEXT NOT NULL DEFAULT '[]',
      sync_status TEXT NOT NULL DEFAULT 'synced',
      updated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS risk_assessments (
      local_id INTEGER PRIMARY KEY AUTOINCREMENT,
      server_id INTEGER UNIQUE,
      mobile_uuid TEXT UNIQUE,
      resident_server_id INTEGER NOT NULL,
      recorded_by_user_id INTEGER,
      recorded_by_name TEXT,
      assessment_date TEXT NOT NULL,
      age_years INTEGER,
      religion TEXT,
      contact_number TEXT,
      philhealth_number TEXT,
      civil_status TEXT,
      ethnicity TEXT,
      pwd_id_number TEXT,
      weight_kg REAL,
      height_cm REAL,
      body_mass_index REAL,
      waist_circumference_cm REAL,
      systolic_bp INTEGER,
      diastolic_bp INTEGER,
      employment_status TEXT,
      ip_classification TEXT,
      requires_immediate_referral INTEGER NOT NULL DEFAULT 0,
      identity_snapshot_json TEXT,
      red_flags_json TEXT NOT NULL DEFAULT '{}',
      past_medical_history_json TEXT NOT NULL DEFAULT '{}',
      family_history_json TEXT NOT NULL DEFAULT '{}',
      tobacco_use TEXT,
      alcohol_consumption_status TEXT,
      alcohol_binge_flag INTEGER,
      physical_activity_met INTEGER,
      high_risk_diet_weekly INTEGER,
      blood_sugar_notes TEXT,
      fbs_result TEXT,
      rbs_result TEXT,
      dm_symptoms_json TEXT NOT NULL DEFAULT '{}',
      lipid_profile_date TEXT,
      total_cholesterol TEXT,
      hdl TEXT,
      ldl TEXT,
      vldl TEXT,
      triglycerides TEXT,
      urinalysis_protein TEXT,
      urinalysis_ketones TEXT,
      urinalysis_date TEXT,
      chronic_respiratory_symptoms_json TEXT NOT NULL DEFAULT '{}',
      lifestyle_modification INTEGER,
      anti_hypertensive_medications TEXT,
      oral_hypoglycemic_medications TEXT,
      follow_up_date TEXT,
      remarks TEXT,
      sync_status TEXT NOT NULL DEFAULT 'synced',
      updated_at TEXT
    );
  `);

  await ensureColumn(db, 'households', 'purok_id', 'INTEGER');
  await ensureColumn(db, 'households', 'purok_display_name', 'TEXT');
  for (const table of ['households', 'residents']) {
    await ensureColumn(db, table, 'verification_status', "TEXT NOT NULL DEFAULT 'approved'");
    await ensureColumn(db, table, 'verification_notes', 'TEXT');
  }
  for (const table of SYNC_TABLES) {
    await ensureColumn(db, table, 'local_revision', 'INTEGER NOT NULL DEFAULT 0');
  }
  // Unknown lifecycle values in old caches stay unknown until authoritative refresh.
  await ensureColumn(db, 'residents', 'resident_status', 'TEXT');
  await ensureColumn(db, 'residents', 'deleted_at', 'TEXT');
  await ensureColumn(db, 'residents', 'official_snapshot_json', 'TEXT');
  await ensureColumn(db, 'residents', 'changed_fields_json', 'TEXT');
  await ensureColumn(db, 'residents', 'propose_household_head', 'INTEGER NOT NULL DEFAULT 0');
  await ensureColumn(db, 'households', 'current_head_name', 'TEXT');
  await ensureColumn(db, 'households', 'is_vacant', 'INTEGER');
  await db.execAsync(`CREATE INDEX IF NOT EXISTS residents_current_name
    ON residents(resident_status, last_name, first_name, middle_name, suffix, local_id);
    CREATE INDEX IF NOT EXISTS households_purok ON households(purok_id);`);
  await repairInvalidMobileUuids(db);
}

export async function getAppState(key: string) {
  const db = await getDatabase();
  const row = await db.getFirstAsync<{ value: string }>(
    'SELECT value FROM app_state WHERE key = ?',
    [key]
  );

  return row?.value ?? null;
}

async function repairInvalidMobileUuids(db: SQLite.SQLiteDatabase) {
  await db.withTransactionAsync(async () => {
    const invalidHouseholds = await db.getAllAsync<{
      local_id: number;
      mobile_uuid: string | null;
    }>(
      `SELECT local_id, mobile_uuid
       FROM households
       WHERE mobile_uuid IS NOT NULL`
    );

    for (const household of invalidHouseholds) {
      if (isValidUuid(household.mobile_uuid)) {
        continue;
      }

      const nextUuid = createUuid();

      await db.runAsync(
        `UPDATE households
         SET mobile_uuid = ?
         WHERE local_id = ?`,
        [nextUuid, household.local_id]
      );

      await db.runAsync(
        `UPDATE residents
         SET household_mobile_uuid = ?
         WHERE household_mobile_uuid = ?`,
        [nextUuid, household.mobile_uuid]
      );

      await db.runAsync(
        `UPDATE field_visits
         SET household_mobile_uuid = ?
         WHERE household_mobile_uuid = ?`,
        [nextUuid, household.mobile_uuid]
      );
    }

    const invalidResidents = await db.getAllAsync<{
      local_id: number;
      mobile_uuid: string | null;
    }>(
      `SELECT local_id, mobile_uuid
       FROM residents
       WHERE mobile_uuid IS NOT NULL`
    );

    for (const resident of invalidResidents) {
      if (isValidUuid(resident.mobile_uuid)) {
        continue;
      }

      await db.runAsync(
        `UPDATE residents
         SET mobile_uuid = ?
         WHERE local_id = ?`,
        [createUuid(), resident.local_id]
      );
    }

    const invalidVisits = await db.getAllAsync<{
      local_id: number;
      mobile_uuid: string | null;
    }>(
      `SELECT local_id, mobile_uuid
       FROM field_visits
       WHERE mobile_uuid IS NOT NULL`
    );

    for (const visit of invalidVisits) {
      if (isValidUuid(visit.mobile_uuid)) {
        continue;
      }

      await db.runAsync(
        `UPDATE field_visits
         SET mobile_uuid = ?
         WHERE local_id = ?`,
        [createUuid(), visit.local_id]
      );
    }

    const invalidRiskAssessments = await db.getAllAsync<{
      local_id: number;
      mobile_uuid: string | null;
    }>(
      `SELECT local_id, mobile_uuid
       FROM risk_assessments
       WHERE mobile_uuid IS NOT NULL`
    );

    for (const assessment of invalidRiskAssessments) {
      if (isValidUuid(assessment.mobile_uuid)) {
        continue;
      }

      await db.runAsync(
        `UPDATE risk_assessments
         SET mobile_uuid = ?
         WHERE local_id = ?`,
        [createUuid(), assessment.local_id]
      );
    }
  });
}

export async function setAppState(key: string, value: string) {
  const db = await getDatabase();
  await db.runAsync(
    'INSERT INTO app_state (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
    [key, value]
  );
}

export async function storeToken(token: string) {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function loadToken() {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function clearToken() {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}

async function replaceBootstrapDataInternal(payload: BootstrapPayload, applicable: () => boolean) {
  const db = await getDatabase();

  await db.withExclusiveTransactionAsync(async (db) => {
    if (!applicable()) throw new DatasetOwnershipError();
    const signature = assignmentSignature(payload.user.id, payload.assignment);
    const expected = (await db.getFirstAsync<{ value: string }>(
      'SELECT value FROM app_state WHERE key = ?', ['verified_assignment_signature']))?.value;
    if (!signature || (expected && expected !== signature)) throw new AssignmentChangedError();
    if (payload.resident_contract_version !== RESIDENT_CONTRACT_VERSION) {
      throw new Error('A compatible Resident download is required before using this device.');
    }
    if (hasLocalEditor()) throw new RefreshDeferredError();
    const identities = {} as Record<SyncTable, Map<number, number>>;
    const uuidIdentities = {} as Record<SyncTable, Map<string, number>>;
    const revisions = {} as Record<SyncTable, Map<number, number>>;
    for (const table of SYNC_TABLES) {
      const pending = await db.getFirstAsync(`SELECT 1 FROM ${table} WHERE sync_status != 'synced' LIMIT 1`);
      if (pending) throw new RefreshDeferredError();
      const rows = await db.getAllAsync<{ local_id: number; server_id: number | null; mobile_uuid: string | null; local_revision: number }>(
        `SELECT local_id, server_id, mobile_uuid, local_revision FROM ${table}`
      );
      identities[table] = new Map(rows.filter(row => row.server_id != null).map(row => [row.server_id!, row.local_id]));
      uuidIdentities[table] = new Map(rows.filter(row => row.mobile_uuid).map(row => [row.mobile_uuid!, row.local_id]));
      revisions[table] = new Map(rows.map(row => [row.local_id, row.local_revision]));
    }
    if (hasLocalEditor()) throw new RefreshDeferredError();
    await db.runAsync('DELETE FROM risk_assessments');
    await db.runAsync('DELETE FROM field_visits');
    await db.runAsync('DELETE FROM residents');
    await db.runAsync('DELETE FROM households');

    for (const household of payload.households) {
      const oldId = (household.id == null ? null : identities.households.get(household.id)) ??
        (household.mobile_uuid ? uuidIdentities.households.get(household.mobile_uuid) : null) ?? null;
      await db.runAsync(
        `INSERT INTO households (
          local_id,
          server_id,
          mobile_uuid,
          purok_id,
          purok_display_name,
          household_no,
          household_address,
          is_social_aid_beneficiary,
          is_active,
          sync_status,
          verification_status,
          verification_notes,
          local_revision,
          updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?, ?, ?, ?)`,
        [
          oldId,
          household.id,
          household.mobile_uuid,
          household.purok_id,
          household.purok_display_name,
          household.household_no,
          household.household_address,
          boolToInt(household.is_social_aid_beneficiary),
          boolToInt(household.is_active),
          household.verification_status ?? 'approved',
          household.verification_notes ?? null,
          Math.max(household.local_revision ?? 0, oldId == null ? 0 : revisions.households.get(oldId) ?? 0),
          household.updated_at,
        ]
      );
      await db.runAsync('UPDATE households SET current_head_name = ?, is_vacant = ? WHERE local_id = last_insert_rowid()',
        [household.current_head_name ?? null, household.is_vacant == null ? null : boolToInt(household.is_vacant)]);
    }

    for (const resident of payload.residents) {
      const oldId = (resident.id == null ? null : identities.residents.get(resident.id)) ??
        (resident.mobile_uuid ? uuidIdentities.residents.get(resident.mobile_uuid) : null) ?? null;
      await db.runAsync(
        `INSERT INTO residents (
          local_id,
          server_id,
          mobile_uuid,
          household_server_id,
          household_mobile_uuid,
          philsys_card_no,
          last_name,
          first_name,
          middle_name,
          suffix,
          birth_date,
          birth_place,
          sex,
          civil_status,
          citizenship,
          religion,
          contact_number,
          email_address,
          relationship_to_head,
          is_active,
          resident_status,
          deleted_at,
          sync_status,
          verification_status,
          verification_notes,
          local_revision,
          updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?, ?, ?, ?)`,
        [
          oldId,
          resident.id,
          resident.mobile_uuid,
          resident.household_id,
          resident.household_mobile_uuid,
          resident.philsys_card_no,
          resident.last_name,
          resident.first_name,
          resident.middle_name,
          resident.suffix,
          resident.birth_date,
          resident.birth_place,
          resident.sex,
          resident.civil_status,
          resident.citizenship,
          resident.religion,
          resident.contact_number,
          resident.email_address,
          resident.relationship_to_head,
          boolToInt(resident.is_active),
          resident.resident_status ?? null,
          resident.deleted_at ?? null,
          resident.verification_status ?? 'approved',
          resident.verification_notes ?? null,
          Math.max(resident.local_revision ?? 0, oldId == null ? 0 : revisions.residents.get(oldId) ?? 0),
          resident.updated_at,
        ]
      );
      const officialSnapshot = resident.id == null ? null : resident.official_snapshot ?? residentSnapshot({ ...resident,
        household_server_id: resident.household_id });
      await db.runAsync(`UPDATE residents SET official_snapshot_json = ?, changed_fields_json = NULL,
        propose_household_head = ? WHERE local_id = last_insert_rowid()`,
        [officialSnapshot ? JSON.stringify(officialSnapshot) : null, boolToInt(resident.propose_household_head ?? false)]);
    }

    for (const visit of payload.field_visits) {
      await db.runAsync(
        `INSERT INTO field_visits (
          local_id,
          server_id,
          mobile_uuid,
          household_server_id,
          household_mobile_uuid,
          visited_at,
          notes,
          photos_json,
          sync_status,
          updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?)`,
        [
          identities.field_visits.get(visit.id) ?? null,
          visit.id,
          visit.mobile_uuid,
          visit.household_id,
          visit.household_mobile_uuid,
          visit.visited_at,
          visit.notes,
          JSON.stringify(visit.photos ?? []),
          visit.updated_at,
        ]
      );
    }

    for (const assessment of payload.risk_assessments) {
      await db.runAsync(
        `INSERT INTO risk_assessments (
          local_id,
          server_id,
          mobile_uuid,
          resident_server_id,
          recorded_by_user_id,
          recorded_by_name,
          assessment_date,
          age_years,
          religion,
          contact_number,
          philhealth_number,
          civil_status,
          ethnicity,
          pwd_id_number,
          weight_kg,
          height_cm,
          body_mass_index,
          waist_circumference_cm,
          systolic_bp,
          diastolic_bp,
          employment_status,
          ip_classification,
          requires_immediate_referral,
          identity_snapshot_json,
          red_flags_json,
          past_medical_history_json,
          family_history_json,
          tobacco_use,
          alcohol_consumption_status,
          alcohol_binge_flag,
          physical_activity_met,
          high_risk_diet_weekly,
          blood_sugar_notes,
          fbs_result,
          rbs_result,
          dm_symptoms_json,
          lipid_profile_date,
          total_cholesterol,
          hdl,
          ldl,
          vldl,
          triglycerides,
          urinalysis_protein,
          urinalysis_ketones,
          urinalysis_date,
          chronic_respiratory_symptoms_json,
          lifestyle_modification,
          anti_hypertensive_medications,
          oral_hypoglycemic_medications,
          follow_up_date,
          remarks,
          sync_status,
          updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?)`,
        [
          identities.risk_assessments.get(assessment.id) ?? null,
          assessment.id,
          assessment.mobile_uuid,
          assessment.resident_id,
          assessment.recorded_by_user_id,
          assessment.recorded_by_name,
          assessment.assessment_date,
          assessment.age_years,
          assessment.religion,
          assessment.contact_number,
          assessment.philhealth_number,
          assessment.civil_status,
          assessment.ethnicity,
          assessment.pwd_id_number,
          assessment.weight_kg,
          assessment.height_cm,
          assessment.body_mass_index,
          assessment.waist_circumference_cm,
          assessment.systolic_bp,
          assessment.diastolic_bp,
          assessment.employment_status,
          assessment.ip_classification,
          boolToInt(assessment.requires_immediate_referral),
          JSON.stringify(assessment.identity_snapshot ?? null),
          JSON.stringify(assessment.red_flags ?? {}),
          JSON.stringify(assessment.past_medical_history ?? {}),
          JSON.stringify(assessment.family_history ?? {}),
          assessment.tobacco_use,
          assessment.alcohol_consumption_status,
          assessment.alcohol_binge_flag === null ? null : boolToInt(Boolean(assessment.alcohol_binge_flag)),
          assessment.physical_activity_met === null ? null : boolToInt(Boolean(assessment.physical_activity_met)),
          assessment.high_risk_diet_weekly === null ? null : boolToInt(Boolean(assessment.high_risk_diet_weekly)),
          assessment.blood_sugar_notes,
          assessment.fbs_result,
          assessment.rbs_result,
          JSON.stringify(assessment.dm_symptoms ?? {}),
          assessment.lipid_profile_date,
          assessment.total_cholesterol,
          assessment.hdl,
          assessment.ldl,
          assessment.vldl,
          assessment.triglycerides,
          assessment.urinalysis_protein,
          assessment.urinalysis_ketones,
          assessment.urinalysis_date,
          JSON.stringify(assessment.chronic_respiratory_symptoms ?? {}),
          assessment.lifestyle_modification === null ? null : boolToInt(Boolean(assessment.lifestyle_modification)),
          assessment.anti_hypertensive_medications,
          assessment.oral_hypoglycemic_medications,
          assessment.follow_up_date,
          assessment.remarks,
          assessment.updated_at,
        ]
      );
    }
    for (const [key, value] of Object.entries({
      bootstrap_completed: '1', dataset_owner_user_id: String(payload.user.id),
      dataset_assignment: JSON.stringify(payload.assignment), last_sync_at: payload.server_time,
      session_assignment: JSON.stringify(payload.assignment),
      resident_contract_version: String(payload.resident_contract_version ?? 0),
      verified_assignment_signature: assignmentSignature(payload.user.id, payload.assignment) ?? '',
      resident_workspace_blocked: '',
      resident_relationship_choices: JSON.stringify(payload.resident_relationship_choices ?? []),
      resident_query_version: String(Number((await db.getFirstAsync<{ value: string }>(
        'SELECT value FROM app_state WHERE key = ?', ['resident_query_version']))?.value ?? 0) + 1),
    })) {
      await db.runAsync('INSERT OR REPLACE INTO app_state (key, value) VALUES (?, ?)', [key, value]);
    }
    // A form may have mounted while asynchronous inserts were running. Roll back safely.
    if (hasLocalEditor()) throw new RefreshDeferredError();
    if (!applicable()) throw new DatasetOwnershipError();
  });
}

async function ownedWrite<T>(userId: number | undefined, operation: () => Promise<T>, transition = false) {
  return serializeLocalWrite(async () => {
    await assertDatasetOwner(userId);
    if (!transition) await assertUploadAssignment();
    const result = await operation();
    if (!transition) await setAppState('resident_query_version', String(Number(await getAppState('resident_query_version') ?? 0) + 1));
    return result;
  });
}

export async function assertDatasetOwner(userId: number | undefined) {
  if (!userId || !Number.isSafeInteger(userId) || userId < 1 ||
      await getDatasetOwnerUserId() !== String(userId)) {
    throw new DatasetOwnershipError();
  }
}

export const prepareDatasetForUser = (userId: number) => serializeLocalWrite(async () => {
  if (!Number.isSafeInteger(userId) || userId < 1) throw new DatasetOwnershipError();
  const db = await getDatabase();
  await db.withExclusiveTransactionAsync(async db => {
    const owner = await db.getFirstAsync<{ value: string }>(
      'SELECT value FROM app_state WHERE key = ?', ['dataset_owner_user_id']
    );
    if (owner?.value === String(userId)) return;
    // Unknown ownership is not permission to claim or discard pending work.
    for (const table of SYNC_TABLES) {
      if (await db.getFirstAsync(`SELECT 1 FROM ${table} WHERE sync_status != 'synced' LIMIT 1`)) {
        throw new AccountSwitchBlockedError();
      }
    }
    if (hasLocalEditor()) throw new RefreshDeferredError();
    for (const table of [...SYNC_TABLES].reverse()) await db.runAsync(`DELETE FROM ${table}`);
    for (const [key, value] of Object.entries({
      bootstrap_completed: '0', dataset_owner_user_id: String(userId),
      dataset_assignment: '', last_sync_at: '', session_user: '', session_assignment: '',
      resident_contract_version: '', verified_assignment_signature: '', resident_workspace_blocked: '',
    })) {
      await db.runAsync('INSERT OR REPLACE INTO app_state (key, value) VALUES (?, ?)', [key, value]);
    }
    if (hasLocalEditor()) throw new RefreshDeferredError();
  });
});

// Logout removes credentials only. Records, ownership and visit photos stay intact.
export const clearLocalSession = () => serializeLocalWrite(async () => {
  await clearToken();
  await setAppState('session_user', '');
  await setAppState('session_assignment', '');
});

export async function getDatasetOwnerUserId() {
  return getAppState('dataset_owner_user_id');
}

export async function getDatasetAssignment() {
  return getAppState('dataset_assignment');
}

export function assignmentSignature(userId: number, assignment: MobileAssignment): string | null {
  const ids = [userId, assignment?.barangay?.id, assignment?.purok?.id];
  return ids.every(id => typeof id === 'number' && Number.isSafeInteger(id) && id > 0)
    ? ids.join(':') : null;
}

function storedAssignment(raw: string | null): MobileAssignment {
  return parseJsonValue<MobileAssignment>(raw, { barangay: null, purok: null });
}

// Verification invalidates access, not records. Dataset assignment changes only
// in the successful bootstrap transaction, including after an app restart.
export const verifyDatasetAssignment = (userId: number, barangayId: number, purokId: number, applicable = () => true) =>
  serializeLocalWrite(async () => {
    if (!applicable()) throw new DatasetOwnershipError();
    await assertDatasetOwner(userId);
    const signature = assignmentSignature(userId, {
      barangay: { id: barangayId } as MobileAssignment['barangay'],
      purok: { id: purokId } as MobileAssignment['purok'],
    });
    const old = assignmentSignature(userId, storedAssignment(await getDatasetAssignment()));
    const changed = Boolean(old ? old !== signature :
      await getAppState('bootstrap_completed') === '1' || (await getPendingChangeSummary()).total > 0);
    const db = await getDatabase();
    await db.withExclusiveTransactionAsync(async txn => {
      const contract = (await txn.getFirstAsync<{ value: string }>(
        'SELECT value FROM app_state WHERE key = ?', ['resident_contract_version']))?.value;
      const values: Record<string, string> = {
        verified_assignment_signature: signature ?? 'invalid',
        resident_workspace_blocked: !signature || changed ? 'assignment' :
          contract !== String(RESIDENT_CONTRACT_VERSION) ? 'contract' : '',
      };
      if (!signature || changed) {
        const version = (await txn.getFirstAsync<{ value: string }>(
          'SELECT value FROM app_state WHERE key = ?', ['resident_query_version']))?.value;
        values.resident_query_version = String(Number(version ?? 0) + 1);
      }
      for (const [key, value] of Object.entries(values)) {
        await txn.runAsync('INSERT OR REPLACE INTO app_state (key, value) VALUES (?, ?)', [key, value]);
      }
      if (!applicable()) throw new DatasetOwnershipError();
    });
    return hasBootstrapData();
  });

async function assertUploadAssignment() {
  const owner = Number(await getDatasetOwnerUserId());
  const old = assignmentSignature(owner, storedAssignment(await getDatasetAssignment()));
  const verified = await getAppState('verified_assignment_signature');
  if (await getAppState('resident_workspace_blocked') === 'assignment' ||
      (old && verified && old !== verified)) throw new AssignmentChangedError();
}

async function currentResidentScope() {
  if (!await hasBootstrapData()) return null;
  const assignment = storedAssignment(await getDatasetAssignment());
  return { purokId: assignment.purok!.id,
    version: `${await getAppState('verified_assignment_signature')}:${await getAppState('resident_query_version') ?? '0'}` };
}

export async function getHouseholds(search = ''): Promise<HouseholdRecord[]> {
  const db = await getDatabase();
  const rows = await db.getAllAsync<{
    local_id: number;
    server_id: number | null;
    mobile_uuid: string | null;
    purok_id: number | null;
    purok_display_name: string | null;
    household_no: string;
    household_address: string;
    is_social_aid_beneficiary: number;
    is_active: number;
    is_vacant: number | null;
    sync_status: HouseholdRecord['sync_status'];
    updated_at: string | null;
  }>(
    `SELECT * FROM households
     WHERE household_no LIKE ? OR household_address LIKE ?
     ORDER BY household_no ASC`,
    [`%${search}%`, `%${search}%`]
  );

  return rows.map((row) => ({
    ...row,
    is_social_aid_beneficiary: intToBool(row.is_social_aid_beneficiary),
    is_active: intToBool(row.is_active),
    is_vacant: row.is_vacant == null ? undefined : intToBool(row.is_vacant),
  }));
}

export type CurrentResidentCriteria = {
  search?: string;
  sex?: 'Male' | 'Female';
  ageGroup?: '0-5' | '6-12' | '13-17' | '18-59' | '60+';
  householdLocalId?: number;
  sort?: 'nameAsc' | 'nameDesc' | 'youngest' | 'oldest' | 'household';
  screening?: 'due30' | 'allAdults' | 'assessed' | 'allResidents';
  asOf?: string;
};
export type ResidentContinuation = { offset: number; version: string; criteria: string };

const RESIDENT_FROM = `FROM residents
  JOIN households ON (residents.household_server_id IS NOT NULL AND households.server_id = residents.household_server_id)
    OR (residents.household_server_id IS NULL AND residents.household_mobile_uuid IS NOT NULL
      AND TRIM(residents.household_mobile_uuid) <> '' AND households.mobile_uuid = residents.household_mobile_uuid)
  LEFT JOIN (SELECT resident_server_id, MAX(assessment_date) AS latest_risk_assessment_date
    FROM risk_assessments GROUP BY resident_server_id) AS latest_risk_assessments
    ON latest_risk_assessments.resident_server_id = residents.server_id`;
const RESIDENT_SELECT = `SELECT residents.*, households.household_no,
  households.purok_id AS household_purok_id, households.purok_display_name AS household_purok_display_name,
  latest_risk_assessments.latest_risk_assessment_date ${RESIDENT_FROM}`;
const CURRENT_RESIDENT_WHERE = `residents.server_id IS NOT NULL AND residents.resident_status = 'active'
  AND residents.deleted_at IS NULL AND households.purok_id = ?`;
const RESIDENT_ORDER = `residents.last_name COLLATE NOCASE, residents.first_name COLLATE NOCASE,
  COALESCE(residents.middle_name, '') COLLATE NOCASE, COALESCE(residents.suffix, '') COLLATE NOCASE, residents.local_id`;

function residentRecord(row: any): ResidentRecord {
  return { ...row, is_active: intToBool(row.is_active), propose_household_head: intToBool(row.propose_household_head),
    official_snapshot: parseJsonValue(row.official_snapshot_json, null) };
}

export async function getResidentRelationshipChoices(): Promise<string[]> {
  return parseJsonValue(await getAppState('resident_relationship_choices'), []);
}

export async function getResidentHouseholdOptions(mode: ResidentFormMode, search = ''): Promise<HouseholdRecord[]> {
  const scope = await currentResidentScope();
  if (!scope) return [];
  const query = normalizeHouseholdSearch(search);
  const households = await getHouseholds();
  const result = households.filter(h => eligibleResidentHousehold(h, mode, scope.purokId) &&
    normalizeHouseholdSearch(`${h.household_no} ${h.current_head_name ?? ''} ${h.household_address}`).includes(query)).sort(compareHouseholds);
  return (await currentResidentScope())?.version === scope.version ? result : [];
}

function residentCriteria(criteria: CurrentResidentCriteria, purokId: number) {
  let where = CURRENT_RESIDENT_WHERE;
  const params: (string | number)[] = [purokId];
  for (const token of (criteria.search ?? '').trim().split(/\s+/).filter(Boolean)) {
    const search = `%${token.toLowerCase().replace(/[\\%_]/g, '\\$&')}%`;
    // SQLite NOCASE is ASCII-only; fold the accented capitals used in local names too.
    const fields = ['residents.first_name', 'residents.last_name', "COALESCE(residents.middle_name, '')",
      "COALESCE(residents.suffix, '')", "COALESCE(households.household_no, '')"];
    where += ' AND (' + fields.map(field => {
      for (const [upper, lower] of [['Ñ', 'ñ'], ['É', 'é'], ['Á', 'á'], ['Í', 'í'], ['Ó', 'ó'], ['Ú', 'ú'], ['Ü', 'ü']]) {
        field = `REPLACE(${field}, '${upper}', '${lower}')`;
      }
      params.push(search);
      return `${field} LIKE ? ESCAPE '\\'`;
    }).join(' OR ') + ')';
  }
  if (criteria.sex) { where += ' AND residents.sex = ?'; params.push(criteria.sex); }
  if (criteria.householdLocalId !== undefined) { where += ' AND households.local_id = ?'; params.push(criteria.householdLocalId); }
  if (criteria.ageGroup) {
    const ranges = { '0-5': [0, 5], '6-12': [6, 12], '13-17': [13, 17], '18-59': [18, 59], '60+': [60, 999] };
    const range = ranges[criteria.ageGroup];
    const today = criteria.asOf ?? localCalendarDate();
    where += ` AND ${residentAgeSql(today)} BETWEEN ? AND ?`;
    params.push(...range);
  }
  // Preserve the existing Directory screening choices while paging in SQL.
  if (criteria.screening && criteria.screening !== 'allResidents') {
    where += ` AND date(residents.birth_date) <= date(?, '-20 years')`;
    params.push(criteria.asOf ?? new Date().toISOString());
    if (criteria.screening === 'assessed') where += ' AND latest_risk_assessment_date IS NOT NULL';
    if (criteria.screening === 'due30') {
      where += ` AND (latest_risk_assessment_date IS NULL OR julianday(latest_risk_assessment_date) IS NULL
        OR julianday(latest_risk_assessment_date) > julianday(?)
        OR CAST(julianday(?) - julianday(latest_risk_assessment_date) AS INTEGER) > 30)`;
      params.push(criteria.asOf ?? new Date().toISOString(), criteria.asOf ?? new Date().toISOString());
    }
  }
  return { where, params };
}

function localCalendarDate() {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function residentAgeSql(reference: string) {
  // Only validated calendar keys enter SQL; values from the request are never interpolated.
  const date = /^\d{4}-\d{2}-\d{2}$/.test(reference) ? reference : localCalendarDate();
  return `(CASE WHEN date(substr(residents.birth_date, 1, 10), '+0 days') = substr(residents.birth_date, 1, 10)
    AND substr(residents.birth_date, 1, 10) <= '${date}' THEN
    CAST(substr('${date}', 1, 4) AS INTEGER) - CAST(substr(residents.birth_date, 1, 4) AS INTEGER)
    - (substr('${date}', 6, 5) < substr(residents.birth_date, 6, 5)) ELSE NULL END)`;
}

async function residentOrder(criteria: CurrentResidentCriteria) {
  if (criteria.sort === 'nameDesc') return RESIDENT_ORDER.split(', residents.local_id')[0]
    .replaceAll('COLLATE NOCASE', 'COLLATE NOCASE DESC') + ', residents.local_id';
  if (criteria.sort === 'youngest' || criteria.sort === 'oldest') {
    const age = residentAgeSql(criteria.asOf ?? localCalendarDate());
    return `${age} IS NULL, ${age} ${criteria.sort === 'youngest' ? 'ASC' : 'DESC'}, ${RESIDENT_ORDER}`;
  }
  if (criteria.sort === 'household') {
    const homes = await getCurrentOfficialHouseholds();
    // Rank only scoped household IDs using Part 2 natural ordering; residents stay SQL-paged.
    if (homes.length) return `CASE households.local_id ${homes.map((home, index) =>
      `WHEN ${Number(home.local_id)} THEN ${index}`).join(' ')} ELSE ${homes.length} END, ${RESIDENT_ORDER}`;
  }
  return RESIDENT_ORDER;
}

export async function getCurrentOfficialHouseholds(): Promise<HouseholdRecord[]> {
  const scope = await currentResidentScope();
  if (!scope) return [];
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>('SELECT * FROM households WHERE server_id IS NOT NULL AND purok_id = ?', [scope.purokId]);
  return (await currentResidentScope())?.version === scope.version ? rows.map(row => ({ ...row,
    is_active: intToBool(row.is_active), is_social_aid_beneficiary: intToBool(row.is_social_aid_beneficiary),
    is_vacant: row.is_vacant == null ? undefined : intToBool(row.is_vacant),
  })).sort(compareHouseholds) : [];
}

export async function getCurrentOfficialHouseholdCount() {
  return (await getCurrentOfficialHouseholds()).length;
}

// The cache carries new requests and the latest correction outcome, not a full historical ledger.
export async function getResidentWorkflowItems(): Promise<ResidentRecord[]> {
  const scope = await currentResidentScope();
  if (!scope) return [];
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>(`${RESIDENT_SELECT} WHERE households.purok_id = ? AND (
    residents.server_id IS NULL OR (${CURRENT_RESIDENT_WHERE} AND (
      residents.sync_status != 'synced' OR residents.verification_status IN ('submitted', 'rejected')
      OR residents.changed_fields_json IS NOT NULL OR residents.verification_notes IS NOT NULL))) ORDER BY ${RESIDENT_ORDER}`,
    [scope.purokId, scope.purokId]);
  return (await currentResidentScope())?.version === scope.version ? rows.map(residentRecord) : [];
}

export async function getCurrentOfficialResidentCount(criteria: CurrentResidentCriteria = {}): Promise<number> {
  const scope = await currentResidentScope();
  if (!scope) return 0;
  const { where, params } = residentCriteria(criteria, scope.purokId);
  const db = await getDatabase();
  const row = await db.getFirstAsync<{ total: number }>(`SELECT COUNT(*) AS total ${RESIDENT_FROM} WHERE ${where}`, params);
  return (await currentResidentScope())?.version === scope.version ? row?.total ?? 0 : 0;
}

export async function getCurrentOfficialResidentsPage(criteria: CurrentResidentCriteria = {}, continuation?: ResidentContinuation | null) {
  const empty = { rows: [] as ResidentRecord[], total: 0, next: null as ResidentContinuation | null, invalidated: true };
  const scope = await currentResidentScope();
  if (!scope) return empty;
  const key = JSON.stringify(criteria);
  if (continuation && (continuation.version !== scope.version || continuation.criteria !== key)) return empty;
  const offset = continuation?.offset ?? 0;
  if (!Number.isSafeInteger(offset) || offset < 0) return empty;
  const { where, params } = residentCriteria(criteria, scope.purokId);
  const db = await getDatabase();
  const order = await residentOrder(criteria);
  const rows = await db.getAllAsync<any>(`${RESIDENT_SELECT} WHERE ${where} ORDER BY ${order} LIMIT ? OFFSET ?`,
    [...params, CURRENT_RESIDENT_PAGE_SIZE + 1, offset]);
  const total = await getCurrentOfficialResidentCount(criteria);
  if ((await currentResidentScope())?.version !== scope.version) return empty;
  return { rows: rows.slice(0, CURRENT_RESIDENT_PAGE_SIZE).map(residentRecord), total,
    next: rows.length > CURRENT_RESIDENT_PAGE_SIZE ? { offset: offset + CURRENT_RESIDENT_PAGE_SIZE, version: scope.version, criteria: key } : null,
    invalidated: false };
}

// Compatibility reader for list callers: always bounded and never includes requests.
export async function getResidents(search = ''): Promise<ResidentRecord[]> {
  return (await getCurrentOfficialResidentsPage({ search })).rows;
}

export async function getResidentRequests(search = ''): Promise<ResidentRecord[]> {
  const scope = await currentResidentScope();
  if (!scope) return [];
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>(`${RESIDENT_SELECT} WHERE residents.server_id IS NULL AND households.purok_id = ?
    AND (residents.first_name LIKE ? OR residents.last_name LIKE ?) ORDER BY ${RESIDENT_ORDER}`,
    [scope.purokId, `%${search}%`, `%${search}%`]);
  return (await currentResidentScope())?.version === scope.version ? rows.map(residentRecord) : [];
}

export async function getResidentRequestByLocalId(localId: number) {
  const scope = await currentResidentScope();
  if (!scope || !Number.isSafeInteger(localId) || localId < 1) return null;
  const db = await getDatabase();
  const row = await db.getFirstAsync<any>(`${RESIDENT_SELECT} WHERE residents.server_id IS NULL
    AND households.purok_id = ? AND residents.local_id = ?`, [scope.purokId, localId]);
  return row && (await currentResidentScope())?.version === scope.version ? residentRecord(row) : null;
}

export async function getVisits(search = ''): Promise<FieldVisitRecord[]> {
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>(
    `SELECT
      field_visits.*,
      households.household_no AS household_no,
      households.purok_id AS household_purok_id,
      households.purok_display_name AS household_purok_display_name
     FROM field_visits
     LEFT JOIN households ON households.server_id = field_visits.household_server_id
       OR (households.mobile_uuid IS NOT NULL AND TRIM(households.mobile_uuid) <> '' AND households.mobile_uuid = field_visits.household_mobile_uuid)
     WHERE COALESCE(households.household_no, '') LIKE ? OR COALESCE(field_visits.notes, '') LIKE ?
     ORDER BY field_visits.visited_at DESC`,
    [`%${search}%`, `%${search}%`]
  );

  return rows.map((row: any) => ({
    ...row,
    household_purok_id: row.household_purok_id ?? null,
    photos: parsePhotos(row.photos_json),
  }));
}

export async function getHouseholdByLocalId(localId: number) {
  const households = await getHouseholds();
  return households.find((household) => household.local_id === localId) ?? null;
}

export async function getResidentByLocalId(localId: number) {
  const scope = await currentResidentScope();
  if (!scope || !Number.isSafeInteger(localId) || localId < 1) return null;
  const db = await getDatabase();
  const row = await db.getFirstAsync<any>(`${RESIDENT_SELECT} WHERE ${CURRENT_RESIDENT_WHERE} AND residents.local_id = ?`, [scope.purokId, localId]);
  return row && (await currentResidentScope())?.version === scope.version ? residentRecord(row) : null;
}

export async function getResidentsForHousehold(household: {
  server_id?: number | null;
  mobile_uuid?: string | null;
}) {
  const scope = await currentResidentScope();
  if (!scope) return [];
  const db = await getDatabase();
  const reference = household.server_id != null ? 'residents.household_server_id = ?' :
    "residents.household_server_id IS NULL AND residents.household_mobile_uuid IS NOT NULL AND TRIM(residents.household_mobile_uuid) <> '' AND residents.household_mobile_uuid = ?";
  const rows = await db.getAllAsync<any>(`${RESIDENT_SELECT} WHERE ${CURRENT_RESIDENT_WHERE} AND (${reference}) ORDER BY ${RESIDENT_ORDER}`,
    [scope.purokId, household.server_id ?? household.mobile_uuid ?? null]);
  return (await currentResidentScope())?.version === scope.version ? rows.map(residentRecord) : [];
}

export async function getVisitByLocalId(localId: number) {
  const visits = await getVisits();
  return visits.find((visit) => visit.local_id === localId) ?? null;
}

export async function getRiskAssessmentsForResident(
  residentServerId: number
): Promise<RiskAssessmentRecord[]> {
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>(
    `SELECT *
     FROM risk_assessments
     WHERE resident_server_id = ?
     ORDER BY assessment_date DESC, local_id DESC`,
    [residentServerId]
  );

  return rows.map((row: any) => ({
    ...row,
    requires_immediate_referral: intToBool(row.requires_immediate_referral),
    identity_snapshot: parseJsonValue(row.identity_snapshot_json, null),
    red_flags: parseJsonRecord(row.red_flags_json),
    past_medical_history: parseJsonRecord(row.past_medical_history_json),
    family_history: parseJsonRecord(row.family_history_json),
    alcohol_binge_flag:
      row.alcohol_binge_flag === null || row.alcohol_binge_flag === undefined
        ? null
        : intToBool(row.alcohol_binge_flag),
    physical_activity_met:
      row.physical_activity_met === null || row.physical_activity_met === undefined
        ? null
        : intToBool(row.physical_activity_met),
    high_risk_diet_weekly:
      row.high_risk_diet_weekly === null || row.high_risk_diet_weekly === undefined
        ? null
        : intToBool(row.high_risk_diet_weekly),
    dm_symptoms: parseJsonRecord(row.dm_symptoms_json),
    chronic_respiratory_symptoms: parseJsonRecord(row.chronic_respiratory_symptoms_json),
    lifestyle_modification:
      row.lifestyle_modification === null || row.lifestyle_modification === undefined
        ? null
        : intToBool(row.lifestyle_modification),
    weight_kg: toNullableNumber(row.weight_kg),
    height_cm: toNullableNumber(row.height_cm),
    body_mass_index: toNullableNumber(row.body_mass_index),
    waist_circumference_cm: toNullableNumber(row.waist_circumference_cm),
  }));
}

export async function getLatestRiskAssessmentForResident(
  residentServerId: number
): Promise<RiskAssessmentRecord | null> {
  const assessments = await getRiskAssessmentsForResident(residentServerId);

  return assessments[0] ?? null;
}

export async function getRiskAssessmentByLocalId(
  localId: number
): Promise<RiskAssessmentRecord | null> {
  const db = await getDatabase();
  const row = await db.getFirstAsync<any>(
    `SELECT *
     FROM risk_assessments
     WHERE local_id = ?`,
    [localId]
  );

  if (!row) {
    return null;
  }

  return {
    ...row,
    requires_immediate_referral: intToBool(row.requires_immediate_referral),
    identity_snapshot: parseJsonValue(row.identity_snapshot_json, null),
    red_flags: parseJsonRecord(row.red_flags_json),
    past_medical_history: parseJsonRecord(row.past_medical_history_json),
    family_history: parseJsonRecord(row.family_history_json),
    alcohol_binge_flag:
      row.alcohol_binge_flag === null || row.alcohol_binge_flag === undefined
        ? null
        : intToBool(row.alcohol_binge_flag),
    physical_activity_met:
      row.physical_activity_met === null || row.physical_activity_met === undefined
        ? null
        : intToBool(row.physical_activity_met),
    high_risk_diet_weekly:
      row.high_risk_diet_weekly === null || row.high_risk_diet_weekly === undefined
        ? null
        : intToBool(row.high_risk_diet_weekly),
    dm_symptoms: parseJsonRecord(row.dm_symptoms_json),
    chronic_respiratory_symptoms: parseJsonRecord(row.chronic_respiratory_symptoms_json),
    lifestyle_modification:
      row.lifestyle_modification === null || row.lifestyle_modification === undefined
        ? null
        : intToBool(row.lifestyle_modification),
    weight_kg: toNullableNumber(row.weight_kg),
    height_cm: toNullableNumber(row.height_cm),
    body_mass_index: toNullableNumber(row.body_mass_index),
    waist_circumference_cm: toNullableNumber(row.waist_circumference_cm),
  };
}

async function existingIdentity(db: SQLite.SQLiteDatabase, table: SyncTable, localId?: number) {
  if (!localId) return null;
  const row = await db.getFirstAsync<{ server_id: number | null; mobile_uuid: string | null }>(
    `SELECT server_id, mobile_uuid FROM ${table} WHERE local_id = ?`, [localId]
  );
  if (!row) throw new Error('This record is no longer on this device. Please reopen the form.');
  return row;
}

async function saveHouseholdInternal(
  values: Omit<HouseholdRecord, 'sync_status'> & { local_id?: number }
) {
  const db = await getDatabase();
  const current = await existingIdentity(db, 'households', values.local_id);
  const mobileUuid = current?.mobile_uuid ?? values.mobile_uuid ?? createUuid();
  const syncStatus = (current?.server_id ?? values.server_id) ? 'pending_update' : 'pending_create';

  if (values.local_id) {
    await db.runAsync(
      `UPDATE households
       SET mobile_uuid = ?, purok_id = ?, purok_display_name = ?, household_no = ?, household_address = ?, is_social_aid_beneficiary = ?,
           is_active = ?, sync_status = ?, updated_at = ?, local_revision = local_revision + 1
       WHERE local_id = ?`,
      [
        mobileUuid,
        values.purok_id ?? null,
        values.purok_display_name ?? null,
        values.household_no,
        values.household_address,
        boolToInt(values.is_social_aid_beneficiary),
        boolToInt(values.is_active),
        syncStatus,
        new Date().toISOString(),
        values.local_id,
      ]
    );

    return values.local_id;
  }

  const inserted = await db.runAsync(
    `INSERT INTO households (
      server_id,
      mobile_uuid,
      purok_id,
      purok_display_name,
      household_no,
      household_address,
      is_social_aid_beneficiary,
      is_active,
      sync_status,
      verification_status,
      updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      values.server_id ?? null,
      mobileUuid,
      values.purok_id ?? null,
      values.purok_display_name ?? null,
      values.household_no,
      values.household_address,
      boolToInt(values.is_social_aid_beneficiary),
      boolToInt(values.is_active),
      syncStatus,
      'pending',
      new Date().toISOString(),
    ]
  );
  return Number(inserted.lastInsertRowId);
}

async function saveResidentInternal(
  values: Omit<ResidentRecord, 'sync_status'> & { local_id?: number }, db: SQLite.SQLiteDatabase
) {
  const stored = values.local_id ? await db.getFirstAsync<any>('SELECT * FROM residents WHERE local_id = ?', [values.local_id]) : null;
  if (stored) {
    values = { ...stored,
      ...Object.fromEntries(Object.entries(values).filter(([, value]) => value !== undefined)),
      philsys_card_no: stored.philsys_card_no,
      is_active: intToBool(stored.is_active) };
  } else if (!values.is_active) throw new Error('New resident requests must be active.');
  const validation = validateResidentInput(values);
  if (validation) throw new Error(validation);
  const base = parseJsonValue<Record<string, unknown> | null>(stored?.official_snapshot_json ?? null, null);
  const changes = base ? residentChanges(values, base) : null;
  // Keep older queued work on its original contract; do not invent its base or
  // reclassify an unchanged legacy relationship as newly selected canonical data.
  const changedJson = base ? JSON.stringify(changes) : stored ? stored.changed_fields_json ?? null : '{}';
  const current = await existingIdentity(db, 'residents', values.local_id);
  const mobileUuid = current?.mobile_uuid ?? values.mobile_uuid ?? createUuid();
  const syncStatus = (current?.server_id ?? values.server_id) ? 'pending_update' : 'pending_create';

  if (values.local_id) {
    await db.runAsync(
      `UPDATE residents
       SET mobile_uuid = ?, household_server_id = ?, household_mobile_uuid = ?, philsys_card_no = ?, last_name = ?,
           first_name = ?, middle_name = ?, suffix = ?, birth_date = ?, birth_place = ?, sex = ?, civil_status = ?,
           citizenship = ?, religion = ?, contact_number = ?, email_address = ?, relationship_to_head = ?, is_active = ?,
           sync_status = ?, updated_at = ?, local_revision = local_revision + 1
       WHERE local_id = ?`,
      [
        mobileUuid,
        values.household_server_id ?? null,
        values.household_mobile_uuid ?? null,
        values.philsys_card_no ?? null,
        values.last_name,
        values.first_name,
        values.middle_name ?? null,
        values.suffix ?? null,
        values.birth_date,
        values.birth_place,
        values.sex,
        values.civil_status,
        values.citizenship,
        values.religion ?? null,
        values.contact_number ?? null,
        values.email_address ?? null,
        values.relationship_to_head,
        boolToInt(values.is_active),
        syncStatus,
        new Date().toISOString(),
        values.local_id,
      ]
    );

    await db.runAsync('UPDATE residents SET changed_fields_json = ?, propose_household_head = ? WHERE local_id = ?',
      [changedJson, boolToInt(values.propose_household_head ?? false), values.local_id]);
    return values.local_id;
  }

  const inserted = await db.runAsync(
    `INSERT INTO residents (
      server_id,
      mobile_uuid,
      household_server_id,
      household_mobile_uuid,
      philsys_card_no,
      last_name,
      first_name,
      middle_name,
      suffix,
      birth_date,
      birth_place,
      sex,
      civil_status,
      citizenship,
      religion,
      contact_number,
      email_address,
      relationship_to_head,
      is_active,
      sync_status,
      updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      values.server_id ?? null,
      mobileUuid,
      values.household_server_id ?? null,
      values.household_mobile_uuid ?? null,
      values.philsys_card_no ?? null,
      values.last_name,
      values.first_name,
      values.middle_name ?? null,
      values.suffix ?? null,
      values.birth_date,
      values.birth_place,
      values.sex,
      values.civil_status,
      values.citizenship,
      values.religion ?? null,
      values.contact_number ?? null,
      values.email_address ?? null,
      values.relationship_to_head,
      boolToInt(values.is_active),
      syncStatus,
      new Date().toISOString(),
    ]
  );
  const savedId = Number(inserted.lastInsertRowId);
  await db.runAsync('UPDATE residents SET changed_fields_json = ?, propose_household_head = ? WHERE local_id = ?',
    [changedJson, boolToInt(values.propose_household_head ?? false), savedId]);
  await db.runAsync("UPDATE residents SET verification_status = 'pending' WHERE local_id = ?", [savedId]);
  return savedId;
}

async function saveVisitInternal(
  values: Omit<FieldVisitRecord, 'sync_status'> & { local_id?: number }
) {
  const db = await getDatabase();
  const current = await existingIdentity(db, 'field_visits', values.local_id);
  const mobileUuid = current?.mobile_uuid ?? values.mobile_uuid ?? createUuid();
  const syncStatus = (current?.server_id ?? values.server_id) ? 'pending_update' : 'pending_create';

  if (values.local_id) {
    await db.runAsync(
      `UPDATE field_visits
       SET mobile_uuid = ?, household_server_id = ?, household_mobile_uuid = ?, visited_at = ?, notes = ?,
           photos_json = ?, sync_status = ?, updated_at = ?, local_revision = local_revision + 1
       WHERE local_id = ?`,
      [
        mobileUuid,
        values.household_server_id ?? null,
        values.household_mobile_uuid ?? null,
        values.visited_at,
        values.notes ?? null,
        JSON.stringify(values.photos ?? []),
        syncStatus,
        new Date().toISOString(),
        values.local_id,
      ]
    );

    return;
  }

  await db.runAsync(
    `INSERT INTO field_visits (
      server_id,
      mobile_uuid,
      household_server_id,
      household_mobile_uuid,
      visited_at,
      notes,
      photos_json,
      sync_status,
      updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      values.server_id ?? null,
      mobileUuid,
      values.household_server_id ?? null,
      values.household_mobile_uuid ?? null,
      values.visited_at,
      values.notes ?? null,
      JSON.stringify(values.photos ?? []),
      syncStatus,
      new Date().toISOString(),
    ]
  );
}

async function saveRiskAssessmentInternal(
  values: Omit<RiskAssessmentRecord, 'sync_status' | 'requires_immediate_referral'> & {
    local_id?: number;
    requires_immediate_referral?: boolean;
  }
) {
  const db = await getDatabase();
  const existingLocalRecord = values.local_id
    ? await db.getFirstAsync<{
        local_id: number;
        server_id: number | null;
        mobile_uuid: string | null;
        sync_status: SyncStatus;
      }>(
        `SELECT local_id, server_id, mobile_uuid, sync_status
         FROM risk_assessments
         WHERE local_id = ?`,
        [values.local_id]
      )
    : null;
  if (values.local_id && !existingLocalRecord) {
    throw new Error('This record is no longer on this device. Please reopen the form.');
  }
  const shouldCreateNewRecord =
    Boolean(existingLocalRecord?.server_id) ||
    existingLocalRecord?.sync_status === 'synced';
  const targetLocalId = shouldCreateNewRecord ? undefined : values.local_id;
  const mobileUuid = shouldCreateNewRecord
    ? createUuid()
    : existingLocalRecord?.mobile_uuid ?? values.mobile_uuid ?? createUuid();
  const syncStatus: SyncStatus = 'pending_create';
  const requiresImmediateReferral =
    values.requires_immediate_referral ??
    Boolean(
      values.red_flags.chest_pain ||
        values.red_flags.difficulty_breathing ||
        values.red_flags.slurred_speech ||
        values.red_flags.facial_asymmetry
    );

  const params = [
    shouldCreateNewRecord
      ? null
      : values.server_id ?? existingLocalRecord?.server_id ?? null,
    mobileUuid,
    values.resident_server_id,
    values.recorded_by_user_id ?? null,
    values.recorded_by_name ?? null,
    values.assessment_date,
    values.age_years ?? null,
    values.religion ?? null,
    values.contact_number ?? null,
    values.philhealth_number ?? null,
    values.civil_status ?? null,
    values.ethnicity ?? null,
    values.pwd_id_number ?? null,
    values.weight_kg ?? null,
    values.height_cm ?? null,
    values.body_mass_index ?? null,
    values.waist_circumference_cm ?? null,
    values.systolic_bp ?? null,
    values.diastolic_bp ?? null,
    values.employment_status ?? null,
    values.ip_classification ?? null,
    boolToInt(requiresImmediateReferral),
    JSON.stringify(values.identity_snapshot ?? null),
    JSON.stringify(values.red_flags ?? {}),
    JSON.stringify(values.past_medical_history ?? {}),
    JSON.stringify(values.family_history ?? {}),
    values.tobacco_use ?? null,
    values.alcohol_consumption_status ?? null,
    values.alcohol_binge_flag === null || values.alcohol_binge_flag === undefined
      ? null
      : boolToInt(Boolean(values.alcohol_binge_flag)),
    values.physical_activity_met === null || values.physical_activity_met === undefined
      ? null
      : boolToInt(Boolean(values.physical_activity_met)),
    values.high_risk_diet_weekly === null || values.high_risk_diet_weekly === undefined
      ? null
      : boolToInt(Boolean(values.high_risk_diet_weekly)),
    values.blood_sugar_notes ?? null,
    values.fbs_result ?? null,
    values.rbs_result ?? null,
    JSON.stringify(values.dm_symptoms ?? {}),
    values.lipid_profile_date ?? null,
    values.total_cholesterol ?? null,
    values.hdl ?? null,
    values.ldl ?? null,
    values.vldl ?? null,
    values.triglycerides ?? null,
    values.urinalysis_protein ?? null,
    values.urinalysis_ketones ?? null,
    values.urinalysis_date ?? null,
    JSON.stringify(values.chronic_respiratory_symptoms ?? {}),
    values.lifestyle_modification === null || values.lifestyle_modification === undefined
      ? null
      : boolToInt(Boolean(values.lifestyle_modification)),
    values.anti_hypertensive_medications ?? null,
    values.oral_hypoglycemic_medications ?? null,
    values.follow_up_date ?? null,
    values.remarks ?? null,
    syncStatus,
    new Date().toISOString(),
  ];

  if (targetLocalId) {
    await db.runAsync(
      `UPDATE risk_assessments
       SET server_id = ?, mobile_uuid = ?, resident_server_id = ?, recorded_by_user_id = ?, recorded_by_name = ?,
           assessment_date = ?, age_years = ?, religion = ?, contact_number = ?, philhealth_number = ?,
           civil_status = ?, ethnicity = ?, pwd_id_number = ?, weight_kg = ?, height_cm = ?, body_mass_index = ?,
           waist_circumference_cm = ?, systolic_bp = ?, diastolic_bp = ?, employment_status = ?, ip_classification = ?,
           requires_immediate_referral = ?, identity_snapshot_json = ?, red_flags_json = ?, past_medical_history_json = ?,
           family_history_json = ?, tobacco_use = ?, alcohol_consumption_status = ?, alcohol_binge_flag = ?,
           physical_activity_met = ?, high_risk_diet_weekly = ?, blood_sugar_notes = ?, fbs_result = ?, rbs_result = ?,
           dm_symptoms_json = ?, lipid_profile_date = ?, total_cholesterol = ?, hdl = ?, ldl = ?, vldl = ?,
           triglycerides = ?, urinalysis_protein = ?, urinalysis_ketones = ?, urinalysis_date = ?,
           chronic_respiratory_symptoms_json = ?, lifestyle_modification = ?, anti_hypertensive_medications = ?,
           oral_hypoglycemic_medications = ?, follow_up_date = ?, remarks = ?, sync_status = ?, updated_at = ?, local_revision = local_revision + 1
       WHERE local_id = ?`,
      [...params, targetLocalId]
    );

    return;
  }

  await db.runAsync(
    `INSERT INTO risk_assessments (
      server_id,
      mobile_uuid,
      resident_server_id,
      recorded_by_user_id,
      recorded_by_name,
      assessment_date,
      age_years,
      religion,
      contact_number,
      philhealth_number,
      civil_status,
      ethnicity,
      pwd_id_number,
      weight_kg,
      height_cm,
      body_mass_index,
      waist_circumference_cm,
      systolic_bp,
      diastolic_bp,
      employment_status,
      ip_classification,
      requires_immediate_referral,
      identity_snapshot_json,
      red_flags_json,
      past_medical_history_json,
      family_history_json,
      tobacco_use,
      alcohol_consumption_status,
      alcohol_binge_flag,
      physical_activity_met,
      high_risk_diet_weekly,
      blood_sugar_notes,
      fbs_result,
      rbs_result,
      dm_symptoms_json,
      lipid_profile_date,
      total_cholesterol,
      hdl,
      ldl,
      vldl,
      triglycerides,
      urinalysis_protein,
      urinalysis_ketones,
      urinalysis_date,
      chronic_respiratory_symptoms_json,
      lifestyle_modification,
      anti_hypertensive_medications,
      oral_hypoglycemic_medications,
      follow_up_date,
      remarks,
      sync_status,
      updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    params
  );
}

export async function hasBootstrapData() {
  const owner = Number(await getDatasetOwnerUserId());
  const signature = assignmentSignature(owner, storedAssignment(await getDatasetAssignment()));
  return (await getAppState('bootstrap_completed')) === '1' && Boolean(signature) &&
    await getAppState('resident_contract_version') === String(RESIDENT_CONTRACT_VERSION) &&
    await getAppState('verified_assignment_signature') === signature &&
    !await getAppState('resident_workspace_blocked');
}

async function getPendingSyncPayloadInternal() {
  const db = await getDatabase();
  await repairInvalidMobileUuids(db);
  const households = await db.getAllAsync<any>(
    `SELECT * FROM households WHERE sync_status != 'synced' ORDER BY local_id ASC`
  );
  const residents = await db.getAllAsync<any>(
    `SELECT * FROM residents WHERE sync_status != 'synced' ORDER BY local_id ASC`
  );
  const visits = await db.getAllAsync<any>(
    `SELECT * FROM field_visits WHERE sync_status != 'synced' ORDER BY local_id ASC`
  );
  const riskAssessments = await db.getAllAsync<any>(
    `SELECT * FROM risk_assessments WHERE sync_status != 'synced' ORDER BY local_id ASC`
  );

  const snapshot: SnapshotRows = { households, residents, field_visits: visits, risk_assessments: riskAssessments };
  const payload = {
    households: households.map((row: any) => ({
      id: row.server_id ?? undefined,
      mobile_uuid: row.mobile_uuid ?? undefined,
      local_revision: row.local_revision,
      household_no: row.household_no,
      household_address: row.household_address,
      is_social_aid_beneficiary: intToBool(row.is_social_aid_beneficiary),
      is_active: intToBool(row.is_active),
    })),
    residents: residents.map((row: any) => {
      const correction = row.server_id != null && row.changed_fields_json != null;
      const data = {
      id: row.server_id ?? undefined,
      mobile_uuid: row.mobile_uuid ?? undefined,
      local_revision: row.local_revision,
      household_id: row.household_server_id ?? undefined,
      household_mobile_uuid: row.household_mobile_uuid ?? undefined,
      philsys_card_no: row.philsys_card_no ?? undefined,
      last_name: row.last_name,
      first_name: row.first_name,
      middle_name: row.middle_name ?? null,
      suffix: row.suffix ?? null,
      birth_date: row.birth_date,
      birth_place: row.birth_place,
      sex: row.sex,
      civil_status: row.civil_status,
      citizenship: row.citizenship,
      religion: row.religion ?? null,
      contact_number: row.contact_number ?? null,
      email_address: row.email_address ?? null,
      relationship_to_head: row.relationship_to_head,
      is_active: intToBool(row.is_active),
      propose_household_head: intToBool(row.propose_household_head),
      request_contract_version: row.changed_fields_json == null ? undefined : 2,
      };
      if (!correction) return data;
      return { id: data.id, mobile_uuid: data.mobile_uuid, local_revision: data.local_revision,
        household_id: data.household_id, household_mobile_uuid: data.household_mobile_uuid,
        request_contract_version: 2, base_snapshot: parseJsonValue(row.official_snapshot_json, {}),
        proposed_changes: parseJsonValue(row.changed_fields_json, {}) };
    }),
    field_visits: visits.map((row: any) => {
      const photos = parsePhotos(row.photos_json);

      return {
        id: row.server_id ?? undefined,
        mobile_uuid: row.mobile_uuid ?? undefined,
        household_id: row.household_server_id ?? undefined,
        household_mobile_uuid: row.household_mobile_uuid ?? undefined,
        visited_at: row.visited_at,
        notes: row.notes ?? undefined,
        existing_photos: photos
          .filter((photo) => photo.path && !photo.base64)
          .map((photo) => photo.path),
        photos: photos
          .filter((photo) => photo.base64)
          .map((photo) => ({
            file_name: photo.file_name,
            mime_type: photo.mime_type,
            captured_at: photo.captured_at,
            data: photo.base64,
          })),
      };
    }),
    risk_assessments: riskAssessments.map((row: any) => ({
      id: row.server_id ?? undefined,
      mobile_uuid: row.mobile_uuid ?? undefined,
      resident_id: row.resident_server_id,
      assessment_date: row.assessment_date,
      religion: row.religion ?? undefined,
      contact_number: row.contact_number ?? undefined,
      philhealth_number: row.philhealth_number ?? undefined,
      civil_status: row.civil_status ?? undefined,
      ethnicity: row.ethnicity ?? undefined,
      pwd_id_number: row.pwd_id_number ?? undefined,
      weight_kg: toNullableNumber(row.weight_kg) ?? undefined,
      height_cm: toNullableNumber(row.height_cm) ?? undefined,
      waist_circumference_cm: toNullableNumber(row.waist_circumference_cm) ?? undefined,
      systolic_bp: row.systolic_bp ?? undefined,
      diastolic_bp: row.diastolic_bp ?? undefined,
      employment_status: row.employment_status ?? undefined,
      ip_classification: row.ip_classification ?? undefined,
      red_flags: parseJsonRecord(row.red_flags_json),
      past_medical_history: parseJsonRecord(row.past_medical_history_json),
      family_history: parseJsonRecord(row.family_history_json),
      tobacco_use: row.tobacco_use ?? undefined,
      alcohol_consumption_status: row.alcohol_consumption_status ?? undefined,
      alcohol_binge_flag:
        row.alcohol_binge_flag === null || row.alcohol_binge_flag === undefined
          ? undefined
          : intToBool(row.alcohol_binge_flag),
      physical_activity_met:
        row.physical_activity_met === null || row.physical_activity_met === undefined
          ? undefined
          : intToBool(row.physical_activity_met),
      high_risk_diet_weekly:
        row.high_risk_diet_weekly === null || row.high_risk_diet_weekly === undefined
          ? undefined
          : intToBool(row.high_risk_diet_weekly),
      blood_sugar_notes: row.blood_sugar_notes ?? undefined,
      fbs_result: row.fbs_result ?? undefined,
      rbs_result: row.rbs_result ?? undefined,
      dm_symptoms: parseJsonRecord(row.dm_symptoms_json),
      lipid_profile_date: row.lipid_profile_date ?? undefined,
      total_cholesterol: row.total_cholesterol ?? undefined,
      hdl: row.hdl ?? undefined,
      ldl: row.ldl ?? undefined,
      vldl: row.vldl ?? undefined,
      triglycerides: row.triglycerides ?? undefined,
      urinalysis_protein: row.urinalysis_protein ?? undefined,
      urinalysis_ketones: row.urinalysis_ketones ?? undefined,
      urinalysis_date: row.urinalysis_date ?? undefined,
      chronic_respiratory_symptoms: parseJsonRecord(row.chronic_respiratory_symptoms_json),
      lifestyle_modification:
        row.lifestyle_modification === null || row.lifestyle_modification === undefined
          ? undefined
          : intToBool(row.lifestyle_modification),
      anti_hypertensive_medications: row.anti_hypertensive_medications ?? undefined,
      oral_hypoglycemic_medications: row.oral_hypoglycemic_medications ?? undefined,
      follow_up_date: row.follow_up_date ?? undefined,
      remarks: row.remarks ?? undefined,
    })),
  };
  return { snapshot, payload };
}

async function applyResolvedRecordsInternal(resolved: SyncResponse['resolved_records'], snapshot: SnapshotRows) {
  const db = await getDatabase();

  await db.withExclusiveTransactionAsync(async (db) => {
    for (const table of SYNC_TABLES) {
      for (const record of resolved[table]) {
        const uploaded = snapshot[table].find(row => row.mobile_uuid && row.mobile_uuid === record.mobile_uuid);
        if (!uploaded) continue;
        const current = await db.getFirstAsync<any>(
          `SELECT * FROM ${table} WHERE local_id = ? AND mobile_uuid = ?`,
          [uploaded.local_id, uploaded.mobile_uuid]
        );
        if (!current) continue;
        const unchanged = current.local_revision === uploaded.local_revision;

        if (table === 'risk_assessments' && !unchanged) {
          // Assessments are immutable on the server. Keep the newer draft at its
          // existing local ID and retain the acknowledged version as history.
          await db.runAsync(
            `UPDATE risk_assessments SET mobile_uuid = ?, server_id = NULL,
             sync_status = 'pending_create' WHERE local_id = ?`,
            [createUuid(), current.local_id]
          );
          const { local_id: _localId, ...history } = uploaded;
          history.server_id = record.id;
          history.sync_status = 'synced';
          history.updated_at = record.updated_at ?? uploaded.updated_at;
          const columns = Object.keys(history);
          await db.runAsync(
            `INSERT INTO risk_assessments (${columns.join(', ')}) VALUES (${columns.map(() => '?').join(', ')})`,
            Object.values(history)
          );
          continue;
        }

        if (table === 'households' || table === 'residents') {
          await db.runAsync(
            `UPDATE ${table} SET server_id = ?, sync_status = ?, verification_status = ?,
             verification_notes = ?, updated_at = ? WHERE local_id = ?`,
            [record.id ?? current.server_id,
              unchanged ? 'synced' : ((record.id ?? current.server_id) ? 'pending_update' : 'pending_create'),
              record.verification_status ?? 'approved', record.verification_notes ?? null,
              unchanged ? record.updated_at ?? current.updated_at : current.updated_at, current.local_id]
          );
        } else {
          await db.runAsync(
            `UPDATE ${table} SET server_id = ?, sync_status = ?, updated_at = ? WHERE local_id = ?`,
            [record.id, unchanged ? 'synced' : 'pending_update',
              unchanged ? record.updated_at ?? current.updated_at : current.updated_at, current.local_id]
          );
        }
        if (unchanged && (table === 'residents' || table === 'field_visits')) {
          await db.runAsync(
            `UPDATE ${table} SET household_server_id = COALESCE(household_server_id, ?) WHERE local_id = ?`,
            [record.household_id ?? null, current.local_id]
          );
        }
        if (table === 'households' && record.id) {
          for (const childTable of ['residents', 'field_visits']) {
            await db.runAsync(
              `UPDATE ${childTable} SET household_server_id = ? WHERE household_mobile_uuid = ?`,
              [record.id, record.mobile_uuid!]
            );
          }
        }
      }
    }
  });
}

export async function hasPendingChanges() {
  const summary = await getPendingChangeSummary();
  return summary.total > 0;
}

export async function getPendingChangeSummary(): Promise<PendingChangeSummary> {
  const db = await getDatabase();
  const householdRow = await db.getFirstAsync<{ total: number }>(
    `SELECT COUNT(*) AS total FROM households WHERE sync_status != 'synced'`
  );
  const residentRow = await db.getFirstAsync<{ total: number }>(
    `SELECT COUNT(*) AS total FROM residents WHERE sync_status != 'synced'`
  );
  const visitRow = await db.getFirstAsync<{ total: number }>(
    `SELECT COUNT(*) AS total FROM field_visits WHERE sync_status != 'synced'`
  );
  const riskAssessmentRow = await db.getFirstAsync<{ total: number }>(
    `SELECT COUNT(*) AS total FROM risk_assessments WHERE sync_status != 'synced'`
  );

  const households = householdRow?.total ?? 0;
  const residents = residentRow?.total ?? 0;
  const visits = visitRow?.total ?? 0;
  const riskAssessments = riskAssessmentRow?.total ?? 0;

  return {
    households,
    residents,
    visits,
    riskAssessments,
    total: households + residents + visits + riskAssessments,
  };
}
