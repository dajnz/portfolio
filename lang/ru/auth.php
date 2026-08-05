<?php

declare(strict_types=1);

/*
| Сообщения бэкенда аутентификации (шаг 2.3) — статические строки,
| показываемые пользователю из функции Auth.
*/

return [
    'login_failed' => 'Эти учетные данные не совпадают с нашими записями.',
    'session_expired' => 'Сеанс истек — пожалуйста, войдите снова.',
    'totp_verification_expired' => 'Срок проверки TOTP истек.',
    'totp_locked_minutes' => 'Слишком много попыток — повторите через :minutes мин.',
    'totp_locked_permanent' => 'Аккаунт заблокирован — используйте `admin:reset-password` для восстановления доступа.',
    'invalid_totp_code' => 'Введенный код недействителен.',
    'totp_enrollment_expired' => 'Сеанс настройки TOTP истек. Начните заново.',
    'current_password_incorrect' => 'Текущий пароль указан неверно.',
    'github_no_account' => 'К этому аккаунту GitHub не привязан ни один аккаунт администратора.',
    'github_already_linked' => 'Аккаунт GitHub уже привязан.',
    'github_conflict' => 'Этот аккаунт GitHub уже привязан к другому аккаунту администратора.',
    'github_linked' => 'Аккаунт GitHub успешно привязан.',
    'github_unlinked' => 'Аккаунт GitHub отвязан.',
    'github_invalid_intent' => 'Недопустимое намерение OAuth.',
    'profile_updated' => 'Профиль успешно обновлен.',
    'totp_disabled' => 'TOTP отключен.',
    'code_format_invalid' => 'Поле :attribute должно содержать 6-значный код или код восстановления.',
];
