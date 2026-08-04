@props(['url'])
@php
    $appLabel = trim(strip_tags((string) $slot));
    $appInitial = mb_strtoupper(mb_substr($appLabel, 0, 1));
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
{{-- Il quadrato con l'iniziale e' decorativo: ripete il nome scritto nella
     cella accanto. Il ramo con il logo remoto di Laravel e' stato rimosso —
     il nome dell'app non e' mai "Laravel", e un'immagine remota in una email
     viene bloccata dai client per default e traccia l'apertura. --}}
<span class="header-mark" aria-hidden="true">{{ $appInitial }}</span><span class="header-name">{{ $appLabel }}</span>
</a>
</td>
</tr>
