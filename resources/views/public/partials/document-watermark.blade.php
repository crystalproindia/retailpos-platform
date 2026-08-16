@if(data_get($presentation, 'watermark.enabled') && data_get($presentation, 'watermark.data_uri'))
    <img src="{{ $presentation['watermark']['data_uri'] }}" alt="" aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 z-0 max-h-[45%] w-2/3 -translate-x-1/2 -translate-y-1/2 object-contain opacity-[0.12]">
@endif
