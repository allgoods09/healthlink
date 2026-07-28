export type SyncStatus = 'synced' | 'pending_create' | 'pending_update';

export type MobileUser = {
  id: number;
  name: string;
  email: string;
  role: 'bhw';
  assignment_label: string;
  assigned_barangay_id: number;
  assigned_purok_id: number;
};

export type MobileAssignment = {
  barangay: {
    id: number;
    name: string;
    municipality: string;
    province: string;
  } | null;
  purok: {
    id: number;
    purok_number: number;
    purok_name: string | null;
    display_name: string;
  } | null;
};

export type HouseholdRecord = {
  local_id?: number;
  server_id?: number | null;
  mobile_uuid?: string | null;
  purok_id?: number | null;
  purok_display_name?: string | null;
  household_no: string;
  household_address: string;
  is_social_aid_beneficiary: boolean;
  is_active: boolean;
  resident_count?: number;
  sync_status: SyncStatus;
  updated_at?: string | null;
};

export type ResidentRecord = {
  local_id?: number;
  server_id?: number | null;
  mobile_uuid?: string | null;
  household_server_id?: number | null;
  household_mobile_uuid?: string | null;
  household_no?: string | null;
  household_purok_id?: number | null;
  household_purok_display_name?: string | null;
  philsys_card_no?: string | null;
  last_name: string;
  first_name: string;
  middle_name?: string | null;
  suffix?: string | null;
  birth_date: string;
  birth_place: string;
  sex: 'Male' | 'Female';
  civil_status: string;
  citizenship: string;
  religion?: string | null;
  contact_number?: string | null;
  email_address?: string | null;
  relationship_to_head: string;
  is_active: boolean;
  latest_risk_assessment_date?: string | null;
  latest_risk_assessment_sync_status?: SyncStatus | null;
  sync_status: SyncStatus;
  updated_at?: string | null;
};

export type RiskAssessmentFlags = Record<string, boolean>;

export type RiskAssessmentRecord = {
  local_id?: number;
  server_id?: number | null;
  mobile_uuid?: string | null;
  resident_server_id: number;
  recorded_by_user_id?: number | null;
  recorded_by_name?: string | null;
  assessment_date: string;
  age_years?: number | null;
  religion?: string | null;
  contact_number?: string | null;
  philhealth_number?: string | null;
  civil_status?: string | null;
  ethnicity?: string | null;
  pwd_id_number?: string | null;
  weight_kg?: number | null;
  height_cm?: number | null;
  body_mass_index?: number | null;
  waist_circumference_cm?: number | null;
  systolic_bp?: number | null;
  diastolic_bp?: number | null;
  employment_status?: string | null;
  ip_classification?: 'ip' | 'non_ip' | null;
  requires_immediate_referral: boolean;
  identity_snapshot?: Record<string, string | number | null> | null;
  red_flags: RiskAssessmentFlags;
  past_medical_history: RiskAssessmentFlags;
  family_history: RiskAssessmentFlags;
  tobacco_use?: string | null;
  alcohol_consumption_status?: string | null;
  alcohol_binge_flag?: boolean | null;
  physical_activity_met?: boolean | null;
  high_risk_diet_weekly?: boolean | null;
  blood_sugar_notes?: string | null;
  fbs_result?: string | null;
  rbs_result?: string | null;
  dm_symptoms: RiskAssessmentFlags;
  lipid_profile_date?: string | null;
  total_cholesterol?: string | null;
  hdl?: string | null;
  ldl?: string | null;
  vldl?: string | null;
  triglycerides?: string | null;
  urinalysis_protein?: string | null;
  urinalysis_ketones?: string | null;
  urinalysis_date?: string | null;
  chronic_respiratory_symptoms: RiskAssessmentFlags;
  lifestyle_modification?: boolean | null;
  anti_hypertensive_medications?: string | null;
  oral_hypoglycemic_medications?: string | null;
  follow_up_date?: string | null;
  remarks?: string | null;
  sync_status: SyncStatus;
  updated_at?: string | null;
};

export type VisitPhoto = {
  path?: string | null;
  file_name: string;
  mime_type: string;
  file_size_bytes?: number | null;
  captured_at?: string | null;
  uri?: string | null;
  base64?: string | null;
};

export type FieldVisitRecord = {
  local_id?: number;
  server_id?: number | null;
  mobile_uuid?: string | null;
  household_server_id?: number | null;
  household_mobile_uuid?: string | null;
  household_no?: string | null;
  household_purok_id?: number | null;
  household_purok_display_name?: string | null;
  recorded_by_name?: string | null;
  visited_at: string;
  notes?: string | null;
  photo_count?: number;
  photos: VisitPhoto[];
  sync_status: SyncStatus;
  updated_at?: string | null;
};

export type BootstrapPayload = {
  server_time: string;
  user: {
    id: number;
    name: string;
    email: string;
    role: 'bhw';
    approval_status: string;
    assignment_label: string;
    locale: string;
  };
  assignment: MobileAssignment;
  households: Array<{
    id: number;
    mobile_uuid: string | null;
    purok_id: number;
    purok_display_name: string | null;
    household_no: string;
    household_address: string;
    is_social_aid_beneficiary: boolean;
    is_active: boolean;
    resident_count: number;
    updated_at: string | null;
  }>;
  residents: Array<{
    id: number;
    mobile_uuid: string | null;
    household_id: number;
    household_mobile_uuid: string | null;
    philsys_card_no: string | null;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    suffix: string | null;
    birth_date: string;
    birth_place: string;
    sex: 'Male' | 'Female';
    civil_status: string;
    citizenship: string;
    religion: string | null;
    contact_number: string | null;
    email_address: string | null;
    relationship_to_head: string;
    is_active: boolean;
    updated_at: string | null;
  }>;
  field_visits: Array<{
    id: number;
    mobile_uuid: string;
    household_id: number;
    household_mobile_uuid: string | null;
    recorded_by_user_id: number | null;
    recorded_by_name: string | null;
    visited_at: string;
    notes: string | null;
    photo_count: number;
    photos: VisitPhoto[];
    updated_at: string | null;
  }>;
  risk_assessments: Array<{
    id: number;
    mobile_uuid: string;
    resident_id: number;
    recorded_by_user_id: number | null;
    recorded_by_name: string | null;
    assessment_date: string;
    age_years: number | null;
    religion: string | null;
    contact_number: string | null;
    philhealth_number: string | null;
    civil_status: string | null;
    ethnicity: string | null;
    pwd_id_number: string | null;
    weight_kg: number | null;
    height_cm: number | null;
    body_mass_index: number | null;
    waist_circumference_cm: number | null;
    systolic_bp: number | null;
    diastolic_bp: number | null;
    employment_status: string | null;
    ip_classification: 'ip' | 'non_ip' | null;
    requires_immediate_referral: boolean;
    identity_snapshot: Record<string, string | number | null> | null;
    red_flags: RiskAssessmentFlags | null;
    past_medical_history: RiskAssessmentFlags | null;
    family_history: RiskAssessmentFlags | null;
    tobacco_use: string | null;
    alcohol_consumption_status: string | null;
    alcohol_binge_flag: boolean | null;
    physical_activity_met: boolean | null;
    high_risk_diet_weekly: boolean | null;
    blood_sugar_notes: string | null;
    fbs_result: string | null;
    rbs_result: string | null;
    dm_symptoms: RiskAssessmentFlags | null;
    lipid_profile_date: string | null;
    total_cholesterol: string | null;
    hdl: string | null;
    ldl: string | null;
    vldl: string | null;
    triglycerides: string | null;
    urinalysis_protein: string | null;
    urinalysis_ketones: string | null;
    urinalysis_date: string | null;
    chronic_respiratory_symptoms: RiskAssessmentFlags | null;
    lifestyle_modification: boolean | null;
    anti_hypertensive_medications: string | null;
    oral_hypoglycemic_medications: string | null;
    follow_up_date: string | null;
    remarks: string | null;
    updated_at: string | null;
  }>;
  sync: {
    mode: string;
    requires_initial_download: boolean;
    supports_manual_upload: boolean;
    supports_auto_upload_when_online: boolean;
    supported_locales: string[];
  };
};

export type MobileReleasePayload = {
  id: number;
  version_name: string;
  version_code: number;
  release_title: string | null;
  release_notes: string | null;
  status: string;
  status_label: string;
  update_mode: 'optional' | 'required';
  update_mode_label: string;
  artifact_source: string;
  artifact_source_label: string;
  published_at: string | null;
  published_at_human: string | null;
  download_url: string;
  update_page_url: string;
};

export type MobileReleaseCheck = {
  scope: string;
  platform: string;
  checked_at: string;
  release: MobileReleasePayload | null;
  update: {
    available: boolean;
    required: boolean;
    can_continue_offline: boolean;
    message: string | null;
  };
  maintenance: {
    login_enabled: boolean;
    sync_upload_enabled: boolean;
    sync_download_enabled: boolean;
    maintenance_message: string | null;
  };
};

export type SyncResolvedRecord = {
  id: number;
  mobile_uuid: string | null;
  operation: 'created' | 'updated';
  household_id?: number;
  resident_id?: number;
  updated_at?: string | null;
};

export type SyncResponse = {
  success: boolean;
  status: 'success' | 'failed' | 'partial';
  records_synced: number;
  failed_records: Array<{
    collection: string;
    index: number;
    message: string;
  }>;
  resolved_records: {
    households: SyncResolvedRecord[];
    residents: SyncResolvedRecord[];
    field_visits: SyncResolvedRecord[];
    risk_assessments: SyncResolvedRecord[];
  };
  summary: Record<string, number>;
  duration_ms: number;
  synced_at: string;
};
