CREATE DATABASE IF NOT EXISTS u82295 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE u82301;

CREATE TABLE IF NOT EXISTS programming_languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    biography TEXT NOT NULL,
    contract_accepted TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS submission_languages (
    submission_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (submission_id, language_id),
    CONSTRAINT fk_submission_languages_submission
        FOREIGN KEY (submission_id) REFERENCES submissions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_submission_languages_language
        FOREIGN KEY (language_id) REFERENCES programming_languages(id)
        ON DELETE RESTRICT
);

INSERT INTO programming_languages (name)
VALUES
    ('Pascal'),
    ('C'),
    ('C++'),
    ('JavaScript'),
    ('PHP'),
    ('Python'),
    ('Java'),
    ('Haskell'),
    ('Clojure'),
    ('Prolog'),
    ('Scala'),
    ('Go')
ON DUPLICATE KEY UPDATE name = VALUES(name);
