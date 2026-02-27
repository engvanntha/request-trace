<?php

namespace Engvanntha\RequestTrace\Traits;

use Illuminate\Support\Facades\Log;
use Engvanntha\RequestTrace\RequestTraceContext;

trait RequestTraceTrait
{
    protected function traceLog($variableName, $data, $level = 'info', array $context = array(), array $options = array())
    {
        $caller = $this->traceResolveCaller();

        $className = isset($options['class']) ? $options['class'] : $caller['class'];
        $functionName = isset($options['function']) ? $options['function'] : $caller['function'];
        $payloadKey = isset($options['payload_key']) ? $options['payload_key'] : 'data';

        $hiddenKeys = config('requesttrace.hidden_keys', array());
        if (isset($options['hidden']) && is_array($options['hidden'])) {
            $hiddenKeys = array_unique(array_merge($hiddenKeys, $options['hidden']));
        }

        $message = RequestTraceContext::formatMessage($className, $functionName, $variableName);
        $payload = RequestTraceContext::sanitizePayload($data, $hiddenKeys);

        $logContext = array_merge(
            array(
                'request_id' => RequestTraceContext::getRequestId(),
                'project' => RequestTraceContext::getProjectName(),
                'class' => $className,
                'function' => $functionName,
                'variable' => $variableName
            ),
            $context,
            array($payloadKey => $payload)
        );

        Log::log($this->traceNormalizeLevel($level), $message, $logContext);

        return $data;
    }

    protected function traceLogVars(array $variables, array $only = array(), $level = 'info', array $context = array(), array $options = array())
    {
        if (!empty($only)) {
            $variables = array_intersect_key($variables, array_flip($only));
        }

        foreach ($variables as $name => $value) {
            $this->traceLog((string) $name, $value, $level, $context, $options);
        }

        return $variables;
    }

    protected function traceLogMember($memberName, $level = 'info', array $context = array(), array $options = array())
    {
        $value = null;
        if (property_exists($this, $memberName)) {
            $value = $this->{$memberName};
        }

        return $this->traceLog((string) $memberName, $value, $level, $context, $options);
    }

    protected function traceResolveCaller()
    {
        $fallbackClass = is_object($this) ? get_class($this) : 'Closure';
        $caller = array(
            'class' => $fallbackClass,
            'function' => 'unknown'
        );

        $skipFunctions = array(
            'traceLog',
            'traceLogVars',
            'traceLogMember',
            'traceResolveCaller',
            'traceNormalizeLevel'
        );

        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
        foreach ($frames as $frame) {
            $function = isset($frame['function']) ? $frame['function'] : null;
            if (!$function || in_array($function, $skipFunctions, true)) {
                continue;
            }

            $caller['class'] = isset($frame['class']) ? $frame['class'] : $caller['class'];
            $caller['function'] = $function;
            break;
        }

        return $caller;
    }

    protected function traceNormalizeLevel($level)
    {
        $normalized = strtolower((string) $level);
        $allowed = array(
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug'
        );

        if (!in_array($normalized, $allowed, true)) {
            return 'info';
        }

        return $normalized;
    }
}
