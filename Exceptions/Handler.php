<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var string[]
     */
    protected $dontReport = [
        ApiException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var string[]
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $exception) {
            if ( ($exception instanceof \ErrorException) || ($exception instanceof \Error) ) {
                if (app()->runningInConsole()) {
                    Log::channel('error_log')->error($exception);
                    ding()->text( "任务id： " . app('request_id') . "   内容：" . $exception->getMessage() . " " . $exception->getFile() . " " . $exception->getLine() . '行');
                }
            }
        });
    }
}
