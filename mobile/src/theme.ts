import { ColorSchemeName } from 'react-native';

export type ThemeMode = 'light' | 'dark';
export type AppearancePreference = 'system' | ThemeMode;

type ThemeColors = {
  background: string;
  surface: string;
  surfaceElevated: string;
  surfaceMuted: string;
  inputBackground: string;
  inputReadOnly: string;
  inputDisabled: string;
  authInputBackground: string;
  authInputBorder: string;
  brandBackground: string;
  primary: string;
  primaryDark: string;
  primarySoft: string;
  accent: string;
  text: string;
  textMuted: string;
  textOnPrimary: string;
  textOnBrand: string;
  placeholder: string;
  border: string;
  borderStrong: string;
  focus: string;
  danger: string;
  dangerSoft: string;
  dangerBorder: string;
  success: string;
  successSoft: string;
  successBorder: string;
  warning: string;
  warningSoft: string;
  warningBorder: string;
  info: string;
  infoSoft: string;
  infoBorder: string;
  inactive: string;
  inactiveSoft: string;
  overlay: string;
  imageOverlay: string;
  heroTextMuted: string;
  heroBackground: string;
  heroBorder: string;
  heroText: string;
  heroMuted: string;
  heroSurface: string;
  heroSuccessSurface: string;
  tabMuted: string;
  shadow: string;
  cameraBackdrop: string;
};

export type AppTheme = {
  mode: ThemeMode;
  colors: ThemeColors;
  spacing: {
    xs: number;
    sm: number;
    md: number;
    lg: number;
    xl: number;
  };
  radius: {
    sm: number;
    md: number;
    lg: number;
  };
};

const spacing = {
  xs: 6,
  sm: 10,
  md: 16,
  lg: 24,
  xl: 32,
};

const radius = {
  sm: 10,
  md: 16,
  lg: 22,
};

export const lightTheme: AppTheme = {
  mode: 'light',
  colors: {
    background: '#F0F5FA',
    surface: '#FFFFFF',
    surfaceElevated: '#FFFFFF',
    surfaceMuted: '#E4ECF5',
    inputBackground: '#FAFBFC',
    inputReadOnly: '#F2F6FA',
    inputDisabled: '#E9EEF4',
    authInputBackground: 'rgba(240, 247, 255, 0.90)',
    authInputBorder: 'rgba(21, 72, 138, 0.46)',
    brandBackground: '#003F7F',
    primary: '#003F7F',
    primaryDark: '#002D5C',
    primarySoft: '#DCE8F7',
    accent: '#0C66D6',
    text: '#12263A',
    textMuted: '#5B6B7B',
    textOnPrimary: '#FFFFFF',
    textOnBrand: '#FFFFFF',
    placeholder: '#728397',
    border: '#D7E0EA',
    borderStrong: '#A9B9C9',
    focus: '#0C66D6',
    danger: '#B91C1C',
    dangerSoft: '#FDE7E7',
    dangerBorder: '#F6B1B1',
    success: '#166534',
    successSoft: '#DCFCE7',
    successBorder: '#9ED6B1',
    warning: '#9A4D00',
    warningSoft: '#FFF4D6',
    warningBorder: '#F0CA78',
    info: '#135CA8',
    infoSoft: '#E8F1FC',
    infoBorder: '#B8D3F2',
    inactive: '#657384',
    inactiveSoft: '#E8EDF2',
    overlay: 'rgba(7, 22, 43, 0.52)',
    imageOverlay: 'rgba(11, 84, 165, 0.58)',
    heroTextMuted: '#D9E7FA',
    heroBackground: '#003F7F',
    heroBorder: '#003F7F',
    heroText: '#FFFFFF',
    heroMuted: '#D9E7FA',
    heroSurface: 'rgba(255, 255, 255, 0.14)',
    heroSuccessSurface: 'rgba(218, 252, 231, 0.18)',
    tabMuted: '#7E8B99',
    shadow: 'rgba(0, 40, 90, 0.12)',
    cameraBackdrop: 'rgba(0, 0, 0, 0.72)',
  },
  spacing,
  radius,
};

export const darkTheme: AppTheme = {
  mode: 'dark',
  colors: {
    background: '#171B21',
    surface: '#20262F',
    surfaceElevated: '#28313C',
    surfaceMuted: '#2B3541',
    inputBackground: '#1C222A',
    inputReadOnly: '#26303A',
    inputDisabled: '#1A1F26',
    authInputBackground: 'rgba(25, 34, 45, 0.94)',
    authInputBorder: 'rgba(151, 194, 237, 0.44)',
    brandBackground: '#003F7F',
    primary: '#5A9BE0',
    primaryDark: '#B9D9FA',
    primarySoft: '#1D3854',
    accent: '#75B2ED',
    text: '#EDF2F7',
    textMuted: '#AAB5C2',
    textOnPrimary: '#071625',
    textOnBrand: '#FFFFFF',
    placeholder: '#8290A0',
    border: '#3B4654',
    borderStrong: '#596778',
    focus: '#8CC4F5',
    danger: '#F07F7F',
    dangerSoft: '#4A282C',
    dangerBorder: '#8B4D55',
    success: '#62C591',
    successSoft: '#1D402D',
    successBorder: '#367557',
    warning: '#F0B963',
    warningSoft: '#4A371B',
    warningBorder: '#806231',
    info: '#7BB7F2',
    infoSoft: '#203B57',
    infoBorder: '#426E99',
    inactive: '#AAB5C2',
    inactiveSoft: '#313A45',
    overlay: 'rgba(3, 7, 12, 0.68)',
    imageOverlay: 'rgba(5, 42, 82, 0.76)',
    heroTextMuted: '#C9DDF4',
    heroBackground: '#28313C',
    heroBorder: '#5A9BE0',
    heroText: '#EDF2F7',
    heroMuted: '#AAB5C2',
    heroSurface: '#20262F',
    heroSuccessSurface: 'rgba(98, 197, 145, 0.18)',
    tabMuted: '#A9B3BF',
    shadow: 'rgba(0, 0, 0, 0.34)',
    cameraBackdrop: 'rgba(0, 0, 0, 0.82)',
  },
  spacing,
  radius,
};

export function resolveTheme(
  preference: AppearancePreference,
  systemColorScheme: ColorSchemeName
): AppTheme {
  const mode = preference === 'system'
    ? systemColorScheme === 'dark'
      ? 'dark'
      : 'light'
    : preference;

  return mode === 'dark' ? darkTheme : lightTheme;
}
