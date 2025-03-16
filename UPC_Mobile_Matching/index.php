<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Live UPC + EAN-5 Scanner with Server Processing</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body {
      background-color: black;
      color: white;
      text-align: center;
      font-family: Arial, sans-serif;
    }
    #scanner-container {
      position: relative;
      width: 100%;
      max-width: 500px;
      margin: auto;
    }
    #video {
      width: 100%;
      border: 1px solid #ddd;
    }
    /* Barcode guide overlay */
    .barcode-guide {
      position: absolute;
      top: 50%;
      left: 50%;
      width: 60%;
      height: 20%;
      max-width: 300px;
      max-height: 100px;
      transform: translate(-50%, -50%);
      border: 3px dashed #ff0000;
      pointer-events: none;
    }
    #result {
      margin-top: 20px;
      font-size: 1.3rem;
      font-weight: bold;
      color: #007bff;
    }
    .btn-custom {
      width: 90%;
      max-width: 500px;
      margin: 10px auto;
      font-size: 1.2rem;
      padding: 10px;
    }
  </style>
</head>
<body>
  <div class="container mt-3">
    <h2 class="text-center">Live UPC + EAN-5 Scanner</h2>
    <div id="scanner-container">
      <video id="video" autoplay playsinline></video>
      <div class="barcode-guide"></div>
    </div>
    <div id="result" class="alert alert-info mt-3">
      Waiting for barcode scan...
    </div>
    <button id="restartScan" class="btn btn-warning btn-custom" style="display:none;">Restart Scan</button>
  </div>

  <!-- Include ZXing library -->
  <script src="https://unpkg.com/@zxing/library@latest"></script>
  <script>
    window.addEventListener('load', function () {
      const codeReader = new ZXing.BrowserMultiFormatReader();
      const videoElement = document.getElementById('video');
      const resultElement = document.getElementById('result');
      const restartBtn = document.getElementById('restartScan');
      let selectedDeviceId;
      let scanQueue = [];
      let processing = false;

      // Variables for multi-frame confirmation
      let stableUPC = "";
      let upcCount = 0;
      const MIN_CONSECUTIVE_DETECTIONS = 3; // Number of consecutive detections required

      // List video input devices and choose the last one (often the back camera)
      codeReader.listVideoInputDevices()
        .then((videoInputDevices) => {
          if (videoInputDevices.length > 0) {
            selectedDeviceId = videoInputDevices[videoInputDevices.length - 1].deviceId;
            startScanner();
          }
        })
        .catch((err) => {
          console.error(err);
          resultElement.innerHTML = "Error listing video devices: " + err;
        });

      function startScanner() {
        // Set hints to only look for UPC_A and EAN_5
        const hints = new Map();
        hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
          ZXing.BarcodeFormat.UPC_A,
          ZXing.BarcodeFormat.EAN_5
        ]);
        codeReader.decodeFromVideoDevice(selectedDeviceId, 'video', (result, err) => {
          if (result) {
            scanQueue.push(result);
            processQueue();
          } else if (err && !(err instanceof ZXing.NotFoundException)) {
            console.error(err);
            resultElement.innerHTML = "Error: " + err;
          }
        }, hints);
      }

      function processQueue() {
        if (processing || scanQueue.length === 0) return;
        processing = true;
        let upc = null;
        let supplemental = null;
        let processedIndices = [];
        // Process the queue for UPC_A and EAN_5 codes.
        for (let i = 0; i < scanQueue.length; i++) {
          const result = scanQueue[i];
          if (result.format === ZXing.BarcodeFormat.UPC_A && !upc) {
            upc = result.text;
            processedIndices.push(i);
          } else if (result.format === ZXing.BarcodeFormat.EAN_5 && !supplemental) {
            supplemental = result.text;
            processedIndices.push(i);
          }
        }
        // Remove processed items
        processedIndices.sort((a, b) => b - a);
        processedIndices.forEach(index => scanQueue.splice(index, 1));

        // Multi-frame confirmation for UPC detection
        if (upc) {
          if (stableUPC === upc) {
            upcCount++;
          } else {
            stableUPC = upc;
            upcCount = 1;
          }
          // Only proceed if we have the required number of consecutive detections
          if (upcCount >= MIN_CONSECUTIVE_DETECTIONS) {
            resultElement.innerHTML = "<strong>Barcode Found:</strong> " + upc + (supplemental ? " - " + supplemental : "");
            // Introduce a 500ms delay before capturing & sending to server
            setTimeout(() => {
              captureSnapshotAndSend();
              codeReader.reset();
              scanQueue = [];
              restartBtn.style.display = "block";
              // Reset stable detection variables for future scans
              stableUPC = "";
              upcCount = 0;
            }, 500);
          }
        }
        processing = false;
        if (scanQueue.length > 0) {
          processQueue();
        }
      }

      function captureSnapshotAndSend() {
        // Create a temporary canvas to capture the current frame.
        const canvas = document.createElement("canvas");
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function(blob) {
          const formData = new FormData();
          formData.append('image', blob, 'barcode.jpg');
          fetch('http://192.168.86.46:5000/scan', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            console.log("Server Response:", data);
            // Display barcode and comic details
            resultElement.innerHTML = `
              <strong>Barcode Found:</strong> ${data.full_code} <br>
              <strong>Comic Title:</strong> ${data.comic_title} <br>
              <strong>Issue Number:</strong> ${data.issue_number}
            `;
          })
          .catch(error => {
            console.error("Error sending image:", error);
            resultElement.innerHTML += "<br>❌ Server Error. Try again!";
          });
        }, 'image/jpeg');
      }

      restartBtn.addEventListener('click', function () {
        resultElement.innerHTML = "Waiting for barcode scan...";
        codeReader.reset();
        scanQueue = [];
        restartBtn.style.display = "none";
        // Reset stable detection variables when restarting
        stableUPC = "";
        upcCount = 0;
        startScanner();
      });
    });
  </script>
</body>
</html>
