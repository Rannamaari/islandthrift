<?php

namespace App\Console\Commands;

use App\Services\TelegramSalesBotService;
use Illuminate\Console\Command;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook {url?}';

    protected $description = 'Register the application Telegram webhook';

    public function handle(TelegramSalesBotService $telegram): int
    {
        $secret = (string) config('services.telegram_sales.webhook_secret');
        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET must be configured first.');

            return self::FAILURE;
        }

        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
        $result = $telegram->setWebhook($url);

        $this->line((string) ($result['description'] ?? 'Webhook request completed.'));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
