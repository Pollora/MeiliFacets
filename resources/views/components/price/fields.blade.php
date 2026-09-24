@props(['handles', 'bounds', 'money'])
<div class="meilifacetsPriceFields">
    @foreach ($handles as $handle)
        <label class="meilifacetsPriceField">
            <span>{{ $handle->bound->label() }}</span>
            <span class="meilifacetsPriceInput">
                <input type="number" inputmode="numeric" step="any"
                       name="{{ $handle->parameter }}" value="{{ $handle->shown }}"
                       min="{{ $bounds->min }}" max="{{ $bounds->max }}"
                       placeholder="{{ $handle->bound->of($bounds) }}"
                       {{ $handle->bound->hook()->attribute() }}>
                <span aria-hidden="true">{{ $money->symbol() }}</span>
            </span>
        </label>
        @if (! $loop->last)
            <span class="meilifacetsPriceDash" aria-hidden="true">–</span>
        @endif
    @endforeach
</div>
