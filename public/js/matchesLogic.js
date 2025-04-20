function initMatchesFiltering() {
    console.log("📦 initMatchesFiltering() triggered");
    console.log("Dropdown exists?", $('#matchSortSelect').length > 0);
    console.log("Row count:", $('tr.main-row').length);

    // Sorting dropdown
    $('#matchSortSelect').off('change').on('change', sortMatches);

    // Expand match row
    $('.expand-btn').off('click').on('click', function() {
        const uid = $(this).data('user-id');
        $('#detail-' + uid).toggle();
    });

    // Private message button
    $('.pm-btn').off('click').on('click', function() {
        const uid = $(this).data('user-id');
        alert('Open PM to user ' + uid);
    });

    // Cover modal with AJAX details
    $('.match-cover-img').off('click').on('click', function() {
        const img = $(this);
        const src = img.data('img-src');
        $('#coverModalImage').attr('src', src);

        const comicTitle = img.data('comic-title');
        const years = img.data('years');
        const issueNumber = img.data('issue-number');

        $.ajax({
            url: 'getMatchComicDetails.php',
            method: 'GET',
            data: { comic_title: comicTitle, years: years, issue_number: issueNumber },
            dataType: 'json',
            success: function(data) {
                $('#popupTab').text(data.Tab || 'N/A');
                $('#popupVariant').text(data.Variant || 'N/A');
                $('#popupDate').text(data.Date || 'N/A');
                $('#popupUPC').text(data.upc || 'N/A');
                $('#comicDetailsPopup').modal('show');
            },
            error: function() {
                $('#popupTab, #popupVariant, #popupDate, #popupUPC').text('Error');
            }
        });
    });

    // Delete match
    $('.delete-match-btn').off('click').on('click', function () {
        const uid = $(this).data('match-user-id');
        if (!confirm('Are you sure you want to permanently delete this match?')) return;

        $.post('/comicsmp/api/deleteMatch.php', { match_user_id: uid }, function (res) {
            if (res.status === 'success') {
                $(`tr.main-row[data-user-id='${uid}']`).fadeOut(300, function() { $(this).remove(); });
                $(`#detail-${uid}`).fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Failed to delete match: ' + res.message);
            }
        }, 'json');
    });

    // Initial sort on load
    sortMatches();
}

function sortMatches() {
    console.log("🔁 sortMatches triggered");

    const mode = $('#matchSortSelect').val();
    const $rows = $('tr.main-row').toArray();

    $rows.sort((a, b) => {
        const $a = $(a), $b = $(b);
        if (mode === 'newest') {
            return $b.data('match-time') - $a.data('match-time');
        } else if (mode === 'closest') {
            return $a.data('distance') - $b.data('distance');
        } else if (mode === 'most') {
            return $b.data('match-count') - $a.data('match-count');
        }
        return 0;
    });

    const $tableBody = $('#matchesTable tbody');
    $rows.forEach(row => {
        const uid = $(row).data('user-id');
        const $detailRow = $(`#detail-${uid}`);
        $tableBody.append($(row));
        $tableBody.append($detailRow);
    });
}
