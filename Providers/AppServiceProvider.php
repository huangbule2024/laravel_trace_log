<?php

namespace App\Providers;

use App\Exceptions\ApiException;
use App\Preprocess\UuidPreprocess;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Class AppServiceProvider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->isLocal()) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Request $request)
    {
        //本地化 Carbon
        Carbon::setLocale('zh');
        //生成log链条
        $requestId = (string) Str::uuid();
        $context = ['request_id' => $requestId];
        collect(config('logging.channels'))->keys()->map(function ($channel) use ($context) {
            if (! in_array($channel, ['slack', 'papertrail', 'emergency', 'sql'])) {
                Log::channel($channel)->withContext($context);
            }
        });
        //保存request_id到app
        $this->app->singleton('request_id', function () use ($requestId) {
            return $requestId;
        });
        //开启db log
        if (config('log-requests-and-responses.sql_start')) {
            DB::listen(function ($query) use ($requestId) {
                $sql = $query->sql;
                foreach ($query->bindings as $key => $value) {
                    if (is_numeric($key)) {
                        if (is_numeric($value)) {
                            $sql = preg_replace('/\?/', $value, $sql, 1);
                        } else {
                            $sql = preg_replace('/\?/', sprintf('\'%s\'', $value), $sql, 1);
                        }
                    } else {
                        if (is_numeric($value)) {
                            $sql = str_replace(':'.$key, $value, $sql);
                        } else {
                            $sql = str_replace(':'.$key, sprintf('\'%s\'', $value), $sql);
                        }
                    }
                }
                $sql = str_replace('\\', '', $sql);
                $chanel = config('log-requests-and-responses.sql_channel');
                Log::channel($chanel)->debug('request_id: '.$requestId.' run-time: '.$query->time.'ms; '.$sql."\n\n\t");
            });
        }

        //捕获自定义异常输出格式
        $handle = app(\Dingo\Api\Exception\Handler::class);
        $handle->register(function (\Exception $exception) {
            switch ($exception) {
                case $exception instanceof ApiException :
                {
                    if (config('log-requests-and-responses.api_exception_start')) {
                        logger($exception);
                    }
                    $data = $exception->getData();
                    return response()::make(formats($exception->getMessage(), $data, $exception->getCode()));
                }
                case $exception instanceof \ErrorException :
                case $exception instanceof \Error :
                case $exception instanceof QueryException :
                {
                    Log::channel('error_log')->error($exception);
                    ding()->text( "任务id： " . app('request_id') . "   内容：" . $exception->getMessage() . " " . $exception->getFile() . " " . $exception->getLine() . '行');
                    return response()::make(formats($exception->getMessage(), [], 422));
                }
                case $exception instanceof \Mosquitto\Exception :
                {
                    Log::channel('error_log')->error($exception);
                    if ($exception->getMessage() == 'The client is not currently connected.') {
                        return response()::make(formats('客户端当前连接mqtt失败，请联系管理员', [], 500));
                    }
                }
            }
        });

        //拓展builder
        EloquentBuilder::macro('whereLike', function ($column, $val) {
            return $this->where($column, 'like', '%'.$val.'%');
        });

        //重写findOrFail
        EloquentBuilder::macro('findOrThrow', function ($id, $message = '', $columns = ['*']) {
            $flag = true;
            //支持逗号拼接传过来 exp: ["1,2"]
            if (is_array($id)) {
                $id = explode(',', array_pop($id));
            } else {
                if (! is_numeric($id)) {
                    $raw_id = $id;
                    $id = get_id($id);
                    if ($id == 'id错误') {
                        $flag = false;
                        $id = $raw_id;
                    }
                }
            }
            if ($flag == true) {
                $result = $this->find($id, $columns);
                $id = $id instanceof Arrayable ? $id->toArray() : $id;
                if (is_array($id)) {
                    if (count($result) === count(array_unique($id))) {
                        return $result;
                    }
                } elseif (! is_null($result)) {
                    return $result;
                }
            }
            $message = $message ?: '表数据不存在 '.implode(', ', Arr::wrap($id));
            throw_api_exception($message);
        });

        Validator::extend('allow_dash_new', function ($attribute, $value, $parameters, $validator) {
            return is_string($value) && preg_match('/^[.0-9a-zA-Z_-]+$/u', $value);
        });

        Validator::extend('all_chinese', function ($attribute, $value, $parameters, $validator) {
            if (preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $value)>0) {
                return true;
            }
            return false;
        });
        //注入uuid预处理
        $this->app->singleton('uuid', UuidPreprocess::class);
    }
}
