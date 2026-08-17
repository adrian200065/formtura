import { useEffect, useState } from 'react';

/**
 * Live Region component for screen reader announcements
 *
 * @param {Object} props - Component props
 * @param {string} props.politeness - 'polite' or 'assertive'
 */
const LiveRegion = ({ politeness = 'polite' }) => {
  const [message, setMessage] = useState('');
  const [level, setLevel] = useState(politeness);

  useEffect(() => {
    // Expose global API for announcing messages
    window.formturaAnnounce = (msg, msgLevel = politeness) => {
      setLevel(msgLevel);
      setMessage(msg);

      // Clear after announcement
      setTimeout(() => setMessage(''), 1000);
    };

    return () => {
      delete window.formturaAnnounce;
    };
  }, [politeness]);

  return (
    <div
      role="status"
      aria-live={level}
      aria-atomic="true"
      className="formtura-sr-only"
    >
      {message}
    </div>
  );
};

/**
 * Utility function to announce to screen readers
 *
 * @param {string} message - Message to announce
 * @param {string} politeness - 'polite' or 'assertive'
 */
export const announce = (message, politeness = 'polite') => {
  if (window.formturaAnnounce) {
    window.formturaAnnounce(message, politeness);
  }
};

export default LiveRegion;
