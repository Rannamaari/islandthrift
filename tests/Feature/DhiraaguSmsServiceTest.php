<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SmsDeliveryLog;
use App\Models\User;
use App\Services\DhiraaguSmsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DhiraaguSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_deduplicates_and_sends_in_configured_batches(): void
    {
        config()->set('services.dhiraagu_sms', [
            'base_url' => 'https://messaging.example.test/v1/api',
            'auth_key' => 'test-key',
            'source' => 'Micronet',
            'dry_run' => false,
            'chunk_size' => 2,
            'timeout' => 20,
        ]);
        Http::fake(['*' => Http::response([
            'transactionStatus' => 'true',
            'transactionId' => 'TX-123',
            'transactionDescription' => 'Accepted',
            'referenceNumber' => 'REF-123',
        ], 200)]);
        $company = Company::factory()->create();

        $result = app(DhiraaguSmsService::class)->send(
            ['7779493', '+960 7779493', '9608888888', 'invalid', '9999999'],
            'Test message',
            $company->id,
        );

        $this->assertTrue($result['successful']);
        $this->assertSame(['9607779493', '9608888888', '9609999999'], $result['valid']);
        $this->assertSame(['invalid'], $result['invalid']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://messaging.example.test/v1/api/sms'
            && $request['authorizationKey'] === 'test-key'
            && $request['source'] === 'Micronet'
            && ! $request->hasHeader('Authorization'));
        $this->assertSame(2, SmsDeliveryLog::count());
        $this->assertSame(3, SmsDeliveryLog::sum('sent_count'));
    }

    public function test_dry_run_never_calls_the_gateway(): void
    {
        config()->set('services.dhiraagu_sms.dry_run', true);
        Http::preventStrayRequests();
        $company = Company::factory()->create();

        $result = app(DhiraaguSmsService::class)->send('7779493', 'Dry run', $company->id);

        $this->assertTrue($result['successful']);
        Http::assertNothingSent();
        $this->assertDatabaseHas('sms_delivery_logs', [
            'company_id' => $company->id,
            'dry_run' => true,
            'successful' => true,
            'sent_count' => 1,
        ]);
    }

    public function test_admin_can_open_sms_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@islandthrift.local')->firstOrFail();

        $this->actingAs($admin)->get('/admin/sms-messaging')
            ->assertOk()
            ->assertSee('Dhiraagu SMS')
            ->assertSee('DRY RUN');
    }
}
