<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\DeliveryIncident;
use App\Models\InventoryMovement;
use App\Models\MeasurementUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\User;
use App\Notifications\DeliveryIncidentNotification;
use App\Notifications\PurchaseOrderNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SupplyAlertNotification;
use App\Notifications\TemporaryCredentialsNotification;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Notifications\Messages\MailMessage;

beforeEach(function () {
    config(['app.frontend_url' => 'https://app.pollocharly.test/']);

    $this->user = (new User)->forceFill(['name' => 'Ana Administradora', 'email' => 'ana@pollocharly.test']);

    $this->html = fn (MailMessage $message): string => (string) $message->render();

    $this->supply = (new Supply)->forceFill([
        'code' => 'INS-001',
        'name' => 'Pechuga de pollo',
        'current_stock' => 3,
        'minimum_stock' => 10,
    ])->setRelation('measurementUnit', (new MeasurementUnit)->forceFill(['abbreviation' => 'kg']));
});

test('todos los correos usan la marca de Pollo Charly y ninguno rastro de Laravel', function (string $name, callable $build) {
    $html = ($this->html)($build($this)->toMail($this->user));

    expect($html)
        ->toContain('Pollo Charly')
        ->toContain('https://app.pollocharly.test/icon-192.png')
        ->toContain('lang="es"')
        ->toContain('Todos los derechos reservados')
        ->toContain('(502) 77650852')
        ->not->toContain('Laravel')
        ->not->toContain('laravel.com')
        ->not->toContain('All rights reserved');
})->with([
    'recuperar contraseña' => ['reset', fn () => new ResetPasswordNotification(str_repeat('a1', 32))],
    'credenciales temporales' => ['credenciales', fn () => new TemporaryCredentialsNotification('Temporal-123')],
    'código 2FA' => ['2fa', fn () => new TwoFactorCodeNotification('482915')],
    'alerta de reposición' => ['alerta', fn ($test) => new SupplyAlertNotification(
        (new SupplyAlert)->forceFill(['notes' => 'Se terminó antes de lo previsto'])
            ->setRelation('supply', $test->supply)
            ->setRelation('origin', (object) ['name' => 'automatic']),
    )],
    'ajuste de inventario' => ['ajuste', fn ($test) => new SupplyAlertNotification(
        (new SupplyAlert)->forceFill(['inventory_movement_id' => 1])
            ->setRelation('supply', $test->supply)
            ->setRelation('inventoryMovement', (new InventoryMovement)->forceFill([
                'previous_stock' => 3,
                'new_stock' => 8,
                'reason' => 'Conteo físico',
            ])),
    )],
    'incidencia de entrega' => ['incidencia', fn () => new DeliveryIncidentNotification(
        (new DeliveryIncident)->forceFill(['description' => 'Caja dañada', 'evidence_path' => null])
            ->setRelation('supplier', (new Supplier)->forceFill(['company_name' => 'Avícola San Miguel']))
            ->setRelation('type', (object) ['name' => 'Producto dañado']),
    )],
    'orden de compra' => ['orden', fn ($test) => new PurchaseOrderNotification(purchaseOrderFixture($test->supply)),
    ],
]);

function purchaseOrderFixture(Supply $supply): PurchaseOrder
{
    $item = (new PurchaseOrderItem)->forceFill([
        'ordered_quantity' => 12.5,
        'unit_price' => 32,
        'subtotal' => 400,
    ])->setRelation('supply', $supply);

    return (new PurchaseOrder)->forceFill(['code' => 'OC-20261024-0001', 'total' => 1400, 'expected_date' => '2026-10-28'])
        ->setRelation('supplier', (new Supplier)->forceFill(['company_name' => 'Avícola San Miguel', 'contact_name' => 'Don Mario']))
        ->setRelation('items', collect([$item]));
}

test('el correo de recuperación trae el botón, el código y el texto de apoyo en español', function () {
    $html = ($this->html)((new ResetPasswordNotification('tok123'))->toMail($this->user));

    expect($html)
        ->toContain('¡Hola, Ana Administradora!')
        ->toContain('Restablecer contraseña')
        ->toContain('https://app.pollocharly.test/reset-password?token=tok123&amp;email=')
        ->toContain('tok123')
        ->toContain('Si tienes problemas para hacer clic en el botón')
        ->not->toContain("If you're having trouble");
});

test('la contraseña temporal se muestra literal aunque tenga símbolos de Markdown', function () {
    $html = ($this->html)((new TemporaryCredentialsNotification('a_b*c`d', isReset: true))->toMail($this->user));

    expect($html)
        ->toContain('a_b*c`d')
        ->toContain('restableció tu contraseña')
        ->toContain('https://app.pollocharly.test/login');
});

test('el código de verificación aparece destacado y con su vigencia', function () {
    $html = ($this->html)((new TwoFactorCodeNotification('482915'))->toMail($this->user));

    expect($html)->toContain('482915')->toContain('5 minutos');
});

test('las alertas enlazan con la pantalla correspondiente', function () {
    $alert = (new SupplyAlertNotification(
        (new SupplyAlert)->forceFill(['notes' => null])->setRelation('supply', $this->supply)->setRelation('origin', (object) ['name' => 'manual']),
    ))->toMail($this->user);

    $adjustment = (new SupplyAlertNotification(
        (new SupplyAlert)->forceFill(['inventory_movement_id' => 9])->setRelation('supply', $this->supply),
    ))->toMail($this->user);

    expect(($this->html)($alert))
        ->toContain('Pechuga de pollo (INS-001)')
        ->toContain('3.00 kg')
        ->toContain('https://app.pollocharly.test/dashboard/supply-alerts');

    expect(($this->html)($adjustment))->toContain('https://app.pollocharly.test/dashboard/inventory-movements');
});

test('la orden de compra muestra importes en quetzales y el detalle en tabla', function () {
    $html = ($this->html)((new PurchaseOrderNotification(purchaseOrderFixture($this->supply)))->toMail($this->user));

    expect($html)
        ->toContain('¡Estimado(a) Don Mario!')
        ->toContain('OC-20261024-0001')
        ->toContain('28/10/2026')
        ->toContain('Q1,400.00')
        ->toContain('Q32.00')
        ->toContain('12.5 kg')
        ->toContain('<table')
        ->not->toContain('$1,400');
});

test('los textos libres se escapan para que no inyecten HTML', function () {
    $incident = (new DeliveryIncident)->forceFill(['description' => '<script>alert(1)</script> Caja dañada'])
        ->setRelation('supplier', (new Supplier)->forceFill(['company_name' => 'Avícola']))
        ->setRelation('type', (object) ['name' => 'Otro']);

    $html = ($this->html)((new DeliveryIncidentNotification($incident))->toMail($this->user));

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});
