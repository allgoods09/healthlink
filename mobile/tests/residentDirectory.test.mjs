import assert from 'node:assert/strict';
import { test } from 'node:test';
import { data, fixture, home, person } from './residentDirectoryFixture.mjs';
const ids = page => page.rows.map(r => r.server_id);

test('official directory/count/current household count exclude requests and foreign scope but include vacant and availability-false', async t => {
  const payload = data(); payload.households[3].is_vacant = true;
  payload.households.push({ ...home(null, 'Request'), mobile_uuid: '00000000-0000-4000-8000-000000000011', verification_status: 'submitted' });
  const { storage } = await fixture(t, payload);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 5);
  assert.equal(await storage.getCurrentOfficialHouseholdCount(), 4);
  assert.deepEqual((await storage.getCurrentOfficialHouseholds()).map(h => h.household_no), ['2', '10', 'A2', 'A10']);
  assert.equal((await storage.getResidentRequests()).length, 1);
});

test('search trims/collapses tokens across all name parts/household, folds case and treats SQL wildcards literally', async t => {
  const { storage } = await fixture(t);
  const search = text => storage.getCurrentOfficialResidentsPage({ search: text });
  assert.deepEqual(ids(await search('  JUAN   PEÑA  YBAÑEZ Jr.  ')), [1]);
  assert.deepEqual(ids(await search('Juan A10')), [1]);
  assert.deepEqual(ids(await search('%_')), [10]);
  assert.deepEqual(ids(await search("' OR 1=1 --")), []);
  assert.equal((await search('   ')).total, 5);
});

test('sex, birthday-aware general ages and household filters compose inside one scoped paged query', async t => {
  const { storage } = await fixture(t);
  const page = c => storage.getCurrentOfficialResidentsPage({ ...c, asOf: '2026-10-06' });
  assert.deepEqual(ids(await page({ sex: 'Female' })), [2]);
  assert.deepEqual(ids(await page({ ageGroup: '0-5' })), [4]);
  assert.deepEqual(ids(await page({ ageGroup: '6-12' })), [3]);
  const households = await storage.getCurrentOfficialHouseholds();
  const householdLocalId = households.find(h => h.server_id === 1).local_id;
  assert.deepEqual(ids(await page({ search: 'Juan', sex: 'Male', ageGroup: '60+', householdLocalId })), [1]);
  assert.deepEqual(ids(await page({ sex: 'Female', ageGroup: '60+' })), []);
  assert.equal((await page({ householdLocalId: households.find(h => h.server_id === 2).local_id })).total, 1);
});

test('all five sorts are deterministic and household order reuses natural mixed-number ordering', async t => {
  const { storage } = await fixture(t);
  const page = sort => storage.getCurrentOfficialResidentsPage({ sort, asOf: '2026-10-06' });
  assert.deepEqual(ids(await page('nameAsc')), [3, 4, 2, 1, 10]);
  assert.deepEqual(ids(await page('nameDesc')), [10, 1, 2, 4, 3]);
  assert.deepEqual(ids(await page('youngest')), [4, 3, 2, 1, 10]);
  assert.deepEqual(ids(await page('oldest')), [1, 10, 2, 3, 4]);
  assert.deepEqual(ids(await page('household')), [4, 3, 2, 1, 10]);
});

test('invalid/future DOB have unknown age and sort last; leap birthday uses local calendar semantics', async t => {
  const payload = data(); payload.residents = [person(1, { birth_date: '2000-02-29' }),
    person(2, { birth_date: '2026-02-30' }), person(3, { birth_date: '2099-01-01' })];
  const { storage } = await fixture(t, payload);
  assert.deepEqual(ids(await storage.getCurrentOfficialResidentsPage({ asOf: '2026-02-28', ageGroup: '18-59' })), [1]);
  assert.deepEqual(ids(await storage.getCurrentOfficialResidentsPage({ asOf: '2026-02-28', sort: 'youngest' })), [1, 2, 3]);
});

test('composed paging has no duplicates and changing each criterion or assignment invalidates continuation', async t => {
  const payload = data(); payload.residents = Array.from({ length: 130 }, (_, i) => person(i + 1));
  const { storage } = await fixture(t, payload);
  const criteria = { sex: 'Male', search: 'Juan Peña', ageGroup: '60+', sort: 'household', asOf: '2026-10-06' };
  let page = await storage.getCurrentOfficialResidentsPage(criteria); const cursor = page.next;
  const found = ids(page);
  while (page.next) { page = await storage.getCurrentOfficialResidentsPage(criteria, page.next); found.push(...ids(page)); }
  assert.equal(found.length, 130); assert.equal(new Set(found).size, 130);
  for (const change of [{ search: 'Ana' }, { sex: 'Female' }, { ageGroup: '18-59' }, { sort: 'oldest' }, { householdLocalId: 9 }]) {
    assert.equal((await storage.getCurrentOfficialResidentsPage({ ...criteria, ...change }, cursor)).invalidated, true);
  }
  await storage.verifyDatasetAssignment(1, 1, 2);
  assert.equal((await storage.getCurrentOfficialResidentsPage(criteria, cursor)).invalidated, true);
  assert.equal(await storage.getCurrentOfficialHouseholdCount(), 0);
  assert.deepEqual(await storage.getResidentWorkflowItems(), []);
});

test('workflow items keep latest corrections separate while official residents stay current; badge excludes terminal outcomes', async t => {
  const payload = data(); payload.residents[0].verification_status = 'submitted';
  payload.residents[1].verification_status = 'rejected'; payload.residents[1].verification_notes = 'Keep current name';
  payload.residents[2].verification_status = 'approved'; payload.residents[2].verification_notes = 'Updated';
  const h = await fixture(t, payload);
  const request = await h.storage.getResidentWorkflowItems();
  assert.equal(request.length, 4);
  assert.equal(request.filter(h.presentation.isOpenResidentWorkflowItem).length, 1);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 5);
  assert.equal(h.presentation.residentStatusKey(request.find(r => r.server_id === 2)), 'notApprovedResident');
  assert.equal(h.presentation.residentStatusKey(request.find(r => r.server_id === 3)), 'approvedResident');
  const rejected = request.find(r => r.server_id == null);
  assert.equal(h.workflow.residentEditBlocked(rejected), true);
});

test('every age-group boundary and birthday uses DOB only, including adolescents and unknown age', async t => {
  const payload = data();
  payload.residents = ['2026-10-06', '2020-10-07', '2020-10-06', '2013-10-07', '2013-10-06',
    '2008-10-07', '2008-10-06', '1966-10-07', '1966-10-06', 'invalid'].map((birth_date, i) => person(i + 1, { birth_date }));
  const { storage } = await fixture(t, payload);
  for (const [ageGroup, expected] of [['0-5', [1, 2]], ['6-12', [3, 4]], ['13-17', [5, 6]], ['18-59', [7, 8]], ['60+', [9]]]) {
    assert.deepEqual(ids(await storage.getCurrentOfficialResidentsPage({ ageGroup, asOf: '2026-10-06' })), expected);
  }
});

test('display status distinguishes upload/submission/approval/rejection without growing badge for history', async t => {
  const { presentation } = await fixture(t);
  for (const [sync_status, verification_status, key, open] of [['pending_create', 'pending', 'awaitingResidentUpload', true],
    ['synced', 'submitted', 'submittedResident', true], ['synced', 'approved', 'approvedResident', false],
    ['synced', 'rejected', 'notApprovedResident', false]]) {
    const record = { sync_status, verification_status };
    assert.equal(presentation.residentStatusKey(record), key);
    assert.equal(presentation.isOpenResidentWorkflowItem(record), open);
  }
  assert.equal(presentation.residentDirectoryAge('2026-02-30'), null);
  assert.equal(presentation.residentDirectoryAge(null), null);
});
