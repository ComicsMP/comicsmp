<?php
// Modals.php
// -------------------------
// Grade Mapping for Abbreviations (use string keys)
// -------------------------
$gradeMapping = [
    "10.0" => "Gem Mint",
    "9.9"  => "Mint",
    "9.8"  => "NM/M",
    "9.6"  => "NM+",
    "9.4"  => "NM",
    "9.2"  => "NM-",
    "9.0"  => "VF/NM",
    "8.5"  => "VF+",
    "8.0"  => "VF",
    "7.5"  => "VF-",
    "7.0"  => "FN/VF",
    "6.5"  => "FN+",
    "6.0"  => "FN",
    "5.5"  => "FN-",
    "5.0"  => "VG/FN",
    "4.5"  => "VG+",
    "4.0"  => "VG",
    "3.5"  => "VG-",
    "3.0"  => "G/VG",
    "2.5"  => "G",
    "2.0"  => "G",
    "1.8"  => "G-",
    "1.5"  => "Fa/G",
    "1.0"  => "Fa",
    "0.5"  => "Poor"
];
?>

<style>
  /* UPC container: keep everything on one line */
  #popupUPCContainer { white-space: nowrap; }
  /* UPC text and button: inline, same font/size */
  #popupUPC, #submitUPCBtn {
    display: inline-block;
    font-family: inherit;
    font-size: inherit;
    margin: 0;
    padding: 0;
    vertical-align: baseline;
  }
  /* Remove extra button styling to appear like normal text link */
  #submitUPCBtn {
    background: none;
    border: none;
    color: #007bff;
    text-decoration: underline;
    cursor: pointer;
  }
  #submitUPCBtn:hover {
    color: #0056b3;
    text-decoration: none;
  }
  /* Remove focus outline (blue glow) from the UPC input field */
  #newUPC:focus, .form-control:focus {
    outline: none !important;
    box-shadow: none !important;
  }
</style>

<!-- Edit Sale Listing Modal -->
<div class="modal fade" id="editSaleModal" tabindex="-1" aria-labelledby="editSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="editSaleForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editSaleModalLabel">Edit Sale Listing</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="editListingId" name="listing_id">
          <div class="mb-3">
            <label for="editCondition" class="form-label">Condition</label>
            <select class="form-select" id="editCondition" name="condition" required>
              <option value="">Select Condition</option>
              <?php foreach ($gradeMapping as $score => $label): ?>
                <option value="<?= htmlspecialchars($score) ?>">
                  <?= htmlspecialchars("{$score} ({$label})") ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="editGraded" class="form-label">Graded</label>
            <select class="form-select" id="editGraded" name="graded" required>
              <option value="0">Not Graded</option>
              <option value="1">Graded</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="editPrice" class="form-label">Price</label>
            <input type="number" step="0.01" class="form-control" id="editPrice" name="price" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Edit Series Modal -->
<div class="modal fade" id="bulkEditSaleModal" tabindex="-1" aria-labelledby="bulkEditSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="bulkEditSaleForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="bulkEditSaleModalLabel">Bulk Edit Series</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="bulkEditComicTitle" name="comic_title">
          <input type="hidden" id="bulkEditYears" name="years">
          <div class="mb-3">
            <label for="bulkEditCondition" class="form-label">Condition</label>
            <select class="form-select" id="bulkEditCondition" name="condition" required>
              <option value="">Select Condition</option>
              <?php foreach ($gradeMapping as $score => $label): ?>
                <option value="<?= htmlspecialchars($score) ?>">
                  <?= htmlspecialchars("{$score} ({$label})") ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="bulkEditGraded" class="form-label">Graded</label>
            <select class="form-select" id="bulkEditGraded" name="graded" required>
              <option value="0">Not Graded</option>
              <option value="1">Graded</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="bulkEditPrice" class="form-label">Price</label>
            <input type="number" step="0.01" class="form-control" id="bulkEditPrice" name="price" required>
          </div>
          <p class="text-muted">This will update all issues in the selected series.</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Changes for Series</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Cover Popup Modal (Used for Search Results) -->
<div class="modal fade" id="coverPopupModal" tabindex="-1" aria-labelledby="coverPopupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coverPopupModalLabel">Comic Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body popup-modal-body">
        <!-- Left Column: Large Cover Image -->
        <div class="popup-image-container">
          <img id="popupMainImage" src="" alt="Comic Cover">
        </div>
        <!-- Right Column: Details & Similar Issues -->
        <div class="popup-details-container">
          <table class="table table-sm">
            <tr><th>Comic Title:</th><td id="popupComicTitle"></td></tr>
            <tr><th>Years:</th><td id="popupYears"></td></tr>
            <tr><th>Issue Number:</th><td id="popupIssueNumber"></td></tr>
            <tr><th>Tab:</th><td id="popupTab"></td></tr>
            <tr><th>Variant:</th><td id="popupVariant"></td></tr>
            <tr><th>Date:</th><td id="popupDate"></td></tr>
            <tr>
              <th>UPC:</th>
              <td id="popupUPCContainer">
                <span id="popupUPC"></span>
                <button id="submitUPCBtn" class="btn btn-link btn-sm" style="display: none;">Add UPC</button>
              </td>
            </tr>
            <tr id="upcSubmissionRow" style="display: none;">
              <th colspan="2">
                <div class="input-group">
                  <input type="text" id="newUPC" class="form-control" placeholder="Enter UPC (e.g. 759606209088-00311)">
                  <button id="saveUPCBtn" class="btn btn-primary">Save</button>
                  <button id="cancelUPCBtn" class="btn btn-secondary">Cancel</button>
                </div>
              </th>
            </tr>
            <tr id="popupConditionRow"><th>Condition:</th><td id="popupCondition"></td></tr>
            <tr id="popupGradedRow"><th>Graded:</th><td id="popupGraded"></td></tr>
            <tr id="popupPriceRow"><th>Price:</th><td id="popupPrice"></td></tr>
          </table>
          <div class="similar-issues">
            <h6>Similar Issues</h6>
            <div id="similarIssues" class="d-flex flex-wrap"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Send Message Modal for Matches -->
<div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="sendMessageForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="sendMessageModalLabel">Send Message</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="recipient_id" id="recipient_id" value="">
          <div id="messageInfo" class="mb-3">
            <p>You're messaging <strong id="recipientName"></strong> about your matched comics.</p>
          </div>
          <div id="matchComicSelection" class="mb-3">
            <!-- Matched comics checkboxes will be loaded dynamically -->
          </div>
          <div class="mb-3">
            <label for="messagePreview" class="form-label">Message Preview</label>
            <textarea id="messagePreview" name="message" class="form-control" rows="5"></textarea>
            <small class="form-text text-muted">You can edit the message if needed before sending.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Send Message</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- JavaScript for UPC Submission + Formatting -->
<script>
$(document).ready(function(){
  $('#coverPopupModal').on('shown.bs.modal', function () {
    const currentUPC = $('#popupUPC').text().trim();
    if (currentUPC === "" || currentUPC === "N/A") {
      $('#popupUPC').text("");
      $('#submitUPCBtn').show();
    } else {
      $('#submitUPCBtn, #upcSubmissionRow').hide();
    }
  });
  $('#submitUPCBtn').click(()=> $('#upcSubmissionRow').show() );
  $('#cancelUPCBtn').click(()=> $('#upcSubmissionRow').hide() );

  function formatUPC(input) {
    let d = input.replace(/\D/g,'');
    return d.length<=14 ? d : d.slice(0,12)+'-'+d.slice(12);
  }
  $('#newUPC').blur(function(){
    $(this).val(formatUPC(this.value));
  });
  $('#saveUPCBtn').click(function(){
    const newUPC = $('#newUPC').val().trim();
    if (newUPC.includes('-') ? !/^\d{12}-\d+$/.test(newUPC) : newUPC.length>14) return;
    const issueUrl = $('#coverPopupModal').data('issueUrl');
    if (!issueUrl) return;
    $.post('submit_upc.php',{upc:newUPC,issue_url:issueUrl},resp=>{
      if(resp.status==='success'){
        $('#popupUPC').text(newUPC);
        $('#upcSubmissionRow, #submitUPCBtn').hide();
      } else console.error(resp.message);
    },'json').fail(()=>console.error('UPC submit error'));
  });
});
</script>
