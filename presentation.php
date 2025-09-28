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
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
         :root {
             --bg-primary: #0f0f23;
             --bg-secondary: #1a1a2e;
             --bg-tertiary: #16213e;
             --color-1: #ff3b30;    /* Bright Red */
             --color-2: #007aff;    /* Bright Blue */
             --color-3: #34c759;    /* Bright Green */
             --color-4: #ff9500;    /* Bright Orange */
             --color-5: #af52de;    /* Bright Purple */
             --color-6: #ffcc02;    /* Bright Yellow */
             --color-7: #ff2d92;    /* Bright Pink */
             --color-8: #00d4aa;    /* Bright Teal */
             --text-primary: #ffffff;
             --text-secondary: #b8c6db;
             --text-muted: #74b9ff;
             --glass-bg: rgba(255, 255, 255, 0.05);
             --glass-border: rgba(255, 255, 255, 0.1);
             --shadow-dark: 0 8px 32px rgba(0, 0, 0, 0.3);
             --shadow-glow: 0 0 20px rgba(0, 212, 255, 0.3);
         }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Performance optimizations */
        .option-item, .progress-fill, .chart-container {
            will-change: transform, width;
            transform: translateZ(0); /* Force hardware acceleration */
        }

        .progress-fill {
            transition: width 0.3s ease-out; /* Optimized transition */
        }

        /* Reduce repaints during animations */
        .live-dot, .live-dot::after {
            transform: translateZ(0);
            will-change: opacity, transform;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 50%, var(--bg-tertiary) 100%);
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
            position: relative;
        }

         /* Subtle background gradient */
         body::before {
             content: '';
             position: fixed;
             top: 0;
             left: 0;
             width: 100%;
             height: 100%;
             background: 
                 radial-gradient(circle at 20% 80%, rgba(0, 212, 255, 0.03) 0%, transparent 70%),
                 radial-gradient(circle at 80% 20%, rgba(255, 107, 107, 0.03) 0%, transparent 70%);
             z-index: -1;
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
             background: var(--glass-bg);
             backdrop-filter: blur(30px);
             border-bottom: 1px solid var(--glass-border);
             position: relative;
             overflow: hidden;
         }

         .qr-code-container {
             margin-top: 1.5rem;
             display: flex;
             flex-direction: column;
             align-items: center;
             gap: 0.5rem;
         }

         .qr-code {
             width: 120px;
             height: 120px;
             background: white;
             border-radius: 12px;
             padding: 8px;
             box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
             display: flex;
             align-items: center;
             justify-content: center;
             cursor: pointer;
             transition: all 0.3s ease;
         }

         .qr-code:hover {
             transform: scale(1.05);
             box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
         }

         /* QR Code Modal */
         .qr-modal {
             display: none;
             position: fixed;
             z-index: 10000;
             left: 0;
             top: 0;
             width: 100%;
             height: 100%;
             background-color: rgba(0, 0, 0, 0.8);
             backdrop-filter: blur(10px);
         }

         .qr-modal-content {
             position: absolute;
             top: 50%;
             left: 50%;
             transform: translate(-50%, -50%);
             background: white;
             border-radius: 20px;
             padding: 2rem;
             text-align: center;
             box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
             max-width: 90vw;
             max-height: 90vh;
         }

         .qr-modal img {
             max-width: 300px;
             max-height: 300px;
             border-radius: 12px;
         }

         .qr-modal-close {
             position: absolute;
             top: 1rem;
             right: 1rem;
             background: none;
             border: none;
             font-size: 2rem;
             color: #666;
             cursor: pointer;
             padding: 0.5rem;
         }

         .qr-modal-close:hover {
             color: #000;
         }

         .qr-code img {
             width: 100%;
             height: 100%;
             border-radius: 8px;
         }

         .qr-text {
             color: var(--text-secondary);
             font-size: 0.9rem;
             margin: 0;
             font-weight: 500;
         }

         .floating-logo {
             position: fixed;
             top: 1rem;
             left: 1rem;
             z-index: 999;
         }

         .spinning-logo {
             height: 60px;
             width: 60px;
             animation: spin 10s linear infinite;
             filter: brightness(0) invert(1);
             opacity: 0.8;
             transition: opacity 0.3s ease;
         }

         .spinning-logo:hover {
             opacity: 1;
         }

         @keyframes spin {
             0% { transform: rotate(0deg); }
             100% { transform: rotate(360deg); }
         }

         .poll-title {
             font-size: clamp(2rem, 5vw, 4rem);
             font-weight: 700;
             margin-bottom: 1rem;
             background: linear-gradient(135deg, #00d4ff, #4ecdc4);
             -webkit-background-clip: text;
             -webkit-text-fill-color: transparent;
             background-clip: text;
             line-height: 1.2;
             position: relative;
             z-index: 1;
         }

        .poll-meta {
            font-size: 1.2rem;
            color: var(--text-secondary);
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

         .live-indicator {
             display: inline-flex;
             align-items: center;
             gap: 0.5rem;
             background: linear-gradient(135deg, #ff3b30, #ff6b6b);
             padding: 0.4rem 1rem;
             border-radius: 20px;
             border: 1px solid rgba(255, 59, 48, 0.4);
             font-size: 0.9rem;
             font-weight: 600;
             margin-left: 1rem;
             position: relative;
             overflow: hidden;
             animation: liveGlow 3s ease-in-out infinite;
         }

         .live-indicator::before {
             content: '';
             position: absolute;
             top: 0;
             left: -100%;
             width: 100%;
             height: 100%;
             background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
             animation: liveShine 2s ease-in-out infinite;
         }

         .live-dot {
             width: 8px;
             height: 8px;
             background: #fff;
             border-radius: 50%;
             position: relative;
             z-index: 1;
             animation: livePulse 1.5s ease-in-out infinite;
         }

         .live-dot::after {
             content: '';
             position: absolute;
             top: 50%;
             left: 50%;
             width: 16px;
             height: 16px;
             border: 2px solid rgba(255, 255, 255, 0.3);
             border-radius: 50%;
             transform: translate(-50%, -50%);
             animation: liveRipple 2s ease-out infinite;
         }

         @keyframes livePulse {
             0%, 100% { 
                 opacity: 1; 
                 transform: scale(1);
                 box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7);
             }
             50% { 
                 opacity: 0.8; 
                 transform: scale(1.3);
                 box-shadow: 0 0 0 4px rgba(255, 255, 255, 0);
             }
         }

         @keyframes liveRipple {
             0% {
                 transform: translate(-50%, -50%) scale(0.8);
                 opacity: 1;
             }
             100% {
                 transform: translate(-50%, -50%) scale(2);
                 opacity: 0;
             }
         }

         @keyframes liveGlow {
             0%, 100% {
                 box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
             }
             50% {
                 box-shadow: 0 0 20px rgba(255, 59, 48, 0.8), 0 0 30px rgba(255, 59, 48, 0.4);
             }
         }

         @keyframes liveShine {
             0% { left: -100%; }
             50% { left: 100%; }
             100% { left: 100%; }
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
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

         .option-item:hover {
             transform: translateY(-2px);
             box-shadow: var(--shadow-dark);
         }

         .option-item:nth-child(1) { border-left: 3px solid var(--color-1); }
         .option-item:nth-child(2) { border-left: 3px solid var(--color-2); }
         .option-item:nth-child(3) { border-left: 3px solid var(--color-3); }
         .option-item:nth-child(4) { border-left: 3px solid var(--color-4); }
         .option-item:nth-child(5) { border-left: 3px solid var(--color-5); }
         .option-item:nth-child(6) { border-left: 3px solid var(--color-6); }
         .option-item:nth-child(7) { border-left: 3px solid var(--color-7); }
         .option-item:nth-child(8) { border-left: 3px solid var(--color-8); }

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

        .option-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .option-text {
            font-size: 1.5rem;
            font-weight: 600;
            flex: 1;
            color: var(--text-primary);
        }

        .option-stats {
            text-align: right;
            color: var(--text-secondary);
        }

        .vote-count {
            font-size: 2rem;
            font-weight: 700;
            display: block;
            color: #00d4ff;
        }

        .percentage {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .progress-bar {
            height: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .progress-fill {
            height: 100%;
            border-radius: 8px;
            transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

         /* Different colored progress bars for each option */
         .option-item:nth-child(1) .progress-fill {
             background: var(--color-1);
         }
         .option-item:nth-child(2) .progress-fill {
             background: var(--color-2);
         }
         .option-item:nth-child(3) .progress-fill {
             background: var(--color-3);
         }
         .option-item:nth-child(4) .progress-fill {
             background: var(--color-4);
         }
         .option-item:nth-child(5) .progress-fill {
             background: var(--color-5);
         }
         .option-item:nth-child(6) .progress-fill {
             background: var(--color-6);
         }
         .option-item:nth-child(7) .progress-fill {
             background: var(--color-7);
         }
         .option-item:nth-child(8) .progress-fill {
             background: var(--color-8);
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


        /* Chart Section */
        .chart-section {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
            min-width: 400px;
            position: relative;
            overflow: hidden;
        }

        .chart-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, rgba(0, 212, 255, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--text-primary);
            position: relative;
            z-index: 1;
        }

        #presentationChart {
            max-width: 350px;
            max-height: 350px;
            position: relative;
            z-index: 1;
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
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 1rem 2rem;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: linear-gradient(90deg, transparent, var(--accent-primary), transparent);
            opacity: 0.2;
            transition: left 0.6s ease;
        }

        .nav-btn:hover {
            background: rgba(0, 212, 255, 0.1);
            transform: translateY(-3px);
            color: var(--text-primary);
            text-decoration: none;
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-primary);
        }

        .nav-btn:hover::before {
            left: 100%;
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

         /* Controls */
         .controls {
             position: fixed;
             top: 1rem;
             right: 1rem;
             display: flex;
             gap: 0.5rem;
             z-index: 1000;
         }

        .control-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 0.75rem;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
            width: 40px;
            height: 40px;
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
            background: linear-gradient(45deg, transparent, var(--accent-primary), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .control-btn:hover {
            background: rgba(0, 212, 255, 0.1);
            transform: scale(1.1);
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-primary);
        }

        .control-btn:hover::before {
            opacity: 0.2;
        }

        /* No votes state */
        .no-votes {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .no-votes-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-tertiary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .no-votes h2 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .no-votes p {
            color: var(--text-muted);
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
                 padding-top: 4rem; /* Make room for floating logo */
             }

             .floating-logo {
                 top: 0.5rem;
                 left: 0.5rem;
             }

             .spinning-logo {
                 height: 40px;
                 width: 40px;
             }

             .poll-title {
                 font-size: clamp(1.5rem, 4vw, 2.5rem);
                 margin-bottom: 0.5rem;
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
                 top: 0.5rem;
                 right: 0.5rem;
                 gap: 0.25rem;
             }

             .control-btn {
                 width: 35px;
                 height: 35px;
                 font-size: 0.9rem;
                 padding: 0.5rem;
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

        <!-- Floating Logo -->
        <div class="floating-logo">
            <img src="assets/logo.png" alt="Logo" class="spinning-logo">
        </div>

        <!-- Header -->
        <div class="header">
            <h1 class="poll-title"><?php echo htmlspecialchars($currentPoll['question']); ?></h1>
             <div class="poll-meta">
                 <span id="totalVotes"><?php echo $totalVotes; ?></span> Stimme<span id="votesPlural"><?php echo $totalVotes !== 1 ? 'n' : ''; ?></span> • 
                 <?php echo count($options); ?> Option<?php echo count($options) !== 1 ? 'en' : ''; ?>
                 <?php if (count($polls) > 1): ?>
                     • Umfrage <?php echo $currentIndex + 1; ?> von <?php echo count($polls); ?>
                 <?php endif; ?>
                 <span class="live-indicator">
                     <span class="live-dot"></span>
                     LIVE
                 </span>
             </div>
             
             <!-- QR Code -->
             <div class="qr-code-container">
                 <div class="qr-code" id="qrCode" onclick="openQRModal()"></div>
                 <p class="qr-text">Scan to vote</p>
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
                            $percentage = $totalVotes > 0 ? ($option['votes'] / $totalVotes) * 100 : 0;
                            $formattedPercentage = ($percentage == floor($percentage)) ? number_format($percentage, 0) : number_format($percentage, 1);
                            ?>
                            <div class="option-item" data-option="<?php echo $option['id']; ?>">
                                <div class="option-header">
                                    <div class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></div>
                                    <div class="option-stats">
                                        <span class="vote-count"><?php echo $option['votes']; ?></span>
                                        <span class="percentage"><?php echo $formattedPercentage; ?>%</span>
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
        // Chart.js pie chart
        const ctx = document.getElementById('presentationChart').getContext('2d');
        window.presentationChart = new Chart(ctx, {
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
                    borderColor: 'rgba(255, 255, 255, 0.2)',
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
    </script>
    <?php endif; ?>

    <!-- jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
         // Performance optimization: prevent multiple simultaneous requests
         let isUpdating = false;
         
         // Function to fetch results for the current poll and animate progress bars (OPTIMIZED)
         function fetchResults() {
             // Prevent multiple simultaneous requests
             if (isUpdating) {
                 return;
             }
             isUpdating = true;
             const pollId = <?php echo $currentPoll['id']; ?>;
             $.get('results.php', { poll_id: pollId }, function(data) {
                 let totalVotes = data.reduce((sum, item) => sum + parseInt(item.votes, 10), 0);
                 
                 // Cache DOM elements to avoid repeated queries
                 const $totalVotes = $('#totalVotes');
                 const $votesPlural = $('#votesPlural');
                 
                 // Update total votes display (batch DOM updates)
                 $totalVotes.text(totalVotes);
                 $votesPlural.text(totalVotes !== 1 ? 'n' : '');

                 // Process all options in one batch
                 const updates = [];
                 data.forEach(item => {
                     const optionId = item.id;
                     const votes = parseInt(item.votes, 10);
                     const newPerc = totalVotes > 0 ? (votes / totalVotes) * 100 : 0;
                     const formattedPerc = newPerc % 1 === 0 ? newPerc.toFixed(0) : newPerc.toFixed(1);
                     
                     updates.push({
                         optionId: optionId,
                         votes: votes,
                         percentage: formattedPerc,
                         width: newPerc.toFixed(1) + '%'
                     });
                 });

                 // Batch DOM updates for better performance
                 updates.forEach(update => {
                     const $optionContainer = $(`[data-option="${update.optionId}"]`);
                     if ($optionContainer.length > 0) {
                         // Update all elements at once
                         $optionContainer.find('.vote-count').text(update.votes);
                         $optionContainer.find('.percentage').text(update.percentage + '%');
                         $optionContainer.find('.progress-fill').css('width', update.width);
                     }
                 });

                 // Update chart if it exists (only if data changed)
                 if (window.presentationChart) {
                     const newData = data.map(item => item.votes);
                     const currentData = window.presentationChart.data.datasets[0].data;
                     
                     // Only update chart if data actually changed
                     if (JSON.stringify(newData) !== JSON.stringify(currentData)) {
                         window.presentationChart.data.datasets[0].data = newData;
                         window.presentationChart.update('active');
                     }
                 }
             }, 'json').fail(function() {
                 // Silent fail to avoid console spam
                 console.log('Poll update failed - will retry');
             }).always(function() {
                 // Always reset the flag
                 isUpdating = false;
             });
         }

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
            fetchResults();
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

        // Initialize on load
        $(document).ready(function() {
            // Animate progress bars on load
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });

            // Start auto-refresh every 3 seconds (optimized)
            setInterval(fetchResults, 3000);
            
            // Generate QR code
            generateQRCode();
        });
        
        // Generate QR code for the poll using QR Server API (OPTIMIZED - runs once)
        let qrCodeGenerated = false;
        let qrCodeUrl = '';
        
        function generateQRCode() {
            if (qrCodeGenerated) return; // Only generate once
            qrCodeGenerated = true;
            
            const pollId = <?php echo $currentPoll['id']; ?>;
            const pollUrl = `${window.location.origin}${window.location.pathname.replace('/presentation', '')}?id=${pollId}`;
            
            // Use QR Server API to generate QR code
            qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=104x104&data=${encodeURIComponent(pollUrl)}`;
            
            const qrCodeElement = document.getElementById('qrCode');
            if (qrCodeElement) {
                qrCodeElement.innerHTML = `<img src="${qrCodeUrl}" alt="QR Code" style="width: 100%; height: 100%; border-radius: 8px;" onerror="this.parentElement.innerHTML='<div style=\'color: #666; font-size: 12px; text-align: center; padding: 20px;\'>QR Code<br>Error</div>'">`;
            }
        }

        // QR Modal functions
        function openQRModal() {
            const modal = document.getElementById('qrModal');
            const modalImage = document.getElementById('qrModalImage');
            
            if (modal && modalImage && qrCodeUrl) {
                // Create larger QR code for modal
                const largeQRUrl = qrCodeUrl.replace('size=104x104', 'size=300x300');
                modalImage.innerHTML = `<img src="${largeQRUrl}" alt="QR Code" style="width: 100%; height: auto; border-radius: 12px;">`;
                modal.style.display = 'block';
            }
        }

        function closeQRModal() {
            const modal = document.getElementById('qrModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('qrModal');
            if (event.target === modal) {
                closeQRModal();
            }
        }
    </script>

    <!-- QR Code Modal -->
    <div id="qrModal" class="qr-modal">
        <div class="qr-modal-content">
            <button class="qr-modal-close" onclick="closeQRModal()">&times;</button>
            <h3 style="margin-bottom: 1rem; color: #333;">Scan to Vote</h3>
            <div id="qrModalImage"></div>
            <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">Use your phone camera to scan this QR code</p>
        </div>
    </div>
</body>
</html>
