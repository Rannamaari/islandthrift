<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;

class StorefrontContext
{
    private ?Company $company = null;

    private ?Branch $branch = null;

    private ?Warehouse $warehouse = null;

    public function company(): Company
    {
        return $this->company ??= Company::query()
            ->where('is_active', true)
            ->where('website_enabled', true)
            ->orderBy('created_at')
            ->firstOrFail();
    }

    public function branch(): Branch
    {
        $company = $this->company();

        return $this->branch ??= Branch::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->when($company->online_branch_id, fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$company->online_branch_id]))
            ->orderBy('created_at')
            ->firstOrFail();
    }

    public function warehouse(): Warehouse
    {
        $company = $this->company();
        $branch = $this->branch();

        return $this->warehouse ??= Warehouse::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->when($company->online_warehouse_id, fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$company->online_warehouse_id]))
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->firstOrFail();
    }

    /** @return array<string, string> */
    public function deliveryMethods(): array
    {
        return $this->normalizeOptions($this->company()->website_delivery_methods, [
            'pickup' => 'Store Pickup',
            'local_delivery' => 'Local Delivery',
        ]);
    }

    /** @return array<string, string> */
    public function paymentMethods(): array
    {
        return $this->normalizeOptions($this->company()->website_payment_methods, [
            'cash' => 'Cash / Pay on Collection',
            'bank_transfer' => 'Bank Transfer',
        ]);
    }

    /** @param array<int|string, mixed>|null $options
     * @param  array<string, string>  $fallback
     * @return array<string, string>
     */
    private function normalizeOptions(?array $options, array $fallback): array
    {
        if (blank($options)) {
            return $fallback;
        }

        return collect($options)->mapWithKeys(function ($value, $key): array {
            if (is_array($value)) {
                return [(string) ($value['key'] ?? $key) => (string) ($value['label'] ?? $value['name'] ?? $key)];
            }

            return [is_string($key) ? $key : (string) $value => (string) $value];
        })->all();
    }
}
