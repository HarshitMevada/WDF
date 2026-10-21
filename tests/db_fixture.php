<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../HTML/db.php';

$action = $argv[1] ?? '';
$username = $argv[2] ?? '';
if (!preg_match('/\Atest_(?:admin|student)_[a-f0-9]{12}\z/', $username)) {
    fwrite(STDERR, "Invalid integration-test username.\n");
    exit(2);
}

$mysqli = getDatabaseConnection();
try {
    if ($action === 'create-admin') {
        $password = getenv('STUDENTHUB_TEST_PASSWORD');
        if (!is_string($password) || $password === '') {
            throw new RuntimeException('A temporary test password is required.');
        }

        $name = 'Practical 15 Test Admin';
        $email = $username . '@example.test';
        $mobile = (string) random_int(7000000000, 7999999999);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $mysqli->prepare(
            "INSERT INTO students (full_name, username, email, mobile, password_hash, gender, role)
             VALUES (?, ?, ?, ?, ?, 'Other', 'admin')"
        );
        $statement->bind_param('sssss', $name, $username, $email, $mobile, $passwordHash);
        $statement->execute();
        $statement->close();
    } elseif ($action === 'delete-test-user') {
        $statement = $mysqli->prepare('DELETE FROM students WHERE username = ?');
        $statement->bind_param('s', $username);
        $statement->execute();
        $statement->close();
    } elseif ($action === 'cleanup-admin') {
        $eventPrefix = $argv[3] ?? '';
        if (!str_starts_with($username, 'test_admin_')
            || !preg_match('/\ATEST-P15-[a-f0-9]{12}\z/', $eventPrefix)) {
            throw new RuntimeException('Invalid administrator integration-test cleanup scope.');
        }

        $statement = $mysqli->prepare(
            'DELETE FROM events WHERE LEFT(title, CHAR_LENGTH(?)) = ?'
        );
        $statement->bind_param('ss', $eventPrefix, $eventPrefix);
        $statement->execute();
        $statement->close();

        $statement = $mysqli->prepare('DELETE FROM students WHERE username = ?');
        $statement->bind_param('s', $username);
        $statement->execute();
        $statement->close();
    } else {
        fwrite(STDERR, "Unsupported integration-test fixture action.\n");
        exit(2);
    }
} finally {
    $mysqli->close();
}
