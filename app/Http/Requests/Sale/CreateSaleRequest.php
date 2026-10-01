<?php

declare(strict_types=1);

namespace App\Http\Requests\Sale;

use App\Models\PaymentMethod;
use App\Models\ReceiptType;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateSaleRequest',
    title: 'Create Sale Request',
    description: 'Datos necesarios para registrar la venta comercial, realizar el cobro y cerrar la comanda',
    required: ['order_id'],
    properties: [
        new OA\Property(property: 'order_id', description: 'ID de la comanda en estado lista a cobrar', type: 'integer', example: 1),
        new OA\Property(property: 'payment_method', description: 'Método de pago utilizado: efectivo, tarjeta o transferencia', type: 'string', enum: ['efectivo', 'tarjeta', 'transferencia'], example: 'efectivo', nullable: true),
        new OA\Property(property: 'payment_method_id', description: 'ID del método de pago en base de datos', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'received_amount', description: 'Monto en efectivo recibido del cliente (obligatorio para pagos en efectivo)', type: 'number', format: 'float', nullable: true, example: 100.00),
        new OA\Property(property: 'receipt_type', description: 'Tipo de comprobante: ticket o factura (por defecto ticket)', type: 'string', enum: ['ticket', 'factura'], example: 'ticket', nullable: true),
        new OA\Property(property: 'receipt_type_id', description: 'ID del tipo de comprobante en base de datos', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'discount', description: 'Descuento aplicado a la venta (por defecto 0.00)', type: 'number', format: 'float', nullable: true, example: 0.00),
        new OA\Property(property: 'tax', description: 'Impuesto aplicable (por defecto 0.00)', type: 'number', format: 'float', nullable: true, example: 0.00),
    ]
)]
class CreateSaleRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para registrar ventas.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::MESERO_CAJERO, Role::ADMINISTRADOR) ?? false;
    }

    /**
     * Respuesta personalizada en español cuando el usuario no tiene permisos.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para registrar ventas. Se requiere rol de Mesero/Cajero o Administrador.',
        ], 403));
    }

    /**
     * Prepara los datos antes de la validación, soportando order_id desde la ruta.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('id') && ! $this->has('order_id')) {
            $this->merge(['order_id' => $this->route('id')]);
        }
    }

    /**
     * Reglas de validación aplicadas a la solicitud de venta.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_method' => ['nullable', 'string', 'in:efectivo,tarjeta,transferencia'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'receipt_type' => ['nullable', 'string', 'in:ticket,factura'],
            'receipt_type_id' => ['nullable', 'integer', 'exists:receipt_types,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Debe indicar la comanda a la cual corresponde la venta.',
            'order_id.exists' => 'La comanda seleccionada no existe en el registro.',
            'payment_method.in' => 'El método de pago debe ser: efectivo, tarjeta o transferencia.',
            'payment_method_id.exists' => 'El método de pago seleccionado no es válido.',
            'received_amount.numeric' => 'El monto recibido debe ser un valor numérico.',
            'received_amount.min' => 'El monto recibido no puede ser negativo.',
            'receipt_type.in' => 'El tipo de comprobante debe ser: ticket o factura.',
            'receipt_type_id.exists' => 'El tipo de comprobante seleccionado no es válido.',
            'discount.min' => 'El descuento no puede ser negativo.',
            'tax.min' => 'El impuesto no puede ser negativo.',
        ];
    }

    /**
     * Validación condicional según el método de pago elegido.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $paymentMethodName = $this->getResolvedPaymentMethodName();

            if ($paymentMethodName === PaymentMethod::EFECTIVO) {
                if ($this->input('received_amount') === null || $this->input('received_amount') === '') {
                    $v->errors()->add('received_amount', 'Para pagos en efectivo debe ingresar el monto recibido del cliente para calcular el vuelto.');
                }
            }
        });
    }

    /**
     * Obtiene el nombre canónico del método de pago.
     */
    public function getResolvedPaymentMethodName(): string
    {
        $method = $this->input('payment_method');
        if (! empty($method)) {
            return (string) $method;
        }

        $methodId = $this->input('payment_method_id');
        if (! empty($methodId)) {
            $record = PaymentMethod::query()->find($methodId);
            if ($record) {
                return $record->name;
            }
        }

        return PaymentMethod::EFECTIVO;
    }

    /**
     * Obtiene el nombre canónico del tipo de comprobante.
     */
    public function getResolvedReceiptTypeName(): string
    {
        $type = $this->input('receipt_type');
        if (! empty($type)) {
            return (string) $type;
        }

        $typeId = $this->input('receipt_type_id');
        if (! empty($typeId)) {
            $record = ReceiptType::query()->find($typeId);
            if ($record) {
                return $record->name;
            }
        }

        return ReceiptType::TICKET;
    }
}
