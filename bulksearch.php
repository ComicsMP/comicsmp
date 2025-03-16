<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Series Search</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .series-cover {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
        }

        .gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .gallery-item {
            width: calc(12.5% - 8px);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 5px;
            background-color: #f9f9f9;
            box-sizing: border-box;
        }

        .gallery-item img {
            width: 100%;
            height: 225px;
            object-fit: cover;
            margin-bottom: 5px;
            border-radius: 5px;
            cursor: pointer;
        }

        .gallery-item .description {
            max-width: 100%;
            height: 1.5rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 5px;
            text-align: center;
        }

        .gallery-item button {
            width: 100%;
            font-size: 0.8rem;
            padding: 6px 0;
            margin-top: auto;
        }

        .modal img {
            max-width: 100%;
            height: auto;
        }

        .modal-body .details {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center my-4">Complete Series Search</h1>
        <form id="bulkSearchForm">
            <div class="mb-3">
                <label for="seriesName" class="form-label">Series Name</label>
                <input type="text" id="seriesName" class="form-control" placeholder="Start typing series name...">
                <div id="suggestions" class="mt-2"></div>
            </div>
            <div id="yearDropdown" class="mb-3" style="display: none;">
                <label for="seriesYear" class="form-label">Series Year</label>
                <select id="seriesYear" class="form-select"></select>
            </div>
        </form>
        <div id="results" class="results-container">
            <h2 class="section-title">Results</h2>
            <div id="resultsContent" class="gallery"></div>
        </div>
    </div>

    <!-- Modal for Full-Size Image and Details -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Comic Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Full-Size Image">
                    <div class="details mt-3">
                        <p><strong>Series Name:</strong> <span id="modalSeriesName"></span></p>
                        <p><strong>Series Issue:</strong> <span id="modalSeriesIssue"></span></p>
                        <p><strong>Series Year:</strong> <span id="modalSeriesYear"></span></p>
                        <p><strong>Series Cover:</strong> <span id="modalSeriesCover"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            // Fetch series name suggestions
            $("#seriesName").on("input", function () {
                const input = $(this).val();
                if (input.length > 2) {
                    $.ajax({
                        url: "suggest.php",
                        method: "GET",
                        data: { q: input },
                        success: function (data) {
                            $("#suggestions").html(data);
                        },
                        error: function () {
                            $("#suggestions").html("<p class='text-danger'>Unable to fetch suggestions. Please try again.</p>");
                        }
                    });
                } else {
                    $("#suggestions").html("");
                }
                $("#resultsContent").html("");
                $("#yearDropdown").hide();
            });

            // Handle suggestion click
            $(document).on("click", ".suggestion", function () {
                const seriesName = $(this).text();
                $("#seriesName").val(seriesName);
                $("#suggestions").html("");

                $.ajax({
                    url: "getYears.php",
                    method: "GET",
                    data: { series: seriesName },
                    success: function (data) {
                        $("#yearDropdown").show();
                        $("#seriesYear").html('<option value="" selected disabled>Select a Series Year</option>' + data);
                    },
                    error: function () {
                        $("#yearDropdown").html("<p class='text-danger'>Unable to fetch years. Please try again.</p>");
                    }
                });
            });

            // Fetch results for the selected year
            $("#seriesYear").on("change", function () {
                const seriesName = $("#seriesName").val();
                const year = $(this).val();
                if (seriesName && year) {
                    fetchResults(seriesName, year);
                }
            });

            function fetchResults(seriesName, year) {
                $.ajax({
                    url: "bulkSearchResults.php",
                    method: "GET",
                    data: { series: seriesName, year: year },
                    success: function (data) {
                        $("#resultsContent").html(data);
                    },
                    error: function () {
                        $("#resultsContent").html("<p class='text-danger'>An error occurred. Please try again.</p>");
                    }
                });
            }

            // Enlarge image on click with details
            $(document).on("click", ".gallery-item img", function () {
                const fullImagePath = $(this).data("fullsize");
                const seriesName = $(this).data("series-name");
                const seriesYear = $(this).data("series-year");
                const seriesIssue = $(this).data("series-issue");
                const seriesCover = $(this).data("series-cover");

                $("#modalImage").attr("src", fullImagePath);
                $("#modalSeriesName").text(seriesName);
                $("#modalSeriesIssue").text(seriesIssue);
                $("#modalSeriesYear").text(seriesYear);
                $("#modalSeriesCover").text(seriesCover);

                $("#imageModal").modal("show");
            });

            // Handle Add to Wanted List button click
            $(document).on("click", ".add-to-wanted", function () {
                const button = $(this);
                const seriesName = button.data("series");
                const seriesYear = button.data("year");
                const issueNumber = button.data("issue");
                const seriesCover = button.data("series-cover");

                button.prop("disabled", true).text("Adding...");

                $.ajax({
                    url: "addToWanted.php",
                    method: "POST",
                    data: {
                        series_name: seriesName,
                        series_year: seriesYear,
                        issue_number: issueNumber,
                        series_cover: seriesCover
                    },
                    success: function (response) {
                        const result = JSON.parse(response);
                        if (result.status === "success") {
                            button.removeClass("btn-primary").addClass("btn-success").text("Added").prop("disabled", true);
                        } else {
                            alert(result.message || "Failed to add issue.");
                            button.prop("disabled", false).text("Add to List");
                        }
                    },
                    error: function () {
                        alert("An error occurred.");
                        button.prop("disabled", false).text("Add to List");
                    }
                });
            });
        });
    </script>
</body>
</html>
