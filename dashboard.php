<?php
session_start();
require_once 'includes/setup.php';
require_once 'db_connection.php';

$showLoginForm = false;
$error = "";

if (!isset($_SESSION['user_id'])) {
    $showLoginForm = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email    = trim($_POST['email']);
        $password = $_POST['password'];

        if (!empty($email) && !empty($password)) {
            $query  = "SELECT id, username, password_hash FROM users WHERE email = ?";
            $stmt   = $conn->prepare($query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
            }
        } else {
            $error = "All fields are required.";
        }
    }
}
?>
<?php include 'includes/layout_head.php'; ?>
<?php include 'includes/header.php'; ?>

<div class="d-flex">
  <?php if (!$showLoginForm): ?>
    <?php include 'includes/sidebar.php'; ?>
  <?php endif; ?>

  <div class="main-content">
    <?php if ($showLoginForm): ?>
      <!-- Your existing login form here -->
    <?php else: ?>
      <?php include 'includes/mainContent.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/offcanvas.php'; ?>
<?php include 'includes/modals.php'; ?>
<?php include 'includes/scripts.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<script>
  // Cover click
  function bindCoverClicks() {
    $(document).off('click.cover','img.match-cover-img');
    $(document).on('click.cover','img.match-cover-img',function(e){
      e.preventDefault();
      const src = $(this).data('img-src')||$(this).attr('src');
      $('#coverModalImage').attr('src',src);
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById('coverModal')
      ).show();
    });
  }

  function filterMatches() {
    const maxD = parseFloat($('#distanceSlider').val()) || 0;
    const showA = $('#activeCheckbox').is(':checked');
    const showH = $('#hiddenCheckbox').is(':checked');

    $('#matchesContainer .main-row').each(function() {
      const $row = $(this);
      const dist = parseFloat($row.attr('data-distance')) || 0;
      const isHidden = $row.attr('data-hidden') === '1';
      const okActive = dist <= maxD && !isHidden && showA;
      const okHidden = dist <= maxD && isHidden && showH;
      const shouldShow = okActive || okHidden;

      $row.toggle(shouldShow);
      const id = $row.attr('data-user-id');
      $('#detail-' + id).toggle(
        shouldShow && $('#detail-' + id).is(':visible')
      );
    });
  }

  function sortMatches() {
    const mode = $('#matchSortSelect').val();
    const $tbody = $('#matchesContainer #matchesTable tbody');
    const rows = $tbody.find('tr.main-row').get();

    rows.sort((a, b) => {
      const A = $(a), B = $(b);
      if (mode === 'newest')   return B.data('match-time')  - A.data('match-time');
      if (mode === 'closest')  return A.data('distance')    - B.data('distance');
      if (mode === 'most')     return B.data('match-count') - A.data('match-count');
      return 0;
    });

    $tbody.empty();
    rows.forEach(row => {
      const $main = $(row);
      const id = $main.data('user-id');
      const $detail = $('#detail-' + id);
      $tbody.append($main).append($detail);
    });
  }

  function loadMatches() {
    $.get('includes/matches.php', function(html) {
      $('#matchesContainer').html(html);
      $('#activeCheckbox').prop('checked', true);
      $('#hiddenCheckbox').prop('checked', false);
      $('#distanceSlider').val(750);
      $('#distanceValue').text(750);
      sortMatches();
      filterMatches();
      bindCoverClicks();
    }).fail(() => {
      $('#matchesContainer').html('<div class="text-danger">Error loading matches.</div>');
    });
  }

  function loadWanted(){
    $.get('includes/wanted.php', function(html){
      $('#wantedContainer').html(html);
      bindCoverClicks();
    }).fail(()=>{
      $('#wantedContainer').html('<div class="text-danger">Error loading wanted.</div>');
    });
  }

  function loadForSale(){
    $.get('includes/forSale.php', function(html){
      $('#forsaleContainer').html(html);
      bindCoverClicks();
    }).fail(()=>{
      $('#forsaleContainer').html('<div class="text-danger">Error loading for sale.</div>');
    });
  }

  $(document).ready(function() {
    loadMatches();

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
      const tgt = $(e.target).attr('data-bs-target');
      if      (tgt === '#matchesTab') loadMatches();
      else if (tgt === '#wantedTab')  loadWanted();
      else if (tgt === '#forsaleTab') loadForSale();
    });

    $(document)
      .on('change', '#matchSortSelect', sortMatches)
      .on('input change', '#distanceSlider', function() {
        $('#distanceValue').text(this.value);
        filterMatches();
      })
      .on('change', '#activeCheckbox, #hiddenCheckbox', filterMatches)
      .on('click', '.expand-btn', function() {
        const id = $(this).data('user-id');
        $('#detail-' + id).toggle();
      })
      .on('click', '.delete-match-btn', function() {
        const uid = $(this).data('match-user-id');
        if (!confirm('Permanently delete this match?')) return;
        $.post(
          '/comicsmp/api/deleteMatch.php',
          { match_user_id: uid },
          function(res) {
            if (res.status === 'success') {
              $(`.main-row[data-user-id=${uid}]`).remove();
              $(`#detail-${uid}`).remove();
            } else {
              alert('Error: ' + res.message);
            }
          },
          'json'
        ).fail(function(xhr, status, err) {
          console.error('Delete error', status, err, xhr.responseText);
          alert('Server error deleting match.');
        });
      })
      .on('click', '.hide-btn', function(e) {
        e.stopPropagation();
        const $btn = $(this);
        const uid = $btn.data('other-user-id');
        const $row = $(`.main-row[data-user-id="${uid}"]`);
        const currentlyHidden = $row.data('hidden') === 1;
        const endpoint = currentlyHidden
                        ? '/comicsmp/api/unhideMatch.php'
                        : '/comicsmp/api/hideMatch.php';

        $.post(
          endpoint,
          { match_user_id: uid },
          function(res) {
            if (res.status === 'success') {
              const newHidden = currentlyHidden ? 0 : 1;
              $row.attr('data-hidden', newHidden);
              $row.data('hidden', newHidden);
              $btn.text(newHidden ? 'Unhide' : 'Hide');
              filterMatches();
            } else {
              alert('Error: ' + res.message);
            }
          },
          'json'
        ).fail(function(xhr, status, err) {
          console.error('Hide/unhide AJAX error', status, err);
          alert('Server error toggling hide.');
        });
      })
      .on('click', '.pm-btn', function() {
        const uid = $(this).data('user-id');
        alert('Open PM to user ' + uid);
      });
  });

   // 🔧 Fix: Profile tab activation + cleanly remove it on tab switch
  document.addEventListener("DOMContentLoaded", function () {
    const profileLink = document.querySelector('a.dropdown-item[data-bs-target="#profile"]');
    const profileTabPane = document.querySelector('#profile');

    if (profileLink && profileTabPane) {
      profileLink.addEventListener("click", function (e) {
        e.preventDefault();
        // Deactivate all tab panes
        document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('show', 'active'));
        // Activate profile
        profileTabPane.classList.add('show', 'active');
      });

      // Clean up: when any other tab is activated via Bootstrap, remove profile tab active class
      document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tabToggle => {
        tabToggle.addEventListener('shown.bs.tab', function () {
          if (profileTabPane.classList.contains('active')) {
            profileTabPane.classList.remove('show', 'active');
          }
        });
      });
    }
  });

</script>
</body>
</html>
