USE student_hub;

ALTER TABLE students
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER created_at,
    ADD KEY idx_students_deleted_at (deleted_at);
