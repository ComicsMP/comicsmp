<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Live UPC + EAN-5 Scanner with Server Processing</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body { background-color: black; color: white; text-align: center; font-family: Arial, sans-serif; }
    #scanner-container { position: relative; width: 100%; max-width: 500px; margin: auto; }
    #video { width: 100%; border: 1px solid #ddd; }
    .barcode-guide {
      position: absolute; top: 50%; left: 50%;
      width: 60%; height: 20%; max-width: 300px; max-height: 100px;
      transform: translate(-50%, -50%); border: 3px dashed #ff0000;
      pointer-events: none;
    }
    #result { margin-top: 20px; font-size: 1.3rem; font-weight: bold; color: #007bff; }
    .btn-custom { width: 90%; max-width: 500px; margin: 10px auto; font-size: 1.2rem; padding: 10px; }
  </style>
</head>
<body>
  <div class="container mt-3">
    <h2 class="text-center">Live UPC + EAN-5 Scanner</h2>
    <div id="scanner-container">
      <video id="video" autoplay playsinline muted></video>
      <div class="barcode-guide"></div>
    </div>
    <div id="result" class="alert alert-info mt-3">
      Waiting for barcode scan...
    </div>
    <button id="restartScan" class="btn btn-warning btn-custom" style="display:none;">Restart Scan</button>
  </div>

  <!-- ZXing Library -->
  <script src="https://unpkg.com/@zxing/library@latest"></script>
  <script>
  window.addEventListener('load', () => {
    const codeReader = new ZXing.BrowserMultiFormatReader();
    const video = document.getElementById('video');
    const resultEl = document.getElementById('result');
    const restartBtn = document.getElementById('restartScan');

    let scanQueue = [], processing = false;
    let stableUPC = "", upcCount = 0;
    const MIN_DETECTIONS = 3;

    // 1. Acquire camera stream yourself
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(stream => {
        video.srcObject = stream;
        video.onloadedmetadata = () => video.play();
        startDecoding();
      })
      .catch(err => {
        console.error('getUserMedia error:', err);
        resultEl.innerHTML = `❌ Camera access error: ${err.name} — ${err.message}`;
      });

    // 2. Hand the <video> element to ZXing continuously
    function startDecoding() {
      const hints = new Map();
      hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
        ZXing.BarcodeFormat.UPC_A,
        ZXing.BarcodeFormat.EAN_5
      ]);

      codeReader.decodeFromVideoElementContinuously(
        video,
        (result, error) => {
          if (result) {
            scanQueue.push(result);
            processQueue();
          } else if (error && !(error instanceof ZXing.NotFoundException)) {
            console.error('ZXing decode error:', error);
          }
        },
        hints
      );
    }

    function processQueue() {
      if (processing || !scanQueue.length) return;
      processing = true;

      let upc = null, sup = null, idxs = [];
      scanQueue.forEach((r, i) => {
        if (r.format === ZXing.BarcodeFormat.UPC_A && !upc) { upc = r.text; idxs.push(i); }
        if (r.format === ZXing.BarcodeFormat.EAN_5 && !sup) { sup = r.text; idxs.push(i); }
      });
      idxs.sort((a,b)=>b-a).forEach(i=>scanQueue.splice(i,1));

      if (upc) {
        if (stableUPC === upc) upcCount++;
        else { stableUPC = upc; upcCount = 1; }
        if (upcCount >= MIN_DETECTIONS) {
          resultEl.innerHTML = `<strong>Barcode Found:</strong> ${upc}${sup? ' - '+sup: ''}`;
          // pause decoding, snapshot & send
          codeReader.reset();
          scanQueue = [];
          setTimeout(captureAndSend, 500);
          restartBtn.style.display = 'block';
        }
      }

      processing = false;
      if (scanQueue.length) processQueue();
    }

    function captureAndSend() {
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video,0,0);
      canvas.toBlob(blob => {
        const fd = new FormData();
        fd.append('image', blob, 'barcode.jpg');
        fetch('http://192.168.86.68:5000/scan', { method:'POST', body:fd })
          .then(r=>r.json()).then(data=>{
            resultEl.innerHTML = `
              <strong>Barcode:</strong> ${data.full_code}<br>
              <strong>Title:</strong> ${data.comic_title}<br>
              <strong>Issue:</strong> ${data.issue_number}
            `;
          })
          .catch(e=>{
            console.error('POST error:', e);
            resultEl.innerHTML += '<br>❌ Server error';
          });
      }, 'image/jpeg');
    }

    restartBtn.addEventListener('click', () => {
      resultEl.innerHTML = 'Waiting for barcode scan...';
      stableUPC = ''; upcCount = 0; scanQueue = [];
      codeReader.reset();
      restartBtn.style.display = 'none';
      startDecoding();
    });
  });
  </script>
</body>
</html>
