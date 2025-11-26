import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
  i18n: {
    __: (text) => text,
    _x: (text) => text,
    _n: (single, plural, number) => number === 1 ? single : plural,
  },
};

// Mock formturaBuilder global
global.formturaBuilder = {
  ajaxUrl: '/wp-admin/admin-ajax.php',
  nonce: 'test-nonce-12345',
  restUrl: '/wp-json/formtura/v1',
  restNonce: 'test-rest-nonce',
};

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: jest.fn(),
    removeListener: jest.fn(),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    dispatchEvent: jest.fn(),
  })),
});
