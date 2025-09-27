<?php
include_once '../config.php';
include '../partials/header.php';

// Send no-cache headers
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Fetch all polls
$stmt = $pdo->query("SELECT * FROM polls ORDER BY created_at DESC");
$polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><!-- Mobile responsive -->
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">Admin Dashboard</h2>
    <a href="create_poll" class="btn btn-success mb-3">Neue Umfrage Erstellen</a>
    <a href="archived_polls" class="btn btn-secondary mb-3">Archivierte Umfragen</a>
    <a href="logout" class="btn btn-danger mb-3">Logout</a>

    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Frage</th>
                <th>Erstellt am</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($polls as $poll): ?>
            <tr class="<?php echo $poll['archived'] ? 'table-secondary' : ''; ?>">
                <td><?php echo $poll['id']; ?></td>
                <td><?php echo htmlspecialchars($poll['question']); ?></td>
                <td><?php echo $poll['created_at']; ?></td>
                <td>
                    <?php if ($poll['archived']): ?>
                        <span class="badge bg-secondary">Archiviert</span>
                    <?php else: ?>
                        <span class="badge bg-success">Aktiv</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="edit_poll?id=<?php echo $poll['id']; ?>" 
                       class="btn btn-sm btn-primary">
                       Bearbeiten
                    </a>
                    <?php if ($poll['archived']): ?>
                        <a href="unarchive_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-sm btn-warning"
                           onclick="return confirm('Umfrage wiederherstellen?');">
                           Wiederherstellen
                        </a>
                    <?php else: ?>
                        <a href="archive_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-sm btn-secondary"
                           onclick="return confirm('Umfrage archivieren? Sie wird nicht mehr für Abstimmungen angezeigt.');">
                           Archivieren
                        </a>
                    <?php endif; ?>
                    <a href="delete_poll?id=<?php echo $poll['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Sicher das du die Umfrage löschen möchtest? Die Frisbeegötter werden es dir verzeihen.');">
                       Löschen
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
