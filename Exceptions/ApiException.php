<?php

namespace App\Exceptions;

/*
 * api自定义异常， 在AppServiceProvider捕获输出格式
 * @author hbl
 * @Date 2023/7/20
 **/

use Exception;
use Throwable;

class ApiException extends Exception
{
    public $data = [];

    public function __construct(string $message = '', int $code = 500, $data = [], Throwable $previous = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData() {
        return $this->data;
    }
}
