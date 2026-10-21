USE student_hub;

ALTER TABLE events
    MODIFY event_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD COLUMN poster_path VARCHAR(255) NULL AFTER description,
    ADD COLUMN status ENUM('open', 'closed') NOT NULL DEFAULT 'open' AFTER poster_path,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER status;

CREATE TABLE IF NOT EXISTS event_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id SMALLINT UNSIGNED NULL,
    admin_student_id BIGINT UNSIGNED NULL,
    event_title VARCHAR(150) NOT NULL,
    action VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_event_audit_event (event_id),
    KEY idx_event_audit_admin (admin_student_id),
    CONSTRAINT fk_event_audit_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_event_audit_admin
        FOREIGN KEY (admin_student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;
