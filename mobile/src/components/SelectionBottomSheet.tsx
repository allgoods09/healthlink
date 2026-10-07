import React from 'react';
import { Ionicons } from '@expo/vector-icons';
import { FlatList, KeyboardAvoidingView, Modal, Platform, Pressable, StyleSheet, Text, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { AppTheme } from '../theme';

export type SelectionChoice = { value: string; label: string; description?: string };

export function SelectionBottomSheet({ title, visible, choices, selectedValue, onSelect, onClose, header, empty }: {
  title: string; visible: boolean; choices: SelectionChoice[]; selectedValue?: string | null;
  onSelect: (value: string) => void; onClose: () => void; header?: React.ReactNode; empty?: React.ReactElement;
}) {
  const theme = useAppTheme();
  const styles = useThemedStyles(selectionSheetStyles);
  const { height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  return <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
    <KeyboardAvoidingView style={styles.overlay} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <Pressable testID="selection-backdrop" style={StyleSheet.absoluteFill} accessibilityRole="button"
        accessibilityLabel={i18n.t('close')} onPress={onClose} />
      <View accessibilityViewIsModal style={[styles.sheet, { height: height * 0.72, maxHeight: '72%',
        paddingBottom: Math.max(insets.bottom, theme.spacing.md) }]}>
        <Text accessibilityRole="header" style={styles.title}>{title}</Text>
        {header}
        <FlatList style={styles.list} data={choices} keyExtractor={item => item.value} keyboardShouldPersistTaps="handled"
          ListEmptyComponent={empty} renderItem={({ item }) => {
            const selected = item.value === selectedValue;
            return <Pressable accessibilityRole="radio" accessibilityLabel={[item.label, item.description].filter(Boolean).join(', ')}
              accessibilityState={{ selected, checked: selected }} style={[styles.option, selected && styles.selected]}
              onPress={() => { onSelect(item.value); onClose(); }}>
              <View style={styles.optionCopy}><Text style={styles.label}>{item.label}</Text>
                {item.description ? <Text style={styles.description}>{item.description}</Text> : null}</View>
              {selected ? <Ionicons name="checkmark" size={22} color={theme.colors.primary} accessible={false} testID="selection-checkmark" /> : null}
            </Pressable>;
          }} />
        <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('cancel')} style={styles.cancel} onPress={onClose}>
          <Text style={styles.cancelText}>{i18n.t('cancel')}</Text>
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  </Modal>;
}

export const selectionSheetStyles = (theme: AppTheme) => StyleSheet.create({
  overlay: { flex: 1, justifyContent: 'flex-end', backgroundColor: theme.colors.overlay },
  sheet: { borderTopLeftRadius: theme.radius.lg, borderTopRightRadius: theme.radius.lg,
    backgroundColor: theme.colors.surface, paddingTop: theme.spacing.md, paddingHorizontal: theme.spacing.md, gap: theme.spacing.sm },
  title: { fontSize: 19, fontWeight: '700', color: theme.colors.text, flexShrink: 1 },
  list: { flex: 1, minHeight: 0 },
  option: { minHeight: 48, paddingVertical: theme.spacing.md, paddingHorizontal: theme.spacing.sm,
    flexDirection: 'row', alignItems: 'center', gap: theme.spacing.sm, borderBottomWidth: StyleSheet.hairlineWidth, borderColor: theme.colors.border },
  selected: { backgroundColor: theme.colors.primarySoft },
  optionCopy: { flex: 1, minWidth: 0 },
  label: { fontSize: 16, color: theme.colors.text, flexShrink: 1 },
  description: { fontSize: 14, color: theme.colors.textMuted, marginTop: theme.spacing.xs, flexShrink: 1 },
  cancel: { minHeight: 48, alignItems: 'center', justifyContent: 'center', padding: theme.spacing.sm },
  cancelText: { fontSize: 16, color: theme.colors.primary, fontWeight: '600' },
});
