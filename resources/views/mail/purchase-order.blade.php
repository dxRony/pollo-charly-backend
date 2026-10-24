<x-mail::message>
# ¡Estimado(a) {{ $contactName }}!

Se generó una nueva **orden de compra** para **{{ $companyName }}**:

<x-mail::details :rows="$rows" />

@if (count($items) > 0)
## Detalle de productos solicitados

<x-mail::table>
| Producto | Cantidad | Precio | Subtotal |
| :-- | --: | --: | --: |
@foreach ($items as $item)
| {{ $item['name'] }} | {{ $item['quantity'] }} | {{ $item['price'] }} | {{ $item['subtotal'] }} |
@endforeach
</x-mail::table>
@endif

Por favor confirma la recepción de esta orden y la fecha estimada de entrega.

Atentamente,<br>
Pollo Charly
</x-mail::message>
