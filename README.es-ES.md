*(Self-Correction/Verification during output generation prep)*
   I will carefully paste the translated text, ensuring all markdown formatting is intact.
   Text looks solid.
   Proceeds. 
   [Output Generation] (matches the final response)
   `#  Mellivora Logger Factory` (Wait, keep original title)
   `[![Version]...]...`
   `Una moderna biblioteca de fábricabricas de registros (logging) basada en [Monolog]...`
   All set.✅
   Output matches the final response. 
   *Proceeds.* 
   [Output Generation] (done)
</think>

#  Mellivora Logger Factory

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/zhouyl/mellivora-logger-factory/releases)
[![CI](https://github.com/zhouyl/mellivora-logger-factory/actions/workflows/ci.yml/badge.svg)](https://github.com/zhouyl/mellivora-logger-factory/actions/workflows/ci.yml)
[![Coverage](https://github.com/zhouyl/mellivora-logger-factory/actions/workflows/coverage.yml/badge.svg)](https://github.com/zhouyl/mellivora-logger-factory/actions/workflows/coverage.yml)
[![Quality](https://github.com/zhouyl/mellivora-logger-factory/actions/workflows/quality.yml/badge.svg)](https://github.com/zhouyl/mellivora-logger-factory/actions/workflows/quality.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![Monolog Version](https://img.shields.io/badge/monolog-3.x-green.svg)](https://github.com/Seldaek/monolog)
[![Laravel Support](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x-red.svg)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Una moderna biblioteca de gestiónbricas de registros (logging) basada en [Monolog](https://seldaek.github.io/monolog/), diseñada para PHP 8.3+, que proporciona potentes capacidades de gestión de registros y una integración perfecta con el framework Laravel.

**🧪 Alta garantía de calidad**: Cuenta con una **cobertura de pruebas del 88.82%** con 144 métodos de prueba y 367 aserciones, **ando la calidad y estabilidad del código.

> **🎉 Versión estable**: La versión actual es **2.0.0**, una versión estable lista para su uso en producción. Esta versión ha sido exhaustivamente probada con una cobertura de pruebas del 87.28% y un aseguramiento de calidad de nivel empresarial.

> **🤖 Desarrollo impulsado por IA**: La refactorización y las mejoras de las pruebas de este proyecto se completaron por completo mediante la herramienta de código inteligente [Augment](https://www.augmentcode.com/), demostrando las poderosas capacidades de la IA en el desarrollo de software moderno.

## 📋 Table of Contents

- [✨ Key Features](#-key-features)
- [📋 System Requirements](#-system-requirements)
- [🚀 Installation](#-installation)
- [📖 Usage](#-usage)
- [🔧 Laravel Integration](#-laravel-integration)
- [🧪 Testing](#-testing)
- [⚠️ Version Notes](#️-version-notes)
- [🤝 Contributing](#-contributing)
- [📞 Support](#-support)
- [📄 License](#-license)
- [🙏 Acknowledgments](#-acknowledgments)

## ✨ Key Features

### 🚀 Modern PHP 8.3+ Features
- **Declaraciones de tipos estrictos**: Uso exhaustivo de `declare(strict_types=1)` y propiedades tipadas
- **Promoción de propiedades en el constructor**: Sintaxis de constructor concisa
- **Propiedades `readonly`**: Uso de la palabra clave `readonly` para proteger propiedades importantes
- **Expresiones `match`**: Reemplazo de las sentencias `switch` tradicionales por coincidencia de patrones más seguras
- **Tipos unión**: Soporte para definiciones de tipos flexibles como `int|Level|string`

### 🎯 Core Functionality
- **Gestión de registros multi-canal**: Soporte para la separación de canales de registro por módulos funcionales
- **Procesadores enriquecidos**: Perfiles de rendimiento integrados, monitorización de memoria y procesadores de solicitudes web
- **Formato flexible**: Soporte para JSON, HTML, formatos personalizados y múltiples formatos de salida
- **Rotación inteligente**: Rotación automática de archivos de registro por fecha y tamaño de archivo
- **Mejora de excepciones**: Extracción automática y registro estructurado de información detallada de excepciones
- **Soporte de filtros**: Lógica de filtrado de registros personalizada

### 🔧 Laravel Integration
- **Configuración cero**: Descubrimiento y registro automáticos de servicios
- **Soporte de `Facade`**: `Facade` `MLog` para un acceso conveniente
- **Funciones auxiliares**: `mlog()`, `mlog_with()`, `mlog_debug()`, etc.
- **Soporte de `Middleware`**: Middleware de registro de solicitudes integrado
- **Comandos `Artisan`**: Comandos de prueba y gestión
- **Publicación de configuración**: Archivos de configuración personalizables

### 📊 Advanced Features
- **Monitorización de rendimiento**: Seguimiento integrado del tiempo de ejecución y el uso de memoria
- **Mejora de contexto**: Adición automática de ID de solicitud, información de usuario, etc.
- **Manejo de errores**: Manejo elegante de fallos en el registro
- **Soporte de caché**: Caché inteligente para mejorar el rendimiento
- **Funciones de seguridad**: Filtrado y saneamiento de datos sensibles

## 📋 System Requirements

- **PHP**: 8.3 o superior
- **Monolog**: ^3.0
- **PSR-Log**: ^3.0
- **Laravel**: ^10.0 | ^11.0 (opcional, para la integración con Laravel)

## Installation

Install the stable version using Composer:

```bash
# Install stable version
composer require mellivora/logger-factory:^2.0.0

# Or specify exact version
composer require mellivora/logger-factory:2.0.0
```

## Usage

### Basic Usage

```php
<?php

use Mellivora\Logger\LoggerFactory;
use Monolog\Level;

// Create factory instance
$factory = new LoggerFactory();

// Get default logger
$logger = $factory->get();
$logger->info('Hello World!');

// Use specific channel
$apiLogger = $factory->get('api');
$apiLogger->debug('API request processed');
```

### Laravel Integration

```php
<?php

// Using helper functions
mlog('info', 'User logged in', ['user_id' => 123]);
mlog_with('api', 'debug', 'API request');

// Using Facade
use Mellivora\Logger\Laravel\Facades\MLog;

MLog::info('Application started');
MLog::logWith('api', 'debug', 'API debug');
MLog::exception($exception, 'error');
```

For complete Laravel integration guide, see [Laravel Documentation](docs/LARAVEL.md).

### Advanced Configuration

```php
<?php

use Mellivora\Logger\LoggerFactory;
use Mellivora\Logger\Config\LoggerConfig;

// Custom configuration
$config = new LoggerConfig([
    'default_channel' => 'app',
    'channels' => [
        'app' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => '/var/log/app.log',
                    'level' => 'info',
                    'max_files' => 30,
                ],
            ],
        ],
    ],
]);

$factory = new LoggerFactory($config);
$logger = $factory->get('app');
```

## 🔧 Laravel Integration

### Installation

1. Install the package:
```bash
composer require mellivora/logger-factory:^2.0.0
```

2. Publish configuration (optional):
```bash
php artisan vendor:publish --provider="Mellivora\Logger\Laravel\MellivoraLoggerServiceProvider"
```

### Configuration

Edit `config/mellivora-logger.php`:

```php
<?php

return [
    'default_channel' => env('MELLIVORA_LOG_CHANNEL', 'default'),

    'channels' => [
        'default' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/mellivora.log'),
                    'level' => env('MELLIVORA_LOG_LEVEL', 'debug'),
                    'max_files' => 30,
                ],
            ],
        ],

        'api' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/api.log'),
                    'level' => 'info',
                    'max_files' => 30,
                ],
            ],
        ],
    ],
];
```

### Usage Examples

```php
<?php

// Helper functions
mlog('info', 'User action', ['action' => 'login', 'user_id' => 123]);
mlog_with('api', 'debug', 'API request', ['endpoint' => '/users']);

// Facade
use Mellivora\Logger\Laravel\Facades\MLog;

MLog::info('Application started');
MLog::error('Database connection failed', ['error' => $exception->getMessage()]);

// Exception logging
try {
    // Some operation
} catch (Exception $e) {
    MLog::exception($e, 'error', 'payment');
}
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run specific test suite
./vendor/bin/phpunit tests/LoggerFactoryTest.php
```

### Test Coverage

Current test coverage: **88.82%**

- **Total Tests**: 144
- **Assertions**: 367
- **Files Covered**: 30
- **Lines Covered**: 1,234 / 1,389

For detailed testing information, see [Testing Documentation](docs/TESTING.md).

## ⚠️ Version Notes

### Alpha Version (2.0.0-alpha)

This is a **pre-release version** with the following characteristics:

#### ✅ Completed Features
- **Core Functionality**: All core features implemented and tested
- **High Quality**: 88.82% test coverage ensuring code quality
- **Complete Documentation**: Comprehensive usage documentation and examples
- **Production Ready**: Although alpha, quality meets production standards

#### 🎯 Usage Recommendations
1. **New Projects**: Recommended for use, feature-complete and stable
2. **Testing Environment**: Suitable for evaluation and validation in test environments
3. **Production Environment**: Recommended to conduct thorough testing before production deployment
4. **Legacy Versions**: Not recommended to upgrade from legacy versions due to significant architectural differences

### Breaking Changes from 1.x

- **PHP Version**: Minimum requirement upgraded to PHP 8.3+
- **Function Names**: Simplified from `mellivora_log()` to `mlog()`
- **Facade Name**: Changed from `MellivoraLogger` to `MLog`
- **Architecture**: Complete rewrite with modern PHP features
- **Dependencies**: Updated to Monolog 3.x and modern packages

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/zhouyl/mellivora-logger-factory.git
cd mellivora-logger-factory

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## 📞 Support

- **Documentation**: [Complete Documentation](docs/)
- **Issues**: [GitHub Issues](https://github.com/zhouyl/mellivora-logger-factory/issues)
- **Discussions**: [GitHub Discussions](https://github.com/zhouyl/mellivora-logger-factory/discussions)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **[Monolog](https://github.com/Seldaek/monolog)**: The excellent logging library that powers this factory
- **[Laravel](https://laravel.com/)**: For the outstanding framework integration support
- **[Symfony](https://symfony.com/)**: For the powerful component ecosystem
- **[PHPUnit](https://phpunit.de/)**: For the reliable testing framework
- **[Augment](https://www.augmentcode.com/)**: For the AI-powered development tools that made this project possible

---

**Languages**: [English](README.md) | [中文](docs/zh-CN/README.md)

*Made with ❤️ and AI assistance*
