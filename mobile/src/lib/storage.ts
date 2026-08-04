import * as SecureStore from 'expo-secure-store';
import * as SQLite from 'expo-sqlite';

import {
  BootstrapPayload,
  FieldVisitRecord,
  HouseholdRecord,
  RiskAssessmentRecord,
  ResidentRecord,
  SyncStatus,
  SyncResponse,
  VisitPhoto,
} from '../types';

const TOKEN_KEY = 'healthlink_mobile_token';
const DB_NAME = 'healthlink_bhw.db';
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

export async function replaceBootstrapData(payload: BootstrapPayload) {
  const db = await getDatabase();

  await db.withTransactionAsync(async () => {
    await db.runAsync('DELETE FROM risk_assessments');
    await db.runAsync('DELETE FROM field_visits');
    await db.runAsync('DELETE FROM residents');
    await db.runAsync('DELETE FROM households');

    for (const household of payload.households) {
      await db.runAsync(
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
          updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?)`,
        [
          household.id,
          household.mobile_uuid,
          household.purok_id,
          household.purok_display_name,
          household.household_no,
          household.household_address,
          boolToInt(household.is_social_aid_beneficiary),
          boolToInt(household.is_active),
          household.updated_at,
        ]
      );
    }

    for (const resident of payload.residents) {
      await db.runAsync(
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?)`,
        [
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
          resident.updated_at,
        ]
      );
    }

    for (const visit of payload.field_visits) {
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'synced', ?)`,
        [
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
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'synced', ?)`,
        [
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
  });

  await setAppState('bootstrap_completed', '1');
  await setAppState('dataset_owner_user_id', String(payload.user.id));
  await setAppState('dataset_assignment', JSON.stringify(payload.assignment));
  await setAppState('last_sync_at', payload.server_time);
}

export async function clearOperationalData() {
  const db = await getDatabase();

  await db.withTransactionAsync(async () => {
    await db.runAsync('DELETE FROM risk_assessments');
    await db.runAsync('DELETE FROM field_visits');
    await db.runAsync('DELETE FROM residents');
    await db.runAsync('DELETE FROM households');
  });

  await setAppState('bootstrap_completed', '0');
  await setAppState('dataset_owner_user_id', '');
  await setAppState('dataset_assignment', '');
  await setAppState('last_sync_at', '');
  await setAppState('session_user', '');
  await setAppState('session_assignment', '');
}

export async function getDatasetOwnerUserId() {
  return getAppState('dataset_owner_user_id');
}

export async function getDatasetAssignment() {
  return getAppState('dataset_assignment');
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
  }));
}

export async function getResidents(search = ''): Promise<ResidentRecord[]> {
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>(
    `SELECT
      residents.*,
      households.household_no AS household_no,
      households.purok_id AS household_purok_id,
      households.purok_display_name AS household_purok_display_name,
      latest_risk_assessments.latest_risk_assessment_date AS latest_risk_assessment_date
     FROM residents
     LEFT JOIN households ON households.server_id = residents.household_server_id
       OR (households.mobile_uuid IS NOT NULL AND households.mobile_uuid = residents.household_mobile_uuid)
     LEFT JOIN (
       SELECT resident_server_id, MAX(assessment_date) AS latest_risk_assessment_date
       FROM risk_assessments
       GROUP BY resident_server_id
     ) AS latest_risk_assessments ON latest_risk_assessments.resident_server_id = residents.server_id
     WHERE residents.first_name LIKE ?
        OR residents.last_name LIKE ?
        OR COALESCE(residents.middle_name, '') LIKE ?
        OR COALESCE(residents.suffix, '') LIKE ?
        OR COALESCE(households.household_no, '') LIKE ?
     ORDER BY residents.last_name ASC, residents.first_name ASC`,
    [`%${search}%`, `%${search}%`, `%${search}%`, `%${search}%`, `%${search}%`]
  );

  return rows.map((row: any) => ({
    ...row,
    household_purok_id: row.household_purok_id ?? null,
    household_purok_display_name: row.household_purok_display_name ?? null,
    is_active: intToBool(row.is_active),
  }));
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
       OR (households.mobile_uuid IS NOT NULL AND households.mobile_uuid = field_visits.household_mobile_uuid)
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
  const residents = await getResidents();
  return residents.find((resident) => resident.local_id === localId) ?? null;
}

export async function getResidentsForHousehold(household: {
  server_id?: number | null;
  mobile_uuid?: string | null;
}) {
  const db = await getDatabase();
  const rows = await db.getAllAsync<any>(
    `SELECT
      residents.*,
      households.household_no AS household_no,
      households.purok_id AS household_purok_id,
      households.purok_display_name AS household_purok_display_name
     FROM residents
     LEFT JOIN households ON households.server_id = residents.household_server_id
       OR (households.mobile_uuid IS NOT NULL AND households.mobile_uuid = residents.household_mobile_uuid)
     WHERE (residents.household_server_id = ?)
        OR (residents.household_mobile_uuid IS NOT NULL AND residents.household_mobile_uuid = ?)
     ORDER BY residents.last_name ASC, residents.first_name ASC`,
    [household.server_id ?? null, household.mobile_uuid ?? null]
  );

  return rows.map((row: any) => ({
    ...row,
    household_purok_id: row.household_purok_id ?? null,
    household_purok_display_name: row.household_purok_display_name ?? null,
    is_active: intToBool(row.is_active),
  }));
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

export async function saveHousehold(
  values: Omit<HouseholdRecord, 'sync_status'> & { local_id?: number }
) {
  const db = await getDatabase();
  const mobileUuid = values.mobile_uuid ?? createUuid();
  const syncStatus = values.server_id ? 'pending_update' : 'pending_create';

  if (values.local_id) {
    await db.runAsync(
      `UPDATE households
       SET mobile_uuid = ?, purok_id = ?, purok_display_name = ?, household_no = ?, household_address = ?, is_social_aid_beneficiary = ?,
           is_active = ?, sync_status = ?, updated_at = ?
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

    return;
  }

  await db.runAsync(
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
      updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
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
      new Date().toISOString(),
    ]
  );
}

export async function saveResident(
  values: Omit<ResidentRecord, 'sync_status'> & { local_id?: number }
) {
  const db = await getDatabase();
  const mobileUuid = values.mobile_uuid ?? createUuid();
  const syncStatus = values.server_id ? 'pending_update' : 'pending_create';

  if (values.local_id) {
    await db.runAsync(
      `UPDATE residents
       SET mobile_uuid = ?, household_server_id = ?, household_mobile_uuid = ?, philsys_card_no = ?, last_name = ?,
           first_name = ?, middle_name = ?, suffix = ?, birth_date = ?, birth_place = ?, sex = ?, civil_status = ?,
           citizenship = ?, religion = ?, contact_number = ?, email_address = ?, relationship_to_head = ?, is_active = ?,
           sync_status = ?, updated_at = ?
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

    return;
  }

  await db.runAsync(
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
}

export async function saveVisit(
  values: Omit<FieldVisitRecord, 'sync_status'> & { local_id?: number }
) {
  const db = await getDatabase();
  const mobileUuid = values.mobile_uuid ?? createUuid();
  const syncStatus = values.server_id ? 'pending_update' : 'pending_create';

  if (values.local_id) {
    await db.runAsync(
      `UPDATE field_visits
       SET mobile_uuid = ?, household_server_id = ?, household_mobile_uuid = ?, visited_at = ?, notes = ?,
           photos_json = ?, sync_status = ?, updated_at = ?
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

export async function saveRiskAssessment(
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
  const shouldCreateNewRecord =
    Boolean(existingLocalRecord?.server_id) ||
    existingLocalRecord?.sync_status === 'synced';
  const targetLocalId = shouldCreateNewRecord ? undefined : values.local_id;
  const mobileUuid = shouldCreateNewRecord
    ? createUuid()
    : values.mobile_uuid ?? existingLocalRecord?.mobile_uuid ?? createUuid();
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
           oral_hypoglycemic_medications = ?, follow_up_date = ?, remarks = ?, sync_status = ?, updated_at = ?
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
  return (await getAppState('bootstrap_completed')) === '1';
}

export async function getPendingSyncPayload() {
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

  return {
    households: households.map((row: any) => ({
      id: row.server_id ?? undefined,
      mobile_uuid: row.mobile_uuid ?? undefined,
      household_no: row.household_no,
      household_address: row.household_address,
      is_social_aid_beneficiary: intToBool(row.is_social_aid_beneficiary),
      is_active: intToBool(row.is_active),
    })),
    residents: residents.map((row: any) => ({
      id: row.server_id ?? undefined,
      mobile_uuid: row.mobile_uuid ?? undefined,
      household_id: row.household_server_id ?? undefined,
      household_mobile_uuid: row.household_mobile_uuid ?? undefined,
      philsys_card_no: row.philsys_card_no ?? undefined,
      last_name: row.last_name,
      first_name: row.first_name,
      middle_name: row.middle_name ?? undefined,
      suffix: row.suffix ?? undefined,
      birth_date: row.birth_date,
      birth_place: row.birth_place,
      sex: row.sex,
      civil_status: row.civil_status,
      citizenship: row.citizenship,
      religion: row.religion ?? undefined,
      contact_number: row.contact_number ?? undefined,
      email_address: row.email_address ?? undefined,
      relationship_to_head: row.relationship_to_head,
      is_active: intToBool(row.is_active),
    })),
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
}

export async function applyResolvedRecords(resolved: SyncResponse['resolved_records']) {
  const db = await getDatabase();

  await db.withTransactionAsync(async () => {
    for (const household of resolved.households) {
      if (!household.mobile_uuid) continue;

      await db.runAsync(
        `UPDATE households
         SET server_id = ?, sync_status = 'synced', updated_at = ?
         WHERE mobile_uuid = ?`,
        [household.id, household.updated_at ?? new Date().toISOString(), household.mobile_uuid]
      );

      await db.runAsync(
        `UPDATE residents
         SET household_server_id = ?
         WHERE household_mobile_uuid = ?`,
        [household.id, household.mobile_uuid]
      );

      await db.runAsync(
        `UPDATE field_visits
         SET household_server_id = ?
         WHERE household_mobile_uuid = ?`,
        [household.id, household.mobile_uuid]
      );
    }

    for (const resident of resolved.residents) {
      if (!resident.mobile_uuid) continue;

      await db.runAsync(
        `UPDATE residents
         SET server_id = ?, household_server_id = COALESCE(household_server_id, ?),
             sync_status = 'synced', updated_at = ?
         WHERE mobile_uuid = ?`,
        [
          resident.id,
          resident.household_id ?? null,
          resident.updated_at ?? new Date().toISOString(),
          resident.mobile_uuid,
        ]
      );
    }

    for (const visit of resolved.field_visits) {
      if (!visit.mobile_uuid) continue;

      await db.runAsync(
        `UPDATE field_visits
         SET server_id = ?, household_server_id = COALESCE(household_server_id, ?),
             sync_status = 'synced', updated_at = ?
         WHERE mobile_uuid = ?`,
        [
          visit.id,
          visit.household_id ?? null,
          visit.updated_at ?? new Date().toISOString(),
          visit.mobile_uuid,
        ]
      );
    }

    for (const assessment of resolved.risk_assessments) {
      if (!assessment.mobile_uuid) continue;

      await db.runAsync(
        `UPDATE risk_assessments
         SET server_id = ?, sync_status = 'synced', updated_at = ?
         WHERE mobile_uuid = ?`,
        [
          assessment.id,
          assessment.updated_at ?? new Date().toISOString(),
          assessment.mobile_uuid,
        ]
      );
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
