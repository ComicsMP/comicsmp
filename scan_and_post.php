<?php
session_start();
require_once 'db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scan Comic UPC</title>
  <!-- Bootstrap CSS for styling -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <h2>Scan Comic UPC</h2>
    <!-- UPC Scanning Form -->
    <form id="scanForm">
      <div class="mb-3">
        <label for="upc" class="form-label">UPC Code</label>
        <input type="text" id="upc" name="upc" class="form-control" placeholder="Scan or type UPC code" required>
      </div>
      <button type="submit" class="btn btn-primary">Fetch Comic Details</button>
    </form>
    <hr>
    <!-- Listing Form: Populated automatically from UPC scan -->
    <form id="listingForm" method="post" action="addListing.php">
      <div class="mb-3">
        <label for="comic_title" class="form-label">Comic Title</label>
        <input type="text" id="comic_title" name="comic_title" class="form-control" readonly>
      </div>
      <div class="mb-3">
        <label for="volume" class="form-label">Volume</label>
        <input type="text" id="volume" name="volume" class="form-control" readonly>
      </div>
      <div class="mb-3">
        <label for="issue_number" class="form-label">Issue Number</label>
        <input type="text" id="issue_number" name="issue_number" class="form-control" readonly>
      </div>
      <div class="mb-3">
        <label for="image_path" class="form-label">Image Path</label>
        <input type="text" id="image_path" name="image_path" class="form-control" readonly>
      </div>
      <!-- Manual entries for seller -->
      <div class="mb-3">
        <label for="grade" class="form-label">Grade</label>
        <input type="text" id="grade" name="grade" class="form-control" placeholder="Enter comic grade" required>
      </div>
      <div class="mb-3">
        <label for="price" class="form-label">Price</label>
        <input type="text" id="price" name="price" class="form-control" placeholder="Enter sale price" required>
      </div>
      <button type="submit" class="btn btn-success">Post Listing</button>
    </form>
  </div>

  <script>
    // Initially hide the listing form until comic details are fetched
    document.getElementById('listingForm').style.display = 'none';

    document.getElementById('scanForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const upc = document.getElementById('upc').value;
      if(upc.trim() === '') return;

      // Use AJAX (fetch API) to call the PHP backend and fetch comic details
      fetch('getComicByUPC.php?upc=' + encodeURIComponent(upc))
      .then(response => response.json())
      .then(data => {
        if(data.success) {
          // Populate form fields with retrieved comic details
          document.getElementById('comic_title').value = data.comic_title;
          document.getElementById('volume').value = data.volume;
          document.getElementById('issue_number').value = data.issue_number;
          document.getElementById('image_path').value = data.image_path;
          // Display the listing form
          document.getElementById('listingForm').style.display = 'block';
        } else {
          alert('Comic not found for UPC: ' + upc);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while fetching comic details.');
      });
    });
  </script>
</body>
</html>
