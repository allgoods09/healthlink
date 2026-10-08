import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createHomeHarness, nodes, textContent } from './homePresentationHarness.mjs';
import { appContextHarness } from './appContextHarness.mjs';

const tick = () => new Promise(setImmediate);
const style = node => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));
const notification = overrides => ({ id: 'notice-1', title: 'Actual record title', body: 'Actual complete message',
  sender_name: 'Original sender', created_at: '2026-10-08T06:30:00Z', level: 'info', read_at: null,
  action_url: 'https://example.test/record/1', action_label: null, ...overrides });
function fixture(options = {}) {
  const events = [];
  const h = createHomeHarness({ ...options, linking: options.linking ?? { openURL: async url => events.push(['open', url]) },
    context: { notifications: [notification()], unreadNotificationCount: 1, isOnline: true,
      markNotificationRead: async id => events.push(['read', id]),
      markAllNotificationsRead: async () => events.push(['all']),
      refreshNotifications: async () => events.push(['refresh']),
      showToast: (...args) => events.push(['toast', ...args]), ...options.context } });
  const tree = h.render('Notifications');
  const i18n = h.load('i18n.ts').i18n;
  const button = (label, root = tree) => nodes(root).find(n => n.type === 'Pressable' && n.props.accessibilityLabel === label);
  const cards = nodes(tree).filter(n => n.type === 'View' && style(n).padding === 14 && style(n).borderRadius === 8);
  return { ...h, tree, i18n, button, cards, events, theme: h.load('theme.ts')[`${options.mode ?? 'light'}Theme`] };
}

for (const locale of ['en', 'ceb']) for (const mode of ['light', 'dark']) {
  test(`${locale}/${mode}: ChildHeader, compact hierarchy, real long content and accessible wrapping actions`, async () => {
    const record = notification({ title: 'Long actual title '.repeat(20), body: 'Entire long actual body '.repeat(40),
      sender_name: 'Long actual sender '.repeat(20), action_label: 'Long actual action '.repeat(12) });
    const h = fixture({ locale, mode, context: { notifications: [record] } });
    const all = nodes(h.tree); const content = textContent(h.tree); const card = h.cards[0];
    const titles = all.filter(n => n.type === 'Text' && textContent(n) === h.i18n.t('notifications'));
    assert.equal(titles.length, 1); assert.equal(style(titles[0]).textAlign, 'center');
    assert.equal(style(titles[0]).fontWeight, '400'); assert.equal(style(titles[0]).minWidth, 0);
    assert.equal(all.some(n => n.type === 'TopHeader'), false);
    assert.ok(all.some(n => style(n).paddingTop === 24));
    assert.equal(style(h.button(h.i18n.t('back'))).width, 48);
    h.button(h.i18n.t('back')).props.onPress(); assert.equal(h.calls.back, 1);
    assert.deepEqual(h.calls.navigation, []); assert.equal(h.calls.logout, 0);
    assert.ok(!content.includes(h.i18n.t('notificationCenter')));
    assert.ok(!content.includes(h.i18n.t('notificationsBody')));
    for (const value of [record.title, record.body, record.sender_name]) assert.ok(content.includes(value));
    const sender = all.find(n => n.type === 'Text' && n.props.children?.includes(record.sender_name));
    assert.deepEqual(Array.from(sender.props.children), [h.i18n.t('fromLabel'), ': ', record.sender_name]);
    assert.ok(content.includes(h.load('lib/format.ts').formatFriendlyDateTime(record.created_at)));
    assert.ok(content.includes(h.i18n.t('unreadNotificationsLabel', { count: 1 })));
    assert.ok(content.includes(h.i18n.t('newLabel')));
    assert.equal(h.i18n.t('recentNotifications'), locale === 'en' ? 'Recent notifications' : 'Bag-ong mga pahibalo');
    assert.equal(h.i18n.t('refreshNotifications'), locale === 'en' ? 'Refresh' : 'I-refresh');
    const scroll = all.find(n => n.type === 'ScrollView'); assert.equal(scroll.props.contentContainerStyle.padding, 16);
    assert.equal(style(card).backgroundColor, h.theme.colors.surface);
    assert.equal(style(card).borderColor, h.theme.colors.infoBorder); assert.equal(style(card).gap, 6);
    assert.equal(card.props.onPress, undefined, 'Whole card remains non-clickable');
    assert.equal(style(card.props.children[0]).flexWrap, 'wrap');
    assert.equal(style(card.props.children.at(-1)).flexWrap, 'wrap');
    for (const n of all) {
      assert.equal(n.props.numberOfLines, undefined); assert.equal(n.props.allowFontScaling, undefined);
      assert.equal(style(n).shadowOpacity, undefined); assert.equal(style(n).elevation, undefined);
      if (n.type === 'Text') assert.equal(style(n).height, undefined);
    }
    for (const label of [h.i18n.t('markAllRead'), h.i18n.t('refreshNotifications'), h.i18n.t('markRead'), record.action_label]) {
      const control = h.button(label); assert.ok(control); assert.equal(control.props.accessibilityRole, 'button');
      assert.equal(style(control).minHeight, 48); assert.equal(style(control).maxWidth, '100%');
      assert.equal(style(control).backgroundColor, undefined);
    }
    assert.deepEqual(h.events, [], 'Rendering must not fetch or acknowledge notifications');
  });
}

for (const count of [0, 3, 120]) test(`unread count ${count}: accurate summary and conditional Mark All callback`, async () => {
  const h = fixture({ context: { unreadNotificationCount: count } });
  assert.ok(textContent(h.tree).includes(h.i18n.t('unreadNotificationsLabel', { count })));
  const all = h.button(h.i18n.t('markAllRead')); assert.equal(Boolean(all), count > 0);
  if (all) { all.props.onPress(); await tick(); assert.deepEqual(h.events, [['all']]); }
  h.button(h.i18n.t('refreshNotifications')).props.onPress(); await tick();
  assert.deepEqual(h.events.at(-1), ['refresh']);
});

test('all four severity values remain intact without heading indicators; read distinction/actions remain', () => {
  const data = ['info', 'success', 'warning', 'error'].map((level, i) => notification({ id: String(i), level,
    read_at: i % 2 ? '2026-10-08' : null, action_url: null, sender_name: null }));
  const h = fixture({ context: { notifications: data } });
  assert.equal(h.context.notifications, data);
  assert.deepEqual(h.context.notifications.map(record => record.level), ['info', 'success', 'warning', 'error']);
  h.cards.forEach((card, i) => {
    const heading = card.props.children[0];
    const titleWrap = heading.props.children[0];
    assert.equal(titleWrap.props.children.type, 'Text', 'Title is the only child; no replacement indicator');
    assert.equal(textContent(titleWrap), data[i].title);
    assert.equal(style(titleWrap).gap, undefined);
    assert.equal(style(titleWrap).minWidth, 0);
    assert.deepEqual(style(titleWrap.props.children), { color: h.theme.colors.text, fontSize: 16,
      lineHeight: 24, fontWeight: '600', flex: 1 });
    assert.equal(style(card).borderColor, i % 2 ? h.theme.colors.border : h.theme.colors.infoBorder);
    assert.equal(Boolean(h.button(h.i18n.t('markRead'), card)), i % 2 === 0);
    assert.equal(textContent(card).includes(h.i18n.t('newLabel')), i % 2 === 0);
    assert.equal(textContent(card).includes(h.i18n.t('fromLabel') + ':'), false);
    assert.equal(h.button(h.i18n.t('openAction'), card), undefined);
  });
});

test('individual Mark Read supplies exact id and does not open, refresh or mark all', async () => {
  const h = fixture(); h.button(h.i18n.t('markRead')).props.onPress(); await tick();
  assert.deepEqual(h.events, [['read', 'notice-1']]);
});

for (const label of [null, '', 'Review the original request']) test(`Open keeps supplied action label ${JSON.stringify(label)} or null fallback`, async () => {
  const h = fixture({ context: { notifications: [notification({ action_label: label, read_at: '2026-10-07' })] } });
  const action = h.button(label ?? h.i18n.t('openAction')); assert.ok(action);
  assert.ok(textContent(action).includes('>'));
  action.props.onPress(); await tick();
  assert.deepEqual(h.events, [['read', 'notice-1'], ['open', 'https://example.test/record/1']]);
});

test('Open waits for the existing mark-read callback before opening the actual URL', async () => {
  let finish; const h = fixture({ context: { markNotificationRead: id => {
    h.events.push(['read', id]); return new Promise(resolve => { finish = resolve; });
  } } });
  h.button(h.i18n.t('openAction')).props.onPress();
  assert.deepEqual(h.events, [['read', 'notice-1']]); finish(); await tick();
  assert.deepEqual(h.events, [['read', 'notice-1'], ['open', 'https://example.test/record/1']]);
});

for (const locale of ['en', 'ceb']) test(`${locale}: offline Open still marks read first, then warns without opening`, async () => {
  const h = fixture({ locale, context: { isOnline: false } });
  h.button(h.i18n.t('openAction')).props.onPress(); await tick();
  assert.deepEqual(h.events, [['read', 'notice-1'], ['toast', h.i18n.t('notificationOpenOnlineOnly'), 'warning']]);
});

for (const error of [new Error('Original platform failure'), 'not an Error']) test(`Open failure preserves ${typeof error === 'string' ? 'localized fallback' : 'original Error message'}`, async () => {
  const h = fixture({ linking: { openURL: async () => { throw error; } } });
  h.button(h.i18n.t('openAction')).props.onPress(); await tick();
  assert.deepEqual(h.events, [['read', 'notice-1'], ['toast', error instanceof Error ? error.message : h.i18n.t('notificationOpenFailed'), 'error']]);
});

for (const locale of ['en', 'ceb']) for (const isOnline of [true, false]) test(`${locale}/online=${isOnline}: compact truthful empty state keeps explicit Refresh`, async () => {
  const h = fixture({ locale, context: { isOnline, notifications: [], unreadNotificationCount: 0 } });
  for (const key of ['noNotifications', 'noNotificationsBody']) assert.ok(textContent(h.tree).includes(h.i18n.t(key)));
  assert.equal(style(h.cards[0]).padding, 14); assert.equal(style(h.cards[0]).height, undefined);
  assert.equal(h.button(h.i18n.t('markAllRead')), undefined);
  assert.equal(h.button(h.i18n.t('markRead')), undefined);
  h.button(h.i18n.t('refreshNotifications')).props.onPress(); await tick(); assert.deepEqual(h.events, [['refresh']]);
});

for (const created_at of ['malformed original timestamp', null]) test(`timestamp ${created_at} retains existing fallback`, () => {
  const h = fixture({ context: { notifications: [notification({ created_at })] } });
  assert.ok(textContent(h.cards[0]).includes(created_at ?? 'N/A'));
});

test('AppContext notification refresh/read/all preserve server unread counts and existing acknowledgments', async t => {
  const record = notification();
  const h = await appContextHarness({ mobileNotifications: async () => ({ notifications: [record], unread_count: 3 }),
    mobileReadNotification: async (_url, _token, id) => ({ notification: { ...record, id, read_at: '2026-10-08' }, unread_count: 2 }),
    mobileReadAllNotifications: async () => ({ success: true }) });
  t.after(h.close);
  await h.render().signIn({ email: '1', password: 'test-only' });
  assert.equal(h.render().unreadNotificationCount, 3);
  const before = h.calls.length; await h.render().refreshNotifications();
  assert.deepEqual(h.calls.slice(before).map(c => c.name), ['mobileNotifications']);
  await h.render().markNotificationRead(record.id);
  assert.equal(h.render().unreadNotificationCount, 2); assert.equal(h.render().notifications[0].read_at, '2026-10-08');
  assert.equal(h.calls.find(c => c.name === 'mobileReadNotification').args[2], record.id);
  await h.render().markAllNotificationsRead(); assert.equal(h.render().unreadNotificationCount, 0);
  assert.equal(h.render().notifications[0].read_at, '2026-10-08');
});

test('AppContext failed refresh retains saved notifications; failed read/all retain counts', async t => {
  let fail = false; const record = notification();
  const h = await appContextHarness({ mobileNotifications: async () => {
    if (fail) throw new Error('Transport failure'); return { notifications: [record], unread_count: 1 };
  }, mobileReadNotification: async () => { throw new Error('Read failure'); },
  mobileReadAllNotifications: async () => { throw new Error('Read-all failure'); } });
  t.after(h.close); await h.render().signIn({ email: '1', password: 'test-only' }); fail = true;
  await h.render().refreshNotifications(); await h.render().markNotificationRead(record.id); await h.render().markAllNotificationsRead();
  assert.equal(h.render().unreadNotificationCount, 1); assert.equal(h.render().notifications[0].read_at, null);
});
