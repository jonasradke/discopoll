<?php
include_once 'config.php';
include 'partials/header.php';

// Check if a specific poll ID is requested
$pollId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($pollId) {
    // Show only the specific poll
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = :id AND archived = 0");
    $stmt->execute([':id' => $pollId]);
    $allPolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allPolls)) {
        // Poll not found or archived
        $allPolls = [];
    }
} else {
    // Fetch all non-archived polls (latest first)
    $stmt = $pdo->query("SELECT * FROM polls WHERE archived = 0 ORDER BY created_at DESC");
    $allPolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pollId ? 'Umfrage' : 'Alle Laufenden Umfragen'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Optional custom CSS -->
    <link href="assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive -->

    <!-- jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Optional: Animate progress bars smoothly */
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <?php if ($pollId): ?>
        <h1 class="mb-4 text-center">Umfrage</h1>
        <?php if (count($allPolls) === 0): ?>
            <div class="alert alert-warning text-center">
                <h4>Umfrage nicht gefunden</h4>
                <p>Diese Umfrage existiert nicht oder wurde archiviert.</p>
                <a href="/" class="btn btn-primary">Alle Umfragen anzeigen</a>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mb-4">
                <i class="bi bi-qr-code"></i> Sie haben diese Umfrage über einen QR-Code aufgerufen.
                <a href="/" class="btn btn-outline-primary btn-sm ms-2">Alle Umfragen anzeigen</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <h1 class="mb-4 text-center">Alle Laufenden Umfragen</h1>
    <?php endif; ?>
    
    <?php if (count($allPolls) === 0 && !$pollId): ?>
        <div class="alert alert-info text-center">Keine Umfragen verfügbar.</div>
    <?php elseif (count($allPolls) > 0): ?>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <?php foreach ($allPolls as $poll): ?>
                <?php
                // Fetch poll options
                $stmtOptions = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = :poll_id");
                $stmtOptions->execute([':poll_id' => $poll['id']]);
                $options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);

                // Check if this user has already voted on this poll using cookies
                $hasVoted = isset($_COOKIE["voted_poll_{$poll['id']}"]);
                ?>
                
                <div class="card shadow-sm h-100" style="width: 22rem;">
                    <div class="card-body d-flex flex-column">
                        <!-- Poll Question -->
                        <h2 class="card-title h5 mb-3">
                            <?php echo htmlspecialchars($poll['question']); ?>
                        </h2>

                        <?php if (!$hasVoted): ?>
                            <!-- User has NOT voted. Show ONLY the form. -->
                            <form class="pollForm h-100 d-flex flex-column justify-content-between"
                                  id="pollForm_<?php echo $poll['id']; ?>"
                                  data-pollid="<?php echo $poll['id']; ?>"
                                  data-hasvoted="0">
                                <div>
                                    <?php foreach ($options as $option): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="option_id"
                                                   value="<?php echo $option['id']; ?>"
                                                   required>
                                            <label class="form-check-label">
                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-primary mt-2 w-100">
                                    Abstimmen
                                </button>
                            </form>

                        <?php else: ?>
                            <!-- User HAS voted. Show ONLY the results area. -->
                            <div class="pollResults h-100 d-flex flex-column justify-content-center"
                                 id="results_<?php echo $poll['id']; ?>"
                                 data-hasvoted="1"
                                 data-pollid="<?php echo $poll['id']; ?>">
                                <!-- Will be populated by AJAX below -->
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div> <!-- End card -->
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Function to fetch results for a specific poll and animate progress bars
function fetchResults(pollId) {
  $.get('results.php', { poll_id: pollId }, function(data) {
    let totalVotes = data.reduce((sum, item) => sum + parseInt(item.votes, 10), 0);
    if (totalVotes === 0) totalVotes = 1;

    const $container = $('#results_' + pollId);

    data.forEach(item => {
      const optionId  = item.id;
      const text      = item.option_text;
      const votes     = parseInt(item.votes, 10);
      const newPerc   = Math.round((votes / totalVotes) * 100);

      let $barWrapper = $container.find(`.poll-option[data-option-id="${optionId}"]`);
      if ($barWrapper.length === 0) {
        $barWrapper = $(`
          <div class="mb-2 poll-option" data-option-id="${optionId}">
            <small class="option-label"></small>
            <div class="progress">
              <div class="progress-bar"
                   role="progressbar"
                   aria-valuemin="0"
                   aria-valuemax="100"
                   aria-valuenow="0"
                   style="width: 0%;">
              </div>
            </div>
          </div>
        `);
        $container.append($barWrapper);
      }

      $barWrapper.find('.option-label').text(`${text} (${votes} Stimme${votes !== 1 ? 'n' : ''})`);
      const $bar = $barWrapper.find('.progress-bar');
      let oldPercent = parseInt($bar.attr('aria-valuenow'), 10) || 0;
      $bar.css('width', oldPercent + '%');
      setTimeout(() => {
        $bar.attr('aria-valuenow', newPerc).css('width', newPerc + '%').text(newPerc + '%');
      }, 50);
    });
  }, 'json');
}

$(document).ready(function() {
    $('[data-hasvoted="1"]').each(function() {
        const pollId = $(this).data('pollid');
        fetchResults(pollId);
        setInterval(() => fetchResults(pollId), 5000);
    });

    $('.pollForm').on('submit', function(e) {
        e.preventDefault();
        const pollId   = $(this).data('pollid');
        const optionId = $(this).find('input[name="option_id"]:checked').val();
        const $form    = $(this);

        $.post('vote.php', { poll_id: pollId, option_id: optionId }, function() {
            document.cookie = `voted_poll_${pollId}=1; path=/; max-age=${30 * 24 * 60 * 60}`;
            $form.closest('.card-body').html(`
                <div class="pollResults h-100 d-flex flex-column justify-content-center"
                     id="results_${pollId}"
                     data-hasvoted="1"
                     data-pollid="${pollId}">
                </div>
            `);
            fetchResults(pollId);
            setInterval(() => fetchResults(pollId), 5000);
        });
    });
});
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'partials/footer.php'; ?>
</body>
</html>
