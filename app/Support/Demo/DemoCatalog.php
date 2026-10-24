<?php

declare(strict_types=1);

namespace App\Support\Demo;

use App\Models\Role;

/**
 * Datos ficticios de un restaurante de pollo frito. Las cantidades de las recetas y los mínimos de stock están
 * calculados para que, con unos 20 platillos vendidos al día, el inventario se agote y se reponga de forma natural.
 */
final class DemoCatalog
{
    /**
     * @return array<int, array{name: string, email: string, role: string}>
     */
    public static function users(): array
    {
        return [
            ['name' => 'Carlos Méndez', 'email' => 'mesero1@demo.pollocharly.test', 'role' => Role::MESERO_CAJERO],
            ['name' => 'Lucía Ramírez', 'email' => 'mesero2@demo.pollocharly.test', 'role' => Role::MESERO_CAJERO],
            ['name' => 'Roberto Pérez', 'email' => 'cocinero1@demo.pollocharly.test', 'role' => Role::COCINERO],
            ['name' => 'Marta López', 'email' => 'cocinero2@demo.pollocharly.test', 'role' => Role::COCINERO],
        ];
    }

    /**
     * Insumos: stock inicial y mínimo (alerta de reposición), costo por unidad y unidad de medida (abreviatura).
     *
     * @return array<string, array{name: string, unit: string, cost: float, minimum: float, initial: float}>
     */
    public static function supplies(): array
    {
        return [
            'POL' => ['name' => 'Pollo trozado', 'unit' => 'kg', 'cost' => 32.00, 'minimum' => 55, 'initial' => 200],
            'HAR' => ['name' => 'Harina de trigo', 'unit' => 'kg', 'cost' => 6.50, 'minimum' => 6, 'initial' => 30],
            'ACE' => ['name' => 'Aceite vegetal', 'unit' => 'l', 'cost' => 22.00, 'minimum' => 12, 'initial' => 60],
            'SAZ' => ['name' => 'Sazonador para pollo', 'unit' => 'kg', 'cost' => 28.00, 'minimum' => 1.5, 'initial' => 8],
            'PAP' => ['name' => 'Papa', 'unit' => 'kg', 'cost' => 7.00, 'minimum' => 20, 'initial' => 60],
            'REP' => ['name' => 'Repollo', 'unit' => 'kg', 'cost' => 5.00, 'minimum' => 6, 'initial' => 25],
            'ZAN' => ['name' => 'Zanahoria', 'unit' => 'kg', 'cost' => 5.50, 'minimum' => 2, 'initial' => 10],
            'TOR' => ['name' => 'Tortillas', 'unit' => 'u', 'cost' => 0.50, 'minimum' => 120, 'initial' => 500],
            'ARR' => ['name' => 'Arroz', 'unit' => 'kg', 'cost' => 9.00, 'minimum' => 6, 'initial' => 25],
            'FRI' => ['name' => 'Frijol negro', 'unit' => 'kg', 'cost' => 12.00, 'minimum' => 5, 'initial' => 20],
            'KET' => ['name' => 'Salsa de tomate', 'unit' => 'l', 'cost' => 25.00, 'minimum' => 2, 'initial' => 8],
            'MAY' => ['name' => 'Mayonesa', 'unit' => 'l', 'cost' => 34.00, 'minimum' => 1.5, 'initial' => 6],
            'GAS' => ['name' => 'Gaseosa personal', 'unit' => 'u', 'cost' => 6.00, 'minimum' => 40, 'initial' => 150],
            'AGU' => ['name' => 'Agua pura', 'unit' => 'u', 'cost' => 3.00, 'minimum' => 20, 'initial' => 80],
            'LIM' => ['name' => 'Limón', 'unit' => 'kg', 'cost' => 8.00, 'minimum' => 3, 'initial' => 10],
            'AZU' => ['name' => 'Azúcar', 'unit' => 'kg', 'cost' => 8.50, 'minimum' => 3, 'initial' => 15],
            'PAN' => ['name' => 'Pan para sándwich', 'unit' => 'u', 'cost' => 1.50, 'minimum' => 20, 'initial' => 60],
            'FLA' => ['name' => 'Flan de vainilla', 'unit' => 'porc', 'cost' => 5.00, 'minimum' => 10, 'initial' => 40],
        ];
    }

    /**
     * @return array<string, array{description: string, price: float, supplies: array<string, float>}>
     */
    public static function complements(): array
    {
        return [
            'Papas fritas' => ['description' => 'Porción de papas fritas crujientes', 'price' => 10.00, 'supplies' => ['PAP' => 0.20, 'ACE' => 0.03]],
            'Ensalada de repollo' => ['description' => 'Repollo y zanahoria con aderezo', 'price' => 8.00, 'supplies' => ['REP' => 0.10, 'ZAN' => 0.03, 'MAY' => 0.02]],
            'Arroz blanco' => ['description' => 'Porción de arroz blanco', 'price' => 8.00, 'supplies' => ['ARR' => 0.12]],
            'Frijoles volteados' => ['description' => 'Frijol negro volteado', 'price' => 8.00, 'supplies' => ['FRI' => 0.10]],
            'Tortillas (3 unidades)' => ['description' => 'Tres tortillas calientes', 'price' => 4.00, 'supplies' => ['TOR' => 3.0]],
            'Salsa extra' => ['description' => 'Porción adicional de salsa de tomate', 'price' => 3.00, 'supplies' => ['KET' => 0.03]],
        ];
    }

    /**
     * Platillos. "weight" es la popularidad relativa; "with_sides" indica si admite complementos.
     *
     * @return array<string, array{category: string, price: float, description: string, weight: int, with_sides: bool, recipe: array<string, float>}>
     */
    public static function dishes(): array
    {
        return [
            'Pollo frito 2 piezas' => ['category' => 'Pollos', 'price' => 38.00, 'weight' => 18, 'with_sides' => true,
                'description' => 'Dos piezas de pollo frito, doradas y crujientes, con la receta de la casa.',
                'recipe' => ['POL' => 0.45, 'HAR' => 0.05, 'ACE' => 0.06, 'SAZ' => 0.005]],
            'Pollo frito 3 piezas' => ['category' => 'Pollos', 'price' => 52.00, 'weight' => 14, 'with_sides' => true,
                'description' => 'Tres piezas de pollo frito jugoso, ideal para un buen almuerzo.',
                'recipe' => ['POL' => 0.65, 'HAR' => 0.07, 'ACE' => 0.08, 'SAZ' => 0.007]],
            'Medio pollo frito' => ['category' => 'Pollos', 'price' => 95.00, 'weight' => 8, 'with_sides' => true,
                'description' => 'Medio pollo frito en piezas, para compartir entre dos personas.',
                'recipe' => ['POL' => 1.0, 'HAR' => 0.12, 'ACE' => 0.12, 'SAZ' => 0.01]],
            'Pollo entero frito' => ['category' => 'Pollos', 'price' => 175.00, 'weight' => 3, 'with_sides' => true,
                'description' => 'Pollo entero frito en ocho piezas, perfecto para la familia.',
                'recipe' => ['POL' => 2.0, 'HAR' => 0.22, 'ACE' => 0.2, 'SAZ' => 0.02]],
            'Alitas crujientes (8 unidades)' => ['category' => 'Pollos', 'price' => 48.00, 'weight' => 9, 'with_sides' => true,
                'description' => 'Ocho alitas fritas y crujientes con salsa de la casa.',
                'recipe' => ['POL' => 0.5, 'HAR' => 0.05, 'ACE' => 0.06, 'SAZ' => 0.005]],
            'Sándwich de pollo crispy' => ['category' => 'Pollos', 'price' => 42.00, 'weight' => 6, 'with_sides' => true,
                'description' => 'Pechuga empanizada con repollo y mayonesa en pan suave.',
                'recipe' => ['POL' => 0.25, 'HAR' => 0.03, 'ACE' => 0.04, 'PAN' => 1, 'MAY' => 0.02, 'REP' => 0.03]],
            'Combo Personal' => ['category' => 'Combos', 'price' => 55.00, 'weight' => 16, 'with_sides' => true,
                'description' => 'Dos piezas de pollo, papas fritas y una gaseosa.',
                'recipe' => ['POL' => 0.45, 'HAR' => 0.05, 'ACE' => 0.09, 'SAZ' => 0.005, 'PAP' => 0.2, 'GAS' => 1]],
            'Combo Pareja' => ['category' => 'Combos', 'price' => 105.00, 'weight' => 7, 'with_sides' => true,
                'description' => 'Cuatro piezas de pollo, papas fritas, ensalada y dos gaseosas.',
                'recipe' => ['POL' => 0.9, 'HAR' => 0.1, 'ACE' => 0.17, 'SAZ' => 0.01, 'PAP' => 0.4, 'REP' => 0.1, 'GAS' => 2]],
            'Combo Familiar' => ['category' => 'Combos', 'price' => 210.00, 'weight' => 4, 'with_sides' => true,
                'description' => 'Pollo entero frito, papas, ensalada, tortillas y cuatro gaseosas.',
                'recipe' => ['POL' => 2.0, 'HAR' => 0.22, 'ACE' => 0.3, 'SAZ' => 0.02, 'PAP' => 0.8, 'REP' => 0.2, 'TOR' => 6, 'GAS' => 4]],
            'Gaseosa' => ['category' => 'Bebidas', 'price' => 10.00, 'weight' => 10, 'with_sides' => false,
                'description' => 'Gaseosa personal bien fría.',
                'recipe' => ['GAS' => 1]],
            'Agua pura' => ['category' => 'Bebidas', 'price' => 7.00, 'weight' => 4, 'with_sides' => false,
                'description' => 'Botella de agua pura.',
                'recipe' => ['AGU' => 1]],
            'Limonada natural' => ['category' => 'Bebidas', 'price' => 12.00, 'weight' => 6, 'with_sides' => false,
                'description' => 'Limonada preparada al momento con limón fresco.',
                'recipe' => ['LIM' => 0.15, 'AZU' => 0.03]],
            'Flan de vainilla' => ['category' => 'Postres', 'price' => 15.00, 'weight' => 4, 'with_sides' => false,
                'description' => 'Flan casero de vainilla con caramelo.',
                'recipe' => ['FLA' => 1]],
        ];
    }

    /** Platillos que se publican en el menú del día. */
    public static function dailyMenu(): array
    {
        return [
            'Pollo frito 2 piezas',
            'Pollo frito 3 piezas',
            'Alitas crujientes (8 unidades)',
            'Combo Personal',
            'Combo Pareja',
            'Limonada natural',
            'Flan de vainilla',
        ];
    }

    /**
     * Mesas: número => capacidad.
     *
     * @return array<int, int>
     */
    public static function tables(): array
    {
        return [1 => 2, 2 => 2, 3 => 4, 4 => 4, 5 => 4, 6 => 6, 7 => 6, 8 => 8];
    }

    /**
     * Proveedores ficticios (dominio example.com, reservado para ejemplos). "supplies" asocia el código del insumo con el
     * precio acordado.
     *
     * @return array<int, array{company: string, contact: string, phone: string, email: string, address: string, days: array<int, string>, supplies: array<string, float>}>
     */
    public static function suppliers(): array
    {
        return [
            [
                'company' => 'Avícola San Miguel', 'contact' => 'Jorge Hernández', 'phone' => '7700-1001',
                'email' => 'ventas@avicolasanmiguel.example.com', 'address' => 'Km 195 Carretera Interamericana, Quetzaltenango',
                'days' => ['Lunes', 'Jueves'],
                'supplies' => ['POL' => 31.00],
            ],
            [
                'company' => 'Distribuidora La Fortaleza', 'contact' => 'Ana Villatoro', 'phone' => '7700-2002',
                'email' => 'pedidos@lafortaleza.example.com', 'address' => '5a Avenida 12-30 Zona 3, Quetzaltenango',
                'days' => ['Martes', 'Viernes'],
                'supplies' => [
                    'HAR' => 6.20, 'ACE' => 21.00, 'SAZ' => 26.50, 'ARR' => 8.60, 'FRI' => 11.50, 'KET' => 24.00,
                    'MAY' => 32.50, 'GAS' => 5.50, 'AGU' => 2.75, 'AZU' => 8.20, 'PAN' => 1.35, 'FLA' => 4.60,
                ],
            ],
            [
                'company' => 'Verduras y Frutas El Campo', 'contact' => 'Luis Tzoc', 'phone' => '7700-3003',
                'email' => 'contacto@elcampo.example.com', 'address' => 'Mercado La Democracia, Quetzaltenango',
                'days' => ['Lunes', 'Miércoles', 'Sábado'],
                'supplies' => ['PAP' => 6.60, 'REP' => 4.70, 'ZAN' => 5.20, 'LIM' => 7.50, 'TOR' => 0.45],
            ],
        ];
    }

    /** @return array<int, string> */
    public static function cancellationReasons(): array
    {
        return ['El cliente cambió de opinión', 'Pedido duplicado por error', 'El cliente no esperó', 'Error al tomar la comanda'];
    }

    /** @return array<int, string> */
    public static function orderNotes(): array
    {
        return ['Sin picante', 'Bien cocido', 'Sin ensalada', 'Para llevar en bolsa aparte', 'Cliente con prisa', 'Cambiar papas por arroz'];
    }

    /** @return array<int, string> */
    public static function wasteReasons(): array
    {
        return ['Producto caducado', 'Se cayó durante la preparación', 'Empaque dañado en la entrega', 'Se quemó en la freidora'];
    }
}
