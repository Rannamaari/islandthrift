<?php

namespace App\Console\Commands;

use App\Services\TelegramSalesBotService;
use Illuminate\Console\Command;

class TelegramTestCommand extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Send a Telegram test notification to every allowed user';

    public function handle(TelegramSalesBotService $telegram): int
    {
        $sent = $telegram->test();

        if ($sent === 0) {
            $this->error('No messages sent. Enable Telegram and configure at least one allowed numeric chat ID.');

            return self::FAILURE;
        }

        $this->info("Test notification sent to {$sent} allowed user(s).");

        return self::SUCCESS;
    }
}
