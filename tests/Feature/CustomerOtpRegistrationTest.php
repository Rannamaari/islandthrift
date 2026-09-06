<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
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

        $product = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail();
        $this->post(route('store.cart.add'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->get(route('store.checkout'))
            ->assertOk()
            ->assertSee('Log in / Register');
        $this->get(route('store.register', ['redirect' => 'checkout']))->assertOk();

        $response = $this->post(route('store.register.otp'), [
            'name' => 'Test Customer',
            'phone' => '7779493',
        ]);

        $response->assertRedirect(route('store.register'))->assertSessionHasNoErrors();
        $code = $response->getSession()->get('store_customer_otp_debug');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $verified = $this->post(route('store.register.verify'), ['otp' => $code]);
        $verified->assertRedirect(route('store.checkout'))->assertSessionHasNoErrors();

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

    public function test_existing_customer_can_sign_in_by_phone_without_changing_their_name(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('services.dhiraagu_sms.dry_run', true);
        Http::preventStrayRequests();
        $company = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail()->company;
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'code' => 'WEB-EXISTING',
            'name' => 'Existing Customer',
            'phone' => '9607779493',
            'phone_verified_at' => now()->subDay(),
            'opening_balance' => 0,
            'is_active' => true,
            'is_walk_in' => false,
        ]);

        $response = $this->post(route('store.register.otp'), [
            'name' => '',
            'phone' => '7779493',
        ]);
        $response->assertRedirect(route('store.register'))->assertSessionHasNoErrors();

        $verified = $this->post(route('store.register.verify'), [
            'otp' => $response->getSession()->get('store_customer_otp_debug'),
        ]);

        $verified->assertRedirect(route('store.register'))->assertSessionHasNoErrors();
        $this->assertSame($customer->id, session('store_customer_id'));
        $this->assertSame('Existing Customer', $customer->fresh()->name);
    }

    public function test_new_customer_must_enter_a_name_before_an_otp_is_sent(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('services.dhiraagu_sms.dry_run', true);
        Http::preventStrayRequests();

        $this->from(route('store.register'))->post(route('store.register.otp'), [
            'name' => '',
            'phone' => '7779493',
        ])->assertRedirect(route('store.register'))->assertSessionHasErrors('name');

        $this->assertSame(0, SmsDeliveryLog::count());
    }
}
