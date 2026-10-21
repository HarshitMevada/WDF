<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const STUDENTHUB_SESSION_TIMEOUT = 1800;
const STUDENTHUB_REMEMBER_COOKIE = 'studenthub_remember';
const STUDENTHUB_REMEMBER_DAYS = 30;

function startStudentHubSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function studentHubRedirectForRole(string $role): never
{
    header('Location: ' . ($role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));
    exit;
}

function setRememberCookie(string $token, int $expiresAt): void
{
    setcookie(STUDENTHUB_REMEMBER_COOKIE, $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie(): void
{
    setcookie(STUDENTHUB_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[STUDENTHUB_REMEMBER_COOKIE]);
}

function rememberStudent(mysqli $mysqli, int $studentId): void
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = time() + STUDENTHUB_REMEMBER_DAYS * 86400;
    $expiresAtSql = date('Y-m-d H:i:s', $expiresAt);

    $statement = $mysqli->prepare(
        'INSERT INTO remember_tokens (student_id, token_hash, expires_at)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)'
    );
    $statement->bind_param('iss', $studentId, $tokenHash, $expiresAtSql);
    $statement->execute();
    $statement->close();

    setRememberCookie($token, $expiresAt);
}

function clearStudentHubAuthentication(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function getAuthenticatedStudent(): ?array
{
    startStudentHubSession();

    if (isset($_SESSION['student_id'], $_SESSION['last_activity'])) {
        if (time() - (int) $_SESSION['last_activity'] > STUDENTHUB_SESSION_TIMEOUT) {
            clearStudentHubAuthentication();
            clearRememberCookie();
            return null;
        }

        $studentId = (int) $_SESSION['student_id'];
        $mysqli = getDatabaseConnection();
        try {
            $statement = $mysqli->prepare(
                'SELECT student_id, full_name, username, role
                 FROM students WHERE student_id = ? AND deleted_at IS NULL'
            );
            $statement->bind_param('i', $studentId);
            $statement->execute();
            $student = $statement->get_result()->fetch_assoc() ?: null;
            $statement->close();
        } finally {
            $mysqli->close();
        }

        if ($student === null) {
            clearStudentHubAuthentication();
            clearRememberCookie();
            return null;
        }

        $_SESSION['role'] = $student['role'];
        $_SESSION['last_activity'] = time();
        return $student;
    }

    $token = $_COOKIE[STUDENTHUB_REMEMBER_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $mysqli = getDatabaseConnection();
    try {
        $tokenHash = hash('sha256', $token);
        $statement = $mysqli->prepare(
            'SELECT s.student_id, s.full_name, s.username, s.role
             FROM remember_tokens AS t
             INNER JOIN students AS s ON s.student_id = t.student_id
             WHERE t.token_hash = ? AND t.expires_at > CURRENT_TIMESTAMP
               AND s.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->bind_param('s', $tokenHash);
        $statement->execute();
        $student = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if ($student === null) {
            clearRememberCookie();
            return null;
        }

        session_regenerate_id(true);
        $_SESSION['student_id'] = (int) $student['student_id'];
        $_SESSION['role'] = $student['role'];
        $_SESSION['last_activity'] = time();
        rememberStudent($mysqli, (int) $student['student_id']);
        return $student;
    } finally {
        $mysqli->close();
    }
}

function requireAuthenticatedStudent(): array
{
    $student = getAuthenticatedStudent();
    if ($student === null) {
        header('Location: login.php');
        exit;
    }

    return $student;
}

function requireStudentRole(string $role): array
{
    $student = requireAuthenticatedStudent();
    if ($student['role'] !== $role) {
        studentHubRedirectForRole($student['role']);
    }

    return $student;
}

function getStudentHubCsrfToken(): string
{
    startStudentHubSession();
    if (!isset($_SESSION['auth_csrf_token'])) {
        $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['auth_csrf_token'];
}
