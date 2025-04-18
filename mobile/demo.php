<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Filter Modal Demo</title>
  <style>
    /* Basic reset and styling */
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #f4f4f4;
    }
    .content {
      padding: 20px;
    }
    /* Floating Filter Button */
    .filter-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background-color: #007BFF;
      color: #fff;
      padding: 15px;
      border: none;
      border-radius: 50%;
      font-size: 16px;
      cursor: pointer;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    /* Modal Overlay */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
    }
    /* Modal Content */
    .modal-content {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      width: 90%;
      max-width: 400px;
      margin: auto;
    }
    /* Close Button */
    .close {
      float: right;
      font-size: 24px;
      cursor: pointer;
    }
    /* Filter Section */
    .filter-section {
      margin-bottom: 15px;
    }
    .filter-section label {
      display: block;
      margin-bottom: 5px;
    }
    .apply-btn {
      background: #28A745;
      color: #fff;
      padding: 10px;
      width: 100%;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
    }
    /* Demo match content */
    .match {
      background: #fff;
      margin-bottom: 10px;
      padding: 10px;
      border-radius: 4px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  
  <div class="content">
    <h1>Matches</h1>
    <p>This is some dummy content for matches. Use the filter button to adjust your view.</p>
    <div class="match">Match 1: Dummy data...</div>
    <div class="match">Match 2: Dummy data...</div>
    <div class="match">Match 3: Dummy data...</div>
    <div class="match">Match 4: Dummy data...</div>
    <div class="match">Match 5: Dummy data...</div>
  </div>
  
  <!-- Filter Button -->
  <button id="filterButton" class="filter-btn">Filters</button>
  
  <!-- Filter Modal -->
  <div id="filterModal" class="modal">
    <div class="modal-content">
      <span id="closeModal" class="close">&times;</span>
      <h3>Filter Matches</h3>
      <!-- Distance Slider -->
      <div class="filter-section">
        <label for="distanceSlider">Max Distance: <span id="distanceValue">500 mi</span></label>
        <input type="range" id="distanceSlider" min="0" max="1000" value="500" step="10">
      </div>
      <!-- Sorting Options -->
      <div class="filter-section">
        <label for="sortOptions">Sort By:</label>
        <select id="sortOptions">
          <option value="newest">Newest Matches</option>
          <option value="closest">Closest</option>
          <option value="most">Most Matches</option>
        </select>
      </div>
      <!-- Apply Button -->
      <button id="applyFilters" class="apply-btn">Apply</button>
    </div>
  </div>
  
  <script>
    // Get modal elements
    const filterButton = document.getElementById('filterButton');
    const filterModal = document.getElementById('filterModal');
    const closeModal = document.getElementById('closeModal');
    const distanceSlider = document.getElementById('distanceSlider');
    const distanceValue = document.getElementById('distanceValue');
    const applyFilters = document.getElementById('applyFilters');
    const sortOptions = document.getElementById('sortOptions');

    // Open the modal when the filter button is clicked
    filterButton.addEventListener('click', () => {
      filterModal.style.display = 'flex';
    });

    // Close the modal when the close button is clicked
    closeModal.addEventListener('click', () => {
      filterModal.style.display = 'none';
    });

    // Update distance label as the slider moves
    distanceSlider.addEventListener('input', function() {
      distanceValue.textContent = this.value + " mi";
    });

    // Apply filter button - demo only
    applyFilters.addEventListener('click', () => {
      const maxDistance = distanceSlider.value;
      const sortBy = sortOptions.value;
      
      console.log("Filtering with max distance:", maxDistance, "and sorting by:", sortBy);
      
      // You could add your filtering logic here (AJAX or client-side filtering)
      
      // For now, simply close the modal
      filterModal.style.display = 'none';
    });

    // Close modal if user clicks outside the modal content
    window.addEventListener('click', function(event) {
      if (event.target === filterModal) {
        filterModal.style.display = 'none';
      }
    });
  </script>
</body>
</html>
