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

// Get poll ID from query string
if (!isset($_GET['id'])) {
    die("No poll ID provided.");
}
$pollId = (int)$_GET['id'];

// Fetch the poll
$stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id");
$stmt->execute([':id' => $pollId]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$poll) {
    die("Poll not found.");
}

// Fetch poll options with vote counts
$stmtOptions = $pdo->prepare("
    SELECT po.id, po.option_text, COUNT(pv.id) as votes
    FROM poll_options po
    LEFT JOIN poll_votes pv ON pv.option_id = po.id
    WHERE po.poll_id = :poll_id
    GROUP BY po.id, po.option_text
    ORDER BY po.id ASC
");
$stmtOptions->execute([':poll_id' => $pollId]);
$options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

// Calculate total votes
$totalVotes = array_sum(array_column($options, 'votes'));

// Get recent votes for activity log
$stmtRecentVotes = $pdo->prepare("
    SELECT pv.voted_at, po.option_text
    FROM poll_votes pv
    JOIN poll_options po ON pv.option_id = po.id
    WHERE pv.poll_id = :poll_id
    ORDER BY pv.voted_at DESC
    LIMIT 10
");
$stmtRecentVotes->execute([':poll_id' => $pollId]);
$recentVotes = $stmtRecentVotes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Umfrage Anzeigen - <?php echo htmlspecialchars($poll['question']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Chart.js for better visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Umfrage Anzeigen</h2>
                <div>
                    <a href="edit_poll?id=<?php echo $pollId; ?>" class="btn btn-primary">Bearbeiten</a>
                    <a href="dashboard" class="btn btn-secondary">Zurück zum Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Poll Details Card -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Umfrage Details</h5>
                    <?php if ($poll['archived']): ?>
                        <span class="badge bg-secondary fs-6">Archiviert</span>
                    <?php else: ?>
                        <span class="badge bg-success fs-6">Aktiv</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <h4 class="card-title mb-3"><?php echo htmlspecialchars($poll['question']); ?></h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <?php echo $poll['id']; ?></p>
                            <p><strong>Erstellt am:</strong> <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Gesamtstimmen:</strong> <?php echo $totalVotes; ?></p>
                            <p><strong>Anzahl Optionen:</strong> <?php echo count($options); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Statistiken</h5>
                </div>
                <div class="card-body">
                    <?php if ($totalVotes > 0): ?>
                        <?php
                        $topOption = array_reduce($options, function($max, $option) {
                            return ($option['votes'] > $max['votes']) ? $option : $max;
                        }, ['votes' => 0]);
                        ?>
                        <p><strong>Beliebteste Option:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($topOption['option_text']); ?></span><br>
                        <small class="text-muted"><?php echo $topOption['votes']; ?> Stimmen (<?php echo round(($topOption['votes'] / $totalVotes) * 100, 1); ?>%)</small></p>
                        
                        <?php if (count($recentVotes) > 0): ?>
                            <p><strong>Letzte Stimme:</strong><br>
                            <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($recentVotes[0]['voted_at'])); ?></small></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted">Noch keine Stimmen abgegeben.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Visualization -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Ergebnisse</h5>
                </div>
                <div class="card-body">
                    <?php if ($totalVotes > 0): ?>
                        <!-- Progress bars -->
                        <?php foreach ($options as $option): ?>
                            <?php 
                            $percentage = $totalVotes > 0 ? round(($option['votes'] / $totalVotes) * 100, 1) : 0;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    <span class="text-muted"><?php echo $option['votes']; ?> Stimme<?php echo $option['votes'] !== 1 ? 'n' : ''; ?> (<?php echo $percentage; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-primary" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%;" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo $percentage; ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="text-muted">Noch keine Stimmen abgegeben.</i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chart Visualization -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Diagramm</h5>
                </div>
                <div class="card-body">
                    <?php if ($totalVotes > 0): ?>
                        <canvas id="pollChart" width="300" height="300"></canvas>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="text-muted">Keine Daten für Diagramm verfügbar.</i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <?php if (count($recentVotes) > 0): ?>
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Letzte Aktivität</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Zeitpunkt</th>
                                    <th>Gewählte Option</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentVotes as $vote): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y H:i:s', strtotime($vote['voted_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($vote['option_text']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($totalVotes > 0): ?>
<script>
// Chart.js pie chart
const ctx = document.getElementById('pollChart').getContext('2d');
const pollChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php foreach ($options as $option): ?>
                '<?php echo addslashes($option['option_text']); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($options as $option): ?>
                    <?php echo $option['votes']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                '#0d6efd', '#6c757d', '#28a745', '#ffc107', '#dc3545', 
                '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
