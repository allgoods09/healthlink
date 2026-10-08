import assert from 'node:assert/strict';
import { test } from 'node:test';
import { householdUiHarness } from './householdUiHarness.mjs';
import { createHomeHarness } from './homePresentationHarness.mjs';

const style = node => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));
const modalProps = overrides => ({ visible: true, title: 'Example filters', clearLabel: 'Clear example', applyLabel: 'Apply example',
  children: [], onClose() {}, onClear() {}, onApply() {}, ...overrides });

for (const locale of ['en', 'ceb']) for (const dark of [false, true]) {
  test(`${locale}/${dark ? 'dark' : 'light'}: filter trigger preserves exact geometry, icon and selected indicator`, async t => {
    const h = await householdUiHarness(t, 'Visits', { dark });
    const i18n = createHomeHarness({ locale }).load('i18n.ts').i18n;
    const { FilterIconButton } = h.load('components/filters/FilterIconButton.tsx');
    let presses = 0;
    for (const active of [false, true]) {
      const label = i18n.t(active ? 'hhVisitFilterActive' : 'hhVisitFilter');
      const tree = FilterIconButton({ active, accessibilityLabel: label, onPress: () => presses++ });
      assert.equal(tree.props.accessibilityRole, 'button');
      assert.equal(tree.props.accessibilityLabel, label);
      assert.deepEqual(tree.props.accessibilityState, { selected: active });
      assert.deepEqual(style(tree), { width: 50, height: 50, borderRadius: 6, borderWidth: 1,
        borderColor: active ? h.theme.colors.primary : h.theme.colors.border, backgroundColor: h.theme.colors.surface,
        alignItems: 'center', justifyContent: 'center' });
      const icon = h.nodes(tree).find(n => n.type === 'Ionicons');
      assert.equal(icon.props.name, 'filter-outline'); assert.equal(icon.props.size, 22);
      assert.equal(icon.props.accessible, false);
      assert.equal(icon.props.color, active ? h.theme.colors.primary : h.theme.colors.textMuted);
      const dot = h.nodes(tree).find(n => n.type === 'View');
      if (active) {
        assert.equal(dot.props.accessible, false);
        assert.deepEqual(style(dot), { position: 'absolute', top: 5, right: 5, width: 5, height: 5,
          borderRadius: 3, backgroundColor: h.theme.colors.primary });
      } else assert.equal(dot, undefined);
      tree.props.onPress();
    }
    assert.equal(presses, 2);
  });

  test(`${locale}/${dark ? 'dark' : 'light'}: generic modal composes non-date content and configurable actions without managing state`, async t => {
    const i18n = createHomeHarness({ locale }).load('i18n.ts').i18n;
    const h = await householdUiHarness(t, 'Visits', { dark, i18n });
    const { FilterModal } = h.load('components/filters/FilterModal.tsx');
    let changes = 0; let clears = 0; let applies = 0;
    const child = { type: 'TextInput', props: { accessibilityLabel: 'Illustrative age group', value: 'Adult',
      onChangeText: () => changes++, children: [] } };
    const props = modalProps({ title: 'An arbitrary long filter title', children: [child],
      clearLabel: i18n.t('clearFilters'), applyLabel: 'Use selection', error: 'Example validation',
      onClear: () => clears++, onApply: () => applies++ });
    const tree = FilterModal(props); const nodes = h.nodes(tree);
    assert.equal(tree.type, 'Modal'); assert.equal(tree.props.transparent, true);
    assert.equal(tree.props.visible, true); assert.equal(tree.props.animationType, 'none');
    const panel = nodes.find(n => n.props.accessibilityViewIsModal);
    assert.deepEqual(style(panel), { maxHeight: '100%', borderRadius: 8, padding: 16, gap: 12, backgroundColor: h.theme.colors.surface });
    const overlay = nodes.find(n => style(n).backgroundColor === h.theme.colors.overlay);
    assert.equal(style(overlay).paddingTop, 24); assert.equal(style(overlay).paddingBottom, 20);
    const scroll = nodes.find(n => n.type === 'ScrollView');
    assert.equal(scroll.props.keyboardShouldPersistTaps, 'handled');
    assert.deepEqual(scroll.props.contentContainerStyle, { gap: 8 });
    assert.ok(h.nodes(scroll).includes(child), 'Child is supplied unchanged, not recreated by a date-specific implementation');
    child.props.onChangeText('Older adult'); assert.equal(changes, 1);
    assert.equal(child.props.value, 'Adult', 'Consumer remains the state owner');
    assert.ok(h.texts(tree).includes(props.title));
    const title = nodes.find(n => n.props.accessibilityRole === 'header');
    assert.equal(style(title).fontSize, 17); assert.equal(style(title).flexShrink, 1);
    assert.equal(title.props.numberOfLines, undefined);
    const alert = nodes.find(n => n.props.accessibilityRole === 'alert');
    assert.equal(alert.props.accessibilityLiveRegion, 'polite');
    assert.deepEqual(style(alert), { color: h.theme.colors.danger, lineHeight: 22 });
    assert.ok(h.texts(alert).includes('Example validation'));
    const button = label => nodes.find(n => n.type === 'Pressable' && n.props.accessibilityLabel === label);
    assert.ok(button(i18n.t('close'))); assert.ok(button(i18n.t('cancel')));
    for (const label of [props.clearLabel, props.applyLabel]) {
      assert.equal(style(button(label)).minHeight, 48);
      assert.equal(style(button(label)).borderRadius, 6);
      assert.equal(style(button(label)).paddingHorizontal, 16);
    }
    assert.equal(style(button(props.clearLabel)).backgroundColor, h.theme.colors.surface);
    assert.equal(style(button(props.applyLabel)).backgroundColor, h.theme.colors.primary);
    assert.equal(style(button(props.applyLabel).props.children[0]).color, h.theme.colors.textOnPrimary);
    button(props.clearLabel).props.onPress(); button(props.applyLabel).props.onPress();
    assert.equal(clears, 1); assert.equal(applies, 1);
    assert.equal(child.props.value, 'Adult');
    assert.equal(h.nodes(FilterModal({ ...props, error: null })).some(n => n.props.accessibilityRole === 'alert'), false);
  });
}

for (const dismissal of ['close', 'back', 'backdrop', 'escape']) {
  test(`generic modal ${dismissal} invokes only the consumer cancel callback once`, async t => {
    const h = await householdUiHarness(t, 'Visits');
    const { FilterModal } = h.load('components/filters/FilterModal.tsx');
    let closes = 0;
    const tree = FilterModal(modalProps({ onClose: () => closes++,
      onApply: () => assert.fail('Cancel must not apply'), onClear: () => assert.fail('Cancel must not clear') }));
    if (dismissal === 'back') tree.props.onRequestClose();
    else if (dismissal === 'escape') h.nodes(tree).find(n => n.props.accessibilityViewIsModal).props.onAccessibilityEscape();
    else h.nodes(tree).find(n => n.type === 'Pressable' && n.props.accessibilityLabel === (dismissal === 'backdrop' ? 'cancel' : 'close')).props.onPress();
    assert.equal(closes, 1);
  });
}

test('hidden generic modal renders nothing and triggers no callbacks', async t => {
  const h = await householdUiHarness(t, 'Visits');
  const { FilterModal } = h.load('components/filters/FilterModal.tsx');
  const fail = () => assert.fail('Hidden modal must not trigger a callback');
  assert.equal(FilterModal(modalProps({ visible: false, onClose: fail, onClear: fail, onApply: fail })), null);
});
