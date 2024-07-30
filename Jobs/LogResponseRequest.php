<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 日志追踪job
 * @author hbl
 */
class LogResponseRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    private $message;
    private $context;
    private $channel;
    private $should_queue;

    public function __construct($message, $context, $channel)
    {
        $this->message = $message;
        $this->context = $context;
        $this->channel = $channel;
        $this->onQueue('logging_request_and_response');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::channel($this->channel)->withoutContext()->debug($this->message, $this->context);
    }

}
