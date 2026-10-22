<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = requireStudentRole('admin');
startStudentHubSession();

function escapeStudentManagement(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function studentManagementFilters(): array
{
    $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $course = is_string($_GET['course'] ?? null) ? trim($_GET['course']) : '';
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'active';

    if (!in_array($course, ['', 'CSE', 'IT', 'CE', 'Other'], true)) {
        $course = '';
    }
    if (!in_array($status, ['active', 'deleted', 'all'], true)) {
        $status = 'active';
    }

    return [$search, $course, $status];
}

function studentManagementWhere(string $search, string $course, string $status): array
{
    $conditions = ["s.role = 'student'"];
    $values = [];

    if ($status === 'active') {
        $conditions[] = 's.deleted_at IS NULL';
    } elseif ($status === 'deleted') {
        $conditions[] = 's.deleted_at IS NOT NULL';
    }

    if ($search !== '') {
        $conditions[] = '(s.full_name LIKE ? OR s.email LIKE ? OR s.username LIKE ? OR latest.course_code LIKE ?)';
        $like = '%' . $search . '%';
        array_push($values, $like, $like, $like, $like);
    }

    if ($course !== '') {
        $conditions[] = 'latest.course_code = ?';
        $values[] = $course;
    }

    return [implode(' AND ', $conditions), $values];
}

function exportCsvValue(string $value): string
{
    if (preg_match('/^\s*[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

[$search, $courseFilter, $statusFilter] = studentManagementFilters();
$csrfToken = getStudentHubCsrfToken();
$errors = [];
$message = is_string($_SESSION['student_management_message'] ?? null)
    ? $_SESSION['student_management_message']
    : '';
unset($_SESSION['student_management_message']);

$form = [
    'student_id' => 0,
    'full_name' => '',
    'username' => '',
    'email' => '',
    'mobile' => '',
    'gender' => 'Other',
    'course_code' => '',
    'study_year' => '',
];
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$mysqli = null;
$transactionActive = false;

try {
    $mysqli = getDatabaseConnection();

    if ($editId !== null && $editId !== false && $editId > 0) {
        $editStatement = $mysqli->prepare(
            "SELECT s.student_id, s.full_name, s.username, s.email, s.mobile, s.gender,
                    COALESCE(latest.course_code, '') AS course_code,
                    COALESCE(latest.study_year, '') AS study_year
             FROM students AS s
             LEFT JOIN registrations AS latest
                ON latest.registration_id = (
                    SELECT MAX(r.registration_id)
                    FROM registrations AS r
                    WHERE r.student_id = s.student_id
                )
             WHERE s.student_id = ? AND s.role = 'student' AND s.deleted_at IS NULL"
        );
        $editStatement->bind_param('i', $editId);
        $editStatement->execute();
        $form = $editStatement->get_result()->fetch_assoc() ?: $form;
        $editStatement->close();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = is_string($_POST['csrf_token'] ?? null)
            ? $_POST['csrf_token']
            : '';
        if (!hash_equals($csrfToken, $submittedToken)) {
            http_response_code(403);
            $errors[] = 'Your session expired. Refresh the page and try again.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

            if ($action === 'delete' || $action === 'restore') {
                if ($studentId === false || $studentId === null || $studentId < 1) {
                    $errors[] = 'Select a valid student record.';
                } else {
                    $mysqli->begin_transaction();
                    $transactionActive = true;
                    if ($action === 'delete') {
                        $statement = $mysqli->prepare(
                            "UPDATE students SET deleted_at = CURRENT_TIMESTAMP
                             WHERE student_id = ? AND role = 'student' AND deleted_at IS NULL"
                        );
                        $auditAction = 'student_soft_deleted';
                    } else {
                        $statement = $mysqli->prepare(
                            "UPDATE students SET deleted_at = NULL
                             WHERE student_id = ? AND role = 'student' AND deleted_at IS NOT NULL"
                        );
                        $auditAction = 'student_restored';
                    }
                    $statement->bind_param('i', $studentId);
                    $statement->execute();
                    $changed = $statement->affected_rows > 0;
                    $statement->close();

                    if ($changed) {
                        $tokenStatement = $mysqli->prepare(
                            'DELETE FROM remember_tokens WHERE student_id = ?'
                        );
                        $tokenStatement->bind_param('i', $studentId);
                        $tokenStatement->execute();
                        $tokenStatement->close();

                        if ($action === 'delete') {
                            $auditStatement = $mysqli->prepare(
                                'INSERT INTO registration_audit (student_id, action) VALUES (?, ?)'
                            );
                            $auditStatement->bind_param('is', $studentId, $auditAction);
                            $auditStatement->execute();
                            $auditStatement->close();
                        }

                        recordAdminAudit(
                            $mysqli,
                            (int) $admin['student_id'],
                            $action === 'delete' ? 'student_deleted' : 'student_restored',
                            'student',
                            $studentId
                        );

                        $mysqli->commit();
                        $transactionActive = false;
                        $_SESSION['student_management_message'] = $action === 'delete'
                            ? 'Student moved to the deleted list.'
                            : 'Student restored successfully.';
                        $mysqli->close();
                        $mysqli = null;
                        header('Location: admin_students.php');
                        exit;
                    } else {
                        $mysqli->rollback();
                        $transactionActive = false;
                        $errors[] = 'The student record was not changed. It may have already been updated.';
                    }
                }
            } elseif ($action === 'save') {
                $rawStudentId = is_string($_POST['student_id'] ?? null)
                    ? $_POST['student_id']
                    : '';
                $form = [
                    'student_id' => $studentId !== false && $studentId !== null ? $studentId : 0,
                    'full_name' => is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '',
                    'username' => is_string($_POST['username'] ?? null) ? strtolower(trim($_POST['username'])) : '',
                    'email' => is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '',
                    'mobile' => is_string($_POST['mobile'] ?? null) ? trim($_POST['mobile']) : '',
                    'gender' => is_string($_POST['gender'] ?? null) ? trim($_POST['gender']) : '',
                    'course_code' => is_string($_POST['course_code'] ?? null) ? trim($_POST['course_code']) : '',
                    'study_year' => is_string($_POST['study_year'] ?? null) ? trim($_POST['study_year']) : '',
                ];
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                $isEditing = (int) $form['student_id'] > 0;

                if ($rawStudentId !== '' && $rawStudentId !== '0' && $studentId === false) {
                    $errors[] = 'Select a valid student record.';
                }
                if (!preg_match('/^[A-Za-z ]{3,120}$/', $form['full_name'])) {
                    $errors[] = 'Enter a name with 3 to 120 letters and spaces.';
                }
                if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $form['username'])) {
                    $errors[] = 'Username must be 3 to 30 letters, numbers, or underscores.';
                }
                if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 254) {
                    $errors[] = 'Enter a valid email address.';
                }
                if (!preg_match('/^[6-9][0-9]{9}$/', $form['mobile'])) {
                    $errors[] = 'Enter a valid 10-digit mobile number.';
                }
                if (!in_array($form['gender'], ['Male', 'Female', 'Other'], true)) {
                    $errors[] = 'Select a valid gender.';
                }
                if (!in_array($form['course_code'], ['CSE', 'IT', 'CE', 'Other'], true)) {
                    $errors[] = 'Select a valid course.';
                }
                if (!in_array($form['study_year'], ['1', '2', '3', '4'], true)) {
                    $errors[] = 'Select a valid study year.';
                }
                if (!$isEditing && !preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$!%*?&]).{8,}$/', $password)) {
                    $errors[] = 'New students need a password with 8 characters, uppercase, lowercase, number, and special character.';
                } elseif ($isEditing && $password !== '' &&
                    !preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$!%*?&]).{8,}$/', $password)) {
                    $errors[] = 'Password needs 8 characters, uppercase, lowercase, number, and special character.';
                }
                if ($isEditing && ($studentId === false || $studentId === null || $studentId < 1)) {
                    $errors[] = 'Select a valid student record.';
                }

                if ($errors === []) {
                    $duplicateStatement = $mysqli->prepare(
                        "SELECT student_id FROM students
                         WHERE role = 'student'
                           AND (username = ? OR email = ? OR mobile = ?)
                           AND student_id <> ?
                         LIMIT 1"
                    );
                    $currentId = (int) $form['student_id'];
                    $duplicateStatement->bind_param(
                        'sssi',
                        $form['username'],
                        $form['email'],
                        $form['mobile'],
                        $currentId
                    );
                    $duplicateStatement->execute();
                    $duplicateStatement->store_result();
                    $duplicateExists = $duplicateStatement->num_rows > 0;
                    $duplicateStatement->close();

                    if ($duplicateExists) {
                        $errors[] = 'Username, email, or mobile number is already in use.';
                    } else {
                        $mysqli->begin_transaction();
                        $transactionActive = true;
                        if ($isEditing) {
                            $updateStatement = $mysqli->prepare(
                                'UPDATE students
                                 SET full_name = ?, username = ?, email = ?, mobile = ?, gender = ?
                                 WHERE student_id = ? AND role = \'student\' AND deleted_at IS NULL'
                            );
                            $updateStatement->bind_param(
                                'sssssi',
                                $form['full_name'],
                                $form['username'],
                                $form['email'],
                                $form['mobile'],
                                $form['gender'],
                                $currentId
                            );
                            $updateStatement->execute();
                            $updateStatement->close();
                            $savedId = $currentId;

                            if ($password !== '') {
                                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                                $passwordStatement = $mysqli->prepare(
                                    'UPDATE students SET password_hash = ? WHERE student_id = ?'
                                );
                                $passwordStatement->bind_param('si', $passwordHash, $savedId);
                                $passwordStatement->execute();
                                $passwordStatement->close();
                            }
                        } else {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $insertStatement = $mysqli->prepare(
                                "INSERT INTO students
                                    (full_name, username, email, mobile, password_hash, gender)
                                 VALUES (?, ?, ?, ?, ?, ?)"
                            );
                            $insertStatement->bind_param(
                                'ssssss',
                                $form['full_name'],
                                $form['username'],
                                $form['email'],
                                $form['mobile'],
                                $passwordHash,
                                $form['gender']
                            );
                            $insertStatement->execute();
                            $savedId = (int) $mysqli->insert_id;
                            $insertStatement->close();
                        }

                        $registrationStatement = $mysqli->prepare(
                            'SELECT registration_id FROM registrations
                             WHERE student_id = ? ORDER BY registration_id DESC LIMIT 1'
                        );
                        $registrationStatement->bind_param('i', $savedId);
                        $registrationStatement->execute();
                        $registration = $registrationStatement->get_result()->fetch_assoc();
                        $registrationStatement->close();
                        $studyYear = (int) $form['study_year'];

                        if ($registration !== null) {
                            $registrationId = (int) $registration['registration_id'];
                            $courseStatement = $mysqli->prepare(
                                'UPDATE registrations SET course_code = ?, study_year = ?
                                 WHERE registration_id = ?'
                            );
                            $courseStatement->bind_param(
                                'sii',
                                $form['course_code'],
                                $studyYear,
                                $registrationId
                            );
                        } else {
                            $courseStatement = $mysqli->prepare(
                                'INSERT INTO registrations (student_id, course_code, study_year)
                                 VALUES (?, ?, ?)'
                            );
                            $courseStatement->bind_param(
                                'isi',
                                $savedId,
                                $form['course_code'],
                                $studyYear
                            );
                        }
                        $courseStatement->execute();
                        $courseStatement->close();
                        recordAdminAudit(
                            $mysqli,
                            (int) $admin['student_id'],
                            $isEditing ? 'student_updated' : 'student_created',
                            'student',
                            $savedId
                        );
                        $mysqli->commit();
                        $transactionActive = false;

                        $_SESSION['student_management_message'] = $isEditing
                            ? 'Student updated successfully.'
                            : 'Student created successfully.';
                        $mysqli->close();
                        $mysqli = null;
                        header('Location: admin_students.php');
                        exit;
                    }
                }
            } else {
                $errors[] = 'Select a valid student management action.';
            }
        }
    }

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        [$where, $values] = studentManagementWhere($search, $courseFilter, $statusFilter);
        $exportStatement = $mysqli->prepare(
            "SELECT s.student_id, s.full_name, s.username, s.email, s.mobile, s.gender,
                    COALESCE(latest.course_code, '') AS course_code,
                    COALESCE(latest.study_year, '') AS study_year,
                    s.created_at, s.deleted_at
             FROM students AS s
             LEFT JOIN registrations AS latest
                ON latest.registration_id = (
                    SELECT MAX(r.registration_id)
                    FROM registrations AS r
                    WHERE r.student_id = s.student_id
                )
             WHERE {$where}
             ORDER BY s.student_id DESC"
        );
        $exportStatement->execute($values);
        $exportRows = $exportStatement->get_result();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students.csv"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new RuntimeException('Unable to open CSV output stream.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Full name', 'Username', 'Email', 'Mobile', 'Gender', 'Course', 'Year', 'Created', 'Status']);
        while ($row = $exportRows->fetch_assoc()) {
            fputcsv($output, [
                $row['student_id'],
                exportCsvValue($row['full_name']),
                exportCsvValue($row['username']),
                exportCsvValue($row['email']),
                exportCsvValue($row['mobile']),
                $row['gender'],
                $row['course_code'],
                $row['study_year'],
                $row['created_at'],
                $row['deleted_at'] === null ? 'Active' : 'Deleted',
            ]);
        }
        fclose($output);
        $exportStatement->close();
        $mysqli->close();
        exit;
    }

    [$where, $values] = studentManagementWhere($search, $courseFilter, $statusFilter);
    $countStatement = $mysqli->prepare(
        "SELECT COUNT(*) AS total
         FROM students AS s
         LEFT JOIN registrations AS latest
            ON latest.registration_id = (
                SELECT MAX(r.registration_id)
                FROM registrations AS r
                WHERE r.student_id = s.student_id
            )
         WHERE {$where}"
    );
    $countStatement->execute($values);
    $totalStudents = (int) $countStatement->get_result()->fetch_assoc()['total'];
    $countStatement->close();

    $pageSize = 10;
    $totalPages = max(1, (int) ceil($totalStudents / $pageSize));
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $page = $page !== false && $page !== null ? max(1, min($page, $totalPages)) : 1;
    $offset = ($page - 1) * $pageSize;
    $listStatement = $mysqli->prepare(
        "SELECT s.student_id, s.full_name, s.username, s.email, s.mobile, s.gender,
                COALESCE(latest.course_code, '') AS course_code,
                COALESCE(latest.study_year, '') AS study_year,
                s.deleted_at
         FROM students AS s
         LEFT JOIN registrations AS latest
            ON latest.registration_id = (
                SELECT MAX(r.registration_id)
                FROM registrations AS r
                WHERE r.student_id = s.student_id
            )
         WHERE {$where}
         ORDER BY s.student_id DESC
         LIMIT ? OFFSET ?"
    );
    $listValues = [...$values, $pageSize, $offset];
    $listStatement->execute($listValues);
    $students = $listStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStatement->close();
} catch (mysqli_sql_exception $exception) {
    if ($mysqli instanceof mysqli && $transactionActive) {
        try {
            $mysqli->rollback();
            $transactionActive = false;
        } catch (mysqli_sql_exception $rollbackException) {
            error_log('Student management rollback failed: ' . $rollbackException->getMessage());
        }
    }
    error_log('Student management failed: ' . $exception->getMessage());
    http_response_code(500);
    $errors[] = 'Student records could not be loaded or saved. Please try again later.';
    $students = [];
    $totalStudents = 0;
    $totalPages = 1;
    $page = 1;
} finally {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

$query = ['q' => $search, 'course' => $courseFilter, 'status' => $statusFilter];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | Student Hub</title>
    <link rel="stylesheet" href="style.css?v=20261022-1827">
</head>
<body class="body admin-students-page">
    <div class="navbar">
        <nav class="nav">
            <a href="home.html">CHARUSAT</a>
            <?php foreach (studentHubNavigationLinks('admin') as $label => $href) { ?>
                <a href="<?php echo escapeStudentManagement($href); ?>"><?php echo escapeStudentManagement($label); ?></a>
            <?php } ?>
            <form method="POST" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeStudentManagement($csrfToken); ?>">
                <button type="submit">Log out</button>
            </form>
        </nav>
    </div>
    <main class="student-management">
        <section class="card">
            <h1>Student Management</h1>
            <p>Search by student name, email, username, or course. Only student accounts can be edited or deleted here.</p>
            <?php if ($message !== '') { ?><p class="success-message" role="status"><?php echo escapeStudentManagement($message); ?></p><?php } ?>
            <?php foreach ($errors as $error) { ?><p class="error-message" role="alert"><?php echo escapeStudentManagement($error); ?></p><?php } ?>
        </section>

        <section class="card">
            <h2><?php echo (int) $form['student_id'] > 0 ? 'Edit Student' : 'Add Student'; ?></h2>
            <form class="management-form" method="POST" action="admin_students.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeStudentManagement($csrfToken); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="student_id" value="<?php echo (int) $form['student_id']; ?>">
                <label>Full name
                    <input name="full_name" maxlength="120" value="<?php echo escapeStudentManagement((string) $form['full_name']); ?>" required>
                </label>
                <label>Username
                    <input name="username" minlength="3" maxlength="30" value="<?php echo escapeStudentManagement((string) $form['username']); ?>" required>
                </label>
                <label>Email
                    <input type="email" name="email" maxlength="254" value="<?php echo escapeStudentManagement((string) $form['email']); ?>" required>
                </label>
                <label>Mobile
                    <input name="mobile" inputmode="numeric" maxlength="10" value="<?php echo escapeStudentManagement((string) $form['mobile']); ?>" required>
                </label>
                <label>Gender
                    <select name="gender" required>
                        <?php foreach (['Male', 'Female', 'Other'] as $gender) { ?>
                            <option value="<?php echo $gender; ?>" <?php echo $form['gender'] === $gender ? 'selected' : ''; ?>><?php echo $gender; ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>Course
                    <select name="course_code" required>
                        <option value="">Select course</option>
                        <?php foreach (['CSE', 'IT', 'CE', 'Other'] as $course) { ?>
                            <option value="<?php echo $course; ?>" <?php echo $form['course_code'] === $course ? 'selected' : ''; ?>><?php echo $course; ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>Study year
                    <select name="study_year" required>
                        <option value="">Select year</option>
                        <?php foreach (['1', '2', '3', '4'] as $year) { ?>
                            <option value="<?php echo $year; ?>" <?php echo (string) $form['study_year'] === $year ? 'selected' : ''; ?>>Year <?php echo $year; ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>Password <?php echo (int) $form['student_id'] > 0 ? '(leave blank to keep current)' : ''; ?>
                    <input type="password" name="password" autocomplete="new-password" <?php echo (int) $form['student_id'] > 0 ? '' : 'required'; ?>>
                </label>
                <div>
                    <button type="submit"><?php echo (int) $form['student_id'] > 0 ? 'Save changes' : 'Add student'; ?></button>
                    <?php if ((int) $form['student_id'] > 0) { ?><a class="button-link secondary" href="admin_students.php">Cancel</a><?php } ?>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Student Records</h2>
            <form class="management-form" method="GET" action="admin_students.php">
                <label>Search
                    <input type="search" name="q" value="<?php echo escapeStudentManagement($search); ?>" placeholder="Name, email, username, course">
                </label>
                <label>Course
                    <select name="course">
                        <option value="">All courses</option>
                        <?php foreach (['CSE', 'IT', 'CE', 'Other'] as $course) { ?>
                            <option value="<?php echo $course; ?>" <?php echo $courseFilter === $course ? 'selected' : ''; ?>><?php echo $course; ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <?php foreach (['active' => 'Active', 'deleted' => 'Deleted', 'all' => 'All'] as $value => $label) { ?>
                            <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php } ?>
                    </select>
                </label>
                <div>
                    <button type="submit">Search / Filter</button>
                    <a class="button-link secondary" href="admin_students.php">Clear</a>
                    <a class="button-link" href="admin_students.php?<?php echo escapeStudentManagement(http_build_query([...$query, 'export' => 'csv'])); ?>">Export CSV</a>
                </div>
            </form>

            <p><?php echo $totalStudents; ?> student record(s)</p>
            <div class="student-table-wrap">
                <table class="student-table">
                    <thead>
                        <tr><th>Name</th><th>Username / Email</th><th>Mobile</th><th>Course / Year</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($students === []) { ?>
                            <tr><td colspan="6">No students match these filters.</td></tr>
                        <?php } ?>
                        <?php foreach ($students as $student) { ?>
                            <tr>
                                <td><?php echo escapeStudentManagement($student['full_name']); ?></td>
                                <td><?php echo escapeStudentManagement($student['username']); ?><br><?php echo escapeStudentManagement($student['email']); ?></td>
                                <td><?php echo escapeStudentManagement($student['mobile']); ?></td>
                                <td><?php echo escapeStudentManagement($student['course_code']); ?> / <?php echo escapeStudentManagement((string) $student['study_year']); ?></td>
                                <td><?php echo $student['deleted_at'] === null ? 'Active' : 'Deleted'; ?></td>
                                <td>
                                    <?php if ($student['deleted_at'] === null) { ?>
                                        <a class="button-link" href="admin_students.php?edit=<?php echo (int) $student['student_id']; ?>">Edit</a>
                                        <form method="POST" action="admin_students.php" onsubmit="return confirm('Move this student to the deleted list?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeStudentManagement($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?php echo (int) $student['student_id']; ?>">
                                            <button class="danger" type="submit">Delete</button>
                                        </form>
                                    <?php } else { ?>
                                        <form method="POST" action="admin_students.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeStudentManagement($csrfToken); ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="student_id" value="<?php echo (int) $student['student_id']; ?>">
                                            <button type="submit">Restore</button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="student-pagination">
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <span>
                    <?php if ($page > 1) { ?><a class="button-link secondary" href="admin_students.php?<?php echo escapeStudentManagement(http_build_query([...$query, 'page' => $page - 1])); ?>">Previous</a><?php } ?>
                    <?php if ($page < $totalPages) { ?><a class="button-link" href="admin_students.php?<?php echo escapeStudentManagement(http_build_query([...$query, 'page' => $page + 1])); ?>">Next</a><?php } ?>
                </span>
            </div>
        </section>
    </main>
</body>
</html>
