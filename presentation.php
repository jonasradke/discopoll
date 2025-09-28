<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once 'config.php';

// Check if database connection exists
if (!isset($pdo)) {
    die("Database connection failed. Please check your configuration.");
}

// Get poll ID from query parameter, or show all active polls
$pollId = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($pollId) {
        // Single poll presentation
        // First check if archived column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM polls LIKE 'archived'");
        $stmt->execute();
        $hasArchivedColumn = $stmt->fetch() !== false;
        
        if ($hasArchivedColumn) {
            $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id AND archived = 0");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id");
        }
        $stmt->execute([':id' => $pollId]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$poll) {
            die("Poll not found" . ($hasArchivedColumn ? " or archived" : "") . ".");
        }
        
        $polls = [$poll];
    } else {
        // All active polls presentation
        // First check if archived column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM polls LIKE 'archived'");
        $stmt->execute();
        $hasArchivedColumn = $stmt->fetch() !== false;
        
        if ($hasArchivedColumn) {
            $stmt = $pdo->query("SELECT * FROM polls WHERE archived = 0 ORDER BY created_at DESC");
        } else {
            $stmt = $pdo->query("SELECT * FROM polls ORDER BY created_at DESC");
        }
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
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
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(45deg, #ff6b6b, #feca57, #48dbfb, #ff9ff3);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-heavy: 0 20px 60px rgba(0, 0, 0, 0.3);
            --transition-smooth: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            animation: backgroundShift 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }

        .presentation-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Header */
        .header {
            padding: 3rem 2rem;
            text-align: center;
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border-bottom: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: headerShine 3s ease-in-out infinite;
        }

        @keyframes headerShine {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }

        .poll-title {
            font-size: clamp(2.5rem, 6vw, 5rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: var(--secondary-gradient);
            background-size: 400% 400%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
            animation: gradientShift 8s ease-in-out infinite, titlePulse 4s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes titlePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .poll-meta {
            font-size: 1.4rem;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition-smooth);
            animation: metaFloat 6s ease-in-out infinite;
        }

        .meta-item:nth-child(2) { animation-delay: -2s; }
        .meta-item:nth-child(3) { animation-delay: -4s; }

        @keyframes metaFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 59, 48, 0.2);
            border: 1px solid rgba(255, 59, 48, 0.4);
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: #ff3b30;
            border-radius: 50%;
            animation: livePulse 2s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
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
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            animation: optionSlideIn 0.8s ease-out forwards;
            opacity: 0;
            transform: translateX(-50px);
        }

        .option-item:nth-child(1) { animation-delay: 0.1s; }
        .option-item:nth-child(2) { animation-delay: 0.2s; }
        .option-item:nth-child(3) { animation-delay: 0.3s; }
        .option-item:nth-child(4) { animation-delay: 0.4s; }
        .option-item:nth-child(5) { animation-delay: 0.5s; }

        @keyframes optionSlideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .option-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: var(--transition-smooth);
        }

        .option-item:hover {
            transform: translateY(-8px) rotateX(5deg);
            box-shadow: var(--shadow-heavy);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .option-item:hover::before {
            left: 100%;
            transition: left 0.6s ease;
        }

        .option-item.winner {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 165, 0, 0.2));
            border-color: rgba(255, 215, 0, 0.5);
            animation: winnerGlow 2s ease-in-out infinite;
        }

        @keyframes winnerGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.3); }
            50% { box-shadow: 0 0 40px rgba(255, 215, 0, 0.6); }
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
            height: 12px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.9) 0%,
                rgba(255, 255, 255, 0.7) 50%,
                rgba(255, 255, 255, 0.9) 100%);
            border-radius: 8px;
            transition: width 2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .progress-fill::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.3) 0%,
                rgba(255, 255, 255, 0.1) 50%,
                rgba(255, 255, 255, 0.3) 100%);
            border-radius: 8px 8px 0 0;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent 0%,
                rgba(255, 255, 255, 0.4) 50%,
                transparent 100%);
            animation: progressShimmer 3s ease-in-out infinite;
        }

        @keyframes progressShimmer {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }

        .option-text {
            font-size: 1.8rem;
            font-weight: 700;
            flex: 1;
            margin-bottom: 1rem;
            position: relative;
        }

        .option-stats {
            text-align: right;
            opacity: 0.95;
            margin-bottom: 1rem;
        }

        .vote-count {
            font-size: 2.5rem;
            font-weight: 800;
            display: block;
            background: var(--secondary-gradient);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientShift 4s ease-in-out infinite;
        }

        .percentage {
            font-size: 1.2rem;
            opacity: 0.8;
            font-weight: 500;
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
            animation: navSlideUp 1s ease-out 0.5s both;
        }

        @keyframes navSlideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(100px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .nav-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 1rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-bounce);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .nav-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: var(--transition-smooth);
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-5px) scale(1.05);
            color: white;
            text-decoration: none;
            box-shadow: var(--shadow-heavy);
        }

        .nav-btn:hover::before {
            left: 100%;
        }

        .nav-btn.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        /* Controls */
        .controls {
            position: fixed;
            top: 2rem;
            right: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            z-index: 1000;
            animation: controlsSlideIn 1s ease-out 0.3s both;
        }

        @keyframes controlsSlideIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .control-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1rem;
            color: white;
            cursor: pointer;
            transition: var(--transition-bounce);
            font-size: 1.4rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .control-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            transition: var(--transition-smooth);
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1) rotate(5deg);
            box-shadow: var(--shadow-light);
        }

        .control-btn:hover::before {
            transform: translateX(100%);
        }

        .control-btn:active {
            transform: scale(0.95);
        }

        /* Settings Panel */
        .settings-panel {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            z-index: 1000;
            transform: translateX(-100%);
            transition: var(--transition-smooth);
            min-width: 250px;
        }

        .settings-panel.open {
            transform: translateX(0);
        }

        .settings-toggle {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1rem;
            color: white;
            cursor: pointer;
            transition: var(--transition-bounce);
            font-size: 1.4rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
            animation: controlsSlideIn 1s ease-out 0.1s both;
        }

        .settings-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1) rotate(-5deg);
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

        <!-- Settings Toggle -->
        <div class="settings-toggle" onclick="toggleSettings()" title="Einstellungen">
            ⚙️
        </div>

        <!-- Settings Panel -->
        <div class="settings-panel" id="settingsPanel">
            <h3 style="margin-bottom: 1rem; font-size: 1.2rem;">Präsentationseinstellungen</h3>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;">Auto-Update Intervall:</label>
                <select id="updateInterval" style="width: 100%; padding: 0.5rem; border-radius: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white;">
                    <option value="5000">5 Sekunden</option>
                    <option value="10000">10 Sekunden</option>
                    <option value="30000">30 Sekunden</option>
                    <option value="0">Aus</option>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                    <input type="checkbox" id="soundEffects" style="margin: 0;">
                    Sound-Effekte
                </label>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                    <input type="checkbox" id="showWinner" checked style="margin: 0;">
                    Gewinner hervorheben
                </label>
            </div>
        </div>

        <!-- Header -->
        <div class="header">
            <h1 class="poll-title"><?php echo htmlspecialchars($currentPoll['question']); ?></h1>
            <div class="poll-meta">
                <span class="meta-item live-indicator">
                    <span class="live-dot"></span>
                    LIVE
                </span>
                <span class="meta-item">
                    <span id="totalVotesDisplay"><?php echo $totalVotes; ?></span> Stimme<?php echo $totalVotes !== 1 ? 'n' : ''; ?>
                </span>
                <span class="meta-item">
                    <?php echo count($options); ?> Option<?php echo count($options) !== 1 ? 'en' : ''; ?>
                </span>
                <?php if (count($polls) > 1): ?>
                <span class="meta-item">
                    Umfrage <?php echo $currentIndex + 1; ?> von <?php echo count($polls); ?>
                </span>
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
                            <div class="option-item" data-option="<?php echo $option['id']; ?>">
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
        // Enhanced presentation functionality
        let updateInterval = null;
        let soundEnabled = false;
        let showWinner = true;
        let currentData = null;

        // Settings functionality
        function toggleSettings() {
            const panel = document.getElementById('settingsPanel');
            panel.classList.toggle('open');
        }

        // Initialize settings
        function initializeSettings() {
            const intervalSelect = document.getElementById('updateInterval');
            const soundCheckbox = document.getElementById('soundEffects');
            const winnerCheckbox = document.getElementById('showWinner');

            intervalSelect.addEventListener('change', function() {
                const interval = parseInt(this.value);
                if (updateInterval) clearInterval(updateInterval);
                
                if (interval > 0) {
                    updateInterval = setInterval(fetchAndUpdateData, interval);
                }
            });

            soundCheckbox.addEventListener('change', function() {
                soundEnabled = this.checked;
            });

            winnerCheckbox.addEventListener('change', function() {
                showWinner = this.checked;
                updateWinnerHighlight();
            });

            // Start with 10 second interval
            intervalSelect.value = '10000';
            updateInterval = setInterval(fetchAndUpdateData, 10000);
        }

        // Enhanced data fetching with animations
        async function fetchAndUpdateData() {
            try {
                const pollId = <?php echo $currentPoll['id']; ?>;
                const response = await fetch(`results.php?poll_id=${pollId}`);
                const newData = await response.json();
                
                if (currentData && JSON.stringify(currentData) !== JSON.stringify(newData)) {
                    // Data changed, play sound if enabled
                    if (soundEnabled) playNotificationSound();
                    
                    // Animate changes
                    animateDataUpdate(newData);
                }
                
                currentData = newData;
                updateDisplay(newData);
                
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        }

        // Update display with new data
        function updateDisplay(data) {
            const totalVotes = data.reduce((sum, item) => sum + parseInt(item.votes), 0);
            
            // Update total votes with animation
            const totalDisplay = document.getElementById('totalVotesDisplay');
            if (totalDisplay) {
                animateNumber(totalDisplay, parseInt(totalDisplay.textContent) || 0, totalVotes);
            }

            // Update progress bars and vote counts
            data.forEach(item => {
                const percentage = totalVotes > 0 ? Math.round((item.votes / totalVotes) * 100) : 0;
                
                // Find elements
                const progressBar = document.querySelector(`[data-option="${item.id}"] .progress-fill`);
                const voteCount = document.querySelector(`[data-option="${item.id}"] .vote-count`);
                const percentageSpan = document.querySelector(`[data-option="${item.id}"] .percentage`);
                
                if (progressBar) {
                    progressBar.style.width = percentage + '%';
                }
                
                if (voteCount) {
                    animateNumber(voteCount, parseInt(voteCount.textContent) || 0, item.votes);
                }
                
                if (percentageSpan) {
                    percentageSpan.textContent = percentage + '%';
                }
            });

            // Update winner highlight
            if (showWinner) {
                updateWinnerHighlight();
            }

            // Update chart if it exists
            if (window.chart) {
                window.chart.data.datasets[0].data = data.map(item => item.votes);
                window.chart.update('active');
            }
        }

        // Animate number changes
        function animateNumber(element, from, to) {
            const duration = 1000;
            const start = Date.now();
            
            function update() {
                const elapsed = Date.now() - start;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.round(from + (to - from) * easeOutCubic(progress));
                
                element.textContent = current;
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }
            
            requestAnimationFrame(update);
        }

        // Easing function
        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        // Animate data updates
        function animateDataUpdate(newData) {
            // Add pulse animation to changed items
            newData.forEach(item => {
                const optionElement = document.querySelector(`[data-option="${item.id}"]`);
                if (optionElement) {
                    optionElement.style.animation = 'none';
                    optionElement.offsetHeight; // Trigger reflow
                    optionElement.style.animation = 'optionPulse 0.6s ease-out';
                }
            });
        }

        // Update winner highlight
        function updateWinnerHighlight() {
            if (!currentData || !showWinner) return;
            
            const maxVotes = Math.max(...currentData.map(item => parseInt(item.votes)));
            
            document.querySelectorAll('.option-item').forEach((element, index) => {
                const votes = parseInt(currentData[index]?.votes || 0);
                if (votes === maxVotes && maxVotes > 0) {
                    element.classList.add('winner');
                } else {
                    element.classList.remove('winner');
                }
            });
        }

        // Play notification sound
        function playNotificationSound() {
            // Create a simple beep sound using Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        }

        // Fullscreen functionality
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log('Fullscreen not supported:', err);
                });
            } else {
                document.exitFullscreen();
            }
        }

        // Refresh data
        function refreshData() {
            fetchAndUpdateData();
            
            // Add visual feedback
            const refreshBtn = document.querySelector('[onclick="refreshData()"]');
            if (refreshBtn) {
                refreshBtn.style.animation = 'spin 1s ease-out';
                setTimeout(() => {
                    refreshBtn.style.animation = '';
                }, 1000);
            }
        }

        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Prevent default if we're handling the key
            const handled = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'f', 'F', 'r', 'R', 's', 'S', 'Escape'].includes(e.key);
            
            if (handled) {
                e.preventDefault();
            }

            switch(e.key) {
                case 'ArrowLeft':
                case 'ArrowUp':
                    const prevBtn = document.querySelector('.navigation a[href*="index=<?php echo max(0, $currentIndex - 1); ?>"]');
                    if (prevBtn) prevBtn.click();
                    break;
                    
                case 'ArrowRight':
                case 'ArrowDown':
                    const nextBtn = document.querySelector('.navigation a[href*="index=<?php echo min(count($polls) - 1, $currentIndex + 1); ?>"]');
                    if (nextBtn) nextBtn.click();
                    break;
                    
                case 'f':
                case 'F':
                    toggleFullscreen();
                    break;
                    
                case 'r':
                case 'R':
                    refreshData();
                    break;
                    
                case 's':
                case 'S':
                    toggleSettings();
                    break;
                    
                case 'Escape':
                    const settingsPanel = document.getElementById('settingsPanel');
                    if (settingsPanel.classList.contains('open')) {
                        settingsPanel.classList.remove('open');
                    }
                    break;
            }
        });

        // Initialize on load
        window.addEventListener('load', function() {
            // Animate progress bars on load
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500 + (index * 100));
            });

            // Initialize settings
            initializeSettings();
            
            // Initial data fetch
            setTimeout(fetchAndUpdateData, 1000);
            
            // Add CSS animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes optionPulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1); }
                }
                
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>
