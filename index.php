<?php
include_once 'config.php';
include 'partials/header.php';

// Fetch all non-archived polls (latest first)
$stmt = $pdo->query("SELECT * FROM polls WHERE archived = 0 ORDER BY created_at DESC");
$allPolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alle Laufenden Umfragen</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Optional custom CSS -->
    <link href="assets/style.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive -->

    <!-- Vote Confirmation Modal -->
    <div id="voteConfirmModal" class="vote-confirm-modal" style="display: none;">
        <div class="vote-confirm-content">
            <div class="vote-confirm-header">
                <h3>🗳️ Stimme bestätigen</h3>
            </div>
            <div class="vote-confirm-body">
                <div class="vote-confirm-icon">
                    <div class="vote-icon">🗳️</div>
                </div>
                <p class="vote-confirm-text">Möchten Sie wirklich für folgende Option stimmen?</p>
                <div class="selected-option">
                    <strong id="selectedOptionText"></strong>
                </div>
                <p class="vote-warning">⚠️ Sie können Ihre Stimme nach dem Abgeben nicht mehr ändern!</p>
            </div>
            <div class="vote-confirm-footer">
                <button class="btn btn-secondary" onclick="cancelVote()">❌ Abbrechen</button>
                <button class="btn btn-primary" onclick="confirmVote()">✅ Bestätigen</button>
            </div>
        </div>
    </div>

    <!-- jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Optional: Animate progress bars smoothly */
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }

        /* Vote Confirmation Modal */
        .vote-confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vote-confirm-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .vote-confirm-header h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.3rem;
        }

        .vote-confirm-icon {
            margin: 1rem 0;
        }

        .vote-icon {
            font-size: 3rem;
            animation: bounce 1s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .vote-confirm-text {
            color: #666;
            margin: 1rem 0;
            font-size: 1rem;
        }

        .selected-option {
            background: #f8f9fa;
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            color: #007bff;
            font-size: 1.1rem;
        }

        .vote-warning {
            color: #dc3545;
            font-size: 0.9rem;
            margin: 1rem 0;
            font-weight: 500;
        }

        .vote-confirm-footer {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .vote-confirm-footer .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .vote-confirm-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Reactions */
        .reactions-section {
            border-top: 1px solid #e9ecef;
            padding-top: 0.75rem;
        }

        .reactions-display {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .reaction-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            user-select: none;
        }

        .reaction-btn:hover {
            background: #e9ecef;
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .reaction-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
            transform: scale(1.1);
        }

        .reaction-count {
            font-weight: 500;
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <h1 class="mb-4 text-center">Alle Laufenden Umfragen</h1>
    
    <?php if (count($allPolls) === 0): ?>
        <div class="alert alert-info text-center">Keine Umfragen verfügbar.</div>
    <?php else: ?>
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
                        
                        <!-- Reactions Section -->
                        <div class="reactions-section mt-3" data-poll-id="<?php echo $poll['id']; ?>">
                            <div class="reactions-display">
                                <span class="reaction-btn" data-reaction="👍" title="Like">👍 <span class="reaction-count" data-reaction="👍">0</span></span>
                                <span class="reaction-btn" data-reaction="❤️" title="Love">❤️ <span class="reaction-count" data-reaction="❤️">0</span></span>
                                <span class="reaction-btn" data-reaction="😂" title="Laugh">😂 <span class="reaction-count" data-reaction="😂">0</span></span>
                                <span class="reaction-btn" data-reaction="😮" title="Wow">😮 <span class="reaction-count" data-reaction="😮">0</span></span>
                                <span class="reaction-btn" data-reaction="😢" title="Sad">😢 <span class="reaction-count" data-reaction="😢">0</span></span>
                            </div>
                        </div>
                        
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
            const selectedOption = $(this).find('input[name="option_id"]:checked').next('label').text();

            // Show custom confirmation popup
            showVoteConfirmation(selectedOption, function() {
                // User confirmed, proceed with vote
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

        // Vote confirmation functionality
        let voteCallback = null;

        function showVoteConfirmation(selectedOption, callback) {
            document.getElementById('selectedOptionText').textContent = selectedOption;
            document.getElementById('voteConfirmModal').style.display = 'flex';
            voteCallback = callback;
        }

        function confirmVote() {
            document.getElementById('voteConfirmModal').style.display = 'none';
            if (voteCallback) {
                voteCallback();
            }
        }

        function cancelVote() {
            document.getElementById('voteConfirmModal').style.display = 'none';
            voteCallback = null;
        }

        // Close modal when clicking outside
        document.getElementById('voteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cancelVote();
            }
        });

        // Reactions functionality
        $('.reaction-btn').on('click', function() {
            const pollId = $(this).closest('.reactions-section').data('poll-id');
            const reaction = $(this).data('reaction');
            const $btn = $(this);
            
            // Toggle reaction
            if ($btn.hasClass('active')) {
                // Remove reaction
                removeReaction(pollId, reaction, $btn);
            } else {
                // Add reaction
                addReaction(pollId, reaction, $btn);
            }
        });

        function addReaction(pollId, reaction, $btn) {
            $.post('add_reaction.php', { 
                poll_id: pollId, 
                reaction: reaction 
            }, function(response) {
                if (response.success) {
                    $btn.addClass('active');
                    updateReactionCount($btn, 1);
                }
            }, 'json');
        }

        function removeReaction(pollId, reaction, $btn) {
            $.post('remove_reaction.php', { 
                poll_id: pollId, 
                reaction: reaction 
            }, function(response) {
                if (response.success) {
                    $btn.removeClass('active');
                    updateReactionCount($btn, -1);
                }
            }, 'json');
        }

        function updateReactionCount($btn, change) {
            const $count = $btn.find('.reaction-count');
            const currentCount = parseInt($count.text()) || 0;
            const newCount = Math.max(0, currentCount + change);
            $count.text(newCount);
        }

        // Load existing reactions on page load
        $('.reactions-section').each(function() {
            const pollId = $(this).data('poll-id');
            loadReactions(pollId);
        });

        function loadReactions(pollId) {
            $.get('get_reactions.php', { poll_id: pollId }, function(data) {
                if (data.success) {
                    data.reactions.forEach(function(reaction) {
                        const $btn = $(`.reactions-section[data-poll-id="${pollId}"] .reaction-btn[data-reaction="${reaction.reaction}"]`);
                        $btn.find('.reaction-count').text(reaction.count);
                        if (reaction.user_reacted) {
                            $btn.addClass('active');
                        }
                    });
                }
            }, 'json');
        }
});
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'partials/footer.php'; ?>
</body>
</html>
