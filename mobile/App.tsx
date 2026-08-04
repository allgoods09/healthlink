import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { StatusBar } from 'react-native';
import { NavigationBar } from 'expo-navigation-bar';

import { AppProvider, useAppTheme } from './src/context/AppContext';
import { AppNavigator } from './src/navigation/AppNavigator';

function AppShell() {
  const appTheme = useAppTheme();
  const isDark = appTheme.mode === 'dark';

  return (
    <>
      <StatusBar
        animated
        backgroundColor={appTheme.colors.surface}
        barStyle={isDark ? 'light-content' : 'dark-content'}
      />
      <NavigationBar style={isDark ? 'dark' : 'light'} />
      <AppNavigator />
    </>
  );
}

export default function App() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <AppProvider>
          <AppShell />
        </AppProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
