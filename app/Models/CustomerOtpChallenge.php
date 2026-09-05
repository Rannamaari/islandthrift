<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerOtpChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id', 'phone', 'code_hash', 'purpose', 'attempts', 'expires_at',
        'verified_at', 'consumed_at', 'request_ip',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
