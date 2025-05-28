<?php
// This partial contains the Cover tab UI and its JavaScript
?>

<!-- Cover Tab Content -->
<div class="border rounded bg-white shadow-sm p-3 mb-3" style="border-left: 4px solid #007BFF;">

  <!-- Progress Indicator -->
  <div id="progressContainer">
    <div id="progressText">Step 1 of 3</div>
    <div class="progress-bar" id="progressBar"></div>
  </div>

  <!-- Login Section -->
  <div class="login-form section active" id="loginSection">
    <h2>Login</h2>
    <input type="text" id="email" placeholder="Email" />
    <input type="password" id="password" placeholder="Password" />
    <button onclick="loginUser()">Login</button>
  </div>

  <!-- Upload Options -->
  <div class="section" id="bulkOptionsSection" style="text-align:center; margin-top: 2.25rem;">
    <h2>Upload Options</h2>
    <p>How would you like to fill in your comic details? (Works for both bulk or single uploads)</p>

    <!-- Beta Advisory with Helpful Tips -->
    <p style="font-size:0.9rem; color:#999; margin-bottom: 0.75rem;">
      🚧 <strong>Beta Notice:</strong> This feature is still being improved.
    </p>
    

    <!-- Smart Tip for Grouping -->
    <p style="font-size:0.95rem; color:#666; margin-bottom: 1.25rem; max-width: 420px; margin-left: auto; margin-right: auto;">
      💡 <strong>Tip:</strong> If you're listing multiple comics with the <u>same condition and price</u>, choose <strong>“Apply to All”</strong> to speed things up.
    </p>

    <div class="btn-group" style="justify-content:center;">
      <button onclick="setBulkMode('unified')">Apply to All</button>
      <button onclick="setBulkMode('individual')">Customize Each</button>
    </div>
  </div>

  <!-- Upload Section -->
  <div class="section" id="uploadSection">
<h2 id="uploadHeader" style="text-align: center;">Upload Your Cover(s)</h2>
  <!-- Photo Matching Tips -->
  <div style="margin-top: 2.25rem; text-align: center;">
    <p style="font-size:0.9rem; color:#999; margin-bottom: 0.75rem;">
      📸 <strong>Photo Tips:</strong> For better matching results:
    </p>
    <ul style="font-size:0.9rem; color:#666; list-style: disc; padding-left: 1.25rem; text-align: left; max-width: 420px; margin: 0 auto 1.25rem;">
      <li>Avoid glare or reflections — especially from glossy plastic sleeves.</li>
      <li>Remove comics from sleeves if the match is unclear, particularly on all-black covers.</li>
      <li>You can skip tough matches and retry later — the system will improve over time.</li>
    </ul>
  </div>

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
  <div class="loader-dots">
    <span></span><span></span><span></span>
  </div>
  <div style="margin-top: 0.5rem;">Matching your covers…</div>
</div>

    <div id="result"></div>
    <div class="btn-group" style="margin-top:20px;">
      <button onclick="resetAndReload()" class="red-button">Cancel All Matching</button>
    </div>
  </div>

  <!-- Listing Section -->
  <div class="section" id="listingSection">
    <div id="listingForm">
      <h2 style="text-align: center; margin-bottom: 1.5rem;">Review and List</h2>
      <div id="listingContent"></div>
    </div>
    <div id="finalMessage" class="final-message" style="display: none;">
      <h2>Congratulations!</h2>
      <p id="finalText"></p>
      <div class="btn-group">
        <button onclick="resetAll()" class="green-button">List More</button>
        <button onclick="window.location.href='/comicsmp/mobile/dashboard.php'" class="blue-button">Return Home</button>

      </div>
    </div>
  </div>
</div><script>
    // Global Variables
    let currentUserId = null;
    let bulkMode = ""; // "unified" or "individual"
    let uploadMethod = "";
    let uploadMethodChosen = false;
    let imageFiles = []; // Array of File objects
    let matches = [];
    let listingIndex = 0;    
    const serverBaseURL = "http://192.168.86.68:5000";  // Ensure this is correct

    const totalSteps = 3;

    // On page load, retrieve stored user if exists
    window.onload = function() {
      const storedUser = localStorage.getItem("user_id");
      if (storedUser) {
        currentUserId = storedUser;
        showSection("bulkOptionsSection", 1);
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
  // ✅ First, hide all active sections to prevent overlap
  document.querySelectorAll(".section").forEach(sec => {
    sec.classList.remove("active");
    sec.style.display = "none"; // <-- force hide too just in case
  });

  resetAll();

  // ✅ Only reload if not logged in
  if (!localStorage.getItem("user_id")) {
    location.reload();
  }
}


    function resetAll() {
  // Preserve logged-in state
  if (!localStorage.getItem("user_id")) {
    currentUserId = null;
  
  }

  // Clear global state
  bulkMode = "";
  uploadMethod = "";
  uploadMethodChosen = false;
  imageFiles = [];
  matches = [];
  listingIndex = 0;

  // Clear inputs and outputs
  document.getElementById("fileInput").value = "";
  document.getElementById("cameraInput").value = "";
  document.getElementById("uploadHeader").innerText = "Upload Your Cover(s)";
  document.getElementById("result").innerHTML = "";
  document.getElementById("listingContent").innerHTML = "";
  document.getElementById("listingForm").style.display = "block";
  document.getElementById("finalMessage").style.display = "none";
  document.getElementById("photoCaptureButtons").style.display = "block";

  // ❌ Forcefully hide all other steps (especially uploadSection!)
  document.querySelectorAll(".section").forEach(sec => {
    sec.classList.remove("active");
    sec.style.display = "none";
  });

  // ✅ Then show correct Step 1
  if (localStorage.getItem("user_id")) {
    const bulkSection = document.getElementById("bulkOptionsSection");
    bulkSection.style.display = "block";
    bulkSection.classList.add("active");
    bulkSection.setAttribute("data-step", "1");
    updateProgressIndicator();
  } else {
    const loginSection = document.getElementById("loginSection");
    loginSection.style.display = "block";
    loginSection.classList.add("active");
    loginSection.setAttribute("data-step", "0");
    updateProgressIndicator();
  }

  resetManualTab(); // ✅ ensure manual tab gets wiped
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

  // 🧼 Cleanly hide all other sections before showing upload
  document.querySelectorAll(".section").forEach(sec => {
    sec.classList.remove("active");
    sec.style.display = "none";
  });

  const uploadSection = document.getElementById("uploadSection");
  uploadSection.style.display = "block";
  uploadSection.classList.add("active");

  showSection("uploadSection", 2);
  document.getElementById("uploadHeader").innerText = "Upload Your Cover(s)";

  // ✅ Reset UI state
  document.getElementById("photoCaptureButtons").style.display = "block";
  document.getElementById("cameraControls").style.display = "none";
  document.getElementById("loading").style.display = "none";
  document.getElementById("result").innerHTML = "";
  document.getElementById("fileInput").value = "";
  document.getElementById("cameraInput").value = "";

  // 🔄 Clear state
  imageFiles = [];
  matches = [];
  uploadMethod = "";
  uploadMethodChosen = false;
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
  location.href = window.location.href; // hard reload to same page
}


    function processNextImage() {
  if (matches.length >= imageFiles.length) {
  // 🧼 Hide previous step completely
  document.querySelectorAll(".section").forEach(sec => {
    sec.classList.remove("active");
    sec.style.display = "none";
  });

  const listingSection = document.getElementById("listingSection");
  listingSection.style.display = "block";
  listingSection.classList.add("active");
  listingSection.setAttribute("data-step", "3");
  updateProgressIndicator();

  // 🔎 Check if NO valid matches were confirmed
  const confirmedMatches = matches.filter(m => m.acceptedCandidate);
  if (confirmedMatches.length === 0) {
    // ❌ No good covers matched — show friendly message instead
    document.getElementById("listingForm").style.display = "none";
   document.getElementById("finalMessage").innerHTML = `
  <div style="text-align:center;">
    <h2>No Valid Matches</h2>
    <p style="margin: 0.75rem 0;">No confirmed comic covers were found. You can try again or return to your dashboard.</p>
    <p style="font-size:0.9rem; color:#666; margin-top:1rem; max-width:450px; margin-left:auto; margin-right:auto;">
      💡 <strong>Tip:</strong> To improve results, avoid using flash and remove plastic sleeves — especially on glossy or all-black covers. Still stuck? Try scanning the barcode or listing it manually.
    </p>
    <div class="btn-group" style="margin-top: 1.25rem;">
      <button onclick="resetAll()" class="green-button">List More</button>
      <button onclick="window.location.href='/comicsmp/mobile/dashboard.php'" 
              class="blue-button" 
              style="background-color:#007BFF; color:white;">Return Home</button>
    </div>
  </div>
`;


    document.getElementById("finalMessage").style.display = "block";
    return;
  }

  // ✅ Proceed to listing as normal
  if (bulkMode === "unified") {
    showUnifiedListingForm();
    document.getElementById("listingForm").style.display = "block";
  } else {
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
      <div class="match-image-box">
  <img class="full-width-image" src="http://192.168.86.68/comicsmp/${imageUrl}" 
       alt="Candidate Cover"
       onerror="this.onerror=null;this.src='http://192.168.86.68/comicsmp/images/placeholder.jpg';" />
</div>


          
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
            <div class="match-image-box">
  <img src="http://192.168.86.68/comicsmp/${imageUrl}" 
       alt="Candidate Cover"
       onerror="this.onerror=null;this.src='http://192.168.86.68/comicsmp/images/placeholder.jpg';" />
</div>

                 
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
        alert("Please try to re-upload this cover another time. For best results remove the plastic cover sleeve and avoid a flash");
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
          <div class="match-image-box">
  <img class="match-standard" 
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
          <button onclick="resetAll()" class="green-button">List More</button>
          <button onclick="window.location.href='/comicsmp/mobile/dashboard.php'" class="blue-button">Return Home</button>
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

function resetManualTab() {
  const manualResults = document.getElementById("manualResults");
  const manualFilters = document.getElementById("manualFilters");
  const manualTabs = document.getElementById("manualTabs");

  if (manualResults) manualResults.style.display = "none";
  if (manualFilters) manualFilters.style.display = "none";
  if (manualTabs) manualTabs.style.display = "none";

  const manualInput = document.getElementById("manualSearchInput");
  if (manualInput) manualInput.value = "";
}


  </script>
