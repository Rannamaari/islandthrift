<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditTrailService
{
    private static ?bool $tableReady = null;

    /** @var list<string> */
    private const IGNORED_FIELDS = ['created_at', 'updated_at', 'remember_token'];

    public function record(string $event, Model $model): void
    {
        if ($model instanceof AuditLog || ! $this->tableReady()) {
            return;
        }

        try {
            [$oldValues, $newValues] = $this->valuesFor($event, $model);

            if ($event === 'updated' && $newValues === []) {
                return;
            }

            $actor = Auth::user();
            $request = app()->runningInConsole() ? null : request();

            AuditLog::query()->create([
                'company_id' => $this->companyId($model, $actor),
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_name' => $actor?->getAttribute('name'),
                'actor_email' => $actor?->getAttribute('email'),
                'event' => $event,
                'entity_type' => $model::class,
                'entity_id' => (string) $model->getKey(),
                'entity_label' => $this->label($model),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
                'request_method' => $request?->method(),
                'request_url' => $request ? Str::limit($request->fullUrl(), 2000, '') : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function valuesFor(string $event, Model $model): array
    {
        if ($event === 'created') {
            return [[], $this->sanitize($model->getAttributes())];
        }

        if ($event === 'deleted') {
            return [$this->sanitize($model->getOriginal()), []];
        }

        $changes = array_diff_key($model->getChanges(), array_flip(self::IGNORED_FIELDS));
        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        return [$this->sanitize($old), $this->sanitize($changes)];
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function sanitize(array $values): array
    {
        foreach (self::IGNORED_FIELDS as $field) {
            unset($values[$field]);
        }

        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token|authorization|auth_key|otp|payload/i', (string) $key)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_string($value) && mb_strlen($value) > 4000) {
                $values[$key] = mb_substr($value, 0, 4000).'…';
            }
        }

        return $values;
    }

    private function companyId(Model $model, mixed $actor): ?string
    {
        if ($model instanceof Company) {
            return (string) $model->getKey();
        }

        return $model->getAttribute('company_id') ?: $actor?->getAttribute('company_id');
    }

    private function label(Model $model): string
    {
        foreach (['sale_number', 'purchase_number', 'purchase_return_number', 'sale_return_number', 'shift_number', 'sku', 'code', 'name', 'email', 'reference_number'] as $field) {
            if ($value = $model->getAttribute($field)) {
                return Str::limit((string) $value, 255, '');
            }
        }

        return class_basename($model).' '.$model->getKey();
    }

    private function tableReady(): bool
    {
        return self::$tableReady ??= Schema::hasTable('audit_logs');
    }
}
