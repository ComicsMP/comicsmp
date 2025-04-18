<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mobile Comic Search & Listing</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      text-align: center;
      background-color: #f4f4f4;
      padding: 20px;
    }
    .container {
      margin: 20px auto;
      width: 90%;
      max-width: 400px;
      background: white;
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .section {
      display: none;
    }
    .active {
      display: block;
    }
    /* Step 1: Candidate images */
    .comic-result img,
    .candidate img {
      display: block;
      width: 100%;
      height: 220px;
      object-fit: contain;
      margin: 0;
      padding: 0;
    }
    /* Single candidate override: make image slightly larger */
    .single-candidate img {
      height: 250px;
    }
    /* New: Full width image override */
    .full-width-image {
      width: 100% !important;
      height: auto !important;
      display: block;
    }
    /* Step 2: Listing cover image larger */
    .listing-cover img {
      width: 100%;
      height: 300px;
      object-fit: contain;
    }
    /* Remove extra borders for step 2 inner frames */
    .listing-form, .listing-cover, .success-message {
      border: none;
      padding: 0;
      box-shadow: none;
    }
    button, input[type=file], input[type=text], input[type=number], select {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      font-size: 16px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      display: block;
    }
    button {
      background-color: #007BFF;
      color: white;
    }
    button:active {
      background-color: #0056b3;
    }
    #cameraInput, #fileInput {
      display: none;
    }
    #loading {
      display: none;
      font-size: 18px;
      font-weight: bold;
      color: #007BFF;
    }
    #loading span {
      display: inline-block;
      animation: dots 1.5s infinite;
    }
    #loading span:nth-child(2) { animation-delay: 0.3s; }
    #loading span:nth-child(3) { animation-delay: 0.6s; }
    @keyframes dots {
      0% { opacity: 1; }
      50% { opacity: 0.3; }
      100% { opacity: 1; }
    }
    .candidate-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-around;
    }
    .candidate {
      width: 48%;
      margin-bottom: 5px;
      padding: 0;
    }
    /* Candidate info block with no gap */
    .candidate-info, .single-info {
      text-align: left;
      font-size: 14px;
      margin: 5px 0 0 0;
      padding: 0;
      line-height: 1;
    }
    /* Adjust multi-candidate info block to reduce gap */
    .candidate-info {
      margin-top: -5px;
    }
    .candidate-info .issue-row,
    .single-info .issue-row,
    .candidate-info .country-row,
    .single-info .country-row {
      margin: 0;
      padding: 4px;
      line-height: 1;
    }
    .issue-row {
      background-color: #ffffff;
    }
    .country-row {
      background-color: #f2f2f2;
    }
    .action-buttons {
      text-align: center;
    }
    .action-buttons button {
      width: 48%;
      margin: 5px 1%;
    }
    /* Table-like styling for listing details */
    .details-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .details-table tr:nth-child(odd) {
      background-color: #f2f2f2;
    }
    .details-table tr:nth-child(even) {
      background-color: #ffffff;
    }
    .details-table td {
      padding: 8px 5px;
      text-align: left;
      vertical-align: middle;
      font-size: 16px;
    }
    .details-table td.label {
      width: 40%;
      font-weight: bold;
    }
    .details-table td.input {
      width: 60%;
    }
    /* Ensure select and input fields inside the table are left aligned */
    .details-table select,
    .details-table input {
      text-align: left;
      width: 100%;
      font-size: 16px;
      padding: 8px;
      box-sizing: border-box;
    }
    /* Price input group with dollar sign for table cell */
    .price-group {
      display: flex;
      align-items: center;
      justify-content: flex-start;
    }
    .price-group span {
      font-size: 16px;
      font-weight: bold;
      padding-right: 5px;
    }
    .price-group input {
      flex: 1;
      text-align: left;
      font-size: 16px;
    }
    /* Center success action buttons */
    .success-message .action-buttons {
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .success-message .action-buttons button {
      width: 45%;
    }
    /* Cancel button in red */
    .cancel-button {
      background-color: red !important;
    }
    /* Override dropdown text alignment for step 3 */
    .listing-form select,
    .listing-form input {
      text-align: left;
      text-align-last: left;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Login Section (Step 1) -->
    <div class="login-form section active" id="loginSection">
      <h2>Login</h2>
      <input type="text" id="username" placeholder="Username" />
      <input type="text" id="password" placeholder="Password" />
      <button onclick="loginUser()">Login</button>
    </div>

    <!-- Step 1: Find Similar Comics -->
    <div class="section" id="uploadSection">
      <h2>List Your Comic(s) For Sale</h2>
      <button onclick="openCamera()">📷 Take a Photo</button>
      <input type="file" id="cameraInput" accept="image/*" capture="environment" />
      <button onclick="document.getElementById('fileInput').click()">📁 Choose a File</button>
      <input type="file" id="fileInput" accept="image/*" />
      <div id="loading">
        Matching<span>.</span><span>.</span><span>.</span>
      </div>
      <div id="result"></div>
    </div>

    <!-- Step 2: Listing Page -->
    <div class="section" id="listingSection">
      <h2>List For Sale</h2>
      <div id="listingContent"></div>
    </div>
  </div>

  <script>
    let currentUserId = null;
    let matches = [];
    // Global variable to track upload method ("camera" or "file")
    let uploadMethod = "";
    const serverBaseURL = "http://192.168.86.46:5000"; // Adjust if needed

    function loginUser() {
      const username = document.getElementById("username").value;
      const password = document.getElementById("password").value;
      if (!username || !password) {
        alert("Please enter username and password.");
        return;
      }
      fetch(`${serverBaseURL}/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password })
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.user_id) {
            currentUserId = data.user_id;
            document.getElementById("loginSection").style.display = "none";
            document.getElementById("uploadSection").style.display = "block";
          } else {
            alert("Login failed: " + (data.error || "Unknown error"));
          }
        })
        .catch((error) => {
          console.error("Login error:", error);
          alert("Error logging in: " + error.message);
        });
    }

    function openCamera() {
      uploadMethod = "camera";
      document.getElementById("cameraInput").click();
    }

    document.getElementById("fileInput").addEventListener("change", function () {
      uploadMethod = "file";
      handleFileUpload(this.files[0]);
    });

    document.getElementById("cameraInput").addEventListener("change", function () {
      uploadMethod = "camera";
      handleFileUpload(this.files[0]);
    });

    function handleFileUpload(file) {
      if (!file) return;
      let formData = new FormData();
      formData.append("file", file);
      document.getElementById("loading").style.display = "block";
      document.getElementById("result").innerHTML = "";
      fetch(`${serverBaseURL}/search`, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          document.getElementById("loading").style.display = "none";
          if (!Array.isArray(data) || data.length === 0) {
            document.getElementById("result").innerHTML = "<p>No matches found.</p>";
          } else {
            matches = data;
            if (matches.length > 1) {
              displayCandidates(matches);
            } else {
              displaySingleCandidate(matches[0]);
            }
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          document.getElementById("loading").style.display = "none";
          document.getElementById("result").innerHTML = "Error: " + error.message;
        });
    }

    // Display a single candidate, showing the cover (slightly larger) with an info block below it,
    // then the text asking "Is this the correct match?" and the Yes/No buttons.
    // Only the Issue and Country are shown.
    function displaySingleCandidate(comic) {
      let imageUrl = comic.Image_Path.replace("FAISS/images/", "images/");
      let issueNumber = comic.Issue_Number ? comic.Issue_Number : "N/A";
      let country = comic.Country ? comic.Country : "Not Specified";
      document.getElementById("result").innerHTML = `
        <div class="comic-result single-candidate">
          <img class="full-width-image" src="http://192.168.86.46/comicsmp/${imageUrl}" alt="Comic Cover" />
          <div class="single-info">
            <div class="issue-row" style="background-color: #ffffff; padding: 4px; text-align: left; font-size: 14px;">Issue: ${issueNumber}</div>
            <div class="country-row" style="background-color: #f2f2f2; padding: 4px; text-align: left; font-size: 14px;">Country: ${country}</div>
          </div>
          <p>Is this the correct match?</p>
          <div class="action-buttons" style="display: flex; justify-content: center;">
            <button onclick="confirmCandidate(true, '${comic.Unique_ID || comic.unique_id || ""}')">Yes</button>
            <button onclick="confirmCandidate(false)">No</button>
          </div>
        </div>
      `;
    }

    // Display multiple candidate covers in a grid with a small info block for Issue and Country.
    // For each candidate, the info blocks are adjusted to start immediately after the cover image.
    function displayCandidates(candidates) {
      let html = `<p>Select the correct cover:</p><div class="candidate-container">`;
      candidates.forEach((candidate) => {
        let imageUrl = candidate.Image_Path.replace("FAISS/images/", "images/");
        let issueNumber = candidate.Issue_Number ? candidate.Issue_Number : "N/A";
        let country = candidate.Country ? candidate.Country : "Not Specified";
        html += `
          <div class="candidate">
            <img src="http://192.168.86.46/comicsmp/${imageUrl}" alt="Candidate Cover" />
            <div class="candidate-info" style="margin-top: -5px; padding-top: 0;">
              <div class="issue-row" style="background-color: #ffffff; padding: 4px; text-align: left; font-size: 14px;">Issue: ${issueNumber}</div>
              <div class="country-row" style="background-color: #f2f2f2; padding: 4px; text-align: left; font-size: 14px;">Country: ${country}</div>
            </div>
            <button onclick='selectCandidate("${candidate.Unique_ID || candidate.unique_id || ""}")'>Select</button>
          </div>
        `;
      });
      html += `</div>`;
      document.getElementById("result").innerHTML = html;
    }

    function confirmCandidate(isCorrect, uniqueId = "") {
      if (isCorrect) {
        let candidate = matches[0];
        if (uniqueId) {
          candidate.Unique_ID = uniqueId;
        }
        goToListing(candidate);
      } else {
        matches.shift();
        if (matches.length === 0) {
          document.getElementById("result").innerHTML = "<p>No suitable match found.</p>";
          setTimeout(() => { handleNoMatchesLeft(); }, 1500);
        } else if (matches.length === 1) {
          displaySingleCandidate(matches[0]);
        } else {
          displayCandidates(matches);
        }
      }
    }

    function selectCandidate(uniqueId) {
      let candidate = matches.find(item => (item.Unique_ID || item.unique_id || "") === uniqueId);
      if (!candidate) {
        alert("Candidate not found.");
        return;
      }
      goToListing(candidate);
    }

    function goToListing(comic) {
      // Hide the upload section and show the listing section.
      document.getElementById("uploadSection").style.display = "none";
      document.getElementById("listingSection").style.display = "block";
      displayListingForm(comic);
    }

    function displayListingForm(comic) {
      let imageUrl = comic.Image_Path.replace("FAISS/images/", "images/");
      const uniqueId = comic.Unique_ID || comic.unique_id || "";
      // Build a table-like layout for the details (Condition, Graded, Price)
      // The cover image and table are both set to full width.
      document.getElementById("listingContent").innerHTML = `
        <div class="listing-cover">
          <img class="full-width-image" src="http://192.168.86.46/comicsmp/${imageUrl}" alt="Comic Cover">
        </div>
        <div class="listing-form">
          <input type="hidden" id="comic_unique_id" value="${uniqueId}">
          <table class="details-table">
            <tr>
              <td class="label">Condition</td>
              <td class="input">
                <select id="condition">
                  <option value="10.0">10.0 (Gem Mint)</option>
                  <option value="9.9">9.9 (Mint)</option>
                  <option value="9.8">9.8 (NM/M)</option>
                  <option value="9.6">9.6 (NM+)</option>
                  <option value="9.4">9.4 (NM)</option>
                  <option value="9.2">9.2 (NM-)</option>
                  <option value="9.0">9.0 (VF/NM)</option>
                  <option value="8.5">8.5 (VF+)</option>
                  <option value="8.0">8.0 (VF)</option>
                  <option value="7.5">7.5 (VF-)</option>
                  <option value="7.0">7.0 (FN/VF)</option>
                  <option value="6.5">6.5 (FN+)</option>
                  <option value="6.0">6.0 (FN)</option>
                  <option value="5.5">5.5 (FN-)</option>
                  <option value="5.0">5.0 (VG/FN)</option>
                  <option value="4.5">4.5 (VG+)</option>
                  <option value="4.0">4.0 (VG)</option>
                  <option value="3.5">3.5 (VG-)</option>
                  <option value="3.0">3.0 (G/VG)</option>
                  <option value="2.5">2.5 (G)</option>
                  <option value="2.0">2.0 (G)</option>
                  <option value="1.8">1.8 (G-)</option>
                  <option value="1.5">1.5 (Fa/G)</option>
                  <option value="1.0">1.0 (Fa)</option>
                  <option value="0.5">0.5 (Poor)</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="label">Graded</td>
              <td class="input">
                <select id="graded">
                  <option value="1">Yes</option>
                  <option value="0" selected>No</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="label">Price</td>
              <td class="input">
                <div class="price-group">
                  <span>$</span>
                  <input type="number" id="price" placeholder="0.00" step="0.01">
                </div>
              </td>
            </tr>
          </table>
          <div style="margin-top: 20px;">
            <button onclick="createListing()">Create Listing</button>
            <button class="cancel-button" onclick="cancelListing()">Cancel</button>
          </div>
        </div>
      `;
    }

    function cancelListing() {
      // Return to Step 1: clear listing and show upload section.
      document.getElementById("listingSection").style.display = "none";
      document.getElementById("uploadSection").style.display = "block";
      document.getElementById("listingContent").innerHTML = "";
      document.getElementById("result").innerHTML = "";
    }

    function createListing() {
      const uniqueId = document.getElementById("comic_unique_id").value;
      const condition = document.getElementById("condition").value;
      const graded = document.getElementById("graded").value;
      const price = document.getElementById("price").value;
      if (!price) {
        alert("Please enter a price.");
        return;
      }
      if (!currentUserId) {
        alert("User not logged in!");
        return;
      }
      if (!uniqueId) {
        alert("Comic Unique_ID is missing.");
        return;
      }
      const listingData = {
        user_id: currentUserId,
        comic_unique_id: uniqueId,
        comic_condition: condition,
        graded: graded,
        price: price,
        seller_currency: ""
      };
      fetch(`${serverBaseURL}/create_listing`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(listingData)
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.insert_id) {
            showSuccessMessage(data.insert_id);
          } else {
            alert("Error creating listing: " + (data.error || "Unknown error"));
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Error creating listing: " + error.message);
        });
    }

    function showSuccessMessage(insertId) {
      document.getElementById("listingContent").innerHTML = `
        <div class="success-message">
          <h3>Successfully Added</h3>
          <p>Do you want to add another?</p>
          <div class="action-buttons" style="display: flex; justify-content: center; gap: 10px;">
            <button onclick="handleAddAnother()">Yes</button>
            <button class="cancel-button" onclick="cancelListing()">No</button>
          </div>
        </div>
      `;
    }

    // If no matches remain after user selects "No"
    function handleNoMatchesLeft() {
      matches = [];
      document.getElementById("listingSection").style.display = "none";
      document.getElementById("listingContent").innerHTML = "";
      document.getElementById("result").innerHTML = "";
      document.getElementById("uploadSection").style.display = "block";
      if (uploadMethod === "camera") {
        openCamera();
      }
    }

    function handleAddAnother() {
      matches = [];
      document.getElementById("listingSection").style.display = "none";
      document.getElementById("uploadSection").style.display = "block";
      document.getElementById("listingContent").innerHTML = "";
      document.getElementById("result").innerHTML = "";
      if (uploadMethod === "camera") {
        openCamera();
      }
    }
  </script>
</body>
</html>
