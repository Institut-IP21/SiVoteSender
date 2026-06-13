<?php

namespace App\Console\Commands;

use App\Models\SentMessage;
use Illuminate\Console\Command;

class BatchStats extends Command
{
    protected $signature = 'evote:stats:batch
                            {--B|batch= : The batch UUID}';

    protected $description = 'Show statistics for a message batch';

    public function handle(): int
    {
        $batchUuid = $this->option('batch');

        if (!$batchUuid) {
            $batchUuid = $this->ask('Enter batch UUID');
        }

        if (!$batchUuid) {
            $this->error('Batch UUID is required.');
            return 1;
        }

        $messages = SentMessage::batch($batchUuid)->get();

        if ($messages->isEmpty()) {
            $this->error("No messages found for batch {$batchUuid}");
            return 1;
        }

        $successful = $messages->where('successful', true)->count();
        $failed = $messages->where('successful', false)->count();

        $this->table(['Field', 'Value'], [
            ['Batch UUID', $batchUuid],
            ['Total Messages', $messages->count()],
            ['Successful', $successful],
            ['Failed', $failed],
        ]);

        return 0;
    }
}
