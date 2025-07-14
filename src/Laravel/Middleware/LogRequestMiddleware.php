<?php

declare(strict_types=1);

namespace Mellivora\Logger\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mellivora\Logger\Laravel\Facades\MLog;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Request Logging Middleware.
 *
 * Automatically logs detailed information about HTTP requests and responses
 */
class LogRequestMiddleware
{
    /**
     * Handle incoming request.
     *
     * @param Request $request HTTP request object
     * @param Closure $next Next middleware
     * @param null|string $channel Log channel name
     * @param null|string $level Log level
     *
     * @return SymfonyResponse HTTP response object
     */
    public function handle(
        Request $request,
        Closure $next,
        ?string $channel = null,
        ?string $level = 'info',
    ): SymfonyResponse {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Log request start
        $this->logRequest($request, $channel, $level);

        // Process request
        $response = $next($request);

        // Calculate processing time and memory usage
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds
        $memoryUsed = $endMemory - $startMemory;

        // Log response
        $this->logResponse($request, $response, $duration, $memoryUsed, $channel, $level);

        return $response;
    }

    /**
     * Log request information.
     *
     * @param Request $request HTTP request object
     * @param null|string $channel Log channel name
     * @param string $level Log level
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
     * Log response information.
     *
     * @param Request $request HTTP request object
     * @param SymfonyResponse $response HTTP response object
     * @param float $duration Processing time (milliseconds)
     * @param int $memoryUsed Memory usage (bytes)
     * @param null|string $channel Log channel name
     * @param string $level Log level
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
