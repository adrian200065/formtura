# Formtura Testing Guide

This document describes the testing infrastructure and how to run tests for the Formtura plugin.

## Overview

Formtura uses a comprehensive testing strategy with:
- **Jest** for React component tests
- **PHPUnit** for PHP backend tests
- **React Testing Library** for user-centric component testing

## Test Coverage Goals

- **Minimum Coverage:** 60% across all metrics
- **Target Coverage:** 80%+ for critical paths

## Running Tests

### React/JavaScript Tests

```bash
# Run all tests
npm test

# Run tests in watch mode
npm run test:watch

# Run tests with coverage report
npm run test:coverage
```

#### Coverage Reports

After running `npm run test:coverage`, open `coverage/lcov-report/index.html` in your browser to view detailed coverage reports.

### PHP Tests

```bash
# Run all PHP tests
composer test

# Run tests with coverage (requires xdebug)
composer test:coverage
```

#### Coverage Reports

PHP coverage reports are generated in `coverage/html/index.html`.

## Test Structure

### React Tests

Located in `builder/**/__tests__/`:

```
builder/
├── components/
│   ├── __tests__/
│   │   ├── FieldPreview.test.jsx
│   │   ├── FormCanvas.test.jsx
│   │   └── FormBuilder.test.jsx
│   └── ...
└── utils/
    ├── __tests__/
    │   └── helpers.test.js
    └── ...
```

### PHP Tests

Located in `tests/`:

```
tests/
├── bootstrap.php
├── TestCase.php
├── Unit/
│   └── Utils/
│       └── SanitizeTest.php
└── Integration/
    └── (integration tests)
```

## Writing Tests

### React Component Tests

Example test structure:

```javascript
import { render, screen } from '@testing-library/react';
import MyComponent from '../MyComponent';

describe('MyComponent', () => {
  it('should render correctly', () => {
    render(<MyComponent />);
    expect(screen.getByText('Hello')).toBeInTheDocument();
  });
});
```

### PHP Unit Tests

Example test structure:

```php
<?php
namespace Formtura\Tests\Unit;

use Formtura\Tests\TestCase;
use Formtura\MyClass;

class MyClassTest extends TestCase {
    public function test_method() {
        $instance = new MyClass();
        $result = $instance->method();
        $this->assertEquals('expected', $result);
    }
}
```

## Test Configuration

### Jest Configuration

See `jest.config.js` for:
- Test environment setup
- Module path mappings
- Coverage thresholds
- Transform rules

### PHPUnit Configuration

See `phpunit.xml` for:
- Test suites
- Bootstrap file
- Coverage settings
- Code filtering

## Continuous Integration

Tests should be run in CI/CD pipelines before merging:

```yaml
# Example GitHub Actions workflow
- name: Run JavaScript Tests
  run: npm run test:coverage

- name: Run PHP Tests
  run: composer test
```

## Debugging Tests

### Jest

```bash
# Run specific test file
npm test -- FieldPreview.test.jsx

# Run tests matching pattern
npm test -- --testNamePattern="should render"

# Debug with Chrome DevTools
node --inspect-brk node_modules/.bin/jest --runInBand
```

### PHPUnit

```bash
# Run specific test
phpunit --filter test_method

# Run specific test file
phpunit tests/Unit/Utils/SanitizeTest.php

# Stop on failure
phpunit --stop-on-failure
```

## Best Practices

1. **Write tests first** (TDD) when adding new features
2. **Test user behavior**, not implementation details
3. **Use descriptive test names** that explain what's being tested
4. **Mock external dependencies** (AJAX, WordPress functions)
5. **Keep tests isolated** - each test should run independently
6. **Aim for fast tests** - avoid unnecessary waits/delays
7. **Test edge cases** and error conditions
8. **Maintain test coverage** above 60% threshold

## Common Issues

### Issue: Tests fail with "Cannot find module"
**Solution:** Check module path mappings in `jest.config.js`

### Issue: WordPress functions not defined
**Solution:** Add mocks to `jest.setup.js`

### Issue: PHPUnit can't find classes
**Solution:** Run `composer dump-autoload`

### Issue: Coverage below threshold
**Solution:** Add tests for uncovered code paths

## Resources

- [Jest Documentation](https://jestjs.io/)
- [React Testing Library](https://testing-library.com/react)
- [PHPUnit Documentation](https://phpunit.de/)
- [WordPress PHPUnit Guide](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
