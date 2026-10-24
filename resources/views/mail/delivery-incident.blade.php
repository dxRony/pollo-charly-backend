<x-mail::message>
# ¡Hola, {{ $name }}!

Se registró una **recepción no conforme** durante la entrega de un pedido:

<x-mail::details :rows="$rows" />

<x-mail::button :url="$actionUrl">
Revisar compras y proveedores
</x-mail::button>

Por favor revisa el módulo de compras y proveedores para gestionar la corrección o reposición de productos con el proveedor.

Atentamente,<br>
el sistema de Pollo Charly
</x-mail::message>
