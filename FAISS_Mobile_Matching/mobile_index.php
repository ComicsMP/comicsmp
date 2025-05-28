<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Bulk Comic Search & Listing</title>
  <style>
    /* General Layout */
    body {
      font-family: Arial, sans-serif;
      text-align: center;
      background-color: #f4f4f4;
      padding: 20px;
    }
    .container {
      margin: 20px auto;
      width: 90%;
      max-width: 500px;
      background: white;
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .section { display: none; }
    .active { display: block; }
    /* Progress Indicator */
    #progressContainer {
      margin-bottom: 15px;
    }
    #progressText {
      font-size: 16px;
      margin-bottom: 5px;
    }
    .progress-bar {
      width: 0%;
      height: 10px;
      background-color: #007BFF;
      border-radius: 5px;
      transition: width 0.3s ease;
    }
    /* Buttons and Button Groups */
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
    .btn-group {
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .btn-group button { flex: 1; }
    button {
      background-color: #007BFF;
      color: white;
    }
    button:active { background-color: #0056b3; }
    /* Specific Color Classes */
    .green-button { background-color: green; }
    .red-button { background-color: red; }
    .black-button { background-color: black; }
    .blue-button { background-color: blue; }
    /* Hide file inputs */
    #cameraInput, #fileInput { display: none; }
    /* Loading indicator */
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
    /* Image Styles */
    .comic-result img,
    .candidate img,
    .listing-cover img {
      display: block;
      width: 100%;
      object-fit: contain;
    }
    .single-candidate img { height: 250px; }
    .listing-cover img { height: 300px; }
    .full-width-image {
      width: 100% !important;
      height: auto !important;
      display: block;
    }
    /* Candidate Grid & Info */
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
    .candidate-info, .single-info {
      text-align: left;
      font-size: 14px;
      margin: 5px 0 0 0;
      padding: 0;
      line-height: 1;
    }
    .candidate-info .issue-row,
    .single-info .issue-row,
    .candidate-info .country-row,
    .single-info .country-row {
      margin: 0;
      padding: 4px;
      line-height: 1;
    }
    .issue-row { background-color: #ffffff; }
    .country-row { background-color: #f2f2f2; }
    .action-buttons { text-align: center; }
    .action-buttons .btn-group { margin-top: 10px; }
    /* Listing Form Table */
    .details-table {
      width: auto;
      margin: 0 auto; /* Center the table */
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .details-table tr:nth-child(odd) { background-color: #f2f2f2; }
    .details-table tr:nth-child(even) { background-color: #ffffff; }
    .details-table td {
      padding: 8px 5px;
      text-align: left;
      vertical-align: middle;
      font-size: 16px;
    }
    .details-table td.label { width: 40%; font-weight: bold; }
    .details-table td.input { width: 60%; }
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
    .cancel-button { background-color: red !important; }
    /* Unified Listing Box (For Unified Mode) */
    #listingForm {
      /* Default styling for unified mode remains */
    }
    /* Individual Listing Block (For Individual Mode) - Remove extra frame styling and center contents */
    .individual-listing {
      border: none;
      margin-bottom: 20px;
      padding: 10px;
      width: 100%;
      text-align: center;
    }
    .individual-listing h3 { margin-top: 0; }
    /* Final Message Section (Step 4 Part 2) */
    .final-message {
      text-align: center;
      font-size: 18px;
      margin-top: 20px;
    }
    .final-message .btn-group { margin-top: 20px; }
    /* Bulk Options (Initial Choice) */
    .bulk-options { margin: 20px 0; }
  </style>
</head>
<body>
  <div class="container">
    <!-- Progress Indicator -->
    <div id="progressContainer">
      <div id="progressText">Step 0 of 4</div>
      <div class="progress-bar" id="progressBar"></div>
    </div>

    <!-- Login Section -->
    <div class="login-form section active" id="loginSection">
      <h2>Login</h2>
      <input type="text" id="email" placeholder="Email" />
      <input type="password" id="password" placeholder="Password" />
      <button onclick="loginUser()">Login</button>
    </div>

    <!-- Bulk Options (Initial Choice) -->
    <div class="section" id="bulkOptionsSection">
      <h2>Bulk Upload Options</h2>
      <p>Would you like all your comics to have the same details or enter details for each one?</p>
      <div class="btn-group">
        <button onclick="setBulkMode('unified')">Unified Details</button>
        <button onclick="setBulkMode('individual')">Individual Details</button>
      </div>
    </div>

    <!-- Upload Section (Phase 1) -->
    <div class="section" id="uploadSection">
      <h2 id="uploadHeader">Upload Your Cover(s)</h2>
      <div class="btn-group" id="photoCaptureButtons">
        <button onclick="chooseCamera()">📷 Take Photo</button>
        <button onclick="chooseFiles()">📁 Choose File(s)</button>
      </div>
      <div id="cameraControls" style="display:none;" class="btn-group">
        <button onclick="openCamera()">Take Next Photo</button>
        <button class="green-button" onclick="finishPhotoCapture()">Done Taking Photos</button>
      </div>
      <input type="file" id="cameraInput" accept="image/*" capture="environment" />
      <input type="file" id="fileInput" accept="image/*" multiple />
      <div id="loading">
        Matching<span>.</span><span>.</span><span>.</span>
      </div>
      <div id="result"></div>
      <div class="btn-group" style="margin-top:20px;">
        <button onclick="resetAndReload()" class="red-button">Cancel All Matching</button>
      </div>
    </div>

    <!-- Listing Section (Phase 2) -->
    <div class="section" id="listingSection">
      <!-- Step 4 Part 1: Review & Listing Form -->
      <div id="listingForm">
        <h2>Review and List Your Comics</h2>
        <div id="listingContent"></div>
      </div>
      <!-- Step 4 Part 2: Final Confirmation -->
      <div id="finalMessage" class="final-message" style="display: none;">
        <h2>Congratulations!</h2>
        <p id="finalText"></p>
        <div class="btn-group">
          <button onclick="resetAll()" class="green-button">List More For Sale</button>
          <button onclick="window.location.href='home.html'" class="blue-button">Return Home</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Global Variables
    let currentUserId = null;
    let bulkMode = ""; // "unified" or "individual"
    let uploadMethod = "";
    let uploadMethodChosen = false;
    let imageFiles = []; // Array of File objects
    let matches = [];
    let listingIndex = 0;
    const serverBaseURL = "http://192.168.86.68:5000";  // Ensure this is correct

    const totalSteps = 4;

    // On page load, retrieve stored user if exists
    window.onload = function() {
      const storedUser = localStorage.getItem("user_id");
      if (storedUser) {
        currentUserId = storedUser;
        showSection("bulkOptionsSection", 2);
      }
    };

    function updateProgressIndicator() {
      let activeSection = document.querySelector(".section.active");
      let step = activeSection ? parseInt(activeSection.getAttribute("data-step")) : 1;
      document.getElementById("progressText").innerText = "Step " + step + " of " + totalSteps;
      document.getElementById("progressBar").style.width = ((step / totalSteps) * 100) + "%";
    }
    function showSection(sectionId, stepNumber) {
      document.querySelectorAll(".section").forEach(sec => sec.classList.remove("active"));
      const sec = document.getElementById(sectionId);
      sec.classList.add("active");
      sec.setAttribute("data-step", stepNumber);
      updateProgressIndicator();
    }
    // Reset everything and reload the page.
    function resetAndReload() {
      resetAll();
      location.reload();
    }
    function resetAll() {
      // Do not remove user_id from localStorage if you want login to persist.
      currentUserId = null;
      bulkMode = "";
      uploadMethod = "";
      uploadMethodChosen = false;
      imageFiles = [];
      matches = [];
      listingIndex = 0;
      // If you want to force a logout, uncomment the following line:
      // localStorage.removeItem("user_id");
      document.getElementById("uploadHeader").innerText = "Upload Your Cover(s)";
      document.getElementById("result").innerHTML = "";
      document.getElementById("listingContent").innerHTML = "";
      // Ensure listing form is visible by default
      document.getElementById("listingForm").style.display = "block";
      document.getElementById("finalMessage").style.display = "none";
      document.getElementById("photoCaptureButtons").style.display = "block";
      showSection("loginSection", 1);
    }

    // ---------- LOGIN ----------
    function loginUser() {
  const email = document.getElementById("email").value;
  const password = document.getElementById("password").value;
  if (!email || !password) {
    alert("Please enter email and password.");
    return;
  }

  fetch(`${serverBaseURL}/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email, password })
  })
    .then(response => response.json())
    .then(data => {
      if (data.user_id) {
        currentUserId = data.user_id;
        localStorage.setItem("user_id", currentUserId);
        showSection("bulkOptionsSection", 2);
      } else {
        alert("Login failed: " + (data.error || "Unknown error"));
      }
    })
    .catch(error => {
      console.error("Login error:", error);
      alert("Error logging in: " + error.message);
    });
}

    // ---------- BULK OPTIONS ----------
    function setBulkMode(mode) {
      bulkMode = mode;
      showSection("uploadSection", 3);
      document.getElementById("uploadHeader").innerText = "Upload Your Cover(s)";
      if (uploadMethodChosen || imageFiles.length > 0) {
        document.getElementById("photoCaptureButtons").style.display = "none";
      }
      // When individual mode is chosen, no extra unified buttons
      // For unified, the buttons will be generated in the unified form
    }

    // ---------- IMAGE UPLOAD & MATCHING (Phase 1) ----------
    function chooseCamera() {
      uploadMethod = "camera";
      uploadMethodChosen = true;
      document.getElementById("photoCaptureButtons").style.display = "none";
      document.getElementById("cameraControls").style.display = "block";
      openCamera();
    }
    function chooseFiles() {
      uploadMethod = "file";
      uploadMethodChosen = true;
      document.getElementById("photoCaptureButtons").style.display = "none";
      document.getElementById("fileInput").click();
    }
    function openCamera() {
      document.getElementById("cameraInput").click();
    }
    document.getElementById("fileInput").addEventListener("change", function() {
      uploadMethod = "file";
      uploadMethodChosen = true;
      for (let i = 0; i < this.files.length; i++) {
        imageFiles.push(this.files[i]);
      }
      processNextImage();
    });
    document.getElementById("cameraInput").addEventListener("change", function() {
      uploadMethod = "camera";
      uploadMethodChosen = true;
      if (this.files[0]) {
        imageFiles.push(this.files[0]);
        const photoPrompt = document.getElementById("photoPrompt");
        if (photoPrompt) {
          photoPrompt.innerText = "Photo " + imageFiles.length + " captured.";
        }
        document.getElementById("cameraControls").style.display = "block";
      }
    });
    function finishPhotoCapture() {
      processNextImage();
    }
    function cancelAllMatching() {
      resetAndReload();
    }
    function processNextImage() {
      if (matches.length >= imageFiles.length) {
        document.getElementById("uploadSection").style.display = "none";
        showSection("listingSection", 4);
        if (bulkMode === "unified") {
          showUnifiedListingForm();
        } else {
          // For individual mode, hide the unified listing form container and start individual listings.
          document.getElementById("listingForm").style.display = "none";
          listingIndex = 0;
          showNextListing();
        }
        return;
      }
      let file = imageFiles[matches.length];
      let formData = new FormData();
      formData.append("file", file);
      document.getElementById("loading").style.display = "block";
      fetch(`${serverBaseURL}/search`, {
        method: "POST",
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        document.getElementById("loading").style.display = "none";
        if (!Array.isArray(data) || data.length === 0) {
          alert("No match found for image " + (matches.length + 1));
          matches.push({ candidateList: [], currentCandidateIndex: 0, acceptedCandidate: null });
          processNextImage();
        } else {
          let matchObj = {
            candidateList: data,
            currentCandidateIndex: 0,
            acceptedCandidate: null,
            submitted: false
          };
          matches.push(matchObj);
          document.getElementById("uploadHeader").innerText = "Confirm Your Cover(s)";
          if (data.length > 1) {
            displayCandidateGrid(matches.length - 1);
          } else {
            displaySingleCandidateConfirmation(matches.length - 1);
          }
        }
      })
      .catch(error => {
        console.error("Error:", error);
        document.getElementById("loading").style.display = "none";
        alert("Error during matching: " + error.message);
      });
    }
    // For a match object with a single candidate, show confirmation UI.
    function displaySingleCandidateConfirmation(matchIndex) {
  let matchObj = matches[matchIndex];
  document.getElementById("photoCaptureButtons").style.display = "none";
  document.getElementById("cameraControls").style.display = "none";
  let candidate = matchObj.candidateList[matchObj.currentCandidateIndex];
  let imageUrl = candidate.Image_Path 
                 ? candidate.Image_Path.replace("FAISS/images/", "images/") 
                 : ("images/" + candidate.Unique_ID);
  let headerText = "Cover " + (matchIndex + 1) + " of " + imageFiles.length;
  let html = `
    <div class="comic-result single-candidate">
      <img class="full-width-image" src="http://192.168.86.68/comicsmp/${imageUrl}" alt="Candidate Cover"
           onerror="this.onerror=null;this.src='http://192.168.86.68/comicsmp/images/placeholder.jpg';" />
      <div class="single-info">
        <div class="issue-row">Issue: ${candidate.Issue_Number ? candidate.Issue_Number : "N/A"}</div>
        <div class="country-row">Country: ${candidate.Country ? candidate.Country : "Not Specified"}</div>
      </div>
      <p>${headerText}</p>
      <div class="action-buttons btn-group">
        <button onclick="rejectCandidate(${matchIndex})" class="black-button">Wrong cover</button>
        <button onclick="acceptCandidate(${matchIndex})">Yes, this cover</button>
      </div>
    </div>
  `;
  document.getElementById("result").innerHTML = html;
}

    // For a match object with multiple candidates, show grid view.
    function displayCandidateGrid(matchIndex) {
      let matchObj = matches[matchIndex];
      document.getElementById("photoCaptureButtons").style.display = "none";
      document.getElementById("cameraControls").style.display = "none";
      let headerText = "Cover " + (matchIndex + 1) + " of " + imageFiles.length;
      let html = `<p>${headerText}</p><div class="candidate-container">`;
      matchObj.candidateList.forEach(candidate => {
        let imageUrl = candidate.Image_Path 
                       ? candidate.Image_Path.replace("FAISS/images/", "images/") 
                       : ("images/" + candidate.Unique_ID);
        html += `
          <div class="candidate">
            <img src="http://192.168.86.68/comicsmp/${imageUrl}" alt="Candidate Cover"
                 onerror="this.onerror=null;this.src='http://192.168.86.68/comicsmp/images/placeholder.jpg';" />
            <div class="candidate-info">
              <div class="issue-row">Issue: ${candidate.Issue_Number ? candidate.Issue_Number : "N/A"}</div>
              <div class="country-row">Country: ${candidate.Country ? candidate.Country : "Not Specified"}</div>
            </div>
            <div class="btn-group">
              <button onclick='acceptCandidate(${matchIndex}, ${JSON.stringify(candidate)})'>Select this cover</button>
            </div>
          </div>
        `;
      });
      html += `</div>`;
      html += `<div style="text-align:center; margin-top:10px;">
                 <button onclick="gridWrongCover(${matchIndex})" class="black-button">Wrong Cover</button>
               </div>`;
      document.getElementById("result").innerHTML = html;
    }
    // Called when a cover is accepted.
    function acceptCandidate(matchIndex, candidateData = null) {
      let matchObj = matches[matchIndex];
      matchObj.acceptedCandidate = candidateData ? candidateData : matchObj.candidateList[matchObj.currentCandidateIndex];
      document.getElementById("result").innerHTML = `<p>Cover ${matchIndex + 1} processed.</p>`;
      processNextImage();
    }
    // Called when "Wrong Cover" is pressed in single candidate view.
    function rejectCandidate(matchIndex) {
      let matchObj = matches[matchIndex];
      if (matchObj.currentCandidateIndex < matchObj.candidateList.length - 1) {
        matchObj.currentCandidateIndex++;
        displaySingleCandidateConfirmation(matchIndex);
      } else {
        alert("No more candidates available. Please re-upload this cover.");
        processNextImage();
      }
    }
    // Called when "Wrong Cover" is pressed in grid view.
    function gridWrongCover(matchIndex) {
      alert("Cover marked as wrong. Skipping this cover.");
      processNextImage();
    }
    // ---------- INDIVIDUAL MODE LISTING FUNCTIONS ----------
    function showNextListing() {
      // Skip listings without an accepted candidate.
      while (listingIndex < matches.length && (!matches[listingIndex] || !matches[listingIndex].acceptedCandidate)) {
        listingIndex++;
      }
      if (listingIndex >= matches.length) {
        let submittedCount = matches.filter(m => m && m.submitted).length;
        showFinalConfirmation(submittedCount);
        return;
      }
      displayIndividualListing(listingIndex);
    }
    function displayIndividualListing(idx) {
      let matchObj = matches[idx];
      let candidate = matchObj.acceptedCandidate;
      let imageUrl = candidate.Image_Path 
                     ? candidate.Image_Path.replace("FAISS/images/", "images/") 
                     : ("images/" + candidate.Unique_ID);
      let html = `
          <div class="listing-cover">
            <img class="full-width-image" 
                 src="http://192.168.86.68/comicsmp/${imageUrl}" 
                 alt="Comic Cover"
                 onerror="this.onerror=null;this.src='http://192.168.86.68/comicsmp/images/placeholder.jpg';" />
          </div>
          <div class="unified-form">
            <!-- Hidden input required for submit -->
            <input type="hidden" id="comic_unique_id_${idx}" value="${candidate.Unique_ID || candidate.unique_id || ''}">
            <table class="details-table">
              <tr>
                <td class="label">Condition</td>
                <td class="input">
                  <select id="condition_${idx}">
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
                  <select id="graded_${idx}">
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
                    <input type="number" id="price_${idx}" placeholder="0.00" step="0.01">
                  </div>
                </td>
              </tr>
            </table>
            <div class="action-buttons btn-group">
              <button onclick="cancelIndividualListing(${idx})" class="red-button">Cancel</button>
              <button onclick="submitIndividualListing(${idx})" class="green-button">Submit</button>
            </div>
          </div>
      `;
      document.getElementById("listingContent").innerHTML = html;
      document.getElementById("listingForm").style.display = "block";
      document.getElementById("finalMessage").style.display = "none";
    }
    function submitIndividualListing(idx) {
      const condition = document.getElementById("condition_" + idx).value;
      const graded = document.getElementById("graded_" + idx).value;
      const price = document.getElementById("price_" + idx).value;
      if (!price) {
        alert("Please enter a price for this comic.");
        return;
      }
      let comic_unique_id = document.getElementById("comic_unique_id_" + idx).value;
      let listingData = {
        user_id: currentUserId,
        comic_unique_id: comic_unique_id,
        comic_condition: condition,
        graded: graded,
        price: price,
        seller_currency: ""
      };
      fetch(`${serverBaseURL}/create_listing`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(listingData)
      }).then(response => response.json())
        .then(data => {
          if (data.insert_id) {
            matches[idx].submitted = true;
            listingIndex++;
            showNextListing();
          } else {
            alert("Error creating listing for this comic: " + (data.error || "Unknown error"));
          }
        }).catch(error => {
          console.error("Error:", error);
          alert("Error creating listing for this comic: " + error.message);
        });
    }
    function cancelIndividualListing(idx) {
      matches[idx].submitted = false;
      listingIndex++;
      showNextListing();
    }
    // ---------- FINAL CONFIRMATION (Step 4 Part 2) ----------
    function showFinalConfirmation(successCount) {
      // Hide the listing form (Step 4 Part 1)
      document.getElementById("listingForm").style.display = "none";
      let finalHtml = `
        <h2>Congratulations!</h2>
        <p>You have successfully listed ${successCount} comic(s) for sale. They are now available in your "Comics For Sale" dashboard.</p>
        <div class="btn-group">
          <button onclick="resetAll()" class="green-button">List More For Sale</button>
          <button onclick="window.location.href='home.html'" class="blue-button">Return Home</button>
        </div>
      `;
      document.getElementById("finalMessage").innerHTML = finalHtml;
      document.getElementById("finalMessage").style.display = "block";
    }
    // ---------- UNIFIED MODE LISTING (Step 4 for Unified Mode) ----------
    function showUnifiedListingForm() {
      let html = `<div class="unified-previews">`;
      matches.forEach((matchObj) => {
        if (matchObj && matchObj.acceptedCandidate) {
          let candidate = matchObj.acceptedCandidate;
          let imageUrl = candidate.Image_Path 
                         ? candidate.Image_Path.replace("FAISS/images/", "images/") 
                         : ("images/" + candidate.Unique_ID);
          html += `<div class="unified-preview" style="display:inline-block; width:45%; margin:5px;">
                     <img src="http://192.168.86.68/comicsmp/${imageUrl}" style="width:100%;" 
                          onerror="this.onerror=null;this.src='http://192.168.86.68/comicsmp/images/placeholder.jpg';" />
                   </div>`;
        }
      });
      html += `</div>`;
      html += `
         <div class="unified-form">
           <!-- Hidden field in case the submit function needs the comic IDs -->
           <input type="hidden" id="unified_comic_ids" value="${matches.map(m => m.acceptedCandidate ? (m.acceptedCandidate.Unique_ID || m.acceptedCandidate.unique_id) : "").join(",")}">
           <table class="details-table">
             <tr>
               <td class="label">Condition</td>
               <td class="input">
                 <select id="unified_condition">
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
                 <select id="unified_graded">
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
                   <input type="number" id="unified_price" placeholder="0.00" step="0.01">
                 </div>
               </td>
             </tr>
           </table>
           <!-- Button group: two buttons side by side with new labels -->
           <div class="action-buttons btn-group">
             <button onclick="resetAll()" class="red-button">Cancel</button>
             <button onclick="submitUnifiedListings()" class="green-button">Submit</button>
           </div>
         </div>
      `;
      document.getElementById("listingContent").innerHTML = html;
    }
    
    function submitUnifiedListings() {
      // Retrieve common values from unified form
      const condition = document.getElementById("unified_condition").value;
      const graded = document.getElementById("unified_graded").value;
      const price = document.getElementById("unified_price").value;
      
      if (!price) {
        alert("Please enter a price for the comics.");
        return;
      }
      
      // Filter matches that have an accepted candidate and haven't been submitted
      let pendingMatches = matches.filter(m => m && m.acceptedCandidate && !m.submitted);
      
      if (pendingMatches.length === 0) {
        alert("No comics to list.");
        return;
      }
      
      // Submit each pending listing
      pendingMatches.forEach(matchObj => {
        let candidate = matchObj.acceptedCandidate;
        let comic_unique_id = candidate.Unique_ID || candidate.unique_id;
        let listingData = {
          user_id: currentUserId,
          comic_unique_id: comic_unique_id,
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
        .then(response => response.json())
        .then(data => {
  if (data.insert_id) {
    matchObj.submitted = true;
    // only consider the covers the user actually accepted
    const doneOnes = matches.filter(m => m.acceptedCandidate);
    // now trigger final confirmation once those are all submitted
    if (doneOnes.every(m => m.submitted)) {
      showFinalConfirmation(doneOnes.length);
    } else {
      showNextListing();
    }
  } else {
    alert("Error creating listing for a comic: " + (data.error || "Unknown error"));
  }
})
.catch(error => {
  console.error("Error:", error);
  alert("Error creating listing for a comic: " + error.message);
});

      });
    }
  </script>
</body>
</html>
