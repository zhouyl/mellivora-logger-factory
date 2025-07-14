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
 * SMTP Email Handler.
 *
 * Sends log emails via Symfony Mailer with SMTP protocol support.
 * Automatically sends email notifications when log record count reaches specified threshold.
 *
 * Features:
 * - Support for batch sending (sends when specified record count is reached)
 * - Support for SMTP authentication
 * - Automatic log content formatting
 * - Support for multiple recipients
 */
class SmtpHandler extends AbstractProcessingHandler
{
    /**
     * Symfony Mailer instance.
     */
    protected Mailer $mailer;

    /**
     * Log message buffer.
     *
     * @var array<LogRecord>
     */
    protected array $records = [];

    /**
     * Maximum number of records that triggers email sending.
     */
    protected int $maxRecords = 10;

    /**
     * SMTP server configuration.
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
     * Sender email address
     */
    protected string $sender;

    /**
     * List of recipient email addresses.
     *
     * @var array<string>
     */
    protected array $receivers;

    /**
     * Email subject.
     */
    protected string $subject;

    /**
     * Constructor.
     *
     * @param string $sender Sender email address, format: email or "Name <email>"
     * @param array<string>|string $receivers Recipient address or address list
     * @param string $subject Email subject
     * @param array{
     *     host?: string,
     *     port?: int,
     *     username?: string,
     *     password?: string
     * } $certificates SMTP server configuration
     * @param int $maxRecords Maximum number of records that triggers email sending
     * @param int|Level $level Minimum log level
     * @param bool $bubble Whether to bubble log records up
     *
     * @throws Exception When Symfony Mailer component is not available
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

        // Create Mailer instance
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);

        // Store configuration
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
     * Parse email address, supports correct parsing of the following formats.
     * The parsing result will return an array containing [email address, sender/recipient name].
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
        // Recognize "name <my@mailhost.com>" format
        preg_match('/^(.+)<([a-z0-9][\w\.\-]+@[\w\-]+(\.\w+)+)>$/i', $address, $matches);

        if (count($matches) === 4) {
            return [trim($matches[2]), trim($matches[1])];
        }

        // Validate if it's a valid email format
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
