module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/builder/$1',
    '\\.(css|less|scss|sass)$': 'identity-obj-proxy',
  },
  transform: {
    '^.+\\.(js|jsx)$': ['babel-jest', {
      presets: [
        ['@babel/preset-env', { targets: { node: 'current' } }],
        ['@babel/preset-react', { runtime: 'automatic' }]
      ]
    }],
  },
  testMatch: [
    '**/__tests__/**/*.[jt]s?(x)',
    '**/?(*.)+(spec|test).[jt]s?(x)'
  ],
  // Git worktrees under .worktrees/ are full checkouts of this same repo, so
  // without this every test file is discovered twice - once here and once in
  // each worktree, running a stale copy of the code alongside the real one.
  testPathIgnorePatterns: [
    '/node_modules/',
    '/vendor/',
    '/.worktrees/',
    '/dist/',
    // Playwright specs, not Jest - they import @playwright/test's own
    // test()/expect() and only run correctly under `playwright test`.
    '/tests/e2e/',
  ],
  collectCoverageFrom: [
    'builder/**/*.{js,jsx}',
    '!builder/main.jsx',
    '!builder/**/*.test.{js,jsx}',
    '!**/node_modules/**',
    '!**/vendor/**',
  ],
  coverageThreshold: {
    global: {
      branches: 60,
      functions: 60,
      lines: 60,
      statements: 60,
    },
  },
  coverageReporters: ['text', 'lcov', 'html'],
};
