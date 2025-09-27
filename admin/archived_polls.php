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

// Fetch all archived polls
$stmt = $pdo->query("SELECT * FROM polls WHERE archived = 1 ORDER BY created_at DESC");
$archivedPolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archivierte Umfragen</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><!-- Mobile responsive -->
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">Archivierte Umfragen</h2>
    <a href="dashboard" class="btn btn-primary mb-3">Zurück zum Dashboard</a>

    <?php if (count($archivedPolls) === 0): ?>
        <div class="alert alert-info">Keine archivierten Umfragen vorhanden.</div>
    <?php else: ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Frage</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($archivedPolls as $poll): ?>
                <tr class="table-secondary">
                    <td><?php echo $poll['id']; ?></td>
                    <td><?php echo htmlspecialchars($poll['question']); ?></td>
                    <td><?php echo $poll['created_at']; ?></td>
                    <td>
                        <a href="unarchive_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-sm btn-warning"
                           onclick="return confirm('Umfrage wiederherstellen?');">
                           Wiederherstellen
                        </a>
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
    <?php endif; ?>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
