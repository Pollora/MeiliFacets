@props(['handles'])
@foreach ($handles as $handle)
    <input type="hidden" name="{{ $handle->parameter }}" value="{{ $handle->shown }}"
           {{ $handle->bound->hook()->attribute() }}>
@endforeach
