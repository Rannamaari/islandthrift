{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <url><loc>{{ route('store.home') }}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
    <url><loc>{{ route('store.shop') }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
    <url><loc>{{ route('store.contact') }}</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
    @foreach($categories as $category)
        <url><loc>{{ route('store.category', $category->slug) }}</loc><lastmod>{{ $category->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>
    @endforeach
    @foreach($products as $product)
        <url><loc>{{ route('store.product', $product->slug) }}</loc><lastmod>{{ $product->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority>@if(collect($product->images)->first())<image:image><image:loc>{{ \Illuminate\Support\Facades\Storage::disk('public')->url(collect($product->images)->first()) }}</image:loc><image:title>{{ $product->name }}</image:title></image:image>@endif</url>
    @endforeach
</urlset>
