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
  };
  summary: Record<string, number>;
  duration_ms: number;
  synced_at: string;
};
