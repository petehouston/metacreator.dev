{{-- Payment receipt. People forward these to accountants, so the facts table matters
     more than the prose. --}}
@extends('mail.layout')

@section('content')
  <h1 class="mc-text mc-h1" style="margin:0 0 16px;font-size:24px;line-height:1.25;font-weight:700;letter-spacing:-0.02em;color:#12141c;">{{ $heading }}</h1>

  <p class="mc-text" style="margin:0 0 24px;font-size:16px;line-height:1.65;color:#3c4152;">{{ $body }}</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;border:1px solid #e5e7ee;border-radius:10px;">
    @foreach (['number' => 'Invoice', 'plan' => 'Plan', 'amount' => 'Amount', 'paid_at' => 'Paid'] as $key => $label)
      @if (! empty($payload[$key]))
        <tr>
          <td class="mc-muted" style="padding:12px 16px;font-size:14px;color:#6b7285;border-bottom:1px solid #f0f1f5;">{{ $label }}</td>
          <td class="mc-text" style="padding:12px 16px;font-size:14px;font-weight:600;text-align:right;color:#12141c;border-bottom:1px solid #f0f1f5;">{{ $payload[$key] }}</td>
        </tr>
      @endif
    @endforeach
  </table>

  @if ($actionUrl && $actionLabel)
    @include('mail.partials.button', ['url' => $actionUrl, 'label' => $actionLabel])
  @endif
@endsection
