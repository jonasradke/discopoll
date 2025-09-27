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

// Get filter parameter
$filter = $_GET['filter'] ?? 'active';

// Fetch polls based on filter
if ($filter === 'archived') {
    $stmt = $pdo->query("SELECT * FROM polls WHERE archived = 1 ORDER BY created_at DESC");
} elseif ($filter === 'all') {
    $stmt = $pdo->query("SELECT * FROM polls ORDER BY created_at DESC");
} else {
    // Default: active polls
    $stmt = $pdo->query("SELECT * FROM polls WHERE archived = 0 ORDER BY created_at DESC");
}
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
    <style>
        /* Mobile button improvements */
        @media (max-width: 767.98px) {
            .btn-group-vertical .btn {
                min-width: 120px;
                text-align: left;
                white-space: nowrap;
            }
            
            .table td {
                vertical-align: middle;
            }
            
            /* Ensure action column doesn't get too narrow */
            .table th:last-child,
            .table td:last-child {
                min-width: 140px;
            }
        }
        
        /* Desktop button spacing */
        @media (min-width: 768px) {
            .d-md-flex.gap-1 > * {
                margin-right: 0.25rem;
            }
            
            .d-md-flex.gap-1 > *:last-child {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">Admin Dashboard</h2>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
        <a href="create_poll" class="btn btn-success">
            ➕ Neue Umfrage Erstellen
        </a>
        <a href="../presentation" class="btn btn-dark" target="_blank" title="Alle aktiven Umfragen im Präsentationsmodus">
            📊 Präsentationsmodus
        </a>
        <a href="logout" class="btn btn-danger">
            🚪 Logout
        </a>
    </div>
    
    <!-- Filter buttons -->
    <div class="mb-3">
        <div class="btn-group d-none d-sm-flex" role="group" aria-label="Poll filter">
            <a href="dashboard?filter=active" class="btn <?php echo $filter === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Aktive Umfragen
            </a>
            <a href="dashboard?filter=archived" class="btn <?php echo $filter === 'archived' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Archivierte Umfragen
            </a>
            <a href="dashboard?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Alle Umfragen
            </a>
        </div>
        
        <!-- Mobile filter buttons -->
        <div class="d-flex d-sm-none flex-column gap-2">
            <a href="dashboard?filter=active" class="btn <?php echo $filter === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                ✅ Aktive Umfragen
            </a>
            <a href="dashboard?filter=archived" class="btn <?php echo $filter === 'archived' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                📦 Archivierte Umfragen
            </a>
            <a href="dashboard?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                📋 Alle Umfragen
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Frage</th>
                    <th>Status</th>
                    <th>Erstellt am</th>
                    <th style="min-width: 200px;">Aktionen</th>
                </tr>
            </thead>
        <tbody>
        <?php foreach ($polls as $poll): ?>
            <tr>
                <td><?php echo $poll['id']; ?></td>
                <td><?php echo htmlspecialchars($poll['question']); ?></td>
                <td>
                    <?php if ($poll['archived']): ?>
                        <span class="badge bg-secondary">Archiviert</span>
                    <?php else: ?>
                        <span class="badge bg-success">Aktiv</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $poll['created_at']; ?></td>
                <td>
                    <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                        <!-- Mobile: Vertical button group -->
                        <a href="view_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-info mb-1">
                           👁️ Anzeigen
                        </a>
                        <a href="../presentation?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-dark mb-1" 
                           target="_blank"
                           title="Präsentationsmodus öffnen">
                           📊 Präsentation
                        </a>
                        <a href="edit_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-primary mb-1">
                           ✏️ Bearbeiten
                        </a>
                        <?php if ($poll['archived']): ?>
                            <a href="archive_poll?id=<?php echo $poll['id']; ?>&action=unarchive" 
                               class="btn btn-success mb-1"
                               onclick="return confirm('Umfrage wieder aktivieren?');">
                               ✅ Aktivieren
                            </a>
                        <?php else: ?>
                            <a href="archive_poll?id=<?php echo $poll['id']; ?>&action=archive" 
                               class="btn btn-warning mb-1"
                               onclick="return confirm('Umfrage archivieren? Sie wird nicht mehr öffentlich sichtbar sein.');">
                               📦 Archivieren
                            </a>
                        <?php endif; ?>
                        <a href="delete_poll?id=<?php echo $poll['id']; ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Sicher das du die Umfrage löschen möchtest? Die Frisbeegötter werden es dir verzeihen.');">
                           🗑️ Löschen
                        </a>
                    </div>
                    
                    <div class="d-none d-md-flex flex-wrap gap-1" role="group">
                        <!-- Desktop: Horizontal layout with flex wrap -->
                        <a href="view_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-sm btn-info">
                           Anzeigen
                        </a>
                        <a href="../presentation?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-sm btn-dark" 
                           target="_blank"
                           title="Präsentationsmodus öffnen">
                           Präsentation
                        </a>
                        <a href="edit_poll?id=<?php echo $poll['id']; ?>" 
                           class="btn btn-sm btn-primary">
                           Bearbeiten
                        </a>
                        <?php if ($poll['archived']): ?>
                            <a href="archive_poll?id=<?php echo $poll['id']; ?>&action=unarchive" 
                               class="btn btn-sm btn-success"
                               onclick="return confirm('Umfrage wieder aktivieren?');">
                               Aktivieren
                            </a>
                        <?php else: ?>
                            <a href="archive_poll?id=<?php echo $poll['id']; ?>&action=archive" 
                               class="btn btn-sm btn-warning"
                               onclick="return confirm('Umfrage archivieren? Sie wird nicht mehr öffentlich sichtbar sein.');">
                               Archivieren
                            </a>
                        <?php endif; ?>
                        <a href="delete_poll?id=<?php echo $poll['id']; ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Sicher das du die Umfrage löschen möchtest? Die Frisbeegötter werden es dir verzeihen.');">
                           Löschen
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
