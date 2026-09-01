{{-- Bulletproof button: a table cell rather than a styled <a>, so Outlook renders
     the background instead of a bare link. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
  <tr><td align="center" bgcolor="#2667e7" style="border-radius:10px;">
    <a href="{{ $url }}" style="display:inline-block;padding:13px 26px;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">{{ $label }}</a>
  </td></tr>
</table>

<p class="mc-muted" style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#8b90a8;word-break:break-all;">
  Button not working? Paste this into your browser:<br>
  <a href="{{ $url }}" style="color:#2667e7;">{{ $url }}</a>
</p>
