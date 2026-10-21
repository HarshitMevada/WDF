<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

startStudentHubSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Logout must be submitted as a POST request.');
}

$expectedToken = $_SESSION['auth_csrf_token'] ?? '';
$submittedToken = is_string($_POST['csrf_token'] ?? null)
    ? $_POST['csrf_token']
    : '';

if (!is_string($expectedToken) || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    http_response_code(403);
    exit('Invalid logout request. Refresh the page and try again.');
}

$studentId = (int) ($_SESSION['student_id'] ?? 0);
if ($studentId > 0) {
    $mysqli = getDatabaseConnection();
    try {
        $statement = $mysqli->prepare('DELETE FROM remember_tokens WHERE student_id = ?');
        $statement->bind_param('i', $studentId);
        $statement->execute();
        $statement->close();
    } finally {
        $mysqli->close();
    }
}

clearRememberCookie();
clearStudentHubAuthentication();
header('Location: login.php?logged_out=1');
exit;
