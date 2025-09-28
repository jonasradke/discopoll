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
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umfrage anzeigen - <?php echo htmlspecialchars($poll['question']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Umfrage anzeigen</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="dashboard" class="btn btn-secondary">← Zurück</a>
            <a href="../presentation?id=<?php echo $pollId; ?>" class="btn btn-dark" target="_blank">📊 Präsentation</a>
            <a href="edit_poll?id=<?php echo $pollId; ?>" class="btn btn-primary">✏️ Bearbeiten</a>
        </div>
    </div>

    <!-- Poll Information -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title"><?php echo htmlspecialchars($poll['question']); ?></h4>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><strong>Poll ID:</strong> <?php echo $poll['id']; ?></p>
                            <p><strong>Erstellt am:</strong> <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?></p>
                            <p><strong>Status:</strong> 
                                <?php if (isset($poll['archived']) && $poll['archived']): ?>
                                    <span class="badge bg-warning">Archiviert</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Aktiv</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Gesamtstimmen:</strong> <span id="total-votes"><?php echo $totalVotes; ?></span></p>
                            <p><strong>Anzahl Optionen:</strong> <?php echo count($options); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Statistiken</h5>
                    <div id="stats-content">
                        <?php if ($totalVotes > 0): ?>
                            <?php
                            $topOption = null;
                            $maxVotes = 0;
                            foreach ($options as $option) {
                                if ($option['votes'] > $maxVotes) {
                                    $maxVotes = $option['votes'];
                                    $topOption = $option;
                                }
                            }
                            $percentage = round(($topOption['votes'] / $totalVotes) * 100);
                            ?>
                            <p><strong>Beliebteste Option:</strong><br>
                            <span class="text-primary"><?php echo htmlspecialchars($topOption['option_text']); ?></span><br>
                            <small class="text-muted"><?php echo $topOption['votes']; ?> Stimmen (<?php echo $percentage; ?>%)</small></p>
                        <?php else: ?>
                            <p class="text-muted">Noch keine Stimmen abgegeben.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Ergebnisse</h5>
                    
                    <?php if ($totalVotes > 0): ?>
                        <?php foreach ($options as $option): ?>
                            <?php 
                            $percentage = round(($option['votes'] / $totalVotes) * 100);
                            ?>
                            <div class="mb-3" data-option-id="<?php echo $option['id']; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    <span class="text-muted">
                                        <span class="vote-count"><?php echo $option['votes']; ?> Stimme<?php echo $option['votes'] !== 1 ? 'n' : ''; ?></span> 
                                        <span class="vote-percentage">(<?php echo $percentage; ?>%)</span>
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%;" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Noch keine Stimmen abgegeben.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if ($totalVotes > 0): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Verteilung</h5>
                    <canvas id="pollChart" width="300" height="300"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <?php
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

    <?php if (count($recentVotes) > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Letzte Aktivität</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Zeit</th>
                                    <th>Option</th>
                                </tr>
                            </thead>
                            <tbody id="recent-activity">
                                <?php foreach ($recentVotes as $vote): ?>
                                <tr>
                                    <td><small class="text-muted"><?php echo date('H:i:s', strtotime($vote['voted_at'])); ?></small></td>
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

<!-- Chart.js Script -->
<?php if ($totalVotes > 0): ?>
<script>
const ctx = document.getElementById('pollChart').getContext('2d');
window.pollChart = new Chart(ctx, {
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
                '#ff3b30',
                '#007aff',
                '#34c759',
                '#ff9500',
                '#af52de',
                '#ffcc02',
                '#ff2d92',
                '#00d4aa'
            ],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
<?php endif; ?>

<!-- Live Updates Script -->
<script>
// Function to fetch results and update display (EXACT COPY FROM HOMEPAGE)
function fetchResults(pollId) {
    $.get('../results.php', { poll_id: pollId }, function(data) {
        let totalVotes = data.reduce((sum, item) => sum + parseInt(item.votes, 10), 0);
        const divisor = totalVotes > 0 ? totalVotes : 1;

        // Update total votes display
        $('#total-votes').text(totalVotes);
        console.log('Updating poll data - Total votes:', totalVotes);

        data.forEach(item => {
            const optionId = item.id;
            const votes = parseInt(item.votes, 10);
            const newPerc = Math.round((votes / divisor) * 100);

            // Find the option container
            const $optionContainer = $(`[data-option-id="${optionId}"]`);
            
            if ($optionContainer.length > 0) {
                console.log(`Updating option ${optionId}: ${votes} votes, ${newPerc}%`);
                
                // Update vote count
                $optionContainer.find('.vote-count').text(`${votes} Stimme${votes !== 1 ? 'n' : ''}`);
                
                // Update percentage
                $optionContainer.find('.vote-percentage').text(`(${newPerc}%)`);
                
                // Update progress bar with animation
                const $bar = $optionContainer.find('.progress-bar');
                if ($bar.length > 0) {
                    let oldPercent = parseInt($bar.attr('aria-valuenow'), 10) || 0;
                    $bar.css('width', oldPercent + '%');
                    setTimeout(() => {
                        $bar.attr('aria-valuenow', newPerc).css('width', newPerc + '%');
                    }, 50);
                }
            } else {
                console.log(`Option container not found for option ${optionId}`);
            }
        });

        // Update chart if it exists
        if (window.pollChart) {
            window.pollChart.data.datasets[0].data = data.map(item => item.votes);
            window.pollChart.update('active');
        }

        // Update statistics
        updateStatistics(data);
        
    }, 'json').fail(function(xhr, status, error) {
        console.error('Failed to fetch poll results:', error);
    });
}

// Update statistics section
function updateStatistics(data) {
    const realTotalVotes = data.reduce((sum, item) => sum + parseInt(item.votes, 10), 0);
    const statsContent = document.getElementById('stats-content');
    
    if (realTotalVotes > 0 && data.length > 0) {
        // Find the option with most votes
        const topOption = data.reduce((max, option) => 
            parseInt(option.votes) > parseInt(max.votes) ? option : max
        );
        
        const percentage = Math.round((parseInt(topOption.votes) / realTotalVotes) * 100);
        
        statsContent.innerHTML = `
            <p><strong>Beliebteste Option:</strong><br>
            <span class="text-primary">${escapeHtml(topOption.option_text)}</span><br>
            <small class="text-muted">${topOption.votes} Stimmen (${percentage}%)</small></p>
        `;
    } else {
        statsContent.innerHTML = '<p class="text-muted">Noch keine Stimmen abgegeben.</p>';
    }
}


// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Start live updates (EXACT COPY FROM HOMEPAGE)
$(document).ready(function() {
    const pollId = <?php echo $pollId; ?>;
    
    // Add live indicator with animation
    $('h2').append(' <span class="badge bg-success" style="animation: pulse 2s infinite;">🔴 Live</span>');
    
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
    
    // Start updates (faster - every 2 seconds)
    fetchResults(pollId);
    setInterval(() => fetchResults(pollId), 2000);
});
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>