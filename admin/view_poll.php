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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <h2 class="mb-0">Umfrage Anzeigen</h2>
                <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                    <a href="../presentation?id=<?php echo $pollId; ?>" 
                       class="btn btn-dark" 
                       target="_blank"
                       title="Präsentationsmodus öffnen">
                       📊 Präsentation
                    </a>
                    <a href="edit_poll?id=<?php echo $pollId; ?>" class="btn btn-primary">
                        ✏️ Bearbeiten
                    </a>
                    <a href="dashboard" class="btn btn-secondary">
                        ← Zurück zum Dashboard
                    </a>
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
                            <p><strong>Gesamtstimmen:</strong> <span data-total-votes><?php echo $totalVotes; ?></span></p>
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
                <div class="card-body" data-stats-card>
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
                            <div class="mb-3" data-option-id="<?php echo $option['id']; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    <span class="text-muted">
                                        <span data-vote-count><?php echo $option['votes']; ?> Stimme<?php echo $option['votes'] !== 1 ? 'n' : ''; ?></span> 
                                        <span data-percentage>(<?php echo $percentage; ?>%)</span>
                                    </span>
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
    <div class="row" data-recent-activity style="<?php echo count($recentVotes) === 0 ? 'display: none;' : ''; ?>">
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

<script>
// Live updates functionality
let pollChart = null;
const pollId = <?php echo $pollId; ?>;

// Initialize chart reference
<?php if ($totalVotes > 0): ?>
pollChart = window.pollChart || Chart.getChart('pollChart');
<?php endif; ?>

// Function to update poll data
async function updatePollData() {
    try {
        const response = await fetch(`poll_data.php?id=${pollId}`);
        if (!response.ok) throw new Error('Failed to fetch data');
        
        const data = await response.json();
        
        // Update total votes
        updateTotalVotes(data.totalVotes);
        
        // Update statistics
        updateStatistics(data);
        
        // Update progress bars
        updateProgressBars(data.options, data.totalVotes);
        
        // Update chart
        updateChart(data.options, data.totalVotes);
        
        // Update recent activity
        updateRecentActivity(data.recentVotes);
        
        // Update last updated indicator
        updateLastUpdated();
        
    } catch (error) {
        console.error('Error updating poll data:', error);
    }
}

// Update total votes display
function updateTotalVotes(totalVotes) {
    const elements = document.querySelectorAll('[data-total-votes]');
    elements.forEach(el => {
        el.textContent = totalVotes;
    });
}

// Update statistics card
function updateStatistics(data) {
    const statsCard = document.querySelector('[data-stats-card]');
    if (!statsCard) return;
    
    if (data.totalVotes > 0 && data.topOption) {
        const percentage = Math.round((data.topOption.votes / data.totalVotes) * 100 * 10) / 10;
        statsCard.innerHTML = `
            <p><strong>Beliebteste Option:</strong><br>
            <span class="text-primary">${escapeHtml(data.topOption.option_text)}</span><br>
            <small class="text-muted">${data.topOption.votes} Stimmen (${percentage}%)</small></p>
            
            ${data.recentVotes.length > 0 ? `
                <p><strong>Letzte Stimme:</strong><br>
                <small class="text-muted">${formatDateTime(data.recentVotes[0].voted_at)}</small></p>
            ` : ''}
        `;
    } else {
        statsCard.innerHTML = '<p class="text-muted">Noch keine Stimmen abgegeben.</p>';
    }
}

// Update progress bars
function updateProgressBars(options, totalVotes) {
    options.forEach(option => {
        const percentage = totalVotes > 0 ? Math.round((option.votes / totalVotes) * 100 * 10) / 10 : 0;
        
        // Find progress bar elements
        const progressBar = document.querySelector(`[data-option-id="${option.id}"] .progress-bar`);
        const voteCount = document.querySelector(`[data-option-id="${option.id}"] [data-vote-count]`);
        const percentageSpan = document.querySelector(`[data-option-id="${option.id}"] [data-percentage]`);
        
        if (progressBar) {
            // Animate progress bar
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
            progressBar.textContent = percentage + '%';
        }
        
        if (voteCount) {
            voteCount.textContent = option.votes + ' Stimme' + (option.votes !== 1 ? 'n' : '');
        }
        
        if (percentageSpan) {
            percentageSpan.textContent = '(' + percentage + '%)';
        }
    });
}

// Update chart
function updateChart(options, totalVotes) {
    if (!pollChart || totalVotes === 0) {
        // If no chart exists and we now have votes, reload the page to create it
        if (!pollChart && totalVotes > 0) {
            location.reload();
            return;
        }
        return;
    }
    
    // Update chart data
    pollChart.data.datasets[0].data = options.map(option => option.votes);
    pollChart.update('active');
}

// Update recent activity table
function updateRecentActivity(recentVotes) {
    const activitySection = document.querySelector('[data-recent-activity]');
    const activityTableBody = document.querySelector('[data-recent-activity] tbody');
    
    if (recentVotes.length === 0) {
        if (activitySection) {
            activitySection.style.display = 'none';
        }
        return;
    }
    
    if (activitySection) {
        activitySection.style.display = 'block';
    }
    
    if (activityTableBody) {
        activityTableBody.innerHTML = recentVotes.map(vote => `
            <tr>
                <td>${formatDateTime(vote.voted_at)}</td>
                <td>${escapeHtml(vote.option_text)}</td>
            </tr>
        `).join('');
    }
}

// Update last updated indicator
function updateLastUpdated() {
    let indicator = document.querySelector('[data-last-updated]');
    if (!indicator) {
        // Create indicator if it doesn't exist
        indicator = document.createElement('small');
        indicator.className = 'text-muted';
        indicator.setAttribute('data-last-updated', '');
        
        const header = document.querySelector('h2');
        if (header) {
            const container = document.createElement('div');
            container.className = 'd-flex justify-content-between align-items-center';
            header.parentNode.insertBefore(container, header);
            container.appendChild(header);
            container.appendChild(indicator);
        }
    }
    
    const now = new Date();
    indicator.textContent = `Zuletzt aktualisiert: ${now.toLocaleTimeString('de-DE')}`;
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('de-DE');
}

// Start live updates
setInterval(updatePollData, 5000); // Update every 5 seconds

// Add visual indicator for live updates
document.addEventListener('DOMContentLoaded', function() {
    // Add live indicator
    const header = document.querySelector('h2');
    if (header) {
        const liveIndicator = document.createElement('span');
        liveIndicator.className = 'badge bg-success ms-2';
        liveIndicator.innerHTML = '🔴 Live';
        liveIndicator.style.animation = 'pulse 2s infinite';
        header.appendChild(liveIndicator);
    }
    
    // Add CSS for pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
});
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
