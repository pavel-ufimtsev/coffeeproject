<yml_catalog date="{{ $date }}">
    <shop>
        <name>{{ config('app.name') }}</name>
        <company>{{ config('app.name') }}</company>
        <url>{{ url('/') }}</url>
        <currencies>
            <currency id="RUR" rate="1"/>
        </currencies>
        <categories>
@foreach ($categories as $category)
            <category id="{{ $category->id }}"@if ($category->parent_id) parentId="{{ $category->parent_id }}"@endif>{{ $category->name }}</category>
@endforeach
        </categories>
        <offers>
@foreach ($products as $product)
@foreach ($product->activeVariants->filter(fn ($v) => (float) $v->price > 0) as $variant)
            <offer id="{{ $variant->retailcrm_offer_id ?? 'v'.$variant->id }}" group_id="{{ $product->id }}" available="true">
                <name>{{ $product->name }}, {{ $variant->label }}</name>
                <url>{{ url($product->path()) }}</url>
                <price>{{ (float) $variant->price }}</price>
@if ($variant->isDiscounted())
                <oldprice>{{ (float) $variant->old_price }}</oldprice>
@endif
                <currencyId>RUR</currencyId>
                <categoryId>{{ $product->category_id }}</categoryId>
@if ($product->mainImage)
                <picture>{{ $product->mainImage->url() }}</picture>
@endif
@if ($product->roaster)
                <vendor>{{ $product->roaster->name }}</vendor>
@endif
@if ($product->summary)
                <description>{{ \Illuminate\Support\Str::limit(str_replace("\n", ' ', $product->summary), 3000) }}</description>
@endif
@if ($variant->weight_g)
                <weight>{{ $variant->weight_g / 1000 }}</weight>
@endif
                <param name="Размер">{{ $variant->label }}</param>
            </offer>
@endforeach
@endforeach
        </offers>
    </shop>
</yml_catalog>
