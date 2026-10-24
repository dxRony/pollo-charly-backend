<x-mail::message>
# ¡Hola, {{ $name }}!

Recibimos una solicitud para **restablecer la contraseña** de tu cuenta en Pollo Charly.

<x-mail::button :url="$resetUrl">
Restablecer contraseña
</x-mail::button>

Este enlace expirará en **60 minutos**. Si necesitas ingresar el código de recuperación manualmente, este es:

<x-mail::details :rows="[['label' => 'Código de recuperación', 'value' => $token, 'mono' => true]]" />

Si no solicitaste este cambio, no es necesario realizar ninguna acción; tu cuenta sigue completamente segura.

Atentamente,<br>
el equipo de Pollo Charly

<x-slot:subcopy>
Si tienes problemas para hacer clic en el botón «Restablecer contraseña», copia y pega esta dirección en tu navegador: <span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
