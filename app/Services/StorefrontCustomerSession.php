<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Http\Request;

class StorefrontCustomerSession
{
    public function customer(Request $request, Company $company): ?Customer
    {
        $id = $request->session()->get('store_customer_id');

        return $id ? Customer::query()
            ->whereKey($id)
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first() : null;
    }
}
