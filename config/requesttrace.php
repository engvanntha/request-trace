<?php

return array(
    'project_name' => env('REQUEST_TRACE_PROJECT_NAME', env('APP_NAME', 'laravel')),
    'request_header' => env('REQUEST_TRACE_HEADER', 'X-Request-Id'),
    'attach_response_header' => env('REQUEST_TRACE_ATTACH_RESPONSE_HEADER', true),
    'sync_app_uuid' => env('REQUEST_TRACE_SYNC_APP_UUID', true),
    'auto_register_middleware' => env('REQUEST_TRACE_AUTO_REGISTER_MIDDLEWARE', false),
    'auto_log_requests' => env('REQUEST_TRACE_AUTO_LOG_REQUESTS', true),
    'request_log_level' => env('REQUEST_TRACE_REQUEST_LOG_LEVEL', 'info'),
    'response_log_level' => env('REQUEST_TRACE_RESPONSE_LOG_LEVEL', 'info'),
    'request_input_only' => array(),
    'request_input_except' => array(),
    'response_include_body' => env('REQUEST_TRACE_RESPONSE_INCLUDE_BODY', false),
    'response_body_max_length' => (int) env('REQUEST_TRACE_RESPONSE_BODY_MAX_LENGTH', 2048),
    'hidden_keys' => array(
        'password',
        'password_confirmation',
        'client_secret',
        'token',
        'access_token',
        'refresh_token'
    )
);
