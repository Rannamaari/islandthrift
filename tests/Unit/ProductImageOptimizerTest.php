<?php

namespace Tests\Unit;

use App\Services\ProductImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageOptimizerTest extends TestCase
{
    public function test_it_resizes_and_converts_product_images_to_square_webp_files(): void
    {
        Storage::fake('public');
        $source = UploadedFile::fake()->image('camera.jpg', 1800, 900);

        $path = app(ProductImageOptimizer::class)->store($source);

        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('products/', $path);
        $this->assertStringEndsWith('.webp', $path);

        $optimized = Storage::disk('public')->get($path);
        $dimensions = getimagesizefromstring($optimized);

        $this->assertIsArray($dimensions);
        $this->assertSame(1200, $dimensions[0]);
        $this->assertSame(1200, $dimensions[1]);
        $this->assertSame('image/webp', $dimensions['mime']);
        $this->assertLessThan($source->getSize(), strlen($optimized));
    }

    public function test_it_does_not_upscale_small_product_images(): void
    {
        Storage::fake('public');
        $source = UploadedFile::fake()->image('small.png', 600, 400);

        $path = app(ProductImageOptimizer::class)->store($source);
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertIsArray($dimensions);
        $this->assertSame(600, $dimensions[0]);
        $this->assertSame(600, $dimensions[1]);
    }
}
