<?php
include_once 'config.php';

// Get poll ID from query parameter, or show all active polls
$pollId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($pollId) {
    // Single poll presentation
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id AND archived = 0");
    $stmt->execute([':id' => $pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        die("Poll not found or archived.");
    }
    
    $polls = [$poll];
} else {
    // All active polls presentation
    $stmt = $pdo->query("SELECT * FROM polls WHERE archived = 0 ORDER BY created_at DESC");
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get current poll index for navigation
$currentIndex = isset($_GET['index']) ? (int)$_GET['index'] : 0;
$currentPoll = $polls[$currentIndex] ?? $polls[0] ?? null;

if (!$currentPoll) {
    die("No active polls available.");
}

// Fetch options and votes for current poll
$stmtOptions = $pdo->prepare("
    SELECT po.id, po.option_text, COUNT(pv.id) as votes
    FROM poll_options po
    LEFT JOIN poll_votes pv ON pv.option_id = po.id
    WHERE po.poll_id = :poll_id
    GROUP BY po.id, po.option_text
    ORDER BY po.id ASC
");
$stmtOptions->execute([':poll_id' => $currentPoll['id']]);
$options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

$totalVotes = array_sum(array_column($options, 'votes'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poll Presentation - <?php echo htmlspecialchars($currentPoll['question']); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        .presentation-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Header */
        .header {
            padding: 2rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .poll-title {
            font-size: clamp(2rem, 5vw, 4rem);
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        .poll-meta {
            font-size: 1.2rem;
            opacity: 0.8;
            font-weight: 300;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
        }

        .results-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 4rem;
            width: 100%;
            max-width: 1400px;
            align-items: center;
        }

        /* Results Section */
        .results-section {
            space-y: 2rem;
        }

        .option-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .option-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .option-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .option-text {
            font-size: 1.5rem;
            font-weight: 600;
            flex: 1;
        }

        .option-stats {
            text-align: right;
            opacity: 0.9;
        }

        .vote-count {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }

        .percentage {
            font-size: 1rem;
            opacity: 0.7;
        }

        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #fff, rgba(255, 255, 255, 0.8));
            border-radius: 4px;
            transition: width 1s ease-out;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Chart Section */
        .chart-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
            min-width: 400px;
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        #presentationChart {
            max-width: 350px;
            max-height: 350px;
        }

        /* Navigation */
        .navigation {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 1rem;
            z-index: 1000;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 1rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Controls */
        .controls {
            position: fixed;
            top: 2rem;
            right: 2rem;
            display: flex;
            gap: 1rem;
            z-index: 1000;
        }

        .control-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 0.75rem;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        /* No votes state */
        .no-votes {
            text-align: center;
            padding: 4rem 2rem;
            opacity: 0.7;
        }

        .no-votes-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .results-grid {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
            
            .chart-section {
                min-width: auto;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 1rem;
            }
            
            .main-content {
                padding: 2rem 1rem;
            }
            
            .option-item {
                padding: 1.5rem;
            }
            
            .chart-section {
                padding: 2rem;
            }
            
            .controls {
                top: 1rem;
                right: 1rem;
            }
        }

        /* Fullscreen styles */
        .fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div class="presentation-container" id="presentationContainer">
        <!-- Controls -->
        <div class="controls">
            <button class="control-btn" onclick="toggleFullscreen()" title="Fullscreen">
                ⛶
            </button>
            <button class="control-btn" onclick="refreshData()" title="Refresh">
                ↻
            </button>
        </div>

        <!-- Header -->
        <div class="header">
            <h1 class="poll-title"><?php echo htmlspecialchars($currentPoll['question']); ?></h1>
            <div class="poll-meta">
                <?php echo $totalVotes; ?> Stimme<?php echo $totalVotes !== 1 ? 'n' : ''; ?> • 
                <?php echo count($options); ?> Option<?php echo count($options) !== 1 ? 'en' : ''; ?>
                <?php if (count($polls) > 1): ?>
                    • Umfrage <?php echo $currentIndex + 1; ?> von <?php echo count($polls); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if ($totalVotes > 0): ?>
                <div class="results-grid">
                    <!-- Results -->
                    <div class="results-section">
                        <?php foreach ($options as $option): ?>
                            <?php 
                            $percentage = $totalVotes > 0 ? round(($option['votes'] / $totalVotes) * 100, 1) : 0;
                            ?>
                            <div class="option-item">
                                <div class="option-header">
                                    <div class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></div>
                                    <div class="option-stats">
                                        <span class="vote-count"><?php echo $option['votes']; ?></span>
                                        <span class="percentage"><?php echo $percentage; ?>%</span>
                                    </div>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Chart -->
                    <div class="chart-section">
                        <h3 class="chart-title">Verteilung</h3>
                        <canvas id="presentationChart"></canvas>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-votes">
                    <div class="no-votes-icon">📊</div>
                    <h2>Noch keine Stimmen</h2>
                    <p>Die Umfrage wartet auf die ersten Teilnehmer.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <?php if (count($polls) > 1): ?>
        <div class="navigation">
            <?php if ($currentIndex > 0): ?>
                <a href="?<?php echo $pollId ? "id={$pollId}&" : ''; ?>index=<?php echo $currentIndex - 1; ?>" class="nav-btn">
                    ← Vorherige
                </a>
            <?php endif; ?>
            
            <span class="nav-btn" style="background: rgba(255, 255, 255, 0.3);">
                <?php echo $currentIndex + 1; ?> / <?php echo count($polls); ?>
            </span>
            
            <?php if ($currentIndex < count($polls) - 1): ?>
                <a href="?<?php echo $pollId ? "id={$pollId}&" : ''; ?>index=<?php echo $currentIndex + 1; ?>" class="nav-btn">
                    Nächste →
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalVotes > 0): ?>
    <script>
        // Chart.js configuration
        const ctx = document.getElementById('presentationChart').getContext('2d');
        const chart = new Chart(ctx, {
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
                        'rgba(255, 255, 255, 0.9)',
                        'rgba(255, 255, 255, 0.7)',
                        'rgba(255, 255, 255, 0.5)',
                        'rgba(255, 255, 255, 0.3)',
                        'rgba(255, 255, 255, 0.8)',
                        'rgba(255, 255, 255, 0.6)',
                        'rgba(255, 255, 255, 0.4)',
                        'rgba(255, 255, 255, 0.2)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 0.3)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.3)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Auto-refresh every 10 seconds
        setInterval(refreshData, 10000);
    </script>
    <?php endif; ?>

    <script>
        // Fullscreen functionality
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        // Refresh data
        function refreshData() {
            window.location.reload();
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                const prevBtn = document.querySelector('.navigation a[href*="index=<?php echo max(0, $currentIndex - 1); ?>"]');
                if (prevBtn) prevBtn.click();
            } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                const nextBtn = document.querySelector('.navigation a[href*="index=<?php echo min(count($polls) - 1, $currentIndex + 1); ?>"]');
                if (nextBtn) nextBtn.click();
            } else if (e.key === 'f' || e.key === 'F') {
                toggleFullscreen();
            } else if (e.key === 'r' || e.key === 'R') {
                refreshData();
            }
        });

        // Animate progress bars on load
        window.addEventListener('load', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>
