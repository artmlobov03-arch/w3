<?php

declare(strict_types=1);

set_time_limit(10);
ini_set('default_socket_timeout', '5');

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'u82295';
const DB_USER = 'u82295';
const DB_PASSWORD = '7819341';

$availableLanguages = [
    'Pascal',
    'C',
    'C++',
    'JavaScript',
    'PHP',
    'Python',
    'Java',
    'Haskell',
    'Clojure',
    'Prolog',
    'Scala',
    'Go',
];

$genderOptions = [
    'male' => 'Мужской',
    'female' => 'Женский',
];

$values = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'birth_date' => '',
    'gender' => '',
    'languages' => [],
    'biography' => '',
    'contract_accepted' => false,
];

$errors = [];
$successMessage = null;
$dbError = null;

function stringLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }

    return strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $values['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $values['birth_date'] = trim((string) ($_POST['birth_date'] ?? ''));
    $values['gender'] = trim((string) ($_POST['gender'] ?? ''));
    $values['languages'] = array_values(array_unique(array_map('strval', $_POST['languages'] ?? [])));
    $values['biography'] = trim((string) ($_POST['biography'] ?? ''));
    $values['contract_accepted'] = isset($_POST['contract_accepted']);

    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Укажите ФИО.';
    } elseif (stringLength($values['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[\p{L}\s-]+$/u', $values['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефис.';
    }

    if ($values['phone'] === '') {
        $errors['phone'] = 'Укажите телефон.';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $values['phone'])) {
        $errors['phone'] = 'Телефон должен содержать от 7 до 20 допустимых символов.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Укажите e-mail.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный e-mail.';
    } elseif (stringLength($values['email']) > 255) {
        $errors['email'] = 'E-mail не должен превышать 255 символов.';
    }

    if ($values['birth_date'] === '') {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } else {
        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $values['birth_date']);
        $birthDateErrors = DateTimeImmutable::getLastErrors();
        if ($birthDateErrors === false) {
            $birthDateErrors = [
                'warning_count' => 0,
                'error_count' => 0,
            ];
        }
        $isBirthDateValid = $birthDate instanceof DateTimeImmutable
            && $birthDate->format('Y-m-d') === $values['birth_date']
            && $birthDateErrors['warning_count'] === 0
            && $birthDateErrors['error_count'] === 0;

        if (!$isBirthDateValid) {
            $errors['birth_date'] = 'Введите корректную дату рождения.';
        } elseif ($birthDate > new DateTimeImmutable('today')) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
        }
    }

    if (!array_key_exists($values['gender'], $genderOptions)) {
        $errors['gender'] = 'Выберите допустимый пол.';
    }

    if ($values['languages'] === []) {
        $errors['languages'] = 'Выберите хотя бы один любимый язык программирования.';
    } else {
        foreach ($values['languages'] as $language) {
            if (!in_array($language, $availableLanguages, true)) {
                $errors['languages'] = 'Список языков содержит недопустимое значение.';
                break;
            }
        }
    }

    if ($values['biography'] === '') {
        $errors['biography'] = 'Напишите биографию.';
    } elseif (stringLength($values['biography']) > 2000) {
        $errors['biography'] = 'Биография не должна превышать 2000 символов.';
    }

    if (!$values['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо ознакомиться с контрактом.';
    }

    if ($errors === []) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $pdo->beginTransaction();

            $submissionStatement = $pdo->prepare(
                'INSERT INTO submissions (full_name, phone, email, birth_date, gender, biography, contract_accepted) VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)'
            );

            $submissionStatement->execute([
                ':full_name' => $values['full_name'],
                ':phone' => $values['phone'],
                ':email' => $values['email'],
                ':birth_date' => $values['birth_date'],
                ':gender' => $values['gender'],
                ':biography' => $values['biography'],
                ':contract_accepted' => 1,
            ]);

            $submissionId = (int) $pdo->lastInsertId();

            $languageSelectStatement = $pdo->prepare('SELECT id FROM programming_languages WHERE name = :name');
            $submissionLanguageStatement = $pdo->prepare(
                'INSERT INTO submission_languages (submission_id, language_id) VALUES (:submission_id, :language_id)'
            );

            foreach ($values['languages'] as $language) {
                $languageSelectStatement->execute([':name' => $language]);
                $languageId = $languageSelectStatement->fetchColumn();

                if ($languageId === false) {
                    throw new RuntimeException('Не найден язык программирования в справочнике: ' . $language);
                }

                $submissionLanguageStatement->execute([
                    ':submission_id' => $submissionId,
                    ':language_id' => (int) $languageId,
                ]);
            }

            $pdo->commit();

            $successMessage = 'Данные успешно сохранены.';
            $values = [
                'full_name' => '',
                'phone' => '',
                'email' => '',
                'birth_date' => '',
                'gender' => '',
                'languages' => [],
                'biography' => '',
                'contract_accepted' => false,
            ];
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $dbError = 'Не удалось сохранить данные: ' . $exception->getMessage();
        }
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация разработчика</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <div class="sidebar__logo">💻</div>
        <span class="sidebar__tag">Регистрация</span>
        <h2 class="sidebar__title">Анкета разработчика</h2>
        <p class="sidebar__desc">Заполните форму, чтобы зарегистрироваться в системе. Данные сохраняются в базу MySQL.</p>

        <div class="sidebar__steps">
            <div class="step">
                <div class="step__num">1</div>
                <div class="step__text">
                    <strong>Личные данные</strong>
                    ФИО, телефон, e-mail и дата рождения
                </div>
            </div>
            <div class="step">
                <div class="step__num">2</div>
                <div class="step__text">
                    <strong>Профессиональное</strong>
                    Пол, языки программирования, биография
                </div>
            </div>
            <div class="step">
                <div class="step__num">3</div>
                <div class="step__text">
                    <strong>Подтверждение</strong>
                    Согласие с контрактом и отправка анкеты
                </div>
            </div>
        </div>

        <p class="sidebar__footer">© 2025 DevRegister</p>
    </aside>

    <!-- ── Main ── -->
    <main class="main">
        <div class="main__header">
            <h1>Новая анкета</h1>
            <p>Все поля обязательны для заполнения</p>
        </div>

        <?php if ($successMessage !== null): ?>
            <div class="alert alert--success"><?php echo escape($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($dbError !== null): ?>
            <div class="alert alert--error"><?php echo escape($dbError); ?></div>
        <?php endif; ?>

        <form action="" method="post" novalidate>

            <!-- Личные данные -->
            <div class="section">
                <div class="section__title">Личные данные</div>

                <div class="form-row form-row--single" style="margin-bottom:18px;">
                    <div class="field">
                        <label for="full_name">ФИО</label>
                        <input
                            id="full_name"
                            name="full_name"
                            type="text"
                            maxlength="150"
                            value="<?php echo escape($values['full_name']); ?>"
                            class="<?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                            placeholder="Например, Петров Алексей Сергеевич"
                        >
                        <?php if (isset($errors['full_name'])): ?>
                            <span class="error-text"><?php echo escape($errors['full_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input
                            id="phone"
                            name="phone"
                            type="tel"
                            value="<?php echo escape($values['phone']); ?>"
                            class="<?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                            placeholder="+7 999 000-00-00"
                        >
                        <?php if (isset($errors['phone'])): ?>
                            <span class="error-text"><?php echo escape($errors['phone']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="<?php echo escape($values['email']); ?>"
                            class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                            placeholder="developer@example.com"
                        >
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-text"><?php echo escape($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Профиль -->
            <div class="section">
                <div class="section__title">Профиль</div>

                <div class="form-row" style="margin-bottom:18px;">
                    <div class="field">
                        <label for="birth_date">Дата рождения</label>
                        <input
                            id="birth_date"
                            name="birth_date"
                            type="date"
                            value="<?php echo escape($values['birth_date']); ?>"
                            class="<?php echo isset($errors['birth_date']) ? 'is-invalid' : ''; ?>"
                        >
                        <?php if (isset($errors['birth_date'])): ?>
                            <span class="error-text"><?php echo escape($errors['birth_date']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <fieldset class="fieldset-plain <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>">
                            <legend>Пол</legend>
                            <div class="radio-list">
                                <?php foreach ($genderOptions as $genderValue => $genderLabel): ?>
                                    <label class="radio-card">
                                        <input
                                            type="radio"
                                            name="gender"
                                            value="<?php echo escape($genderValue); ?>"
                                            <?php echo $values['gender'] === $genderValue ? 'checked' : ''; ?>
                                        >
                                        <?php echo escape($genderLabel); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['gender'])): ?>
                                <span class="error-text"><?php echo escape($errors['gender']); ?></span>
                            <?php endif; ?>
                        </fieldset>
                    </div>
                </div>

                <div class="form-row form-row--single">
                    <div class="field">
                        <label for="languages">Любимые языки программирования</label>
                        <select
                            id="languages"
                            name="languages[]"
                            multiple
                            class="<?php echo isset($errors['languages']) ? 'is-invalid' : ''; ?>"
                        >
                            <?php foreach ($availableLanguages as $language): ?>
                                <option value="<?php echo escape($language); ?>" <?php echo in_array($language, $values['languages'], true) ? 'selected' : ''; ?>>
                                    <?php echo escape($language); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Удерживайте Ctrl (или Cmd на Mac) для выбора нескольких языков</span>
                        <?php if (isset($errors['languages'])): ?>
                            <span class="error-text"><?php echo escape($errors['languages']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- О себе -->
            <div class="section">
                <div class="section__title">О себе</div>

                <div class="field">
                    <label for="biography">Биография</label>
                    <textarea
                        id="biography"
                        name="biography"
                        rows="5"
                        class="<?php echo isset($errors['biography']) ? 'is-invalid' : ''; ?>"
                        placeholder="Расскажите о своём опыте, проектах и увлечениях..."
                    ><?php echo escape($values['biography']); ?></textarea>
                    <?php if (isset($errors['biography'])): ?>
                        <span class="error-text"><?php echo escape($errors['biography']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Подтверждение -->
            <div class="section">
                <div class="section__title">Подтверждение</div>

                <label class="checkbox-wrap <?php echo isset($errors['contract_accepted']) ? 'is-invalid' : ''; ?>">
                    <input
                        type="checkbox"
                        name="contract_accepted"
                        value="1"
                        <?php echo $values['contract_accepted'] ? 'checked' : ''; ?>
                    >
                    <span>Я ознакомился(-лась) с условиями контракта и согласен(-на) с его положениями</span>
                </label>
                <?php if (isset($errors['contract_accepted'])): ?>
                    <span class="error-text" style="display:block;margin-top:8px;"><?php echo escape($errors['contract_accepted']); ?></span>
                <?php endif; ?>
            </div>

            <div class="submit-row">
                <button class="btn-submit" type="submit">
                    Отправить анкету →
                </button>
            </div>

        </form>
    </main>

</div>
</body>
</html>
