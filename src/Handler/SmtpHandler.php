<?php

namespace Mellivora\Logger\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * 该 handler 通过对 Swift_Mailer 的调用，来完成邮件的发送
 * 主要使用 Swift_SmtpTransport 进行邮件传输
 */
class SmtpHandler extends AbstractProcessingHandler
{
    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var \Swift_Message
     */
    protected $message;

    /**
     * 日志消息缓冲
     *
     * @var array
     */
    protected $records = [];

    /**
     * 当消息超过限定数量时，立即发送邮件
     *
     * @var int
     */
    protected $maxRecords = 10;

    /**
     * 服务器认证参数
     *
     * @var array
     */
    protected $certificates = [
        'host'       => '127.0.0.1', // SMTP邮件服务器
        'port'       => 25, // SMTP端口
        'username'   => null, // SMTP认证用户名
        'password'   => null, // SMTP认证密码
    ];

    /**
     * @param string       $sender       发件人地址
     * @param array|string $receivers    收件人地址或列表
     * @param string       $subject      邮件主题
     * @param array        $certificates 服务器认证参数
     * @param int          $maxRecords
     * @param int          $level
     * @param bool         $bubble
     *
     * @throws \Exception
     */
    public function __construct(
        $sender,
        $receivers,
        $subject,
        array $certificates = [],
        $maxRecords = 10,
        $level = Logger::ERROR,
        $bubble = true
    ) {
        if (! class_exists('\Swift_Mailer')) {
            throw new \Exception(
                'Require components: Swift_Mailer (ref: http://swiftmailer.org/)'
            );
        }

        parent::__construct($level, $bubble);

        // 合并默认选项
        $certificates += $this->certificates;

        // 创建 transport
        $transport = new \Swift_SmtpTransport(
            $certificates['host'],
            $certificates['port']
        );

        if (! empty($certificates['username'])) {
            $transport->setUsername($certificates['username'])
                ->setPassword($certificates['password']);
        }

        // 发件人地址
        list($fromaddr, $fromname) = $this->parseAddress($sender);

        // 创建邮件消息
        $message = new \Swift_Message($subject, null, 'text/plain', 'utf-8');
        $message->setDate(time())->setFrom($fromaddr, $fromname);

        // 设置收件人地址
        foreach ((array) $receivers as $receiver) {
            list($addr, $name) = $this->parseAddress($receiver);
            $message->addTo($addr, $name);
        }

        $this->mailer     = new \Swift_Mailer($transport);
        $this->message    = $message;
        $this->maxRecords = $maxRecords;
    }

    /**
     * 解析邮件地址，支持对以下格式进行正确解析
     * 解析结果将返回一个数组，内容分别为 [邮件地址，发/收件人名称]
     *
     * - name <my@mailhost.com>
     * - my@mailhost.com
     *
     * @param string $address
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    protected function parseAddress($address)
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

        throw new \RuntimeException("Invalid email address format '$address'");
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $this->records[] = $record;

        if (count($this->records) >= $this->maxRecords) {
            $this->send();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->send();
    }

    /**
     * 立即执行邮件发送
     */
    protected function send()
    {
        if (! empty($this->records)) {
            $this->message->setBody(
                (string) $this->getFormatter()->formatBatch($this->records)
            );
            $this->mailer->send($this->message);
        }

        $this->records = [];
    }
}
