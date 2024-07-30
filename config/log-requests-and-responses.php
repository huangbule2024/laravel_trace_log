<?php
return [
    "request_start" => env("LOGGING_REQUEST_START", true),
    "response_start" => env("LOGGING_RESPONSE_START", false),
    'request_should_queue' => env('LOGGING_REQUEST_SHOULD_QUEUE', false),
    'response_should_queue' => env('LOGGING_RESPONSE_SHOULD_QUEUE', true),
    'request_channel' => env('LOGGING_REQUEST_CHANNEL', "stack"),
    'response_channel' => env('LOGGING_RESPONSE_CHANNEL', "api_response"),
    'ignore_path' => [
        //忽略url exp:  /api/pub/test
    ],
    'sql_start' => env("LOGGING_SQL_START", false), //sql日志
    'sql_channel' => env('LOGGING_SQL_CHANNEL', "sql"),//sql对应channel
    'api_exception_start' => env('LOGGING_API_EXCEPTION_START', false), //是否开启App\Exceptions\ApiException日志
];
