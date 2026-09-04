@php($message = __(
    'La recherche est momentanément indisponible. Merci de réessayer dans quelques instants.',
    'meilifacets'
))
<div class="meilifacetsUnavailable" role="alert">
    <p>{{ $message }}</p>
</div>
