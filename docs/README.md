# Documentation Index

Welcome to the Mellivora Logger Factory documentation center!

## 📚 Documentation List

### Core Documentation
- **[README.md](../README.md)** - Main project documentation with complete feature introduction and usage guide
- **[TESTING.md](TESTING.md)** - Testing documentation with coverage analysis and testing guide
- **[LARAVEL.md](LARAVEL.md)** - Laravel integration guide with detailed framework integration instructions
- **[UPGRADE.md](../UPGRADE.md)** - Upgrade guide from legacy versions to PHP 8.3+

### Configuration Files
- **[config/logger.php](../config/logger.php)** - Basic configuration example
- **[config/mellivora-logger.php](../config/mellivora-logger.php)** - Laravel configuration file

### Example Code
- **[examples/](../examples/)** - Usage example code

## 🎯 Quick Navigation

### New Users
1. Read [README.md](../README.md) to understand the project overview
2. Check [System Requirements](../README.md#-system-requirements) to confirm environment compatibility
3. Follow [Installation](../README.md#installation) for setup and configuration

### Laravel Users
1. Read [Laravel Integration Guide](LARAVEL.md)
2. Follow the guide for installation and configuration
3. Check example code to understand best practices

### Developers
1. Read [Testing Documentation](TESTING.md) to understand the test suite
2. Check [Upgrade Guide](../UPGRADE.md) for migration information
3. Review configuration examples for advanced usage

## 📖 Documentation Structure

```
docs/
├── README.md                 # This documentation index
├── LARAVEL.md               # Laravel integration guide
├── TESTING.md               # Testing documentation
├── zh-CN/                   # Chinese documentation
│   ├── README.md           # Chinese project documentation
│   ├── LARAVEL.md          # Chinese Laravel guide
│   ├── TESTING.md          # Chinese testing documentation
│   └── UPGRADE.md          # Chinese upgrade guide
└── examples/                # Code examples
```

## 🌐 Language Support

This project provides documentation in multiple languages:

- **English**: Primary documentation language
- **中文 (Chinese)**: Complete Chinese translation available in `zh-CN/` directory

### Language Navigation
- **English Documentation**: Current directory
- **中文文档**: [zh-CN/](zh-CN/) directory

## 🚀 Getting Started

### Quick Start
1. **Installation**: Follow the [installation guide](../README.md#installation)
2. **Basic Usage**: Check [usage examples](../README.md#usage)
3. **Laravel Integration**: See [Laravel guide](LARAVEL.md) for framework integration

### Advanced Topics
- **Custom Handlers**: Learn about creating custom log handlers
- **Processors**: Understand log processors and context enhancement
- **Performance**: Optimize logging performance for production
- **Testing**: Write tests for your logging implementation

## 🔧 Configuration

### Basic Configuration
```php
<?php
use Mellivora\Logger\LoggerFactory;
use Mellivora\Logger\Config\LoggerConfig;

$config = new LoggerConfig([
    'default_channel' => 'app',
    'channels' => [
        'app' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => '/var/log/app.log',
                    'level' => 'info',
                ],
            ],
        ],
    ],
]);

$factory = new LoggerFactory($config);
```

### Laravel Configuration
```php
<?php
// config/mellivora-logger.php
return [
    'default_channel' => env('MELLIVORA_LOG_CHANNEL', 'default'),
    'channels' => [
        'default' => [
            'handlers' => [
                [
                    'type' => 'rotating_file',
                    'path' => storage_path('logs/mellivora.log'),
                    'level' => env('MELLIVORA_LOG_LEVEL', 'debug'),
                ],
            ],
        ],
    ],
];
```

## 🧪 Testing

The project maintains high test coverage:
- **Line Coverage**: 88.82%
- **Test Methods**: 144
- **Assertions**: 403

For detailed testing information, see [Testing Documentation](TESTING.md).

## 📞 Support

### Getting Help
- **Documentation**: Browse this documentation for comprehensive guides
- **Issues**: Report bugs or request features on [GitHub Issues](https://github.com/zhouyl/mellivora-logger-factory/issues)
- **Discussions**: Join community discussions on [GitHub Discussions](https://github.com/zhouyl/mellivora-logger-factory/discussions)

### Contributing
We welcome contributions! Please see our [Contributing Guide](../CONTRIBUTING.md) for details on how to contribute to the project.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](../LICENSE) file for details.

---

**Languages**: [English](README.md) | [中文](zh-CN/README_DOCS.md)
