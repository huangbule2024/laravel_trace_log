# laravel_trace_log
日志追踪
```php
<?php

namespace App\Providers;

use App\Exceptions\ApiException;
use App\Exceptions\Handler;
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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Request $request)
    {
        //生成log链条
        $requestId = (string) Str::uuid();
        $context = ['request_id' => $requestId];
        collect(config('logging.channels'))->keys()->map(function ($channel) use ($context) {
            if (! in_array($channel, ['slack', 'papertrail', 'emergency', 'sql'])) {
                Log::channel($channel)->withContext($context);
            }
        });
        $path = request()->path();
        $arr_ignore = ['/'];
        if (! in_array($path, $arr_ignore)) {
            logger($path.' '.json_encode($request->input(), 256));
        }
        //保存request_id到app
        $this->app->singleton('request_id', function () use ($requestId) {
            return $requestId;
        });

        //本地化 Carbon
        Carbon::setLocale('zh');

        //开启db log
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
            Log::channel('sql')->debug('request_id: '.$requestId.' run-time: '.$query->time.'ms; '.$sql."\n\n\t");
        });


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
    }
}

```


### 安装        "owen-it/laravel-auditing": "^13.5",
```php 
//额外增加字段 request_id
'resolvers' => [
        'ip_address' => OwenIt\Auditing\Resolvers\IpAddressResolver::class,
        'user_agent' => OwenIt\Auditing\Resolvers\UserAgentResolver::class,
        'url'        => OwenIt\Auditing\Resolvers\UrlResolver::class,
        'request_id' => \App\Resolvers\RequestIdResolver::class,
    ],
```
数据库迁移文件
```php
            $table->string('request_id')->nullable();
```
效果：
![图片](https://cdn.learnku.com/uploads/images/202312/04/20465/jC6Gf6D2a6.png!large)

### LINUX 根据request_id追踪日志：
```php
tail -100f laravel.log | grep -C 10 "3245dc5d-5f21-43ca-be17-2b53ffe291e0"

tail -100f sql.log | grep -C 10 "3245dc5d-5f21-43ca-be17-2b53ffe291e0"

zgrep --color=auto  -i -C 10 "3245dc5d-5f21-43ca-be17-2b53ffe291e0" api_response.log.1.gz  //输出日志非常大必须压缩
```

### 钉钉机器人报警

```php
        "wangju/ding-notice": "^1.0",
```
```php
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

```
### 钉钉机器人效果
```html
任务id： ad312b82-e869-436e-ab31-0d63bdd19b41   内容：Invalid argument supplied for foreach() /var/www/WITMED/app/Actions/Offline/ListDepartmentExperts.php 47行
```

### 日志审核走异步
[参考](https://learnku.com/articles/85688 "参考下篇文章")


### supervisor 配置
```
[program:logging_request_and_response]
command=php artisan queue:work --queue=logging_request_and_response --daemon --tries=3
process_name=%(program_name)s
numprocs=1
startsecs=0
startretries=10
autostart=true
autorestart=true
user=www-data
nodaemon=false
directory=/var/www/project
stdout_logfile=/var/www/project/storage/logs/logging_request_and_response.log
stderr_logfile=/var/log/supervisor/logging_request_and_response.err.log
```
