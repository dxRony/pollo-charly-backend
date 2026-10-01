<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tiempo límite de modificación libre de comandas (en minutos)
    |--------------------------------------------------------------------------
    |
    | Define el tiempo máximo en minutos desde que una comanda es enviada
    | a cocina para permitir modificaciones y eliminaciones libres de platillos.
    | Superado este tiempo, o si cocina inicia preparación, solo se admiten adiciones.
    |
    */
    'modification_time_limit_minutes' => (int) env('ORDER_MODIFICATION_TIME_LIMIT_MINUTES', 5),
];
