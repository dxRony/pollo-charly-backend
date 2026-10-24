<x-mail::message>
# ¡Hola, {{ $name }}!

{{ $intro }}

<x-mail::details :rows="$rows" />

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

{{ $closing }}

Atentamente,<br>
el sistema de Pollo Charly
</x-mail::message>
