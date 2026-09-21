# Historias de Usuario y Casos de Uso — Actualización a partir del BPMN

**Fuente:** `BPMN.drawio.xml` (12 páginas) y sus 12 imágenes de referencia en `/home/julian/SS1/proyecto/referencias/`, contrastado contra `Proyecto SS1 - Fase 1.md`, `HUs - Actuales.txt` y `Casos de uso - Actuales.md`.

**Nota sobre el actor "Cliente":** en varios diagramas BPMN aparece un carril "Cliente" (p. ej. *REALIZAR Y CANCELAR PEDIDO*, *MODIFICACIÓN DE PEDIDO EN CURSO*). El cliente **no es un usuario del sistema** — el alcance del proyecto excluye explícitamente una plataforma de pedidos en línea para clientes. Esos carriles representan la interacción física real (el cliente habla con el mesero, consume, paga), y se usan aquí únicamente como contexto/precondición de las Historias de Usuario del Mesero/Cajero y del Sistema. Ninguna HU le da al Cliente una pantalla o cuenta propia.

**Diagrama sin HU propia:** la página *REPORTES Y ANALISIS DE INFORMACION* es un diagrama integrador — ilustra cómo se encadenan procesos que ya tienen su propia HU (venta/consumo → movimiento de insumos → alerta → compra → reportes). No genera una HU nueva; su contenido queda cubierto por HU-10, HU-11, HU-13 y HU-21 en conjunto.

## Registro de cambios frente a la versión anterior

| HU/CU | Estado | Resumen del cambio |
|---|---|---|
| HU-01 a HU-05 / CU001 a CU005 | Sin cambios de fondo | Se conservan tal cual (autenticación y gestión de usuarios no aparecen en el BPMN nuevo). |
| HU-06 / CU006 | **Actualizada** | "Gestión de platillos" ahora incluye definir receta (insumos + cantidad por porción), asociar complementos, y bloquear la desactivación si el platillo está en comandas activas. |
| HU-07 / CU007 | **Nueva** | Gestión de complementos (catálogo de extras), inexistente antes. |
| HU-08 / CU008 | **Nueva** | Publicación del menú del día en la landing page — directamente relevante para el frontend ya construido. |
| HU-09 / CU009 | Renumerada | Antes CU007 "Gestión de productos e insumos". Se agrega el campo de cantidad de referencia/mínima. |
| HU-10 / CU010 | **Nueva** | Registro unificado de movimientos (compra, salida, merma, ajuste), antes implícito y disperso. |
| HU-11 / CU011 | **Actualizada** | Antes CU008 "Alertas de insumos por agotarse" (solo manual). Ahora combina alerta manual del cocinero **y** alerta automática del sistema al comparar existencia contra el mínimo. |
| HU-12 / CU012 | **Actualizada** | "Gestión de proveedores" ya existía como HU sin CU. Se agrega el historial de compras/incidencias y el flujo de validación de datos del proveedor. |
| HU-13 / CU013 | **Actualizada** | Antes "Registro de compras" (plana). Ahora incluye la solicitud generada por una alerta y su aprobación/rechazo por la administradora. |
| HU-14 / CU014 | **Actualizada** | Antes "Recepción de productos". Se detalla el flujo de verificación e incidencia con el proveedor. |
| HU-15 / CU015 | **Actualizada** | Antes "Registro de comanda" (sin verificación de stock). Ahora bloquea existencias al enviar la comanda a cocina. |
| HU-16 / CU016 | **Nueva** | Cancelación de comanda antes de preparación, con liberación del stock bloqueado. |
| HU-17 / CU017 | **Nueva** | Modificación de una comanda ya enviada a cocina, con restricción según tiempo/estado. |
| HU-18 / CU018 | Renumerada | Antes "Seguimiento de estado de comanda", sin cambios de fondo. |
| HU-19 / CU019 | **Actualizada** | Antes "Registro de venta". Ahora deduce inventario y emite comprobante como parte del mismo paso. |
| HU-20 / CU020 | Renumerada | Antes "Registro de ingresos, egresos y gastos", con la consulta/filtro incorporada explícitamente. |
| HU-21 / CU021 | **Actualizada** | Antes "Reportes y exportación". Se agrega el formato de exportación **imagen**, además de PDF y Excel. |
| HU-11 / CU011 | **Actualización posterior** | Por indicación del ingeniero del curso: además de mostrarse en el listado, la alerta se envía por correo electrónico a la administradora. |
| HU-15, HU-16, HU-17, HU-18 / CU015-CU018 | **Actualización posterior** | Por indicación del ingeniero del curso: Cocina debe ver comandas entrantes, canceladas y modificadas **en tiempo real** (WebSockets), no solo mediante consulta manual. |
| HU-22 / CU022 | **Nueva** | Gestión de mesas, incorporada a partir del script de base de datos de `pollo_charly_db.sql` (`restaurant_tables`, `order_types`). No estaba en el BPMN original ni en las HUs previas. |
| HU-15, HU-16, HU-19 / CU015, CU016, CU019 | **Actualizada** | Se agrega la asignación de mesa/tipo de pedido al registrar la comanda (HU-15) y su liberación al cancelar (HU-16) o cerrar la venta (HU-19), también a partir del script de base de datos. |
| HU-17 / CU017 | **Actualizada** | Se incorpora `order_item_status_id` del script: un platillo eliminado durante la modificación se marca como `eliminado` en vez de borrarse, preservando el detalle de la comanda para trazabilidad. |

## Notas para la refactorización de `pollo_charly_db.sql`

Puntos concretos a corregir/completar en el script a partir de esta versión de las HUs, para que quien lo refactorice no tenga que releer toda la conversación de análisis:

1. **Falta la tabla de alertas de HU-11.** El script actual no tiene ningún `supply_alerts`. Ver la sección "Detalles técnicos" de HU-11 para la estructura sugerida (`supply_alerts`: supply_id, origin [manual/automatic], user_id nullable, created_at, status [pending/attended], y opcionalmente `purchase_request_id` para enlazar con HU-13).
2. **`inventory_movements.adjustment_status_type_id` no debería ser `NOT NULL` para todos los movimientos.** Según HU-10, ese estado de aprobación solo aplica a movimientos de tipo `ajuste` — una compra, salida o merma no debería depender de él. Hacer la columna `NULL`-able (solo se llena cuando `inventory_movement_type = ajuste`) en vez de forzar una fila "no aplica" en el catálogo `adjustment_status_types`.
3. **Faltan tablas propias de Laravel que no corresponden a ninguna HU de negocio, pero que el sistema necesita para funcionar** con la configuración ya definida en `pollo-charly-backend/.env` (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`):
   - `password_reset_tokens` (HU-03, recuperación de contraseña).
   - Almacenamiento del código de verificación de HU-04 (2FA) — puede ser una tabla propia (`two_factor_codes`) o columnas en `users`, a decidir.
   - `sessions`, `cache`, `jobs` — tablas estándar de Laravel para sesión, caché y colas.
   
   Estas normalmente las genera Laravel con sus propias migraciones (`php artisan migrate`); no es obligatorio que vivan en este script SQL, pero si el flujo de trabajo del equipo es "todo el esquema sale de este script", hay que agregarlas aquí también para no encontrarse con el error en producción.
4. **`users` no tiene forma de hacer baja lógica.** No hay `is_active` ni `deleted_at`. HU-05 exige poder desactivar un usuario sin borrar su historial — agregar una de las dos columnas.
5. **`restaurant_tables` no tiene forma de marcar una mesa como dada de baja**, solo `table_status_id` para el estado operativo (libre/ocupada). Ver HU-22: la sugerencia es agregar un tercer valor al catálogo `table_statuses` (ej. `inactiva`) en vez de una columna nueva.

---

# Parte 1 — Historias de Usuario

## ÉPICA 1 — Autenticación y control de acceso

### HU-01 — Inicio de sesión

**Como** usuario del sistema
**Quiero** iniciar sesión con mis credenciales
**Para** acceder a las funciones correspondientes a mi rol

```gherkin
Feature: Inicio de sesión

  Scenario: Credenciales correctas y cuenta activa
    Given tengo una cuenta activa con correo "admin@pollocharly.com" y una contraseña válida
    When ingreso mi correo y contraseña correctos y solicito iniciar sesión
    Then el sistema me autentica y me redirige a la pantalla principal correspondiente a mi rol

  Scenario: Contraseña incorrecta
    Given tengo una cuenta activa
    When ingreso mi correo correcto pero una contraseña incorrecta
    Then el sistema rechaza el acceso con un mensaje genérico "Las credenciales no coinciden con nuestros registros"
    And no indica si el correo existe o no

  Scenario: Cuenta desactivada
    Given mi cuenta fue desactivada por la administradora
    When ingreso mis credenciales correctas
    Then el sistema rechaza el acceso con un mensaje indicando que la cuenta no está disponible

  Scenario: Cuenta con segundo factor habilitado
    Given tengo una cuenta activa con autenticación de dos factores habilitada
    When ingreso mis credenciales correctas
    Then el sistema no completa el inicio de sesión todavía
    And me solicita el código de verificación de HU-04
```

**Detalles técnicos:**
- Autenticación por token (Sanctum API token), no por sesión de cookies — consistente con lo ya implementado en `pollo-charly-backend`.
- La contraseña se compara con `Hash::check` contra el hash almacenado (`bcrypt`), nunca en texto plano.
- La respuesta de error de credenciales inválidas debe ser el mismo mensaje tanto si el correo no existe como si la contraseña es incorrecta (evita enumeración de usuarios).
- El JSON de usuario autenticado debe incluir el rol (`role.name`) para que el frontend decida a qué dashboard redirigir.
- Reutiliza el endpoint `POST /api/login` y el flujo `LoginAction` ya implementados; extenderlo para bifurcar hacia HU-04 cuando el usuario tenga 2FA habilitado.

**Referencias:** RF-01, RF-05, CU001

---

### HU-02 — Cierre de sesión

**Como** usuario del sistema
**Quiero** cerrar mi sesión
**Para** evitar que otra persona use mi cuenta desde el mismo dispositivo

```gherkin
Feature: Cierre de sesión

  Scenario: Cierre de sesión exitoso
    Given tengo una sesión activa
    When presiono "Cerrar sesión"
    Then el sistema invalida mi token en el servidor
    And me redirige a la pantalla de inicio de sesión

  Scenario: Error al invalidar la sesión
    Given tengo una sesión activa pero el servidor no puede procesar la solicitud
    When presiono "Cerrar sesión"
    Then el sistema muestra un mensaje indicando que no fue posible completar la operación
    And mantiene al usuario en la pantalla actual para reintentar
```

**Detalles técnicos:**
- Ya implementado: `POST /api/logout` revoca el `PersonalAccessToken` actual (`currentAccessToken()->delete()`).
- El frontend debe limpiar el token de `localStorage` incluso si la petición de logout falla en red, para no dejar al usuario con un token "zombie" percibido como sesión activa.
- RNF-03 (expiración por inactividad) se maneja aparte, como expiración de token por tiempo, no como parte de esta HU.

**Referencias:** RF-02, RNF-03, CU002

---

### HU-03 — Recuperación de contraseña

**Como** usuario del sistema
**Quiero** recuperar mi contraseña
**Para** volver a acceder si la olvidé

```gherkin
Feature: Recuperación de contraseña

  Scenario: Solicitud con correo registrado
    Given existe una cuenta con el correo "mesero@pollocharly.com"
    When solicito recuperación de contraseña con ese correo
    Then el sistema genera un enlace o código temporal
    And lo envía al correo mediante el servicio de correo (SES)
    And muestra un mensaje genérico de confirmación de envío

  Scenario: Solicitud con correo no registrado
    Given no existe ninguna cuenta con el correo ingresado
    When solicito recuperación de contraseña con ese correo
    Then el sistema muestra el mismo mensaje genérico de confirmación de envío
    And no revela si el correo existe o no en el sistema

  Scenario: Enlace o código expirado
    Given recibí un enlace de recuperación hace más tiempo del permitido
    When intento usarlo para establecer una nueva contraseña
    Then el sistema rechaza el enlace por expirado
    And me solicita iniciar el proceso de recuperación nuevamente

  Scenario: Nueva contraseña no cumple los requisitos
    Given tengo un enlace de recuperación válido
    When ingreso una nueva contraseña que no cumple la política mínima (longitud, complejidad)
    Then el sistema rechaza el cambio e indica qué requisito falta
```

**Detalles técnicos:**
- El token de recuperación se almacena hasheado (no en texto plano) con fecha de expiración corta (sugerido: 60 minutos).
- Envío de correo vía AWS SES, según la arquitectura ya definida en el documento de Fase 1.
- Al completar el cambio, invalidar todos los tokens de acceso activos del usuario (forzar nuevo login en todos los dispositivos).

**Referencias:** RF-06, RNF-02, CU003

---

### HU-04 — Autenticación de dos factores

**Como** usuario del sistema
**Quiero** verificar mi identidad con un código enviado a mi correo
**Para** proteger el acceso a mi cuenta ante un inicio de sesión

```gherkin
Feature: Autenticación de dos factores

  Scenario: Código correcto dentro del tiempo permitido
    Given ingresé mis credenciales correctamente y el sistema me envió un código de un solo uso
    When ingreso el código correcto antes de que expire
    Then el sistema completa el inicio de sesión y me redirige según mi rol

  Scenario: Código incorrecto
    Given el sistema me envió un código de verificación
    When ingreso un código distinto al enviado
    Then el sistema muestra un mensaje de error
    And no completa el inicio de sesión

  Scenario: Código expirado
    Given el código enviado ya superó su tiempo de validez
    When intento ingresarlo
    Then el sistema lo rechaza por expirado
    And me permite solicitar un nuevo código

  Scenario: Demasiados intentos fallidos
    Given ya fallé la validación del código el número máximo de veces permitido
    When intento ingresar el código nuevamente
    Then el sistema bloquea temporalmente la validación para esa sesión de login
```

**Detalles técnicos:**
- Código numérico de un solo uso (sugerido: 6 dígitos), expiración corta (sugerido: 5 minutos).
- Límite de intentos configurable (sugerido: 5), con bloqueo temporal (sugerido: 15 minutos) tras superarlo.
- Envío por AWS SES, mismo mecanismo que HU-03.
- Este flujo solo se activa si el usuario tiene 2FA habilitado; si no, HU-01 completa el login directamente.

**Referencias:** RF-07, CU001, CU004

---

### HU-05 — Gestión de usuarios

**Como** administradora
**Quiero** registrar, consultar, actualizar y desactivar usuarios
**Para** controlar quién tiene acceso al sistema y con qué rol

```gherkin
Feature: Gestión de usuarios

  Scenario: Registrar un nuevo usuario
    Given estoy autenticada como administradora
    When registro un usuario con nombre, correo, rol y contraseña inicial válidos
    Then el sistema crea la cuenta activa con el rol asignado
    And ese usuario puede iniciar sesión con las funciones de ese rol

  Scenario: Correo duplicado
    Given ya existe un usuario registrado con el correo "cocinero@pollocharly.com"
    When intento registrar otro usuario con ese mismo correo
    Then el sistema rechaza la operación indicando que el correo ya está en uso

  Scenario: Datos incompletos o inválidos
    Given estoy registrando o editando un usuario
    When dejo campos obligatorios vacíos o con formato inválido (ej. correo mal formado)
    Then el sistema indica los campos que deben corregirse sin guardar cambios

  Scenario: Desactivar un usuario
    Given un usuario ya no debe tener acceso al sistema
    When lo desactivo desde la gestión de usuarios
    Then el sistema realiza una baja lógica sin eliminar su historial de operaciones
    And ese usuario ya no puede iniciar sesión

  Scenario: Acceso restringido a otros roles
    Given estoy autenticada con el rol mesero/cajero o cocinero
    When intento acceder a la gestión de usuarios
    Then el sistema deniega el acceso por no tener el rol de administradora
```

**Detalles técnicos:**
- Roles permitidos: `Administrador`, `Mesero/Cajero`, `Cocinero` (ya existen como seed en `RoleSeeder`).
- Baja lógica: la tabla `users` de `pollo_charly_db.sql` **todavía no tiene** una columna para esto (ni `is_active` ni `deleted_at`) — hay que agregar una de las dos antes de implementar esta HU (ver "Notas para la refactorización" al inicio del documento). Nunca `DELETE` físico — preserva la integridad referencial con ventas, comandas y movimientos ya registrados por ese usuario.
- Restricción por rol implementada como middleware/policy en Laravel, no solo ocultando botones en el frontend.

**Referencias:** RF-03, RF-04, RF-05, CU005

---

## ÉPICA 2 — Gestión de menú (platillos, recetas, complementos y menú del día)

*Origen BPMN:* `GESTIÓN DE PLATILLOS Y RECETAS`, `GESTION DE COMPLEMENTOS`, `PUBLICACION Y GESTION DEL MENU DEL DIA EN LANDING PAGE`.

### HU-06 — Gestión de platillos y recetas

**Como** administradora
**Quiero** registrar, consultar, actualizar y desactivar platillos junto con su receta y complementos aplicables
**Para** mantener actualizado el menú del negocio y su consumo real de insumos

```gherkin
Feature: Gestión de platillos y recetas

  Scenario: Crear un platillo nuevo con receta y complementos
    Given estoy autenticada como administradora en el módulo de platillos y recetas
    When ingreso nombre, categoría, descripción y precio de venta
    And defino la receta seleccionando insumos y cantidad por porción
    And asocio la lista de complementos aplicables
    And confirmo el registro
    Then el sistema valida la información básica, los insumos asignados y los precios
    And guarda el platillo junto con su receta y complementos asociados
    And lo muestra disponible en el catálogo de platillos y en las comandas

  Scenario: Nombre de platillo duplicado
    Given ya existe un platillo activo llamado "Pollo Frito Familiar"
    When intento crear otro platillo con ese mismo nombre
    Then el sistema notifica que el nombre ya existe
    And no guarda el registro hasta que se corrija

  Scenario: Datos o cantidades de receta inválidos
    Given estoy definiendo la receta de un platillo
    When dejo insumos sin cantidad, o ingreso un precio no numérico o negativo
    Then el sistema notifica el error en los campos o cantidades requeridas
    And no guarda el platillo hasta corregirlo

  Scenario: Editar datos básicos o precio de un platillo existente
    Given el platillo "Pollo Frito Familiar" ya existe
    When busco el platillo y modifico su precio o descripción
    And confirmo el cambio
    Then el sistema valida y guarda la actualización
    And el menú y la disponibilidad de venta quedan actualizados

  Scenario: Desactivar un platillo sin órdenes pendientes
    Given el platillo no tiene comandas activas (pendientes o en preparación)
    When lo selecciono y confirmo la desactivación
    Then el sistema cambia su estado a inactivo
    And deja de estar disponible para nuevas comandas sin eliminar su historial de ventas

  Scenario: Intentar desactivar un platillo con comandas activas
    Given el platillo tiene al menos una comanda pendiente o en preparación
    When intento desactivarlo
    Then el sistema rechaza la operación
    And notifica que existe una orden en preparación o pendiente que lo impide
```

**Detalles técnicos:**
- Tablas (nombres según `pollo_charly_db.sql`): `dishes` (name único entre activos, category_id, description, price, image_url, is_active, is_daily_menu — ver HU-08), `dish_recipes` (dish_id, supply_id, required_quantity) y `dish_complements` (pivote dish_id/complement_id).
- La validación de duplicado de nombre solo debe considerar platillos activos (permite reutilizar el nombre de uno desactivado, a decidir con el equipo si se prefiere unicidad absoluta).
- "En comandas activas" = existe al menos un `order_item` con ese `dish_id` en una `order` cuyo `order_status` sea `pendiente` o `en_preparacion` (ver HU-15/HU-18).
- Seguir el patrón Form Request → Action → Resource definido en la skill `pollo-charly-standards` (`StoreDishRequest`, `DishResource`, etc.).

**Referencias:** RF-08, CU006

---

### HU-07 — Gestión de complementos

**Como** administradora
**Quiero** registrar, consultar, actualizar y desactivar complementos (extras)
**Para** ofrecer adicionales asociables a los platillos sin mezclarlos con el menú principal

```gherkin
Feature: Gestión de complementos

  Scenario: Crear un complemento nuevo
    Given estoy autenticada como administradora en el módulo de complementos
    When ingreso nombre, descripción, precio extra e insumos asociados
    And confirmo el registro
    Then el sistema valida los campos obligatorios y el formato del precio
    And verifica que no exista un complemento duplicado
    And guarda el registro y notifica el éxito
    And lo muestra en el catálogo de complementos actualizado

  Scenario: Complemento duplicado
    Given ya existe un complemento activo llamado "Extra queso"
    When intento crear otro complemento con ese mismo nombre
    Then el sistema notifica el duplicado
    And no guarda el registro

  Scenario: Datos inválidos en el formulario
    Given estoy creando o editando un complemento
    When el precio no es numérico/positivo o falta un campo obligatorio
    Then el sistema notifica el error en el formulario sin guardar

  Scenario: Editar un complemento existente
    Given el complemento "Extra queso" ya existe
    When selecciono el complemento y modifico sus datos o precio
    Then el sistema valida y guarda la edición
    And notifica el registro exitoso

  Scenario: Desactivar un complemento sin uso activo
    Given el complemento no está asociado a ninguna comanda activa
    When selecciono el complemento y confirmo la desactivación
    Then el sistema lo actualiza a estado inactivo
    And muestra confirmación exitosa

  Scenario: Intentar desactivar un complemento en uso activo
    Given el complemento está asociado a al menos una comanda pendiente o en preparación
    When intento desactivarlo
    Then el sistema notifica la restricción por estado activo en comanda
    And no lo desactiva
```

**Detalles técnicos:**
- Tabla `complements` (name único entre activos, description, extra_price, is_active) + pivote `complement_supplies` (complement_id, supply_id, required_quantity) para descontar insumos cuando se vende un complemento, igual que la receta de un platillo. La asociación complemento-platillo vive en `dish_complements` (ver HU-06).
- La verificación de "uso activo" replica la misma regla que HU-06 pero contra `order_item_complements` (líneas de comanda que usan ese complemento).
- Endpoint y estructura idénticos en espíritu a HU-06 (mismo patrón CRUD con Form Request + Resource); considerar un `ComplementController` separado del de platillos por ser una entidad propia, aunque comparta convenciones.

**Referencias:** RF-08 (extendido), CU007

---

### HU-08 — Publicación y gestión del menú del día en landing page

**Como** administradora
**Quiero** marcar qué platillos se destacan como "Menú del día" en la landing page pública
**Para** que los clientes vean una selección actualizada sin exponer platillos sin insumos disponibles

```gherkin
Feature: Publicación del menú del día

  Scenario: Marcar platillos con insumos disponibles
    Given estoy autenticada como administradora en el módulo de landing page
    And consulto el catálogo de platillos disponible
    When marco uno o varios platillos para destacar en "Menú del día"
    Then el sistema verifica la disponibilidad de insumos en almacén para esos platillos
    And, si hay insumos suficientes, actualiza el atributo "es_menu_dia" a verdadero
    And refresca el contenedor de "Menú del día" en la landing page
    And muestra un mensaje de publicación exitosa

  Scenario: Intentar destacar un platillo sin insumos suficientes
    Given selecciono un platillo cuya receta requiere un insumo con existencia insuficiente
    When intento marcarlo como "Menú del día"
    Then el sistema notifica que el platillo tiene stock insuficiente
    And no lo marca como menú del día
    And me permite corregir la selección de platillos agotados o vacíos

  Scenario: Previsualizar la landing page actualizada
    Given ya publiqué cambios en el menú del día
    When accedo a la previsualización
    Then veo reflejados exactamente los platillos actualmente marcados como "es_menu_dia = true"

  Scenario: Landing page pública consume el menú del día real
    Given hay al menos un platillo marcado como "es_menu_dia = true"
    When un visitante entra a la landing page sin iniciar sesión
    Then ve la sección "Menú del día" poblada con esos platillos (nombre y, si aplica, descripción/precio)
    And no ve platillos que no estén marcados como menú del día

  Scenario: Ningún platillo marcado como menú del día
    Given no hay ningún platillo con "es_menu_dia = true"
    When un visitante entra a la landing page
    Then la sección "Menú del día" se muestra vacía o con un mensaje neutral, sin error
```

**Detalles técnicos:**
- Reutiliza el atributo `is_daily_menu` (boolean) en la tabla `dishes` introducido en HU-06.
- La verificación de "insumos disponibles" recorre `dish_recipes` del platillo (HU-06) y compara cada `supply_id` contra `supplies.current_stock` (HU-09/HU-10).
- Endpoint público de solo lectura (sin `auth:sanctum`) para que la landing page (ya implementada en `pollo-charly-frontend`, componente `MenuCard`) deje de usar el arreglo estático `MENU_ITEMS` y consuma este endpoint real — **este es el reemplazo directo del placeholder que se dejó en `LandingPage.tsx`**.
- Endpoint de administración (`PATCH /api/dishes/{id}/daily-menu` o similar) protegido por rol Administradora.

**Referencias:** RF-08 (extendido, sin RF explícito para landing page — requisito derivado del proceso BPMN), CU008

---

## ÉPICA 3 — Gestión de productos, insumos y movimientos

*Origen BPMN:* `MOVIMIENTOS Y CONTROL DE PRODUCTOS`, y el tramo de "Cocinero" en `GESTION DE ABASTECIMIENTO Y COMPRAS`.

### HU-09 — Gestión de productos e insumos

**Como** administradora
**Quiero** registrar, consultar y actualizar productos e insumos
**Para** mantener organizada la información de lo que utiliza el negocio

```gherkin
Feature: Gestión de productos e insumos

  Scenario: Registrar un insumo nuevo
    Given estoy autenticada como administradora
    When registro un insumo con nombre, unidad de medida, categoría y cantidad de referencia (mínima)
    Then el sistema valida la información
    And queda disponible para asociarse a compras, recetas y movimientos

  Scenario: Datos inválidos
    Given estoy registrando o editando un insumo
    When falta un campo obligatorio o la cantidad de referencia no es numérica/positiva
    Then el sistema indica los campos que deben corregirse

  Scenario: Actualizar un insumo sin perder historial
    Given el insumo "Pollo entero" ya tiene movimientos registrados
    When actualizo su unidad de medida o cantidad de referencia
    Then el sistema guarda el cambio
    And el historial de movimientos previos permanece intacto
```

**Detalles técnicos:**
- Tabla `supplies` (code, name, measurement_unit_id, current_stock, minimum_stock, unit_cost, is_active), según `pollo_charly_db.sql`.
- El campo `minimum_stock` es el que usa HU-11 para decidir si generar una alerta de reposición.
- `current_stock` se actualiza únicamente a través de HU-10 (nunca editable directamente desde este formulario), para mantener el historial de movimientos (`inventory_movements`) como única fuente de verdad.

**Referencias:** RF-09, CU009

---

### HU-10 — Registro de movimientos de productos e insumos

**Como** usuario autorizado (cocinero, mesero/cajero o administradora, según el tipo de movimiento)
**Quiero** registrar movimientos de compra, salida, merma o ajuste sobre un producto o insumo
**Para** mantener un historial trazable de disponibilidad en vez de depender de observación manual

```gherkin
Feature: Registro de movimientos de productos e insumos

  Scenario: Movimiento de compra aumenta existencia
    Given se recibió una compra confirmada de un insumo (HU-13/HU-14)
    When se registra el movimiento de tipo "compra" con la cantidad recibida
    Then el sistema aumenta la existencia del insumo
    And guarda tipo, cantidad, usuario, fecha y existencia resultante en el historial

  Scenario: Movimiento de salida (venta o consumo) con existencia suficiente
    Given el insumo tiene existencia disponible mayor o igual a la cantidad solicitada
    When se registra un movimiento de tipo "salida" (venta o consumo en cocina)
    Then el sistema valida la disponibilidad
    And disminuye la existencia
    And guarda el movimiento en el historial

  Scenario: Movimiento de salida rechazado por falta de existencia
    Given el insumo no tiene existencia suficiente para la cantidad solicitada
    When se intenta registrar un movimiento de tipo "salida"
    Then el sistema rechaza el movimiento
    And no modifica la existencia actual

  Scenario: Registrar una merma o producto dañado
    Given un insumo se dañó o venció antes de usarse
    When el cocinero registra un movimiento de tipo "merma" indicando el motivo
    Then el sistema disminuye la existencia
    And guarda el motivo junto con el resto de los datos del movimiento

  Scenario: Solicitar un ajuste de inventario
    Given se detecta una diferencia entre la existencia física y la registrada en el sistema
    When se registra un movimiento de tipo "ajuste" con la cantidad corregida
    Then el sistema envía la solicitud a revisión de la administradora antes de aplicarla

  Scenario: Administradora aprueba el ajuste
    Given existe una solicitud de ajuste pendiente
    When la administradora la revisa y la aprueba
    Then el sistema corrige la cantidad registrada
    And guarda el movimiento con la existencia resultante en el historial

  Scenario: Administradora rechaza el ajuste
    Given existe una solicitud de ajuste pendiente
    When la administradora la rechaza
    Then el sistema descarta el ajuste sin modificar la existencia actual

  Scenario: El movimiento deja la existencia por debajo del mínimo
    Given después de aplicar un movimiento la existencia queda por debajo de la cantidad de referencia del insumo
    When el sistema termina de guardar el movimiento
    Then genera automáticamente una alerta de reposición (ver HU-11)
```

**Detalles técnicos:**
- Tabla `inventory_movements` (supply_id, inventory_movement_type_id [`compra`,`salida`,`merma`,`ajuste`], quantity, reason (nullable, usado en merma), user_id, order_id (nullable, enlaza con la comanda que originó una salida), purchase_order_id (nullable, enlaza con la compra que originó una entrada), previous_stock, new_stock, adjustment_status_type_id, approver_user_id (nullable)), según `pollo_charly_db.sql`.
- Toda actualización de `current_stock` en `supplies` debe ocurrir dentro de una transacción junto con la inserción en `inventory_movements` (RNF-16: operaciones que afectan varios registros deben ser transaccionales).
- El movimiento de tipo `ajuste` es el único que requiere aprobación de administradora antes de aplicarse; los demás se aplican de inmediato al registrarse. **Importante para la refactorización del script:** `adjustment_status_type_id` solo debería llenarse (o ser relevante) cuando el tipo es `ajuste` — no debería ser `NOT NULL` para compra/salida/merma (ver "Notas para la refactorización de `pollo_charly_db.sql`" al inicio del documento).
- Este registro es el que consumen HU-15 (verificación de stock al comandar) y HU-19 (deducción de inventario al vender) internamente — no son formularios distintos, son la misma tabla de movimientos vista desde otros flujos.

**Referencias:** RF-09 (extendido), CU010

---

### HU-11 — Alertas de reposición de insumos

**Como** cocinero
**Quiero** que se genere una alerta cuando un insumo se esté agotando (manual o automáticamente)
**Para** que la administradora sepa qué reponer antes de que falte

```gherkin
Feature: Alertas de reposición de insumos

  Scenario: Alerta automática al registrar un movimiento
    Given un movimiento de salida o merma deja la existencia de un insumo por debajo de su cantidad de referencia
    When el sistema termina de procesar ese movimiento
    Then genera automáticamente una alerta de reposición para ese insumo
    And la asocia a la fecha y al usuario que originó el movimiento

  Scenario: Alerta manual generada por el cocinero
    Given el cocinero observa que un insumo se está agotando aunque el sistema no lo haya detectado aún
    When selecciona el insumo y genera una alerta manualmente
    Then el sistema registra la alerta indicando fecha y usuario que la generó

  Scenario: Intentar generar una alerta para un producto inexistente
    Given el cocinero intenta seleccionar un producto que no está registrado
    When intenta generar la alerta
    Then el sistema solicita seleccionar un producto válido del catálogo

  Scenario: Notificación por correo a la administradora al generarse una alerta
    Given se genera una alerta de reposición, manual o automática
    When el sistema termina de registrarla
    Then además de quedar visible en el listado de alertas pendientes, envía un correo a la administradora
    And ese correo incluye el insumo, el origen de la alerta (manual/automática) y la fecha

  Scenario: Administradora consulta alertas pendientes
    Given existen alertas generadas (manuales o automáticas) sin atender
    When la administradora consulta el listado de alertas
    Then el sistema muestra todas las alertas pendientes con su insumo, origen (manual/automática), fecha y usuario

  Scenario: Administradora atiende una alerta generando la solicitud de compra
    Given hay una alerta pendiente
    When la administradora la revisa y decide reponer el insumo
    Then el sistema la marca como atendida
    And continúa con el flujo de HU-13 (solicitud y aprobación de compra)

  Scenario: Intentar marcar una alerta ya atendida
    Given una alerta ya fue marcada como atendida anteriormente
    When se intenta marcarla como atendida de nuevo
    Then el sistema indica que no puede marcarse nuevamente
```

**Detalles técnicos:**
- Tabla nueva a agregar al script — no existe todavía en `pollo_charly_db.sql` (ver "Notas para la refactorización" al inicio del documento): `supply_alerts` (supply_id, origin [`manual`,`automatic`], user_id (nullable si es automática y no la origina una persona directamente, aunque en la práctica siempre hay un usuario detrás del movimiento), created_at, status [`pending`,`attended`]).
- La alerta automática se dispara como efecto colateral de HU-10 (mismo request/transacción o un evento/listener de Laravel posterior al commit).
- Una alerta atendida puede (opcionalmente) enlazarse a la solicitud de compra que generó (`purchase_request_id` nullable, ver HU-13), para trazabilidad completa entre HU-11 y HU-13.
- El correo a la administradora reutiliza el mismo mecanismo de envío (AWS SES) ya usado en HU-03/HU-04. Si se generan varias alertas en un lapso corto (ej. varios movimientos de salida seguidos), conviene agrupar el envío (ej. un correo resumen cada cierto intervalo) en vez de un correo por cada alerta individual, para no saturar la bandeja de la administradora — decisión a validar con el equipo antes de implementar.

**Referencias:** RF-10, RF-11, CU011

---

## ÉPICA 4 — Gestión de proveedores y compras

*Origen BPMN:* `GESTION DE PROVEEDORES`, `GESTION DE ABASTECIMIENTO Y COMPRAS`.

### HU-12 — Gestión de proveedores

**Como** administradora
**Quiero** registrar, consultar, actualizar y desactivar proveedores, y consultar su historial
**Para** mantener organizada la información de contacto, productos y desempeño de cada proveedor

```gherkin
Feature: Gestión de proveedores

  Scenario: Buscar un proveedor ya registrado
    Given el proveedor "Distribuidora Avícola" ya existe en el sistema
    When lo busco desde la gestión de proveedores
    Then el sistema muestra su información de contacto, productos, precios e historial de compras e incidencias

  Scenario: Registrar un proveedor nuevo
    Given el proveedor no está registrado en el sistema
    When solicito su información y el proveedor proporciona contacto, productos, precios y días de entrega
    Then el sistema valida la información recibida
    And, si los datos son correctos, la guarda como un nuevo proveedor

  Scenario: Datos de proveedor incorrectos o incompletos
    Given estoy registrando un proveedor nuevo
    When los datos proporcionados no son correctos o están incompletos
    Then el sistema no guarda el registro
    And solicita nuevamente la información faltante o corregida

  Scenario: Desactivar un proveedor
    Given un proveedor ya no trabaja con el negocio
    When lo desactivo desde la gestión de proveedores
    Then el sistema realiza una baja lógica
    And conserva su historial de compras asociado

  Scenario: Registrar una incidencia al recibir una entrega
    Given el mesero recibe y revisa una entrega del proveedor
    When identifica un retraso, diferencia de peso o problema de calidad
    Then registra el tipo de incidencia
    And el sistema actualiza el historial del proveedor con esa incidencia

  Scenario: Entrega sin incidencias
    Given el mesero recibe y revisa una entrega del proveedor
    When no encuentra ningún problema con la entrega
    Then el sistema actualiza el historial del proveedor sin registrar incidencia
```

**Detalles técnicos:**
- Tabla `suppliers` (company_name, contact_name, phone, email, address, is_active) + `supplier_supplies` (supplier_id, supply_id, agreed_price) + `supplier_delivery_days` (supplier_id, delivery_day_id) + tabla `delivery_incidents` (purchase_order_id, supplier_id, receiving_user_id, delivery_incident_type_id, delivery_incident_status_id, description, evidence_path), según `pollo_charly_db.sql`.
- El historial de "compras, precios e incidencias" es una vista de solo lectura que agrega datos de `purchase_orders`/`purchase_order_items` (HU-13) y `delivery_incidents`.
- La incidencia se registra en el mismo paso que HU-14 (recepción de compra) — son la misma acción vista desde dos HUs relacionadas; comparten el mismo formulario/endpoint de recepción.

**Referencias:** RF-12, RF-15, CU012

---

### HU-13 — Solicitud, aprobación y registro de compras

**Como** administradora
**Quiero** revisar las solicitudes de compra generadas por una alerta de reposición, aprobarlas o rechazarlas, y registrar la compra
**Para** llevar un control organizado del abastecimiento en vez de decidir solo por observación

```gherkin
Feature: Solicitud, aprobación y registro de compras

  Scenario: Revisar una solicitud generada por una alerta atendida
    Given una alerta de reposición fue atendida (HU-11) y generó una solicitud de compra
    When la administradora revisa los productos y cantidades sugeridas
    Then el sistema le presenta la información para decidir si aprobar o rechazar

  Scenario: Aprobar la solicitud y registrar la compra
    Given la administradora aprueba la solicitud
    When selecciona un proveedor y registra la compra con fecha, productos, cantidades, precios y total
    Then el sistema calcula el total automáticamente según cantidades y precios
    And asocia la compra al proveedor seleccionado
    And envía la orden de compra correspondiente

  Scenario: Rechazar la solicitud sin comprar
    Given la administradora determina que no es necesario comprar todavía
    When rechaza la solicitud
    Then el sistema cierra la solicitud sin generar ninguna compra

  Scenario: Confirmar la recepción de una compra
    Given una compra fue entregada y verificada conforme (HU-14)
    When se confirma la recepción
    Then el sistema actualiza las existencias del insumo comprado
    And actualiza el historial de movimientos (como movimiento de tipo "compra", HU-10)

  Scenario: Registrar una compra manual sin alerta previa
    Given la administradora decide comprar un insumo sin que exista una alerta de reposición
    When registra la compra directamente con fecha, proveedor, productos, cantidades y precios
    Then el sistema la procesa igual que una compra aprobada desde una solicitud
```

**Detalles técnicos:**
- Tabla `purchase_requests` (requester_user_id, purchase_request_status_id [`pendiente`,`aprobada`,`rechazada`], reason) con su detalle `purchase_request_items` (purchase_request_id, supply_id, suggested_quantity, approved_quantity nullable) — el vínculo hacia la alerta que la originó (`supply_alerts`, HU-11) todavía no existe como columna en el script y debe agregarse (ver "Notas para la refactorización"). Y tabla `purchase_orders` (code, supplier_id, admin_user_id, purchase_request_id nullable —nullable porque también existe la compra manual sin alerta previa—, purchase_order_status_id, total, expected_date, received_date) con su detalle `purchase_order_items` (purchase_order_id, supply_id, ordered_quantity, received_quantity nullable, unit_price, subtotal), según `pollo_charly_db.sql`.
- El total de la compra es calculado en backend (`sum(ordered_quantity * unit_price)`), nunca confiar en un total enviado desde el frontend.
- La actualización de existencias ocurre al **confirmar la recepción** (HU-14), no al simplemente registrar/aprobar la compra — la orden de compra registrada representa un pedido en tránsito hasta que el mesero la recibe conforme.

**Referencias:** RF-13, CU013

---

### HU-14 — Recepción de compras y registro de incidencias

**Como** mesero/cajero
**Quiero** registrar la recepción de los productos solicitados a un proveedor
**Para** dejar evidencia de retrasos, diferencias de peso o problemas de calidad antes de aceptar la entrega

```gherkin
Feature: Recepción de compras

  Scenario: Recepción conforme
    Given llega un pedido correspondiente a una compra registrada (HU-13)
    When reviso cantidad, peso y calidad de los productos entregados
    And confirmo que la entrega es conforme
    Then el sistema confirma la recepción de la compra
    And actualiza existencias e historial de movimientos (HU-10)

  Scenario: Recepción no conforme
    Given reviso cantidad, peso y calidad de los productos entregados
    When identifico que la entrega no es conforme (falta cantidad, hay diferencia de peso o problema de calidad)
    Then registro una incidencia indicando el motivo
    And la administradora recibe la incidencia para solicitar corrección o reposición al proveedor

  Scenario: Proveedor corrige o repone productos
    Given se registró una incidencia por entrega no conforme
    When el proveedor corrige o repone los productos observados
    Then el mesero vuelve a revisar la entrega corregida
    And el flujo continúa como una recepción normal
```

**Detalles técnicos:**
- Reutiliza `delivery_incidents` (HU-12) y dispara el movimiento de tipo `compra` en `inventory_movements` (HU-10) únicamente cuando la recepción se confirma como conforme.
- El `purchase_order_status_id` pasa de "en tránsito" a "recibida" solo tras la confirmación; una incidencia deja la orden de compra en un estado de "pendiente de corrección" (catálogo `purchase_order_statuses`) hasta que se resuelve.

**Referencias:** RF-14, RF-15, CU014

---

## ÉPICA 5 — Comandas y ventas

*Origen BPMN:* `GESTION DE VENTAS Y COMANDAS`, `REALIZAR Y CANCELAR PEDIDO`, `MODIFICACION DE PEDIDO EN CURSO`.

**Nota técnica transversal — tiempo real en Cocina:** por indicación del ingeniero del curso, la pantalla de Cocina no puede depender solo de que el cocinero recargue o consulte manualmente; debe reflejar automáticamente las comandas entrantes (HU-15), canceladas (HU-16) y modificadas (HU-17). Mecanismo sugerido: **Laravel Reverb** (servidor WebSocket propio de Laravel, sin costo de suscripción externa como Pusher — coherente con la arquitectura "beta" de costo $0/mes definida en `Proyecto SS1 - Fase 1.md`) en el backend, y **Laravel Echo** en el frontend suscrito a un canal (ej. `private-cocina`). Eventos sugeridos: `ComandaEnviadaACocina`, `ComandaCancelada`, `ComandaModificada`. La consulta manual (HU-18) se conserva como sincronización inicial al abrir la pantalla y como respaldo ante una reconexión, no se elimina — el WebSocket la complementa, no la reemplaza.

### HU-15 — Registro de comanda con verificación de disponibilidad

**Como** mesero/cajero
**Quiero** registrar una comanda con los platillos y complementos solicitados, verificando disponibilidad de insumos
**Para** llevar el pedido del cliente desde que se toma hasta que se envía a cocina, sin prometer platillos sin insumos

```gherkin
Feature: Registro de comanda

  Scenario: Registrar comanda en mesa con insumos disponibles
    Given un cliente comunicó su pedido de platillos y complementos al mesero, sentado en una mesa libre
    When registro la comanda indicando el tipo de pedido "en mesa", la mesa correspondiente, los platillos, cantidades, complementos y observaciones
    Then el sistema calcula automáticamente el subtotal y el total
    And verifica la disponibilidad de insumos según la receta de cada platillo y complemento
    And, si hay insumos suficientes, bloquea (reserva) esas existencias
    And marca la mesa como ocupada
    And envía la comanda a cocina en estado "pendiente"

  Scenario: Registrar comanda para llevar sin asignar mesa
    Given un cliente solicita su pedido para llevar
    When registro la comanda indicando el tipo de pedido "para llevar", sin seleccionar ninguna mesa
    Then el sistema procesa la comanda igual que una comanda en mesa, sin bloquear ni requerir ninguna mesa

  Scenario: Intentar asignar una comanda a una mesa ya ocupada
    Given una mesa ya tiene una comanda activa asignada
    When intento registrar una nueva comanda de tipo "en mesa" seleccionando esa misma mesa
    Then el sistema rechaza la asignación e indica que la mesa está ocupada
    And me permite elegir otra mesa libre

  Scenario: Insumos insuficientes para un platillo solicitado
    Given un platillo o complemento solicitado requiere un insumo sin existencia suficiente
    When intento registrar la comanda
    Then el sistema notifica la falta de producto al mesero
    And no envía esa comanda a cocina hasta que se ajuste el pedido

  Scenario: Informar al cliente sobre un producto no disponible
    Given el sistema notificó falta de producto al mesero
    When el mesero informa al cliente que ese platillo no está disponible
    Then el cliente puede volver a seleccionar del menú disponible actual

  Scenario: Cálculo automático de subtotal y total
    Given registro una comanda con múltiples platillos, cantidades y complementos con precio extra
    When confirmo la comanda
    Then el subtotal de cada línea es cantidad × precio unitario (platillo o complemento)
    And el total es la suma de todos los subtotales

  Scenario: Cocina recibe la comanda entrante en tiempo real
    Given el cocinero tiene abierta la pantalla de comandas de cocina
    When el sistema envía una comanda nueva a cocina (insumos bloqueados exitosamente)
    Then la comanda aparece automáticamente en su listado de pendientes
    And no requiere recargar la página para verla
```

**Detalles técnicos:**
- Tabla `orders` (code, restaurant_table_id nullable, waiter_user_id, order_type_id, order_status_id [`pendiente`,`en_preparacion`,`lista`,`entregada`,`cancelada`], notes, preparation_start_time), `order_items` (order_id, dish_id, order_item_status_id, quantity, unit_price, subtotal, notes) y `order_item_complements` (order_item_id, complement_id, quantity, unit_price, subtotal) para los complementos de cada línea, según `pollo_charly_db.sql`.
- **Nota de reconciliación con el script:** `orders` no tiene columnas propias de `subtotal`/`total` — el total de la comanda se calcula sumando `order_items.subtotal` (más `order_item_complements.subtotal`) al momento de consultarla, no se persiste duplicado en `orders`. Los Gherkin de esta HU que dicen "el sistema calcula el subtotal y el total" se refieren a ese cálculo agregado, no a columnas nuevas en `orders`.
- "Bloquear existencias" implica reservar la cantidad de insumos necesaria (según receta) sin todavía registrar un movimiento de `salida` definitivo — se materializa como `salida` en HU-19 al confirmar la venta, o se libera en HU-16 si se cancela.
- Reemplaza y extiende la HU "Registro de comanda" anterior, que no contemplaba verificación ni bloqueo de stock.
- Al enviar la comanda a cocina, emitir el evento `ComandaEnviadaACocina` por el canal `private-cocina` (ver nota técnica transversal al inicio de esta épica), después de confirmar la transacción (no antes, para no notificar una comanda que luego falla al guardarse).
- La comanda referencia una mesa (`restaurant_table_id`, nullable) y un tipo de pedido (`order_type_id`: `en_mesa` / `para_llevar`) — ver HU-22 para la gestión del catálogo de mesas. Solo las comandas de tipo `en_mesa` requieren una mesa libre; las de tipo `para_llevar` se procesan sin mesa asignada.
- Marcar la mesa como ocupada es parte de la misma transacción que registra la comanda, para evitar que dos meseros asignen la misma mesa simultáneamente.

**Referencias:** RF-16, RF-17, RF-18, CU015

---

### HU-16 — Cancelación de comanda antes de preparación

**Como** mesero/cajero
**Quiero** anular una comanda antes de que cocina empiece a prepararla
**Para** liberar las existencias reservadas cuando el cliente cambia de decisión

```gherkin
Feature: Cancelación de comanda

  Scenario: Cliente solicita cancelar antes del envío a cocina
    Given una comanda fue comunicada por el cliente pero aún no se registró como enviada a cocina
    When el cliente solicita cancelar el pedido
    Then el mesero anula la comanda antes de preparación

  Scenario: Liberar bloqueo de existencias al cancelar
    Given una comanda en estado "pendiente" tenía existencias bloqueadas
    When el mesero anula la comanda
    Then el sistema libera el bloqueo de esas existencias
    And registra la cancelación con fecha y usuario responsable

  Scenario: Liberar la mesa asignada al cancelar
    Given la comanda cancelada era de tipo "en mesa" y tenía una mesa asignada
    When el sistema procesa la cancelación
    Then marca esa mesa como libre nuevamente

  Scenario: Intentar cancelar una comanda ya en preparación
    Given una comanda ya pasó a estado "en_preparacion"
    When se intenta anularla como si aún no se hubiera enviado a cocina
    Then el sistema rechaza la cancelación directa
    And remite al flujo de HU-17 (modificación de pedido en curso), que sí permite ajustes controlados

  Scenario: Cocina se entera de la cancelación en tiempo real
    Given la comanda ya era visible en el listado de pendientes de cocina
    When el mesero/cajero la cancela antes de preparación
    Then el sistema notifica la cancelación a la pantalla de cocina en tiempo real
    And la comanda se retira o se marca como cancelada en esa pantalla sin necesidad de recargar
```

**Detalles técnicos:**
- La comanda pasa a estado `cancelada` (no se elimina el registro, por trazabilidad).
- Liberar el bloqueo significa revertir la reserva hecha en HU-15 sin crear un movimiento de `salida` — las existencias vuelven a estar disponibles tal como estaban antes de comandar.
- Esta HU solo aplica mientras la comanda no ha sido enviada a cocina (antes del paso "Enviar comanda a cocina y bloquear stock" de HU-15); una vez en cocina, cualquier cambio pasa por HU-17.
- Emitir el evento `ComandaCancelada` por el canal `private-cocina` (ver nota técnica transversal al inicio de esta épica) al confirmar la cancelación.

**Referencias:** RF-18 (extiende el enum de estados con `cancelada`), CU016

---

### HU-17 — Modificación de comanda en curso

**Como** mesero/cajero
**Quiero** modificar una comanda que el cliente ya envió a cocina, dentro de reglas de tiempo y estado
**Para** permitir ajustes razonables sin afectar un pedido que ya está en preparación

```gherkin
Feature: Modificación de comanda en curso

  Scenario: Modificación libre dentro del tiempo permitido y antes de preparación
    Given la comanda todavía no entra en estado "en_preparacion" y no ha superado el tiempo límite de modificación
    When el mesero consulta el estado de la orden y solicita modificar o eliminar platillos
    Then el sistema permite modificar o eliminar los platillos indicados
    And marca los platillos eliminados como "eliminado" dentro de la comanda, sin borrar el registro
    And actualiza la reserva de inventario e insumos
    And envía la actualización de la comanda a cocina
    And confirma la modificación al cliente

  Scenario: Solo adición permitida por estar en preparación o fuera de tiempo
    Given la comanda ya está en estado "en_preparacion" o superó el tiempo límite de modificación
    When el mesero intenta eliminar un platillo
    Then el sistema bloquea la eliminación y habilita únicamente la adición de productos
    And informa al mesero la restricción para que la comunique al cliente

  Scenario: Cliente agrega un producto extra bajo la restricción de solo-adición
    Given la comanda está bajo la restricción de solo-adición
    When el cliente decide agregar platillos o complementos adicionales
    Then el mesero registra los platillos adicionales
    And el sistema actualiza la reserva de inventario e insumos
    And envía la actualización de la comanda a cocina
    And confirma la modificación al cliente

  Scenario: Cliente no desea agregar nada bajo la restricción de solo-adición
    Given la comanda está bajo la restricción de solo-adición y el mesero ya informó al cliente
    When el cliente decide no agregar ningún producto extra
    Then el proceso de modificación finaliza sin cambios en la comanda

  Scenario: Cocina ve la modificación en tiempo real
    Given la comanda ya era visible en la pantalla de cocina
    When se registra una modificación (adición o eliminación permitida) y se reenvía a cocina
    Then el sistema notifica la actualización a la pantalla de cocina en tiempo real
    And el cocinero ve el detalle actualizado sin recargar la página
```

**Detalles técnicos:**
- El "tiempo límite de modificación" es un parámetro configurable (sugerido por defecto: 5 minutos desde que la comanda se envió a cocina); se recomienda almacenarlo en configuración del sistema en vez de codificarlo fijo, para que la administradora pueda ajustarlo sin desplegar código nuevo.
- La evaluación "¿en preparación o tiempo límite superado?" es una sola condición OR sobre la tabla `orders`: `order.preparation_start_time IS NOT NULL OR now() > order.created_at + tiempo_limite`. `preparation_start_time` es el campo que el script ya reserva para esto — se llena cuando el cocinero marca la comanda como "en preparación" (HU-18).
- Igual que en HU-15, cualquier platillo/complemento agregado debe pasar la misma verificación de disponibilidad de insumos antes de aceptarse.
- Un platillo eliminado de la comanda cambia su `order_item_status_id` a `eliminado` (catálogo de estados de línea de comanda) en vez de borrar la fila — así se conserva la trazabilidad de qué se pidió originalmente aunque ya no se facture. La comanda no necesita una tabla de auditoría aparte para este caso: el propio detalle con su estado por línea es suficiente.
- Emitir el evento `ComandaModificada` por el canal `private-cocina` (ver nota técnica transversal al inicio de esta épica) tras confirmar la actualización de la reserva de inventario.
- El estado por línea (`order_item_status_id`) se usa únicamente para distinguir `activo` de `eliminado` dentro de una comanda — no se usa para llevar un seguimiento independiente de preparación por platillo. El tiempo de preparación (`preparation_start_time`) se controla a nivel de comanda completa, no por línea, así que la restricción de esta HU (bloquear eliminación / habilitar solo adición) sigue evaluándose sobre la comanda entera, no plato por plato.

**Referencias:** RF-18 (extendido), CU017

---

### HU-18 — Seguimiento y actualización de estado de comanda

**Como** cocinero
**Quiero** ver las comandas pendientes actualizarse automáticamente y poder cambiar su estado
**Para** coordinar con el mesero cuándo un pedido está en preparación o listo para entregar, sin depender de recargar la pantalla

```gherkin
Feature: Seguimiento de estado de comanda

  Scenario: Sincronización inicial al abrir la pantalla de cocina
    Given existen comandas en estado "pendiente" o "en_preparacion"
    When el cocinero abre la pantalla de comandas
    Then el sistema consulta y muestra el listado completo de comandas en esos estados
    And a partir de ese momento la pantalla se suscribe al canal de tiempo real de cocina

  Scenario: Reconexión tras pérdida de conexión en tiempo real
    Given la pantalla de cocina estaba suscrita al canal de tiempo real y la conexión se interrumpió
    When la conexión se restablece
    Then el sistema vuelve a sincronizar el listado completo de comandas pendientes y en preparación
    And evita así quedarse con información desactualizada por eventos perdidos durante la desconexión

  Scenario: Marcar una comanda como "en preparación"
    Given una comanda está en estado "pendiente"
    When el cocinero comienza a prepararla y marca el cambio de estado
    Then el sistema actualiza el estado a "en_preparacion"
    And a partir de ese momento HU-17 solo permite adiciones sobre esa comanda

  Scenario: Marcar una comanda como "lista"
    Given una comanda está en estado "en_preparacion"
    When el cocinero termina de prepararla y la marca como lista
    Then el sistema actualiza el estado a "lista"
    And notifica al mesero/cajero que el pedido está listo para entregar

  Scenario: Rol no autorizado intenta cambiar el estado
    Given un usuario con rol distinto a cocinero o mesero/cajero intenta cambiar el estado de una comanda
    When realiza la solicitud
    Then el sistema deniega la operación por no tener el rol permitido
```

**Detalles técnicos:**
- Transición de estados válida: `pendiente → en_preparacion → lista → entregada`, más la rama `pendiente → cancelada` (HU-16). No se permiten saltos hacia atrás.
- Restringir el cambio de estado a los roles `cocinero` y `mesero/cajero`, según ya definido en la HU original.
- La pantalla de cocina combina dos fuentes: una consulta REST (`GET /api/orders?status=pendiente,en_preparacion`) para la carga/resincronización inicial, y la suscripción por Laravel Echo a `private-cocina` para los eventos `ComandaEnviadaACocina`, `ComandaCancelada` y `ComandaModificada` (ver nota técnica transversal al inicio de esta épica) mientras la conexión esté activa.
- Al marcar una comanda como "en preparación", este es el paso que debe llenar `orders.preparation_start_time` — HU-17 depende de este campo para decidir si bloquea la eliminación de platillos.

**Referencias:** RF-19, RF-20, CU018

---

### HU-19 — Registro de venta y cierre de comanda

**Como** mesero/cajero
**Quiero** registrar la venta a partir de una comanda lista, calculando el vuelto cuando el pago es en efectivo
**Para** dejar constancia del pago, deducir el inventario real y cerrar el pedido

```gherkin
Feature: Registro de venta

  Scenario: Registrar venta de una comanda lista
    Given una comanda está en estado "lista" y el cliente solicitó la cuenta y pagó
    When el mesero recibe el cobro y confirma la venta
    Then el sistema registra la venta con fecha, hora, usuario responsable, productos, cantidades y total
    And deduce del inventario las existencias reservadas (movimiento de tipo "salida", HU-10)
    And marca la comanda como "entregada"

  Scenario: Calcular el vuelto en un pago en efectivo
    Given el total de la venta es de Q85.00 y el método de pago es efectivo
    When el mesero ingresa que recibió Q100.00 del cliente
    Then el sistema calcula y muestra un vuelto de Q15.00
    And registra el monto recibido y el vuelto junto con la venta

  Scenario: Monto recibido menor al total
    Given el total de la venta es de Q85.00 y el método de pago es efectivo
    When el mesero ingresa un monto recibido menor a Q85.00
    Then el sistema rechaza confirmar la venta hasta que el monto recibido cubra el total

  Scenario: Pago con un método distinto a efectivo
    Given el cliente indica que pagará con un método distinto a efectivo (ej. tarjeta gestionada fuera del sistema)
    When el mesero selecciona ese método de pago y confirma la venta
    Then el sistema registra la venta con monto recibido igual al total y vuelto en cero
    And no intenta procesar ningún cobro electrónico (el procesamiento de pagos con tarjeta está fuera del alcance del sistema)

  Scenario: Liberar la mesa al cerrar la venta
    Given la comanda vendida era de tipo "en mesa" y tenía una mesa asignada
    When se completa el registro de la venta
    Then el sistema marca esa mesa como libre nuevamente

  Scenario: Total de la venta coincide con el total de la comanda
    Given la comanda "lista" tiene un total calculado previamente
    When se registra la venta asociada
    Then el total de la venta es exactamente el total de esa comanda, sin recalcularse manualmente

  Scenario: Intentar registrar una venta de una comanda no lista
    Given una comanda está en estado "pendiente" o "en_preparacion"
    When se intenta registrar una venta directamente sobre ella
    Then el sistema rechaza la operación indicando que la comanda debe estar en estado "lista"
```

**Detalles técnicos:**
- Tabla `sales` (order_id único, cashier_user_id, receipt_type_id, payment_method_id, sale_status_id, receipt_number, subtotal, discount, tax, total, received_amount, change_amount), según `pollo_charly_db.sql` — los productos/cantidades se obtienen de `order_items`, no se duplican en la venta.
- `change_amount = received_amount - total`, calculado y validado en backend (rechazar si `received_amount < total`), nunca confiar en un vuelto enviado desde el frontend.
- Para pagos que no son en efectivo, `received_amount = total` y `change_amount = 0` por convención (ver escenario "Pago con un método distinto a efectivo"); `payment_method_id` es un dato informativo del método usado, el sistema no integra ninguna pasarela de pago (explícitamente fuera del alcance del proyecto).
- `discount` y `tax` quedan disponibles en el esquema pero, salvo que el equipo decida lo contrario, esta HU no define reglas de negocio para aplicarlos automáticamente — de momento se puede asumir `0.00` en ambos y dejarlos como campo abierto para una fase futura.
- La deducción de inventario convierte la reserva bloqueada en HU-15 en un movimiento definitivo de tipo `salida` dentro de la misma transacción que crea la venta y marca la comanda como `entregada`.
- "Emitir factura" en el BPMN se interpreta aquí como generar un comprobante interno de venta (`receipt_number`), no facturación electrónica — explícitamente excluida del alcance del proyecto.
- Si la comanda es de tipo `en_mesa`, liberar la mesa (`restaurant_tables.table_status_id = libre`) es parte de la misma transacción que registra la venta.

**Referencias:** RF-21, CU019

---

### HU-22 — Gestión de mesas

**Como** administradora
**Quiero** registrar y consultar las mesas del local y su estado (libre/ocupada)
**Para** que el mesero/cajero pueda asignar cada comanda "en mesa" a una mesa realmente disponible

```gherkin
Feature: Gestión de mesas

  Scenario: Registrar una mesa nueva
    Given estoy autenticada como administradora
    When registro una mesa con número y capacidad
    Then el sistema la guarda en estado "libre"
    And queda disponible para asignarse a comandas de tipo "en mesa"

  Scenario: Número de mesa duplicado
    Given ya existe una mesa registrada con el número 5
    When intento registrar otra mesa con ese mismo número
    Then el sistema rechaza el registro indicando que el número ya existe

  Scenario: Consultar el estado de las mesas
    Given existen mesas registradas con distintos estados
    When el mesero/cajero consulta el listado de mesas al tomar un pedido
    Then el sistema muestra cuáles están libres y cuáles ocupadas

  Scenario: Desactivar una mesa
    Given una mesa ya no está en uso (ej. se retiró del local)
    When la administradora la desactiva
    Then el sistema la excluye de las mesas disponibles para nuevas comandas
    And conserva el historial de comandas que la usaron
```

**Detalles técnicos:**
- Tabla `restaurant_tables` (number único, capacity, table_status_id) + catálogo `table_statuses`, según `pollo_charly_db.sql`.
- El script no tiene una columna `is_active` separada para "mesa dada de baja" — la forma más simple de resolverlo sin agregar una columna nueva es usar el propio catálogo `table_statuses` con un tercer valor (ej. `libre`, `ocupada`, `inactiva`) en vez de mezclar dos conceptos (ocupación operativa vs. si la mesa sigue existiendo) en columnas separadas.
- El estado operativo (`libre`/`ocupada`) lo cambian automáticamente HU-15 (ocupa), HU-16 (libera al cancelar) y HU-19 (libera al vender) — este módulo no expone una acción manual de "ocupar/liberar" fuera de ese ciclo, solo el CRUD del catálogo (alta, edición de capacidad, baja a `inactiva`).
- Esta HU es la más liviana de las 22: no estaba en el BPMN original, se incorporó directamente desde el script de base de datos por decisión del equipo (ver registro de cambios al inicio del documento).

**Referencias:** CU022 (sin RF explícito — funcionalidad incorporada desde el diseño de base de datos, no desde el BPMN)

---

## ÉPICA 6 — Gestión financiera

### HU-20 — Registro y consulta de ingresos, egresos y gastos

**Como** administradora
**Quiero** registrar ingresos adicionales, egresos y gastos operativos, y consultarlos con filtros
**Para** llevar el control económico del negocio más allá de las ventas

```gherkin
Feature: Registro y consulta de movimientos económicos

  Scenario: Registrar un ingreso adicional
    Given ocurre un ingreso que no proviene de una venta
    When lo registro indicando concepto, monto, fecha, responsable y comprobante
    Then el sistema valida los campos, el monto y mis permisos
    And suma el monto al balance
    And guarda el registro en el historial de movimientos económicos

  Scenario: Registrar un egreso
    Given ocurre un egreso del negocio
    When lo registro con concepto, monto, fecha y responsable
    Then el sistema resta el monto del balance
    And lo guarda en el historial

  Scenario: Registrar un gasto operativo
    Given ocurre un gasto operativo (ej. mantenimiento, insumos de limpieza)
    When lo registro con su justificación, concepto, monto y fecha
    Then el sistema lo resta del balance y guarda la justificación junto con el registro

  Scenario: Datos inválidos al registrar un movimiento
    Given estoy registrando un ingreso, egreso o gasto
    When el monto no es numérico/positivo o falta un campo obligatorio
    Then el sistema rechaza el registro e indica qué corregir

  Scenario: Consultar historial con filtros
    Given existen movimientos económicos registrados en distintas fechas y tipos
    When selecciono un periodo y filtros de consulta (fecha, tipo, responsable)
    Then el sistema muestra el historial y un resumen económico que cumple esos filtros

  Scenario: Generar un reporte desde la consulta
    Given ya consulté el historial filtrado
    When solicito generar un reporte de ese resultado
    Then el sistema genera el reporte en PDF o Excel (ver HU-21)

  Scenario: Una venta en efectivo también queda reflejada en el libro de caja
    Given se registra una venta en efectivo (HU-19)
    When el sistema confirma esa venta
    Then genera automáticamente un movimiento económico de tipo "ingreso" enlazado a esa venta
    And ese movimiento aparece en el historial de esta HU igual que cualquier otro ingreso, pero identificable como proveniente de una venta
```

**Detalles técnicos:**
- Tabla `cash_movements` (user_id, sale_id nullable, cash_movement_type_id [`ingreso`,`egreso`,`gasto`], cash_movement_category_id, amount, concept, justification nullable, evidence_path nullable, date), según `pollo_charly_db.sql`.
- `sale_id` es lo que distingue un movimiento manual (esta HU, `sale_id = null`) de uno generado automáticamente al confirmar una venta en efectivo (HU-19, `sale_id` apunta a esa venta). Ambos vienen de la misma tabla; la vista de historial puede filtrar u ordenar por esta columna para diferenciarlos visualmente.
- El "balance" puede calcularse on-the-fly (`SUM` condicional por `cash_movement_type_id`) en vez de mantenerse como columna acumulada, para evitar inconsistencias — a decidir según volumen esperado de datos (es bajo para este negocio, así que calcular on-the-fly es razonable y más simple).
- Los filtros de RF-25 (fecha, tipo de movimiento, producto, usuario responsable) aplican tanto aquí como en HU-09/HU-10 para consultas de compras/movimientos de producto.

**Referencias:** RF-22, RF-23, RF-24, RF-25, CU020

---

## ÉPICA 7 — Reportes y analítica

### HU-21 — Consulta, análisis y exportación de reportes

**Como** administradora
**Quiero** generar reportes de ventas, compras, productos más vendidos y resultados económicos, y exportarlos
**Para** tomar decisiones informadas y compartir la información en el formato que necesite

```gherkin
Feature: Reportes y exportación

  Scenario: Generar un reporte con filtros válidos
    Given ingreso al módulo de reportes
    When selecciono el tipo de reporte o métrica y defino periodo, producto, movimiento u otros filtros
    Then el sistema valida los filtros y mis permisos de consulta
    And consulta los datos de ventas, compras, ingresos, egresos y movimientos correspondientes
    And genera tablas, gráficas e indicadores para visualizar y analizar

  Scenario: Filtros inválidos
    Given estoy definiendo los filtros de un reporte
    When ingreso un rango de fechas inválido (ej. fecha final anterior a la inicial) o un filtro incompleto
    Then el sistema no genera el reporte
    And solicita corregir los filtros

  Scenario: Productos más vendidos y días de mayor actividad
    Given hay ventas registradas en el periodo seleccionado
    When genero el reporte de productos más vendidos
    Then el sistema identifica los platillos con mayor cantidad vendida en ese periodo
    And puede identificar los días con mayor y menor cantidad de ventas

  Scenario: Resumen económico del periodo
    Given hay ventas, ingresos, egresos y gastos registrados en el periodo seleccionado
    When genero el resumen económico
    Then el sistema muestra el resultado económico (ingresos - egresos - gastos) de ese periodo

  Scenario: Exportar reporte en PDF
    Given ya visualicé un reporte generado
    When elijo exportarlo en formato PDF
    Then el sistema genera y entrega el archivo PDF con la información mostrada

  Scenario: Exportar reporte en Excel
    Given ya visualicé un reporte generado
    When elijo exportarlo en formato Excel
    Then el sistema genera y entrega el archivo .xlsx con la información mostrada

  Scenario: Exportar reporte como imagen
    Given ya visualicé un reporte generado (tabla o gráfica)
    When elijo exportarlo como imagen
    Then el sistema genera y entrega la imagen correspondiente a esa visualización

  Scenario: Reportes no bloquean otros módulos
    Given se está generando un reporte con un volumen considerable de datos
    When otro usuario realiza operaciones normales del sistema al mismo tiempo (ej. registrar una comanda)
    Then esas operaciones responden con normalidad, sin esperar a que el reporte termine de generarse

  Scenario: Acceso restringido a otros roles
    Given un usuario con rol mesero/cajero o cocinero intenta acceder al módulo de reportes
    When realiza la solicitud
    Then el sistema deniega el acceso por no tener el rol de administradora
```

**Detalles técnicos:**
- La generación de reportes debe ejecutarse de forma que no bloquee otros módulos (RNF-06) — considerar colas (`queue`) de Laravel para exportaciones pesadas si el volumen lo justifica; para el volumen esperado de este negocio, una generación síncrona simple puede ser suficiente al inicio.
- Formatos de exportación: PDF, Excel (.xlsx) e imagen — la imagen es una adición respecto a la HU original, tomada directamente del BPMN y coherente con la descripción de procesos de `Proyecto SS1 - Fase 1.md`.
- Reutiliza las mismas fuentes de datos que HU-09/HU-10 (`supplies`/`inventory_movements`), HU-13 (`purchase_orders`), HU-19 (`sales`) y HU-20 (`cash_movements`) — este módulo no duplica datos, solo los consulta y los presenta.
- Acceso restringido exclusivamente al rol Administradora.

**Referencias:** RF-26, RF-27, RF-28, RF-29, RF-30, RF-31, RNF-06, CU021

---

# Parte 2 — Casos de Uso

## CU001 — Inicio de sesión

*Alto nivel*

| Número: | CU001 |
| :---- | :---- |
| **Caso de Uso:** | Inicio de sesión |
| **Actores:** | Usuario |
| **Descripción:** | El Usuario ingresa sus credenciales para acceder al sistema. El sistema valida la información y, si las credenciales son correctas y la cuenta se encuentra activa, permite el acceso mostrando las funciones correspondientes al rol del Usuario. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU001 |
| :---- | :---- |
| **Caso de Uso:** | Inicio de sesión |
| **Actores:** | Usuario |
| **Propósito:** | Permitir al Usuario autenticarse y acceder a las funciones correspondientes a su rol. |
| **Precondición:** | El Usuario debe tener una cuenta registrada y activa. |
| **Resumen:** | El Usuario ingresa su correo y contraseña. El sistema valida las credenciales y verifica que la cuenta no se encuentre desactivada. Si la información es correcta, el Usuario accede al sistema y es dirigido según su rol. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-01, RF-01, RF-05 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Usuario accede al apartado de inicio de sesión. | **2.** El sistema muestra los campos para correo y contraseña. |
| **3.** El Usuario ingresa su correo y contraseña. | **4.** El sistema valida las credenciales ingresadas. |
| **5.** El Usuario solicita iniciar sesión. | **6.** El sistema verifica que las credenciales sean correctas y que la cuenta esté activa. |
|  | **7.** El sistema permite el acceso y muestra las funciones correspondientes al rol del Usuario. |

*Escenarios alternos*

**4.** Las credenciales ingresadas son incorrectas, se muestra un mensaje indicando que los datos no son válidos.

**6.** La cuenta del Usuario está desactivada, se muestra un mensaje indicando que no puede acceder al sistema.

**6.** Las credenciales son correctas, pero el Usuario debe completar la autenticación de dos factores, por lo que se continúa con el CU004.

---

## CU002 — Cierre de sesión

*Alto nivel*

| Número: | CU002 |
| :---- | :---- |
| **Caso de Uso:** | Cierre de sesión |
| **Actores:** | Usuario |
| **Descripción:** | El Usuario solicita cerrar su sesión para evitar que otra persona pueda utilizar su cuenta desde el mismo dispositivo. El sistema invalida la sesión y redirige al Usuario al inicio de sesión. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU002 |
| :---- | :---- |
| **Caso de Uso:** | Cierre de sesión |
| **Actores:** | Usuario (Iniciador) |
| **Propósito:** | Finalizar de forma segura la sesión activa del Usuario. |
| **Precondición:** | El Usuario debe tener una sesión activa. |
| **Resumen:** | El Usuario selecciona la opción para cerrar sesión. El sistema invalida la sesión y redirige al Usuario al apartado de inicio de sesión. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-02, RF-02, RNF-03 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Usuario selecciona la opción "Cerrar sesión". | **2.** El sistema solicita cerrar la sesión activa. |
| **3.** El Usuario confirma la acción. | **4.** El sistema invalida la sesión del Usuario. |
|  | **5.** El sistema redirige al Usuario al inicio de sesión. |

*Escenarios alternos*

**4.** Ocurre un error al invalidar la sesión, se muestra un mensaje indicando que no fue posible completar la operación.

---

## CU003 — Recuperación de contraseña

*Alto nivel*

| Número: | CU003 |
| :---- | :---- |
| **Caso de Uso:** | Recuperación de contraseña |
| **Actores:** | Usuario |
| **Descripción:** | El Usuario solicita recuperar su contraseña utilizando su correo registrado. El sistema genera y envía un enlace o código para restablecer la contraseña. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU003 |
| :---- | :---- |
| **Caso de Uso:** | Recuperación de contraseña |
| **Actores:** | Usuario (Iniciador) |
| **Propósito:** | Permitir al Usuario recuperar el acceso a su cuenta cuando ha olvidado su contraseña. |
| **Precondición:** | El Usuario debe tener un correo registrado en el sistema. |
| **Resumen:** | El Usuario solicita la recuperación ingresando su correo. El sistema procesa la solicitud y envía un enlace o código mediante correo electrónico. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-03, RF-06 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Usuario selecciona la opción de recuperación de contraseña. | **2.** El sistema solicita el correo registrado. |
| **3.** El Usuario ingresa su correo. | **4.** El sistema procesa la solicitud de recuperación. |
|  | **5.** El sistema genera un enlace o código temporal. |
|  | **6.** El sistema envía el enlace o código al correo del Usuario. |
| **7.** El Usuario utiliza el enlace o código recibido. | **8.** El sistema permite establecer una nueva contraseña. |
| **9.** El Usuario ingresa su nueva contraseña. | **10.** El sistema actualiza la contraseña y permite utilizarla para iniciar sesión. |

*Escenarios alternos*

**4.** El correo ingresado no está registrado, el sistema muestra un mensaje general sin revelar si el correo existe.

**8.** El enlace o código ha expirado, el sistema solicita realizar nuevamente la recuperación.

**8.** El código ingresado no es válido, el sistema muestra un mensaje de error.

**10.** La nueva contraseña no cumple los requisitos establecidos, el sistema solicita corregirla.

---

## CU004 — Autenticación de dos factores

*Alto nivel*

| Número: | CU004 |
| :---- | :---- |
| **Caso de Uso:** | Autenticación de dos factores |
| **Actores:** | Usuario |
| **Descripción:** | Después de validar sus credenciales, el Usuario recibe un código de verificación por correo y debe ingresarlo correctamente para completar el inicio de sesión. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU004 |
| :---- | :---- |
| **Caso de Uso:** | Autenticación de dos factores |
| **Actores:** | Usuario (Iniciador) |
| **Propósito:** | Verificar la identidad del Usuario mediante un segundo factor de autenticación. |
| **Precondición:** | Las credenciales del Usuario deben haber sido validadas correctamente. |
| **Resumen:** | El sistema genera un código de un solo uso y lo envía al correo del Usuario. El Usuario introduce el código y el sistema valida que sea correcto y no haya expirado. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-04, RF-07, CU001 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Usuario ingresa correctamente sus credenciales en el inicio de sesión. | **2.** El sistema genera un código temporal de verificación. |
|  | **3.** El sistema envía el código al correo del Usuario. |
| **4.** El Usuario ingresa el código recibido. | **5.** El sistema valida el código. |
|  | **6.** El sistema permite completar el inicio de sesión. |

*Escenarios alternos*

**5.** El código ingresado es incorrecto, se muestra un mensaje de error.

**5.** El código ha expirado, se solicita generar un nuevo código.

**5.** El Usuario supera la cantidad máxima de intentos permitidos, se bloquea temporalmente la validación.

---

## CU005 — Gestión de usuarios

*Alto nivel*

| Número: | CU005 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de usuarios |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora registra, consulta, actualiza y desactiva usuarios del sistema, asignándoles uno de los roles disponibles. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU005 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de usuarios |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Administrar las cuentas y roles de los trabajadores que utilizan el sistema. |
| **Precondición:** | La Administradora debe haber iniciado sesión. |
| **Resumen:** | La Administradora accede a la gestión de usuarios y puede registrar nuevos usuarios, consultar información existente, actualizar datos o desactivar cuentas. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-05, RF-03, RF-04 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora accede a la gestión de usuarios. | **2.** El sistema muestra los usuarios registrados y las opciones disponibles. |
| **3.** La Administradora selecciona una operación. | **4.** El sistema muestra el formulario o información correspondiente. |
| **5.** La Administradora ingresa o modifica la información del usuario y confirma la operación. | **6.** El sistema valida y guarda los datos. |
|  | **7.** El sistema muestra el resultado de la operación. |

*Escenarios alternos*

**3.** La Administradora selecciona consultar, el sistema muestra la información del usuario.

**5.** Los datos ingresados no cumplen las validaciones, el sistema muestra los campos que deben corregirse.

**5.** El correo ya está asociado a otro usuario, el sistema muestra un mensaje indicando que no puede utilizarse.

**5.** La Administradora selecciona desactivar, el sistema realiza una baja lógica sin eliminar el historial del usuario.

---

## CU006 — Gestión de platillos y recetas

*Alto nivel*

| Número: | CU006 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de platillos y recetas |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora registra, consulta, actualiza y desactiva los platillos ofrecidos por el negocio, definiendo además la receta (insumos y cantidades por porción) y los complementos aplicables a cada platillo. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU006 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de platillos y recetas |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Mantener actualizado el menú disponible para las comandas y su consumo real de insumos. |
| **Precondición:** | La Administradora debe haber iniciado sesión. Deben existir insumos y, opcionalmente, complementos registrados. |
| **Resumen:** | La Administradora administra la información de los platillos (nombre, categoría, descripción, precio), define su receta seleccionando insumos y cantidades por porción, y asocia los complementos aplicables. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-06, RF-08 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora ingresa al módulo de platillos y recetas. | **2.** El sistema muestra el catálogo de platillos registrados. |
| **3.** La Administradora selecciona la acción a realizar (Nuevo, Modificar o Desactivar). | **4.** El sistema muestra el formulario correspondiente. |
| **5.** La Administradora ingresa o modifica nombre, categoría, descripción y precio de venta. | **6.** El sistema avanza al formulario de receta. |
| **7.** La Administradora define la receta seleccionando insumos y cantidades por porción, y asocia la lista de complementos aplicables. | **8.** El sistema valida la información básica, los insumos asignados y los precios. |
| **9.** La Administradora confirma el registro. | **10.** El sistema verifica disponibilidad y duplicidad de nombre, guarda el platillo con su receta y complementos, y actualiza el menú y la disponibilidad de venta. |

*Escenarios alternos*

**8.** Los datos no son válidos (campos o cantidades requeridas faltantes), el sistema notifica el error y regresa al formulario de receta.

**10.** El nombre del platillo ya existe, el sistema lo notifica y regresa al formulario de datos básicos.

**3.** La Administradora selecciona desactivar; el sistema verifica si el platillo está en comandas activas: si lo está, notifica la restricción de orden en preparación pendiente y no lo desactiva; si no lo está, cambia su estado a inactivo y actualiza el menú.

---

## CU007 — Gestión de complementos

*Alto nivel*

| Número: | CU007 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de complementos |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora registra, consulta, actualiza y desactiva los complementos (extras) que pueden asociarse a los platillos. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU007 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de complementos |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Mantener actualizado el catálogo de complementos disponibles para las comandas. |
| **Precondición:** | La Administradora debe haber iniciado sesión. |
| **Resumen:** | La Administradora administra nombre, descripción, precio extra e insumos asociados de cada complemento, evitando duplicados y protegiendo los que están en uso activo. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-07, RF-08 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora ingresa al módulo de complementos. | **2.** El sistema muestra el catálogo de complementos registrados. |
| **3.** La Administradora selecciona la acción a realizar (Crear, Editar o Desactivar). | **4.** El sistema muestra el formulario correspondiente. |
| **5.** La Administradora ingresa o modifica nombre, descripción, precio extra e insumos asociados. | **6.** El sistema valida los campos obligatorios y el formato del precio. |
| **7.** La Administradora confirma la operación. | **8.** El sistema verifica si habrá duplicado, guarda el registro/edición y notifica el éxito, mostrando el catálogo actualizado. |

*Escenarios alternos*

**6.** Los datos no son válidos, el sistema notifica el error en el formulario y regresa a los datos ingresados.

**8.** Ya existe un complemento con ese nombre, el sistema notifica el duplicado y regresa al formulario.

**3.** La Administradora selecciona desactivar; el sistema verifica si el complemento está en uso activo (comandas pendientes o en preparación): si lo está, notifica la restricción y no lo desactiva; si no, lo actualiza a estado inactivo.

---

## CU008 — Publicación y gestión del menú del día en landing page

*Alto nivel*

| Número: | CU008 |
| :---- | :---- |
| **Caso de Uso:** | Publicación y gestión del menú del día en landing page |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora marca los platillos que se destacarán como "Menú del día" en la landing page pública, verificando que tengan insumos suficientes antes de publicarlos. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU008 |
| :---- | :---- |
| **Caso de Uso:** | Publicación y gestión del menú del día en landing page |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Mantener actualizada la sección pública "Menú del día" sin exponer platillos sin insumos disponibles. |
| **Precondición:** | La Administradora debe haber iniciado sesión y deben existir platillos activos con receta definida (CU006). |
| **Resumen:** | La Administradora consulta el catálogo de platillos, marca los que desea destacar, el sistema valida disponibilidad de insumos y publica los cambios en la landing page. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-08 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora ingresa al módulo de landing page. | **2.** El sistema muestra el catálogo de platillos disponible. |
| **3.** La Administradora marca los platos a destacar en "Menú del día". | **4.** El sistema verifica la disponibilidad de insumos en almacén para esos platillos. |
|  | **5.** El sistema actualiza el atributo "es_menu_dia" y refresca el contenedor de Menú del día en la landing page. |
|  | **6.** El sistema muestra un mensaje de publicación exitosa. |
| **7.** La Administradora previsualiza la landing page actualizada. | **8.** El sistema confirma que la información publicada corresponde a la selección realizada. |

*Escenarios alternos*

**4.** Un platillo seleccionado no tiene insumos suficientes, el sistema notifica el stock insuficiente y regresa al paso 3 para corregir la selección de platillos agotados o vacíos.

---

## CU009 — Gestión de productos e insumos

*Alto nivel*

| Número: | CU009 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de productos e insumos |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora registra, consulta y actualiza productos e insumos utilizados por el negocio, indicando información como nombre, unidad de medida, categoría y cantidad de referencia. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU009 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de productos e insumos |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Mantener organizada la información de los productos e insumos utilizados por el negocio. |
| **Precondición:** | La Administradora debe haber iniciado sesión. |
| **Resumen:** | La Administradora administra los productos e insumos y mantiene su información actualizada sin perder el historial de movimientos. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-09, RF-09 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora accede a la gestión de productos e insumos. | **2.** El sistema muestra los productos registrados. |
| **3.** La Administradora selecciona registrar, consultar o actualizar. | **4.** El sistema muestra el formulario o información solicitada. |
| **5.** La Administradora ingresa los datos del producto, incluyendo la cantidad de referencia. | **6.** El sistema valida la información. |
| **7.** La Administradora confirma la operación. | **8.** El sistema guarda la información sin eliminar el historial de movimientos. |

*Escenarios alternos*

**6.** La cantidad de referencia u otro campo numérico no es válido, el sistema muestra un error.

**6.** Falta información obligatoria, el sistema indica los campos que deben completarse.

---

## CU010 — Registro de movimientos de productos e insumos

*Alto nivel*

| Número: | CU010 |
| :---- | :---- |
| **Caso de Uso:** | Registro de movimientos de productos e insumos |
| **Actores:** | Cocinero, Mesero/Cajero, Administradora |
| **Descripción:** | Un usuario autorizado registra un movimiento de compra, salida, merma o ajuste sobre un producto o insumo, y el sistema mantiene actualizada la existencia y el historial. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU010 |
| :---- | :---- |
| **Caso de Uso:** | Registro de movimientos de productos e insumos |
| **Actores:** | Cocinero, Mesero/Cajero, Administradora (según el tipo de movimiento) |
| **Propósito:** | Sustituir el control por observación manual con un historial de movimientos trazable. |
| **Precondición:** | El producto o insumo debe estar registrado (CU009). |
| **Resumen:** | El usuario selecciona el producto o insumo, indica el tipo de movimiento, cantidad y observación; el sistema valida, aplica el efecto correspondiente sobre la existencia y guarda el historial. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-10, RF-09 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El usuario selecciona el producto o insumo. | **2.** El sistema muestra el formulario del movimiento. |
| **3.** El usuario ingresa cantidad, fecha y observación, e indica el tipo de movimiento. | **4.** El sistema valida la información del movimiento. |
|  | **5.** El sistema aplica el efecto según el tipo: compra (aumenta), salida (valida disponibilidad y disminuye), merma (registra motivo y disminuye) o ajuste (envía a revisión). |
|  | **6.** El sistema guarda tipo, cantidad, usuario, fecha y existencia resultante, y actualiza el historial de movimientos. |
|  | **7.** El sistema evalúa si la existencia resultante quedó por debajo del mínimo. |

*Escenarios alternos*

**5.** El movimiento es de tipo salida y no hay existencia suficiente, el sistema rechaza el movimiento sin modificar la existencia.

**5.** El movimiento es de tipo ajuste, la Administradora debe aprobarlo o rechazarlo antes de que se aplique (continúa en CU011 si genera alerta, o se aplica directamente si se aprueba).

**7.** La existencia resultante queda por debajo de la cantidad de referencia, el sistema genera automáticamente una alerta de reposición (CU011).

---

## CU011 — Alertas de reposición de insumos

*Alto nivel*

| Número: | CU011 |
| :---- | :---- |
| **Caso de Uso:** | Alertas de reposición de insumos |
| **Actores:** | Cocinero, Administradora, Sistema |
| **Descripción:** | El sistema genera automáticamente una alerta cuando un movimiento deja la existencia de un insumo por debajo del mínimo, y el Cocinero también puede generar una alerta manualmente. La Administradora consulta y atiende las alertas. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU011 |
| :---- | :---- |
| **Caso de Uso:** | Alertas de reposición de insumos |
| **Actores:** | Cocinero (Iniciador en el caso manual), Sistema (Iniciador en el caso automático), Administradora |
| **Propósito:** | Informar oportunamente sobre productos o insumos que necesitan reposición, ya sea por detección automática o por observación directa. |
| **Precondición:** | El Cocinero y la Administradora deben tener cuentas activas; el producto debe estar registrado. |
| **Resumen:** | El sistema genera una alerta automáticamente al detectar existencia por debajo del mínimo, o el Cocinero la genera manualmente. La Administradora consulta las alertas pendientes y puede marcarlas como atendidas. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-11, RF-10, RF-11 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** (Automático) Un movimiento deja la existencia por debajo del mínimo, **o** (Manual) el Cocinero identifica que un insumo se está agotando y selecciona el producto. |  |
|  | **2.** El sistema registra la alerta, indicando origen (automática o manual), fecha y usuario (si aplica), y envía un correo a la Administradora con el detalle. |
| **3.** La Administradora consulta las alertas pendientes. | **4.** El sistema muestra las alertas registradas. |
| **5.** La Administradora atiende la alerta. | **6.** El sistema la marca como atendida y continúa hacia la solicitud de compra (CU013). |

*Escenarios alternos*

**1.** (Manual) El producto seleccionado no existe, el sistema solicita seleccionar un producto registrado.

**5.** La alerta ya fue atendida, el sistema indica que no puede marcarse nuevamente.

---

## CU012 — Gestión de proveedores

*Alto nivel*

| Número: | CU012 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de proveedores |
| **Actores:** | Administradora, Mesero/Cajero, Proveedor (externo) |
| **Descripción:** | La Administradora registra, consulta, actualiza y desactiva proveedores, y consulta su historial de compras e incidencias. Las incidencias se registran cuando el Mesero recibe una entrega no conforme. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU012 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de proveedores |
| **Actores:** | Administradora (Iniciador), Mesero/Cajero, Proveedor (externo, no usuario del sistema) |
| **Propósito:** | Mantener organizada la información de contacto, productos y desempeño histórico de cada proveedor. |
| **Precondición:** | La Administradora debe haber iniciado sesión. |
| **Resumen:** | La Administradora busca o registra proveedores validando su información, y consulta su historial de compras e incidencias registradas por el Mesero al recibir entregas. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-12, RF-12, RF-15 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora busca un proveedor en el sistema. | **2.** El sistema consulta el registro de proveedores. |
| **3.** Si no está registrado, la Administradora solicita la información al Proveedor. | **4.** El sistema valida la información recibida (contacto, productos, precios, días de entrega). |
| **5.** La Administradora confirma el registro. | **6.** El sistema guarda el proveedor y muestra su historial de compras, precios e incidencias. |
| **7.** (Cuando se registra una entrega) El Mesero recibe y revisa la entrega. | **8.** El sistema espera el resultado de la revisión. |
| **9.** El Mesero registra si hubo o no incidencia. | **10.** El sistema actualiza el historial del proveedor. |

*Escenarios alternos*

**2.** El proveedor ya está registrado, el sistema muestra directamente su información.

**4.** Los datos recibidos no son correctos, el sistema solicita nuevamente la información al Proveedor.

**9.** Hubo incidencia (retraso, peso o calidad), el Mesero registra el tipo de incidencia antes de que el sistema actualice el historial.

---

## CU013 — Solicitud, aprobación y registro de compras

*Alto nivel*

| Número: | CU013 |
| :---- | :---- |
| **Caso de Uso:** | Solicitud, aprobación y registro de compras |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora revisa las solicitudes de compra generadas por alertas de reposición, las aprueba o rechaza, y registra la compra correspondiente a un proveedor. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU013 |
| :---- | :---- |
| **Caso de Uso:** | Solicitud, aprobación y registro de compras |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Controlar el abastecimiento del negocio de forma organizada y trazable en vez de basarse solo en observación. |
| **Precondición:** | Debe existir una alerta atendida (CU011) o la Administradora decide comprar directamente. |
| **Resumen:** | La Administradora revisa productos y cantidades sugeridas, aprueba o rechaza la solicitud, y de ser aprobada, selecciona proveedor y registra la compra. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-13, RF-13 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora revisa productos y cantidades de la solicitud generada. | **2.** El sistema muestra la información para decidir. |
| **3.** La Administradora aprueba la solicitud. | **4.** El sistema habilita el registro de la compra. |
| **5.** La Administradora selecciona proveedor y registra fecha, productos, cantidades y precios. | **6.** El sistema calcula el total automáticamente y asocia la compra al proveedor. |
|  | **7.** El sistema envía la orden de compra al Proveedor (CU014). |

*Escenarios alternos*

**3.** La Administradora rechaza la solicitud, el sistema cierra la solicitud sin generar ninguna compra.

**1.** No existe una alerta previa y la Administradora decide comprar directamente, el flujo continúa desde el paso 5 sin pasar por la aprobación de solicitud.

---

## CU014 — Recepción de compras y registro de incidencias

*Alto nivel*

| Número: | CU014 |
| :---- | :---- |
| **Caso de Uso:** | Recepción de compras y registro de incidencias |
| **Actores:** | Mesero/Cajero, Proveedor (externo), Administradora |
| **Descripción:** | El Mesero recibe y revisa la entrega de una compra, confirmándola si es conforme o registrando una incidencia si no lo es. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU014 |
| :---- | :---- |
| **Caso de Uso:** | Recepción de compras y registro de incidencias |
| **Actores:** | Mesero/Cajero (Iniciador), Proveedor (externo), Administradora |
| **Propósito:** | Dejar evidencia de retrasos, diferencias de peso o problemas de calidad antes de aceptar una entrega y actualizar existencias. |
| **Precondición:** | Debe existir una compra registrada en tránsito (CU013). |
| **Resumen:** | El Mesero revisa cantidad, peso y calidad de la entrega; si es conforme, el sistema actualiza existencias e historial; si no, se registra una incidencia y se solicita corrección al Proveedor. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-14, RF-14, RF-15 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Proveedor entrega los productos. | **2.** El sistema espera la confirmación de recepción. |
| **3.** El Mesero verifica cantidad, peso y calidad. | **4.** El sistema registra el resultado de la verificación. |
| **5.** El Mesero confirma que la entrega es conforme. | **6.** El sistema confirma la recepción de la compra y actualiza existencias e historial (CU010). |

*Escenarios alternos*

**3.** La entrega no es conforme, el Mesero registra el tipo de incidencia; la Administradora solicita al Proveedor corregir o reponer los productos, y el Mesero vuelve a revisar la entrega corregida (retorna al paso 3).

---

## CU015 — Registro de comanda con verificación de disponibilidad

*Alto nivel*

| Número: | CU015 |
| :---- | :---- |
| **Caso de Uso:** | Registro de comanda con verificación de disponibilidad |
| **Actores:** | Mesero/Cajero, Cliente |
| **Descripción:** | El Mesero/Cajero registra una comanda con los platillos y complementos solicitados por el Cliente; el sistema verifica disponibilidad de insumos y bloquea existencias antes de enviarla a cocina. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU015 |
| :---- | :---- |
| **Caso de Uso:** | Registro de comanda con verificación de disponibilidad |
| **Actores:** | Mesero/Cajero (Iniciador), Cliente (no usuario del sistema) |
| **Propósito:** | Llevar el pedido del Cliente desde que se toma hasta que se envía a cocina, sin comprometer platillos sin insumos disponibles. |
| **Precondición:** | El Mesero/Cajero debe haber iniciado sesión. El Cliente debe haber comunicado su pedido. |
| **Resumen:** | El Mesero/Cajero registra los platillos, cantidades, complementos y observaciones; el sistema calcula el total, verifica disponibilidad y bloquea existencias antes de enviar la comanda a cocina. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-15, RF-16, RF-17, RF-18 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Cliente comunica su pedido al Mesero/Cajero. | |
| **2.** El Mesero/Cajero indica el tipo de pedido (en mesa o para llevar) y, si aplica, selecciona una mesa libre. | **3.** El sistema valida que la mesa esté libre (si aplica). |
| **4.** El Mesero/Cajero registra la comanda con platillos, cantidades, complementos y observaciones. | **5.** El sistema calcula automáticamente subtotal y total. |
|  | **6.** El sistema verifica disponibilidad de insumos según la receta de cada platillo/complemento. |
|  | **7.** El sistema bloquea las existencias necesarias, marca la mesa como ocupada (si aplica), envía la comanda a cocina en estado "pendiente" y notifica en tiempo real a la pantalla de Cocina. |

*Escenarios alternos*

**3.** La mesa seleccionada ya está ocupada, el sistema rechaza la asignación y solicita elegir otra mesa libre.

**6.** Los insumos no son suficientes, el sistema notifica la falta de producto al Mesero/Cajero, quien informa al Cliente para que seleccione otro platillo (retorna al paso 4 con la selección corregida).

---

## CU016 — Cancelación de comanda antes de preparación

*Alto nivel*

| Número: | CU016 |
| :---- | :---- |
| **Caso de Uso:** | Cancelación de comanda antes de preparación |
| **Actores:** | Mesero/Cajero, Cliente |
| **Descripción:** | El Mesero/Cajero anula una comanda que el Cliente decidió cancelar antes de que cocina comience a prepararla, liberando las existencias bloqueadas. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU016 |
| :---- | :---- |
| **Caso de Uso:** | Cancelación de comanda antes de preparación |
| **Actores:** | Mesero/Cajero (Iniciador), Cliente (no usuario del sistema) |
| **Propósito:** | Permitir revertir una comanda sin afectar el inventario cuando el Cliente cambia de decisión a tiempo. |
| **Precondición:** | La comanda debe existir y no haber sido marcada como "en_preparacion". |
| **Resumen:** | El Cliente solicita cancelar, el Mesero/Cajero anula la comanda y el sistema libera el bloqueo de existencias reservadas. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-16, RF-18 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Cliente solicita cancelar el pedido. | |
| **2.** El Mesero/Cajero anula la comanda antes de preparación. | **3.** El sistema libera el bloqueo de existencias asociado. |
|  | **4.** El sistema registra la cancelación con fecha y usuario responsable, libera la mesa asociada si aplica, y notifica en tiempo real a la pantalla de Cocina. |

*Escenarios alternos*

**2.** La comanda ya está en estado "en_preparacion", el sistema rechaza la cancelación directa y remite al CU017 (modificación de pedido en curso).

---

## CU017 — Modificación de comanda en curso

*Alto nivel*

| Número: | CU017 |
| :---- | :---- |
| **Caso de Uso:** | Modificación de comanda en curso |
| **Actores:** | Mesero/Cajero, Cliente, Sistema |
| **Descripción:** | El Mesero/Cajero modifica una comanda ya enviada a cocina; el sistema evalúa el tiempo transcurrido y el estado para permitir una modificación libre o restringir solo a adiciones. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU017 |
| :---- | :---- |
| **Caso de Uso:** | Modificación de comanda en curso |
| **Actores:** | Mesero/Cajero (Iniciador), Cliente (no usuario del sistema) |
| **Propósito:** | Permitir ajustes razonables a un pedido en curso sin afectar uno que ya está en preparación. |
| **Precondición:** | La comanda debe existir y haber sido enviada a cocina (posterior al CU015). |
| **Resumen:** | El Mesero/Cajero consulta el estado de la comanda; el sistema evalúa tiempo y estado en cocina para decidir si permite modificar/eliminar libremente o solo agregar productos. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-17, RF-18 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Cliente solicita un cambio o ajuste en el pedido. | |
| **2.** El Mesero/Cajero atiende la solicitud y consulta el estado de la orden en el sistema. | **3.** El sistema evalúa el tiempo transcurrido y el estado en cocina. |
| **4.** El Mesero/Cajero modifica o elimina los platillos permitidos. | **5.** El sistema marca los platillos eliminados como "eliminado" en el detalle de la comanda, actualiza la reserva de inventario e insumos, envía la actualización a cocina y notifica el cambio en tiempo real a la pantalla de Cocina. |
| **6.** El Mesero/Cajero confirma la modificación al Cliente. | |

*Escenarios alternos*

**3.** La comanda está en preparación o se superó el tiempo límite, el sistema bloquea la eliminación y habilita solo adición; el Mesero/Cajero informa la restricción al Cliente, quien decide si agrega productos extra (continúa en el paso 4 solo con adiciones) o no agrega nada (el proceso finaliza sin cambios).

---

## CU018 — Seguimiento y actualización de estado de comanda

*Alto nivel*

| Número: | CU018 |
| :---- | :---- |
| **Caso de Uso:** | Seguimiento y actualización de estado de comanda |
| **Actores:** | Cocinero, Mesero/Cajero |
| **Descripción:** | El Cocinero consulta las comandas pendientes y en preparación, y actualiza su estado para coordinar con el Mesero/Cajero cuándo un pedido está listo. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU018 |
| :---- | :---- |
| **Caso de Uso:** | Seguimiento y actualización de estado de comanda |
| **Actores:** | Cocinero (Iniciador), Mesero/Cajero |
| **Propósito:** | Coordinar la preparación y entrega de comandas entre cocina y sala. |
| **Precondición:** | Deben existir comandas registradas (CU015). |
| **Resumen:** | El Cocinero abre su pantalla de comandas, el sistema sincroniza el listado de pendientes y en preparación y a partir de ahí lo mantiene actualizado en tiempo real (nuevas comandas, cancelaciones y modificaciones), mientras el Cocinero actualiza el estado conforme avanza la preparación. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-18, RF-19, RF-20 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Cocinero abre la pantalla de comandas. | **2.** El sistema consulta y muestra las comandas pendientes y en preparación, y suscribe la pantalla al canal de tiempo real de Cocina. |
| **3.** El Cocinero marca una comanda como "en preparación". | **4.** El sistema actualiza el estado. |
| **5.** El Cocinero marca la comanda como "lista". | **6.** El sistema actualiza el estado y notifica al Mesero/Cajero. |

*Escenarios alternos*

**3.** Un usuario con rol distinto a cocinero o mesero/cajero intenta cambiar el estado, el sistema deniega la operación.

**2.** Se pierde la conexión en tiempo real y luego se restablece, el sistema vuelve a sincronizar el listado completo para no depender de eventos que pudieron perderse durante la desconexión.

---

## CU019 — Registro de venta y cierre de comanda

*Alto nivel*

| Número: | CU019 |
| :---- | :---- |
| **Caso de Uso:** | Registro de venta y cierre de comanda |
| **Actores:** | Mesero/Cajero, Cliente |
| **Descripción:** | El Mesero/Cajero registra la venta de una comanda lista tras recibir el cobro del Cliente, deduciendo el inventario y cerrando la comanda. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU019 |
| :---- | :---- |
| **Caso de Uso:** | Registro de venta y cierre de comanda |
| **Actores:** | Mesero/Cajero (Iniciador), Cliente (no usuario del sistema) |
| **Propósito:** | Dejar constancia del pago y cierre del pedido, deduciendo el inventario real. |
| **Precondición:** | La comanda debe estar en estado "lista". |
| **Resumen:** | El Cliente solicita la cuenta y paga; el Mesero/Cajero registra la venta y el sistema deduce el inventario reservado y marca la comanda como entregada. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-19, RF-21 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** El Cliente solicita la cuenta y realiza el pago. | |
| **2.** El Mesero/Cajero indica el método de pago y, si es efectivo, el monto recibido. | **3.** El sistema calcula el vuelto (monto recibido menos total) y confirma la venta. |
|  | **4.** El sistema registra la venta con fecha, hora, usuario, productos, cantidades, total, monto recibido y vuelto. |
|  | **5.** El sistema deduce del inventario las existencias reservadas (movimiento de salida). |
|  | **6.** El sistema marca la comanda como "entregada" y libera la mesa asociada si aplica. |

*Escenarios alternos*

**2.** La comanda no está en estado "lista", el sistema rechaza el registro de la venta indicando el estado requerido.

**3.** El monto recibido es menor al total, el sistema rechaza confirmar la venta hasta que se cubra el total.

**2.** El método de pago no es efectivo, el sistema asume monto recibido igual al total y vuelto en cero, sin procesar ningún cobro electrónico.

---

## CU020 — Registro y consulta de ingresos, egresos y gastos

*Alto nivel*

| Número: | CU020 |
| :---- | :---- |
| **Caso de Uso:** | Registro y consulta de ingresos, egresos y gastos |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora registra ingresos, egresos y gastos operativos, y consulta el historial con filtros para un periodo determinado. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU020 |
| :---- | :---- |
| **Caso de Uso:** | Registro y consulta de ingresos, egresos y gastos |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Llevar el control económico del negocio más allá de las ventas. |
| **Precondición:** | La Administradora debe haber iniciado sesión. |
| **Resumen:** | La Administradora registra un movimiento económico clasificándolo como ingreso, egreso o gasto, y puede consultar el historial filtrado por periodo, tipo o responsable. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-20, RF-22, RF-23, RF-24, RF-25 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora selecciona la operación económica a registrar. | **2.** El sistema solicita concepto, monto, fecha, responsable y comprobante. |
| **3.** La Administradora ingresa los datos. | **4.** El sistema valida campos, monto y permisos de usuario. |
|  | **5.** El sistema clasifica el movimiento (ingreso suma, egreso resta, gasto resta y justifica) y actualiza el balance. |
| **6.** La Administradora selecciona periodo y filtros de consulta. | **7.** El sistema muestra el historial y un resumen económico filtrado. |

*Escenarios alternos*

**4.** Los datos no son válidos, el sistema solicita corregirlos antes de guardar.

**7.** La Administradora solicita generar un reporte del resultado filtrado, el sistema continúa con el CU021.

---

## CU021 — Consulta, análisis y exportación de reportes

*Alto nivel*

| Número: | CU021 |
| :---- | :---- |
| **Caso de Uso:** | Consulta, análisis y exportación de reportes |
| **Actores:** | Administradora |
| **Descripción:** | La Administradora genera reportes de ventas, compras, productos más vendidos y resultados económicos, los visualiza y los exporta en PDF, Excel o imagen. |
| **Tipo:** | Primario |

*Expandido*

| Número: | CU021 |
| :---- | :---- |
| **Caso de Uso:** | Consulta, análisis y exportación de reportes |
| **Actores:** | Administradora (Iniciador) |
| **Propósito:** | Apoyar la toma de decisiones administrativas con información consolidada y exportable. |
| **Precondición:** | La Administradora debe haber iniciado sesión. |
| **Resumen:** | La Administradora selecciona el tipo de reporte y filtros; el sistema valida, consulta los datos correspondientes, genera tablas/gráficas/indicadores y permite exportar el resultado. |
| **Tipo:** | Primario y Esencial |
| **Referencias:** | HU-21, RF-26, RF-27, RF-28, RF-29, RF-30, RF-31 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora ingresa al módulo de reportes. | **2.** El sistema muestra los tipos de reporte o métricas disponibles. |
| **3.** La Administradora selecciona el tipo de reporte y define periodo, producto, movimiento y otros filtros. | **4.** El sistema valida los filtros y permisos de consulta. |
|  | **5.** El sistema consulta ventas, compras, ingresos, egresos y movimientos correspondientes, y genera tablas, gráficas e indicadores. |
| **6.** La Administradora visualiza y analiza los resultados. | |
| **7.** La Administradora decide exportar el reporte y selecciona el formato. | **8.** El sistema genera el archivo en el formato elegido (PDF, Excel o imagen). |

*Escenarios alternos*

**4.** Los filtros no son válidos, el sistema regresa al paso 3 solicitando corregirlos.

**7.** La Administradora decide no exportar, el proceso finaliza tras la visualización.

---

## CU022 — Gestión de mesas

*Alto nivel*

| Número: | CU022 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de mesas |
| **Actores:** | Administradora, Mesero/Cajero |
| **Descripción:** | La Administradora registra y mantiene el catálogo de mesas del local; el Mesero/Cajero consulta su disponibilidad al tomar un pedido en mesa. |
| **Tipo:** | Secundario |

*Expandido*

| Número: | CU022 |
| :---- | :---- |
| **Caso de Uso:** | Gestión de mesas |
| **Actores:** | Administradora (Iniciador del CRUD), Mesero/Cajero (consulta) |
| **Propósito:** | Permitir que las comandas "en mesa" se asignen a una mesa real y disponible del local. |
| **Precondición:** | La Administradora debe haber iniciado sesión para registrar mesas. |
| **Resumen:** | La Administradora registra las mesas del local con número y capacidad. El Mesero/Cajero consulta cuáles están libres u ocupadas al registrar una comanda (CU015); el propio sistema cambia el estado de la mesa según el ciclo de la comanda, sin una acción manual de "ocupar/liberar". |
| **Tipo:** | Secundario |
| **Referencias:** | HU-22 |

*Flujo de eventos*

| Acción de los actores | Respuesta del sistema |
| ----- | ----- |
| **1.** La Administradora registra una mesa con número y capacidad. | **2.** El sistema valida el número y la guarda en estado "libre". |
| **3.** El Mesero/Cajero consulta el listado de mesas al tomar un pedido. | **4.** El sistema muestra el estado actual de cada mesa (libre/ocupada). |

*Escenarios alternos*

**2.** El número de mesa ya existe, el sistema rechaza el registro e indica el conflicto.

**1.** La Administradora desactiva una mesa que ya no está en uso, el sistema la excluye de las mesas disponibles conservando su historial de comandas.
