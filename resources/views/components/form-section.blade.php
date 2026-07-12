@props(['title', 'help' => null])

<section {{ $attributes->merge(['class' => 'form-section']) }}>
    <div class="mb-5">
        <h2 class="form-section-title">{{ $title }}</h2>
        @if ($help)<p class="form-section-help">{{ $help }}</p>@endif
    </div>
    {{ $slot }}
</section>
