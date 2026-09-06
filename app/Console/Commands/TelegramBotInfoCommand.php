<?php

namespace App\Console\Commands;

use App\Services\TelegramSalesBotService;
use Illuminate\Console\Command;

class TelegramBotInfoCommand extends Command
{
    protected $signature = 'telegram:info';

    protected $description = 'Show Telegram bot identity and users who have messaged it';

    public function handle(TelegramSalesBotService $telegram): int
    {
        $bot = $telegram->getMe()['result'] ?? [];
        $this->info('Bot: @'.($bot['username'] ?? 'unknown').' ('.($bot['first_name'] ?? 'unknown').')');

        $updates = collect($telegram->getUpdates()['result'] ?? []);
        $users = $updates->map(function (array $update): ?array {
            $message = $update['message'] ?? null;
            if (! isset($message['chat']['id'])) {
                return null;
            }

            return [
                'chat_id' => (string) $message['chat']['id'],
                'username' => isset($message['from']['username']) ? '@'.$message['from']['username'] : '',
                'name' => trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')),
            ];
        })->filter()->unique('chat_id')->values();

        if ($users->isEmpty()) {
            $this->warn('No users found. Each user must open the bot and press Start, then run this command again.');

            return self::SUCCESS;
        }

        $this->table(['Chat ID', 'Username', 'Name'], $users->all());

        return self::SUCCESS;
    }
}
