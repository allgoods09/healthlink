// Explicit server-contract fixture builder. Legacy-cache tests omit this helper.
export function withHouseholdContract(payload) {
  const { barangay, purok } = payload.assignment;
  return { ...payload, household_contract_version: 1, households: payload.households.map(row => {
    const official = row.id != null;
    const own = row.purok_id === purok.id;
    const social = row.is_social_aid_beneficiary ?? false;
    return { ...row, barangay_id: barangay.id, submitted_by_user_id: official ? null : payload.user.id,
      access_mode: official ? own ? 'operational' : 'lookup' : 'request',
      member_coverage: official ? own ? 'complete' : 'undisclosed' : 'unverified',
      ...(official && own ? { base_snapshot: { purok_id: row.purok_id, household_no: row.household_no,
        household_address: row.household_address, is_social_aid_beneficiary: social } } : {}) };
  }) };
}
