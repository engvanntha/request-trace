<?php

namespace Engvanntha\RequestTrace\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Engvanntha\RequestTrace\RequestTraceContext;

class CaptureRequestTrace
{
    public function handle($request, Closure $next)
    {
        $startedAt = microtime(true);
        $headerName = config('requesttrace.request_header', 'X-Request-Id');
        $requestId = $this->resolveRequestId($request, $headerName);

        RequestTraceContext::setRequestId($requestId);

        if (config('requesttrace.sync_app_uuid', true)) {
            config(array('app.uuid' => $requestId));
        }

        if ($request && isset($request->attributes)) {
            $request->attributes->set('requesttrace.request_id', $requestId);
        }

        $this->setLogContext($requestId);
        $this->autoLogIncomingRequest($request);

        $response = $next($request);

        if (config('requesttrace.attach_response_header', true) && is_object($response) && isset($response->headers)) {
            $response->headers->set($headerName, $requestId);
        }

        $this->autoLogOutgoingResponse($request, $response, $startedAt);

        return $response;
    }

    protected function resolveRequestId($request, $headerName)
    {
        $requestId = '';

        if ($request && method_exists($request, 'header')) {
            $requestId = trim((string) $request->header($headerName, ''));
        }

        if ($requestId === '') {
            $requestId = RequestTraceContext::getRequestId();
        }

        return $requestId;
    }

    protected function setLogContext($requestId)
    {
        $context = array(
            'request_id' => $requestId,
            'project' => RequestTraceContext::getProjectName()
        );

        $logger = Log::getFacadeRoot();
        if (is_object($logger) && method_exists($logger, 'withContext')) {
            Log::withContext($context);
        }
    }

    protected function autoLogIncomingRequest($request)
    {
        if (!config('requesttrace.auto_log_requests', true)) {
            return;
        }

        $action = $this->resolveAction($request);
        $payload = array(
            'http_method' => method_exists($request, 'method') ? $request->method() : null,
            'path' => method_exists($request, 'path') ? $request->path() : null,
            'ip' => method_exists($request, 'ip') ? $request->ip() : null,
            'input' => $this->extractRequestInput($request)
        );

        $payload = RequestTraceContext::sanitizePayload($payload, config('requesttrace.hidden_keys', array()));
        $message = RequestTraceContext::formatMessage($action['class'], $action['method'], 'request');
        Log::log($this->normalizeLevel(config('requesttrace.request_log_level', 'info')), $message, array('data' => $payload));
    }

    protected function autoLogOutgoingResponse($request, $response, $startedAt)
    {
        if (!config('requesttrace.auto_log_requests', true)) {
            return;
        }

        $action = $this->resolveAction($request);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $payload = array(
            'status' => $this->responseStatus($response),
            'duration_ms' => $durationMs
        );

        if (config('requesttrace.response_include_body', false)) {
            $payload['body'] = $this->extractResponseBody($response);
        }

        $message = RequestTraceContext::formatMessage($action['class'], $action['method'], 'response');
        Log::log($this->normalizeLevel(config('requesttrace.response_log_level', 'info')), $message, array('data' => $payload));
    }

    protected function resolveAction($request)
    {
        $default = array('class' => 'HttpKernel', 'method' => 'handle');
        if (!$request || !method_exists($request, 'route')) {
            return $default;
        }

        try {
            $route = $request->route();
            if (!$route) {
                return $default;
            }

            $actionName = null;
            if (is_object($route) && method_exists($route, 'getActionName')) {
                $actionName = (string) $route->getActionName();
            } elseif (is_array($route) && isset($route['controller'])) {
                $actionName = (string) $route['controller'];
            }

            if (!$actionName || $actionName === 'Closure') {
                return array('class' => 'Closure', 'method' => '__invoke');
            }

            $parts = explode('@', $actionName);
            if (count($parts) === 2) {
                return array('class' => $parts[0], 'method' => $parts[1]);
            }

            return array('class' => $actionName, 'method' => '__invoke');
        } catch (\Throwable $e) {
            return $default;
        }
    }

    protected function extractRequestInput($request)
    {
        if (!$request) {
            return array();
        }

        $input = method_exists($request, 'all') ? $request->all() : array();

        $only = config('requesttrace.request_input_only', array());
        if (!empty($only)) {
            $input = array_intersect_key($input, array_flip($only));
        }

        $except = config('requesttrace.request_input_except', array());
        if (!empty($except)) {
            foreach ($except as $key) {
                if (array_key_exists($key, $input)) {
                    unset($input[$key]);
                }
            }
        }

        return $input;
    }

    protected function responseStatus($response)
    {
        if (is_object($response) && method_exists($response, 'getStatusCode')) {
            return (int) $response->getStatusCode();
        }

        return null;
    }

    protected function extractResponseBody($response)
    {
        if (!is_object($response) || !method_exists($response, 'getContent')) {
            return null;
        }

        $body = $response->getContent();
        if (!is_string($body)) {
            return $body;
        }

        $maxLen = (int) config('requesttrace.response_body_max_length', 2048);
        if ($maxLen > 0 && strlen($body) > $maxLen) {
            return substr($body, 0, $maxLen) . '...[truncated]';
        }

        return $body;
    }

    protected function normalizeLevel($level)
    {
        $level = strtolower((string) $level);
        $allowed = array('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug');
        return in_array($level, $allowed, true) ? $level : 'info';
    }
}
