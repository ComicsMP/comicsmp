<!-- Modals.php -->
<!-- MODALS -->
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
              <option value="10">10</option>
              <option value="9.9">9.9</option>
              <option value="9.8">9.8</option>
              <option value="9.6">9.6</option>
              <option value="9.4">9.4</option>
              <option value="9.2">9.2</option>
              <option value="9.0">9.0</option>
              <option value="8.5">8.5</option>
              <option value="8.0">8.0</option>
              <option value="7.5">7.5</option>
              <option value="7.0">7.0</option>
              <option value="6.5">6.5</option>
              <option value="6.0">6.0</option>
              <option value="5.5">5.5</option>
              <option value="5.0">5.0</option>
              <option value="4.5">4.5</option>
              <option value="4.0">4.0</option>
              <option value="3.5">3.5</option>
              <option value="3.0">3.0</option>
              <option value="2.5">2.5</option>
              <option value="2.0">2.0</option>
              <option value="1.8">1.8</option>
              <option value="1.5">1.5</option>
              <option value="1.0">1.0</option>
              <option value="0.5">0.5</option>
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
              <option value="10">10</option>
              <option value="9.9">9.9</option>
              <option value="9.8">9.8</option>
              <option value="9.6">9.6</option>
              <option value="9.4">9.4</option>
              <option value="9.2">9.2</option>
              <option value="9.0">9.0</option>
              <option value="8.5">8.5</option>
              <option value="8.0">8.0</option>
              <option value="7.5">7.5</option>
              <option value="7.0">7.0</option>
              <option value="6.5">6.5</option>
              <option value="6.0">6.0</option>
              <option value="5.5">5.5</option>
              <option value="5.0">5.0</option>
              <option value="4.5">4.5</option>
              <option value="4.0">4.0</option>
              <option value="3.5">3.5</option>
              <option value="3.0">3.0</option>
              <option value="2.5">2.5</option>
              <option value="2.0">2.0</option>
              <option value="1.8">1.8</option>
              <option value="1.5">1.5</option>
              <option value="1.0">1.0</option>
              <option value="0.5">0.5</option>
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
<!-- Cover Popup Modal -->
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
            <tr>
              <th>Comic Title:</th>
              <td id="popupComicTitle"></td>
            </tr>
            <tr>
              <th>Years:</th>
              <td id="popupYears"></td>
            </tr>
            <tr>
              <th>Issue Number:</th>
              <td id="popupIssueNumber"></td>
            </tr>
            <tr>
              <th>Tab:</th>
              <td id="popupTab"></td>
            </tr>
            <tr>
              <th>Variant:</th>
              <td id="popupVariant"></td>
            </tr>
            <tr>
              <th>Date:</th>
              <td id="popupDate"></td>
            </tr>
            <tr>
              <th>UPC:</th>
              <td id="popupUPC"></td>
            </tr>
            <tr id="popupConditionRow">
              <th>Condition:</th>
              <td id="popupCondition"></td>
            </tr>
            <tr id="popupGradedRow">
              <th>Graded:</th>
              <td id="popupGraded"></td>
            </tr>
            <tr id="popupPriceRow">
              <th>Price:</th>
              <td id="popupPrice"></td>
            </tr>
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
