@extends('layouts.storefront')
@section('title', ($currentCategory?->name ? $currentCategory->name.' | ' : '').'Shop Electronics | Island Thrift')
@section('description', $currentCategory?->description ?: 'Browse phones, laptops, audio, accessories and gadgets available from Island Thrift in Himmafushi.')

@section('content')
<section class="store-container pt-10 sm:pt-14">
    <span class="store-kicker">Island Thrift catalogue</span><h1 class="mt-2 text-4xl font-black tracking-tight sm:text-5xl">{{ $currentCategory?->name ?? 'Shop all products' }}</h1><p class="mt-3 max-w-2xl text-slate-600">Find useful electronics and gadgets, powered by the same live catalogue and stock used in our shop.</p>
</section>
<section id="categories" class="store-container mt-8 flex gap-2 overflow-x-auto pb-2"><a href="{{ route('store.shop') }}" class="store-filter-pill {{ !request('category') ? 'active' : '' }}">All</a>@foreach($categories as $category)<a href="{{ route('store.category', $category->slug) }}" class="store-filter-pill {{ request('category') === $category->id ? 'active' : '' }}">{{ $category->name }}</a>@endforeach</section>
<section class="store-container mt-6 grid items-start gap-8 lg:grid-cols-[260px_1fr]">
    <details class="rounded-3xl border border-slate-200 bg-white p-5 lg:hidden"><summary class="cursor-pointer font-extrabold">Filters & sorting</summary><div class="mt-5">@include('storefront.partials.filters')</div></details>
    <aside class="sticky top-28 hidden rounded-3xl border border-slate-200 bg-white p-6 lg:block"><h2 class="mb-5 text-lg font-black">Filter products</h2>@include('storefront.partials.filters')</aside>
    <div>
        <div class="mb-5 flex items-center justify-between gap-3"><p class="text-sm text-slate-500">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p></div>
        @if($products->isNotEmpty())<div class="store-product-grid">@foreach($products as $product)<x-storefront.product-card :product="$product" />@endforeach</div><div class="mt-10">{{ $products->links() }}</div>@else<div class="store-empty py-16"><strong class="block text-lg text-slate-800">No products found</strong><span class="mt-2 block">Try adjusting your search or filters.</span></div>@endif
    </div>
</section>
@endsection
