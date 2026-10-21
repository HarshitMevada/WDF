USE student_hub;

INSERT IGNORE INTO students
    (full_name, username, email, mobile, password_hash, gender, role)
VALUES
    ('Demo Student 01', 'seed_student_01', 'student01@example.test', '6000000001', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 02', 'seed_student_02', 'student02@example.test', '6000000002', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 03', 'seed_student_03', 'student03@example.test', '6000000003', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 04', 'seed_student_04', 'student04@example.test', '6000000004', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 05', 'seed_student_05', 'student05@example.test', '6000000005', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 06', 'seed_student_06', 'student06@example.test', '6000000006', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 07', 'seed_student_07', 'student07@example.test', '6000000007', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 08', 'seed_student_08', 'student08@example.test', '6000000008', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 09', 'seed_student_09', 'student09@example.test', '6000000009', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 10', 'seed_student_10', 'student10@example.test', '6000000010', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 11', 'seed_student_11', 'student11@example.test', '6000000011', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 12', 'seed_student_12', 'student12@example.test', '6000000012', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 13', 'seed_student_13', 'student13@example.test', '6000000013', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 14', 'seed_student_14', 'student14@example.test', '6000000014', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 15', 'seed_student_15', 'student15@example.test', '6000000015', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 16', 'seed_student_16', 'student16@example.test', '6000000016', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 17', 'seed_student_17', 'student17@example.test', '6000000017', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 18', 'seed_student_18', 'student18@example.test', '6000000018', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 19', 'seed_student_19', 'student19@example.test', '6000000019', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student'),
    ('Demo Student 20', 'seed_student_20', 'student20@example.test', '6000000020', '$2y$12$livWsQmOBEawzJsQSVTWcOTzQJpN04XAg4DDbFsphoKWkZnFJ8duK', 'Other', 'student');

INSERT IGNORE INTO registrations (student_id, course_code, study_year)
SELECT student_id,
       CASE MOD(CAST(SUBSTRING(username, 14) AS UNSIGNED) - 1, 4)
           WHEN 0 THEN 'CSE'
           WHEN 1 THEN 'IT'
           WHEN 2 THEN 'CE'
           ELSE 'Other'
       END,
       MOD(CAST(SUBSTRING(username, 14) AS UNSIGNED) - 1, 4) + 1
FROM students
WHERE username LIKE 'seed_student_%';
