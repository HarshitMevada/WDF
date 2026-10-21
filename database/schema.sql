CREATE DATABASE IF NOT EXISTS student_hub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_hub;

CREATE TABLE IF NOT EXISTS students (
    student_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mobile CHAR(10) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_username (username),
    UNIQUE KEY uq_students_email (email),
    UNIQUE KEY uq_students_mobile (mobile),
    KEY idx_students_deleted_at (deleted_at)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS courses (
    course_code VARCHAR(10) NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (course_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    event_id SMALLINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(40) NOT NULL,
    event_date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    PRIMARY KEY (event_id),
    KEY idx_events_date (event_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
    registration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    course_code VARCHAR(10) NOT NULL,
    study_year TINYINT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_registration_student_course_year
        (student_id, course_code, study_year),
    CONSTRAINT chk_registration_study_year
        CHECK (study_year BETWEEN 1 AND 4),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_course
        FOREIGN KEY (course_code) REFERENCES courses (course_code)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO courses (course_code, course_name) VALUES
    ('CSE', 'Computer Science'),
    ('IT', 'Information Technology'),
    ('CE', 'Computer Engineering'),
    ('Other', 'Other');

INSERT IGNORE INTO events (event_id, title, category, event_date, description) VALUES
    (1, 'Coding Competition', 'Technical', '2026-10-10', 'Participate in a coding competition and test your programming skills.'),
    (2, 'Annual Sports Day', 'Sports', '2026-10-12', 'Take part in exciting sports and outdoor activities.'),
    (3, 'Web Development Workshop', 'Technical', '2026-10-15', 'Learn HTML, CSS and JavaScript in a practical workshop.'),
    (4, 'Cultural Fest', 'Cultural', '2026-10-18', 'Enjoy music, dance, performances and cultural activities.'),
    (5, 'AI Seminar', 'Technical', '2026-10-20', 'Explore artificial intelligence and its real-world applications.'),
    (6, 'Cricket Tournament', 'Sports', '2026-10-22', 'Join the interdepartmental cricket tournament.'),
    (7, 'Photography Competition', 'Cultural', '2026-10-25', 'Showcase your photography and creative skills.'),
    (8, 'Cyber Security Workshop', 'Technical', '2026-10-27', 'Understand cyber security basics and online safety.'),
    (9, 'Basketball Match', 'Sports', '2026-10-29', 'Watch or participate in the college basketball match.'),
    (10, 'Dance Competition', 'Cultural', '2026-11-02', 'Show your dance skills in the college competition.'),
    (11, 'Project Exhibition', 'Technical', '2026-11-05', 'Present innovative projects and student ideas.'),
    (12, 'Football Tournament', 'Sports', '2026-11-08', 'Compete with other teams in the football tournament.'),
    (13, 'Music Night', 'Cultural', '2026-11-10', 'Enjoy live music and performances by students.'),
    (14, 'Robotics Seminar', 'Technical', '2026-11-12', 'Learn about robotics, automation and new technologies.'),
    (15, 'Chess Competition', 'Sports', '2026-11-15', 'Test your strategic thinking in a chess competition.');
