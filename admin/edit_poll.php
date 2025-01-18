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

// 1. Get poll ID from query string
if (!isset($_GET['id'])) {
    die("No poll ID provided.");
}
$pollId = (int)$_GET['id'];

// 2. Fetch the poll
$stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id");
$stmt->execute([':id' => $pollId]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$poll) {
    die("Poll not found.");
}

// 3. Fetch existing poll options
$stmtOpts = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = :poll_id ORDER BY id ASC");
$stmtOpts->execute([':poll_id' => $pollId]);
$options = $stmtOpts->fetchAll(PDO::FETCH_ASSOC);

// 4. Handle form submission
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // (Optional) CSRF token check
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     die("CSRF token validation failed.");
    // }

    $question = trim($_POST['question'] ?? '');

    // We'll have arrays:
    //   $_POST['option_id'][]   => e.g. ["12", "", "14", ...] (empty => new)
    //   $_POST['option_text'][] => e.g. ["Option A", "New Option", ...]
    $option_ids   = $_POST['option_id']   ?? [];
    $option_texts = $_POST['option_text'] ?? [];

    // Count how many are non-empty
    $nonEmptyCount = 0;

    // Collect data for existing vs. new
    // We'll also figure out which existing IDs are "removed" if they aren't present
    // in the final submission. So let's see which IDs were originally there:
    $originalIDs = array_column($options, 'id'); // e.g. [12, 13, 14, ...]
    $postedExistingIDs = [];
    $updates = [];
    $inserts = [];

    // 1) Walk through the posted arrays by index
    foreach ($option_ids as $index => $oid) {
        $oid  = trim($oid);
        $text = trim($option_texts[$index] ?? '');

        // If text is empty, skip entirely (not saved)
        if ($text === '') {
            continue;
        }

        // We have a non-empty option
        $nonEmptyCount++;

        // If oid == '' => new option
        if ($oid === '') {
            $inserts[] = $text;
        } else {
            // existing
            $postedExistingIDs[] = (int)$oid;
            $updates[] = [
                'id'   => (int)$oid,
                'text' => $text,
            ];
        }
    }

    // 2) Check if fewer than 2 remain
    if ($nonEmptyCount < 2) {
        $errorMsg = "You must have at least 2 non-empty options.";
    } else {
        // 3) Save DB changes

        // (A) Update poll question
        $stmtQ = $pdo->prepare("UPDATE polls SET question = :q WHERE id = :pid");
        $stmtQ->execute([
            ':q'   => $question,
            ':pid' => $pollId
        ]);

        // (B) Delete any existing IDs that weren't posted => user removed them
        $removedIDs = array_diff($originalIDs, $postedExistingIDs);
        if (!empty($removedIDs)) {
            $placeholders = rtrim(str_repeat('?,', count($removedIDs)), ',');
            $sqlDel = "DELETE FROM poll_options WHERE poll_id = ? AND id IN ($placeholders)";
            $stmtDel = $pdo->prepare($sqlDel);
            $stmtDel->execute(array_merge([$pollId], $removedIDs));
        }

        // (C) Update the kept existing options
        foreach ($updates as $upd) {
            $stmtUp = $pdo->prepare("
                UPDATE poll_options 
                SET option_text = :txt
                WHERE id = :oid AND poll_id = :pid
            ");
            $stmtUp->execute([
                ':txt' => $upd['text'],
                ':oid' => $upd['id'],
                ':pid' => $pollId
            ]);
        }

        // (D) Insert new ones
        foreach ($inserts as $txt) {
            $stmtIns = $pdo->prepare("
                INSERT INTO poll_options (poll_id, option_text)
                VALUES (:pid, :txt)
            ");
            $stmtIns->execute([
                ':pid' => $pollId,
                ':txt' => $txt
            ]);
        }

        // Done
        header("Location: dashboard");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Umfrage Bearbeiten</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><!-- Mobile responsive -->
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Umfrage Bearbeiten (ID: <?php echo $pollId; ?>)</h2>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- (Optional) CSRF token 
        <input type="hidden" name="csrf_token" value="<?php //echo $_SESSION['csrf_token']; ?>">
        -->

        <!-- Poll Question -->
        <div class="mb-3">
            <label class="form-label">Frage</label>
            <input type="text" name="question" class="form-control"
                   value="<?php echo htmlspecialchars($poll['question']); ?>"
                   required>
        </div>

        <label class="form-label">Antworten</label>
        <div id="optionsContainer" class="mb-3">
            <!-- Show each existing option in a single list -->
            <?php foreach ($options as $opt): ?>
                <div class="input-group mb-2 option-row" data-option-row>
                    <!-- Hidden ID indicates an existing row -->
                    <input type="hidden" name="option_id[]" value="<?php echo $opt['id']; ?>">
                    <input type="text"
                           name="option_text[]"
                           class="form-control"
                           value="<?php echo htmlspecialchars($opt['option_text']); ?>"
                           required>
                    <button type="button" class="btn btn-outline-danger removeOptionBtn">
                        -
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="addOptionBtn" class="btn btn-secondary mb-3">
            + Option Hinzufügen
        </button>
        <br>

        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="dashboard" class="btn btn-secondary">Abbrechen</a>
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// We unify existing + new options in one container (#optionsContainer).
// Each row has: <input hidden> for ID (empty if new), <input text> for the text, a "Remove" btn.

// 1. Add row for NEW option
let newOptionCount = 0;
const optionsContainer = document.getElementById('optionsContainer');
const addOptionBtn = document.getElementById('addOptionBtn');

addOptionBtn.addEventListener('click', () => {
    newOptionCount++;
    const rowDiv = document.createElement('div');
    rowDiv.classList.add('input-group', 'mb-2', 'option-row');
    rowDiv.setAttribute('data-option-row', 'true');
    rowDiv.innerHTML = `
        <input type="hidden" name="option_id[]" value="">
        <input type="text" name="option_text[]" class="form-control" 
               placeholder="New Option ${newOptionCount}" required>
        <button type="button" class="btn btn-outline-danger removeOptionBtn">-</button>
    `;
    optionsContainer.appendChild(rowDiv);

    attachRemoveEvents();
    updateRemoveButtons();
});

// 2. Remove row from the DOM
function attachRemoveEvents() {
    // Attach click event to all remove buttons
    const removeBtns = document.querySelectorAll('.removeOptionBtn');
    removeBtns.forEach(btn => {
        btn.onclick = () => {
            const row = btn.closest('.option-row');
            row.remove();
            updateRemoveButtons();
        };
    });
}

// 3. Hide/disable remove if total rows <= 2
function updateRemoveButtons() {
    const rows = document.querySelectorAll('[data-option-row]');
    if (rows.length <= 2) {
        // Hide remove buttons
        rows.forEach(r => {
            const b = r.querySelector('.removeOptionBtn');
            b.style.display = 'none';
        });
    } else {
        // Show remove buttons
        rows.forEach(r => {
            const b = r.querySelector('.removeOptionBtn');
            b.style.display = '';
        });
    }
}

// On page load, attach remove events to existing rows & update
attachRemoveEvents();
updateRemoveButtons();
</script>
<?php include '../partials/footer.php'; ?>

</body>
</html>
