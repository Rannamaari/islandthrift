{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('store.home') }}</loc></url>
    <url><loc>{{ route('store.shop') }}</loc></url>
    <url><loc>{{ route('store.contact') }}</loc></url>
    @foreach($products as $product)
        <url><loc>{{ route('store.product', $product->slug) }}</loc><lastmod>{{ $product->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
</urlset>
