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
      <!-- Search Mode Toggle -->
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
      <!-- Tab Filter (Cover Type) -->
      <div class="filter-group" id="tabFilterGroup">
        <label for="tabSelect">Cover Type</label>
        <select id="tabSelect" class="form-select" disabled>
          <option value="">Select a cover type</option>
        </select>
      </div>
      <!-- Year Filter -->
      <div class="filter-group" id="yearFilterGroup">
        <label for="yearSelect">Year</label>
        <select id="yearSelect" class="form-select" disabled>
          <option value="">Select a year</option>
        </select>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Bundle (includes Popper and Offcanvas JS) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Offcanvas Filter Logic -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  const titleInput = document.getElementById("comicTitle");
  const countrySelect = document.getElementById("countrySelect");
  const tabSelect = document.getElementById("tabSelect");
  const yearSelect = document.getElementById("yearSelect");

  function resetDropdown(selectElement, placeholderText) {
    selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
    selectElement.disabled = true;
  }

  // Load Tabs when title changes
  titleInput.addEventListener("change", function () {
    // Convert the comic title to uppercase to match your DB values
    const title = titleInput.value.trim().toUpperCase();
    const country = countrySelect.value;

    resetDropdown(tabSelect, "Select a cover type");
    resetDropdown(yearSelect, "Select a year");

    if (title === "") return;

    // This assumes getTabs.php is in the root of /comicsmp/
    fetch(`getTabs.php?comic_title=${encodeURIComponent(title)}&country=${encodeURIComponent(country)}&returnJson=1`)
      .then(response => response.json())
      .then(tabs => {
        if (Array.isArray(tabs) && tabs.length > 0) {
          let issuesFound = false;
          tabs.forEach(tab => {
            const option = document.createElement("option");
            option.value = tab;
            option.textContent = tab;
            // Auto-select if the tab is "Issues"
            if (tab.toUpperCase() === "ISSUES") {
              option.selected = true;
              issuesFound = true;
            }
            tabSelect.appendChild(option);
          });
          tabSelect.disabled = false;
          // If "Issues" is available, dispatch a change event to load years
          if (issuesFound) {
            tabSelect.dispatchEvent(new Event('change'));
          }
        }
      })
      .catch(error => {
        console.error("Error fetching tabs:", error);
      });
  });

  // Load Years when Tab is selected
  tabSelect.addEventListener("change", function () {
    const title = titleInput.value.trim().toUpperCase();
    const country = countrySelect.value;
    const tab = tabSelect.value;

    resetDropdown(yearSelect, "Select a year");

    if (!title || !tab) return;

    // This assumes get_years.php is in the root of /comicsmp/
    fetch(`getYears.php?comic_title=${encodeURIComponent(title)}&country=${encodeURIComponent(country)}&tab=${encodeURIComponent(tab)}`)
      .then(response => response.text())
      .then(optionsHtml => {
        yearSelect.innerHTML = optionsHtml;
        yearSelect.disabled = false;
      })
      .catch(error => {
        console.error("Error fetching years:", error);
      });
  });
});
</script>
