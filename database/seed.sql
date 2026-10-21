USE student_hub;

INSERT INTO students
    (full_name, username, email, mobile, password_hash, gender)
VALUES
    ('Sample Student', 'sample_student', 'sample.student@example.test', '9000000000',
     '$2y$10$/4hdzN5LAuIwKksIlzblReJGAJhJKyHglxpX7p9CSH74R.CHn/xpy', 'Other')
ON DUPLICATE KEY UPDATE
    student_id = LAST_INSERT_ID(student_id);

SET @sample_student_id = LAST_INSERT_ID();

INSERT IGNORE INTO registrations (student_id, course_code, study_year)
VALUES (@sample_student_id, 'CSE', 2);
