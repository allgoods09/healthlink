import { Ionicons } from '@expo/vector-icons';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { ChildHeader, childPageStyles } from '../components/ChildHeader';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { i18n } from '../i18n';

export function LanguageScreen({ navigation }: any) {
  const styles = useThemedStyles(childPageStyles);
  const theme = useAppTheme();
  const { language, setLanguagePreference } = useAppContext();
  return <View style={styles.screen}>
    <ChildHeader title={i18n.t('languageTitle')} onBack={() => navigation.goBack()} />
    <ScrollView contentContainerStyle={styles.content}>
      {([["en","english"],["ceb","cebuano"]] as const).map(([value, label]) => {
        const selected = language === value;
        return <Pressable key={value} accessibilityRole="radio" accessibilityLabel={i18n.t(label)}
          accessibilityState={{ checked: selected, selected }} onPress={() => void setLanguagePreference(value)}
          style={[styles.row, selected && styles.selected]}>
          <Text style={[styles.rowLabel, selected && styles.selectedLabel]}>{i18n.t(label)}</Text>
          {selected ? <Ionicons accessible={false} name="checkmark" size={22} color={theme.colors.primaryDark} /> : null}
        </Pressable>;
      })}
    </ScrollView>
  </View>;
}
