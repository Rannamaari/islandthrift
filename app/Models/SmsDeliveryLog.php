<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SmsDeliveryLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id', 'user_id', 'purpose', 'source', 'transaction_id',
        'transaction_description', 'reference_number', 'http_status',
        'recipient_count', 'sent_count', 'failed_count', 'successful',
        'dry_run', 'invalid_recipients', 'gateway_response', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'dry_run' => 'boolean',
            'invalid_recipients' => 'array',
            'gateway_response' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
