-- Начальные данные для тестов (SQLite)

INSERT OR IGNORE INTO account_roles (name, owner_kind) VALUES
('admin', 'employee'),
('master', 'employee'),
('client', 'client');

INSERT OR IGNORE INTO order_statuses (name, is_issued, is_closed) VALUES
('Новая', 0, 0),
('Принята', 0, 0),
('Отклонена', 0, 1),
('Передана мастеру', 0, 0),
('Диагностика', 0, 0),
('В работе', 0, 0),
('Ожидание запчастей', 0, 0),
('Ожидает оплаты', 0, 0),
('Завершена', 1, 1);

INSERT OR IGNORE INTO device_types (name) VALUES
('Смартфон'),
('Ноутбук'),
('Планшет'),
('Компьютер'),
('Принтер'),
('Монитор'),
('Игровая приставка'),
('Телевизор'),
('Другая техника');

INSERT OR IGNORE INTO brands (name) VALUES
('Samsung'),
('Apple'),
('ASUS'),
('Lenovo'),
('Xiaomi'),
('HP'),
('Sony');

INSERT OR IGNORE INTO positions (name) VALUES
('Управляющий'),
('Старший мастер'),
('Мастер по ремонту'),
('Приёмщик');

INSERT OR IGNORE INTO skill_levels (name, level_rank) VALUES
('Младший специалист', 1),
('Специалист', 2),
('Ведущий специалист', 3),
('Эксперт', 4);

INSERT OR IGNORE INTO specializations (name) VALUES
('Ремонт смартфонов'),
('Ремонт ноутбуков'),
('Ремонт планшетов'),
('Ремонт компьютеров'),
('Ремонт принтеров'),
('Ремонт мониторов'),
('Ремонт приставок'),
('Ремонт телевизоров'),
('Ремонт прочей электроники');

INSERT OR IGNORE INTO payment_methods (name, is_voucher) VALUES
('Наличные', 0),
('Банковская карта', 0),
('Ваучер', 1);

INSERT OR IGNORE INTO payment_statuses (name, is_successful) VALUES
('Ожидает', 0),
('Проведен', 1),
('Отклонен', 0);

INSERT OR IGNORE INTO discount_calculation_types (name, value_kind) VALUES
('Процентная скидка', 'percentage'),
('Фиксированная сумма', 'fixed');

INSERT OR IGNORE INTO discount_types (name, calculation_type_id, value) VALUES
('Промокод WELCOME5 (5%)', 1, 5.00),
('Пенсионная льгота (10%)', 1, 10.00),
('Студенческая льгота (7%)', 1, 7.00),
('Скидка постоянного клиента (500 руб)', 2, 500.00);

INSERT OR IGNORE INTO benefit_categories (name, description) VALUES
('Пенсионная', 'Для граждан пенсионного возраста'),
('Студенческая', 'Для студентов очной формы обучения'),
('Инвалидность', 'Для граждан с подтверждённой инвалидностью');

INSERT OR IGNORE INTO inventory_movement_types (name, source_kind, direction) VALUES
('Приход по поставке', 'supply', 1),
('Списание в заказ', 'order', -1),
('Инвентаризационная корректировка (+)', 'adjustment', 1),
('Инвентаризационная корректировка (-)', 'adjustment', -1);

INSERT OR IGNORE INTO employees (last_name, first_name, middle_name, phone, hired_on) VALUES
('Петров', 'Петр', 'Николаевич', '+79990001122', '2023-01-10');

INSERT OR IGNORE INTO employee_positions (employee_id, position_id, valid_from) VALUES
(1, 1, '2023-01-10');

INSERT OR IGNORE INTO employees (last_name, first_name, middle_name, phone, hired_on) VALUES
('Сидоров', 'Алексей', 'Петрович', '+79990003344', '2023-02-15');

INSERT OR IGNORE INTO masters (employee_id) VALUES (2);
INSERT OR IGNORE INTO employee_positions (employee_id, position_id, valid_from) VALUES (2, 3, '2023-02-15');
INSERT OR IGNORE INTO master_specializations (employee_id, specialization_id, skill_level_id, experience_since, valid_from, recorded_by_employee_id) VALUES
(2, 1, 3, '2019-03-01', '2023-02-15', 1),
(2, 3, 2, '2020-05-01', '2023-02-15', 1);

INSERT OR IGNORE INTO employees (last_name, first_name, middle_name, phone, hired_on) VALUES
('Смирнов', 'Дмитрий', 'Иванович', '+79990004455', '2023-03-01');

INSERT OR IGNORE INTO masters (employee_id) VALUES (3);
INSERT OR IGNORE INTO employee_positions (employee_id, position_id, valid_from) VALUES (3, 3, '2023-03-01');
INSERT OR IGNORE INTO master_specializations (employee_id, specialization_id, skill_level_id, experience_since, valid_from, recorded_by_employee_id) VALUES
(3, 2, 3, '2018-09-01', '2023-03-01', 1),
(3, 4, 3, '2018-09-01', '2023-03-01', 1);

INSERT OR IGNORE INTO clients (last_name, first_name, middle_name, phone, email) VALUES
('Иванов', 'Иван', 'Петрович', '+79991234567', 'ivan@example.test');

INSERT OR IGNORE INTO vouchers (code, client_id, face_value, expires_on) VALUES
('GIFT500', 1, 500.00, '2027-12-31');

-- Хеши паролей: admin, master, master2 -> spektr123; ivan -> client123
INSERT OR IGNORE INTO user_accounts (login, password_hash, role_id, employee_id, client_id, is_active) VALUES
('admin', '$2y$10$Dc6GNgb/pWO9r0ZD.lXl2OrrLC1Vb1sXGyaGp8O2qNERBdQ2f.DC.', 1, 1, null, 1),
('master', '$2y$10$Dc6GNgb/pWO9r0ZD.lXl2OrrLC1Vb1sXGyaGp8O2qNERBdQ2f.DC.', 2, 2, null, 1),
('master2', '$2y$10$Dc6GNgb/pWO9r0ZD.lXl2OrrLC1Vb1sXGyaGp8O2qNERBdQ2f.DC.', 2, 3, null, 1),
('ivan', '$2y$10$QVlMAQM9aYbCW2C57MajAOI.ZteG6urlAjAL24SVZCL2XCvyj/2QO', 3, null, 1, 1);

INSERT OR IGNORE INTO work_types (name, requires_specialist) VALUES
('Диагностика', 0),
('Замена разъема питания', 1),
('Замена дисплея', 1),
('Очистка системы охлаждения', 1),
('Замена динамика', 1),
('Замена аккумулятора', 1),
('Настройка ПО', 0),
('Ремонт системной платы', 1);

INSERT OR IGNORE INTO work_tariffs (work_type_id, valid_from, price, warranty_days) VALUES
(1, '2024-01-01', 500.00, 0),
(2, '2024-01-01', 1200.00, 90),
(3, '2024-01-01', 2900.00, 120),
(4, '2024-01-01', 1600.00, 30),
(5, '2024-01-01', 1300.00, 90),
(6, '2024-01-01', 1000.00, 90),
(7, '2024-01-01', 900.00, 30),
(8, '2024-01-01', 3500.00, 90);

INSERT OR IGNORE INTO parts (article, name) VALUES
('CON-USB-C-01', 'Разъем USB-C универсальный'),
('OLED-IP13-02', 'Дисплейный модуль iPhone 13 OLED'),
('FAN-ASUS-14', 'Кулер системы охлаждения ASUS'),
('SPK-XM-26', 'Динамик разговорный Xiaomi');

INSERT OR IGNORE INTO part_tariffs (part_id, valid_from, unit_price) VALUES
(1, '2024-01-01', 400.00),
(2, '2024-01-01', 4300.00),
(3, '2024-01-01', 1200.00),
(4, '2024-01-01', 650.00);

INSERT OR IGNORE INTO device_models (device_type_id, brand_id, model_name) VALUES
(1, 1, 'Galaxy S23'),
(2, 3, 'VivoBook 15'),
(1, 2, 'iPhone 13'),
(2, 4, 'IdeaPad 3'),
(3, 5, 'Pad 6');

INSERT OR IGNORE INTO devices (model_id, serial_number, purchase_date) VALUES
(3, 'IP13-RU-88201', '2023-05-10');
