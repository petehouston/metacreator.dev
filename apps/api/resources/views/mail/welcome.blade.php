{{-- Welcome gets its own template: it is the one transactional email people actually
     read end to end, so it earns the extra "what to do next" block. --}}
@extends('mail.layout')

@section('content')
  <h1 class="mc-text mc-h1" style="margin:0 0 16px;font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-0.02em;color:#12141c;">{{ $heading }}</h1>

  <p class="mc-text" style="margin:0 0 24px;font-size:16px;line-height:1.65;color:#3c4152;">{{ $body }}</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
    @foreach ([
      ['Find your next hook', 'Headline analyzer scores a title before you commit to filming it.'],
      ['Post at the right size', 'Character counters and thread splitters for every platform.'],
      ['Know what is working', 'Engagement rate and UTM tracking, without a spreadsheet.'],
    ] as [$title, $copy])
      <tr><td style="padding:0 0 14px;">
        <p class="mc-text" style="margin:0 0 2px;font-size:15px;font-weight:600;color:#12141c;">{{ $title }}</p>
        <p class="mc-muted" style="margin:0;font-size:14px;line-height:1.6;color:#6b7285;">{{ $copy }}</p>
      </td></tr>
    @endforeach
  </table>

  @if ($actionUrl && $actionLabel)
    @include('mail.partials.button', ['url' => $actionUrl, 'label' => $actionLabel])
  @endif
@endsection
