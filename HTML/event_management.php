<?php
declare(strict_types=1);

const EVENT_POSTER_MAX_BYTES = 3 * 1024 * 1024;
const EVENT_CATEGORIES = ['Technical', 'Sports', 'Cultural', 'Other'];

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
        && is_file($realPath)
        && !unlink($realPath)) {
        error_log('Unable to remove Student Hub event poster: ' . $relativePath);
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
): void {
    $statement = $mysqli->prepare(
        'INSERT INTO event_audit (event_id, admin_student_id, event_title, action)
         VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('iiss', $eventId, $adminId, $eventTitle, $action);
    $statement->execute();
    $statement->close();
}
