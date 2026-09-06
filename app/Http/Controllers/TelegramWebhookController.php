<?php

namespace App\Http\Controllers;

use App\Services\TelegramSalesBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramSalesBotService $telegram): JsonResponse
    {
        $configuredSecret = (string) config('services.telegram_sales.webhook_secret');
        abort_if(
            $configuredSecret === '' || ! hash_equals($configuredSecret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403,
        );

        $telegram->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
