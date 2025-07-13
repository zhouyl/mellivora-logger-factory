# PHP 8.3+ 升级指南

本文档记录了将 mellivora-logger-factory 从 PHP 5.6 升级到 PHP 8.3+ 的过程和变更。

**本次升级完全由智能编程 AI 工具 Augment 完成！**

## 升级概述

### 系统要求变更

**之前:**
- PHP >= 5.6.0
- Monolog ~1.23
- PSR-Log ~1.0
- PHPUnit ~5.0
- PHP-CS-Fixer ~2.0
- SwiftMailer ~5.4.9
- hassankhan/config ^2.0 (支持 JSON/YAML 配置)

**现在:**
- PHP >= 8.3.0
- Monolog ^3.0
- PSR-Log ^3.0
- PHPUnit ^11.0
- PHP-CS-Fixer ^3.0
- Symfony Mailer ^7.0 (替代 SwiftMailer)
- 移除外部配置库依赖，仅支持 PHP 配置文件

## 主要变更

### 1. 依赖升级

- **Monolog**: 从 1.x 升级到 3.x
- **PSR-Log**: 从 1.x 升级到 3.x
- **PHPUnit**: 从 5.x 升级到 11.x
- **PHP-CS-Fixer**: 从 2.x 升级到 3.x
- **SwiftMailer**: 替换为 Symfony Mailer 7.x
- **hassankhan/config**: 完全移除，不再支持 JSON/YAML 配置文件

### 2. PHP 8.3 新特性应用

#### 构造函数属性提升
```php
// 之前
class CostTimeProcessor
{
    protected Level $level;

    public function __construct($level = Level::Debug)
    {
        $this->level = $level instanceof Level ? $level : Level::fromName($level);
    }
}

// 现在
class CostTimeProcessor
{
    public function __construct(
        protected readonly Level $level = Level::Debug
    ) {
    }
}
```

#### Match 表达式
```php
// 之前
public function setLevel($level)
{
    if ($level instanceof Level) {
        $this->level = $level;
    } elseif (is_string($level)) {
        $this->level = Level::fromName($level);
    } elseif (is_int($level)) {
        $this->level = Level::fromValue($level);
    } else {
        throw new \InvalidArgumentException('Invalid level type');
    }
}

// 现在
public function setLevel(int|Level|string $level): self
{
    $this->level = match (true) {
        $level instanceof Level => $level,
        is_string($level)       => Level::fromName($level),
        is_int($level)          => Level::fromValue($level),
        default                 => throw new \InvalidArgumentException('Invalid level type'),
    };
}
```

#### 类型化属性和只读属性
```php
// 之前
protected $formatters = [];
protected $processors = [];

// 现在
protected array $formatters = [];
protected readonly Level $level;
```

### 3. PHP 语法更新

#### 类型声明
- 添加了严格的类型声明
- 更新了方法返回类型
- 修复了 nullable 参数声明
- 使用联合类型 (int|Level|string)

#### ArrayAccess 接口
```php
// 之前
public function offsetSet($channel, $logger)
public function offsetGet($channel)
public function offsetExists($channel)
public function offsetUnset($channel)

// 现在
public function offsetSet(mixed $channel, mixed $value): void
public function offsetGet(mixed $channel): mixed
public function offsetExists(mixed $channel): bool
public function offsetUnset(mixed $channel): void
```

#### 测试方法
```php
// 之前
protected function setUp()

// 现在
protected function setUp(): void
```

### 3. Monolog 3.x 兼容性

#### Level 枚举
```php
// 之前
use Monolog\Logger;
$level = Logger::INFO;

// 现在
use Monolog\Level;
$level = Level::Info;
```

#### LogRecord 对象
```php
// 之前
public function __invoke(array $record)
{
    $record['extra']['data'] = $value;
    return $record;
}

// 现在
public function __invoke(LogRecord $record): LogRecord
{
    $record->extra['data'] = $value;
    return $record;
}
```

### 4. 配置文件变更

#### 移除外部配置库支持
```php
// 之前 - 支持多种格式
LoggerFactory::buildWith('config/logger.json');  // ❌ 不再支持
LoggerFactory::buildWith('config/logger.yaml'); // ❌ 不再支持
LoggerFactory::buildWith('config/logger.ini');  // ❌ 不再支持

// 现在 - 仅支持 PHP 配置文件
LoggerFactory::buildWith('config/logger.php');  // ✅ 仅支持此格式
```

#### 配置文件验证
```php
// 现在会进行严格验证
public static function buildWith(string $configFile): self
{
    if (!file_exists($configFile)) {
        throw new \InvalidArgumentException("Configuration file not found: {$configFile}");
    }

    if (!str_ends_with($configFile, '.php')) {
        throw new \InvalidArgumentException("Only PHP configuration files are supported: {$configFile}");
    }

    $config = require $configFile;

    if (!is_array($config)) {
        throw new \InvalidArgumentException("Configuration file must return an array: {$configFile}");
    }

    return self::build($config);
}
```

### 5. SwiftMailer 到 Symfony Mailer

#### 邮件发送
```php
// 之前 (SwiftMailer)
$transport = new \Swift_SmtpTransport($host, $port);
$mailer = new \Swift_Mailer($transport);
$message = new \Swift_Message($subject);

// 现在 (Symfony Mailer)
$dsn = sprintf('smtp://%s:%s@%s:%d', $username, $password, $host, $port);
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);
$email = new Email();
```

### 5. 配置文件更新

#### PHPUnit 配置
- 更新到 PHPUnit 11.x 格式
- 使用新的 XML schema
- 更新覆盖率配置

#### PHP-CS-Fixer 配置
- 重命名配置文件: `.php_cs.dist` → `.php-cs-fixer.dist.php`
- 更新已弃用的规则名称
- 修复配置语法

### 6. CI/CD 更新

#### GitHub Actions
- 添加了新的 GitHub Actions 工作流
- 支持 PHP 8.1-8.4 版本测试
- 替代了 Travis CI

#### Travis CI
- 更新支持的 PHP 版本到 8.1-8.4

## 破坏性变更

### 1. 最低 PHP 版本要求
- 从 PHP 5.6 提升到 PHP 8.3

### 2. 配置文件格式限制
- 不再支持 JSON、YAML、INI、XML 配置文件
- 仅支持 PHP 配置文件 (.php)
- 移除了 hassankhan/config 依赖

### 3. 方法签名变更
- 所有 Processor 的 `__invoke` 方法现在接受 `LogRecord` 对象
- Handler 的 `handle` 和 `write` 方法签名已更新
- 构造函数使用属性提升，参数顺序可能有变化

### 4. 常量变更
- Monolog 日志级别常量已替换为 Level 枚举

### 5. 属性访问变更
- 许多属性现在是只读的 (readonly)
- 类型化属性要求严格的类型匹配

## 迁移指南

### 对于库的使用者

1. **更新 PHP 版本**: 确保使用 PHP 8.3 或更高版本
2. **更新 composer.json**:
   ```json
   {
       "require": {
           "mellivora/logger-factory": "^3.0"
       }
   }
   ```
3. **转换配置文件格式**:
   ```php
   // 删除 JSON/YAML 配置文件
   rm config/logger.json config/logger.yaml

   // 仅使用 PHP 配置文件
   // config/logger.php
   return [
       'default' => 'app',
       'formatters' => [...],
       'processors' => [...],
       'handlers' => [...],
       'loggers' => [...],
   ];
   ```
4. **更新代码中的日志级别引用**:
   ```php
   // 之前
   use Monolog\Logger;
   $logger->setLevel(Logger::INFO);

   // 现在
   use Monolog\Level;
   $logger->setLevel(Level::Info);
   ```

### 对于自定义 Processor

如果您有自定义的 Processor，需要更新 `__invoke` 方法：

```php
// 之前
public function __invoke(array $record)
{
    $record['extra']['custom'] = 'value';
    return $record;
}

// 现在
use Monolog\LogRecord;

public function __invoke(LogRecord $record): LogRecord
{
    $record->extra['custom'] = 'value';
    return $record;
}
```

## 测试

升级后的代码已通过以下测试：

- ✅ 所有单元测试通过 (PHPUnit 11.x)
- ✅ 代码风格检查通过 (PHP-CS-Fixer 3.x)
- ✅ PHP 8.4 兼容性测试通过
- ✅ 所有核心功能正常工作

## 注意事项

1. **向后兼容性**: 此升级包含破坏性变更，不向后兼容 PHP 5.6-7.x
2. **依赖更新**: 所有主要依赖都已更新到最新稳定版本
3. **性能提升**: 得益于 PHP 8.x 的性能改进和新特性
4. **安全性**: 使用最新版本的依赖提供了更好的安全性

## 支持

如果在升级过程中遇到问题，请：

1. 检查 PHP 版本是否 >= 8.1
2. 确保所有依赖都已正确更新
3. 查看此升级指南中的迁移说明
4. 在 GitHub 上提交 issue
