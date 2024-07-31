<?php

namespace App\Http\Middleware;

use App\Jobs\LogResponseRequest;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * 日志追踪链条中间件
 * @author hbl
 */
class LogRequestsAndResponses
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (config('log-requests-and-responses.request_start')) {
            $path = request()->path();
            $arr_ignore = config('log-requests-and-responses.ignore_path');
            if (!in_array($path, $arr_ignore)) {
                $chanel = config('log-requests-and-responses.request_channel');
                $should_queue = config('log-requests-and-responses.request_should_queue');
                $this->pushLog($request, 'request => ', $chanel, $should_queue);
            }
        }
        $response = $next($request);
        $this->logger($request, $response);
        return $response;
    }

    /**
     * Execute terminable actions after the response is returned.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\Response $response
     * @return void
     */
    public function logger($request, $response): void
    {
        if (config('log-requests-and-responses.response_start')) {
            $message = $response->getContent();
            $chanel = config('log-requests-and-responses.response_channel');
            $should_queue = config('log-requests-and-responses.response_should_queue');
            $this->pushLog($request, $message, $chanel, $should_queue);
        }
    }

    private function pushLog($request, $message, $chanel, $should_queue): void
    {
        $context = $this->getRequestData($request);
        $job = new LogResponseRequest($message, $context, $chanel);
        if ($should_queue) {
            dispatch($job);
        } else {
            Log::channel($chanel)->debug($message, $context);
        }
    }

    private function getRequestData($request)
    {
        return [
            'request_method' => $request->method(),
            'request_body' => $this->handleRequestBody($request),
            'request_url' => $request->url(),
        ];
    }

    private function handleRequestBody($request)
    {
        if (!empty($request->all())) {
            return $request->all();
        } elseif (empty($request->all()) && !empty($request->getContent())) {
            return $request->getContent();
        }
        return null;
    }
}
