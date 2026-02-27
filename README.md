# engvanntha/request-trace

Reusable request-aware logging package for Laravel 5.8 / 7 / 11.

## Installation

```bash
composer require engvanntha/request-trace
```

## Features

- Captures `X-Request-Id` from incoming HTTP requests (falls back to generated UUID).
- Auto logs all client requests/responses with `Class@method` (no per-function calls).
- Provides `RequestTraceTrait` for class/function/variable-aware logs.
- Supports variable selection and sensitive-field masking.
- Works in controllers, services, middleware, and other classes.

## Trait usage

```php
use Engvanntha\RequestTrace\Traits\RequestTraceTrait;

class ClassName
{
    use RequestTraceTrait;

    public function functionName(array $data)
    {
        $user = $this->queryUser($data);

        // Log one variable
        $this->traceLog('user', $user);

        // Log selected variables only
        $this->traceLogVars(compact('data', 'user'), array('user'));

        return $user;
    }
}
```

Generated message format:

```text
<X-Request-Id><CurrentProjectName><ClassName>@<functionName>@<variableName>
```

## Middleware

Register middleware:

```php
\Engvanntha\RequestTrace\Middleware\CaptureRequestTrace::class
```

This middleware should run early in the HTTP stack.

With `auto_log_requests=true`, each request is logged automatically in format:

```text
<X-Request-Id>@<CurrentProjectName>@<ClassName>@<functionName>@<request|response>
```

Use `RequestTraceTrait` only for additional deep logs inside selected services/functions.
