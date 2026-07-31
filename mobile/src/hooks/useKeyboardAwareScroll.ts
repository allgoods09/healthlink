import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Dimensions,
  Keyboard,
  NativeSyntheticEvent,
  NativeScrollEvent,
  Platform,
  ScrollView,
  TextInput,
} from 'react-native';

type UseKeyboardAwareScrollOptions = {
  extraOffset?: number;
  keyboardVerticalOffset?: number;
  focusDelayMs?: number;
};

export function useKeyboardAwareScroll({
  extraOffset = 20,
  keyboardVerticalOffset = 0,
  focusDelayMs = 90,
}: UseKeyboardAwareScrollOptions = {}) {
  const scrollRef = useRef<ScrollView | null>(null);
  const scrollOffsetRef = useRef(0);
  const keyboardHeightRef = useRef(0);
  const focusTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [keyboardInset, setKeyboardInset] = useState(0);

  const scrollFocusedInputIntoView = useCallback(() => {
    const focusedInput = (TextInput as any).State?.currentlyFocusedInput?.() as
      | { measureInWindow?: (callback: (x: number, y: number, width: number, height: number) => void) => void }
      | null;

    if (!focusedInput?.measureInWindow || keyboardHeightRef.current <= 0) {
      return;
    }

    focusedInput.measureInWindow((_x, y, _width, height) => {
      const windowHeight = Dimensions.get('window').height;
      const safeBottom =
        windowHeight - keyboardHeightRef.current - keyboardVerticalOffset - extraOffset;
      const inputBottom = y + height;

      if (inputBottom <= safeBottom) {
        return;
      }

      const overlap = inputBottom - safeBottom;

      scrollRef.current?.scrollTo({
        y: Math.max(scrollOffsetRef.current + overlap, 0),
        animated: true,
      });
    });
  }, [extraOffset, keyboardVerticalOffset]);

  const scheduleFocusedInputScroll = useCallback(() => {
    if (focusTimerRef.current) {
      clearTimeout(focusTimerRef.current);
    }

    focusTimerRef.current = setTimeout(() => {
      requestAnimationFrame(scrollFocusedInputIntoView);
    }, focusDelayMs);
  }, [focusDelayMs, scrollFocusedInputIntoView]);

  useEffect(() => {
    const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvent = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';

    const showSubscription = Keyboard.addListener(showEvent, (event) => {
      const nextHeight = event.endCoordinates.height;

      keyboardHeightRef.current = nextHeight;
      setKeyboardInset(nextHeight + extraOffset);
      scheduleFocusedInputScroll();
    });

    const hideSubscription = Keyboard.addListener(hideEvent, () => {
      keyboardHeightRef.current = 0;
      setKeyboardInset(0);
    });

    return () => {
      showSubscription.remove();
      hideSubscription.remove();

      if (focusTimerRef.current) {
        clearTimeout(focusTimerRef.current);
      }
    };
  }, [extraOffset, scheduleFocusedInputScroll]);

  const handleScroll = useCallback(
    (event: NativeSyntheticEvent<NativeScrollEvent>) => {
      scrollOffsetRef.current = event.nativeEvent.contentOffset.y;
    },
    []
  );

  const handleInputFocus = useCallback(() => {
    scheduleFocusedInputScroll();
  }, [scheduleFocusedInputScroll]);

  return {
    scrollRef,
    keyboardInset,
    handleScroll,
    handleInputFocus,
  };
}
