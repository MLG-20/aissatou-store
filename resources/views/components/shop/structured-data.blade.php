@props(['blocks'])

@foreach (array_filter($blocks) as $block)
    <script type="application/ld+json">{!! \App\Support\StructuredData::encode($block) !!}</script>
@endforeach
