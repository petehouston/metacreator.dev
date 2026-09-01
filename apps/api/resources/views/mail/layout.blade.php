{{-- Base email shell. Table-based and inline-styled on purpose: Outlook and Gmail
     still discard <style> blocks, floats and most of flexbox. Dark mode is handled
     with a media query that clients supporting it will honour, and a light palette
     that reads correctly in the ones that do not. --}}
<!DOCTYPE html>
<html lang="en" style="margin:0;padding:0;">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $heading }}</title>
<style>
  @media (prefers-color-scheme: dark) {
    .mc-body { background:#0b0c10 !important; }
    .mc-card { background:#15171e !important; border-color:#262a35 !important; }
    .mc-text { color:#e6e8ee !important; }
    .mc-muted { color:#9aa1b1 !important; }
    .mc-rule { border-color:#262a35 !important; }
  }
  @media only screen and (max-width:600px) {
    .mc-card { padding:24px !important; }
    .mc-h1 { font-size:22px !important; }
  }
</style>
</head>
@php
    $siteUrl = rtrim((string) config('app.frontend_url'), '/');

    // Account mail gets the preferences link by default; mail addressed to someone
    // who may have no account (a newsletter confirmation) passes its own footer.
    $preferencesUrl = $preferencesUrl ?? $siteUrl.'/dashboard/settings/notifications';
    $footerNote = $footerNote ?? 'You are receiving this because you have a MetaCreator account.';
@endphp
<body class="mc-body" style="margin:0;padding:0;background:#f4f5f8;">
<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">{{ $preview ?? \Illuminate\Support\Str::limit(strip_tags($body ?? ''), 120) }}</span>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="mc-body" style="background:#f4f5f8;">
<tr><td align="center" style="padding:32px 16px;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

    <tr><td style="padding-bottom:24px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
      {{-- Live text, not the logo PNG: most clients block images by default, and a
           blocked header would leave the mail with no identity at all. Colours match
           the lockup — brand cobalt for the name, signal emerald for the TLD. --}}
      <a href="{{ $siteUrl }}" style="text-decoration:none;color:#2667e7;font-size:18px;font-weight:700;letter-spacing:-0.02em;">MetaCreator<span style="color:#13c990;">.dev</span></a>
    </td></tr>

    <tr><td class="mc-card" style="background:#ffffff;border:1px solid #e5e7ee;border-radius:14px;padding:36px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
      @yield('content')
    </td></tr>

    <tr><td style="padding-top:24px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#8b90a8;" class="mc-muted">
      <p style="margin:0 0 8px;">{{ $footerNote }}</p>
      @if (! empty($preferencesUrl))
        <p style="margin:0 0 8px;"><a href="{{ $preferencesUrl }}" style="color:#8b90a8;">Manage notification preferences</a></p>
      @endif
      <p style="margin:0;">&copy; {{ date('Y') }} MetaCreator.dev</p>
    </td></tr>

  </table>

</td></tr>
</table>
</body>
</html>
