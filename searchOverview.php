<?php
session_start();
require_once 'db_connection.php';
if (!isset($_SESSION['user_id'])) {
    echo "Please log in.";
    exit;
}
?>
<div>
  <h4>Comic Search</h4>
  <form method="get" action="searchResults.php" id="dashboardSearchForm">
    <div class="mb-3">
      <label for="searchQuery" class="form-label">Enter Comic Title or Keyword</label>
      <input type="text" name="query" id="searchQuery" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
</div>
