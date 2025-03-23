<!-- mainContent.php -->
<!-- MAIN CONTENT AREA -->
<div class="main-content">
  <div class="tab-content" id="profileTabContent">
    
    <!-- DASHBOARD TAB -->
    <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
      <h2>Dashboard</h2>
      <p>Welcome to the Dashboard. Customize this section as needed.</p>
    </div>

    <!-- SEARCH TAB -->
    <div class="tab-pane fade" id="search" role="tabpanel">
      <section class="content-area">
        <div class="search-controls mb-3">
          <div id="tabButtons" class="btn-group mb-2" role="group" aria-label="Tab Buttons">
            <!-- Populated by your JavaScript (updateTabButtons) -->
          </div>
          <div class="d-flex align-items-center gap-2">
            <select id="issueSelectMain" class="form-select" style="display:none; max-width: 160px;">
              <!-- Populated by loadMainIssues() -->
            </select>
            <button id="variantToggleMain" type="button" class="btn btn-outline-primary" data-enabled="0" style="display:none;">
              Include Variants
            </button>
          </div>
        </div>
        <div id="resultsGallery" class="gallery"></div>
      </section>
    </div>

    <!-- WANTED TAB -->
    <div class="tab-pane fade" id="wanted" role="tabpanel">
      <?php if (empty($wantedSeries)): ?>
        <p>No wanted items found.</p>
      <?php else: ?>
        <table class="table table-striped" id="wantedTable">
          <thead>
            <tr>
              <th>Comic Title</th>
              <th>Years</th>
              <th>Issue Numbers</th>
              <th>Count</th>
              <th>Expand</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($wantedSeries as $index => $series): ?>
              <tr class="main-row" data-index="<?php echo $index; ?>">
                <td><?php echo htmlspecialchars($series['comic_title']); ?></td>
                <td><?php echo htmlspecialchars($series['years']); ?></td>
                <td><?php echo htmlspecialchars($series['issues']); ?></td>
                <td><?php echo htmlspecialchars($series['count']); ?></td>
                <td>
                  <button class="btn btn-info btn-sm expand-btn" 
                          data-comic-title="<?php echo htmlspecialchars($series['comic_title']); ?>" 
                          data-years="<?php echo htmlspecialchars($series['years']); ?>" 
                          data-issue-urls="<?php echo htmlspecialchars($series['issue_urls']); ?>"
                          data-index="<?php echo $index; ?>">
                    Expand
                  </button>
                </td>
              </tr>
              <tr class="expand-row" id="expand-<?php echo $index; ?>" style="display:none;">
                <td colspan="5">
                  <div class="cover-container" id="covers-<?php echo $index; ?>">
                    <!-- Wanted covers loaded via AJAX -->
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Edit Comic Modal -->
<div class="modal fade" id="editSaleModal" tabindex="-1" aria-labelledby="editSaleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editSaleForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editSaleModalLabel">Edit Sale Listing</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="listing_id" id="editListingId">
          <div class="mb-3">
            <label for="editPrice" class="form-label">Price</label>
            <input type="number" class="form-control" id="editPrice" name="price" required>
          </div>
          <div class="mb-3">
            <label for="editCondition" class="form-label">Condition</label>
            <select class="form-select" id="editCondition" name="condition" required>
  <option value="">Select Condition</option>
  <option value="10">10 (Gem Mint)</option>
  <option value="9.9">9.9 (Mint)</option>
  <option value="9.8">9.8 (Near Mint/Mint)</option>
  <option value="9.6">9.6 (Near Mint+)</option>
  <option value="9.4">9.4 (Near Mint)</option>
  <option value="9.2">9.2 (Near Mint–)</option>
  <option value="9.0">9.0 (Very Fine/Near Mint)</option>
  <option value="8.5">8.5 (Very Fine+)</option>
  <option value="8.0">8.0 (Very Fine)</option>
  <option value="7.5">7.5 (Very Fine–)</option>
  <option value="7.0">7.0 (Fine/Very Fine)</option>
  <option value="6.5">6.5 (Fine+)</option>
  <option value="6.0">6.0 (Fine)</option>
  <option value="5.5">5.5 (Fine–)</option>
  <option value="5.0">5.0 (Very Good/Fine)</option>
  <option value="4.5">4.5 (Very Good+)</option>
  <option value="4.0">4.0 (Very Good)</option>
  <option value="3.5">3.5 (Very Good–)</option>
  <option value="3.0">3.0 (Good/Very Good)</option>
  <option value="2.5">2.5 (Good+)</option>
  <option value="2.0">2.0 (Good)</option>
  <option value="1.8">1.8 (Good–)</option>
  <option value="1.5">1.5 (Fair/Good)</option>
  <option value="1.0">1.0 (Fair)</option>
  <option value="0.5">0.5 (Poor)</option>
</select>

          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Bulk Edit Modal -->
<div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="bulkEditForm">
        <div class="modal-header">
          <h5 class="modal-title" id="bulkEditModalLabel">Bulk Edit Series</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="comic_title" id="bulkComicTitle">
          <input type="hidden" name="years" id="bulkYears">
          <div class="mb-3">
            <label for="bulkPrice" class="form-label">New Price</label>
            <input type="number" class="form-control" id="bulkPrice" name="price" required>
          </div>
          <div class="mb-3">
  <label for="bulkCondition" class="form-label">New Condition</label>
  <select class="form-select" id="bulkCondition" name="condition" required>
    <option value="">Select Condition</option>
    <option value="10">10 (Gem Mint)</option>
    <option value="9.9">9.9 (Mint)</option>
    <option value="9.8">9.8 (Near Mint/Mint)</option>
    <option value="9.6">9.6 (Near Mint+)</option>
    <option value="9.4">9.4 (Near Mint)</option>
    <option value="9.2">9.2 (Near Mint–)</option>
    <option value="9.0">9.0 (Very Fine/Near Mint)</option>
    <option value="8.5">8.5 (Very Fine+)</option>
    <option value="8.0">8.0 (Very Fine)</option>
    <option value="7.5">7.5 (Very Fine–)</option>
    <option value="7.0">7.0 (Fine/Very Fine)</option>
    <option value="6.5">6.5 (Fine+)</option>
    <option value="6.0">6.0 (Fine)</option>
    <option value="5.5">5.5 (Fine–)</option>
    <option value="5.0">5.0 (Very Good/Fine)</option>
    <option value="4.5">4.5 (Very Good+)</option>
    <option value="4.0">4.0 (Very Good)</option>
    <option value="3.5">3.5 (Very Good–)</option>
    <option value="3.0">3.0 (Good/Very Good)</option>
    <option value="2.5">2.5 (Good+)</option>
    <option value="2.0">2.0 (Good)</option>
    <option value="1.8">1.8 (Good–)</option>
    <option value="1.5">1.5 (Fair/Good)</option>
    <option value="1.0">1.0 (Fair)</option>
    <option value="0.5">0.5 (Poor)</option>
  </select>
</div>


        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Apply to All</button>
        </div>
      </form>
    </div>
  </div>
</div>


    <!-- COMICS FOR SALE TAB -->
    <div class="tab-pane fade" id="selling" role="tabpanel">
      <?php if (empty($saleGroups)): ?>
        <p>No comics listed for sale.</p>
      <?php else: ?>
        <table class="table table-striped" id="sellingTable">
          <thead>
            <tr>
              <th>Comic Title</th>
              <th>Years</th>
              <th>Issue Numbers</th>
              <th>Count</th>
              <th>Expand</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($saleGroups as $index => $group): ?>
              <tr class="main-row" data-index="<?php echo $index; ?>">
                <td><?php echo htmlspecialchars($group['comic_title']); ?></td>
                <td><?php echo htmlspecialchars($group['years']); ?></td>
                <td><?php echo htmlspecialchars($group['issues']); ?></td>
                <td><?php echo htmlspecialchars($group['count']); ?></td>
                <td>
                  <button class="btn btn-info btn-sm sale-expand-btn"
                          data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>"
                          data-years="<?php echo htmlspecialchars($group['years']); ?>"
                          data-issue-urls="<?php echo htmlspecialchars($group['issue_urls']); ?>"
                          data-index="<?php echo $index; ?>">
                    Expand
                  </button>
                </td>
              </tr>
              <tr class="expand-row" id="expand-sale-<?php echo $index; ?>" style="display:none;">
                <td colspan="5">
                  <button class="btn btn-warning btn-sm bulk-edit-btn"
                          data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>"
                          data-years="<?php echo htmlspecialchars($group['years']); ?>"
                          data-index="<?php echo $index; ?>">
                    Bulk Edit Series
                  </button>
                  <div class="cover-container" id="sale-covers-<?php echo $index; ?>">
                    <!-- Sale covers loaded via AJAX -->
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- MATCHES TAB using Sub-Tabbed Interface with Messaging -->
    <div class="tab-pane fade" id="matches" role="tabpanel">
      <?php if (empty($groupedMatches)): ?>
        <p>No matches found at this time.</p>
      <?php else: ?>
        <div class="accordion" id="matchesAccordion">
          <?php foreach ($groupedMatches as $otherUserId => $matchesArray):
                  $displayName = $userNamesMap[$otherUserId] ?? ('User #'.$otherUserId);
                  // Separate matches into buy and sell groups
                  $buyMatches = array_filter($matchesArray, function($m) use ($user_id) {
                      return $m['buyer_id'] == $user_id;
                  });
                  $sellMatches = array_filter($matchesArray, function($m) use ($user_id) {
                      return $m['seller_id'] == $user_id;
                  });
                  // Determine overall intent: "buy", "sell", or "buy_sell"
                  $intent = ($buyMatches && !$sellMatches) ? 'buy' : (($sellMatches && !$buyMatches) ? 'sell' : 'buy_sell');
          ?>
            <div class="accordion-item">
              <h2 class="accordion-header" id="heading-<?php echo $otherUserId; ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $otherUserId; ?>">
                  <?php echo htmlspecialchars($displayName); ?> (<?php echo count($matchesArray); ?> matches)
                </button>
              </h2>
              <div id="collapse-<?php echo $otherUserId; ?>" class="accordion-collapse collapse" data-bs-parent="#matchesAccordion">
                <div class="accordion-body">
                  <!-- Sub-tabs for Buy, Sell, and Message -->
                  <ul class="nav nav-tabs" id="subTab-<?php echo $otherUserId; ?>" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="buy-tab-<?php echo $otherUserId; ?>" data-bs-toggle="tab" data-bs-target="#buy-<?php echo $otherUserId; ?>" type="button" role="tab">
                        Buy From <?php echo htmlspecialchars($displayName); ?>
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="sell-tab-<?php echo $otherUserId; ?>" data-bs-toggle="tab" data-bs-target="#sell-<?php echo $otherUserId; ?>" type="button" role="tab">
                        Sell To <?php echo htmlspecialchars($displayName); ?>
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="message-tab-<?php echo $otherUserId; ?>" data-bs-toggle="tab" data-bs-target="#message-<?php echo $otherUserId; ?>" type="button" role="tab">
                        Message <?php echo htmlspecialchars($displayName); ?>
                      </button>
                    </li>
                  </ul>
                  <div class="tab-content mt-2">
                    <!-- Buy Tab Content -->
                    <div class="tab-pane fade show active" id="buy-<?php echo $otherUserId; ?>" role="tabpanel">
                      <?php if (empty($buyMatches)): ?>
                        <p>No comics available to buy.</p>
                      <?php else: ?>
                        <table class="table table-bordered">
                          <thead>
                            <tr>
                              <th>Cover</th>
                              <th>Comic Title</th>
                              <th>Issue #</th>
                              <th>Year</th>
                              <th>Condition</th>
                              <th>Price</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($buyMatches as $m): ?>
                              <tr>
                                <td style="width:70px;">
                                  <img class="match-cover-img" 
                                       src="<?php echo htmlspecialchars(getFinalImagePath($m['image_path'])); ?>" 
                                       alt="Cover" 
                                       style="width:70px; height:100px; object-fit:cover;"
                                       data-comic-title="<?php echo htmlspecialchars($m['comic_title']); ?>"
                                       data-years="<?php echo htmlspecialchars($m['years']); ?>"
                                       data-issue-number="<?php echo htmlspecialchars($m['issue_number']); ?>"
                                       data-tab="<?php echo htmlspecialchars($m['tab'] ?? ''); ?>"
                                       data-variant="<?php echo htmlspecialchars($m['variant'] ?? ''); ?>"
                                       data-date="<?php echo htmlspecialchars($m['date'] ?? ''); ?>"
                                       data-upc="<?php echo htmlspecialchars($m['upc'] ?? $m['UPC'] ?? 'N/A'); ?>"
                                       data-condition="<?php echo htmlspecialchars($m['comic_condition'] ?? ''); ?>"
                                       data-graded="<?php echo htmlspecialchars(($m['graded'] ?? '') == '1' ? 'Yes' : 'No'); ?>"
                                       data-price="<?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                                <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                                <td><?php echo htmlspecialchars($m['years']); ?></td>
                                <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                                <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      <?php endif; ?>
                    </div>
                    <!-- Sell Tab Content -->
                    <div class="tab-pane fade" id="sell-<?php echo $otherUserId; ?>" role="tabpanel">
                      <?php if (empty($sellMatches)): ?>
                        <p>No comics available to sell.</p>
                      <?php else: ?>
                        <table class="table table-bordered">
                          <thead>
                            <tr>
                              <th>Cover</th>
                              <th>Comic Title</th>
                              <th>Issue #</th>
                              <th>Year</th>
                              <th>Condition</th>
                              <th>Price</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($sellMatches as $m): ?>
                              <tr>
                                <td style="width:70px;">
                                  <img class="match-cover-img" 
                                       src="<?php echo htmlspecialchars(getFinalImagePath($m['image_path'])); ?>" 
                                       alt="Cover" 
                                       style="width:70px; height:100px; object-fit:cover;"
                                       data-comic-title="<?php echo htmlspecialchars($m['comic_title']); ?>"
                                       data-years="<?php echo htmlspecialchars($m['years']); ?>"
                                       data-issue-number="<?php echo htmlspecialchars($m['issue_number']); ?>"
                                       data-tab="<?php echo htmlspecialchars($m['tab'] ?? ''); ?>"
                                       data-variant="<?php echo htmlspecialchars($m['variant'] ?? ''); ?>"
                                       data-date="<?php echo htmlspecialchars($m['date'] ?? ''); ?>"
                                       data-upc="<?php echo htmlspecialchars($m['upc'] ?? $m['UPC'] ?? 'N/A'); ?>"
                                       data-condition="<?php echo htmlspecialchars($m['comic_condition'] ?? ''); ?>"
                                       data-graded="<?php echo htmlspecialchars(($m['graded'] ?? '') == '1' ? 'Yes' : 'No'); ?>"
                                       data-price="<?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                                <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                                <td><?php echo htmlspecialchars($m['years']); ?></td>
                                <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                                <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      <?php endif; ?>
                    </div>
                    <!-- Message Tab Content -->
                    <div class="tab-pane fade" id="message-<?php echo $otherUserId; ?>" role="tabpanel">
                      <p class="small text-muted">
                        Please select the issues you wish to reference. The default subject and message will update automatically.
                      </p>
                      <form class="send-message-form" data-other-user-id="<?php echo $otherUserId; ?>" data-intent="<?php echo $intent; ?>" data-displayname="<?php echo htmlspecialchars($displayName); ?>">
                        <input type="hidden" name="recipient_id" value="<?php echo $otherUserId; ?>">
                        
                        <div class="mb-3">
                          <?php if (!empty($buyMatches)): ?>
                            <h6 class="text-secondary">Buy From <?php echo htmlspecialchars($displayName); ?></h6>
                            <?php foreach ($buyMatches as $index => $m): ?>
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="selected_issues_buy[]" value="<?php echo $index; ?>" id="issue-buy-<?php echo $otherUserId . '-' . $index; ?>">
                                <label class="form-check-label" for="issue-buy-<?php echo $otherUserId . '-' . $index; ?>">
                                  <?php 
                                    echo htmlspecialchars(
                                      ($m['comic_title'] ?? $m['Comic_Title'] ?? '') . " Issue " . 
                                      ($m['issue_number'] ?? $m['Issue_Number'] ?? '') . " (" . 
                                      ($m['years'] ?? $m['Years'] ?? '') . ") - " .
                                      "Condition: " . ($m['comic_condition'] ?? $m['condition'] ?? 'N/A') . ", " .
                                      "Price: " . (!empty($m['price']) ? '$'.number_format($m['price'],2).' '.($m['currency'] ?? $currency) : 'N/A')
                                    );
                                  ?>
                                </label>
                              </div>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                          <?php if (!empty($sellMatches)): ?>
                            <h6 class="text-secondary">Sell To <?php echo htmlspecialchars($displayName); ?></h6>
                            <?php foreach ($sellMatches as $index => $m): ?>
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="selected_issues_sell[]" value="<?php echo $index; ?>" id="issue-sell-<?php echo $otherUserId . '-' . $index; ?>">
                                <label class="form-check-label" for="issue-sell-<?php echo $otherUserId . '-' . $index; ?>">
                                  <?php 
                                    echo htmlspecialchars(
                                      ($m['comic_title'] ?? $m['Comic_Title'] ?? '') . " Issue " . 
                                      ($m['issue_number'] ?? $m['Issue_Number'] ?? '') . " (" . 
                                      ($m['years'] ?? $m['Years'] ?? '') . ") - " .
                                      "Condition: " . ($m['comic_condition'] ?? $m['condition'] ?? 'N/A') . ", " .
                                      "Price: " . (!empty($m['price']) ? '$'.number_format($m['price'],2).' '.($m['currency'] ?? $currency) : 'N/A')
                                    );
                                  ?>
                                </label>
                              </div>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                          <label for="subject-<?php echo $otherUserId; ?>" class="form-label">Subject</label>
                          <input type="text" class="form-control" id="subject-<?php echo $otherUserId; ?>" name="subject" placeholder="Enter subject">
                        </div>
                        
                        <div class="mb-3">
                          <label for="message-<?php echo $otherUserId; ?>-text" class="form-label">Message</label>
                          <textarea class="form-control" id="message-<?php echo $otherUserId; ?>-text" name="message" rows="4" placeholder="Enter your message here"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Message</button>
                      </form>
                    </div>
                  </div><!-- End sub-tab content -->
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- PROFILE TAB -->
    <div class="tab-pane fade" id="profile" role="tabpanel">
      <?php include 'includes/profile_content_inner.php'; ?>
    </div>

    <!-- MESSAGES TAB -->
    <div class="tab-pane fade" id="messages" role="tabpanel">
      <?php include 'messages.php'; ?>
    </div>
    
  </div>
</div>

<!-- Include jQuery and Bootstrap JS (if not already loaded) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Auto-Populate Default Subject and Message on Tab Show and on Checkbox Change -->
<script>
$('button[data-bs-target^="#message-"]').on('shown.bs.tab', function(e) {
  var targetId = $(this).data('bsTarget');
  var $messageTab = $(targetId);
  var form = $messageTab.find('.send-message-form');
  if(form.length) {
    updateDefaultText(form);
  }
});

$(document).on("change", ".send-message-form input[name='selected_issues_buy[]'], .send-message-form input[name='selected_issues_sell[]']", function() {
  var form = $(this).closest('.send-message-form');
  updateDefaultText(form);
});

function updateDefaultText(form) {
  var intent = form.data("intent"); 
  var displayName = form.data("displayname");
  var subjectField = form.find("input[name='subject']");
  var messageField = form.find("textarea[name='message']");
  
  var selectedBuy = [];
  form.find('input[name="selected_issues_buy[]"]:checked').each(function(){
    var labelText = $(this).siblings("label").text();
    selectedBuy.push(labelText);
  });
  var selectedSell = [];
  form.find('input[name="selected_issues_sell[]"]:checked').each(function(){
    var labelText = $(this).siblings("label").text();
    selectedSell.push(labelText);
  });
  
  var overallIntent = intent;
  if(selectedBuy.length > 0 && selectedSell.length === 0) {
    overallIntent = "buy";
  } else if(selectedSell.length > 0 && selectedBuy.length === 0) {
    overallIntent = "sell";
  } else if(selectedBuy.length > 0 && selectedSell.length > 0) {
    overallIntent = "buy_sell";
  }
  
  var defaultSubject = "";
  if(overallIntent === "buy") {
    defaultSubject = "Inquiry: Interested in Purchasing Matched Comics";
  } else if(overallIntent === "sell") {
    defaultSubject = "Inquiry: Interested in Selling Matched Comics";
  } else {
    defaultSubject = "Inquiry: Buy & Sell Inquiry for Matched Comics";
  }
  subjectField.val(defaultSubject);
  
  var defaultMessage = "Hello " + displayName + ",\n\n";
  if(overallIntent === "buy") {
    defaultMessage += "I am interested in purchasing the following issues:\n\n";
    defaultMessage += selectedBuy.join("\n") + "\n\n";
  } else if(overallIntent === "sell") {
    defaultMessage += "I am interested in selling the following issues:\n\n";
    defaultMessage += selectedSell.join("\n") + "\n\n";
  } else {
    defaultMessage += "I am interested in both buying and selling the following issues:\n\n";
    if(selectedBuy.length > 0) {
      defaultMessage += "Buy:\n" + selectedBuy.join("\n") + "\n\n";
    }
    if(selectedSell.length > 0) {
      defaultMessage += "Sell:\n" + selectedSell.join("\n") + "\n\n";
    }
  }
  defaultMessage += "Please let me know if you are interested.\n\nThank you.";
  messageField.val(defaultMessage);
}
</script>

<script>
$(document).on("submit", ".send-message-form", function(e) {
  e.preventDefault();
  var form = $(this);
  var formData = form.serialize();
  $.ajax({
    url: "sendMessage.php",
    method: "POST",
    data: formData,
    dataType: "json",
    success: function(response) {
      if (response.status === 'success') {
        alert("Message sent successfully.");
        form[0].reset();
      } else {
        alert("Error: " + response.message);
      }
    },
    error: function() {
      alert("Failed to send message.");
    }
  });
});
</script>
