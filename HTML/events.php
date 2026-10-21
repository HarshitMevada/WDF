<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Only GET requests are supported.']);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $mysqli = getDatabaseConnection();
    $statement = $mysqli->prepare(
        'SELECT event_id AS id, title, category, event_date AS date, description
         FROM events
         ORDER BY event_id'
    );
    $statement->execute();
    $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    echo json_encode($events, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (mysqli_sql_exception $exception) {
    error_log('Unable to load Student Hub events: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Event data is temporarily unavailable.']);
} catch (JsonException $exception) {
    error_log('Unable to encode Student Hub events: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Event data could not be prepared.']);
} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}
