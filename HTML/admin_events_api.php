<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/event_management.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respondEventApi(int $status, bool $success, string $message, mixed $data = null): never
{
    http_response_code($status);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function eventApiInput(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
}

function validateEventApiInput(array $input): array
{
    $errors = [];
    if (!preg_match('/\A.{2,150}\z/us', $input['title'])) {
        $errors[] = 'Enter an event title between 2 and 150 characters.';
    }
    if (!in_array($input['category'], EVENT_CATEGORIES, true)) {
        $errors[] = 'Choose a valid event category.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input['event_date']);
    if ($date === false || $date->format('Y-m-d') !== $input['event_date']) {
        $errors[] = 'Enter a valid event date.';
    }
    if (!preg_match('/\A.{1,500}\z/us', $input['description'])) {
        $errors[] = 'Enter an event description of up to 500 characters.';
    }
    if (!in_array($input['status'], ['open', 'closed'], true)) {
        $errors[] = 'Choose whether registration is open or closed.';
    }

    return $errors;
}

$mysqli = null;
$uploadedPoster = null;
$posterToRemove = null;
$transactionActive = false;

try {
    $student = getAuthenticatedStudent();
    if ($student === null) {
        respondEventApi(401, false, 'Please sign in to manage events.');
    }
    if ($student['role'] !== 'admin') {
        respondEventApi(403, false, 'Administrator access is required.');
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        respondEventApi(405, false, 'Only GET and POST requests are supported.');
    }

    $mysqli = getDatabaseConnection();
    if ($method === 'GET') {
        $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
        if ($category !== '' && !in_array($category, EVENT_CATEGORIES, true)) {
            respondEventApi(400, false, 'Choose a valid event category.');
        }
        if ($status !== '' && !in_array($status, ['open', 'closed'], true)) {
            respondEventApi(400, false, 'Choose a valid event status.');
        }

        $conditions = [];
        $parameters = [];
        $types = '';
        if ($search !== '') {
            $conditions[] = '(title LIKE ? OR description LIKE ?)';
            $like = '%' . $search . '%';
            $parameters[] = $like;
            $parameters[] = $like;
            $types .= 'ss';
        }
        if ($category !== '') {
            $conditions[] = 'category = ?';
            $parameters[] = $category;
            $types .= 's';
        }
        if ($status !== '') {
            $conditions[] = 'status = ?';
            $parameters[] = $status;
            $types .= 's';
        }

        $sql = 'SELECT event_id AS id, title, category, event_date AS date,
                       description, status, poster_path
                FROM events'
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . ' ORDER BY event_date DESC, event_id DESC';
        $statement = $mysqli->prepare($sql);
        if ($parameters !== []) {
            $statement->bind_param($types, ...$parameters);
        }
        $statement->execute();
        $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
        respondEventApi(200, true, 'Events loaded.', $events);
    }

    $submittedToken = eventApiInput('csrf_token');
    $expectedToken = getStudentHubCsrfToken();
    if (!hash_equals($expectedToken, $submittedToken)) {
        respondEventApi(403, false, 'Your session expired. Refresh and try again.');
    }

    $action = eventApiInput('action');
    $rawId = eventApiInput('event_id');
    $eventId = filter_var($rawId, FILTER_VALIDATE_INT);
    if ($rawId === '' || $eventId === false || $eventId < 0) {
        respondEventApi(422, false, 'Select a valid event record.');
    }

    if ($action === 'delete') {
        if ($eventId < 1) {
            respondEventApi(422, false, 'Select a valid event to delete.');
        }

        $mysqli->begin_transaction();
        $transactionActive = true;
        $statement = $mysqli->prepare(
            'SELECT title, poster_path FROM events WHERE event_id = ? FOR UPDATE'
        );
        $statement->bind_param('i', $eventId);
        $statement->execute();
        $event = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($event === null) {
            $mysqli->rollback();
            $transactionActive = false;
            respondEventApi(404, false, 'That event no longer exists.');
        }

        auditEventChange($mysqli, $eventId, (int) $student['student_id'], $event['title'], 'deleted');
        $statement = $mysqli->prepare('DELETE FROM events WHERE event_id = ?');
        $statement->bind_param('i', $eventId);
        $statement->execute();
        $statement->close();
        $mysqli->commit();
        $transactionActive = false;
        removeEventPoster($event['poster_path']);
        respondEventApi(200, true, 'Event deleted successfully.');
    }

    if (!in_array($action, ['create', 'update'], true)) {
        respondEventApi(400, false, 'Choose a valid event action.');
    }
    if (($action === 'create' && $eventId !== 0) || ($action === 'update' && $eventId < 1)) {
        respondEventApi(422, false, 'The event action does not match the selected event.');
    }

    $input = [
        'title' => eventApiInput('title'),
        'category' => eventApiInput('category'),
        'event_date' => eventApiInput('event_date'),
        'description' => eventApiInput('description'),
        'status' => eventApiInput('status'),
    ];
    $validationErrors = validateEventApiInput($input);
    if ($validationErrors !== []) {
        respondEventApi(422, false, implode(' ', $validationErrors), ['errors' => $validationErrors]);
    }

    $uploadedPoster = saveEventPoster(is_array($_FILES['poster'] ?? null) ? $_FILES['poster'] : []);
    $mysqli->begin_transaction();
    $transactionActive = true;
    if ($action === 'update') {
        $statement = $mysqli->prepare(
            'SELECT poster_path FROM events WHERE event_id = ? FOR UPDATE'
        );
        $statement->bind_param('i', $eventId);
        $statement->execute();
        $currentEvent = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($currentEvent === null) {
            $mysqli->rollback();
            $transactionActive = false;
            removeEventPoster($uploadedPoster);
            $uploadedPoster = null;
            respondEventApi(404, false, 'That event no longer exists.');
        }

        $posterToRemove = $uploadedPoster !== null ? $currentEvent['poster_path'] : null;
        $posterPath = $uploadedPoster ?? $currentEvent['poster_path'];
        $statement = $mysqli->prepare(
            'UPDATE events
             SET title = ?, category = ?, event_date = ?, description = ?,
                 status = ?, poster_path = ?
             WHERE event_id = ?'
        );
        $statement->bind_param(
            'ssssssi',
            $input['title'],
            $input['category'],
            $input['event_date'],
            $input['description'],
            $input['status'],
            $posterPath,
            $eventId
        );
        $statement->execute();
        $statement->close();
        auditEventChange($mysqli, $eventId, (int) $student['student_id'], $input['title'], 'updated');
        $savedId = $eventId;
    } else {
        $posterPath = $uploadedPoster;
        $statement = $mysqli->prepare(
            'INSERT INTO events (title, category, event_date, description, status, poster_path)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param(
            'ssssss',
            $input['title'],
            $input['category'],
            $input['event_date'],
            $input['description'],
            $input['status'],
            $posterPath
        );
        $statement->execute();
        $savedId = (int) $mysqli->insert_id;
        $statement->close();
        auditEventChange($mysqli, $savedId, (int) $student['student_id'], $input['title'], 'created');
    }

    $mysqli->commit();
    $transactionActive = false;
    if ($posterToRemove !== null) {
        removeEventPoster($posterToRemove);
    }

    $statement = $mysqli->prepare(
        'SELECT event_id AS id, title, category, event_date AS date,
                description, status, poster_path
         FROM events WHERE event_id = ?'
    );
    $statement->bind_param('i', $savedId);
    $statement->execute();
    $savedEvent = $statement->get_result()->fetch_assoc();
    $statement->close();
    respondEventApi(
        $action === 'create' ? 201 : 200,
        true,
        $action === 'create' ? 'Event created successfully.' : 'Event updated successfully.',
        $savedEvent
    );
} catch (mysqli_sql_exception $exception) {
    if ($transactionActive && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    if ($uploadedPoster !== null) {
        removeEventPoster($uploadedPoster);
    }
    error_log('Student Hub event API database error: ' . $exception->getMessage());
    respondEventApi(503, false, 'Event data is temporarily unavailable. Please try again.');
} catch (RuntimeException $exception) {
    if ($transactionActive && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    if ($uploadedPoster !== null) {
        removeEventPoster($uploadedPoster);
    }
    respondEventApi(422, false, $exception->getMessage());
} catch (JsonException $exception) {
    error_log('Student Hub event API JSON error: ' . $exception->getMessage());
    http_response_code(500);
    echo '{"success":false,"message":"The server could not prepare a valid response."}';
} catch (Throwable $exception) {
    if ($transactionActive && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    if ($uploadedPoster !== null) {
        removeEventPoster($uploadedPoster);
    }
    error_log('Unexpected Student Hub event API error: ' . $exception->getMessage());
    http_response_code(500);
    echo '{"success":false,"message":"An unexpected server error occurred."}';
} finally {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}
