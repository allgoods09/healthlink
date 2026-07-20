import React, { useState } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import {
  ActivityIndicator,
  ImageBackground,
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
import { authBackgroundImage, BrandMark } from '../components/BrandMark';

export function LoginScreen({ navigation }: any) {
  const { apiBaseUrl, signIn, statusMessage } = useAppContext();
  const [serverUrl, setServerUrl] = useState(apiBaseUrl);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showServerSettings, setShowServerSettings] = useState(false);
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
    <ImageBackground
      source={authBackgroundImage}
      style={styles.background}
      imageStyle={styles.backgroundImage}
    >
      <StatusBar style="light" />
      <View style={styles.overlay} />

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.flex}
      >
        <ScrollView
          contentContainerStyle={styles.scroll}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.hero}>
            <BrandMark logoSize={118} titleSize={34} subtitleSize={28} />
          </View>

          <View style={styles.formSection}>
            <View style={styles.inputShell}>
              <Ionicons
                name="person-outline"
                size={28}
                color={theme.colors.primary}
                style={styles.leftIcon}
              />
              <TextInput
                autoCapitalize="none"
                keyboardType="email-address"
                placeholder={i18n.t('email')}
                placeholderTextColor="rgba(13, 66, 129, 0.45)"
                style={styles.input}
                value={email}
                onChangeText={setEmail}
              />
            </View>

            <View style={styles.inputShell}>
              <Ionicons
                name="lock-closed-outline"
                size={26}
                color={theme.colors.primary}
                style={styles.leftIcon}
              />
              <TextInput
                secureTextEntry={!showPassword}
                placeholder={i18n.t('password')}
                placeholderTextColor="rgba(13, 66, 129, 0.45)"
                style={styles.input}
                value={password}
                onChangeText={setPassword}
              />
              <Pressable
                onPress={() => setShowPassword((current) => !current)}
                accessibilityRole="button"
                accessibilityLabel={
                  showPassword ? i18n.t('hidePassword') : i18n.t('showPassword')
                }
                style={styles.eyeButton}
              >
                <Ionicons
                  name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                  size={28}
                  color="rgba(13, 66, 129, 0.75)"
                />
              </Pressable>
            </View>

            <Pressable
              onPress={() =>
                navigation.navigate('ForgotPassword', { apiBaseUrl: serverUrl })
              }
              style={styles.forgotButton}
            >
              <Text style={styles.forgotButtonText}>{i18n.t('forgotPassword')}</Text>
            </Pressable>

            {(error || statusMessage) && (
              <View style={[styles.alert, error ? styles.alertDanger : styles.alertInfo]}>
                <Text style={styles.alertText}>{error ?? statusMessage}</Text>
              </View>
            )}

            <Pressable
              onPress={handleSubmit}
              style={[styles.primaryButton, submitting && styles.buttonDisabled]}
              disabled={submitting}
            >
              {submitting ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <Text style={styles.primaryButtonText}>{i18n.t('signIn')}</Text>
              )}
            </Pressable>

            <Pressable
              onPress={() => setShowServerSettings((current) => !current)}
              style={styles.serverToggle}
            >
              <Text style={styles.serverToggleText}>
                {showServerSettings
                  ? i18n.t('hideServerSettings')
                  : i18n.t('serverSettings')}
              </Text>
              <Ionicons
                name={showServerSettings ? 'chevron-up-outline' : 'chevron-down-outline'}
                size={18}
                color="rgba(255, 255, 255, 0.86)"
              />
            </Pressable>

            {showServerSettings ? (
              <View style={styles.serverPanel}>
                <Text style={styles.serverLabel}>{i18n.t('apiBaseUrl')}</Text>
                <TextInput
                  autoCapitalize="none"
                  keyboardType="url"
                  placeholder="http://192.168.x.x:8000"
                  placeholderTextColor="rgba(13, 66, 129, 0.45)"
                  style={styles.serverInput}
                  value={serverUrl}
                  onChangeText={setServerUrl}
                />
                <Text style={styles.serverHelp}>
                  {i18n.t('changeServer')} {i18n.t('serverUrlHelp')}
                </Text>
              </View>
            ) : null}
          </View>

          <View style={styles.notes}>
            <Text style={styles.notePrimary}>{i18n.t('loginSubtitle')}</Text>
            <Text style={styles.noteSecondary}>{i18n.t('loginSecondaryNote')}</Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  background: {
    flex: 1,
    backgroundColor: theme.colors.primary,
  },
  backgroundImage: {
    resizeMode: 'cover',
  },
  overlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(11, 84, 165, 0.58)',
  },
  flex: { flex: 1 },
  scroll: {
    flexGrow: 1,
    justifyContent: 'space-between',
    paddingHorizontal: theme.spacing.lg,
    paddingTop: 56,
    paddingBottom: 34,
  },
  hero: {
    alignItems: 'center',
    marginBottom: theme.spacing.xl,
  },
  formSection: {
    gap: 14,
  },
  inputShell: {
    minHeight: 74,
    borderRadius: 16,
    borderWidth: 1.2,
    borderColor: 'rgba(21, 72, 138, 0.46)',
    backgroundColor: 'rgba(240, 247, 255, 0.88)',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    shadowColor: '#0A366A',
    shadowOpacity: 0.16,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 8 },
    elevation: 4,
  },
  leftIcon: {
    marginRight: 12,
  },
  input: {
    flex: 1,
    color: theme.colors.primaryDark,
    fontSize: 16,
    paddingVertical: 18,
  },
  eyeButton: {
    paddingLeft: 10,
  },
  forgotButton: {
    alignSelf: 'flex-end',
    marginTop: -2,
  },
  forgotButtonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '500',
    textDecorationLine: 'underline',
  },
  alert: {
    borderRadius: 16,
    padding: 14,
    borderWidth: 1,
  },
  alertText: {
    color: '#FFFFFF',
    lineHeight: 20,
    textAlign: 'center',
  },
  alertDanger: {
    backgroundColor: 'rgba(157, 25, 25, 0.38)',
    borderColor: 'rgba(255, 235, 235, 0.34)',
  },
  alertInfo: {
    backgroundColor: 'rgba(8, 46, 89, 0.36)',
    borderColor: 'rgba(255, 255, 255, 0.28)',
  },
  primaryButton: {
    backgroundColor: '#0E5FB8',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(5, 46, 98, 0.55)',
    minHeight: 76,
    paddingVertical: 15,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#072B56',
    shadowOpacity: 0.2,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 8 },
    elevation: 4,
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '700',
  },
  serverToggle: {
    marginTop: 2,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  serverToggleText: {
    color: 'rgba(255, 255, 255, 0.9)',
    fontSize: 14,
    fontWeight: '600',
  },
  serverPanel: {
    marginTop: 2,
    borderRadius: 18,
    backgroundColor: 'rgba(240, 247, 255, 0.86)',
    borderWidth: 1,
    borderColor: 'rgba(19, 70, 137, 0.24)',
    padding: 16,
  },
  serverLabel: {
    color: theme.colors.primaryDark,
    fontWeight: '700',
    marginBottom: 10,
  },
  serverInput: {
    minHeight: 56,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: 'rgba(19, 70, 137, 0.18)',
    backgroundColor: 'rgba(255, 255, 255, 0.92)',
    paddingHorizontal: 14,
    color: theme.colors.primaryDark,
  },
  serverHelp: {
    color: 'rgba(18, 38, 58, 0.78)',
    marginTop: 10,
    lineHeight: 20,
    fontSize: 13,
  },
  notes: {
    marginTop: theme.spacing.xl,
    paddingHorizontal: 4,
  },
  notePrimary: {
    color: '#FFFFFF',
    textAlign: 'center',
    fontSize: 14,
    lineHeight: 24,
    fontWeight: '500',
  },
  noteSecondary: {
    color: 'rgba(255, 255, 255, 0.94)',
    textAlign: 'center',
    fontSize: 14,
    lineHeight: 24,
    marginTop: 18,
  },
  buttonDisabled: {
    opacity: 0.7,
  },
});
