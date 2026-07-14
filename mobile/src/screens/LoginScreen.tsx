import React, { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import { theme } from '../theme';

export function LoginScreen({ navigation }: any) {
  const { apiBaseUrl, signIn, statusMessage } = useAppContext();
  const [serverUrl, setServerUrl] = useState(apiBaseUrl);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);

    try {
      await signIn({
        email,
        password,
        apiBaseUrl: serverUrl,
      });
    } catch (nextError) {
      setError(nextError instanceof Error ? nextError.message : 'Sign in failed.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.flex}
    >
      <ScrollView contentContainerStyle={styles.scroll}>
        <View style={styles.hero}>
          <Text style={styles.kicker}>{i18n.t('appTitle')}</Text>
          <Text style={styles.title}>{i18n.t('loginTitle')}</Text>
          <Text style={styles.subtitle}>{i18n.t('loginSubtitle')}</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.label}>{i18n.t('apiBaseUrl')}</Text>
          <TextInput
            autoCapitalize="none"
            keyboardType="url"
            placeholder="http://192.168.x.x:8000"
            style={styles.input}
            value={serverUrl}
            onChangeText={setServerUrl}
          />

          <Text style={styles.help}>
            {i18n.t('changeServer')} Use your computer&apos;s LAN IP, not `localhost`.
          </Text>

          <Text style={styles.label}>{i18n.t('email')}</Text>
          <TextInput
            autoCapitalize="none"
            keyboardType="email-address"
            style={styles.input}
            value={email}
            onChangeText={setEmail}
          />

          <Text style={styles.label}>{i18n.t('password')}</Text>
          <TextInput
            secureTextEntry
            style={styles.input}
            value={password}
            onChangeText={setPassword}
          />

          {(error || statusMessage) && (
            <View style={styles.alert}>
              <Text style={styles.alertText}>{error ?? statusMessage}</Text>
            </View>
          )}

          <Pressable
            onPress={handleSubmit}
            style={[styles.primaryButton, submitting && styles.buttonDisabled]}
            disabled={submitting}
          >
            {submitting ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>{i18n.t('signIn')}</Text>
            )}
          </Pressable>

          <Pressable
            onPress={() =>
              navigation.navigate('ForgotPassword', { apiBaseUrl: serverUrl })
            }
            style={styles.secondaryButton}
          >
            <Text style={styles.secondaryButtonText}>{i18n.t('forgotPassword')}</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: theme.colors.background },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: theme.spacing.lg,
    gap: theme.spacing.lg,
  },
  hero: {
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.lg,
  },
  kicker: {
    color: '#D9FFFA',
    fontSize: 12,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    marginBottom: 12,
  },
  title: {
    color: '#fff',
    fontSize: 30,
    fontWeight: '700',
    lineHeight: 36,
  },
  subtitle: {
    marginTop: 12,
    color: '#D9FFFA',
    fontSize: 15,
    lineHeight: 22,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    padding: theme.spacing.lg,
    gap: theme.spacing.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  label: {
    color: theme.colors.text,
    fontWeight: '600',
    marginTop: 6,
  },
  help: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 6,
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
  alert: {
    backgroundColor: theme.colors.dangerSoft,
    borderRadius: theme.radius.md,
    padding: 14,
    marginTop: 8,
  },
  alertText: {
    color: theme.colors.danger,
    lineHeight: 20,
  },
  primaryButton: {
    backgroundColor: theme.colors.accent,
    borderRadius: theme.radius.md,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 10,
  },
  primaryButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  secondaryButton: {
    alignItems: 'center',
    paddingVertical: 10,
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  buttonDisabled: {
    opacity: 0.7,
  },
});
