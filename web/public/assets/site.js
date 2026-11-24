// мобильное меню
const menuButton = document.querySelector('.menu-toggle');
const menu = document.querySelector('.site-nav');
if (menuButton && menu) {
    menuButton.addEventListener('click', () => {
        const open = menu.classList.toggle('opened');
        menuButton.classList.toggle('opened', open);
        menuButton.setAttribute('aria-expanded', String(open));
    });
}

// показ пароля
const passwordButton = document.querySelector('.password-toggle');
const passwordInput = document.querySelector('#password');
if (passwordButton && passwordInput) {
    passwordButton.addEventListener('click', () => {
        const visible = passwordInput.type === 'password';
        passwordInput.type = visible ? 'text' : 'password';
        passwordButton.textContent = visible ? 'Скрыть' : 'Показать';
        passwordButton.setAttribute('aria-pressed', String(visible));
    });
}

// показываем подходящие услуги для выбранного устройства
const deviceType = document.querySelector('#type');
const serviceSelect = document.querySelector('#service_id');
if (deviceType && serviceSelect) {
    const showServices = () => {
        for (const option of serviceSelect.options) {
            if (!option.value) continue;
            const visible = option.dataset.types.split(',').includes(deviceType.value);
            option.hidden = !visible;
            option.disabled = !visible;
        }
        if (serviceSelect.selectedOptions[0]?.hidden) serviceSelect.value = '';
    };
    deviceType.addEventListener('change', showServices);
    showServices();
}


// выбор мастера для указанного типа техники
const masterChoices = document.querySelector('#master_choices');
const otherTypeField = document.querySelector('#other_type_field');
const otherTypeInput = document.querySelector('#type_other');
const requestSummary = document.querySelector('#request_summary');
const brandInput = document.querySelector('#brand');
const modelInput = document.querySelector('#model');

if (deviceType && masterChoices) {
    const updateRequest = () => {
        const examples = {
            'Смартфон': ['Samsung', 'Galaxy S23'],
            'Ноутбук': ['ASUS', 'VivoBook 15'],
            'Принтер': ['HP', 'LaserJet Pro'],
            'Монитор': ['LG', 'UltraGear'],
            'Игровая приставка': ['Sony', 'PlayStation 5'],
            'Телевизор': ['Samsung', 'UE43'],
        };
        const example = examples[deviceType.value] || ['Например, Xiaomi', 'Укажите модель'];
        brandInput.placeholder = example[0];
        modelInput.placeholder = example[1];
        if (otherTypeField && otherTypeInput) {
            const isOther = deviceType.value === 'Другая техника';
            otherTypeField.hidden = !isOther;
            otherTypeInput.required = isOther;
        }

        for (const card of masterChoices.querySelectorAll('[data-types]')) {
            const allowed = card.dataset.types.split(',');
            const pair = `${deviceType.value}:${serviceSelect?.value || ''}`;
            const pairs = card.dataset.pairs.split(';');
            const visible = allowed.includes(deviceType.value)
                && (!serviceSelect?.value || pairs.includes(pair));
            card.hidden = !visible;
            const radio = card.querySelector('input[type="radio"]');
            radio.disabled = !visible;
            if (!visible) radio.checked = false;
        }
        if (!masterChoices.querySelector('input:checked')) {
            masterChoices.querySelector('input[value="0"]').checked = true;
        }
        if (requestSummary && serviceSelect) {
            const typeName = deviceType.value || 'Вид техники не выбран';
            const name = [brandInput.value.trim(), modelInput.value.trim()].filter(Boolean).join(' ');
            const service = serviceSelect.selectedOptions[0]?.value
                ? serviceSelect.selectedOptions[0].textContent.trim()
                : 'Услуга не выбрана';
            const selectedMaster = masterChoices.querySelector('input:checked');
            const masterText = selectedMaster?.value !== '0'
                ? selectedMaster.closest('label').querySelector('strong').textContent.trim()
                : 'мастер пока не выбран';
            requestSummary.textContent = `${typeName}${name ? ': ' + name : ''}. ${service}. ${masterText}`;
        }
    };
    [deviceType, serviceSelect, brandInput, modelInput, otherTypeInput].forEach((field) => {
        if (!field) return;
        field.addEventListener('input', updateRequest);
        field.addEventListener('change', updateRequest);
    });
    masterChoices.addEventListener('change', updateRequest);
    updateRequest();
}

// проверяем повтор пароля перед регистрацией
const passwordRepeat = document.querySelector('#password_repeat');
if (passwordInput && passwordRepeat) {
    const checkPasswords = () => {
        passwordRepeat.setCustomValidity(
            passwordRepeat.value && passwordInput.value !== passwordRepeat.value
                ? 'Пароли не совпадают' : ''
        );
    };
    passwordInput.addEventListener('input', checkPasswords);
    passwordRepeat.addEventListener('input', checkPasswords);
}
