<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/event_management.php';

requireStudentRole('admin');
$csrfToken = getStudentHubCsrfToken();

function escapeEventAdmin(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | Student Hub</title>
    <link rel="stylesheet" href="style.css?v=20261022-1827">
    <script src="admin_events.js" defer></script>
</head>
<body class="body admin-events-page">
    <nav class="nav">
        <?php foreach (studentHubNavigationLinks('admin') as $label => $href) { ?>
            <a href="<?php echo escapeEventAdmin($href); ?>"><?php echo escapeEventAdmin($label); ?></a>
        <?php } ?>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?php echo escapeEventAdmin($csrfToken); ?>">
            <button type="submit">Log out</button>
        </form>
    </nav>
    <main class="dashboard">
        <section class="card">
            <h1>Manage Events</h1>
            <p>Create events, update event details and posters, or delete outdated events.</p>
            <p id="eventAdminMessage" role="status" aria-live="polite">Loading events...</p>
            <form id="eventForm" method="POST" action="admin_events_api.php" enctype="multipart/form-data" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo escapeEventAdmin($csrfToken); ?>">
                <input type="hidden" name="action" id="eventAction" value="create">
                <input type="hidden" name="event_id" id="eventId" value="0">
                <h2 id="eventFormHeading">Add Event</h2>
                <label for="title">Title</label>
                <input id="title" name="title" maxlength="150" minlength="2" required>
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach (EVENT_CATEGORIES as $category) { ?>
                        <option value="<?php echo escapeEventAdmin($category); ?>">
                            <?php echo escapeEventAdmin($category); ?>
                        </option>
                    <?php } ?>
                </select>
                <label for="eventDate">Event date</label>
                <input id="eventDate" type="date" name="event_date" required>
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="500" required></textarea>
                <label for="eventStatus">Registration status</label>
                <select id="eventStatus" name="status" required>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                </select>
                <label for="poster">Poster (JPEG, PNG, or WebP; max 3 MB)</label>
                <input id="poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp">
                <p id="currentPoster" hidden></p>
                <button id="saveEventButton" type="submit">Create Event</button>
                <button id="cancelEditButton" type="button" hidden>Cancel Edit</button>
            </form>
        </section>
        <section class="card">
            <h2>Event List</h2>
            <div class="event-tools">
                <input id="eventAdminSearch" type="search" placeholder="Search title or description" aria-label="Search events">
                <select id="eventAdminCategory" aria-label="Filter events by category">
                    <option value="">All categories</option>
                    <?php foreach (EVENT_CATEGORIES as $category) { ?>
                        <option value="<?php echo escapeEventAdmin($category); ?>">
                            <?php echo escapeEventAdmin($category); ?>
                        </option>
                    <?php } ?>
                </select>
                <select id="eventAdminStatus" aria-label="Filter events by registration status">
                    <option value="">All statuses</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div id="adminEventList" class="admin-event-list" aria-busy="true">
                <p>Loading events...</p>
            </div>
        </section>
    </main>
</body>
</html>
