<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 格式化输出格式.
 * @author hbl
 * @date 2023-08-09
 * Class FormatResponse
 */
class FormatResponse extends Middleware
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        $content = $response->getContent();
        $content_type = $response->headers->get('content-type');
        if (strpos($content_type, 'text/html') !== false && !Str::startsWith($content, '<!DOCTYPE')) {
            $is_json = is_json($content);
            if (is_null($content)) {
                $response->setContent(formats('成功'));
            } elseif (! $is_json) {
                $response->setContent(formats('成功', $content));
            }
        }
        if (strpos($content_type, 'application/json') !== false) {
            $arr = json_decode($content, true);
            if (is_array($arr) && ! isset($arr['status_code'])) {
                if (! empty($arr['meta']['pagination'])) {
                    $arr = array_merge($arr, $arr['meta']['pagination']);
                    unset($arr['meta']['pagination']);
                }
                $response->setContent(formats('成功', $arr));
            }
        }
        return $response;
    }
}
