-- Схема БД сервисного центра (PostgreSQL 14+)
-- Запускайте в пустой базе данных. Скрипт создает таблицы и ограничения,
-- но не добавляет тестовые данные и учетные записи.

BEGIN;

CREATE TABLE clients (
    client_id       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    last_name       varchar(100) NOT NULL,
    first_name      varchar(100) NOT NULL,
    middle_name     varchar(100),
    phone           varchar(30) NOT NULL,
    email           varchar(254),
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE device_types (
    device_type_id  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(100) NOT NULL UNIQUE
);

CREATE TABLE brands (
    brand_id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(120) NOT NULL UNIQUE
);

CREATE TABLE device_models (
    model_id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    device_type_id  bigint NOT NULL REFERENCES device_types(device_type_id) ON DELETE RESTRICT,
    brand_id        bigint NOT NULL REFERENCES brands(brand_id) ON DELETE RESTRICT,
    model_name      varchar(150) NOT NULL,
    UNIQUE (brand_id, model_name)
);

CREATE TABLE devices (
    device_id       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    model_id        bigint NOT NULL REFERENCES device_models(model_id) ON DELETE RESTRICT,
    serial_number   varchar(120),
    purchase_date   date,
    UNIQUE (serial_number)
);

CREATE TABLE employees (
    employee_id     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    last_name       varchar(100) NOT NULL,
    first_name      varchar(100) NOT NULL,
    middle_name     varchar(100),
    phone           varchar(30),
    hired_on        date NOT NULL,
    dismissed_on    date,
    CHECK (dismissed_on IS NULL OR dismissed_on >= hired_on)
);

CREATE TABLE positions (
    position_id     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(100) NOT NULL UNIQUE
);

CREATE TABLE employee_positions (
    employee_position_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    employee_id     bigint NOT NULL REFERENCES employees(employee_id) ON DELETE RESTRICT,
    position_id     bigint NOT NULL REFERENCES positions(position_id) ON DELETE RESTRICT,
    valid_from      date NOT NULL,
    valid_to        date,
    CHECK (valid_to IS NULL OR valid_to > valid_from),
    UNIQUE (employee_id, position_id, valid_from)
);
CREATE INDEX ix_employee_positions_period ON employee_positions(employee_id, valid_from, valid_to);

-- Подтип сотрудника: только сотрудники, внесенные сюда, являются мастерами.
CREATE TABLE masters (
    employee_id     bigint PRIMARY KEY REFERENCES employees(employee_id) ON DELETE RESTRICT
);

CREATE TABLE skill_levels (
    skill_level_id  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    level_rank      smallint NOT NULL UNIQUE CHECK (level_rank > 0)
);

CREATE TABLE specializations (
    specialization_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(100) NOT NULL UNIQUE
);

CREATE TABLE master_specializations (
    master_specialization_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    employee_id     bigint NOT NULL REFERENCES masters(employee_id) ON DELETE RESTRICT,
    specialization_id bigint NOT NULL REFERENCES specializations(specialization_id) ON DELETE RESTRICT,
    skill_level_id  bigint NOT NULL REFERENCES skill_levels(skill_level_id) ON DELETE RESTRICT,
    experience_since date,
    valid_from      date NOT NULL,
    valid_to        date,
    recorded_at     timestamptz NOT NULL DEFAULT now(),
    recorded_by_employee_id bigint NOT NULL REFERENCES employees(employee_id) ON DELETE RESTRICT,
    CHECK (valid_to IS NULL OR valid_to > valid_from),
    UNIQUE (employee_id, specialization_id, valid_from)
);
CREATE UNIQUE INDEX ux_master_specialization_current
    ON master_specializations(employee_id, specialization_id) WHERE valid_to IS NULL;
CREATE INDEX ix_master_specializations_period
    ON master_specializations(employee_id, specialization_id, valid_from, valid_to);

-- Квалификация хранится периодами: периоды одной специализации у мастера
-- не могут пересекаться, а закрывать уже использованный период можно лишь
-- если он продолжает покрывать дату всех выполненных работ.
CREATE FUNCTION protect_master_specialization_history() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Историю квалификаций нельзя удалять; закройте период и добавьте новый';
    END IF;
    IF NEW.employee_id IS DISTINCT FROM OLD.employee_id
       OR NEW.specialization_id IS DISTINCT FROM OLD.specialization_id
       OR NEW.skill_level_id IS DISTINCT FROM OLD.skill_level_id
       OR NEW.experience_since IS DISTINCT FROM OLD.experience_since
       OR NEW.valid_from IS DISTINCT FROM OLD.valid_from
       OR OLD.valid_to IS NOT NULL OR NEW.valid_to IS NULL THEN
        RAISE EXCEPTION 'Квалификацию нельзя переписывать: закройте текущий период и внесите новый';
    END IF;
    IF EXISTS (
        SELECT 1 FROM order_works ow
        JOIN order_assignments a ON a.assignment_id = ow.assignment_id
        WHERE a.employee_id = OLD.employee_id
          AND ow.performed_at IS NOT NULL
          AND ow.performed_at::date >= OLD.valid_from
          AND ow.performed_at::date >= NEW.valid_to
    ) THEN
        RAISE EXCEPTION 'Новый конец периода исключит дату уже выполненной работы';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_master_specialization_history_append_only
BEFORE UPDATE OR DELETE ON master_specializations
FOR EACH ROW EXECUTE FUNCTION protect_master_specialization_history();

CREATE FUNCTION prevent_master_specialization_overlap() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    PERFORM 1 FROM masters WHERE employee_id = NEW.employee_id FOR UPDATE;
    IF EXISTS (
        SELECT 1 FROM master_specializations ms
        WHERE ms.employee_id = NEW.employee_id
          AND ms.specialization_id = NEW.specialization_id
          AND ms.master_specialization_id <> COALESCE(NEW.master_specialization_id, -1)
          AND NEW.valid_from < COALESCE(ms.valid_to, 'infinity'::date)
          AND ms.valid_from < COALESCE(NEW.valid_to, 'infinity'::date)
    ) THEN
        RAISE EXCEPTION 'Периоды одной специализации мастера не могут пересекаться';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_master_specialization_no_overlap
BEFORE INSERT OR UPDATE OF employee_id, specialization_id, valid_from, valid_to ON master_specializations
FOR EACH ROW EXECUTE FUNCTION prevent_master_specialization_overlap();

CREATE TABLE account_roles (
    role_id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    owner_kind      varchar(20) NOT NULL CHECK (owner_kind IN ('client', 'employee'))
);

-- XOR-ограничение запрещает учетную запись без владельца или сразу с двумя.
CREATE TABLE user_accounts (
    account_id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    login           varchar(120) NOT NULL,
    password_hash   text NOT NULL,
    role_id         bigint NOT NULL REFERENCES account_roles(role_id) ON DELETE RESTRICT,
    client_id       bigint REFERENCES clients(client_id) ON DELETE RESTRICT,
    employee_id     bigint REFERENCES employees(employee_id) ON DELETE RESTRICT,
    is_active       boolean NOT NULL DEFAULT true,
    created_at      timestamptz NOT NULL DEFAULT now(),
    CHECK ((client_id IS NOT NULL)::int + (employee_id IS NOT NULL)::int = 1)
);
CREATE UNIQUE INDEX ux_user_accounts_login_ci ON user_accounts (lower(login));
CREATE UNIQUE INDEX ux_user_accounts_client ON user_accounts (client_id) WHERE client_id IS NOT NULL;
CREATE UNIQUE INDEX ux_user_accounts_employee ON user_accounts (employee_id) WHERE employee_id IS NOT NULL;

CREATE FUNCTION check_account_role_owner() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE expected_kind varchar(20);
BEGIN
    SELECT owner_kind INTO expected_kind FROM account_roles WHERE role_id = NEW.role_id;
    IF (NEW.client_id IS NOT NULL AND expected_kind <> 'client')
       OR (NEW.employee_id IS NOT NULL AND expected_kind <> 'employee') THEN
        RAISE EXCEPTION 'Роль учётной записи не соответствует её владельцу';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_account_role_owner
BEFORE INSERT OR UPDATE OF role_id, client_id, employee_id ON user_accounts
FOR EACH ROW EXECUTE FUNCTION check_account_role_owner();

CREATE FUNCTION protect_account_role_owner_kind() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.owner_kind IS DISTINCT FROM OLD.owner_kind
       AND EXISTS (SELECT 1 FROM user_accounts WHERE role_id = OLD.role_id) THEN
        RAISE EXCEPTION 'Нельзя менять тип владельца роли, уже назначенной учётным записям';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_account_role_owner_kind_immutable
BEFORE UPDATE OF owner_kind ON account_roles
FOR EACH ROW EXECUTE FUNCTION protect_account_role_owner_kind();

CREATE TABLE order_statuses (
    order_status_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    is_issued       boolean NOT NULL DEFAULT false,
    is_closed       boolean NOT NULL DEFAULT false,
    CHECK (NOT (is_issued AND is_closed))
);
CREATE UNIQUE INDEX ux_order_status_single_issued ON order_statuses(is_issued) WHERE is_issued;
CREATE UNIQUE INDEX ux_order_status_single_closed ON order_statuses(is_closed) WHERE is_closed;

CREATE TABLE repair_orders (
    order_id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_id       bigint NOT NULL REFERENCES clients(client_id) ON DELETE RESTRICT,
    device_id       bigint NOT NULL REFERENCES devices(device_id) ON DELETE RESTRICT,
    order_status_id bigint NOT NULL REFERENCES order_statuses(order_status_id) ON DELETE RESTRICT,
    created_at      timestamptz NOT NULL DEFAULT now(),
    planned_visit_at timestamptz,
    device_received_at timestamptz,
    fault_description text NOT NULL,
    queue_priority  smallint NOT NULL DEFAULT 0,
    planned_ready_at timestamptz,
    issued_at       timestamptz,
    closed_at       timestamptz,
    CHECK (queue_priority >= 0),
    CHECK (device_received_at IS NULL OR device_received_at >= created_at),
    CHECK (planned_ready_at IS NULL OR planned_ready_at >= created_at),
    CHECK (issued_at IS NULL OR (device_received_at IS NOT NULL AND issued_at >= device_received_at)),
    CHECK (planned_ready_at IS NULL OR planned_ready_at >= COALESCE(device_received_at, created_at)),
    CHECK (closed_at IS NULL OR closed_at >= COALESCE(issued_at, created_at))
);
CREATE INDEX ix_repair_orders_queue ON repair_orders(order_status_id, queue_priority, created_at);

-- Закрытие возможно только после статуса выдачи; выдать заказ можно лишь
-- после полной оплаты. Нельзя создавать заказ сразу закрытым.
CREATE FUNCTION enforce_order_close_after_issue() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_is_closed boolean;
        previous_is_issued boolean;
BEGIN
    SELECT is_closed INTO target_is_closed
    FROM order_statuses WHERE order_status_id = NEW.order_status_id;
    IF target_is_closed IS NULL THEN
        RAISE EXCEPTION 'Не найден статус заказа';
    END IF;
    IF TG_OP = 'INSERT' THEN
        IF target_is_closed OR NEW.closed_at IS NOT NULL THEN
            RAISE EXCEPTION 'Нельзя создать заказ уже закрытым';
        END IF;
        RETURN NEW;
    END IF;
    IF target_is_closed THEN
        SELECT is_issued INTO previous_is_issued
        FROM order_statuses WHERE order_status_id = OLD.order_status_id;
        IF previous_is_issued IS DISTINCT FROM true OR OLD.issued_at IS NULL THEN
            RAISE EXCEPTION 'Заказ можно закрыть только после выдачи';
        END IF;
        IF NEW.closed_at IS NULL THEN NEW.closed_at := now(); END IF;
        IF NEW.closed_at < OLD.issued_at THEN
            RAISE EXCEPTION 'Заказ нельзя закрыть раньше выдачи';
        END IF;
    ELSIF NEW.closed_at IS NOT NULL THEN
        RAISE EXCEPTION 'Дата закрытия допустима только у закрытого заказа';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_repair_order_close_after_issue
BEFORE INSERT OR UPDATE OF order_status_id, issued_at, closed_at ON repair_orders
FOR EACH ROW EXECUTE FUNCTION enforce_order_close_after_issue();

CREATE FUNCTION protect_closed_status_meaning() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.is_closed IS DISTINCT FROM OLD.is_closed
       AND EXISTS (SELECT 1 FROM repair_orders WHERE order_status_id = OLD.order_status_id) THEN
        RAISE EXCEPTION 'Признак закрывающего статуса нельзя менять после его использования';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_status_closed_semantics_immutable
BEFORE UPDATE OF is_closed ON order_statuses
FOR EACH ROW EXECUTE FUNCTION protect_closed_status_meaning();

-- Журнал переходов статусов. repair_orders.order_status_id хранит актуальный статус,
-- а эта таблица — историю; каждое изменение текущего статуса приложения записывают сюда.
CREATE TABLE order_status_history (
    status_history_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id        bigint NOT NULL REFERENCES repair_orders(order_id) ON DELETE RESTRICT,
    order_status_id bigint NOT NULL REFERENCES order_statuses(order_status_id) ON DELETE RESTRICT,
    changed_at      timestamptz NOT NULL DEFAULT now(),
    changed_by_employee_id bigint REFERENCES employees(employee_id) ON DELETE RESTRICT,
    note            text
);
CREATE INDEX ix_order_status_history_timeline ON order_status_history(order_id, changed_at DESC);

-- При создании заказа фиксируем начальный статус. При последующих изменениях
-- заказ и история должны обновляться одной транзакцией.
CREATE FUNCTION write_initial_order_status_history() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO order_status_history(order_id, order_status_id, changed_at)
    VALUES (NEW.order_id, NEW.order_status_id, NEW.created_at);
    RETURN NEW;
END $$;
CREATE TRIGGER trg_repair_order_initial_status
AFTER INSERT ON repair_orders
FOR EACH ROW EXECUTE FUNCTION write_initial_order_status_history();

CREATE FUNCTION assert_order_status_history_consistent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_order_id bigint;
        current_status bigint;
        latest_status bigint;
BEGIN
    target_order_id := NEW.order_id;
    SELECT order_status_id INTO current_status FROM repair_orders WHERE order_id = target_order_id;
    SELECT order_status_id INTO latest_status FROM order_status_history
    WHERE order_id = target_order_id
    ORDER BY changed_at DESC, status_history_id DESC LIMIT 1;
    IF current_status IS DISTINCT FROM latest_status THEN
        RAISE EXCEPTION 'Текущий статус заказа % не совпадает с последней записью истории', target_order_id;
    END IF;
    RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER ctr_repair_order_status_history
AFTER INSERT OR UPDATE OF order_status_id ON repair_orders
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
EXECUTE FUNCTION assert_order_status_history_consistent();
CREATE CONSTRAINT TRIGGER ctr_order_status_history_consistent
AFTER INSERT ON order_status_history
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
EXECUTE FUNCTION assert_order_status_history_consistent();

CREATE FUNCTION prevent_order_status_history_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'История статусов заказа является append-only';
END $$;
CREATE TRIGGER trg_order_status_history_append_only
BEFORE UPDATE OR DELETE ON order_status_history
FOR EACH ROW EXECUTE FUNCTION prevent_order_status_history_rewrite();

CREATE FUNCTION check_order_creation_time() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM order_assignments
        WHERE order_id = NEW.order_id AND assigned_at < NEW.created_at
    ) THEN
        RAISE EXCEPTION 'Дата создания заказа не может быть позже уже существующего назначения';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_repair_order_creation_time
BEFORE INSERT OR UPDATE OF created_at ON repair_orders
FOR EACH ROW EXECUTE FUNCTION check_order_creation_time();

CREATE FUNCTION prevent_reassigning_order_with_dependents() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (NEW.client_id IS DISTINCT FROM OLD.client_id OR NEW.device_id IS DISTINCT FROM OLD.device_id) AND (
        EXISTS (SELECT 1 FROM order_assignments WHERE order_id = OLD.order_id)
        OR EXISTS (SELECT 1 FROM order_parts WHERE order_id = OLD.order_id)
        OR EXISTS (SELECT 1 FROM applied_discounts WHERE order_id = OLD.order_id)
        OR EXISTS (SELECT 1 FROM payments WHERE order_id = OLD.order_id)
        OR EXISTS (
            SELECT 1 FROM warranty_claims wc
            JOIN order_works ow ON ow.order_work_id = wc.original_order_work_id
            JOIN order_assignments a ON a.assignment_id = ow.assignment_id
            WHERE a.order_id = OLD.order_id OR wc.repair_order_id = OLD.order_id
        )
    ) THEN
        RAISE EXCEPTION 'Нельзя менять клиента или устройство заказа после появления работ, запчастей, скидок, платежей или гарантии';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_repair_order_identity_immutable_after_work
BEFORE UPDATE OF client_id, device_id ON repair_orders
FOR EACH ROW EXECUTE FUNCTION prevent_reassigning_order_with_dependents();

CREATE TABLE order_assignments (
    assignment_id   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id        bigint NOT NULL REFERENCES repair_orders(order_id) ON DELETE RESTRICT,
    employee_id     bigint NOT NULL REFERENCES masters(employee_id) ON DELETE RESTRICT,
    assigned_at     timestamptz NOT NULL DEFAULT now(),
    work_started_at timestamptz,
    work_completed_at timestamptz,
    UNIQUE (assignment_id, order_id),
    CHECK (work_started_at IS NULL OR work_started_at >= assigned_at),
    CHECK (work_completed_at IS NULL OR (work_started_at IS NOT NULL AND work_completed_at >= work_started_at))
);
CREATE INDEX ix_order_assignments_employee ON order_assignments(employee_id, assigned_at);

CREATE FUNCTION check_assignment_dates_and_employment() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_created timestamptz;
        employee_hired date;
        employee_dismissed date;
        check_at timestamptz;
BEGIN
    SELECT o.created_at, e.hired_on, e.dismissed_on
    INTO order_created, employee_hired, employee_dismissed
    FROM repair_orders o
    JOIN employees e ON e.employee_id = NEW.employee_id
    WHERE o.order_id = NEW.order_id;
    IF order_created IS NULL OR NEW.assigned_at < order_created THEN
        RAISE EXCEPTION 'Мастера нельзя назначить раньше создания заказа';
    END IF;
    FOREACH check_at IN ARRAY ARRAY[NEW.assigned_at, NEW.work_started_at, NEW.work_completed_at]
    LOOP
        IF check_at IS NOT NULL AND (
            check_at::date < employee_hired
            OR (employee_dismissed IS NOT NULL AND check_at::date > employee_dismissed)
        ) THEN
            RAISE EXCEPTION 'Назначение и работа должны попадать в период работы сотрудника';
        END IF;
    END LOOP;
    IF EXISTS (
        SELECT 1 FROM order_works ow
        WHERE ow.assignment_id = OLD.assignment_id
          AND ow.performed_at IS NOT NULL
          AND (NEW.work_started_at IS NULL OR ow.performed_at < NEW.work_started_at
               OR (NEW.work_completed_at IS NOT NULL AND ow.performed_at > NEW.work_completed_at))
    ) THEN
        RAISE EXCEPTION 'Изменение дат назначения конфликтует с уже выполненными работами';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_assignment_employment_dates
BEFORE INSERT OR UPDATE OF order_id, employee_id, assigned_at, work_started_at, work_completed_at ON order_assignments
FOR EACH ROW EXECUTE FUNCTION check_assignment_dates_and_employment();

CREATE FUNCTION protect_employment_dates_from_assignments() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM order_assignments a
        WHERE a.employee_id = OLD.employee_id
          AND (a.assigned_at::date < NEW.hired_on
               OR (NEW.dismissed_on IS NOT NULL AND (
                   a.assigned_at::date > NEW.dismissed_on
                   OR (a.work_started_at IS NOT NULL AND a.work_started_at::date > NEW.dismissed_on)
                   OR (a.work_completed_at IS NOT NULL AND a.work_completed_at::date > NEW.dismissed_on)
               )))
    ) THEN
        RAISE EXCEPTION 'Новый период работы сотрудника конфликтует с уже существующими назначениями';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_employee_period_protect_assignments
BEFORE UPDATE OF hired_on, dismissed_on ON employees
FOR EACH ROW EXECUTE FUNCTION protect_employment_dates_from_assignments();

CREATE TABLE work_types (
    work_type_id    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(150) NOT NULL UNIQUE,
    requires_specialist boolean NOT NULL DEFAULT false
);

CREATE TABLE work_type_specializations (
    work_type_id    bigint NOT NULL REFERENCES work_types(work_type_id) ON DELETE RESTRICT,
    specialization_id bigint NOT NULL REFERENCES specializations(specialization_id) ON DELETE RESTRICT,
    minimum_skill_level_id bigint NOT NULL REFERENCES skill_levels(skill_level_id) ON DELETE RESTRICT,
    PRIMARY KEY (work_type_id, specialization_id)
);

CREATE FUNCTION protect_work_specialization_requirements() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE old_work_type bigint;
        new_work_type bigint;
BEGIN
    IF TG_OP <> 'INSERT' THEN old_work_type := OLD.work_type_id; END IF;
    IF TG_OP <> 'DELETE' THEN new_work_type := NEW.work_type_id; END IF;
    IF EXISTS (
        SELECT 1 FROM order_works ow
        JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
        WHERE wt.work_type_id IN (old_work_type, new_work_type)
    ) THEN
        RAISE EXCEPTION 'Требования к квалификации нельзя менять после появления работ этого вида';
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_work_specialization_requirements_immutable
BEFORE INSERT OR UPDATE OR DELETE ON work_type_specializations
FOR EACH ROW EXECUTE FUNCTION protect_work_specialization_requirements();

CREATE FUNCTION protect_work_type_specialist_requirement() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.requires_specialist IS DISTINCT FROM OLD.requires_specialist
       AND EXISTS (
           SELECT 1 FROM order_works ow
           JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
           WHERE wt.work_type_id = OLD.work_type_id
       ) THEN
        RAISE EXCEPTION 'Требование к специализации нельзя менять после появления работ этого вида';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_work_type_specialist_requirement_immutable
BEFORE UPDATE OF requires_specialist ON work_types
FOR EACH ROW EXECUTE FUNCTION protect_work_type_specialist_requirement();

-- Тарифы версионируются. Старые строки нельзя переписывать при изменении условий.
CREATE TABLE work_tariffs (
    tariff_id       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    work_type_id    bigint NOT NULL REFERENCES work_types(work_type_id) ON DELETE RESTRICT,
    valid_from      date NOT NULL,
    price           numeric(12,2) NOT NULL CHECK (price >= 0),
    warranty_days   integer NOT NULL DEFAULT 0 CHECK (warranty_days >= 0),
    UNIQUE (work_type_id, valid_from),
    UNIQUE (tariff_id, work_type_id)
);

CREATE FUNCTION require_newest_tariff_version() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE latest_valid_from date;
BEGIN
    SELECT max(valid_from) INTO latest_valid_from FROM work_tariffs WHERE work_type_id = NEW.work_type_id;
    IF latest_valid_from IS NOT NULL AND NEW.valid_from <= latest_valid_from THEN
        RAISE EXCEPTION 'Новая версия тарифа должна иметь дату начала позже существующих версий';
    END IF;
    IF EXISTS (
        SELECT 1 FROM order_works ow
        JOIN work_tariffs old_tariff ON old_tariff.tariff_id = ow.tariff_id
        WHERE old_tariff.work_type_id = NEW.work_type_id
          AND ow.tariff_applied_on >= NEW.valid_from
    ) THEN
        RAISE EXCEPTION 'Новая версия тарифа не может менять условия уже оценённых работ';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_work_tariff_version_order
BEFORE INSERT ON work_tariffs
FOR EACH ROW EXECUTE FUNCTION require_newest_tariff_version();

CREATE FUNCTION prevent_work_tariff_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Версии тарифов неизменяемы; для изменения добавьте новую версию';
END $$;
CREATE TRIGGER trg_work_tariff_append_only
BEFORE UPDATE OR DELETE ON work_tariffs
FOR EACH ROW EXECUTE FUNCTION prevent_work_tariff_rewrite();

-- Заказ определяется через назначение мастера, поэтому order_id здесь не дублируется.
CREATE TABLE order_works (
    order_work_id   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    assignment_id   bigint NOT NULL REFERENCES order_assignments(assignment_id) ON DELETE RESTRICT,
    tariff_id       bigint NOT NULL REFERENCES work_tariffs(tariff_id) ON DELETE RESTRICT,
    tariff_applied_on date NOT NULL DEFAULT CURRENT_DATE,
    performed_at    timestamptz
);
CREATE INDEX ix_order_works_assignment ON order_works(assignment_id);

CREATE FUNCTION check_order_work_tariff_and_qualification() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE tariff_type bigint;
        tariff_start date;
        needs_specialist boolean;
        assigned_employee bigint;
        qualification_date date;
BEGIN
    SELECT work_type_id, valid_from INTO tariff_type, tariff_start
    FROM work_tariffs WHERE tariff_id = NEW.tariff_id;
    IF tariff_start IS NULL OR NEW.tariff_applied_on < tariff_start THEN
        RAISE EXCEPTION 'Тариф не действовал на дату его применения';
    END IF;
    IF EXISTS (
        SELECT 1 FROM work_tariffs newer
        WHERE newer.work_type_id = tariff_type
          AND newer.valid_from > tariff_start
          AND newer.valid_from <= NEW.tariff_applied_on
    ) THEN
        RAISE EXCEPTION 'Для этой даты уже действует более новая версия тарифа';
    END IF;
    SELECT wt.requires_specialist, a.employee_id INTO needs_specialist, assigned_employee
    FROM work_types wt
    JOIN order_assignments a ON a.assignment_id = NEW.assignment_id
    WHERE wt.work_type_id = tariff_type;
    qualification_date := COALESCE(NEW.performed_at::date, NEW.tariff_applied_on);
    IF needs_specialist AND NOT EXISTS (
        SELECT 1 FROM work_type_specializations wts
        JOIN master_specializations ms
          ON ms.specialization_id = wts.specialization_id
         AND ms.employee_id = assigned_employee
         AND qualification_date >= ms.valid_from
         AND (ms.valid_to IS NULL OR qualification_date < ms.valid_to)
        JOIN skill_levels actual_level ON actual_level.skill_level_id = ms.skill_level_id
        JOIN skill_levels minimum_level ON minimum_level.skill_level_id = wts.minimum_skill_level_id
        WHERE wts.work_type_id = tariff_type
          AND actual_level.level_rank >= minimum_level.level_rank
    ) THEN
        RAISE EXCEPTION 'На дату работы у мастера нет специализации требуемого уровня';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_work_tariff_qualification
BEFORE INSERT OR UPDATE OF assignment_id, tariff_id, tariff_applied_on, performed_at ON order_works
FOR EACH ROW EXECUTE FUNCTION check_order_work_tariff_and_qualification();

CREATE FUNCTION check_order_work_performed_time() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE assignment_started timestamptz;
        assignment_completed timestamptz;
BEGIN
    IF NEW.performed_at IS NULL THEN RETURN NEW; END IF;
    SELECT work_started_at, work_completed_at
    INTO assignment_started, assignment_completed
    FROM order_assignments WHERE assignment_id = NEW.assignment_id;
    IF assignment_started IS NULL OR NEW.performed_at < assignment_started
       OR (assignment_completed IS NOT NULL AND NEW.performed_at > assignment_completed) THEN
        RAISE EXCEPTION 'Дата выполнения работы должна входить в период работы назначения';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_work_performed_time
BEFORE INSERT OR UPDATE OF assignment_id, performed_at ON order_works
FOR EACH ROW EXECUTE FUNCTION check_order_work_performed_time();

CREATE FUNCTION freeze_completed_order_work() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.performed_at IS NOT NULL
       AND (NEW.assignment_id IS DISTINCT FROM OLD.assignment_id
            OR NEW.tariff_id IS DISTINCT FROM OLD.tariff_id
            OR NEW.tariff_applied_on IS DISTINCT FROM OLD.tariff_applied_on
            OR NEW.performed_at IS DISTINCT FROM OLD.performed_at) THEN
        RAISE EXCEPTION 'Нельзя менять завершённую работу: тариф, мастер и дата выполнения фиксируются';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_work_freeze_after_completion
BEFORE UPDATE ON order_works
FOR EACH ROW EXECUTE FUNCTION freeze_completed_order_work();

CREATE FUNCTION prevent_reassigning_master_after_work() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.employee_id IS DISTINCT FROM OLD.employee_id
       AND EXISTS (SELECT 1 FROM order_works WHERE assignment_id = OLD.assignment_id) THEN
        RAISE EXCEPTION 'Нельзя менять мастера назначения после добавления выполненных работ';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_assignment_master_immutable_after_work
BEFORE UPDATE OF employee_id ON order_assignments
FOR EACH ROW EXECUTE FUNCTION prevent_reassigning_master_after_work();

CREATE TABLE warranty_statuses (
    warranty_status_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE
);

CREATE TABLE warranty_claims (
    warranty_claim_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    original_order_work_id bigint NOT NULL REFERENCES order_works(order_work_id) ON DELETE RESTRICT,
    repair_order_id bigint REFERENCES repair_orders(order_id) ON DELETE RESTRICT,
    warranty_status_id bigint NOT NULL REFERENCES warranty_statuses(warranty_status_id) ON DELETE RESTRICT,
    claimed_at      timestamptz NOT NULL DEFAULT now(),
    reason          text NOT NULL,
    decision        text
);

CREATE FUNCTION check_warranty_claim_device() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE original_device bigint;
        claim_device bigint;
        completed_at timestamptz;
        warranty_term integer;
BEGIN
    SELECT o.device_id, ow.performed_at, wt.warranty_days
    INTO original_device, completed_at, warranty_term
    FROM order_works ow
    JOIN order_assignments a ON a.assignment_id = ow.assignment_id
    JOIN repair_orders o ON o.order_id = a.order_id
    JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
    WHERE ow.order_work_id = NEW.original_order_work_id;
    IF completed_at IS NULL OR warranty_term <= 0
       OR NEW.claimed_at < completed_at
       OR NEW.claimed_at > completed_at + make_interval(days => warranty_term) THEN
        RAISE EXCEPTION 'Гарантийное обращение должно относиться к выполненной работе и попадать в срок её гарантии';
    END IF;
    IF NEW.repair_order_id IS NOT NULL THEN
        SELECT device_id INTO claim_device
        FROM repair_orders WHERE order_id = NEW.repair_order_id;
        IF original_device IS DISTINCT FROM claim_device THEN
            RAISE EXCEPTION 'Гарантийный заказ должен относиться к тому же устройству, что и исходная работа';
        END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_warranty_claim_same_device
BEFORE INSERT OR UPDATE OF original_order_work_id, repair_order_id, claimed_at ON warranty_claims
FOR EACH ROW EXECUTE FUNCTION check_warranty_claim_device();

CREATE TABLE reviews (
    order_work_id   bigint PRIMARY KEY REFERENCES order_works(order_work_id) ON DELETE RESTRICT,
    account_id      bigint NOT NULL REFERENCES user_accounts(account_id) ON DELETE RESTRICT,
    rating          smallint NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment         text,
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE FUNCTION check_review_author_and_completion() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE work_performed_at timestamptz;
        order_client bigint;
        author_client bigint;
        account_active boolean;
BEGIN
    SELECT ow.performed_at, o.client_id INTO work_performed_at, order_client
    FROM order_works ow
    JOIN order_assignments a ON a.assignment_id = ow.assignment_id
    JOIN repair_orders o ON o.order_id = a.order_id
    WHERE ow.order_work_id = NEW.order_work_id;
    SELECT client_id, is_active INTO author_client, account_active
    FROM user_accounts WHERE account_id = NEW.account_id;
    IF work_performed_at IS NULL OR NEW.created_at < work_performed_at THEN
        RAISE EXCEPTION 'Отзыв можно оставить только после выполнения работы';
    END IF;
    IF author_client IS DISTINCT FROM order_client OR account_active IS DISTINCT FROM true THEN
        RAISE EXCEPTION 'Отзыв может оставить только активная учётная запись клиента этого заказа';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_review_author_and_completion
BEFORE INSERT OR UPDATE OF order_work_id, account_id, created_at ON reviews
FOR EACH ROW EXECUTE FUNCTION check_review_author_and_completion();

CREATE FUNCTION protect_review_author_identity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.client_id IS DISTINCT FROM OLD.client_id
       AND EXISTS (SELECT 1 FROM reviews WHERE account_id = OLD.account_id) THEN
        RAISE EXCEPTION 'Нельзя менять клиента учётной записи, уже оставившей отзыв';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_account_client_immutable_after_review
BEFORE UPDATE OF client_id ON user_accounts
FOR EACH ROW EXECUTE FUNCTION protect_review_author_identity();

CREATE TABLE suppliers (
    supplier_id     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(200) NOT NULL,
    phone           varchar(30),
    email           varchar(254)
);

CREATE TABLE parts (
    part_id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    article         varchar(100) NOT NULL UNIQUE,
    name            varchar(200) NOT NULL
);

-- Версии текущей розничной цены запчасти. Новую версию можно добавлять
-- при изменении рынка независимо от поступления новой поставки. Поэтому
-- старая складская партия при продаже оценивается по действующему тарифу,
-- а закупочная цена остаётся отдельно в строке поставки.
CREATE TABLE part_tariffs (
    part_tariff_id  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    part_id         bigint NOT NULL REFERENCES parts(part_id) ON DELETE RESTRICT,
    valid_from      date NOT NULL,
    unit_price      numeric(12,2) NOT NULL CHECK (unit_price >= 0),
    UNIQUE (part_id, valid_from),
    UNIQUE (part_tariff_id, part_id)
);

CREATE FUNCTION require_newest_part_tariff_version() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE latest_valid_from date;
BEGIN
    PERFORM 1 FROM parts WHERE part_id = NEW.part_id FOR UPDATE;
    SELECT max(valid_from) INTO latest_valid_from FROM part_tariffs WHERE part_id = NEW.part_id;
    IF latest_valid_from IS NOT NULL AND NEW.valid_from <= latest_valid_from THEN
        RAISE EXCEPTION 'Новая цена запчасти должна иметь дату позже предыдущих версий';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_part_tariff_version_order
BEFORE INSERT ON part_tariffs
FOR EACH ROW EXECUTE FUNCTION require_newest_part_tariff_version();

CREATE FUNCTION prevent_part_tariff_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Версии цены запчасти неизменяемы; для изменения добавьте новую версию';
END $$;
CREATE TRIGGER trg_part_tariff_append_only
BEFORE UPDATE OR DELETE ON part_tariffs
FOR EACH ROW EXECUTE FUNCTION prevent_part_tariff_rewrite();

CREATE TABLE supplies (
    supply_id       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    supplier_id     bigint NOT NULL REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    document_number varchar(100),
    supplied_at     timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE supply_lines (
    supply_line_id  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    supply_id       bigint NOT NULL REFERENCES supplies(supply_id) ON DELETE RESTRICT,
    part_id         bigint NOT NULL REFERENCES parts(part_id) ON DELETE RESTRICT,
    quantity        integer NOT NULL CHECK (quantity > 0),
    purchase_price  numeric(12,2) NOT NULL CHECK (purchase_price >= 0),
    UNIQUE (supply_line_id, part_id)
);

CREATE TABLE order_parts (
    order_part_id   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id        bigint NOT NULL REFERENCES repair_orders(order_id) ON DELETE RESTRICT,
    part_id         bigint NOT NULL REFERENCES parts(part_id) ON DELETE RESTRICT,
    part_tariff_id  bigint NOT NULL,
    price_applied_on date NOT NULL DEFAULT CURRENT_DATE,
    quantity        integer NOT NULL CHECK (quantity > 0),
    UNIQUE (order_part_id, part_id),
    FOREIGN KEY (part_tariff_id, part_id) REFERENCES part_tariffs(part_tariff_id, part_id) ON DELETE RESTRICT
);

CREATE FUNCTION check_order_part_tariff() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE tariff_part bigint;
        tariff_start date;
        order_created_at timestamptz;
BEGIN
    SELECT part_id, valid_from INTO tariff_part, tariff_start
    FROM part_tariffs WHERE part_tariff_id = NEW.part_tariff_id;
    IF tariff_part IS DISTINCT FROM NEW.part_id OR tariff_start IS NULL
       OR NEW.price_applied_on < tariff_start THEN
        RAISE EXCEPTION 'Версия цены должна относиться к этой запчасти и действовать на дату применения';
    END IF;
    SELECT created_at INTO order_created_at
    FROM repair_orders WHERE order_id = NEW.order_id;
    IF order_created_at IS NULL OR NEW.price_applied_on < order_created_at::date THEN
        RAISE EXCEPTION 'Цена запчасти не может применяться раньше даты заказа';
    END IF;
    IF EXISTS (
        SELECT 1 FROM part_tariffs newer
        WHERE newer.part_id = NEW.part_id
          AND newer.valid_from > tariff_start
          AND newer.valid_from <= NEW.price_applied_on
    ) THEN
        RAISE EXCEPTION 'Для этой даты уже действует более новая цена запчасти';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_part_tariff
BEFORE INSERT OR UPDATE OF part_id, part_tariff_id, price_applied_on ON order_parts
FOR EACH ROW EXECUTE FUNCTION check_order_part_tariff();

CREATE FUNCTION protect_quantity_from_existing_movements() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE moved bigint;
        protected_part bigint;
BEGIN
    protected_part := NEW.part_id;
    PERFORM 1 FROM parts WHERE part_id = protected_part FOR UPDATE;
    IF TG_TABLE_NAME = 'supply_lines' THEN
        SELECT COALESCE(sum(quantity_delta), 0) INTO moved
        FROM inventory_movements WHERE supply_line_id = OLD.supply_line_id;
        IF NEW.quantity < moved THEN
            RAISE EXCEPTION 'Количество поставки не может быть меньше уже принятого на склад';
        END IF;
    ELSE
        SELECT COALESCE(sum(-quantity_delta), 0) INTO moved
        FROM inventory_movements WHERE order_part_id = OLD.order_part_id;
        IF NEW.quantity < moved THEN
            RAISE EXCEPTION 'Количество запчасти в заказе не может быть меньше уже списанного';
        END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_supply_line_quantity_protection
BEFORE UPDATE OF quantity ON supply_lines
FOR EACH ROW EXECUTE FUNCTION protect_quantity_from_existing_movements();
CREATE TRIGGER trg_order_part_quantity_protection
BEFORE UPDATE OF quantity ON order_parts
FOR EACH ROW EXECUTE FUNCTION protect_quantity_from_existing_movements();

CREATE TABLE inventory_movement_types (
    movement_type_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    source_kind     varchar(20) NOT NULL CHECK (source_kind IN ('supply', 'order', 'adjustment')),
    direction       smallint NOT NULL CHECK (direction IN (-1, 1))
);

-- Остаток запчасти рассчитывается как сумма quantity_delta по движениям.
CREATE TABLE inventory_movements (
    movement_id     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    movement_type_id bigint NOT NULL REFERENCES inventory_movement_types(movement_type_id) ON DELETE RESTRICT,
    supply_line_id  bigint,
    order_part_id   bigint,
    adjustment_part_id bigint REFERENCES parts(part_id) ON DELETE RESTRICT,
    quantity_delta  integer NOT NULL CHECK (quantity_delta <> 0),
    moved_at        timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (supply_line_id) REFERENCES supply_lines(supply_line_id) ON DELETE RESTRICT,
    FOREIGN KEY (order_part_id) REFERENCES order_parts(order_part_id) ON DELETE RESTRICT,
    CHECK (num_nonnulls(supply_line_id, order_part_id, adjustment_part_id) = 1)
);
CREATE INDEX ix_inventory_movements_adjustment_part ON inventory_movements(adjustment_part_id, moved_at);

CREATE FUNCTION check_inventory_movement() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE expected_kind varchar(20);
        expected_direction smallint;
        linked_quantity integer;
        existing_total bigint;
        stock_total bigint;
        movement_part bigint;
BEGIN
    SELECT source_kind, direction INTO expected_kind, expected_direction
    FROM inventory_movement_types WHERE movement_type_id = NEW.movement_type_id;
    IF expected_kind IS NULL THEN
        RAISE EXCEPTION 'Не найден тип движения запчасти';
    END IF;
    IF sign(NEW.quantity_delta)::smallint <> expected_direction THEN
        RAISE EXCEPTION 'Знак изменения количества не соответствует типу движения';
    END IF;
    IF (expected_kind = 'supply' AND (NEW.supply_line_id IS NULL OR NEW.order_part_id IS NOT NULL OR NEW.adjustment_part_id IS NOT NULL))
       OR (expected_kind = 'order' AND (NEW.order_part_id IS NULL OR NEW.supply_line_id IS NOT NULL OR NEW.adjustment_part_id IS NOT NULL))
       OR (expected_kind = 'adjustment' AND (NEW.supply_line_id IS NOT NULL OR NEW.order_part_id IS NOT NULL OR NEW.adjustment_part_id IS NULL)) THEN
        RAISE EXCEPTION 'Источник движения не соответствует его типу';
    END IF;
    IF NEW.supply_line_id IS NOT NULL THEN
        SELECT part_id, quantity INTO movement_part, linked_quantity
        FROM supply_lines WHERE supply_line_id = NEW.supply_line_id;
        SELECT COALESCE(sum(quantity_delta), 0) INTO existing_total
        FROM inventory_movements WHERE supply_line_id = NEW.supply_line_id AND movement_id <> COALESCE(NEW.movement_id, -1);
        IF NEW.quantity_delta < 0 OR existing_total + NEW.quantity_delta > linked_quantity THEN
            RAISE EXCEPTION 'Движения превышают количество в строке поставки';
        END IF;
    ELSIF NEW.order_part_id IS NOT NULL THEN
        SELECT part_id, quantity INTO movement_part, linked_quantity
        FROM order_parts WHERE order_part_id = NEW.order_part_id;
        SELECT COALESCE(sum(-quantity_delta), 0) INTO existing_total
        FROM inventory_movements WHERE order_part_id = NEW.order_part_id AND movement_id <> COALESCE(NEW.movement_id, -1);
        IF NEW.quantity_delta > 0 OR existing_total - NEW.quantity_delta > linked_quantity THEN
            RAISE EXCEPTION 'Списания превышают количество запчасти в заказе';
        END IF;
    ELSE
        movement_part := NEW.adjustment_part_id;
    END IF;
    -- Запчасть прихода/расхода выводится из строки-источника; для ручной
    -- корректировки указывается adjustment_part_id.
    PERFORM 1 FROM parts WHERE part_id = movement_part FOR UPDATE;
    SELECT COALESCE(sum(quantity_delta), 0) INTO stock_total
    FROM inventory_movements im
    LEFT JOIN supply_lines sl ON sl.supply_line_id = im.supply_line_id
    LEFT JOIN order_parts op ON op.order_part_id = im.order_part_id
    WHERE COALESCE(sl.part_id, op.part_id, im.adjustment_part_id) = movement_part
      AND im.movement_id <> COALESCE(NEW.movement_id, -1);
    IF stock_total + NEW.quantity_delta < 0 THEN
        RAISE EXCEPTION 'Остаток запчасти не может стать отрицательным';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_inventory_movement_valid
BEFORE INSERT OR UPDATE ON inventory_movements
FOR EACH ROW EXECUTE FUNCTION check_inventory_movement();

CREATE FUNCTION prevent_inventory_movement_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Журнал движений запчастей является append-only; исправление оформляется обратным движением';
END $$;
CREATE TRIGGER trg_inventory_movement_append_only
BEFORE UPDATE OR DELETE ON inventory_movements
FOR EACH ROW EXECUTE FUNCTION prevent_inventory_movement_rewrite();

CREATE FUNCTION prevent_movement_type_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (NEW.source_kind, NEW.direction) IS DISTINCT FROM (OLD.source_kind, OLD.direction)
       AND EXISTS (SELECT 1 FROM inventory_movements WHERE movement_type_id = OLD.movement_type_id) THEN
        RAISE EXCEPTION 'Нельзя менять правила типа движения, уже использованного в журнале';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_movement_type_semantics_immutable
BEFORE UPDATE OF source_kind, direction ON inventory_movement_types
FOR EACH ROW EXECUTE FUNCTION prevent_movement_type_rewrite();

CREATE TABLE benefit_categories (
    benefit_category_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(100) NOT NULL UNIQUE,
    description     text
);

CREATE TABLE client_benefits (
    client_benefit_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_id       bigint NOT NULL REFERENCES clients(client_id) ON DELETE RESTRICT,
    benefit_category_id bigint NOT NULL REFERENCES benefit_categories(benefit_category_id) ON DELETE RESTRICT,
    valid_from      date NOT NULL,
    valid_to        date,
    evidence_reference varchar(200),
    CHECK (valid_to IS NULL OR valid_to > valid_from),
    UNIQUE (client_benefit_id, client_id)
);

CREATE TABLE discount_calculation_types (
    discount_calculation_type_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    value_kind      varchar(20) NOT NULL CHECK (value_kind IN ('percentage', 'fixed'))
);

CREATE TABLE discount_types (
    discount_type_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(120) NOT NULL UNIQUE,
    calculation_type_id bigint NOT NULL REFERENCES discount_calculation_types(discount_calculation_type_id) ON DELETE RESTRICT,
    value           numeric(12,2) NOT NULL CHECK (value >= 0)
);

CREATE FUNCTION check_discount_type_value() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE kind varchar(20);
BEGIN
    SELECT value_kind INTO kind FROM discount_calculation_types
    WHERE discount_calculation_type_id = NEW.calculation_type_id;
    IF kind = 'percentage' AND NEW.value > 100 THEN
        RAISE EXCEPTION 'Процентная скидка не может превышать 100%%';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_discount_type_value
BEFORE INSERT OR UPDATE OF calculation_type_id, value ON discount_types
FOR EACH ROW EXECUTE FUNCTION check_discount_type_value();

CREATE FUNCTION protect_discount_definition_after_use() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (NEW.calculation_type_id IS DISTINCT FROM OLD.calculation_type_id
        OR NEW.value IS DISTINCT FROM OLD.value)
       AND EXISTS (SELECT 1 FROM applied_discounts WHERE discount_type_id = OLD.discount_type_id) THEN
        RAISE EXCEPTION 'Нельзя менять условия скидки после её применения к заказам';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_discount_definition_immutable_after_use
BEFORE UPDATE OF calculation_type_id, value ON discount_types
FOR EACH ROW EXECUTE FUNCTION protect_discount_definition_after_use();

CREATE FUNCTION protect_discount_calculation_kind_after_use() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.value_kind = 'percentage' AND EXISTS (
        SELECT 1 FROM discount_types
        WHERE calculation_type_id = OLD.discount_calculation_type_id AND value > 100
    ) THEN
        RAISE EXCEPTION 'Нельзя назначить процентный тип расчёта: у скидок есть значения больше 100%%';
    END IF;
    IF NEW.value_kind IS DISTINCT FROM OLD.value_kind
       AND EXISTS (
           SELECT 1 FROM discount_types dt
           JOIN applied_discounts ad ON ad.discount_type_id = dt.discount_type_id
           WHERE dt.calculation_type_id = OLD.discount_calculation_type_id
       ) THEN
        RAISE EXCEPTION 'Нельзя менять способ расчёта уже применённой скидки';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_discount_kind_immutable_after_use
BEFORE UPDATE OF value_kind ON discount_calculation_types
FOR EACH ROW EXECUTE FUNCTION protect_discount_calculation_kind_after_use();

CREATE TABLE applied_discounts (
    applied_discount_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id        bigint NOT NULL REFERENCES repair_orders(order_id) ON DELETE RESTRICT,
    discount_type_id bigint NOT NULL REFERENCES discount_types(discount_type_id) ON DELETE RESTRICT,
    client_benefit_id bigint REFERENCES client_benefits(client_benefit_id) ON DELETE RESTRICT,
    calculation_base numeric(12,2) NOT NULL DEFAULT 0 CHECK (calculation_base >= 0),
    discount_amount numeric(12,2) NOT NULL CHECK (discount_amount >= 0)
);

CREATE FUNCTION check_discount_total_limit() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_subtotal numeric(12,2);
        already_discounted numeric(12,2);
        discount_kind varchar(20);
        discount_value numeric(12,2);
        expected_discount numeric(12,2);
        rebase_discount boolean;
BEGIN
    PERFORM 1 FROM repair_orders WHERE order_id = NEW.order_id FOR UPDATE;
    IF EXISTS (SELECT 1 FROM repair_orders WHERE order_id = NEW.order_id AND issued_at IS NOT NULL) THEN
        RAISE EXCEPTION 'Нельзя применять скидку после выдачи заказа';
    END IF;
    IF EXISTS (
        SELECT 1 FROM payments p
        JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
        WHERE p.order_id = NEW.order_id AND ps.is_successful
    ) THEN
        RAISE EXCEPTION 'Нельзя менять скидки после успешной оплаты заказа';
    END IF;
    SELECT
        COALESCE((SELECT sum(wt.price)
                  FROM order_works ow
                  JOIN order_assignments a ON a.assignment_id = ow.assignment_id
                  JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
                  WHERE a.order_id = NEW.order_id), 0)
        + COALESCE((SELECT sum(op.quantity * pt.unit_price)
                    FROM order_parts op
                    JOIN part_tariffs pt ON pt.part_tariff_id = op.part_tariff_id
                    WHERE op.order_id = NEW.order_id), 0)
    INTO order_subtotal;
    rebase_discount := TG_OP = 'INSERT'
        OR NEW.order_id IS DISTINCT FROM OLD.order_id
        OR NEW.discount_type_id IS DISTINCT FROM OLD.discount_type_id;
    IF rebase_discount THEN
        NEW.calculation_base := order_subtotal;
    ELSIF NEW.calculation_base IS DISTINCT FROM OLD.calculation_base THEN
        RAISE EXCEPTION 'База расчёта скидки фиксируется при её применении';
    END IF;
    SELECT COALESCE(sum(discount_amount), 0) INTO already_discounted
    FROM applied_discounts
    WHERE order_id = NEW.order_id
      AND applied_discount_id <> COALESCE(NEW.applied_discount_id, -1);
    SELECT dct.value_kind, dt.value INTO discount_kind, discount_value
    FROM discount_types dt
    JOIN discount_calculation_types dct
      ON dct.discount_calculation_type_id = dt.calculation_type_id
    WHERE dt.discount_type_id = NEW.discount_type_id;
    IF discount_kind = 'percentage' THEN
        expected_discount := round(NEW.calculation_base * discount_value / 100, 2);
    ELSE
        expected_discount := LEAST(discount_value, NEW.calculation_base);
    END IF;
    IF NEW.discount_amount <> expected_discount THEN
        RAISE EXCEPTION 'Сумма скидки должна соответствовать её типу и значению';
    END IF;
    IF NEW.discount_amount + already_discounted > order_subtotal THEN
        RAISE EXCEPTION 'Суммарная скидка не может превышать стоимость работ и запчастей';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_discount_total_limit
BEFORE INSERT OR UPDATE ON applied_discounts
FOR EACH ROW EXECUTE FUNCTION check_discount_total_limit();

CREATE FUNCTION prevent_discount_rewrite_after_payment() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_order bigint;
BEGIN
    target_order := OLD.order_id;
    PERFORM 1 FROM repair_orders WHERE order_id = target_order FOR UPDATE;
    IF EXISTS (SELECT 1 FROM repair_orders WHERE order_id = target_order AND issued_at IS NOT NULL) THEN
        RAISE EXCEPTION 'Нельзя менять скидку после выдачи заказа';
    END IF;
    IF EXISTS (
        SELECT 1 FROM payments p
        JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
        WHERE p.order_id = target_order AND ps.is_successful
    ) THEN
        RAISE EXCEPTION 'Нельзя менять или удалять скидки после успешной оплаты заказа';
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_applied_discount_immutable_after_payment
BEFORE UPDATE OR DELETE ON applied_discounts
FOR EACH ROW EXECUTE FUNCTION prevent_discount_rewrite_after_payment();

CREATE FUNCTION prevent_order_total_change_after_discount() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE old_order_id bigint;
        new_order_id bigint;
BEGIN
    IF TG_TABLE_NAME = 'order_parts' THEN
        old_order_id := OLD.order_id;
        IF TG_OP <> 'DELETE' THEN new_order_id := NEW.order_id; END IF;
    ELSE
        SELECT order_id INTO old_order_id FROM order_assignments WHERE assignment_id = OLD.assignment_id;
        IF TG_OP <> 'DELETE' THEN
            SELECT order_id INTO new_order_id FROM order_assignments WHERE assignment_id = NEW.assignment_id;
        END IF;
    END IF;
    PERFORM 1 FROM repair_orders
    WHERE order_id IN (old_order_id, new_order_id)
    ORDER BY order_id FOR UPDATE;
    IF EXISTS (SELECT 1 FROM applied_discounts WHERE order_id = old_order_id)
       OR EXISTS (SELECT 1 FROM payments WHERE order_id = old_order_id)
       OR EXISTS (SELECT 1 FROM repair_orders WHERE order_id = old_order_id AND issued_at IS NOT NULL)
       OR (new_order_id IS NOT NULL AND EXISTS (
           SELECT 1 FROM applied_discounts WHERE order_id = new_order_id
       ))
       OR (new_order_id IS NOT NULL AND EXISTS (
           SELECT 1 FROM repair_orders WHERE order_id = new_order_id AND issued_at IS NOT NULL
       )) THEN
        RAISE EXCEPTION 'Нельзя менять состав или стоимость заказа после скидки или создания платежа';
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_parts_total_immutable_after_discount
BEFORE UPDATE OF order_id, quantity, part_tariff_id, price_applied_on OR DELETE ON order_parts
FOR EACH ROW EXECUTE FUNCTION prevent_order_total_change_after_discount();
CREATE TRIGGER trg_order_works_total_immutable_after_discount
BEFORE UPDATE OF assignment_id, tariff_id OR DELETE ON order_works
FOR EACH ROW EXECUTE FUNCTION prevent_order_total_change_after_discount();

CREATE FUNCTION prevent_cost_lines_after_order_issue() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_order bigint;
BEGIN
    IF TG_TABLE_NAME = 'order_parts' THEN
        target_order := NEW.order_id;
    ELSE
        SELECT order_id INTO target_order FROM order_assignments WHERE assignment_id = NEW.assignment_id;
    END IF;
    PERFORM 1 FROM repair_orders WHERE order_id = target_order FOR UPDATE;
    IF EXISTS (SELECT 1 FROM repair_orders WHERE order_id = target_order AND issued_at IS NOT NULL)
       OR EXISTS (SELECT 1 FROM applied_discounts WHERE order_id = target_order)
       OR EXISTS (SELECT 1 FROM payments WHERE order_id = target_order) THEN
        RAISE EXCEPTION 'Нельзя менять стоимость заказа после скидки, платежа или выдачи';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_parts_no_insert_after_issue
BEFORE INSERT ON order_parts
FOR EACH ROW EXECUTE FUNCTION prevent_cost_lines_after_order_issue();
CREATE TRIGGER trg_order_works_no_insert_after_issue
BEFORE INSERT ON order_works
FOR EACH ROW EXECUTE FUNCTION prevent_cost_lines_after_order_issue();

CREATE FUNCTION check_applied_discount_benefit() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_client bigint;
        benefit_client bigint;
        benefit_from date;
        benefit_to date;
        order_date date;
BEGIN
    IF NEW.client_benefit_id IS NULL THEN RETURN NEW; END IF;
    SELECT client_id, created_at::date INTO order_client, order_date
    FROM repair_orders WHERE order_id = NEW.order_id;
    SELECT client_id, valid_from, valid_to INTO benefit_client, benefit_from, benefit_to
    FROM client_benefits WHERE client_benefit_id = NEW.client_benefit_id;
    IF order_client IS DISTINCT FROM benefit_client THEN
        RAISE EXCEPTION 'Льгота должна принадлежать клиенту заказа';
    END IF;
    IF order_date < benefit_from OR (benefit_to IS NOT NULL AND order_date >= benefit_to) THEN
        RAISE EXCEPTION 'Льгота не действовала на дату создания заказа';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_applied_discount_benefit
BEFORE INSERT OR UPDATE OF order_id, client_benefit_id ON applied_discounts
FOR EACH ROW EXECUTE FUNCTION check_applied_discount_benefit();

CREATE FUNCTION protect_client_benefit_from_applied_discounts() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM applied_discounts ad
        JOIN repair_orders o ON o.order_id = ad.order_id
        WHERE ad.client_benefit_id = OLD.client_benefit_id
          AND (o.client_id <> NEW.client_id
               OR o.created_at::date < NEW.valid_from
               OR (NEW.valid_to IS NOT NULL AND o.created_at::date >= NEW.valid_to))
    ) THEN
        RAISE EXCEPTION 'Нельзя менять владельца или период льготы, уже применённой к заказам';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_client_benefit_protect_discounts
BEFORE UPDATE OF client_id, valid_from, valid_to ON client_benefits
FOR EACH ROW EXECUTE FUNCTION protect_client_benefit_from_applied_discounts();

CREATE TABLE vouchers (
    voucher_id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code            varchar(100) NOT NULL UNIQUE,
    client_id       bigint NOT NULL REFERENCES clients(client_id) ON DELETE RESTRICT,
    face_value      numeric(12,2) NOT NULL CHECK (face_value > 0),
    expires_on      date,
    issued_at       timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE payment_methods (
    payment_method_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    is_voucher      boolean NOT NULL DEFAULT false
);
CREATE UNIQUE INDEX ux_payment_methods_single_voucher ON payment_methods(is_voucher) WHERE is_voucher;

CREATE TABLE payment_statuses (
    payment_status_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE,
    is_successful   boolean NOT NULL DEFAULT false
);

CREATE TABLE payments (
    payment_id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id        bigint NOT NULL REFERENCES repair_orders(order_id) ON DELETE RESTRICT,
    payment_method_id bigint NOT NULL REFERENCES payment_methods(payment_method_id) ON DELETE RESTRICT,
    payment_status_id bigint NOT NULL REFERENCES payment_statuses(payment_status_id) ON DELETE RESTRICT,
    paid_at         timestamptz,
    amount          numeric(12,2) NOT NULL CHECK (amount > 0),
    external_reference varchar(160)
);

-- Возврат не переписывает исходный платёж: он фиксируется отдельной
-- неизменяемой записью и уменьшает фактически полученную сумму.
CREATE TABLE payment_refunds (
    refund_id       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payment_id      bigint NOT NULL REFERENCES payments(payment_id) ON DELETE RESTRICT,
    refunded_at     timestamptz NOT NULL DEFAULT now(),
    amount          numeric(12,2) NOT NULL CHECK (amount > 0),
    reason          text NOT NULL,
    external_reference varchar(160)
);
CREATE INDEX ix_payment_refunds_payment ON payment_refunds(payment_id, refunded_at);

CREATE FUNCTION validate_payment_refund() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE original_amount numeric(12,2);
        payment_successful boolean;
        already_refunded numeric(12,2);
        payment_order bigint;
BEGIN
    SELECT order_id INTO payment_order
    FROM payments WHERE payment_id = NEW.payment_id;
    PERFORM 1 FROM repair_orders WHERE order_id = payment_order FOR UPDATE;
    SELECT p.amount, ps.is_successful INTO original_amount, payment_successful
    FROM payments p
    JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
    WHERE p.payment_id = NEW.payment_id
    FOR UPDATE OF p;
    IF payment_successful IS DISTINCT FROM true THEN
        RAISE EXCEPTION 'Возвращать можно только подтверждённый платёж';
    END IF;
    SELECT COALESCE(sum(amount), 0) INTO already_refunded
    FROM payment_refunds WHERE payment_id = NEW.payment_id;
    IF already_refunded + NEW.amount > original_amount THEN
        RAISE EXCEPTION 'Сумма возвратов превышает исходный платёж';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_refund_valid
BEFORE INSERT ON payment_refunds
FOR EACH ROW EXECUTE FUNCTION validate_payment_refund();

CREATE FUNCTION prevent_payment_refund_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Возвраты являются неизменяемым журналом; исправление оформляется новой компенсирующей записью';
END $$;
CREATE TRIGGER trg_payment_refund_append_only
BEFORE UPDATE OR DELETE ON payment_refunds
FOR EACH ROW EXECUTE FUNCTION prevent_payment_refund_rewrite();

-- paid_at устанавливается приложением только после подтверждения успешной оплаты.
CREATE FUNCTION check_payment_success_timestamp() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE successful boolean;
BEGIN
    SELECT is_successful INTO successful FROM payment_statuses
    WHERE payment_status_id = NEW.payment_status_id;
    IF successful IS DISTINCT FROM (NEW.paid_at IS NOT NULL) THEN
        RAISE EXCEPTION 'Дата оплаты должна быть заполнена только для успешного платежа';
    END IF;
    IF NEW.paid_at IS NULL AND (
        EXISTS (SELECT 1 FROM receipts WHERE payment_id = NEW.payment_id)
        OR EXISTS (SELECT 1 FROM voucher_transactions WHERE payment_id = NEW.payment_id)
    ) THEN
        RAISE EXCEPTION 'Нельзя отменить успешность платежа, уже использованного для чека или ваучера';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_success_timestamp
BEFORE INSERT OR UPDATE OF payment_status_id, paid_at ON payments
FOR EACH ROW EXECUTE FUNCTION check_payment_success_timestamp();

CREATE FUNCTION protect_payment_after_voucher_operation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM voucher_transactions WHERE payment_id = OLD.payment_id)
       AND (NEW.order_id IS DISTINCT FROM OLD.order_id
            OR NEW.payment_method_id IS DISTINCT FROM OLD.payment_method_id
            OR NEW.amount IS DISTINCT FROM OLD.amount) THEN
        RAISE EXCEPTION 'Нельзя изменять сумму, заказ или способ оплаты после операции по ваучеру';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_protect_voucher_operation
BEFORE UPDATE OF order_id, payment_method_id, amount ON payments
FOR EACH ROW EXECUTE FUNCTION protect_payment_after_voucher_operation();

CREATE FUNCTION protect_payment_status_semantics() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.is_successful <> OLD.is_successful
       AND EXISTS (SELECT 1 FROM payments WHERE payment_status_id = OLD.payment_status_id) THEN
        RAISE EXCEPTION 'Нельзя менять смысл статуса, уже используемого платежами';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_status_semantics_immutable
BEFORE UPDATE OF is_successful ON payment_statuses
FOR EACH ROW EXECUTE FUNCTION protect_payment_status_semantics();

CREATE FUNCTION protect_voucher_payment_method_semantics() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.is_voucher IS DISTINCT FROM OLD.is_voucher
       AND EXISTS (SELECT 1 FROM payments WHERE payment_method_id = OLD.payment_method_id) THEN
        RAISE EXCEPTION 'Нельзя менять смысл способа оплаты, уже использованного платежами';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_voucher_payment_method_semantics_immutable
BEFORE UPDATE OF is_voucher ON payment_methods
FOR EACH ROW EXECUTE FUNCTION protect_voucher_payment_method_semantics();

-- Пополнение ваучера: положительная сумма; списание в оплату: отрицательная.
CREATE TABLE voucher_transactions (
    voucher_transaction_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    voucher_id      bigint NOT NULL REFERENCES vouchers(voucher_id) ON DELETE RESTRICT,
    payment_id      bigint REFERENCES payments(payment_id) ON DELETE RESTRICT,
    occurred_at     timestamptz NOT NULL DEFAULT now(),
    amount_change   numeric(12,2) NOT NULL CHECK (amount_change <> 0),
    description     varchar(200) NOT NULL,
    CHECK (amount_change > 0 OR payment_id IS NOT NULL),
    UNIQUE (payment_id)
);
CREATE INDEX ix_voucher_transactions_voucher ON voucher_transactions(voucher_id, occurred_at);

CREATE FUNCTION check_voucher_transaction() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE voucher_client bigint;
        payment_client bigint;
        payment_amount numeric(12,2);
        payment_paid_at timestamptz;
        payment_method_voucher boolean;
        balance_after numeric(12,2);
        initial_value numeric(12,2);
BEGIN
    -- Блокируем ваучер, чтобы параллельные списания не обошли проверку остатка.
    SELECT client_id, face_value INTO voucher_client, initial_value
    FROM vouchers WHERE voucher_id = NEW.voucher_id FOR UPDATE;
    IF NEW.payment_id IS NOT NULL THEN
        SELECT o.client_id, p.amount, p.paid_at, pm.is_voucher
        INTO payment_client, payment_amount, payment_paid_at, payment_method_voucher
        FROM payments p
        JOIN payment_methods pm ON pm.payment_method_id = p.payment_method_id
        JOIN repair_orders o ON o.order_id = p.order_id
        WHERE p.payment_id = NEW.payment_id;
        IF payment_client IS DISTINCT FROM voucher_client THEN
            RAISE EXCEPTION 'Ваучер и связанный с операцией платёж должны принадлежать одному клиенту';
        END IF;
        IF payment_paid_at IS NULL OR payment_amount <> abs(NEW.amount_change) THEN
            RAISE EXCEPTION 'Операция ваучера должна соответствовать подтверждённой оплате';
        END IF;
    END IF;
    IF NEW.amount_change < 0 THEN
        IF EXISTS (
            SELECT 1 FROM vouchers v
            WHERE v.voucher_id = NEW.voucher_id
              AND v.expires_on IS NOT NULL AND v.expires_on < NEW.occurred_at::date
        ) THEN
            RAISE EXCEPTION 'Срок действия ваучера истёк';
        END IF;
        IF payment_method_voucher IS DISTINCT FROM true THEN
            RAISE EXCEPTION 'Списание ваучера должно быть связано с оплатой ваучером';
        END IF;
    END IF;
    SELECT initial_value + COALESCE(sum(amount_change), 0) INTO balance_after
    FROM voucher_transactions
    WHERE voucher_id = NEW.voucher_id
      AND voucher_transaction_id <> COALESCE(NEW.voucher_transaction_id, -1);
    balance_after := balance_after + NEW.amount_change;
    IF balance_after < 0 THEN
        RAISE EXCEPTION 'Недостаточно средств на ваучере';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_voucher_transaction_valid
BEFORE INSERT OR UPDATE ON voucher_transactions
FOR EACH ROW EXECUTE FUNCTION check_voucher_transaction();

CREATE FUNCTION assert_voucher_payment_debit() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_payment bigint;
        voucher_method boolean;
        payment_successful boolean;
        has_debit boolean;
BEGIN
    target_payment := NEW.payment_id;
    IF target_payment IS NULL THEN RETURN NULL; END IF;
    SELECT pm.is_voucher, ps.is_successful
    INTO voucher_method, payment_successful
    FROM payments p
    JOIN payment_methods pm ON pm.payment_method_id = p.payment_method_id
    JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
    WHERE p.payment_id = target_payment;
    SELECT EXISTS (
        SELECT 1 FROM voucher_transactions
        WHERE payment_id = target_payment AND amount_change < 0
    ) INTO has_debit;
    IF voucher_method AND payment_successful AND NOT has_debit THEN
        RAISE EXCEPTION 'Успешный платёж ваучером должен иметь запись о списании';
    END IF;
    IF has_debit AND (voucher_method IS DISTINCT FROM true OR payment_successful IS DISTINCT FROM true) THEN
        RAISE EXCEPTION 'Списание ваучера должно быть связано с успешным платежом ваучером';
    END IF;
    RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER ctr_payment_requires_voucher_debit
AFTER INSERT OR UPDATE OF payment_method_id, payment_status_id, paid_at ON payments
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
EXECUTE FUNCTION assert_voucher_payment_debit();
CREATE CONSTRAINT TRIGGER ctr_voucher_debit_matches_payment
AFTER INSERT ON voucher_transactions
DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
EXECUTE FUNCTION assert_voucher_payment_debit();

CREATE FUNCTION enforce_order_payment_total() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_subtotal numeric(12,2);
        discount_total numeric(12,2);
        successful_payments numeric(12,2);
        new_payment_successful boolean;
BEGIN
    PERFORM 1 FROM repair_orders WHERE order_id = NEW.order_id FOR UPDATE;
    SELECT
        COALESCE((SELECT sum(wt.price)
                  FROM order_works ow
                  JOIN order_assignments a ON a.assignment_id = ow.assignment_id
                  JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
                  WHERE a.order_id = NEW.order_id), 0)
        + COALESCE((SELECT sum(op.quantity * pt.unit_price)
                    FROM order_parts op
                    JOIN part_tariffs pt ON pt.part_tariff_id = op.part_tariff_id
                    WHERE op.order_id = NEW.order_id), 0)
    INTO order_subtotal;
    SELECT COALESCE(sum(ad.discount_amount), 0) INTO discount_total
    FROM applied_discounts ad WHERE ad.order_id = NEW.order_id;
    SELECT COALESCE(sum(p.amount), 0) INTO successful_payments
    FROM payments p
    JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
    WHERE p.order_id = NEW.order_id
      AND p.payment_id <> COALESCE(NEW.payment_id, -1)
      AND ps.is_successful;
    successful_payments := successful_payments - COALESCE((
        SELECT sum(r.amount)
        FROM payment_refunds r
        JOIN payments p ON p.payment_id = r.payment_id
        WHERE p.order_id = NEW.order_id
    ), 0);
    SELECT is_successful INTO new_payment_successful
    FROM payment_statuses WHERE payment_status_id = NEW.payment_status_id;
    IF new_payment_successful THEN
        successful_payments := successful_payments + NEW.amount;
    END IF;
    IF successful_payments > order_subtotal - discount_total THEN
        RAISE EXCEPTION 'Успешные платежи превышают остаток к оплате по заказу';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_amount_within_order_total
BEFORE INSERT OR UPDATE OF order_id, amount, payment_status_id ON payments
FOR EACH ROW EXECUTE FUNCTION enforce_order_payment_total();

CREATE FUNCTION prevent_voucher_history_rewrite() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'История операций ваучера является append-only';
END $$;
CREATE TRIGGER trg_voucher_transaction_append_only
BEFORE UPDATE OR DELETE ON voucher_transactions
FOR EACH ROW EXECUTE FUNCTION prevent_voucher_history_rewrite();

CREATE FUNCTION protect_voucher_from_history_changes() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM voucher_transactions WHERE voucher_id = OLD.voucher_id)
       AND (NEW.client_id IS DISTINCT FROM OLD.client_id
            OR NEW.face_value IS DISTINCT FROM OLD.face_value
            OR (NEW.expires_on IS NOT NULL AND EXISTS (
                SELECT 1 FROM voucher_transactions
                WHERE voucher_id = OLD.voucher_id AND occurred_at::date > NEW.expires_on
            ))) THEN
        RAISE EXCEPTION 'Изменение владельца, номинала или срока ваучера противоречит истории операций';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_voucher_protect_history
BEFORE UPDATE OF client_id, face_value, expires_on ON vouchers
FOR EACH ROW EXECUTE FUNCTION protect_voucher_from_history_changes();

CREATE TABLE receipt_delivery_statuses (
    receipt_delivery_status_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name            varchar(60) NOT NULL UNIQUE
);

CREATE TABLE receipts (
    receipt_id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payment_id      bigint NOT NULL UNIQUE REFERENCES payments(payment_id) ON DELETE RESTRICT,
    receipt_number  varchar(120) NOT NULL UNIQUE,
    issued_at       timestamptz NOT NULL DEFAULT now(),
    email_sent_to   varchar(254),
    delivery_status_id bigint NOT NULL REFERENCES receipt_delivery_statuses(receipt_delivery_status_id) ON DELETE RESTRICT
);

CREATE FUNCTION check_receipt_payment_success() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE payment_paid_at timestamptz;
BEGIN
    SELECT paid_at INTO payment_paid_at FROM payments WHERE payment_id = NEW.payment_id;
    IF payment_paid_at IS NULL THEN
        RAISE EXCEPTION 'Чек можно сформировать только для успешного платежа';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_receipt_successful_payment
BEFORE INSERT OR UPDATE OF payment_id ON receipts
FOR EACH ROW EXECUTE FUNCTION check_receipt_payment_success();

-- Заказ считается полностью оплаченным, когда успешные платежи за вычетом
-- возвратов покрывают работы и запчасти за вычетом закреплённых скидок.
CREATE FUNCTION order_is_fully_paid(target_order_id bigint) RETURNS boolean
LANGUAGE sql STABLE AS $$
    SELECT
      COALESCE((SELECT sum(wt.price)
                FROM order_works ow
                JOIN order_assignments a ON a.assignment_id = ow.assignment_id
                JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
                WHERE a.order_id = target_order_id), 0)
        + COALESCE((SELECT sum(op.quantity * pt.unit_price)
                    FROM order_parts op
                    JOIN part_tariffs pt ON pt.part_tariff_id = op.part_tariff_id
                    WHERE op.order_id = target_order_id), 0)
      - COALESCE((SELECT sum(ad.discount_amount)
                  FROM applied_discounts ad WHERE ad.order_id = target_order_id), 0)
      <= COALESCE((SELECT sum(p.amount)
                   FROM payments p
                   JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
                   WHERE p.order_id = target_order_id AND ps.is_successful), 0)
         - COALESCE((SELECT sum(r.amount)
                     FROM payment_refunds r
                     JOIN payments p ON p.payment_id = r.payment_id
                     WHERE p.order_id = target_order_id), 0)
$$;

CREATE FUNCTION enforce_paid_order_issuance() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target_is_issued boolean;
        previous_issued_at timestamptz;
BEGIN
    IF TG_OP = 'UPDATE' THEN previous_issued_at := OLD.issued_at; END IF;
    SELECT is_issued INTO target_is_issued
    FROM order_statuses WHERE order_status_id = NEW.order_status_id;
    IF target_is_issued AND NOT order_is_fully_paid(NEW.order_id) THEN
        RAISE EXCEPTION 'Нельзя выдать заказ: он должен быть полностью оплачен';
    END IF;
    IF target_is_issued AND NEW.issued_at IS NULL THEN
        NEW.issued_at := now();
    END IF;
    IF NEW.issued_at IS DISTINCT FROM previous_issued_at THEN
        IF previous_issued_at IS NOT NULL THEN
            RAISE EXCEPTION 'Дата выдачи после установки не изменяется';
        END IF;
        IF NOT target_is_issued OR NOT order_is_fully_paid(NEW.order_id) THEN
            RAISE EXCEPTION 'Дату выдачи можно установить только при статусе выдачи и полной оплате';
        END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_repair_order_issue_requires_full_payment
BEFORE INSERT OR UPDATE OF order_status_id, issued_at ON repair_orders
FOR EACH ROW EXECUTE FUNCTION enforce_paid_order_issuance();

CREATE FUNCTION protect_issued_status_meaning() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.is_issued IS DISTINCT FROM OLD.is_issued
       AND EXISTS (SELECT 1 FROM repair_orders WHERE order_status_id = OLD.order_status_id) THEN
        RAISE EXCEPTION 'Признак статуса выдачи нельзя менять после его использования заказами';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_order_status_issue_semantics_immutable
BEFORE UPDATE OF is_issued ON order_statuses
FOR EACH ROW EXECUTE FUNCTION protect_issued_status_meaning();

CREATE FUNCTION protect_settled_payments() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE old_successful boolean;
        new_target_order bigint;
BEGIN
    SELECT ps.is_successful INTO old_successful
    FROM payment_statuses ps WHERE ps.payment_status_id = OLD.payment_status_id;
    IF TG_OP = 'DELETE' THEN
        IF old_successful OR EXISTS (SELECT 1 FROM repair_orders WHERE order_id = OLD.order_id AND issued_at IS NOT NULL) THEN
            RAISE EXCEPTION 'Подтверждённый платёж нельзя удалить; возвраты требуют отдельной записи в журнале возвратов';
        END IF;
        RETURN OLD;
    END IF;
    new_target_order := NEW.order_id;
    IF old_successful
       OR EXISTS (SELECT 1 FROM repair_orders WHERE order_id IN (OLD.order_id, new_target_order) AND issued_at IS NOT NULL) THEN
        RAISE EXCEPTION 'Подтверждённый платёж нельзя переписывать; возвраты требуют отдельной записи в журнале возвратов';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_settled_immutable
BEFORE UPDATE OR DELETE ON payments
FOR EACH ROW EXECUTE FUNCTION protect_settled_payments();

CREATE FUNCTION prevent_payment_after_order_issue() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM repair_orders WHERE order_id = NEW.order_id AND issued_at IS NOT NULL) THEN
        RAISE EXCEPTION 'Нельзя создавать платежи по уже выданному заказу';
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER trg_payment_no_insert_after_issue
BEFORE INSERT ON payments
FOR EACH ROW EXECUTE FUNCTION prevent_payment_after_order_issue();

-- Индексы на внешние ключи, которые не покрыты ведущими столбцами PK/UNIQUE.
CREATE INDEX ix_device_models_type ON device_models(device_type_id);
CREATE INDEX ix_devices_model ON devices(model_id);
CREATE INDEX ix_employee_positions_position ON employee_positions(position_id);
CREATE INDEX ix_master_specializations_specialization ON master_specializations(specialization_id);
CREATE INDEX ix_master_specializations_skill_level ON master_specializations(skill_level_id);
CREATE INDEX ix_work_type_specializations_specialization ON work_type_specializations(specialization_id);
CREATE INDEX ix_user_accounts_role ON user_accounts(role_id);
CREATE INDEX ix_repair_orders_client ON repair_orders(client_id);
CREATE INDEX ix_repair_orders_device ON repair_orders(device_id);
CREATE INDEX ix_order_status_history_status ON order_status_history(order_status_id);
CREATE INDEX ix_order_status_history_changed_by ON order_status_history(changed_by_employee_id);
CREATE INDEX ix_order_assignments_order ON order_assignments(order_id);
CREATE INDEX ix_order_works_tariff ON order_works(tariff_id);
CREATE INDEX ix_warranty_claims_original_work ON warranty_claims(original_order_work_id);
CREATE INDEX ix_warranty_claims_repair_order ON warranty_claims(repair_order_id);
CREATE INDEX ix_warranty_claims_status ON warranty_claims(warranty_status_id);
CREATE INDEX ix_supplies_supplier ON supplies(supplier_id);
CREATE INDEX ix_supply_lines_supply ON supply_lines(supply_id);
CREATE INDEX ix_supply_lines_part ON supply_lines(part_id);
CREATE INDEX ix_part_tariffs_part ON part_tariffs(part_id, valid_from DESC);
CREATE INDEX ix_order_parts_order ON order_parts(order_id);
CREATE INDEX ix_order_parts_part ON order_parts(part_id);
CREATE INDEX ix_order_parts_tariff ON order_parts(part_tariff_id);
CREATE INDEX ix_inventory_movements_type ON inventory_movements(movement_type_id);
CREATE INDEX ix_inventory_movements_supply_line ON inventory_movements(supply_line_id);
CREATE INDEX ix_inventory_movements_order_part ON inventory_movements(order_part_id);
CREATE INDEX ix_client_benefits_client ON client_benefits(client_id);
CREATE INDEX ix_client_benefits_category ON client_benefits(benefit_category_id);
CREATE INDEX ix_discount_types_calculation_type ON discount_types(calculation_type_id);
CREATE INDEX ix_applied_discounts_order ON applied_discounts(order_id);
CREATE INDEX ix_applied_discounts_type ON applied_discounts(discount_type_id);
CREATE INDEX ix_applied_discounts_benefit ON applied_discounts(client_benefit_id);
CREATE INDEX ix_vouchers_client ON vouchers(client_id);
CREATE INDEX ix_payments_order ON payments(order_id);
CREATE INDEX ix_payments_method ON payments(payment_method_id);
CREATE INDEX ix_payments_status ON payments(payment_status_id);
CREATE INDEX ix_receipts_delivery_status ON receipts(delivery_status_id);

COMMIT;






-- проверки вставки и изменения записей

CREATE OR REPLACE FUNCTION check_assignment_dates_and_employment() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_created timestamptz;
        employee_hired date;
        employee_dismissed date;
        check_at timestamptz;
BEGIN
    SELECT o.created_at, e.hired_on, e.dismissed_on
    INTO order_created, employee_hired, employee_dismissed
    FROM repair_orders o
    JOIN employees e ON e.employee_id = NEW.employee_id
    WHERE o.order_id = NEW.order_id;
    IF order_created IS NULL OR NEW.assigned_at < order_created THEN
        RAISE EXCEPTION 'Мастера нельзя назначить раньше создания заказа';
    END IF;
    FOREACH check_at IN ARRAY ARRAY[NEW.assigned_at, NEW.work_started_at, NEW.work_completed_at]
    LOOP
        IF check_at IS NOT NULL AND (
            check_at::date < employee_hired
            OR (employee_dismissed IS NOT NULL AND check_at::date > employee_dismissed)
        ) THEN
            RAISE EXCEPTION 'Назначение и работа должны попадать в период работы сотрудника';
        END IF;
    END LOOP;
    IF TG_OP = 'UPDATE' THEN
        IF EXISTS (
            SELECT 1 FROM order_works ow
            WHERE ow.assignment_id = OLD.assignment_id
              AND ow.performed_at IS NOT NULL
              AND (NEW.work_started_at IS NULL OR ow.performed_at < NEW.work_started_at
                   OR (NEW.work_completed_at IS NOT NULL AND ow.performed_at > NEW.work_completed_at))
        ) THEN
            RAISE EXCEPTION 'Изменение дат назначения конфликтует с уже выполненными работами';
        END IF;
    END IF;
    RETURN NEW;
END $$;

CREATE OR REPLACE FUNCTION check_discount_total_limit() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE order_subtotal numeric(12,2);
        already_discounted numeric(12,2);
        discount_kind varchar(20);
        discount_value numeric(12,2);
        expected_discount numeric(12,2);
        rebase_discount boolean;
BEGIN
    PERFORM 1 FROM repair_orders WHERE order_id = NEW.order_id FOR UPDATE;
    IF EXISTS (SELECT 1 FROM repair_orders WHERE order_id = NEW.order_id AND issued_at IS NOT NULL) THEN
        RAISE EXCEPTION 'Нельзя применять скидку после выдачи заказа';
    END IF;
    IF EXISTS (
        SELECT 1 FROM payments p
        JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
        WHERE p.order_id = NEW.order_id AND ps.is_successful
    ) THEN
        RAISE EXCEPTION 'Нельзя менять скидки после успешной оплаты заказа';
    END IF;
    SELECT
        COALESCE((SELECT sum(wt.price)
                  FROM order_works ow
                  JOIN order_assignments a ON a.assignment_id = ow.assignment_id
                  JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
                  WHERE a.order_id = NEW.order_id), 0)
        + COALESCE((SELECT sum(op.quantity * pt.unit_price)
                    FROM order_parts op
                    JOIN part_tariffs pt ON pt.part_tariff_id = op.part_tariff_id
                    WHERE op.order_id = NEW.order_id), 0)
    INTO order_subtotal;
    IF TG_OP = 'INSERT' THEN
        rebase_discount := true;
    ELSE
        rebase_discount := NEW.order_id IS DISTINCT FROM OLD.order_id
            OR NEW.discount_type_id IS DISTINCT FROM OLD.discount_type_id;
    END IF;
    IF rebase_discount THEN
        NEW.calculation_base := order_subtotal;
    ELSIF NEW.calculation_base IS DISTINCT FROM OLD.calculation_base THEN
        RAISE EXCEPTION 'База расчёта скидки фиксируется при её применении';
    END IF;
    SELECT COALESCE(sum(discount_amount), 0) INTO already_discounted
    FROM applied_discounts
    WHERE order_id = NEW.order_id
      AND applied_discount_id <> COALESCE(NEW.applied_discount_id, -1);
    SELECT dct.value_kind, dt.value INTO discount_kind, discount_value
    FROM discount_types dt
    JOIN discount_calculation_types dct
      ON dct.discount_calculation_type_id = dt.calculation_type_id
    WHERE dt.discount_type_id = NEW.discount_type_id;
    IF discount_kind = 'percentage' THEN
        expected_discount := round(NEW.calculation_base * discount_value / 100, 2);
    ELSE
        expected_discount := LEAST(discount_value, NEW.calculation_base);
    END IF;
    IF NEW.discount_amount <> expected_discount THEN
        RAISE EXCEPTION 'Сумма скидки должна соответствовать её типу и значению';
    END IF;
    IF NEW.discount_amount + already_discounted > order_subtotal THEN
        RAISE EXCEPTION 'Суммарная скидка не может превышать стоимость работ и запчастей';
    END IF;
    RETURN NEW;
END $$;
