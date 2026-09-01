{{ $heading }}

{{ $body }}
@if (! empty($actionUrl))

{{ $actionLabel ?? 'Open' }}: {{ $actionUrl }}
@endif

--
MetaCreator.dev
{{ $footerNote ?? 'You are receiving this because you have a MetaCreator account.' }}
