<?php

namespace App\Services;

use App\Models\SmsDeliveryLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DhiraaguSmsService
{
    public function __construct(private MaldivesPhoneNormalizer $normalizer) {}

    /** @return array{successful: bool, valid: list<string>, invalid: list<string>, logs: list<SmsDeliveryLog>} */
    public function send(array|string $destinations, string $content, ?string $companyId = null, ?string $userId = null, string $purpose = 'general'): array
    {
        $normalized = $this->normalizer->normalizeMany($destinations);
        $logs = [];

        if ($normalized['valid'] === []) {
            return ['successful' => false, ...$normalized, 'logs' => []];
        }

        $dryRun = (bool) config('services.dhiraagu_sms.dry_run', true);
        $source = (string) config('services.dhiraagu_sms.source', 'Micronet');
        $chunkSize = max(1, min(200, (int) config('services.dhiraagu_sms.chunk_size', 200)));
        $chunks = array_chunk($normalized['valid'], $chunkSize);

        foreach ($chunks as $index => $chunk) {
            $responseData = [];
            $httpStatus = null;
            $successful = false;
            $description = null;

            if ($dryRun) {
                $successful = true;
                $httpStatus = 200;
                $responseData = [
                    'transactionStatus' => 'true',
                    'transactionId' => 'dry-run-'.Str::uuid(),
                    'transactionDescription' => 'Simulated SMS delivery',
                    'referenceNumber' => 'DRY-'.Str::upper(Str::random(10)),
                ];
            } else {
                $baseUrl = rtrim((string) config('services.dhiraagu_sms.base_url'), '/');
                $authKey = (string) config('services.dhiraagu_sms.auth_key');

                if ($baseUrl === '' || $authKey === '') {
                    $description = 'Dhiraagu SMS credentials are not configured.';
                } else {
                    try {
                        $response = Http::acceptJson()
                            ->timeout((int) config('services.dhiraagu_sms.timeout', 20))
                            ->post($baseUrl.'/sms', [
                                'destination' => $chunk,
                                'content' => $content,
                                'source' => $source,
                                'authorizationKey' => $authKey,
                            ]);
                        $httpStatus = $response->status();
                        $responseData = $this->safeResponse($response);
                        $transactionStatus = data_get($responseData, 'transactionStatus');
                        $successful = $response->successful()
                            && ($transactionStatus === true || strtolower((string) $transactionStatus) === 'true');
                        $description = data_get($responseData, 'transactionDescription');
                    } catch (Throwable) {
                        $description = 'SMS gateway request failed.';
                    }
                }
            }

            $sentCount = $successful ? count($chunk) : 0;
            $logs[] = SmsDeliveryLog::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'purpose' => $purpose,
                'source' => $source,
                'transaction_id' => data_get($responseData, 'transactionId'),
                'transaction_description' => $description ?? data_get($responseData, 'transactionDescription'),
                'reference_number' => data_get($responseData, 'referenceNumber'),
                'http_status' => $httpStatus,
                'recipient_count' => count($chunk),
                'sent_count' => $sentCount,
                'failed_count' => count($chunk) - $sentCount,
                'successful' => $successful,
                'dry_run' => $dryRun,
                'invalid_recipients' => $index === 0 ? $normalized['invalid'] : [],
                'gateway_response' => $responseData,
                'sent_at' => now(),
            ]);
        }

        return [
            'successful' => collect($logs)->every->successful,
            ...$normalized,
            'logs' => $logs,
        ];
    }

    /** @return array<string, mixed> */
    private function safeResponse(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $this->redactSecrets($data) : ['transactionDescription' => 'Non-JSON gateway response'];
    }

    /** @return array<string, mixed> */
    private function redactSecrets(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), ['authorizationkey', 'authorization', 'password', 'auth_key'], true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redactSecrets($value);
            }
        }

        return $data;
    }
}
