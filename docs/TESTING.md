# Testing Documentation

## 📊 Test Coverage Overview

This project maintains **88.82%** line coverage and **76.92%** method coverage, exceeding industry standards.

### Coverage Statistics

| Component | Method Coverage | Line Coverage | Status | Improvement |
|-----------|----------------|---------------|--------|-------------|
| **Overall** | **76.92%** (40/52) | **88.82%** (286/322) | 🟢 Excellent | +34.65% |
| Logger | 91.67% (11/12) | 96.36% (53/55) | 🟢 Excellent | +7.23% |
| LoggerFactory | 82.35% (14/17) | 91.18% (93/102) | 🟢 Excellent | +52.64% |
| NamedRotatingFileHandler | 42.86% (3/7) | 80.95% (51/63) | 🟡 Good | +15.02% |
| SmtpHandler | 66.67% (2/3) | 95.65% (22/23) | 🟢 Excellent | New |
| CostTimeProcessor | 100.00% (2/2) | 100.00% (20/20) | 🟢 Excellent | +30.00% |
| MemoryProcessor | 66.67% (2/3) | 82.35% (14/17) | 🟢 Excellent | +5.88% |
| ProfilerProcessor | 100.00% (2/2) | 100.00% (22/22) | 🟢 Excellent | +31.82% |
| ScriptProcessor | 100.00% (2/2) | 100.00% (7/7) | 🟢 Excellent | +7.69% |
| WebProcessor | 50.00% (2/4) | 30.77% (4/13) | 🟡 Good | +7.69% |

## 🧪 Test Suite Details

### Core Functionality Tests
- **LoggerTest**: Core functionality tests for Logger class
- **LoggerEdgeCasesTest**: Edge case tests for Logger class
- **LoggerFactoryTest**: Basic functionality tests for factory class
- **LoggerFactoryEdgeCasesTest**: Edge case tests for factory class
- **LoggerFactoryComprehensiveTest**: Comprehensive tests for factory class
- **LoggerFactoryAdvancedTest**: Advanced functionality tests for factory class

### Handler Tests
- **HandlerTest**: Tests for various log handlers
- **NamedRotatingFileHandlerTest**: Specific tests for rotating file handler
- **SmtpHandlerTest**: Email handler functionality tests

### Processor Tests
- **ProcessorTest**: Tests for log processors
- **CostTimeProcessorTest**: Performance timing processor tests
- **MemoryProcessorTest**: Memory usage processor tests
- **ProfilerProcessorTest**: Profiling processor tests

### Integration Tests
- **ComprehensiveCoverageTest**: Full integration coverage tests
- **LaravelIntegrationTest**: Laravel framework integration tests

## 🚀 Running Tests

### Prerequisites

Ensure you have the required dependencies installed:

```bash
composer install
```

### Basic Test Commands

```bash
# Run all tests
composer test

# Run tests with verbose output
./vendor/bin/phpunit --verbose

# Run specific test file
./vendor/bin/phpunit tests/LoggerTest.php

# Run specific test method
./vendor/bin/phpunit --filter testBasicLogging tests/LoggerTest.php
```

### Coverage Reports

```bash
# Generate HTML coverage report
composer test:coverage-html

# Generate Clover XML coverage report
composer test:coverage-clover

# Generate text coverage report
composer test:coverage-text

# Run tests with coverage (combined)
composer test:coverage
```

### Test Categories

```bash
# Run only unit tests
./vendor/bin/phpunit --group unit

# Run only integration tests
./vendor/bin/phpunit --group integration

# Run only Laravel tests
./vendor/bin/phpunit --group laravel

# Exclude slow tests
./vendor/bin/phpunit --exclude-group slow
```

## 📋 Test Structure

### Test Organization

```
tests/
├── Unit/                          # Unit tests
│   ├── LoggerTest.php            # Core logger functionality
│   ├── LoggerFactoryTest.php     # Factory pattern tests
│   ├── HandlerTest.php           # Handler tests
│   └── ProcessorTest.php         # Processor tests
├── Integration/                   # Integration tests
│   ├── ComprehensiveCoverageTest.php
│   └── LaravelIntegrationTest.php
├── Feature/                       # Feature tests
│   └── EndToEndTest.php
└── TestCase.php                   # Base test case
```

### Test Naming Conventions

- **Unit Tests**: `test{MethodName}{Scenario}`
- **Integration Tests**: `test{Feature}{Integration}`
- **Edge Cases**: `test{Method}{EdgeCase}`

Example:
```php
public function testBasicLoggingWithDefaultChannel()
public function testLoggerFactoryWithCustomConfig()
public function testHandlerWithInvalidConfiguration()
```

## 🎯 Test Quality Metrics

### Current Statistics

- **Total Tests**: 144
- **Total Assertions**: 403
- **Average Assertions per Test**: 2.8
- **Test Execution Time**: ~0.3 seconds
- **Memory Usage**: ~18MB

### Quality Indicators

| Metric | Value | Target | Status |
|--------|-------|--------|--------|
| Line Coverage | 88.82% | >85% | ✅ Achieved |
| Method Coverage | 76.92% | >75% | ✅ Achieved |
| Test Count | 144 | >100 | ✅ Achieved |
| Assertions | 403 | >300 | ✅ Achieved |
| Execution Time | 0.3s | <1s | ✅ Achieved |

## 🔧 Writing Tests

### Test Case Template

```php
<?php

namespace Mellivora\Logger\Tests;

use PHPUnit\Framework\TestCase;
use Mellivora\Logger\Logger;
use Mellivora\Logger\LoggerFactory;

class ExampleTest extends TestCase
{
    private LoggerFactory $factory;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new LoggerFactory();
        $this->logger = $this->factory->get();
    }

    public function testBasicFunctionality(): void
    {
        // Arrange
        $message = 'Test message';
        $context = ['key' => 'value'];

        // Act
        $this->logger->info($message, $context);

        // Assert
        $this->assertTrue(true); // Add meaningful assertions
    }

    protected function tearDown(): void
    {
        // Cleanup if needed
        parent::tearDown();
    }
}
```

### Testing Best Practices

#### 1. Arrange-Act-Assert Pattern

```php
public function testLoggerCreatesCorrectRecord(): void
{
    // Arrange
    $handler = new TestHandler();
    $logger = new Logger('test', [$handler]);
    $message = 'Test message';

    // Act
    $logger->info($message);

    // Assert
    $this->assertTrue($handler->hasInfoRecords());
    $this->assertStringContainsString($message, $handler->getRecords()[0]['message']);
}
```

#### 2. Data Providers

```php
/**
 * @dataProvider logLevelProvider
 */
public function testAllLogLevels(string $level, int $expectedLevelValue): void
{
    $handler = new TestHandler();
    $logger = new Logger('test', [$handler]);

    $logger->{$level}('Test message');

    $this->assertEquals($expectedLevelValue, $handler->getRecords()[0]['level']->value);
}

public static function logLevelProvider(): array
{
    return [
        ['debug', 100],
        ['info', 200],
        ['warning', 300],
        ['error', 400],
        ['critical', 500],
    ];
}
```

#### 3. Exception Testing

```php
public function testInvalidConfigurationThrowsException(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid configuration');

    new LoggerFactory(['invalid' => 'config']);
}
```

#### 4. Mock Usage

```php
public function testHandlerIsCalledCorrectly(): void
{
    $handler = $this->createMock(HandlerInterface::class);
    $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($record) {
                return $record['message'] === 'Test message';
            }));

    $logger = new Logger('test', [$handler]);
    $logger->info('Test message');
}
```

## 🐛 Debugging Tests

### Common Issues

#### 1. Test Isolation

Ensure tests don't affect each other:

```php
protected function setUp(): void
{
    parent::setUp();
    // Reset static state
    LoggerFactory::resetInstance();
}
```

#### 2. File System Tests

Use temporary directories:

```php
private string $tempDir;

protected function setUp(): void
{
    parent::setUp();
    $this->tempDir = sys_get_temp_dir() . '/mellivora_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
}

protected function tearDown(): void
{
    if (is_dir($this->tempDir)) {
        $this->removeDirectory($this->tempDir);
    }
    parent::tearDown();
}
```

#### 3. Time-Sensitive Tests

Use fixed timestamps:

```php
public function testTimestampFormatting(): void
{
    $fixedTime = new DateTimeImmutable('2024-01-01 12:00:00');
    
    // Mock time-dependent functionality
    $this->assertEquals('2024-01-01 12:00:00', $fixedTime->format('Y-m-d H:i:s'));
}
```

## 📊 Continuous Integration

### GitHub Actions

The project uses GitHub Actions for automated testing:

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: [8.3, 8.4]
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test
```

### Coverage Reporting

Coverage reports are automatically generated and uploaded:

- **Codecov**: For coverage tracking and reporting
- **GitHub Actions**: For CI/CD integration
- **Local HTML**: For detailed local analysis

## 🎯 Test Maintenance

### Regular Tasks

1. **Update test data**: Keep test fixtures current
2. **Review coverage**: Identify untested code paths
3. **Performance monitoring**: Watch for slow tests
4. **Dependency updates**: Keep test dependencies current

### Coverage Goals

- **Minimum Line Coverage**: 85%
- **Minimum Method Coverage**: 75%
- **Critical Path Coverage**: 100%
- **New Code Coverage**: 90%

## 📚 Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Best Practices](https://phpunit.de/best-practices.html)
- [Mockery Documentation](http://docs.mockery.io/)

---

**Languages**: [English](TESTING.md) | [中文](zh-CN/TESTING.md)
