import assert from 'node:assert/strict';
import test from 'node:test';

import { findHouseholdByReference } from '../src/lib/householdIdentity.ts';

const savedFirst = { local_id: 1, server_id: 101, mobile_uuid: null };
const savedSecond = { local_id: 2, server_id: 202, mobile_uuid: null };
const pendingFirst = { local_id: 3, server_id: null, mobile_uuid: 'mobile-first' };
const pendingSecond = { local_id: 4, server_id: null, mobile_uuid: 'mobile-second' };

test('a valid server ID selects its household', () => {
  assert.equal(
    findHouseholdByReference([savedFirst, savedSecond], { household_server_id: 202 }),
    savedSecond
  );
});

test('a valid mobile UUID selects its household', () => {
  assert.equal(
    findHouseholdByReference([pendingFirst, pendingSecond], {
      household_mobile_uuid: 'mobile-second',
    }),
    pendingSecond
  );
});

test('null server IDs do not identify unrelated households', () => {
  assert.equal(
    findHouseholdByReference([pendingFirst, pendingSecond], {
      household_server_id: null,
    }),
    null
  );
});

test('null or empty mobile UUIDs do not identify unrelated households', () => {
  for (const uuid of [null, '', '   ']) {
    assert.equal(
      findHouseholdByReference([savedFirst, savedSecond], {
        household_mobile_uuid: uuid,
      }),
      null
    );
  }
});

test('resident edit keeps its server-created household when UUIDs are null', () => {
  const resident = { household_server_id: 202, household_mobile_uuid: null };
  const selected = findHouseholdByReference([savedFirst, savedSecond], resident);

  assert.equal(selected, savedSecond);
  assert.equal(selected?.server_id, resident.household_server_id);
});

test('visit edit keeps its unsynced household when server IDs are null', () => {
  const visit = { household_server_id: null, household_mobile_uuid: 'mobile-second' };
  const selected = findHouseholdByReference([pendingFirst, pendingSecond], visit);

  assert.equal(selected, pendingSecond);
  assert.equal(selected?.mobile_uuid, visit.household_mobile_uuid);
});

test('two unsynced households cannot match only because server IDs are null', () => {
  assert.equal(
    findHouseholdByReference([pendingFirst, pendingSecond], {
      household_server_id: null,
      household_mobile_uuid: 'mobile-second',
    }),
    pendingSecond
  );
});

test('two server-created households cannot match only because UUIDs are null', () => {
  assert.equal(
    findHouseholdByReference([savedFirst, savedSecond], {
      household_server_id: 202,
      household_mobile_uuid: null,
    }),
    savedSecond
  );
});

test('a valid server ID takes precedence over a conflicting mobile UUID', () => {
  const first = { server_id: 101, mobile_uuid: 'mobile-first' };
  const second = { server_id: 202, mobile_uuid: 'mobile-second' };

  assert.equal(
    findHouseholdByReference([first, second], {
      household_server_id: 202,
      household_mobile_uuid: 'mobile-first',
    }),
    second
  );
});

test('a missing server ID does not fall through to a mobile UUID', () => {
  assert.equal(
    findHouseholdByReference([pendingFirst], {
      household_server_id: 202,
      household_mobile_uuid: 'mobile-first',
    }),
    null
  );
});
