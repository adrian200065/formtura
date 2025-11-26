import { useEffect, useRef } from 'react';

/**
 * Hook for managing focus on elements
 *
 * @param {boolean} shouldFocus - Whether to focus the element
 * @returns {Object} - Ref to attach to element
 */
export const useFocusManagement = (shouldFocus = false) => {
  const elementRef = useRef(null);

  useEffect(() => {
    if (shouldFocus && elementRef.current) {
      elementRef.current.focus();
    }
  }, [shouldFocus]);

  return elementRef;
};

/**
 * Hook for trapping focus within a container (for modals, dialogs)
 *
 * @param {boolean} isActive - Whether focus trap is active
 * @returns {Object} - Ref to attach to container
 */
export const useFocusTrap = (isActive = false) => {
  const containerRef = useRef(null);

  useEffect(() => {
    if (!isActive || !containerRef.current) return;

    const container = containerRef.current;
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    const handleTabKey = (e) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          lastElement?.focus();
          e.preventDefault();
        }
      } else {
        if (document.activeElement === lastElement) {
          firstElement?.focus();
          e.preventDefault();
        }
      }
    };

    const handleEscapeKey = (e) => {
      if (e.key === 'Escape') {
        // Trigger close event
        const closeButton = container.querySelector('[data-close-modal]');
        closeButton?.click();
      }
    };

    container.addEventListener('keydown', handleTabKey);
    container.addEventListener('keydown', handleEscapeKey);

    // Focus first element
    firstElement?.focus();

    return () => {
      container.removeEventListener('keydown', handleTabKey);
      container.removeEventListener('keydown', handleEscapeKey);
    };
  }, [isActive]);

  return containerRef;
};

/**
 * Hook for managing keyboard navigation (arrow keys)
 *
 * @param {Array} items - Array of items to navigate
 * @param {Function} onSelect - Callback when item is selected
 * @returns {Object} - { currentIndex, setCurrentIndex, handleKeyDown }
 */
export const useKeyboardNavigation = (items, onSelect) => {
  const [currentIndex, setCurrentIndex] = useState(0);

  const handleKeyDown = (e) => {
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setCurrentIndex((prev) => Math.min(prev + 1, items.length - 1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setCurrentIndex((prev) => Math.max(prev - 1, 0));
        break;
      case 'Enter':
      case ' ':
        e.preventDefault();
        if (onSelect && items[currentIndex]) {
          onSelect(items[currentIndex]);
        }
        break;
      case 'Home':
        e.preventDefault();
        setCurrentIndex(0);
        break;
      case 'End':
        e.preventDefault();
        setCurrentIndex(items.length - 1);
        break;
      default:
        break;
    }
  };

  return { currentIndex, setCurrentIndex, handleKeyDown };
};

export default {
  useFocusManagement,
  useFocusTrap,
  useKeyboardNavigation,
};
