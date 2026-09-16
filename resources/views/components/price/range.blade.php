@use(Modules\MeiliFacets\Enums\Hook)
@props(['label', 'readout', 'fill', 'handles', 'bounds', 'money'])
<p class="meilifacetsRangeHead">
    <span aria-hidden="true">{{ $label }}</span>
    <span class="meilifacetsRangeReadout" {{ Hook::PriceReadout->attribute() }}>{{ $readout }}</span>
</p>
<div class="meilifacetsRange" style="--from: {{ $fill->from }}; --to: {{ $fill->to }}" {{ Hook::PriceRange->attribute() }}>
    <div class="meilifacetsRangeTrack" {{ Hook::PriceTrack->attribute() }}>
        <div class="meilifacetsRangeFill" aria-hidden="true"></div>
        @foreach ($handles as $handle)
            <button type="button" role="slider" class="meilifacetsRangeHandle"
                    style="--at: {{ $handle->at }}"
                    aria-label="{{ $handle->bound->handleLabel() }}"
                    aria-valuemin="{{ $handle->floor }}" aria-valuemax="{{ $handle->ceiling }}"
                    aria-valuenow="{{ $handle->value }}" aria-valuetext="{{ $handle->written() }}"
                    data-bound="{{ $handle->bound->value }}" {{ Hook::PriceHandle->attribute() }}>
                <span class="meilifacetsRangeTip" {{ Hook::PriceTip->attribute() }}>{{ $handle->written() }}</span>
            </button>
        @endforeach
    </div>
</div>
<p class="meilifacetsRangeBounds">
    <span {{ Hook::PriceBoundsMin->attribute() }}>{{ $money->of($bounds->min) }}</span><span {{ Hook::PriceBoundsMax->attribute() }}>{{ $money->of($bounds->max) }}</span>
</p>
