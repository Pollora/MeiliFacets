{{-- The client writes a pill's label before the button's own content: the mark after it is the theme's. --}}
<ul {{ $attributes->class('meilifacetsActiveValues') }} aria-label="{{ __('Active filters') }}"
    @if ($values === []) hidden @endif {{ $hook('active-values') }}>
    @foreach ($values as $value)
        <li class="meilifacetsActiveValue"><button type="button" name="{{ $value->parameter }}" value="{{ $value->value }}" data-kind="{{ $value->kind->value }}" aria-label="{{ $value->action }}" {{ $hook('active-value') }}>{{ $value->label }}<span aria-hidden="true">✕</span></button></li>
    @endforeach
    <template {{ $hook('active-value-template') }}>
        <li class="meilifacetsActiveValue"><button type="button" {{ $hook('active-value') }}><span aria-hidden="true">✕</span></button></li>
    </template>
</ul>
