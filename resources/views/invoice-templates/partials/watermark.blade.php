@if(data_get($render, 'watermark.enabled') && data_get($render, 'watermark.data_uri'))
    <div class="invoice-watermark" aria-hidden="true">
        <img src="{{ $render['watermark']['data_uri'] }}" alt="">
    </div>
@endif
