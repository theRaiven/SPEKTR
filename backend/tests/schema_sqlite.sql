-- Схема тестовой базы данных для SQLite (локальный запуск автотестов)

CREATE TABLE IF NOT EXISTS clients (
    client_id INTEGER PRIMARY KEY AUTOINCREMENT,
    last_name TEXT NOT NULL,
    first_name TEXT NOT NULL,
    middle_name TEXT,
    phone TEXT NOT NULL,
    email TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS device_types (
    device_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS brands (
    brand_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS device_models (
    model_id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_type_id INTEGER NOT NULL REFERENCES device_types(device_type_id),
    brand_id INTEGER NOT NULL REFERENCES brands(brand_id),
    model_name TEXT NOT NULL,
    UNIQUE (brand_id, model_name)
);

CREATE TABLE IF NOT EXISTS devices (
    device_id INTEGER PRIMARY KEY AUTOINCREMENT,
    model_id INTEGER NOT NULL REFERENCES device_models(model_id),
    serial_number TEXT UNIQUE,
    purchase_date TEXT
);

CREATE TABLE IF NOT EXISTS employees (
    employee_id INTEGER PRIMARY KEY AUTOINCREMENT,
    last_name TEXT NOT NULL,
    first_name TEXT NOT NULL,
    middle_name TEXT,
    phone TEXT,
    hired_on TEXT NOT NULL,
    dismissed_on TEXT
);

CREATE TABLE IF NOT EXISTS positions (
    position_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS employee_positions (
    employee_position_id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(employee_id),
    position_id INTEGER NOT NULL REFERENCES positions(position_id),
    valid_from TEXT NOT NULL,
    valid_to TEXT,
    UNIQUE (employee_id, position_id, valid_from)
);

CREATE TABLE IF NOT EXISTS masters (
    employee_id INTEGER PRIMARY KEY REFERENCES employees(employee_id)
);

CREATE TABLE IF NOT EXISTS skill_levels (
    skill_level_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    level_rank INTEGER NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS specializations (
    specialization_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS master_specializations (
    master_specialization_id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES masters(employee_id),
    specialization_id INTEGER NOT NULL REFERENCES specializations(specialization_id),
    skill_level_id INTEGER NOT NULL REFERENCES skill_levels(skill_level_id),
    experience_since TEXT,
    valid_from TEXT NOT NULL,
    valid_to TEXT,
    recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
    recorded_by_employee_id INTEGER NOT NULL REFERENCES employees(employee_id),
    UNIQUE (employee_id, specialization_id, valid_from)
);

CREATE TABLE IF NOT EXISTS account_roles (
    role_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    owner_kind TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS user_accounts (
    account_id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role_id INTEGER NOT NULL REFERENCES account_roles(role_id),
    client_id INTEGER REFERENCES clients(client_id),
    employee_id INTEGER REFERENCES employees(employee_id),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS auth_tokens (
    token_id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL REFERENCES user_accounts(account_id),
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS order_statuses (
    order_status_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    is_issued INTEGER NOT NULL DEFAULT 0,
    is_closed INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS repair_orders (
    order_id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL REFERENCES clients(client_id),
    device_id INTEGER NOT NULL REFERENCES devices(device_id),
    order_status_id INTEGER NOT NULL REFERENCES order_statuses(order_status_id),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    planned_visit_at TEXT,
    device_received_at TEXT,
    fault_description TEXT NOT NULL,
    queue_priority INTEGER NOT NULL DEFAULT 0,
    planned_ready_at TEXT,
    issued_at TEXT,
    closed_at TEXT,
    rejection_reason TEXT
);

CREATE TABLE IF NOT EXISTS order_status_history (
    status_history_id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES repair_orders(order_id),
    order_status_id INTEGER NOT NULL REFERENCES order_statuses(order_status_id),
    changed_at TEXT NOT NULL DEFAULT (datetime('now')),
    changed_by_employee_id INTEGER REFERENCES employees(employee_id),
    note TEXT
);

CREATE TABLE IF NOT EXISTS order_assignments (
    assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES repair_orders(order_id),
    employee_id INTEGER NOT NULL REFERENCES masters(employee_id),
    assigned_at TEXT NOT NULL DEFAULT (datetime('now')),
    work_started_at TEXT,
    work_completed_at TEXT,
    UNIQUE (assignment_id, order_id)
);

CREATE TABLE IF NOT EXISTS work_types (
    work_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    requires_specialist INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS work_type_specializations (
    work_type_id INTEGER NOT NULL REFERENCES work_types(work_type_id),
    specialization_id INTEGER NOT NULL REFERENCES specializations(specialization_id),
    minimum_skill_level_id INTEGER NOT NULL REFERENCES skill_levels(skill_level_id),
    PRIMARY KEY (work_type_id, specialization_id)
);

CREATE TABLE IF NOT EXISTS work_tariffs (
    tariff_id INTEGER PRIMARY KEY AUTOINCREMENT,
    work_type_id INTEGER NOT NULL REFERENCES work_types(work_type_id),
    valid_from TEXT NOT NULL,
    price REAL NOT NULL,
    warranty_days INTEGER NOT NULL DEFAULT 0,
    UNIQUE (work_type_id, valid_from),
    UNIQUE (tariff_id, work_type_id)
);

CREATE TABLE IF NOT EXISTS order_works (
    order_work_id INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id INTEGER NOT NULL REFERENCES order_assignments(assignment_id),
    tariff_id INTEGER NOT NULL REFERENCES work_tariffs(tariff_id),
    tariff_applied_on TEXT NOT NULL DEFAULT (date('now')),
    performed_at TEXT
);

CREATE TABLE IF NOT EXISTS warranty_statuses (
    warranty_status_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS warranty_claims (
    warranty_claim_id INTEGER PRIMARY KEY AUTOINCREMENT,
    original_order_work_id INTEGER NOT NULL REFERENCES order_works(order_work_id),
    repair_order_id INTEGER REFERENCES repair_orders(order_id),
    warranty_status_id INTEGER NOT NULL REFERENCES warranty_statuses(warranty_status_id),
    claimed_at TEXT NOT NULL DEFAULT (datetime('now')),
    reason TEXT NOT NULL,
    decision TEXT
);

CREATE TABLE IF NOT EXISTS reviews (
    order_work_id INTEGER PRIMARY KEY REFERENCES order_works(order_work_id),
    account_id INTEGER NOT NULL REFERENCES user_accounts(account_id),
    rating INTEGER NOT NULL,
    comment TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT
);

CREATE TABLE IF NOT EXISTS parts (
    part_id INTEGER PRIMARY KEY AUTOINCREMENT,
    article TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS part_tariffs (
    part_tariff_id INTEGER PRIMARY KEY AUTOINCREMENT,
    part_id INTEGER NOT NULL REFERENCES parts(part_id),
    valid_from TEXT NOT NULL,
    unit_price REAL NOT NULL,
    UNIQUE (part_id, valid_from),
    UNIQUE (part_tariff_id, part_id)
);

CREATE TABLE IF NOT EXISTS supplies (
    supply_id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL REFERENCES suppliers(supplier_id),
    document_number TEXT,
    supplied_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS supply_lines (
    supply_line_id INTEGER PRIMARY KEY AUTOINCREMENT,
    supply_id INTEGER NOT NULL REFERENCES supplies(supply_id),
    part_id INTEGER NOT NULL REFERENCES parts(part_id),
    quantity INTEGER NOT NULL,
    purchase_price REAL NOT NULL,
    UNIQUE (supply_line_id, part_id)
);

CREATE TABLE IF NOT EXISTS order_parts (
    order_part_id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES repair_orders(order_id),
    part_id INTEGER NOT NULL REFERENCES parts(part_id),
    part_tariff_id INTEGER NOT NULL REFERENCES part_tariffs(part_tariff_id),
    price_applied_on TEXT NOT NULL DEFAULT (date('now')),
    quantity INTEGER NOT NULL,
    UNIQUE (order_part_id, part_id)
);

CREATE TABLE IF NOT EXISTS inventory_movement_types (
    movement_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    source_kind TEXT NOT NULL,
    direction INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS inventory_movements (
    movement_id INTEGER PRIMARY KEY AUTOINCREMENT,
    movement_type_id INTEGER NOT NULL REFERENCES inventory_movement_types(movement_type_id),
    supply_line_id INTEGER REFERENCES supply_lines(supply_line_id),
    order_part_id INTEGER REFERENCES order_parts(order_part_id),
    adjustment_part_id INTEGER REFERENCES parts(part_id),
    quantity_delta INTEGER NOT NULL,
    moved_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS benefit_categories (
    benefit_category_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS client_benefits (
    client_benefit_id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL REFERENCES clients(client_id),
    benefit_category_id INTEGER NOT NULL REFERENCES benefit_categories(benefit_category_id),
    valid_from TEXT NOT NULL,
    valid_to TEXT,
    evidence_reference TEXT,
    UNIQUE (client_benefit_id, client_id)
);

CREATE TABLE IF NOT EXISTS discount_calculation_types (
    discount_calculation_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    value_kind TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS discount_types (
    discount_type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    calculation_type_id INTEGER NOT NULL REFERENCES discount_calculation_types(discount_calculation_type_id),
    value REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS applied_discounts (
    applied_discount_id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES repair_orders(order_id),
    discount_type_id INTEGER NOT NULL REFERENCES discount_types(discount_type_id),
    client_benefit_id INTEGER REFERENCES client_benefits(client_benefit_id),
    calculation_base REAL NOT NULL DEFAULT 0,
    discount_amount REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS vouchers (
    voucher_id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    client_id INTEGER NOT NULL REFERENCES clients(client_id),
    face_value REAL NOT NULL,
    expires_on TEXT,
    issued_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS payment_methods (
    payment_method_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    is_voucher INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS payment_statuses (
    payment_status_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    is_successful INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES repair_orders(order_id),
    payment_method_id INTEGER NOT NULL REFERENCES payment_methods(payment_method_id),
    payment_status_id INTEGER NOT NULL REFERENCES payment_statuses(payment_status_id),
    paid_at TEXT,
    amount REAL NOT NULL,
    external_reference TEXT
);

CREATE TABLE IF NOT EXISTS payment_refunds (
    refund_id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INTEGER NOT NULL REFERENCES payments(payment_id),
    refunded_at TEXT NOT NULL DEFAULT (datetime('now')),
    amount REAL NOT NULL,
    reason TEXT NOT NULL,
    external_reference TEXT
);

CREATE TABLE IF NOT EXISTS voucher_transactions (
    voucher_transaction_id INTEGER PRIMARY KEY AUTOINCREMENT,
    voucher_id INTEGER NOT NULL REFERENCES vouchers(voucher_id),
    payment_id INTEGER UNIQUE REFERENCES payments(payment_id),
    occurred_at TEXT NOT NULL DEFAULT (datetime('now')),
    amount_change REAL NOT NULL,
    description TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS receipt_delivery_statuses (
    receipt_delivery_status_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS receipts (
    receipt_id INTEGER PRIMARY KEY AUTOINCREMENT,
    payment_id INTEGER NOT NULL UNIQUE REFERENCES payments(payment_id),
    receipt_number TEXT NOT NULL UNIQUE,
    issued_at TEXT NOT NULL DEFAULT (datetime('now')),
    email_sent_to TEXT,
    delivery_status_id INTEGER NOT NULL REFERENCES receipt_delivery_statuses(receipt_delivery_status_id)
);
