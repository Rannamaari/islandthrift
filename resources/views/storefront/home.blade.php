@extends('layouts.storefront')

@section('title', 'Island Thrift | Electronics & Gadgets in Himmafushi')
@section('description', 'Shop phones, laptops, speakers, accessories and everyday gadgets from Island Thrift in Himmafushi, Maldives.')

@section('content')
<section class="store-container pt-5 sm:pt-8">
    <div class="store-hero relative overflow-hidden rounded-[2rem] px-6 py-12 sm:px-10 lg:grid lg:grid-cols-[1.1fr_.9fr] lg:items-center lg:px-16 lg:py-16">
        <div class="relative z-10 max-w-2xl">
            <span class="store-eyebrow">Your local tech shop</span>
            <h1 class="mt-5 text-4xl font-black leading-[1.04] tracking-[-.045em] text-white sm:text-6xl lg:text-7xl">Tech you want.<br><span class="text-cyan-300">Right here in Himmafushi.</span></h1>
            <p class="mt-6 max-w-xl text-base leading-7 text-indigo-100 sm:text-lg">Shop phones, laptops, speakers, accessories and everyday gadgets from Island Thrift.</p>
            <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('store.shop') }}" class="store-hero-button">Shop Now</a><a href="#featured" class="store-hero-secondary">Explore Products</a></div>
            <div class="mt-9 flex flex-wrap gap-x-7 gap-y-3 text-xs font-bold uppercase tracking-[.12em] text-indigo-200"><span>● Local in Himmafushi</span><span>● Island-wide & Malé delivery</span></div>
        </div>
        <div class="store-device-stage mt-10 lg:mt-0" aria-hidden="true">
            <div class="store-device store-device-phone"><div></div></div>
            <div class="store-device store-device-laptop"><div class="store-device-screen"><span>ISLAND<br>THRIFT</span></div><i></i></div>
            <div class="store-orb store-orb-one"></div><div class="store-orb store-orb-two"></div>
        </div>
    </div>
</section>

<section id="categories" class="store-section store-container">
    <div class="store-section-heading"><div><span class="store-kicker">Find your tech</span><h2>Shop by Category</h2></div><a href="{{ route('store.shop') }}">Browse all →</a></div>
    @if($categories->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-5">
            @foreach($categories as $category)
                <a href="{{ route('store.category', $category->slug) }}" class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg">
                    <span class="mb-7 grid h-12 w-12 place-items-center rounded-2xl bg-indigo-50 text-xl font-black text-indigo-600">{{ strtoupper(substr($category->name, 0, 1)) }}</span>
                    <strong class="block text-base sm:text-lg">{{ $category->name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}</span><span class="absolute right-5 bottom-5 text-indigo-500 transition group-hover:translate-x-1">→</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="store-empty">Categories will appear here when products are enabled for online sale.</div>
    @endif
</section>

<section id="featured" class="store-section store-container">
    <div class="store-section-heading"><div><span class="store-kicker">Chosen for you</span><h2>Featured Products</h2></div><a href="{{ route('store.shop') }}">Shop all →</a></div>
    @if($featured->isNotEmpty())<div class="store-product-grid">@foreach($featured as $product)<x-storefront.product-card :product="$product" />@endforeach</div>@else<div class="store-empty">Enable “Show Online” on products in Filament to publish them here.</div>@endif
</section>

<section class="store-section store-container">
    <div class="store-section-heading"><div><span class="store-kicker">Just added</span><h2>New Arrivals</h2></div><a href="{{ route('store.shop', ['sort' => 'newest']) }}">View newest →</a></div>
    @if($newArrivals->isNotEmpty())<div class="store-product-grid">@foreach($newArrivals as $product)<x-storefront.product-card :product="$product" />@endforeach</div>@else<div class="store-empty">Newly published products will appear automatically.</div>@endif
</section>

<section class="store-section store-container">
    <div class="overflow-hidden rounded-[2rem] bg-slate-950 px-7 py-12 text-white sm:px-12 lg:flex lg:items-center lg:justify-between lg:px-16">
        <div class="max-w-2xl"><span class="store-eyebrow">Island Thrift · Himmafushi</span><h2 class="mt-4 text-3xl font-black tracking-tight sm:text-5xl">Everything tech. One local shop.</h2><p class="mt-4 max-w-xl leading-7 text-slate-300">Phones, laptops, audio, accessories and everyday electronics available from Island Thrift in Himmafushi.</p></div><a href="{{ route('store.shop') }}" class="store-button-light mt-8 lg:mt-0">Browse Shop</a>
    </div>
</section>

<section class="store-section store-container">
    <div class="text-center"><span class="store-kicker">Why Island Thrift</span><h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Local tech, made simple</h2></div>
    <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Local in Himmafushi','Shop electronics without leaving the island.'],['Wide Selection','From phones and laptops to cables and everyday gadgets.'],['Easy Ordering','Browse and order directly through our website.'],['Friendly Support','Contact us if you need help choosing a product.']] as [$title,$copy])
            <div class="rounded-3xl border border-slate-200 bg-white p-6"><span class="mb-5 block h-2 w-10 rounded-full bg-indigo-500"></span><h3 class="font-extrabold">{{ $title }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $copy }}</p></div>
        @endforeach
    </div>
</section>

@if($popular->isNotEmpty())
<section class="store-section store-container"><div class="store-section-heading"><div><span class="store-kicker">Customer favourites</span><h2>Popular Products</h2></div></div><div class="store-product-grid">@foreach($popular as $product)<x-storefront.product-card :product="$product" />@endforeach</div></section>
@endif

<section class="store-section store-container"><div class="rounded-[2rem] bg-gradient-to-r from-indigo-600 to-violet-600 px-7 py-12 text-center text-white sm:px-12"><h2 class="text-3xl font-black tracking-tight sm:text-4xl">Looking for something specific?</h2><p class="mt-3 text-indigo-100">Browse our complete collection of electronics and gadgets.</p><a href="{{ route('store.shop') }}" class="store-button-light mt-7">Shop All Products</a></div></section>
@endsection
