<x-mail::message>
# ¡Hola, {{ $name }}!

@if ($isReset)
Un administrador **restableció tu contraseña**. Estas son tus nuevas credenciales temporales:
@else
Se creó una cuenta para ti en el sistema de **Pollo Charly**. Estas son tus credenciales temporales:
@endif

<x-mail::details :rows="[
    ['label' => 'Correo', 'value' => $email],
    ['label' => 'Contraseña temporal', 'value' => $password, 'mono' => true],
]" />

<x-mail::button :url="$loginUrl">
Iniciar sesión
</x-mail::button>

Por seguridad, el sistema te pedirá **cambiar esta contraseña** la primera vez que inicies sesión.

Si no esperabas este correo, comunícate con la administración.

Atentamente,<br>
el equipo de Pollo Charly
</x-mail::message>
