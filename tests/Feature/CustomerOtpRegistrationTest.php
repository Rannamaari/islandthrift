<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SmsDeliveryLog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerOtpRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_sign_in_with_a_dry_run_otp(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('services.dhiraagu_sms.dry_run', true);
        Http::preventStrayRequests();

        $this->get(route('store.register'))
            ->assertOk()
            ->assertSee('Register or sign in by phone')
            ->assertDontSee('name="email"', false);
        $this->get(route('store.home'))
            ->assertOk()
            ->assertSee('>Register</a>', false)
            ->assertDontSee('>Account</a>', false);

        $response = $this->post(route('store.register.otp'), [
            'name' => 'Test Customer',
            'phone' => '7779493',
        ]);

        $response->assertRedirect(route('store.register'))->assertSessionHasNoErrors();
        $code = $response->getSession()->get('store_customer_otp_debug');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $verified = $this->post(route('store.register.verify'), ['otp' => $code]);
        $verified->assertRedirect(route('store.register'))->assertSessionHasNoErrors();

        $customer = Customer::query()->where('phone', '9607779493')->firstOrFail();
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertSame($customer->id, session('store_customer_id'));
        $this->assertDatabaseHas('sms_delivery_logs', ['purpose' => 'customer_otp', 'dry_run' => true]);
        $this->get(route('store.home'))
            ->assertOk()
            ->assertSee('Account profile')
            ->assertSee('Sign out');
    }

    public function test_invalid_phone_is_rejected_without_creating_sms_log(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->from(route('store.register'))->post(route('store.register.otp'), [
            'name' => 'Test Customer',
            'phone' => '123',
        ])->assertRedirect(route('store.register'))->assertSessionHasErrors('phone');

        $this->assertSame(0, SmsDeliveryLog::count());
    }
}
