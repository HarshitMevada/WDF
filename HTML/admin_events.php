<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = requireStudentRole('admin');
startStudentHubSession();

const EVENT_POSTER_MAX_BYTES = 3 * 1024 * 1024;
const EVENT_CATEGORIES = ['Technical', 'Sports', 'Cultural', 'Other'];

function escapeEventAdmin(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function removeEventPoster(?string $relativePath): void
{
    if ($relativePath === null
        || !preg_match('#^uploads/event-posters/event_[a-f0-9]{32}\.(jpg|png|webp)$#', $relativePath)) {
        return;
    }

    $path = __DIR__ . '/' . $relativePath;
    $posterDirectory = realpath(__DIR__ . '/uploads/event-posters');
    $realPath = realpath($path);
    if ($posterDirectory !== false
        && $realPath !== false
        && dirname($realPath) === $posterDirectory
        && is_file($realPath)) {
        if (!unlink($realPath)) {
            error_log('Unable to remove replaced Student Hub event poster: ' . $relativePath);
        }
    }
}

function saveEventPoster(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The poster upload failed. Please try again.');
    }
    if (!isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || !is_uploaded_file($file['tmp_name'])
        || (int) $file['size'] < 1
        || (int) $file['size'] > EVENT_POSTER_MAX_BYTES) {
        throw new RuntimeException('Choose an image poster smaller than 3 MB.');
    }

    $image = @getimagesize($file['tmp_name']);
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if ($image === false || !isset($extensions[$mime]) || ($image['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Posters must be valid JPEG, PNG, or WebP images.');
    }

    $directory = __DIR__ . '/uploads/event-posters';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The poster upload directory is unavailable.');
    }

    $filename = 'event_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $relativePath = 'uploads/event-posters/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $relativePath)) {
        throw new RuntimeException('The poster could not be saved. Check the upload directory permissions.');
    }

    return $relativePath;
}

function auditEventChange(
    mysqli $mysqli,
    int $eventId,
    int $adminId,
    string $eventTitle,
    string $action
): void
{
    $statement = $mysqli->prepare(
        'INSERT INTO event_audit (event_id, admin_student_id, event_title, action)
         VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('iiss', $eventId, $adminId, $eventTitle, $action);
    $statement->execute();
    $statement->close();
}

$csrfToken = getStudentHubCsrfToken();
$errors = [];
$message = is_string($_SESSION['event_management_message'] ?? null)
    ? $_SESSION['event_management_message']
    : '';
unset($_SESSION['event_management_message']);
$form = [
    'event_id' => 0,
    'title' => '',
    'category' => 'Technical',
    'event_date' => '',
    'description' => '',
    'status' => 'open',
    'poster_path' => null,
];
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$categoryFilter = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (!in_array($categoryFilter, array_merge([''], EVENT_CATEGORIES), true)) {
    $categoryFilter = '';
}
if (!in_array($statusFilter, ['', 'open', 'closed'], true)) {
    $statusFilter = '';
}

$mysqli = null;
$uploadedPoster = null;
$transactionActive = false;

try {
    $mysqli = getDatabaseConnection();

    if ($editId !== null && $editId !== false && $editId > 0) {
        $editStatement = $mysqli->prepare(
            'SELECT event_id, title, category, event_date, description, status, poster_path
             FROM events WHERE event_id = ?'
        );
        $editStatement->bind_param('i', $editId);
        $editStatement->execute();
        $storedEvent = $editStatement->get_result()->fetch_assoc();
        $editStatement->close();
        if ($storedEvent !== null) {
            $form = $storedEvent;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if (!hash_equals($csrfToken, $submittedToken)) {
            http_response_code(403);
            $errors[] = 'Your session expired. Refresh the page and try again.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

            if ($action === 'delete') {
                if ($eventId === false || $eventId === null || $eventId < 1) {
                    $errors[] = 'Select a valid event.';
                } else {
                    $mysqli->begin_transaction();
                    $transactionActive = true;
                    $deleteStatement = $mysqli->prepare(
                        'SELECT title, poster_path FROM events WHERE event_id = ? FOR UPDATE'
                    );
                    $deleteStatement->bind_param('i', $eventId);
                    $deleteStatement->execute();
                    $eventToDelete = $deleteStatement->get_result()->fetch_assoc();
                    $deleteStatement->close();
                    if ($eventToDelete === null) {
                        throw new RuntimeException('That event no longer exists.');
                    }

                    auditEventChange(
                        $mysqli,
                        $eventId,
                        (int) $admin['student_id'],
                        $eventToDelete['title'],
                        'deleted'
                    );
                    $deleteStatement = $mysqli->prepare('DELETE FROM events WHERE event_id = ?');
                    $deleteStatement->bind_param('i', $eventId);
                    $deleteStatement->execute();
                    $deleteStatement->close();
                    $mysqli->commit();
                    $transactionActive = false;
                    removeEventPoster($eventToDelete['poster_path']);
                    $_SESSION['event_management_message'] = 'Event deleted successfully.';
                    $mysqli->close();
                    $mysqli = null;
                    header('Location: admin_events.php');
                    exit;
                }
            } elseif ($action === 'save') {
                $form = [
                    'event_id' => $eventId !== false && $eventId !== null ? $eventId : 0,
                    'title' => is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '',
                    'category' => is_string($_POST['category'] ?? null) ? trim($_POST['category']) : '',
                    'event_date' => is_string($_POST['event_date'] ?? null) ? trim($_POST['event_date']) : '',
                    'description' => is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '',
                    'status' => is_string($_POST['status'] ?? null) ? $_POST['status'] : '',
                    'poster_path' => null,
                ];

                if (!preg_match('/\A.{2,150}\z/us', $form['title'])) {
                    $errors[] = 'Enter an event title between 2 and 150 characters.';
                }
                if (!in_array($form['category'], EVENT_CATEGORIES, true)) {
                    $errors[] = 'Choose a valid event category.';
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['event_date']);
                if ($date === false || $date->format('Y-m-d') !== $form['event_date']) {
                    $errors[] = 'Enter a valid event date.';
                }
                if (!preg_match('/\A.{1,500}\z/us', $form['description'])) {
                    $errors[] = 'Enter an event description of up to 500 characters.';
                }
                if (!in_array($form['status'], ['open', 'closed'], true)) {
                    $errors[] = 'Choose whether registration is open or closed.';
                }
                if ($eventId === false || $eventId === null) {
                    $errors[] = 'Select a valid event record.';
                }

                if ($errors === []) {
                    $uploadedPoster = saveEventPoster(
                        is_array($_FILES['poster'] ?? null) ? $_FILES['poster'] : []
                    );
                    $mysqli->begin_transaction();
                    $transactionActive = true;
                    $isEditing = (int) $form['event_id'] > 0;
                    $oldPoster = null;

                    if ($isEditing) {
                        $currentStatement = $mysqli->prepare(
                            'SELECT poster_path FROM events WHERE event_id = ? FOR UPDATE'
                        );
                        $currentStatement->bind_param('i', $form['event_id']);
                        $currentStatement->execute();
                        $currentEvent = $currentStatement->get_result()->fetch_assoc();
                        $currentStatement->close();
                        if ($currentEvent === null) {
                            throw new RuntimeException('That event no longer exists.');
                        }
                        $oldPoster = $currentEvent['poster_path'];
                        $posterPath = $uploadedPoster ?? $oldPoster;
                        $saveStatement = $mysqli->prepare(
                            'UPDATE events
                             SET title = ?, category = ?, event_date = ?, description = ?,
                                 status = ?, poster_path = ?
                             WHERE event_id = ?'
                        );
                        $saveStatement->bind_param(
                            'ssssssi',
                            $form['title'],
                            $form['category'],
                            $form['event_date'],
                            $form['description'],
                            $form['status'],
                            $posterPath,
                            $form['event_id']
                        );
                        $saveStatement->execute();
                        $saveStatement->close();
                        auditEventChange(
                            $mysqli,
                            (int) $form['event_id'],
                            (int) $admin['student_id'],
                            $form['title'],
                            'updated'
                        );
                        $savedEventId = (int) $form['event_id'];
                    } else {
                        $posterPath = $uploadedPoster;
                        $saveStatement = $mysqli->prepare(
                            'INSERT INTO events (title, category, event_date, description, status, poster_path)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $saveStatement->bind_param(
                            'ssssss',
                            $form['title'],
                            $form['category'],
                            $form['event_date'],
                            $form['description'],
                            $form['status'],
                            $posterPath
                        );
                        $saveStatement->execute();
                        $savedEventId = (int) $mysqli->insert_id;
                        $saveStatement->close();
                        auditEventChange(
                            $mysqli,
                            $savedEventId,
                            (int) $admin['student_id'],
                            $form['title'],
                            'created'
                        );
                    }

                    $mysqli->commit();
                    $transactionActive = false;
                    if ($uploadedPoster !== null && isset($oldPoster)) {
                        removeEventPoster($oldPoster);
                    }
                    $_SESSION['event_management_message'] = $isEditing
                        ? 'Event updated successfully.'
                        : 'Event created successfully.';
                    $mysqli->close();
                    $mysqli = null;
                    header('Location: admin_events.php');
                    exit;
                }
            } else {
                $errors[] = 'Choose a valid event action.';
            }
        }
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
    if ($categoryFilter !== '') {
        $conditions[] = 'category = ?';
        $parameters[] = $categoryFilter;
        $types .= 's';
    }
    if ($statusFilter !== '') {
        $conditions[] = 'status = ?';
        $parameters[] = $statusFilter;
        $types .= 's';
    }
    $sql = 'SELECT event_id, title, category, event_date, description, status, poster_path
            FROM events'
        . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
        . ' ORDER BY event_date DESC, event_id DESC';
    $listStatement = $mysqli->prepare($sql);
    if ($parameters !== []) {
        $listStatement->bind_param($types, ...$parameters);
    }
    $listStatement->execute();
    $events = $listStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStatement->close();
} catch (mysqli_sql_exception $exception) {
    if ($transactionActive && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    if ($uploadedPoster !== null) {
        removeEventPoster($uploadedPoster);
    }
    error_log('Unable to manage Student Hub events: ' . $exception->getMessage());
    $errors[] = 'Event data could not be saved or loaded. Please try again.';
    $events = [];
} catch (RuntimeException $exception) {
    if ($transactionActive && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    if ($uploadedPoster !== null) {
        removeEventPoster($uploadedPoster);
    }
    $errors[] = $exception->getMessage();
} catch (Exception $exception) {
    if ($transactionActive && $mysqli instanceof mysqli) {
        $mysqli->rollback();
    }
    if ($uploadedPoster !== null) {
        removeEventPoster($uploadedPoster);
    }
    error_log('Unexpected Student Hub event management failure: ' . $exception->getMessage());
    $errors[] = 'An unexpected error occurred while managing events.';
    $events = [];
} finally {
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | Student Hub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="body">
    <nav class="nav">
        <a href="admin_dashboard.php">Admin Dashboard</a>
        <a href="admin_students.php">Manage Students</a>
        <a href="event_calendar.html">Public Event Calendar</a>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?php echo escapeEventAdmin($csrfToken); ?>">
            <button type="submit">Log out</button>
        </form>
    </nav>
    <main class="dashboard">
        <section class="card">
            <h1>Manage Events</h1>
            <p>Create events, update event details and posters, or delete outdated events.</p>
            <?php if ($message !== '') { ?><p role="status"><?php echo escapeEventAdmin($message); ?></p><?php } ?>
            <?php foreach ($errors as $error) { ?><p class="event-error" role="alert"><?php echo escapeEventAdmin($error); ?></p><?php } ?>
            <form method="POST" enctype="multipart/form-data" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo escapeEventAdmin($csrfToken); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="event_id" value="<?php echo (int) $form['event_id']; ?>">
                <h2><?php echo (int) $form['event_id'] > 0 ? 'Edit Event' : 'Add Event'; ?></h2>
                <label for="title">Title</label>
                <input id="title" name="title" maxlength="150" required value="<?php echo escapeEventAdmin((string) $form['title']); ?>">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach (EVENT_CATEGORIES as $category) { ?>
                        <option value="<?php echo escapeEventAdmin($category); ?>" <?php echo $form['category'] === $category ? 'selected' : ''; ?>>
                            <?php echo escapeEventAdmin($category); ?>
                        </option>
                    <?php } ?>
                </select>
                <label for="event_date">Event date</label>
                <input id="event_date" type="date" name="event_date" required value="<?php echo escapeEventAdmin((string) $form['event_date']); ?>">
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="500" required><?php echo escapeEventAdmin((string) $form['description']); ?></textarea>
                <label for="status">Registration status</label>
                <select id="status" name="status" required>
                    <option value="open" <?php echo $form['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="closed" <?php echo $form['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                <label for="poster">Poster (JPEG, PNG, or WebP; max 3 MB)</label>
                <input id="poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                <?php if (is_string($form['poster_path']) && $form['poster_path'] !== '') { ?>
                    <p>Current poster: <a href="<?php echo escapeEventAdmin($form['poster_path']); ?>" target="_blank" rel="noopener">View poster</a></p>
                <?php } ?>
                <button type="submit"><?php echo (int) $form['event_id'] > 0 ? 'Save Changes' : 'Create Event'; ?></button>
                <?php if ((int) $form['event_id'] > 0) { ?><a href="admin_events.php">Cancel edit</a><?php } ?>
            </form>
        </section>
        <section class="card">
            <h2>Event List</h2>
            <form method="GET" class="event-tools">
                <input type="search" name="q" placeholder="Search title or description" value="<?php echo escapeEventAdmin($search); ?>">
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach (EVENT_CATEGORIES as $category) { ?>
                        <option value="<?php echo escapeEventAdmin($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                            <?php echo escapeEventAdmin($category); ?>
                        </option>
                    <?php } ?>
                </select>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                <button type="submit">Filter</button>
                <a href="admin_events.php">Clear</a>
            </form>
            <?php if (($events ?? []) === []) { ?>
                <p>No events match these filters.</p>
            <?php } else { ?>
                <div class="admin-event-list">
                    <?php foreach ($events as $event) { ?>
                        <article class="card">
                            <h3><?php echo escapeEventAdmin($event['title']); ?></h3>
                            <p><?php echo escapeEventAdmin($event['category']); ?> ·
                                <?php echo escapeEventAdmin($event['event_date']); ?> ·
                                <?php echo escapeEventAdmin(ucfirst($event['status'])); ?></p>
                            <p><?php echo escapeEventAdmin($event['description']); ?></p>
                            <?php if (is_string($event['poster_path']) && $event['poster_path'] !== '') { ?>
                                <img class="admin-event-poster" src="<?php echo escapeEventAdmin($event['poster_path']); ?>" alt="Poster for <?php echo escapeEventAdmin($event['title']); ?>">
                            <?php } ?>
                            <a href="admin_events.php?edit=<?php echo (int) $event['event_id']; ?>">Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete this event? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo escapeEventAdmin($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?php echo (int) $event['event_id']; ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </main>
</body>
</html>
