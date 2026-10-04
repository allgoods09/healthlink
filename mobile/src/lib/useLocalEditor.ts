import { useLayoutEffect } from 'react';
import { registerLocalEditor } from './syncGuard';

// Even an unsaved form must not be replaced by a downloaded dataset.
export function useLocalEditor() {
  useLayoutEffect(registerLocalEditor, []);
}
