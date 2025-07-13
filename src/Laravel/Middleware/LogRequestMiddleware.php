<?php

declare(strict_types=1);

namespace Mellivora\Logger\Laravel\Middleware;

use Illuminate\Http\Request;
use Mellivora\Logger\Laravel\Facades\MLog;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 请求日志中间件.
 *
 * 自动记录 HTTP 请求和响应的详细信息
 */
class LogRequestMiddleware
{
    /**
     * 处理传入的请求
     *
     * @param Request $request HTTP 请求对象
     * @param \Closure $next 下一个中间件
     * @param null|string $channel 日志通道名称
     * @param null|string $level 日志级别
     *
     * @return SymfonyResponse HTTP 响应对象
     */
    public function handle(
        Request $request,
        \Closure $next,
        ?string $channel = null,
        ?string $level = 'info',
    ): SymfonyResponse {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // 记录请求开始
        $this->logRequest($request, $channel, $level);

        // 处理请求
        $response = $next($request);

        // 计算处理时间和内存使用
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // 毫秒
        $memoryUsed = $endMemory - $startMemory;

        // 记录响应
        $this->logResponse($request, $response, $duration, $memoryUsed, $channel, $level);

        return $response;
    }

    /**
     * 记录请求信息.
     *
     * @param Request $request HTTP 请求对象
     * @param null|string $channel 日志通道名称
     * @param string $level 日志级别
     */
    protected function logRequest(Request $request, ?string $channel, string $level): void
    {
        $context = [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'query' => $request->query->all(),
            'body' => $this->filterRequestBody($request),
        ];

        MellivoraLogger::log(
            $level,
            "HTTP Request: {$request->getMethod()} {$request->getPathInfo()}",
            $context,
            $channel,
        );
    }

    /**
     * 记录响应信息.
     *
     * @param Request $request HTTP 请求对象
     * @param SymfonyResponse $response HTTP 响应对象
     * @param float $duration 处理时间（毫秒）
     * @param int $memoryUsed 内存使用量（字节）
     * @param null|string $channel 日志通道名称
     * @param string $level 日志级别
     */
    protected function logResponse(
        Request $request,
        SymfonyResponse $response,
        float $duration,
        int $memoryUsed,
        ?string $channel,
        string $level,
    ): void {
        $statusCode = $response->getStatusCode();
        $responseLevel = $this->getResponseLevel($statusCode, $level);

        $context = [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'memory_used' => $this->formatBytes($memoryUsed),
            'response_size' => strlen($response->getContent()),
        ];

        MLog::log(
            $responseLevel,
            "HTTP Response: {$statusCode} {$request->getMethod()} " .
            "{$request->getPathInfo()} ({$duration}ms)",
            $context,
            $channel,
        );
    }

    /**
     * 根据状态码确定日志级别.
     *
     * @param int $statusCode HTTP 状态码
     * @param string $defaultLevel 默认日志级别
     *
     * @return string 日志级别
     */
    protected function getResponseLevel(int $statusCode, string $defaultLevel): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            $statusCode >= 300 => 'info',
            default => $defaultLevel,
        };
    }

    /**
     * 过滤敏感的请求头.
     *
     * @param array $headers 原始请求头
     *
     * @return array 过滤后的请求头
     */
    protected function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ];

        $filtered = [];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders, true)) {
                $filtered[$key] = '[FILTERED]';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * 过滤敏感的请求体数据.
     *
     * @param Request $request HTTP 请求对象
     *
     * @return array 过滤后的请求体数据
     */
    protected function filterRequestBody(Request $request): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'ssn',
        ];

        $data = $request->all();

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[FILTERED]';
            }
        }

        return $data;
    }

    /**
     * 格式化字节数为可读字符串.
     *
     * @param int $bytes 字节数
     *
     * @return string 格式化后的字符串
     */
    protected function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 * 1024 => round($bytes / 1024 / 1024, 2) . ' MB',
            $bytes >= 1024 => round($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }
}
