{{-- The default body for any catalog event: heading, prose, one call to action. --}}
@extends('mail.layout')

@section('content')
  <h1 class="mc-text mc-h1" style="margin:0 0 16px;font-size:24px;line-height:1.25;font-weight:700;letter-spacing:-0.02em;color:#12141c;">{{ $heading }}</h1>

  <p class="mc-text" style="margin:0 0 28px;font-size:16px;line-height:1.65;color:#3c4152;">{{ $body }}</p>

  @if ($actionUrl && $actionLabel)
    @include('mail.partials.button', ['url' => $actionUrl, 'label' => $actionLabel])
  @endif
@endsection
