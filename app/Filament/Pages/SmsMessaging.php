<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminSupport;
use App\Models\Customer;
use App\Models\SmsDeliveryLog;
use App\Models\User;
use App\Services\DhiraaguSmsService;
use App\Services\MaldivesPhoneNormalizer;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class SmsMessaging extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'SMS Messaging';

    protected static ?string $title = 'Customer SMS';

    protected string $view = 'filament.pages.sms-messaging';

    public string $manualNumbers = '';

    public string $message = '';

    public bool $sendAllCustomers = false;

    public array $customerIds = [];

    public array $userIds = [];

    public array $excludedCustomerIds = [];

    public array $recipientPreview = [];

    public array $invalidPreview = [];

    public array $confirmedRecipients = [];

    public bool $pendingConfirmation = false;

    public ?array $lastResult = null;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('nav.administration');
    }

    public static function canAccess(): bool
    {
        return (bool) AdminSupport::user()?->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess() && AdminSupport::companyId(), 403);
    }

    public function reviewSms(MaldivesPhoneNormalizer $normalizer, DhiraaguSmsService $sms): void
    {
        $this->validate(['message' => ['required', 'string', 'max:1000']]);
        $companyId = AdminSupport::companyId();
        $recipients = [];

        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_walk_in', false)
            ->whereNotNull('phone')
            ->when(! $this->sendAllCustomers, fn ($query) => $query->whereKey($this->customerIds))
            ->when($this->sendAllCustomers && $this->excludedCustomerIds !== [], fn ($query) => $query->whereNotIn('id', $this->excludedCustomerIds))
            ->get(['id', 'name', 'phone']);

        foreach ($customers as $customer) {
            $recipients[] = ['type' => 'Customer', 'name' => $customer->name, 'phone' => $customer->phone];
        }

        User::query()->where('company_id', $companyId)->whereKey($this->userIds)->whereNotNull('phone')
            ->get(['name', 'phone'])->each(function (User $user) use (&$recipients): void {
                $recipients[] = ['type' => 'User', 'name' => $user->name, 'phone' => $user->phone];
            });

        foreach (preg_split('/[\s,;]+/', trim($this->manualNumbers), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $number) {
            $recipients[] = ['type' => 'Manual', 'name' => 'Manual recipient', 'phone' => $number];
        }

        $normalized = $normalizer->normalizeMany(array_column($recipients, 'phone'));
        $validLookup = array_flip($normalized['valid']);
        $preview = [];

        foreach ($recipients as $recipient) {
            $phone = $normalizer->normalize($recipient['phone']);
            if ($phone && isset($validLookup[$phone])) {
                $preview[$phone] = [...$recipient, 'phone' => $phone];
            }
        }

        $this->recipientPreview = array_values($preview);
        $this->invalidPreview = $normalized['invalid'];
        $this->confirmedRecipients = array_keys($preview);
        $this->pendingConfirmation = false;

        if ($this->confirmedRecipients === []) {
            $this->addError('manualNumbers', 'Select or enter at least one valid Maldives number.');

            return;
        }

        if ((bool) config('services.dhiraagu_sms.dry_run', true)) {
            $this->performSend($sms);

            return;
        }

        $this->pendingConfirmation = true;
    }

    public function confirmSend(DhiraaguSmsService $sms): void
    {
        abort_unless($this->pendingConfirmation, 422);
        $this->performSend($sms);
    }

    /** @return array<string,string> */
    public function customerOptions(): array
    {
        return Customer::query()->where('company_id', AdminSupport::companyId())->where('is_active', true)
            ->where('is_walk_in', false)->whereNotNull('phone')->orderBy('name')->get()
            ->mapWithKeys(fn (Customer $customer) => [$customer->id => "{$customer->name} — {$customer->phone}"])->all();
    }

    /** @return array<string,string> */
    public function userOptions(): array
    {
        return User::query()->where('company_id', AdminSupport::companyId())->where('is_active', true)
            ->whereNotNull('phone')->orderBy('name')->get()
            ->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} — {$user->phone}"])->all();
    }

    /** @return Collection<int,SmsDeliveryLog> */
    public function recentLogs(): Collection
    {
        return SmsDeliveryLog::query()->where('company_id', AdminSupport::companyId())->latest('sent_at')->limit(25)->get();
    }

    private function performSend(DhiraaguSmsService $sms): void
    {
        $result = $sms->send($this->confirmedRecipients, $this->message, AdminSupport::companyId(), AdminSupport::user()?->id, 'admin_broadcast');
        $this->lastResult = [
            'successful' => $result['successful'],
            'valid' => count($result['valid']),
            'invalid' => count($result['invalid']),
            'sent' => collect($result['logs'])->sum('sent_count'),
            'failed' => collect($result['logs'])->sum('failed_count'),
        ];
        $this->pendingConfirmation = false;

        Notification::make()
            ->title($result['successful'] ? 'SMS request completed' : 'SMS request failed')
            ->body("Sent: {$this->lastResult['sent']}; failed: {$this->lastResult['failed']}; invalid: {$this->lastResult['invalid']}")
            ->color($result['successful'] ? 'success' : 'danger')
            ->send();
    }
}
