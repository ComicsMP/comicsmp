<?php
// only start a session if one isn’t already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connection.php';

// ── Load user and their favorites ──
$user_id = $_SESSION['user_id'] ?? 0;
$favoritedTitles = [];
if ($user_id) {
    $stmt = $conn->prepare("
      SELECT comic_title, years
        FROM user_favorite_titles
       WHERE user_id = ?
      ORDER BY favorited_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $favoritedTitles[] = $r;
    }
    $stmt->close();
}
?>

<!-- Offcanvas Search Filters -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="searchFiltersOffcanvas" aria-labelledby="searchFiltersLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="searchFiltersLabel">Search Filters</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div class="advanced-search">

      <!-- My Favorites Card -->
      <?php if ($user_id && count($favoritedTitles)): ?>
  <div class="card mb-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-bold">My Favorites</span>
      <?php if (count($favoritedTitles) > 5): ?>
        <a href="#" id="expandFavoritesBtn" class="text-primary small">Show All</a>
      <?php endif; ?>
    </div>
    <ul class="list-group list-group-flush" id="favoritesList">
      <?php foreach ($favoritedTitles as $i => $fav): ?>
        <li class="list-group-item favorite-item <?= $i >= 5 ? 'extra-favorite d-none' : '' ?>">
          <a href="#" class="favorite-link" data-title="<?= htmlspecialchars($fav['comic_title'], ENT_QUOTES) ?>" data-year="<?= htmlspecialchars($fav['years'], ENT_QUOTES) ?>">
            <?= htmlspecialchars($fav['comic_title']) ?>
            <small class="text-muted">(<?= htmlspecialchars($fav['years']) ?>)</small>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>



      <!-- Title & Filters Card -->
      <div class="card mb-4 shadow-sm">
        <div class="card-header fw-bold">Search by Title</div>
        <div class="card-body">
          <!-- Comic Title Input -->
          <div class="mb-3">
            <label for="comicTitle" class="form-label fw-bold">Comic Title</label>
            <input type="text"
                   id="comicTitle"
                   class="form-control modern-input"
                   placeholder="Start typing comic title..."
                   autocomplete="off">
            <div id="suggestions" class="mt-1"></div>
          </div>

          <!-- Search Mode Toggle -->
          <div class="btn-group mb-3" role="group" aria-label="Search mode">
            <button type="button" class="btn btn-outline-primary search-mode" data-mode="smart">Smart</button>
            <button type="button" class="btn btn-outline-primary search-mode active" data-mode="startsWith">Starts With</button>
          </div>

          <!-- Country & Year Side-by-Side -->
          <div class="row g-3">
            <div class="col-6">
              <label for="countrySelect" class="form-label fw-bold">Country</label>
              <select id="countrySelect" class="form-select">
                <?php
                  if (isset($resultCountries)) {
                    while ($row = mysqli_fetch_assoc($resultCountries)) {
                      $country  = $row['Country'];
                      $selected = ($country === "USA") ? "selected" : "";
                      echo "<option value=\"$country\" $selected>$country</option>";
                    }
                  }
                ?>
              </select>
            </div>
            <div class="col-6">
              <label for="yearSelect" class="form-label fw-bold">Year</label>
              <select id="yearSelect" class="form-select">
                <option value="">Select a year</option>
                <!-- dynamically loaded -->
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- UPC Search Card -->
      <div class="card mb-4 shadow-sm">
        <div class="card-header fw-bold">Search by UPC</div>
        <div class="card-body">
          <div class="mb-3">
            
            <input type="text"
                   id="upcSearch"
                   class="form-control"
                   placeholder="e.g., 759606209088-00311 (w/wo dash)">
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
