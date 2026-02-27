<?php

namespace Engvanntha\RequestTrace;

use Illuminate\Support\Str;

class RequestTraceContext
{
    protected static $requestId;
    protected static $projectName;

    public static function setRequestId($requestId)
    {
        $normalized = static::normalize($requestId);
        if ($normalized === '') {
            $normalized = static::generateRequestId();
        }

        static::$requestId = $normalized;
    }

    public static function getRequestId()
    {
        if (!empty(static::$requestId)) {
            return static::$requestId;
        }

        $fromConfig = static::normalize(config('requesttrace.request_id'));
        if ($fromConfig !== '') {
            static::$requestId = $fromConfig;
            return static::$requestId;
        }

        $fromAppUuid = static::normalize(config('app.uuid'));
        if ($fromAppUuid !== '') {
            static::$requestId = $fromAppUuid;
            return static::$requestId;
        }

        if (function_exists('request')) {
            try {
                $request = request();
                if ($request && method_exists($request, 'header')) {
                    $headerName = config('requesttrace.request_header', 'X-Request-Id');
                    $headerValue = static::normalize($request->header($headerName, ''));
                    if ($headerValue !== '') {
                        static::$requestId = $headerValue;
                        return static::$requestId;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore request helper failures in non-http contexts.
            }
        }

        static::$requestId = static::generateRequestId();
        return static::$requestId;
    }

    public static function setProjectName($projectName)
    {
        $normalized = static::normalize($projectName);
        if ($normalized !== '') {
            static::$projectName = $normalized;
        }
    }

    public static function getProjectName()
    {
        if (!empty(static::$projectName)) {
            return static::$projectName;
        }

        $projectName = static::normalize(config('requesttrace.project_name', config('app.name', 'laravel')));
        if ($projectName === '') {
            $projectName = 'laravel';
        }

        static::$projectName = $projectName;
        return static::$projectName;
    }

    public static function formatMessage($className, $functionName, $variableName)
    {
        return sprintf(
            '<%s><%s><%s>@<%s>@<%s>',
            static::getRequestId(),
            static::getProjectName(),
            static::shortClassName($className),
            $functionName ?: 'unknown',
            $variableName ?: 'data'
        );
    }

    public static function sanitizePayload($payload, array $hiddenKeys = array())
    {
        if (empty($hiddenKeys)) {
            return $payload;
        }

        $normalizedHidden = array();
        foreach ($hiddenKeys as $hiddenKey) {
            $normalizedHidden[] = strtolower((string) $hiddenKey);
        }

        return static::sanitizeRecursive($payload, $normalizedHidden);
    }

    protected static function sanitizeRecursive($payload, array $normalizedHidden)
    {
        if (is_object($payload)) {
            if ($payload instanceof \JsonSerializable) {
                $payload = $payload->jsonSerialize();
            } elseif (method_exists($payload, 'toArray')) {
                $payload = $payload->toArray();
            } else {
                return $payload;
            }
        }

        if (!is_array($payload)) {
            return $payload;
        }

        $sanitized = array();
        foreach ($payload as $key => $value) {
            $keyName = strtolower((string) $key);
            if (in_array($keyName, $normalizedHidden, true)) {
                $sanitized[$key] = '***';
                continue;
            }

            $sanitized[$key] = static::sanitizeRecursive($value, $normalizedHidden);
        }

        return $sanitized;
    }

    protected static function shortClassName($className)
    {
        $normalized = static::normalize($className);
        if ($normalized === '') {
            return 'Closure';
        }

        $parts = explode('\\', $normalized);
        return end($parts) ?: $normalized;
    }

    protected static function normalize($value)
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    protected static function generateRequestId()
    {
        try {
            return (string) Str::uuid();
        } catch (\Throwable $e) {
            return uniqid('req_', true);
        }
    }
}
