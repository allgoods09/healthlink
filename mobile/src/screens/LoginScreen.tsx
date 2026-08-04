import React, { useState } from "react";
import { Ionicons } from "@expo/vector-icons";
import { StatusBar } from "expo-status-bar";
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
        <ImageBackground
            source={authBackgroundImage}
            style={styles.background}
            imageStyle={styles.backgroundImage}
        >
            <StatusBar style="light" />
            <View style={styles.overlay} />

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
                    <View style={styles.hero}>
                        <BrandMark
                            logoSize={118}
                            titleSize={34}
                            subtitleSize={28}
                        />
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
                                placeholder={i18n.t("email")}
                                placeholderTextColor={theme.colors.placeholder}
                                style={styles.input}
                                value={email}
                                onChangeText={setEmail}
                                onFocus={handleInputFocus}
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
                                    name={
                                        showPassword
                                            ? "eye-off-outline"
                                            : "eye-outline"
                                    }
                                    size={28}
                                    color={theme.colors.primaryDark}
                                />
                            </Pressable>
                        </View>

                        <Pressable
                            onPress={() => navigation.navigate("ForgotPassword")}
                            style={styles.forgotButton}
                        >
                            <Text style={styles.forgotButtonText}>
                                {i18n.t("forgotPassword")}
                            </Text>
                        </Pressable>

                        <Pressable
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
                    </View>

                    <View style={[styles.notes, { paddingBottom: keyboardInset }]}>
                        <Text style={styles.notePrimary}>
                            {i18n.t("loginSubtitle")}
                        </Text>
                        <Text style={styles.noteSecondary}>
                            {i18n.t("loginSecondaryNote")}
                        </Text>
                    </View>
                </ScrollView>
            </KeyboardAvoidingView>
        </ImageBackground>
    );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
    background: {
        flex: 1,
        backgroundColor: theme.colors.brandBackground,
    },
    backgroundImage: {
        resizeMode: "cover",
    },
    overlay: {
        ...StyleSheet.absoluteFill,
        backgroundColor: theme.colors.imageOverlay,
    },
    flex: { flex: 1 },
    scroll: {
        flexGrow: 1,
        justifyContent: "space-between",
        paddingHorizontal: theme.spacing.lg,
        paddingTop: 56,
        paddingBottom: 34,
    },
    hero: {
        alignItems: "center",
        marginBottom: theme.spacing.xl,
    },
    formSection: {
        gap: 14,
    },
    inputShell: {
        minHeight: 74,
        borderRadius: 16,
        borderWidth: 1.2,
        borderColor: theme.colors.authInputBorder,
        backgroundColor: theme.colors.authInputBackground,
        flexDirection: "row",
        alignItems: "center",
        paddingHorizontal: 18,
        shadowColor: theme.colors.shadow,
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
        color: theme.colors.text,
        fontSize: 16,
        paddingVertical: 18,
    },
    eyeButton: {
        paddingLeft: 10,
    },
    forgotButton: {
        alignSelf: "flex-end",
        marginTop: -2,
    },
    forgotButtonText: {
        color: theme.colors.textOnBrand,
        fontSize: 15,
        fontWeight: "500",
        textDecorationLine: "underline",
    },
    primaryButton: {
        backgroundColor: theme.colors.accent,
        borderRadius: 16,
        borderWidth: 1,
        borderColor: theme.colors.primaryDark,
        minHeight: 76,
        paddingVertical: 15,
        alignItems: "center",
        justifyContent: "center",
        shadowColor: theme.colors.shadow,
        shadowOpacity: 0.2,
        shadowRadius: 12,
        shadowOffset: { width: 0, height: 8 },
        elevation: 4,
    },
    primaryButtonText: {
        color: theme.colors.textOnPrimary,
        fontSize: 18,
        fontWeight: "700",
    },
    notes: {
        marginTop: theme.spacing.xl,
        paddingHorizontal: 4,
    },
    notePrimary: {
        color: theme.colors.textOnBrand,
        textAlign: "center",
        fontSize: 14,
        lineHeight: 24,
        fontWeight: "500",
    },
    noteSecondary: {
        color: theme.colors.textOnBrand,
        textAlign: "center",
        fontSize: 14,
        lineHeight: 24,
        marginTop: 18,
    },
    buttonDisabled: {
        opacity: 0.7,
    },
});
