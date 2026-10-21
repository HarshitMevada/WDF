USE student_hub;

ALTER TABLE students
    ADD COLUMN role ENUM('student', 'admin') NOT NULL DEFAULT 'student'
        AFTER gender,
    ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL
        AFTER role;

CREATE TABLE IF NOT EXISTS remember_tokens (
    token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_remember_tokens_student (student_id),
    UNIQUE KEY uq_remember_tokens_hash (token_hash),
    KEY idx_remember_tokens_expiry (expires_at),
    CONSTRAINT fk_remember_tokens_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
