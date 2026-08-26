{{-- Sign-in link. No marketing, no navigation, one action — anything else in a
     security email trains people to click around in security emails. --}}
@extends('mail.layout')

@section('content')
  <h1 class="mc-text mc-h1" style="margin:0 0 16px;font-size:24px;line-height:1.25;font-weight:700;letter-spacing:-0.02em;color:#12141c;">{{ $heading }}</h1>

  <p class="mc-text" style="margin:0 0 28px;font-size:16px;line-height:1.65;color:#3c4152;">{{ $body }}</p>

  @if ($actionUrl && $actionLabel)
    @include('mail.partials.button', ['url' => $actionUrl, 'label' => $actionLabel])
  @endif

  <p class="mc-muted" style="margin:28px 0 0;padding-top:20px;border-top:1px solid #e5e7ee;font-size:13px;line-height:1.6;color:#8b90a8;" class="mc-rule">
    If you did not request this link, no action is needed — it expires on its own and
    nobody can sign in without it.
  </p>
@endsection
