import { renderHook, act } from '@testing-library/react';
import { useKeyboardNavigation } from '../useFocusManagement';

/**
 * useKeyboardNavigation calls useState() but the module only imports
 * useEffect and useRef from 'react' - calling it throws a ReferenceError.
 * It has no caller anywhere in the codebase yet, which is exactly how a
 * dead export can carry a broken implementation with nothing to catch it
 * until ESLint actually runs over builder/ (see no-undef).
 */
describe('useKeyboardNavigation', () => {
  it('does not throw when the hook is used', () => {
    const items = ['a', 'b', 'c'];

    expect(() => renderHook(() => useKeyboardNavigation(items, () => {}))).not.toThrow();
  });

  it('moves the current index forward on ArrowDown', () => {
    const items = ['a', 'b', 'c'];
    const { result } = renderHook(() => useKeyboardNavigation(items, () => {}));

    act(() => {
      result.current.handleKeyDown({ key: 'ArrowDown', preventDefault: () => {} });
    });

    expect(result.current.currentIndex).toBe(1);
  });

  it('calls onSelect with the current item on Enter', () => {
    const items = ['a', 'b', 'c'];
    const onSelect = jest.fn();
    const { result } = renderHook(() => useKeyboardNavigation(items, onSelect));

    act(() => {
      result.current.handleKeyDown({ key: 'ArrowDown', preventDefault: () => {} });
    });
    act(() => {
      result.current.handleKeyDown({ key: 'Enter', preventDefault: () => {} });
    });

    expect(onSelect).toHaveBeenCalledWith('b');
  });
});
