import React from 'react';
import {
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';
import { AppTheme } from '../theme';
import { MobileConfirmationRequest } from '../types';

type Props = {
  confirmation: MobileConfirmationRequest | null;
  onResolve: (confirmed: boolean) => void;
};

export function ActionConfirmationModal({ confirmation, onResolve }: Props) {
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const tone = confirmation?.tone ?? 'primary';
  const toneColor = tone === 'danger'
    ? theme.colors.danger
    : tone === 'warning'
      ? theme.colors.warning
      : theme.colors.primary;

  return (
    <Modal
      transparent
      animationType="fade"
      visible={Boolean(confirmation)}
      onRequestClose={() => onResolve(false)}
      statusBarTranslucent
    >
      <View style={styles.overlay} accessibilityViewIsModal>
        <View style={styles.card} accessibilityRole="alert">
          <Text style={[styles.eyebrow, { color: toneColor }]}>
            {i18n.t('confirmationRequired')}
          </Text>
          <Text style={styles.title}>{confirmation?.title}</Text>
          <Text style={styles.message}>{confirmation?.message}</Text>

          <View style={styles.actions}>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={i18n.t('cancel')}
              onPress={() => onResolve(false)}
              style={styles.cancelButton}
            >
              <Text style={styles.cancelButtonText}>{i18n.t('cancel')}</Text>
            </Pressable>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={confirmation?.confirmLabel}
              onPress={() => onResolve(true)}
              style={[styles.confirmButton, { backgroundColor: toneColor }]}
            >
              <Text style={styles.confirmButtonText}>{confirmation?.confirmLabel}</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'center',
    padding: theme.spacing.lg,
    backgroundColor: theme.colors.overlay,
  },
  card: {
    backgroundColor: theme.colors.surfaceElevated,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
    padding: theme.spacing.lg,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 0.28,
    shadowRadius: 22,
    shadowOffset: { width: 0, height: 12 },
    elevation: 10,
  },
  eyebrow: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1.1,
    textTransform: 'uppercase',
  },
  title: {
    color: theme.colors.text,
    fontSize: 22,
    fontWeight: '800',
    marginTop: 10,
  },
  message: {
    color: theme.colors.textMuted,
    fontSize: 15,
    lineHeight: 23,
    marginTop: 10,
  },
  actions: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    justifyContent: 'flex-end',
    marginTop: theme.spacing.lg,
  },
  cancelButton: {
    alignItems: 'center',
    borderColor: theme.colors.borderStrong,
    borderRadius: theme.radius.md,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 48,
    paddingHorizontal: theme.spacing.md,
  },
  cancelButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  confirmButton: {
    alignItems: 'center',
    borderRadius: theme.radius.md,
    flex: 1,
    justifyContent: 'center',
    minHeight: 48,
    paddingHorizontal: theme.spacing.md,
  },
  confirmButtonText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '800',
  },
});
