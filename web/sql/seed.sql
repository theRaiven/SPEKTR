-- Демонстрационные данные для БД сервисного центра (PostgreSQL 14+)
-- ВНИМАНИЕ: рассчитано на пустую схему после create_service_center.sql.
-- Идентификаторы заданы явно для наглядности связей; повторно скрипт не запускать.

BEGIN;

INSERT INTO clients (client_id,last_name,first_name,middle_name,phone,email,created_at) OVERRIDING SYSTEM VALUE VALUES
(1,'Иванова','Анна','Сергеевна','+7-913-000-0001','anna@example.ru','2026-01-10'),
(2,'Петров','Максим','Ильич','+7-913-000-0002','max@example.ru','2026-01-12'),
(3,'Смирнова','Елена',NULL,'+7-913-000-0003','elena@example.ru','2026-02-01');

INSERT INTO device_types (device_type_id,name) OVERRIDING SYSTEM VALUE VALUES
(1,'Ноутбук'),(2,'Телефон');
INSERT INTO brands (brand_id,name) OVERRIDING SYSTEM VALUE VALUES
(1,'Lenovo'),(2,'Samsung'),(3,'Apple');
INSERT INTO device_models (model_id,device_type_id,brand_id,model_name) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,'ThinkPad E14'),(2,2,2,'Galaxy S23'),(3,2,3,'iPhone 13');
INSERT INTO devices (device_id,model_id,serial_number,purchase_date) OVERRIDING SYSTEM VALUE VALUES
(1,1,'LN-E14-001','2024-09-01'),(2,2,'SM-S23-002','2025-02-15'),(3,3,'AP-13-003','2023-12-20'),(4,1,'LN-E14-004','2023-06-12');
INSERT INTO employees (employee_id,last_name,first_name,middle_name,phone,hired_on,dismissed_on) OVERRIDING SYSTEM VALUE VALUES
(1,'Орлова','Мария','Павловна','+7-913-100-0001','2022-03-01',NULL),
(2,'Кузнецов','Игорь','Андреевич','+7-913-100-0002','2021-05-10',NULL),
(3,'Соколов','Денис','Романович','+7-913-100-0003','2023-02-01',NULL),
(4,'Васильев','Артём','Олегович','+7-913-100-0004','2020-08-15',NULL);
INSERT INTO positions (position_id,name) OVERRIDING SYSTEM VALUE VALUES
(1,'Администратор'),(2,'Мастер'),(3,'Бухгалтер');
INSERT INTO employee_positions (employee_position_id,employee_id,position_id,valid_from,valid_to) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,'2022-03-01',NULL),(2,2,2,'2021-05-10',NULL),(3,3,2,'2023-02-01',NULL),(4,4,2,'2020-08-15',NULL);
INSERT INTO masters (employee_id) VALUES (2),(3),(4);
INSERT INTO skill_levels (skill_level_id,name,level_rank) OVERRIDING SYSTEM VALUE VALUES
(1,'Начальный',1),(2,'Средний',2),(3,'Высокий',3);
INSERT INTO specializations (specialization_id,name) OVERRIDING SYSTEM VALUE VALUES
(1,'Ремонт ноутбуков'),(2,'Ремонт телефонов'),(3,'Микропайка плат');
INSERT INTO master_specializations
    (employee_id,specialization_id,skill_level_id,experience_since,valid_from,valid_to,recorded_by_employee_id) VALUES
(2,1,3,'2021-05-10','2021-05-10',NULL,1),(3,2,2,'2023-02-01','2023-02-01',NULL,1),
(4,1,3,'2020-08-15','2020-08-15',NULL,1),(4,2,2,'2022-01-01','2022-01-01',NULL,1),
(4,3,3,'2022-01-01','2022-01-01',NULL,1);
INSERT INTO account_roles (role_id,name,owner_kind) OVERRIDING SYSTEM VALUE VALUES
(1,'Клиент','client'),(2,'Мастер','employee'),(3,'Администратор','employee');
INSERT INTO user_accounts (account_id,login,password_hash,role_id,client_id,employee_id,is_active,created_at) OVERRIDING SYSTEM VALUE VALUES
(1,'anna.demo','$2y$12$J8C27bC53q5OEaivFzSl3uF7i7.LdqYRrsJYBdJSEhzSAWW2Bil/q',1,1,NULL,true,'2026-01-10'),
(2,'igor.demo','$2y$12$Nsqw2cIJgBNBPuz5ygqRwe8FxfNaiK/tk/PRw7hcG0U9DaYsbjabG',2,NULL,2,true,'2026-01-10'),
(3,'admin.demo','$2y$12$c4eGEemb9q1jVqGReYAfVu.r3b3xhkeRtlaemklnjuLDFC4Qa2zta',3,NULL,1,true,'2026-01-10'),
(4,'max.demo','$2y$12$od9bzrkXuyUthYfdvS1gc.KBEv7qXwD39DEOcNlzhzigpwD5tjFxG',1,2,NULL,true,'2026-01-12');

INSERT INTO order_statuses (order_status_id,name,is_issued,is_closed) OVERRIDING SYSTEM VALUE VALUES
(1,'Заявка создана',false,false),(2,'Ожидает приёма устройства',false,false),(3,'Принят',false,false),
(4,'Ожидает мастера',false,false),(5,'В ремонте',false,false),(6,'Ожидает запчасть',false,false),
(7,'Готов',false,false),(8,'Выдан',true,false),(9,'Закрыт',false,true),
(10,'Отказано',false,false),(11,'Отменен',false,false);
INSERT INTO repair_orders (order_id,client_id,device_id,order_status_id,created_at,planned_visit_at,device_received_at,fault_description,queue_priority,planned_ready_at,issued_at,closed_at) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,1,'2026-03-01 08:30+07','2026-03-01 09:00+07','2026-03-01 09:00+07','Ноутбук включается, но нет изображения',0,'2026-03-05 18:00+07',NULL,NULL),
(2,2,2,1,'2026-03-02 09:30+07','2026-03-02 10:30+07','2026-03-02 10:30+07','Быстро разряжается аккумулятор телефона',1,'2026-03-06 18:00+07',NULL,NULL),
(3,1,1,1,'2026-03-10 10:30+07',NULL,'2026-03-10 11:00+07','Повторно пропадает изображение после ремонта',0,'2026-03-12 18:00+07',NULL,NULL),
(4,2,4,1,'2026-03-11 12:00+07','2026-03-14 10:00+07',NULL,'Не заряжается ноутбук',2,'2026-03-16 18:00+07',NULL,NULL);

-- История статусов демонстрационных заказов; последний статус совпадает с repair_orders.
INSERT INTO order_status_history
    (status_history_id,order_id,order_status_id,changed_at,changed_by_employee_id,note)
OVERRIDING SYSTEM VALUE VALUES
(101,1,3,'2026-03-01 09:00+07',1,'Устройство принято'),
(102,1,5,'2026-03-01 10:15+07',NULL,'Мастер начал ремонт'),
(103,1,7,'2026-03-04 14:30+07',1,'Ремонт завершён'),
(104,1,8,'2026-03-04 16:00+07',1,'Устройство выдано'),
(111,1,9,'2026-03-04 16:05+07',1,'Заказ закрыт после выдачи'),
(105,2,3,'2026-03-02 10:30+07',1,'Устройство принято'),
(106,2,5,'2026-03-02 11:30+07',NULL,'Мастер начал ремонт'),
(107,2,7,'2026-03-05 15:30+07',1,'Ремонт завершён'),
(108,3,3,'2026-03-10 11:00+07',1,'Устройство принято'),
(109,3,5,'2026-03-10 12:15+07',NULL,'Повторный ремонт начат'),
(110,4,2,'2026-03-11 12:05+07',1,'Ожидает приёма устройства');

INSERT INTO order_assignments (assignment_id,order_id,employee_id,assigned_at,work_started_at,work_completed_at) OVERRIDING SYSTEM VALUE VALUES
(1,1,2,'2026-03-01 10:00+07','2026-03-01 10:15+07','2026-03-02 12:00+07'),
(2,1,4,'2026-03-02 12:30+07','2026-03-02 13:00+07','2026-03-04 14:00+07'),
(3,2,3,'2026-03-02 11:00+07','2026-03-02 11:30+07','2026-03-05 15:00+07'),
(4,3,4,'2026-03-10 12:00+07','2026-03-10 12:15+07',NULL);
INSERT INTO work_types (work_type_id,name,requires_specialist) OVERRIDING SYSTEM VALUE VALUES
(1,'Диагностика',false),(2,'Ремонт материнской платы',true),
(3,'Замена аккумулятора',true),(4,'Микропайка',true);
INSERT INTO work_type_specializations (work_type_id,specialization_id,minimum_skill_level_id) VALUES
(2,1,2),(3,2,2),(4,3,3);
INSERT INTO work_tariffs (tariff_id,work_type_id,valid_from,price,warranty_days) OVERRIDING SYSTEM VALUE VALUES
(1,1,'2025-01-01',800,30),(2,2,'2025-01-01',4500,180),(3,3,'2025-01-01',2500,90),(4,4,'2025-01-01',3200,180),
(5,2,'2026-04-01',5000,90);
INSERT INTO order_works (order_work_id,assignment_id,tariff_id,tariff_applied_on,performed_at) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,'2026-03-01','2026-03-01 12:00+07'),(2,2,2,'2026-03-04','2026-03-04 14:00+07'),
(3,3,3,'2026-03-05','2026-03-05 15:00+07'),(4,4,4,'2026-03-10','2026-03-10 16:00+07');
INSERT INTO warranty_statuses (warranty_status_id,name) OVERRIDING SYSTEM VALUE VALUES
(1,'Принято'),(2,'В проверке'),(3,'Подтверждено'),(4,'Отказано'),(5,'Выполнено');
INSERT INTO warranty_claims (warranty_claim_id,original_order_work_id,repair_order_id,warranty_status_id,claimed_at,reason,decision) OVERRIDING SYSTEM VALUE VALUES
(1,2,3,2,'2026-03-10 11:00+07','Неисправность повторилась в гарантийный период',NULL);
INSERT INTO reviews (order_work_id,account_id,rating,comment,created_at) VALUES
(2,1,5,'Мастер подробно объяснил причину неисправности','2026-03-05 10:00+07'),
(3,4,4,'Телефон работает, ремонт выполнен в срок','2026-03-06 10:00+07');

INSERT INTO suppliers (supplier_id,name,phone,email) OVERRIDING SYSTEM VALUE VALUES
(1,'ТехноСнаб','+7-383-200-0001','sales@technosnab.example'),(2,'Мобильные детали','+7-383-200-0002','parts@mobile.example');
INSERT INTO parts (part_id,article,name) OVERRIDING SYSTEM VALUE VALUES
(1,'BAT-S23','Аккумулятор для Galaxy S23'),(2,'USB-C-LEN','Разъём питания USB-C'),(3,'FLEX-IPH13','Шлейф дисплея iPhone 13');
INSERT INTO part_tariffs (part_tariff_id,part_id,valid_from,unit_price) OVERRIDING SYSTEM VALUE VALUES
(1,1,'2025-01-01',2200),(2,2,'2025-01-01',900),(3,3,'2025-01-01',1500);
INSERT INTO supplies (supply_id,supplier_id,document_number,supplied_at) OVERRIDING SYSTEM VALUE VALUES
(1,2,'ПТ-1042','2026-02-25 09:00+07'),(2,1,'ПТ-1049','2026-03-01 09:00+07');
INSERT INTO supply_lines (supply_line_id,supply_id,part_id,quantity,purchase_price) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,5,1200),(2,2,2,10,450),(3,2,3,4,900);
INSERT INTO order_parts (order_part_id,order_id,part_id,part_tariff_id,price_applied_on,quantity) OVERRIDING SYSTEM VALUE VALUES
(1,2,1,1,'2026-03-02',1),(2,4,2,2,'2026-03-11',1);
INSERT INTO inventory_movement_types (movement_type_id,name,source_kind,direction) OVERRIDING SYSTEM VALUE VALUES
(1,'Поступление','supply',1),(2,'Расход по заказу','order',-1),
(3,'Положительная корректировка','adjustment',1),(4,'Отрицательная корректировка','adjustment',-1);
INSERT INTO inventory_movements (movement_id,movement_type_id,supply_line_id,order_part_id,adjustment_part_id,quantity_delta,moved_at) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,NULL,NULL,5,'2026-02-25 09:00+07'),(2,1,2,NULL,NULL,10,'2026-03-01 09:00+07'),
(3,1,3,NULL,NULL,4,'2026-03-01 09:00+07'),(4,2,NULL,1,NULL,-1,'2026-03-05 15:00+07');

INSERT INTO benefit_categories (benefit_category_id,name,description) OVERRIDING SYSTEM VALUE VALUES
(1,'Студент','Льготная категория при подтверждении статуса'),(2,'Пенсионер','Льготная категория');
INSERT INTO client_benefits (client_benefit_id,client_id,benefit_category_id,valid_from,valid_to,evidence_reference) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,'2026-01-01','2026-12-31','Демо: студенческий билет');
INSERT INTO discount_calculation_types (discount_calculation_type_id,name,value_kind) OVERRIDING SYSTEM VALUE VALUES
(1,'Процент','percentage'),(2,'Фиксированная сумма','fixed');
INSERT INTO discount_types (discount_type_id,name,calculation_type_id,value) OVERRIDING SYSTEM VALUE VALUES
(1,'Студенческая скидка 10 процентов',1,10),(2,'Весенняя акция 500 рублей',2,500);
INSERT INTO applied_discounts (applied_discount_id,order_id,discount_type_id,client_benefit_id,discount_amount) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,1,530),(2,2,2,NULL,500);
INSERT INTO vouchers (voucher_id,code,client_id,face_value,expires_on,issued_at) OVERRIDING SYSTEM VALUE VALUES
(1,'DEMO-2000-ANNA',1,2000,'2026-12-31','2026-02-01 10:00+07');
INSERT INTO payment_methods (payment_method_id,name,is_voucher) OVERRIDING SYSTEM VALUE VALUES
(1,'Банковская карта',false),(2,'Наличные',false),(3,'Предоплаченный ваучер',true);
INSERT INTO payment_statuses (payment_status_id,name,is_successful) OVERRIDING SYSTEM VALUE VALUES
(1,'Создан',false),(2,'Оплачен',true),(3,'Отменён',false);
INSERT INTO payments (payment_id,order_id,payment_method_id,payment_status_id,paid_at,amount,external_reference) OVERRIDING SYSTEM VALUE VALUES
(1,1,3,2,'2026-03-04 15:00+07',2000,'DEMO-VOUCHER-1'),(2,1,1,2,'2026-03-04 15:05+07',2770,'DEMO-CARD-1'),
(3,2,1,2,'2026-03-05 15:10+07',4200,'DEMO-CARD-2');
-- Частичный возврат оставляет заказ №2 недоплаченным; такой заказ нельзя выдать,
-- пока клиент не внесёт оставшуюся сумму.
INSERT INTO payment_refunds (refund_id,payment_id,refunded_at,amount,reason,external_reference) OVERRIDING SYSTEM VALUE VALUES
(1,3,'2026-03-05 16:00+07',100,'Демонстрационный частичный возврат','DEMO-REFUND-1');
-- Выдаём заказ только после того, как успешные платежи покрыли всю сумму.
UPDATE repair_orders SET order_status_id = 8, issued_at = '2026-03-04 16:00+07' WHERE order_id = 1;
UPDATE repair_orders SET order_status_id = 9, closed_at = '2026-03-04 16:05+07' WHERE order_id = 1;
UPDATE repair_orders SET order_status_id = 7 WHERE order_id = 2;
UPDATE repair_orders SET order_status_id = 5 WHERE order_id = 3;
UPDATE repair_orders SET order_status_id = 2 WHERE order_id = 4;
INSERT INTO voucher_transactions (voucher_transaction_id,voucher_id,payment_id,occurred_at,amount_change,description) OVERRIDING SYSTEM VALUE VALUES
(1,1,1,'2026-03-04 15:00+07',-2000,'Оплата заказа №1 ваучером');
INSERT INTO receipt_delivery_statuses (receipt_delivery_status_id,name) OVERRIDING SYSTEM VALUE VALUES
(1,'Ожидает отправки'),(2,'Отправлен'),(3,'Ошибка отправки');
INSERT INTO receipts (receipt_id,payment_id,receipt_number,issued_at,email_sent_to,delivery_status_id) OVERRIDING SYSTEM VALUE VALUES
(1,1,'DEMO-RECEIPT-001','2026-03-04 15:00+07','anna@example.ru',2),
(2,2,'DEMO-RECEIPT-002','2026-03-04 15:05+07','anna@example.ru',2),
(3,3,'DEMO-RECEIPT-003','2026-03-05 15:10+07','max@example.ru',2);

-- Синхронизируем генераторы идентификаторов после ручных демонстрационных ID.
DO $$
DECLARE r record;
BEGIN
  FOR r IN
    SELECT c.table_name, c.column_name
    FROM information_schema.columns c
    WHERE c.table_schema = current_schema()
      AND c.is_identity = 'YES'
  LOOP
    EXECUTE format(
      'SELECT setval(pg_get_serial_sequence(%L, %L), COALESCE(MAX(%I), 0) + 1, false) FROM %I',
      current_schema() || '.' || r.table_name, r.column_name, r.column_name, r.table_name
    );
  END LOOP;
END $$;

COMMIT;
