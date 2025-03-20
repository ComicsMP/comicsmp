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
      <!-- The offcanvas panel will open automatically when Search is activated -->
      <section class="content-area">
        <!-- Insert your tab buttons, issueSelectMain, and variantToggleMain above the gallery.
             The script uses "#tabButtons", "#issueSelectMain", and "#variantToggleMain" dynamically. -->
        <div class="search-controls mb-3">
          <!-- Tab Buttons -->
          <div id="tabButtons" class="btn-group mb-2" role="group" aria-label="Tab Buttons">
            <!-- Populated by your JavaScript (updateTabButtons) -->
          </div>

          <!-- Issue Select & Variant Toggle -->
          <div class="d-flex align-items-center gap-2">
            <select id="issueSelectMain" class="form-select" style="display:none; max-width: 160px;">
              <!-- Populated by loadMainIssues() -->
            </select>
            <button id="variantToggleMain" type="button" class="btn btn-outline-primary" data-enabled="0" style="display:none;">
              Include Variants
            </button>
          </div>
        </div>
        <!-- Results Gallery -->
        <div id="resultsGallery" class="gallery"></div>
      </section>
    </div>

    <!-- WANTED TAB -->
    <div class="tab-pane fade" id="wanted" role="tabpanel">
      <h2 class="mt-4">My Wanted Comics</h2>
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

    <!-- COMICS FOR SALE TAB -->
    <div class="tab-pane fade" id="selling" role="tabpanel">
      <h2 class="mt-4">Comics for Sale</h2>
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

    <!-- MATCHES TAB -->
    <div class="tab-pane fade" id="matches" role="tabpanel">
      <h2 class="mt-4">Your Matches</h2>
      <?php if (empty($groupedMatches)): ?>
        <p>No matches found at this time.</p>
      <?php else: ?>
        <table class="table table-striped" id="matchesTable">
          <thead>
            <tr>
              <th>Other Party</th>
              <th># of Issues Matched</th>
              <th>Contact</th>
              <th>Expand</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groupedMatches as $otherUserId => $matchesArray):
                    $displayName = $userNamesMap[$otherUserId] ?? ('User #'.$otherUserId);
            ?>
              <tr class="match-main-row" data-index="<?php echo $otherUserId; ?>">
                <td><?php echo htmlspecialchars($displayName); ?></td>
                <td><?php echo count($matchesArray); ?></td>
                <td>
                  <button class="btn btn-sm btn-primary send-message-btn"
                          data-other-user-id="<?php echo $otherUserId; ?>"
                          data-other-username="<?php echo htmlspecialchars($displayName); ?>"
                          data-matches='<?php echo json_encode($matchesArray); ?>'>
                    PM
                  </button>
                </td>
                <td>
                  <button class="btn btn-info btn-sm expand-match-btn"
                          data-other-user-id="<?php echo $otherUserId; ?>">
                    Expand
                  </button>
                </td>
              </tr>
              <tr class="expand-match-row" id="expand-match-<?php echo $otherUserId; ?>" style="display:none;">
                <td colspan="4">
                  <?php 
                    $buyMatches = array_filter($matchesArray, function($m) use ($user_id) {
                        return $m['buyer_id'] == $user_id;
                    });
                    $sellMatches = array_filter($matchesArray, function($m) use ($user_id) {
                        return $m['seller_id'] == $user_id;
                    });
                  ?>
                  <?php if (!empty($buyMatches)): ?>
                    <h5>Comics You Can Buy From <?php echo htmlspecialchars($displayName); ?></h5>
                    <table class="table table-bordered nested-table">
                      <thead>
                        <tr>
                          <th>Cover</th>
                          <th>Comic Title</th>
                          <th>Issue #</th>
                          <th>Year</th>
                          <th>Condition</th>
                          <th>Graded</th>
                          <th>Price</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($buyMatches as $m): ?>
                          <tr>
                            <td style="width:70px;">
                              <img class="match-cover-img cover-img"
                                   src="<?php echo htmlspecialchars(getFinalImagePath($m['image_path'])); ?>"
                                   data-context="buy"
                                   data-other-user-id="<?php echo $otherUserId; ?>"
                                   data-comic-title="<?php echo htmlspecialchars($m['comic_title']); ?>"
                                   data-years="<?php echo htmlspecialchars($m['years']); ?>"
                                   data-issue-number="<?php echo htmlspecialchars($m['issue_number']); ?>"
                                   data-tab="<?php echo htmlspecialchars($m['tab'] ?? ''); ?>"
                                   data-variant="<?php echo htmlspecialchars($m['variant'] ?? ''); ?>"
                                   data-date="<?php echo htmlspecialchars($m['date'] ?? ''); ?>"
                                   data-upc="<?php echo htmlspecialchars($m['upc'] ?? 'N/A'); ?>"
                                   alt="Cover">
                            </td>
                            <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                            <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                            <td><?php echo htmlspecialchars($m['years']); ?></td>
                            <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                            <td><?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?></td>
                            <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php endif; ?>

                  <?php if (!empty($sellMatches)): ?>
                    <h5>Comics You Can Sell To <?php echo htmlspecialchars($displayName); ?></h5>
                    <table class="table table-bordered nested-table">
                      <thead>
                        <tr>
                          <th>Cover</th>
                          <th>Comic Title</th>
                          <th>Issue #</th>
                          <th>Year</th>
                          <th>Condition</th>
                          <th>Graded</th>
                          <th>Price</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($sellMatches as $m): ?>
                          <tr>
                            <td style="width:70px;">
                              <img class="match-cover-img cover-img"
                                   src="<?php echo htmlspecialchars(getFinalImagePath($m['image_path'])); ?>"
                                   data-context="sell"
                                   data-other-user-id="<?php echo $otherUserId; ?>"
                                   data-comic-title="<?php echo htmlspecialchars($m['comic_title']); ?>"
                                   data-years="<?php echo htmlspecialchars($m['years']); ?>"
                                   data-issue-number="<?php echo htmlspecialchars($m['issue_number']); ?>"
                                   data-tab="<?php echo htmlspecialchars($m['tab'] ?? ''); ?>"
                                   data-variant="<?php echo htmlspecialchars($m['variant'] ?? ''); ?>"
                                   data-date="<?php echo htmlspecialchars($m['date'] ?? ''); ?>"
                                   data-upc="<?php echo htmlspecialchars($m['upc'] ?? 'N/A'); ?>"
                                   alt="Cover">
                            </td>
                            <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                            <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                            <td><?php echo htmlspecialchars($m['years']); ?></td>
                            <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                            <td><?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?></td>
                            <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- PROFILE TAB -->
    <div class="tab-pane fade" id="profile" role="tabpanel">
      <?php include 'profile_content_inner.php'; ?>
    </div>

  </div>
</div>
