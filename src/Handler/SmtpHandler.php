<?php

declare(strict_types=1);

namespace Mellivora\Logger\Handler;

use Exception;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/**
 * SMTP 邮件处理器.
 *
 * 通过 Symfony Mailer 发送日志邮件，支持 SMTP 协议。
 * 当日志记录数量达到指定阈值时，会自动发送邮件通知。
 *
 * 特性：
 * - 支持批量发送（达到指定记录数时发送）
 * - 支持 SMTP 认证
 * - 自动格式化日志内容
 * - 支持多个收件人
 */
class SmtpHandler extends AbstractProcessingHandler
{
    /**
     * Symfony Mailer 实例.
     */
    protected Mailer $mailer;

    /**
     * 日志消息缓冲.
     *
     * @var array<LogRecord>
     */
    protected array $records = [];

    /**
     * 触发邮件发送的最大记录数.
     */
    protected int $maxRecords = 10;

    /**
     * SMTP 服务器配置.
     *
     * @var array{host: string, port: int, username: null|string, password: null|string}
     */
    protected array $certificates = [
        'host' => '127.0.0.1',
        'port' => 25,
        'username' => null,
        'password' => null,
    ];

    /**
     * 发件人地址
     */
    protected string $sender;

    /**
     * 收件人地址列表.
     *
     * @var array<string>
     */
    protected array $receivers;

    /**
     * 邮件主题.
     */
    protected string $subject;

    /**
     * 构造函数.
     *
     * @param string $sender 发件人地址，格式：email 或 "Name <email>"
     * @param array<string>|string $receivers 收件人地址或地址列表
     * @param string $subject 邮件主题
     * @param array{
     *     host?: string,
     *     port?: int,
     *     username?: string,
     *     password?: string
     * } $certificates SMTP 服务器配置
     * @param int $maxRecords 触发邮件发送的最大记录数
     * @param int|Level $level 最低日志级别
     * @param bool $bubble 是否向上传递日志记录
     *
     * @throws Exception 当 Symfony Mailer 组件不存在时抛出异常
     */
    public function __construct(
        string $sender,
        array|string $receivers,
        string $subject,
        array $certificates = [],
        int $maxRecords = 10,
        int|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        if (!class_exists(Mailer::class)) {
            throw new Exception(
                'Require components: Symfony Mailer ' .
                '(ref: https://symfony.com/doc/current/mailer.html)',
            );
        }

        parent::__construct($level, $bubble);

        // 合并配置
        $certificates = array_merge($this->certificates, $certificates);

        // 构建 DSN
        $auth = '';
        if (!empty($certificates['username'])) {
            $auth = urlencode($certificates['username']) . ':' .
                    urlencode($certificates['password'] ?? '') . '@';
        }

        $dsn = sprintf(
            'smtp://%s%s:%d',
            $auth,
            $certificates['host'],
            $certificates['port'],
        );

        // 创建 Mailer 实例
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);

        // 存储配置
        $this->sender = $sender;
        $this->receivers = is_array($receivers) ? $receivers : [$receivers];
        $this->subject = $subject;
        $this->maxRecords = $maxRecords;
    }

    public function close(): void
    {
        $this->send();
    }

    /**
     * 解析邮件地址，支持对以下格式进行正确解析
     * 解析结果将返回一个数组，内容分别为 [邮件地址，发/收件人名称].
     *
     * - name <my@mailhost.com>
     * - my@mailhost.com
     *
     * @param string $address
     *
     * @return array
     *
     * @codeCoverageIgnore
     *
     * @throws RuntimeException
     */
    protected function parseAddress(string $address): array
    {
        // 识别 "name <my@mailhost.com>" 类似的格式
        preg_match('/^(.+)<([a-z0-9][\w\.\-]+@[\w\-]+(\.\w+)+)>$/i', $address, $matches);

        if (count($matches) === 4) {
            return [trim($matches[2]), trim($matches[1])];
        }

        // 验证是否有效的 email 格式
        $pattern = '/^
            [-_a-z0-9\'+*$^&%=~!?{}]++
            (?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+
            @
            (
                ?:(?![-.])
                [-a-z0-9.]+
                (?<![-.])
                \.[a-z]{2,6}|\d{1,3}
                (?:\.\d{1,3}){3}
            )$/ixD';

        if (preg_match($pattern, $address)) {
            return [$address, strstr($address, '@', true)];
        }

        throw new RuntimeException("Invalid email address format '$address'");
    }

    protected function write(LogRecord $record): void
    {
        $this->records[] = $record;

        if (count($this->records) >= $this->maxRecords) {
            $this->send();
        }
    }

    /**
     * 立即执行邮件发送
     *
     * @codeCoverageIgnore
     */
    protected function send(): void
    {
        if (!empty($this->records)) {
            // 解析发件人地址
            [$fromaddr, $fromname] = $this->parseAddress($this->sender);

            // 创建邮件消息
            $email = (new Email())
                ->from($fromname ? "$fromname <$fromaddr>" : $fromaddr)
                ->subject($this->subject)
                ->text((string) $this->getFormatter()->formatBatch($this->records));

            // 设置收件人地址
            foreach ($this->receivers as $receiver) {
                [$addr, $name] = $this->parseAddress($receiver);
                $email->addTo($name ? "$name <$addr>" : $addr);
            }

            $this->mailer->send($email);
        }

        $this->records = [];
    }
}
