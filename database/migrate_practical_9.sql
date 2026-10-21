USE student_hub;

ALTER TABLE students
    ADD COLUMN username VARCHAR(30) NULL AFTER full_name;

UPDATE students
SET username = CONCAT('student', student_id)
WHERE username IS NULL;

ALTER TABLE students
    MODIFY username VARCHAR(30) NOT NULL,
    ADD UNIQUE KEY uq_students_username (username);

CREATE TABLE IF NOT EXISTS registration_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_registration_audit_student (student_id),
    CONSTRAINT fk_registration_audit_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;
