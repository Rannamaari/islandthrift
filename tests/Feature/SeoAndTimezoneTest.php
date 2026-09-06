<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoAndTimezoneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storefront_exposes_indexable_product_category_and_local_business_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);
        $product = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail();
        $category = $product->category;

        $this->get(route('store.product', $product->slug))
            ->assertOk()
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"valueAddedTaxIncluded":true', false)
            ->assertSee('"addressLocality":"Himmafushi"', false)
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="'.route('store.product', $product->slug).'">', false);

        $this->get(route('store.category', $category->slug))
            ->assertOk()
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee(route('store.product', $product->slug), false)
            ->assertSee('<link rel="canonical" href="'.route('store.category', $category->slug).'">', false);

        $this->get(route('store.shop', ['q' => 'phone']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get(route('store.sitemap'))
            ->assertOk()
            ->assertSee(route('store.category', $category->slug), false)
            ->assertSee(route('store.product', $product->slug), false);
    }

    #[Test]
    public function pos_api_returns_stored_utc_timestamps_as_maldives_time(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $warehouse = Warehouse::factory()->create();
        $admin = User::factory()->forWarehouse($warehouse)->create();
        $admin->assignRole('admin');
        $sale = Sale::factory()->create([
            'company_id' => $warehouse->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Completed,
            'completed_at' => '2026-09-05 20:30:00',
        ]);

        $this->actingAs($admin)
            ->getJson(route('pos.sales.show', $sale))
            ->assertOk()
            ->assertJsonPath('data.completed_at', '2026-09-06T01:30:00+05:00');

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Indian/Maldives', config('app.business_timezone'));
    }

    #[Test]
    public function google_analytics_and_search_console_tags_are_configurable(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('services.google.analytics_measurement_id', 'G-TEST1234');
        config()->set('services.google.site_verification', 'verification-token');
        $product = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail();

        $this->get(route('store.product', $product->slug))
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-TEST1234', false)
            ->assertSee("gtag('config', \"G-TEST1234\")", false)
            ->assertSee("gtag('event', 'view_item'", false)
            ->assertSee('<meta name="google-site-verification" content="verification-token">', false);
    }
}
