import React, { useState } from "react";
import { Ionicons } from "@expo/vector-icons";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
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
} from "react-native";

import { useAppContext, useAppTheme, useThemedStyles } from "../context/AppContext";
import { useKeyboardAwareScroll } from "../hooks/useKeyboardAwareScroll";
import { i18n } from "../i18n";
import { AppTheme } from "../theme";
import { authBackgroundImage, BrandMark } from "../components/BrandMark";

export function LoginScreen({ navigation }: any) {
    const theme = useAppTheme();
    const styles = useThemedStyles(createStyles);
    const insets = useSafeAreaInsets();
    const { showToast, signIn, statusMessage } = useAppContext();
    const { handleInputFocus, handleScroll, keyboardInset, scrollRef } =
        useKeyboardAwareScroll();
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [showPassword, setShowPassword] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleSubmit() {
        setSubmitting(true);
        setError(null);

        try {
            await signIn({
                email,
                password,
            });
        } catch (nextError) {
            const message =
                nextError instanceof Error
                    ? nextError.message
                    : "Sign in failed.";

            setError(message);
            showToast(message, "error");
        } finally {
            setSubmitting(false);
        }
    }

    React.useEffect(() => {
        if (!statusMessage || error) {
            return;
        }

        showToast(statusMessage, "warning");
    }, [error, showToast, statusMessage]);

    return (
        <View style={styles.background}>
            <StatusBar style="light" />

            <KeyboardAvoidingView
                behavior={Platform.OS === "ios" ? "padding" : undefined}
                style={styles.flex}
            >
                <ScrollView
                    ref={scrollRef}
                    contentContainerStyle={styles.scroll}
                    keyboardShouldPersistTaps="handled"
                    onScroll={handleScroll}
                    scrollEventThrottle={16}
                >
                    <ImageBackground source={authBackgroundImage} style={[styles.hero, { paddingTop: insets.top + 20 }]}
                        imageStyle={styles.backgroundImage}>
                        <View pointerEvents="none" style={styles.overlay} />
                        <BrandMark
                            logoSize={92}
                            titleSize={28}
                            subtitleSize={18}
                        />
                        <Text style={styles.portal}>{i18n.t("loginPortal")}</Text>
                    </ImageBackground>

                    <View style={styles.formSection}>
                        <View style={styles.introduction}>
                            <Text accessibilityRole="header" style={styles.heading}>{i18n.t("loginWelcome")}</Text>
                            <Text style={styles.support}>{i18n.t("loginAccess")}</Text>
                        </View>
                        <Text style={styles.label}>{i18n.t("email")}</Text>
                        <View style={styles.inputShell}>
                            <Ionicons
                                accessible={false}
                                name="mail-outline"
                                size={22}
                                color={theme.colors.primary}
                                style={styles.leftIcon}
                            />
                            <TextInput
                                accessibilityLabel={i18n.t("email")}
                                autoCapitalize="none"
                                keyboardType="email-address"
                                placeholder={i18n.t("email")}
                                placeholderTextColor={theme.colors.placeholder}
                                style={styles.input}
                                value={email}
                                onChangeText={setEmail}
                                onFocus={handleInputFocus}
                            />
                        </View>

                        <Text style={styles.label}>{i18n.t("password")}</Text>
                        <View style={styles.inputShell}>
                            <Ionicons
                                name="lock-closed-outline"
                                accessible={false}
                                size={22}
                                color={theme.colors.primary}
                                style={styles.leftIcon}
                            />
                            <TextInput
                                accessibilityLabel={i18n.t("password")}
                                secureTextEntry={!showPassword}
                                placeholder={i18n.t("password")}
                                placeholderTextColor={theme.colors.placeholder}
                                style={styles.input}
                                value={password}
                                onChangeText={setPassword}
                                onFocus={handleInputFocus}
                            />
                            <Pressable
                                onPress={() =>
                                    setShowPassword((current) => !current)
                                }
                                accessibilityRole="button"
                                accessibilityLabel={
                                    showPassword
                                        ? i18n.t("hidePassword")
                                        : i18n.t("showPassword")
                                }
                                style={styles.eyeButton}
                            >
                                <Ionicons
                                    accessible={false}
                                    name={
                                        showPassword
                                            ? "eye-off-outline"
                                            : "eye-outline"
                                    }
                                    size={22}
                                    color={theme.colors.primaryDark}
                                />
                            </Pressable>
                        </View>

                        <Pressable
                            accessibilityRole="button"
                            accessibilityLabel={i18n.t("forgotPassword")}
                            onPress={() => navigation.navigate("ForgotPassword")}
                            style={styles.forgotButton}
                        >
                            <Text style={styles.forgotButtonText}>
                                {i18n.t("forgotPassword")}
                            </Text>
                        </Pressable>

                        <Pressable
                            accessibilityRole="button"
                            accessibilityLabel={i18n.t("signIn")}
                            accessibilityState={{ disabled: submitting, busy: submitting }}
                            onPress={handleSubmit}
                            style={[
                                styles.primaryButton,
                                submitting && styles.buttonDisabled,
                            ]}
                            disabled={submitting}
                        >
                            {submitting ? (
                                <ActivityIndicator color={theme.colors.textOnPrimary} />
                            ) : (
                                <Text style={styles.primaryButtonText}>
                                    {i18n.t("signIn")}
                                </Text>
                            )}
                        </Pressable>
                        <View style={[styles.notes, { paddingBottom: keyboardInset + insets.bottom }]}>
                            <Text style={styles.notePrimary}>
                                {i18n.t("loginGuidance")}
                            </Text>
                        </View>
                    </View>
                </ScrollView>
            </KeyboardAvoidingView>
        </View>
    );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
    background: {
        flex: 1,
        backgroundColor: theme.colors.surface,
    },
    backgroundImage: {
        resizeMode: "cover",
    },
    overlay: {
        ...StyleSheet.absoluteFill,
        backgroundColor: "rgba(0, 45, 92, 0.88)",
    },
    flex: { flex: 1 },
    scroll: {
        flexGrow: 1,
    },
    hero: {
        alignItems: "center",
        backgroundColor: theme.colors.brandBackground,
        paddingHorizontal: 20,
        paddingBottom: 24,
        gap: 12,
    },
    portal: { color: theme.colors.textOnBrand, fontSize: 14, lineHeight: 21, textAlign: "center" },
    introduction: { gap: 6, marginBottom: 8 },
    heading: { color: theme.colors.text, fontSize: 22, lineHeight: 28, fontWeight: "600" },
    support: { color: theme.colors.textMuted, fontSize: 14, lineHeight: 22 },
    label: { color: theme.colors.text, fontSize: 14, lineHeight: 20, fontWeight: "500" },
    formSection: {
        backgroundColor: theme.colors.surface,
        padding: 20,
        gap: 8,
    },
    inputShell: {
        minHeight: 54,
        borderRadius: 6,
        borderWidth: 1,
        borderColor: theme.colors.border,
        backgroundColor: theme.colors.inputBackground,
        flexDirection: "row",
        alignItems: "center",
        paddingHorizontal: 12,
    },
    leftIcon: {
        marginRight: 12,
    },
    input: {
        flex: 1,
        minWidth: 0,
        color: theme.colors.text,
        fontSize: 16,
        paddingVertical: 12,
    },
    eyeButton: {
        minWidth: 48,
        minHeight: 48,
        alignItems: "center",
        justifyContent: "center",
    },
    forgotButton: {
        alignSelf: "flex-end",
        minHeight: 48,
        justifyContent: "center",
        maxWidth: "100%",
    },
    forgotButtonText: {
        color: theme.colors.primary,
        fontSize: 15,
        fontWeight: "500",
        textDecorationLine: "underline",
    },
    primaryButton: {
        backgroundColor: theme.colors.primary,
        borderRadius: 6,
        minHeight: 54,
        paddingVertical: 12,
        alignItems: "center",
        justifyContent: "center",
    },
    primaryButtonText: {
        color: theme.colors.textOnPrimary,
        fontSize: 16,
        lineHeight: 24,
        fontWeight: "700",
    },
    notes: {
        marginTop: 12,
    },
    notePrimary: {
        color: theme.colors.textMuted,
        fontSize: 13,
        lineHeight: 20,
    },
    buttonDisabled: {
        opacity: 0.7,
    },
});
