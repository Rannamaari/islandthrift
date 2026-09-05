<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('website_enabled')->default(true)->index();
            $table->uuid('online_branch_id')->nullable();
            $table->uuid('online_warehouse_id')->nullable();
            $table->string('website_whatsapp')->nullable();
            $table->json('website_social_links')->nullable();
            $table->json('website_delivery_methods')->nullable();
            $table->json('website_payment_methods')->nullable();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('slug')->nullable();
            $table->string('image_path')->nullable();
            $table->unique(['company_id', 'slug']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('slug')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->json('images')->nullable();
            $table->decimal('sale_price', 15, 4)->nullable();
            $table->boolean('show_online')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'show_online', 'is_active', 'created_at']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('sales_channel')->default('pos')->index();
            $table->string('order_status')->nullable()->index();
            $table->string('payment_status')->nullable()->index();
            $table->string('website_payment_method')->nullable();
            $table->string('delivery_method')->nullable();
            $table->decimal('delivery_charge', 15, 4)->default(0);
            $table->text('delivery_address')->nullable();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->index(['company_id', 'sales_channel', 'order_status']);
        });

        DB::table('categories')->orderBy('id')->each(function (object $category): void {
            DB::table('categories')->where('id', $category->id)->update([
                'slug' => (Str::slug($category->name) ?: 'category').'-'.Str::lower(substr($category->id, 0, 6)),
            ]);
        });

        DB::table('products')->orderBy('id')->each(function (object $product): void {
            DB::table('products')->where('id', $product->id)->update([
                'slug' => Str::slug("{$product->name}-{$product->sku}") ?: Str::lower($product->sku),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'sales_channel', 'order_status']);
            $table->dropUnique(['tracking_token']);
            $table->dropColumn([
                'sales_channel', 'order_status', 'payment_status', 'website_payment_method',
                'delivery_method', 'delivery_charge', 'delivery_address', 'tracking_token',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'show_online', 'is_active', 'created_at']);
            $table->dropUnique(['company_id', 'slug']);
            $table->dropColumn(['slug', 'short_description', 'images', 'sale_price', 'show_online', 'is_featured']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'slug']);
            $table->dropColumn(['slug', 'image_path']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'website_enabled', 'online_branch_id', 'online_warehouse_id', 'website_whatsapp',
                'website_social_links', 'website_delivery_methods', 'website_payment_methods',
            ]);
        });
    }
};
