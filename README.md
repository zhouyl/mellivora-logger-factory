# Logger Factory for Monolog [![Build Status](https://api.travis-ci.org/zhouyl/mellivora-logger-factory.svg?branch=master)](https://travis-ci.org/zhouyl/mellivora-logger-factory)

该库基于 [monolog](https://seldaek.github.io/monolog/) 进行开发，针对 `monolog` 进行了一系列的扩展，及配置增强功能。

## 1. 安装

```bash
composer require mellivora/logger-factory
```

## 2. 使用

根据配置文件实例化

```php
$factory = Mellivora\Logger\LoggerFactory::build(require 'config/logger.php');
```

`php`/`yaml`/`json`/`ini`/`xml` 格式通过 `buildWith()` 方法加载配置，需要 `hassankhan/config` 库的支持，其中 `yaml` 格式需要 `symfony/yaml` 库的支持

```php
$factory = Mellivora\Logger\LoggerFactory::buildWith('config/logger.yaml');
```

创建一个已定义的 logger

```php
$factory->get('cli');
// or
$factory['cli'];
```

根据 default 获取一个未定义的 logger

```php
$factory->get('foo');
// or
$factory['foo'];
```

复制一个 logger

```php
$factory->get('foo')->withName('bar');
```

根据 handlers 配置，创建一个 logger

```php
$factory->make('bar', ['cli', 'file']);
```

也可以把自己定义的 logger 添加到 factory 中

```php
$factory->add('orders', $logger);
$factory->get('orders')->withName('order_create'); // copy from `orders`
```

并以自定义的 logger 做为默认 logger

```php
$factory->setDefault('orders');
```

## 3. 配置

`Logger-Factory` 支持灵活的配置方式，同时在引入 `hassankhan/config` 库后，可以支持 `yaml/json/ini/xml` 等格式的配置文件，具体请参考[配置目录文件](config/)。

推荐安装 `symfony/yaml` 库后使用，因为 `yaml` 格式使得配置文件看起来更清晰可读。

### 3.1 配置文件说明

关于 `formatte` `processor` `handler` 的相关信息，请查看[monolog的帮助说明](https://seldaek.github.io/monolog/doc/02-handlers-formatters-processors.html)

**配置方法**

配置文件中，通过 `class` 指定类的全名，`params` 指定参数列表。 [参考代码内容](src/LoggerFactory.php#L329)

#### 3.1.1 formatters

用于最终输出日志消息的格式，通过对应的命名及参数设置，可以提供给后面的 `handlers` 使用。

```yaml
# formatters - 用于最终输出日志消息的格式
formatters:

  # 简单消息输出
  simple:
    class: Monolog\Formatter\LineFormatter
    params:
      format: "[%datetime%][%level_name%] %message% %context%\n"

  # 输出消息详情
  venbose:
    class: Monolog\Formatter\LineFormatter
    params:
      format: "[%datetime%][%channel%][%level_name%] %message% %context% %extra%\n"

  # JSON 格式输出，便于 ELK 收集
  json:
    class: Monolog\Formatter\JsonFormatter
```

#### 3.1.2 processors

通过加载注册的 `processor` 将会附加在消息的 extra 字段中。

在 `Logger-Factory` 中，增加了下面几种 `processor` 来增强日志的附属信息:

-  [CostTimeProcessor](src/Processor/CostTimeProcessor.php) - 用于获取时间成本消耗，会记录上次写日志到当前写日志消耗的时间
-  [ProfilerProcessor](src/Processor/ProfilerProcessor.php) - 可获取时间成本消耗、内存消耗数据
-  [ScriptProcessor](src/Processor/ScriptProcessor.php) - 可用于 cli 模式下，获取脚本命令的完整信息
-  [WebProcessor](src/Processor/WebProcessor.php) - 可用于 http 请求下，获取 web 的部分 header 信息

```yaml
# processors - 注册的 processor 将会附加在消息的 extra 字段中
processors:
  # 用于日志输出所在 的 file, line, class, method, ...
  intro:
    class: Monolog\Processor\IntrospectionProcessor
    params:
      level: ERROR
      skipStackFramesCount: 2

  # 用于捕获 http web 请求头信息
  web:
    class: Mellivora\Logger\Processor\WebProcessor
    params:
      level: ERROR

  # 用于捕获脚本运行信息
  script:
    class: Mellivora\Logger\Processor\ScriptProcessor
    params:
      level: ERROR

  # 用于性能分析
  profiler:
    class: Mellivora\Logger\Processor\ProfilerProcessor
    params:
      level: DEBUG
```

#### 3.1.3 handlers

通过拼装 `formatter`/`processor`，用于日志输出方式的设定。

在 `Logger-Factory` 中，增加了下面几种 `handler` 来进行日志存储:

- [NamedRotatingFileHandler](src/Handler/NamedRotatingFileHandler.php) - 支持日志自动分片、缓冲写入、根据 channel 调整文件名的功能，支持以下参数设定:
    - `filename` - 日志文件名，可使用 %channel% 来动态生成文件名
    - `maxBytes` - 单个日志文件最大尺寸，默认值 100000000 (100mb)，0为不限制
    - `backupCount` - 保留的日志备份文件数量，默认值 10，0为不保留备份
    - `bufferSize` - 缓冲区日志数大小，默认值 0，不开启
    - `dateFormat` - 备份日志文件的日期格式，默认 Y-m-d
    - `level` - 日志级别过滤，默认 debug 级别
    - `bubble` - 设置为 false 日志将被过滤，默认 true
    - `filePermission` - 文件权限，默认 0644
    - `useLocking` - 是否对日志文件加锁，默认 false
- [SmtpHandler](src/Handler/SmtpHandler.php) - 通过加载 `swiftmailer`，来支持方便快捷的邮件发送配置，支持以下参数设定:
    - `sender` - 发件人，支持两种格式 `john@mailhost.com` `John <john@mailhost.com>`
    - `receivers` - 收件人，数组或字符类型，格式同发件人
    - `subject` - 邮件主题
    - `certificates` - 服务器认证信息，包含 [host,port,username,password]
    - `maxRecords` - 最大记录数，达到该数量后将立即发送邮件，默认为 10
    - `level`
    - `bubble`

```yaml
# handlers - 通过拼装 formatter/processor，用于日志输出方式的设定
handlers:

  # 文件输出，使用 JSON 格式，方便 ELK 收集
  file:
    class: Mellivora\Logger\Handler\NamedRotatingFileHandler
    params:
      filename: "logs/%channel%.log"
      maxBytes: 100000000 # 100Mb，文件最大尺寸
      backupCount: 10 # 文件保留数量
      bufferSize: 10 # 缓冲区大小(日志数量)
      level: INFO
    formatter: json
    processors: [intro, web, script, profiler]

  # 命令行模式，输出简单日志内容
  cli:
    class: Monolog\Handler\StreamHandler
    params:
      stream: php://stdout
      level: DEBUG
    formatter: simple

  # 邮件预警，仅报告致命错误 CRITICAL
  mail:
    class: Mellivora\Logger\Handler\SmtpHandler
    params:
      sender: logger-factory <sender@mailhost.com>
      receivers:
        - receiver <receiver@mailhost.com>
      subject: "[ERROR] FROM Logger-Factory"
      certificates:
        host: smtp.mailhost.com
        port: 25
        username: sender@mailhost.com
        password: sender-passwd
      maxRecords: 10
      level: CRITICAL
    formatter: venbose
    processors: [intro, web, script, profiler]
```

#### 3.1.4 loggers

当声明的 logger 不在以下列表中时，默认为 default。参考默认配置:

```yaml
# loggers -  当声明的 logger 不在以下列表中时，默认为 default
loggers:

  # 默认日志
  default: [file, mail]

  # 命令行模式
  cli: [cli, file, mail]

  # 异常处理
  exception: [file, mail]
```

## 4. 过滤器的使用

`Mellivora\Logger\Logger` 新增了 `filter` 过滤器的支持。

对日志消息进行替换

```php
$logger->addFilter(function($level, &$message, &$context) {
    if (isset($context['password'])) {
        $context['password'] = '******';
    }

    $message = preg_replace('/password=[^&]+/', 'password=******', $message);

    return true;
});
```

对日志消息进行过滤

```php
$logger->addFilter(function($level, $message, $context) {
    return !isset($context['password']);
});
```

自定义过滤器类

```php
class PasswordFilter
{
    public function __invoke($level, $message, $context)
    {
        // ...
    }
}
$logger->addFilter(new PasswordFilter);
```

## 5. 日志路径支持

`Mellivora\Logger\LoggerFactory` 提供了关于项目根目录设置的方法，用于来协助日志文件目录定位。

```php
Mellivora\Logger\LoggerFactory::setRootPath('/your/application');
```

在 `Mellivora\Logger\Handler\NamedRotatingFileHandler` 中，采用了如下方法来获取日志目录

```php
$filename = LoggerFactory::getRootPath() . '/' . $filename;
```

你也可以利用 `LoggerFactory::getRootPath()` 来定义自己的日志 handler，协助方便的指定日志路径。

## License

The MIT License (MIT). Please see License File for more information.
