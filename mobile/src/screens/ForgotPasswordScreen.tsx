import React, { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { theme } from '../theme';

export function ForgotPasswordScreen({ route, navigation }: any) {
  const { apiBaseUrl, requestPasswordReset } = useAppContext();
  const [serverUrl] = useState(route.params?.apiBaseUrl ?? apiBaseUrl);
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    setSuccess(null);

    try {
      const message = await requestPasswordReset({
        email,
        apiBaseUrl: serverUrl,
      });
      setSuccess(message);
    } catch (nextError) {
      setError(nextError instanceof Error ? nextError.message : 'Request failed.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.screen}>
      <View style={styles.card}>
        <Text style={styles.title}>{i18n.t('forgotPasswordTitle')}</Text>
        <Text style={styles.subtitle}>{i18n.t('forgotPasswordSubtitle')}</Text>

        <Text style={styles.label}>{i18n.t('email')}</Text>
        <TextInput
          autoCapitalize="none"
          keyboardType="email-address"
          value={email}
          onChangeText={setEmail}
          style={styles.input}
        />

        {error && (
          <View style={[styles.message, styles.errorMessage]}>
            <Text style={styles.errorText}>{error}</Text>
          </View>
        )}

        {success && (
          <View style={[styles.message, styles.successMessage]}>
            <Text style={styles.successText}>{success}</Text>
          </View>
        )}

        <Pressable
          onPress={handleSubmit}
          style={styles.primaryButton}
          disabled={submitting}
        >
          {submitting ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.primaryButtonText}>{i18n.t('sendResetLink')}</Text>
          )}
        </Pressable>

        <Pressable onPress={() => navigation.goBack()} style={styles.secondaryButton}>
          <Text style={styles.secondaryButtonText}>{i18n.t('backToLogin')}</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
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
    backgroundColor: '#FAFBFA',
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  message: {
    borderRadius: theme.radius.md,
    padding: 14,
    marginTop: 16,
  },
  errorMessage: {
    backgroundColor: theme.colors.dangerSoft,
  },
  successMessage: {
    backgroundColor: theme.colors.successSoft,
  },
  errorText: {
    color: theme.colors.danger,
  },
  successText: {
    color: theme.colors.success,
  },
  primaryButton: {
    marginTop: 18,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#fff',
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
