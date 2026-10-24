<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Pollo Charly. Todos los derechos reservados.

Este es un correo automático; por favor no respondas a este mensaje.<br>
¿Necesitas ayuda? Llámanos o escríbenos por WhatsApp al (502) 77650852.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
