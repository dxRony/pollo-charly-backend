<x-mail::message>
# ¡Hola, {{ $name }}!

Has solicitado iniciar sesión en el sistema de Pollo Charly. Tu código de verificación de 6 dígitos es:

<x-mail::code>
{{ $code }}
</x-mail::code>

Este código de un solo uso expirará en **5 minutos**.

Si no fuiste tú quien intentó iniciar sesión, te recomendamos **cambiar tu contraseña de inmediato** para proteger tu cuenta.

Atentamente,<br>
el equipo de Pollo Charly
</x-mail::message>
