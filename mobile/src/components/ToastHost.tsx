import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import {
  Animated,
  PanResponder,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { theme } from '../theme';
import { MobileToast } from '../types';

type ToastHostProps = {
  toast: MobileToast | null;
  onDismiss: () => void;
};

export function ToastHost({ toast, onDismiss }: ToastHostProps) {
  const insets = useSafeAreaInsets();
  const opacity = React.useRef(new Animated.Value(0)).current;
  const translateY = React.useRef(new Animated.Value(-24)).current;
  const translateX = React.useRef(new Animated.Value(0)).current;

  const dismissToast = React.useCallback(() => {
    Animated.parallel([
      Animated.timing(opacity, {
        toValue: 0,
        duration: 180,
        useNativeDriver: true,
      }),
      Animated.timing(translateY, {
        toValue: -24,
        duration: 180,
        useNativeDriver: true,
      }),
      Animated.timing(translateX, {
        toValue: 0,
        duration: 180,
        useNativeDriver: true,
      }),
    ]).start(({ finished }) => {
      if (finished) {
        onDismiss();
      }
    });
  }, [onDismiss, opacity, translateX, translateY]);

  React.useEffect(() => {
    if (!toast) {
      return;
    }

    opacity.setValue(0);
    translateY.setValue(-24);
    translateX.setValue(0);

    Animated.parallel([
      Animated.timing(opacity, {
        toValue: 1,
        duration: 220,
        useNativeDriver: true,
      }),
      Animated.spring(translateY, {
        toValue: 0,
        damping: 16,
        stiffness: 180,
        mass: 0.9,
        useNativeDriver: true,
      }),
    ]).start();

    const timer = setTimeout(() => {
      dismissToast();
    }, 5000);

    return () => clearTimeout(timer);
  }, [dismissToast, opacity, toast, translateX, translateY]);

  const panResponder = React.useMemo(
    () =>
      PanResponder.create({
        onMoveShouldSetPanResponder: (_, gestureState) =>
          Math.abs(gestureState.dx) > 8 &&
          Math.abs(gestureState.dx) > Math.abs(gestureState.dy),
        onPanResponderMove: (_, gestureState) => {
          translateX.setValue(gestureState.dx);
        },
        onPanResponderRelease: (_, gestureState) => {
          if (Math.abs(gestureState.dx) > 84) {
            dismissToast();
            return;
          }

          Animated.spring(translateX, {
            toValue: 0,
            damping: 18,
            stiffness: 220,
            mass: 0.8,
            useNativeDriver: true,
          }).start();
        },
      }),
    [dismissToast, translateX]
  );

  if (!toast) {
    return null;
  }

  const palette = paletteForLevel(toast.level);

  return (
    <View
      pointerEvents="box-none"
      style={[styles.portal, { top: Math.max(insets.top, 12) + 6 }]}
    >
      <Animated.View
        {...panResponder.panHandlers}
        style={[
          styles.card,
          {
            borderColor: palette.border,
            backgroundColor: palette.background,
            opacity,
            transform: [{ translateY }, { translateX }],
          },
        ]}
      >
        <View
          style={[styles.iconWrap, { backgroundColor: palette.iconBackground }]}
        >
          <Ionicons name={palette.icon} size={18} color={palette.iconColor} />
        </View>
        <View style={styles.messageWrap}>
          <Text style={[styles.message, { color: palette.textColor }]}>
            {toast.message}
          </Text>
        </View>
        <Pressable onPress={dismissToast} style={styles.closeButton}>
          <Ionicons name="close" size={18} color={palette.textColor} />
        </Pressable>
      </Animated.View>
    </View>
  );
}

function paletteForLevel(level: MobileToast['level']) {
  switch (level) {
    case 'success':
      return {
        background: '#ECFDF3',
        border: '#A7F3D0',
        iconBackground: '#D1FAE5',
        iconColor: '#047857',
        textColor: '#065F46',
        icon: 'checkmark-circle' as const,
      };
    case 'warning':
      return {
        background: '#FFF7ED',
        border: '#FED7AA',
        iconBackground: '#FFEDD5',
        iconColor: '#C2410C',
        textColor: '#9A3412',
        icon: 'warning' as const,
      };
    case 'error':
      return {
        background: '#FEF2F2',
        border: '#FECACA',
        iconBackground: '#FEE2E2',
        iconColor: '#B91C1C',
        textColor: '#991B1B',
        icon: 'alert-circle' as const,
      };
    default:
      return {
        background: '#EFF6FF',
        border: '#BFDBFE',
        iconBackground: '#DBEAFE',
        iconColor: '#1D4ED8',
        textColor: '#1E3A8A',
        icon: 'notifications' as const,
      };
  }
}

const styles = StyleSheet.create({
  portal: {
    position: 'absolute',
    left: 12,
    right: 12,
    zIndex: 80,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    borderWidth: 1,
    borderRadius: 18,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: 14,
    shadowColor: theme.colors.shadow,
    shadowOpacity: 1,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 6 },
    elevation: 5,
  },
  iconWrap: {
    width: 32,
    height: 32,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  messageWrap: {
    flex: 1,
    paddingTop: 2,
  },
  message: {
    fontSize: 14,
    fontWeight: '700',
    lineHeight: 20,
  },
  closeButton: {
    padding: 2,
    marginLeft: 10,
  },
});
