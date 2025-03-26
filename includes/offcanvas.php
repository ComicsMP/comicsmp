<!-- Offcanvas Search Filters -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="searchFiltersOffcanvas" aria-labelledby="searchFiltersLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="searchFiltersLabel">Search Filters</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div class="advanced-search">
      <!-- Search input field -->
      <div class="search-input-container">
        <input type="text" id="comicTitle" class="modern-input" placeholder="Start typing comic title..." autocomplete="off">
        <div id="suggestions"></div>
      </div>
      <!-- Search Mode Toggle (Added margin-top to lower it) -->
      <div class="search-mode-group" style="margin-top: 10px;">
        <button type="button" class="btn btn-outline-primary search-mode" data-mode="allWords">All Words</button>
        <button type="button" class="btn btn-outline-primary search-mode" data-mode="anyWords">Any Words</button>
        <button type="button" class="btn btn-outline-primary search-mode active" data-mode="startsWith">Starts With</button>
      </div>
      <!-- Country Filter -->
      <div class="filter-group">
        <label for="countrySelect">Country</label>
        <select id="countrySelect" class="form-select">
          <?php
            if ($resultCountries) {
              while ($row = mysqli_fetch_assoc($resultCountries)) {
                $country = $row['Country'];
                $selected = ($country == "USA") ? "selected" : "";
                echo "<option value=\"$country\" $selected>$country</option>";
              }
            }
          ?>
        </select>
      </div>
      <!-- Year Filter -->
      <div class="filter-group" id="yearFilterGroup">
        <label for="yearSelect">Year</label>
        <select id="yearSelect" class="form-select">
          <option value="">Select a year</option>
          <!-- Year options will be loaded dynamically -->
        </select>
      </div>
    </div>
  </div>
</div>
