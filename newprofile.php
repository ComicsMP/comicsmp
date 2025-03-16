<?php
include 'db_connection.php';
session_start();

$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo "Error: User not logged in.";
    exit;
}

// Fetch condensed wanted list
$wanted_query = "
    SELECT 
        wanted_items.series_name, 
        wanted_items.series_year, 
        GROUP_CONCAT(wanted_items.issue_number ORDER BY LENGTH(wanted_items.issue_number), wanted_items.issue_number ASC SEPARATOR ', ') AS issues,
        COUNT(wanted_items.issue_number) AS total_issues,
        comics.Image_Path_400 AS image_path_400
    FROM wanted_items
    LEFT JOIN comics 
    ON wanted_items.series_name = comics.Series_Name 
    AND wanted_items.issue_number = comics.Series_Issue 
    AND wanted_items.series_cover = comics.Series_Cover
    WHERE wanted_items.user_id = ?
    GROUP BY wanted_items.series_name, wanted_items.series_year";
$stmt = $conn->prepare($wanted_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wanted_result = $stmt->get_result();

// Fetch condensed selling list
$selling_query = "
    SELECT 
        selling_items.series_name, 
        selling_items.series_year, 
        GROUP_CONCAT(selling_items.issue_number ORDER BY LENGTH(selling_items.issue_number), selling_items.issue_number ASC SEPARATOR ', ') AS issues,
        COUNT(selling_items.issue_number) AS total_issues,
        selling_items.image_path, 
        selling_items.condition, 
        selling_items.price, 
        selling_items.currency
    FROM seller_listings AS selling_items
    WHERE selling_items.user_id = ?
    GROUP BY selling_items.series_name, selling_items.series_year";
$selling_stmt = $conn->prepare($selling_query);
$selling_stmt->bind_param("i", $user_id);
$selling_stmt->execute();
$selling_result = $selling_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        table { width: 100%; margin-top: 20px; }
        th, td { text-align: center; vertical-align: middle; }
        .issues-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .expanded-row { background-color: #f9f9f9; display: none; }
        .expanded-content { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; }
        .expanded-content .image-container {
            position: relative;
            display: inline-block;
        }
        .expanded-content img { width: 125px; height: auto; border-radius: 5px; cursor: pointer; }
        .delete-issue {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 5px;
            border-radius: 50%;
            font-size: 0.8rem;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.9);
        }
        .delete-issue i { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="text-center my-4">User Profile</h1>

    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="wanted-tab" data-bs-toggle="tab" data-bs-target="#wanted" role="tab" aria-controls="wanted" aria-selected="true">Wanted</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="sell-tab" data-bs-toggle="tab" data-bs-target="#sell" role="tab" aria-controls="sell" aria-selected="false">Sell</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Wanted Tab -->
        <div class="tab-pane fade show active" id="wanted" role="tabpanel" aria-labelledby="wanted-tab">
            <h2 class="mt-3">Wanted List</h2>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Series Name</th>
                        <th>Series Year</th>
                        <th>Issue # Missing</th>
                        <th>Total Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $wanted_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['series_name']) ?></td>
                            <td><?= htmlspecialchars($row['series_year']) ?></td>
                            <td class="issues-cell" title="<?= htmlspecialchars($row['issues']) ?>"><?= htmlspecialchars($row['issues']) ?></td>
                            <td><?= htmlspecialchars($row['total_issues']) ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm toggle-expand" 
                                    data-series-name="<?= htmlspecialchars($row['series_name']) ?>" 
                                    data-series-year="<?= htmlspecialchars($row['series_year']) ?>" 
                                    data-user-id="<?= $user_id ?>">
                                    Expand
                                </button>
                            </td>
                        </tr>
                        <tr class="expanded-row" id="expand-<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9]/', '-', $row['series_name'])) ?>-<?= htmlspecialchars($row['series_year']) ?>">
                            <td colspan="5">
                                <div class="expanded-content">
                                    <!-- Populated dynamically via AJAX -->
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Sell Tab -->
        <div class="tab-pane fade" id="sell" role="tabpanel" aria-labelledby="sell-tab">
            <h2 class="mt-3">Sell List</h2>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Series Name</th>
                        <th>Series Year</th>
                        <th>Issue # Selling</th>
                        <th>Total Amount</th>
                        <th>Condition</th>
                        <th>Price</th>
                        <th>Image</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $selling_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['series_name']) ?></td>
                            <td><?= htmlspecialchars($row['series_year']) ?></td>
                            <td class="issues-cell" title="<?= htmlspecialchars($row['issues']) ?>"><?= htmlspecialchars($row['issues']) ?></td>
                            <td><?= htmlspecialchars($row['total_issues']) ?></td>
                            <td><?= htmlspecialchars($row['condition']) ?></td>
                            <td><?= htmlspecialchars($row['price']) ?> <?= htmlspecialchars($row['currency']) ?></td>
                            <td>
                                <img src="<?= htmlspecialchars($row['image_path'] ?? 'placeholder.png') ?>" alt="Cover">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    $(document).on("click", ".toggle-expand", function () {
        const button = $(this);
        const seriesName = button.data("series-name");
        const seriesYear = button.data("series-year");
        const userId = button.data("user-id");
        const expandRow = $(`#expand-${seriesName.replace(/[^a-zA-Z0-9]/g, '-')}-${seriesYear}`);

        if (expandRow.is(":visible")) {
            expandRow.hide();
            button.text("Expand");
        } else {
            $.ajax({
                url: "fetchWantedIssues.php",
                method: "POST",
                data: { series_name: seriesName, series_year: seriesYear, user_id: userId },
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        const content = expandRow.find(".expanded-content");
                        content.empty();
                        response.data.forEach(issue => {
                            const card = `
                                <div class="image-container">
                                    <img src="${issue.image_path || 'placeholder.png'}" alt="Cover">
                                    <p class="text-center mt-2">${issue.issue_number}</p>
                                    <button class="btn btn-outline-danger btn-sm delete-issue" data-id="${issue.id}">Delete</button>
                                </div>`;
                            content.append(card);
                        });
                        expandRow.show();
                        button.text("Collapse");
                    } else {
                        alert(response.message);
                    }
                },
                error: function () {
                    alert("Error loading issues.");
                }
            });
        }
    });

    $(document).on("click", ".delete-issue", function () {
        const issueId = $(this).data("id");
        const issueContainer = $(this).closest(".image-container");

        $.ajax({
            url: "deleteWanted.php",
            method: "POST",
            data: { id: issueId },
            success: function (response) {
                const result = JSON.parse(response);
                if (result.status === "success") {
                    issueContainer.remove();
                } else {
                    alert(result.message || "Failed to delete issue.");
                }
            },
            error: function () {
                alert("Error deleting issue.");
            }
        });
    });
</script>
</body>
</html>
