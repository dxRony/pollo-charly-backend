DROP DATABASE IF EXISTS pollo_charly_db;
CREATE DATABASE pollo_charly_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE pollo_charly_db;

SET NAMES utf8mb4;
SET default_storage_engine = InnoDB;

-- usuarios, auth y sistema de laravel

CREATE TABLE roles (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    two_factor_code VARCHAR(10) NULL,
    two_factor_expires_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

CREATE TABLE personal_access_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
);

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX (user_id),
    INDEX (last_activity)
);

CREATE TABLE cache (
    `key` VARCHAR(255) PRIMARY KEY,
    value MEDIUMTEXT NOT NULL,
    expiration INT NOT NULL
);

CREATE TABLE cache_locks (
    `key` VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL
);

CREATE TABLE jobs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX (queue)
);

CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT NULL,
    cancelled_at INT NULL,
    created_at INT NOT NULL,
    finished_at INT NULL
);

CREATE TABLE failed_jobs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- catalogo de datos


CREATE TABLE categories (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE measurement_units (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    abbreviation VARCHAR(10) NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE table_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'disponible', 'ocupada', 'mantenimiento', 'inactiva'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE order_types (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'en_mesa', 'para_llevar'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE order_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'pendiente', 'en_preparacion', 'lista', 'entregada', 'cancelada'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE order_item_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'pendiente', 'en_preparacion', 'listo', 'cancelado', 'eliminado'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE receipt_types (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'ticket', 'factura'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE payment_methods (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'efectivo', 'tarjeta', 'transferencia', 'mixto'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE sale_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'completada', 'anulada'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE cash_movement_types (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'ingreso', 'egreso', 'gasto_operativo'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE cash_movement_categories (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE, -- 'venta', 'pago_proveedor', 'servicios', 'mantenimiento', 'sueldos', 'otro'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE delivery_days (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE purchase_request_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'pendiente', 'aprobada', 'rechazada_sin_comprar', 'procesada'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE purchase_order_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'solicitada', 'recibida_completa', 'recibida_con_incidencia', 'cancelada'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE delivery_incident_types (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'peso_incompleto', 'producto_danado', 'producto_equivocado', 'retraso', 'otro'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE delivery_incident_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'reportada', 'en_correccion', 'resuelta'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE inventory_movement_types (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'compra_entrada', 'consumo_venta', 'merma_dano', 'ajuste_inventario', 'cancelacion_pedido'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE adjustment_status_types (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'aprobado', 'pendiente_aprobacion', 'rechazado'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE alert_origins (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'manual', 'automatic'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE alert_statuses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'pending', 'attended'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- insumos, platos, recetas y complementos

CREATE TABLE supplies (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    measurement_unit_id BIGINT NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    current_stock DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    minimum_stock DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (measurement_unit_id) REFERENCES measurement_units(id) ON DELETE RESTRICT
);

CREATE TABLE dishes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT NOT NULL,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NULL,
    is_daily_menu BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

CREATE TABLE dish_recipes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    dish_id BIGINT NOT NULL,
    supply_id BIGINT NOT NULL,
    required_quantity DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT
);

CREATE TABLE complements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    extra_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE complement_supplies (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    complement_id BIGINT NOT NULL,
    supply_id BIGINT NOT NULL,
    required_quantity DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (complement_id) REFERENCES complements(id) ON DELETE CASCADE,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT
);

CREATE TABLE dish_complements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    dish_id BIGINT NOT NULL,
    complement_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
    FOREIGN KEY (complement_id) REFERENCES complements(id) ON DELETE CASCADE
);

-- mesas y pedidos

CREATE TABLE restaurant_tables (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_status_id BIGINT NOT NULL,
    number INT NOT NULL UNIQUE,
    capacity INT NOT NULL DEFAULT 4,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (table_status_id) REFERENCES table_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    restaurant_table_id BIGINT NULL,
    waiter_user_id BIGINT NOT NULL,
    order_type_id BIGINT NOT NULL,
    order_status_id BIGINT NOT NULL,
    cancellation_reason TEXT NULL,
    notes TEXT NULL,
    preparation_start_time TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (restaurant_table_id) REFERENCES restaurant_tables(id) ON DELETE SET NULL,
    FOREIGN KEY (waiter_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_type_id) REFERENCES order_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_status_id) REFERENCES order_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    dish_id BIGINT NOT NULL,
    order_item_status_id BIGINT NOT NULL, -- 'pendiente', 'en_preparacion', 'listo', 'cancelado', 'eliminado'
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_item_status_id) REFERENCES order_item_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE order_item_complements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_item_id BIGINT NOT NULL,
    complement_id BIGINT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (complement_id) REFERENCES complements(id) ON DELETE RESTRICT
);

-- ventas y movimientos de caja

CREATE TABLE sales (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL UNIQUE,
    cashier_user_id BIGINT NOT NULL,
    receipt_type_id BIGINT NOT NULL,
    payment_method_id BIGINT NOT NULL,
    sale_status_id BIGINT NOT NULL,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    received_amount DECIMAL(10,2) NOT NULL, -- HU-19
    change_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- HU-19
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (cashier_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (receipt_type_id) REFERENCES receipt_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT,
    FOREIGN KEY (sale_status_id) REFERENCES sale_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE cash_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    sale_id BIGINT NULL,
    cash_movement_type_id BIGINT NOT NULL,
    cash_movement_category_id BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    concept VARCHAR(255) NOT NULL,
    justification TEXT NULL,
    evidence_path VARCHAR(255) NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
    FOREIGN KEY (cash_movement_type_id) REFERENCES cash_movement_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (cash_movement_category_id) REFERENCES cash_movement_categories(id) ON DELETE RESTRICT
);

-- proveedores, solicitudes de compra, pedidos de compra e incidencias de entrega

CREATE TABLE suppliers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE supplier_delivery_days (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    delivery_day_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_day_id) REFERENCES delivery_days(id) ON DELETE CASCADE
);

CREATE TABLE supplier_supplies (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    supply_id BIGINT NOT NULL,
    agreed_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT
);

CREATE TABLE purchase_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    requester_user_id BIGINT NOT NULL,
    purchase_request_status_id BIGINT NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (purchase_request_status_id) REFERENCES purchase_request_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE purchase_request_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    purchase_request_id BIGINT NOT NULL,
    supply_id BIGINT NOT NULL,
    suggested_quantity DECIMAL(10,2) NOT NULL,
    approved_quantity DECIMAL(10,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT
);

-- Alertas de Insumos (manuales y automáticas) enlazables a solicitudes de compra
CREATE TABLE supply_alerts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supply_id BIGINT NOT NULL,
    alert_origin_id BIGINT NOT NULL,
    alert_status_id BIGINT NOT NULL,
    user_id BIGINT NULL,
    purchase_request_id BIGINT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_origin_id) REFERENCES alert_origins(id) ON DELETE RESTRICT,
    FOREIGN KEY (alert_status_id) REFERENCES alert_statuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE SET NULL
);

CREATE TABLE purchase_orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    supplier_id BIGINT NOT NULL,
    admin_user_id BIGINT NOT NULL,
    purchase_request_id BIGINT NULL,
    purchase_order_status_id BIGINT NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expected_date DATE NULL,
    received_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_status_id) REFERENCES purchase_order_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE purchase_order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT NOT NULL,
    supply_id BIGINT NOT NULL,
    ordered_quantity DECIMAL(10,2) NOT NULL,
    received_quantity DECIMAL(10,2) NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT
);

CREATE TABLE delivery_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT NOT NULL,
    supplier_id BIGINT NOT NULL,
    receiving_user_id BIGINT NOT NULL,
    delivery_incident_type_id BIGINT NOT NULL,
    delivery_incident_status_id BIGINT NOT NULL,
    description TEXT NOT NULL,
    evidence_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (receiving_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (delivery_incident_type_id) REFERENCES delivery_incident_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (delivery_incident_status_id) REFERENCES delivery_incident_statuses(id) ON DELETE RESTRICT
);

-- movimientos de inventario (kardex)

CREATE TABLE inventory_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supply_id BIGINT NOT NULL,
    inventory_movement_type_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    order_id BIGINT NULL,
    order_item_id BIGINT NULL, -- HU-17: Nullable, points to specific order item for item-level stock reversal
    purchase_order_id BIGINT NULL,
    adjustment_status_type_id BIGINT NULL,
    approver_user_id BIGINT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    previous_stock DECIMAL(12,2) NOT NULL,
    new_stock DECIMAL(12,2) NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (supply_id) REFERENCES supplies(id) ON DELETE RESTRICT,
    FOREIGN KEY (inventory_movement_type_id) REFERENCES inventory_movement_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (adjustment_status_type_id) REFERENCES adjustment_status_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE SET NULL
);
