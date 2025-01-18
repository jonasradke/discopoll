<?php
include_once '../config.php';
include '../partials/header.php'; // Shared header, if desired

// Send no-cache headers (so user can't see a stale form after logout, etc.)
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check admin session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit;
}

// (Optional) generate CSRF token if missing
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Server-side error message (e.g., if fewer than 2 options)
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token (optional)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $question = trim($_POST['question'] ?? '');
    $option_texts = $_POST['option_text'] ?? []; 
    // We'll treat each row's text as a "new option."

    // 1) Collect non-empty texts
    $cleanOptions = [];
    foreach ($option_texts as $txt) {
        $txt = trim($txt);
        if ($txt !== '') {
            $cleanOptions[] = $txt;
        }
    }
    $nonEmptyCount = count($cleanOptions);

    // 2) Check if fewer than 2 remain
    if ($nonEmptyCount < 2) {
        $errorMsg = "Du musst mindestens 2 nicht-leere Antworten angeben.";
    } else {
        // 3) Insert poll
        $stmt = $pdo->prepare("INSERT INTO polls (question, created_at) 
                               VALUES (:question, NOW())");
        $stmt->execute([':question' => $question]);
        $poll_id = $pdo->lastInsertId();

        // 4) Insert each new option
        $stmtOpt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text)
                                  VALUES (:poll_id, :option_text)");
        foreach ($cleanOptions as $txt) {
            $stmtOpt->execute([
                ':poll_id'     => $poll_id,
                ':option_text' => $txt
            ]);
        }

        // (Re)generate CSRF token to avoid replay
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // 5) Redirect to dashboard (rewriting hides '.php')
        header('Location: dashboard');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Umfrage Erstellen</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"><!-- Mobile responsive -->
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="mb-4">Umfrage Erstellen</h2>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">Frage</label>
                    <input type="text" name="question" class="form-control" 
                           value="<?php echo htmlspecialchars($question ?? ''); ?>" 
                           required>
                </div>

                <label class="form-label">Antworten</label>
                <div id="optionsContainer" class="mb-3">
                    <!-- 2 default rows -->
                    <!-- Each row has a data attribute + remove button (like edit poll) -->
                    <div class="input-group mb-2 option-row" data-option-row>
                        <input type="text" name="option_text[]" class="form-control" 
                               placeholder="Option 1" required>
                        <button type="button" class="btn btn-outline-danger removeOptionBtn"> - </button>
                    </div>
                    <div class="input-group mb-2 option-row" data-option-row>
                        <input type="text" name="option_text[]" class="form-control"
                               placeholder="Option 2" required>
                        <button type="button" class="btn btn-outline-danger removeOptionBtn"> - </button>
                    </div>
                </div>

                <button type="button" id="addOptionBtn" class="btn btn-secondary mb-3">
                    + Option Hinzufügen
                </button>
                <br>

                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn btn-primary">Umfrage Erstellen</button>
                    <a href="dashboard" class="btn btn-outline-danger">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Similar logic to "edit poll":
// - Each row has a "remove" button
// - We can't remove if total rows <= 2
// - We can add new rows

let newOptionCount = 2; // starts at 2
const optionsContainer = document.getElementById('optionsContainer');
const addOptionBtn     = document.getElementById('addOptionBtn');

function attachRemoveEvents() {
    document.querySelectorAll('.removeOptionBtn').forEach(btn => {
        btn.onclick = () => {
            const row = btn.closest('.option-row');
            row.remove();
            updateRemoveButtons();
        };
    });
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('[data-option-row]');
    if (rows.length <= 2) {
        // Hide remove buttons if only 2 left
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

// On "Add Option" click, create a new row
addOptionBtn.addEventListener('click', () => {
    newOptionCount++;
    const row = document.createElement('div');
    row.classList.add('input-group', 'mb-2', 'option-row');
    row.setAttribute('data-option-row', 'true');
    row.innerHTML = `
        <input type="text" name="option_text[]" class="form-control"
               placeholder="Option ${newOptionCount}" required>
        <button type="button" class="btn btn-outline-danger removeOptionBtn"> - </button>
    `;
    optionsContainer.appendChild(row);

    attachRemoveEvents();
    updateRemoveButtons();
});

// Initial attach
attachRemoveEvents();
updateRemoveButtons();
</script>

<!-- Shared footer partial, if needed -->
<?php include '../partials/footer.php'; ?>

</body>
</html>
