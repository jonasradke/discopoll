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
                        <button class="btn btn-secondary mb-1" 
                                onclick="showQRCode(<?php echo $poll['id']; ?>, '<?php echo htmlspecialchars($poll['question']); ?>')"
                                title="QR-Code anzeigen">
                           📱 QR-Code
                        </button>
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
                        <button class="btn btn-sm btn-secondary" 
                                onclick="showQRCode(<?php echo $poll['id']; ?>, '<?php echo htmlspecialchars($poll['question']); ?>')"
                                title="QR-Code anzeigen">
                           QR-Code
                        </button>
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

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalLabel">QR-Code für Umfrage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContainer">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Lädt...</span>
                    </div>
                </div>
                <div class="mt-3">
                    <h6 id="pollQuestion"></h6>
                    <p class="text-muted mb-3">Scannen Sie diesen QR-Code, um zur Umfrage zu gelangen</p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="pollUrl" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard()">
                            📋 Kopieren
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                <button type="button" class="btn btn-primary" onclick="downloadQRCode()">📥 Herunterladen</button>
            </div>
        </div>
    </div>
</div>

<script>
function showQRCode(pollId, question) {
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    const pollUrl = `${window.location.origin}/?id=${pollId}`;
    
    // Update modal content
    document.getElementById('pollQuestion').textContent = question;
    document.getElementById('pollUrl').value = pollUrl;
    
    // Generate QR code
    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(pollUrl)}`;
    document.getElementById('qrCodeContainer').innerHTML = `<img src="${qrCodeUrl}" alt="QR Code" class="img-fluid" style="max-width: 300px;">`;
    
    // Show modal
    modal.show();
}

function copyToClipboard() {
    const urlInput = document.getElementById('pollUrl');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        // Show success feedback
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✅ Kopiert!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
        alert('URL konnte nicht kopiert werden. Bitte manuell kopieren.');
    }
}

function downloadQRCode() {
    const qrCodeImg = document.querySelector('#qrCodeContainer img');
    if (qrCodeImg) {
        const link = document.createElement('a');
        link.download = `qr-code-umfrage-${document.getElementById('pollQuestion').textContent.substring(0, 20)}.png`;
        link.href = qrCodeImg.src;
        link.click();
    }
}
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
