<div {{ $attributes }} {{ $hook('search') }} data-search="{{ $root->name }}" {{ $contract }}>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <x-meilifacets::search-toggle :name="$root->name">
            @isset($icon)
                <x-slot:icon>{{ $icon }}</x-slot:icon>
            @endisset
        </x-meilifacets::search-toggle>
        <x-meilifacets::search-panel :name="$root->name">
            <x-meilifacets::search-input :name="$root->name" />
            @foreach ($sectionTypes() as $postType)
                <x-meilifacets::search-section :name="$root->name" :type="$postType" />
            @endforeach
            <x-meilifacets::search-empty />
            <x-meilifacets::search-unavailable />
        </x-meilifacets::search-panel>
    @endif
</div>
