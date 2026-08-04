import React, { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { useKeyboardAwareScroll } from '../hooks/useKeyboardAwareScroll';
import { i18n } from '../i18n';
import { AppTheme } from '../theme';

export function ForgotPasswordScreen({ navigation }: any) {
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const { requestPasswordReset, showToast } = useAppContext();
  const { handleInputFocus } = useKeyboardAwareScroll();
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);

    try {
      const message = await requestPasswordReset(email);
      showToast(message, 'success');
    } catch (nextError) {
      showToast(
        nextError instanceof Error ? nextError.message : 'Request failed.',
        'error'
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <KeyboardShiftView style={styles.screen}>
      <View style={styles.card}>
        <Text style={styles.title}>{i18n.t('forgotPasswordTitle')}</Text>
        <Text style={styles.subtitle}>{i18n.t('forgotPasswordSubtitle')}</Text>

        <Text style={styles.label}>{i18n.t('email')}</Text>
        <TextInput
          autoCapitalize="none"
          keyboardType="email-address"
          value={email}
          onChangeText={setEmail}
          onFocus={handleInputFocus}
          placeholder={i18n.t('email')}
          placeholderTextColor={theme.colors.placeholder}
          style={styles.input}
        />

        <Pressable
          onPress={handleSubmit}
          style={styles.primaryButton}
          disabled={submitting}
        >
          {submitting ? (
            <ActivityIndicator color={theme.colors.textOnPrimary} />
          ) : (
            <Text style={styles.primaryButtonText}>{i18n.t('sendResetLink')}</Text>
          )}
        </Pressable>

        <Pressable onPress={() => navigation.goBack()} style={styles.secondaryButton}>
          <Text style={styles.secondaryButtonText}>{i18n.t('backToLogin')}</Text>
        </Pressable>
      </View>
    </KeyboardShiftView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: {
    flex: 1,
    padding: theme.spacing.lg,
    justifyContent: 'center',
    backgroundColor: theme.colors.background,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.text,
  },
  subtitle: {
    marginTop: 10,
    marginBottom: 20,
    color: theme.colors.textMuted,
    lineHeight: 22,
  },
  label: {
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.inputBackground,
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  primaryButton: {
    marginTop: 18,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '700',
    fontSize: 16,
  },
  secondaryButton: {
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 8,
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
});
