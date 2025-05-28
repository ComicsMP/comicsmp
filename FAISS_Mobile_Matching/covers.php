<?php
session_start();
require_once '../db_connection.php';  // adjust path if needed
if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}
$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>List Comics</title>


  <!-- Bootstrap & Icons -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

  <style>
    * { box-sizing: border-box; margin:0; padding:0 }
    body { font-family:'Roboto',sans-serif; background:#f4f4f4; color:#333; padding-bottom:60px; }
    a { text-decoration:none; color:inherit; }

    /* Header */
    .header-row {
      display:flex;
      align-items:center;
      background:#007BFF;
      padding:10px 15px;
      margin-bottom:0;
    }
    .header-row .back-icon {
      font-size:24px;
      color:#fff;
      margin-right:10px;
      cursor:pointer;
    }
    .header-row h2 {
      color:#fff;
      font-size:18px;
      margin:0;
    }

   

    /* General Layout */
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
    #progressContainer { margin-bottom: 15px; }
    #progressText { font-size: 16px; margin-bottom: 5px; }
    .progress-bar {
      width: 0%; height: 10px; background-color: #007BFF;
      border-radius: 5px; transition: width 0.3s ease;
    }

    /* Buttons & Inputs */
    button, input[type=file], input[type=text], input[type=number], select {
      width: 100%; padding: 12px; margin: 10px 0;
      font-size: 16px; border: none; border-radius: 5px;
      cursor: pointer; display: block;
    }
    .btn-group { display: flex; justify-content: center; gap: 10px; }
    .btn-group button { flex: 1; }
    button { background-color: #007BFF; color: white; }
    button:active { background-color: #0056b3; }

    /* Specific Colors */
    .green-button { background-color: green; }
    .red-button   { background-color: red; }
    .black-button { background-color: black; }
    .blue-button  { background-color: blue; }

    /* Hide file inputs */
    #cameraInput, #fileInput { display: none; }

    /* Loading */
    #loading {
      display: none; font-size: 18px; font-weight: bold; color: #007BFF;
    }
    #loading span {
      display: inline-block; animation: dots 1.5s infinite;
    }
    #loading span:nth-child(2) { animation-delay: 0.3s; }
    #loading span:nth-child(3) { animation-delay: 0.6s; }
    @keyframes dots {
      0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; }
    }

    /* Image Styles */
    .comic-result img,
    .candidate img,
    .listing-cover img { display: block; width: 100%; object-fit: contain; }
    .single-candidate img { height: 250px; }
    .listing-cover img { height: 300px; }
    .full-width-image {
      width: 100% !important; height: auto !important; display: block;
    }

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}
html, body {
  margin: 0;
  padding: 0;
  background: #f4f4f4;
  font-family: 'Roboto', sans-serif;
  color: #333;
}

.tabs {
  display: flex;
  margin: 0;
  padding: 0;
  border-bottom: 1px solid #ccc;
  background: #fff;
}

.tab-btn {
  flex: 1;
  padding: 12px 10px;
  border: none;
  background: #eee;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tab-btn.active {
  background: #007BFF;
  color: #fff;
}

.tab-btn i {
  font-size: 18px;
  margin-right: 6px;
}




    /* Candidate Grid */
    .candidate-container {
      display: flex; flex-wrap: wrap; justify-content: space-around;
    }
    .candidate { width: 48%; margin-bottom: 5px; padding: 0; }
    .candidate-info, .single-info {
      text-align: left; font-size: 14px; margin: 5px 0 0 0; padding: 0; line-height: 1;
    }
    .issue-row   { background-color: #ffffff; }
    .country-row { background-color: #f2f2f2; }

    /* Action Buttons */
    .action-buttons { text-align: center; }
    .action-buttons .btn-group { margin-top: 10px; }

    

    /* Details Table */
    .details-table {
      width: auto; margin: 0 auto; border-collapse: collapse; margin-bottom: 20px;
    }
    .details-table tr:nth-child(odd)  { background-color: #f2f2f2; }
    .details-table tr:nth-child(even) { background-color: #ffffff; }
    .details-table td {
      padding: 8px 5px; text-align: left; vertical-align: middle; font-size: 16px;
    }
    .details-table .label { width: 40%; font-weight: bold; }
    .details-table .input { width: 60%; }

    .price-group {
      display: flex; align-items: center; justify-content: flex-start;
    }
    .price-group span {
      font-size: 16px; font-weight: bold; padding-right: 5px;
    }
    .price-group input {
      flex: 1; text-align: left; font-size: 16px;
    }
    .cancel-button { background-color: red !important; }

    /* Unified & Individual modes, final message, etc. */
    /* ... (rest of your existing styles) ... */
  </style>
</head>
<body>
 <!-- Full replacement block -->
<div class="header-row">
  <div class="back-icon" onclick="history.back();">
    <i class="bi bi-arrow-left-circle-fill"></i>
  </div>
  <h2>List Comics</h2>
</div>

<!-- Insert this EXACTLY after the header row in covers.php -->
<div class="tabs" style="margin-bottom: 0;">
  <button class="tab-btn" onclick="window.location.href='../mobile/list_comics.php'">
    <i class="bi bi-keyboard-fill"></i>Manual
  </button>
  <button class="tab-btn active" data-target="cover">
    <i class="bi bi-card-image"></i>Cover
  </button>
  <button class="tab-btn" data-target="barcode">
    <i class="bi bi-upc-scan"></i>UPC
  </button>
</div>

<?php include __DIR__ . '/covers_partial.php'; ?>

  
</body>
</html>
