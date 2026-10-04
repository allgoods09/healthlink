type HouseholdIdentity = {
  server_id?: number | null;
  mobile_uuid?: string | null;
};

type HouseholdReference = {
  household_server_id?: number | null;
  household_mobile_uuid?: string | null;
};

export function findHouseholdByReference<T extends HouseholdIdentity>(
  households: readonly T[],
  reference: HouseholdReference
): T | null {
  const serverId = reference.household_server_id;

  if (typeof serverId === 'number' && Number.isSafeInteger(serverId) && serverId > 0) {
    return households.find((household) => household.server_id === serverId) ?? null;
  }

  const mobileUuid = reference.household_mobile_uuid;

  if (typeof mobileUuid === 'string' && mobileUuid.trim() !== '') {
    return households.find((household) => household.mobile_uuid === mobileUuid) ?? null;
  }

  return null;
}
