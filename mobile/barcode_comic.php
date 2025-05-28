<?php
// comicsmp/mobile/barcode_comic.php
?>
<div class="container mt-3" style="padding-bottom: 80px;">
  <h5 class="text-center fw-bold mb-3" style="color:#007BFF">
    Scan Comic Barcode (UPC + EAN-5)
  </h5>

  <div id="scanner-container" class="position-relative mx-auto" style="width: 100%; max-width: 600px;">
    <video id="video" autoplay playsinline muted
           style="width:100%;height:auto;border:2px solid #ccc;border-radius:8px;
                  background:#222">
    </video>
    <div class="barcode-guide"></div>
  </div>

  <div id="result" class="alert alert-info mt-3 text-center fw-semibold" style="font-size:1.1rem;">
    Waiting for barcode scan…
  </div>

  <button id="restartScan" class="btn btn-warning w-100 mt-2 fw-semibold" style="display:none;">
    Restart Scan
  </button>
</div>

<style>
  .barcode-guide {
    position:absolute; top:50%; left:50%;
    width:80%; height:30%; max-width:400px; max-height:120px;
    transform:translate(-50%,-50%);
    border:3px dashed #ff0000;
    pointer-events:none;
  }
</style>

<!-- ZXing Library -->
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script>
function initBarcodeScanner() {
  const codeReader = new ZXing.BrowserMultiFormatReader();
  const videoEl   = document.getElementById('video');
  const resultEl  = document.getElementById('result');
  const restartBt = document.getElementById('restartScan');

  let scanQueue = [], processing = false;
  let stableUPC = '', upcCount = 0;
  const MIN_DETECTIONS = 3;

  // 1) List devices & pick the back camera (or first)
  codeReader.listVideoInputDevices()
    .then(devices => {
      if (!devices.length) {
        resultEl.innerHTML = '❌ No camera found.';
        return;
      }
      // try to pick “environment” cam
      const back = devices.find(d => /back|environment/i.test(d.label));
      const deviceId = (back||devices[0]).deviceId;

      // 2) Start decoding from that device
      const hints = new Map();
      hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
        ZXing.BarcodeFormat.UPC_A,
        ZXing.BarcodeFormat.EAN_5
      ]);

      codeReader.decodeFromVideoDevice(deviceId, 'video', (result, err) => {
        if (result) {
          scanQueue.push(result);
          processQueue();
        } else if (err && !(err instanceof ZXing.NotFoundException)) {
          console.error('ZXing error', err);
          resultEl.innerHTML = '❌ ' + err.message;
        }
      }, hints);
    })
    .catch(err => {
      console.error('Camera init error', err);
      resultEl.innerHTML = `❌ Camera error: ${err.name} — ${err.message}`;
    });

  // 3) Confirm multiple frames before “found”
  function processQueue() {
    if (processing || !scanQueue.length) return;
    processing = true;

    let upc, sup, idxs = [];
    scanQueue.forEach((r,i) => {
      if (r.format === ZXing.BarcodeFormat.UPC_A && !upc) {
        upc = r.text; idxs.push(i);
      }
      if (r.format === ZXing.BarcodeFormat.EAN_5 && !sup) {
        sup = r.text; idxs.push(i);
      }
    });
    idxs.sort((a,b)=>b-a).forEach(i=>scanQueue.splice(i,1));

    if (upc) {
      if (stableUPC === upc) upcCount++;
      else { stableUPC = upc; upcCount = 1; }
      if (upcCount >= MIN_DETECTIONS) {
        resultEl.innerHTML = `<strong>Barcode Found:</strong> ${upc}${sup? ' - '+sup : ''}`;
        codeReader.reset();
        scanQueue = [];
        restartBt.style.display = 'block';
        setTimeout(captureAndSend, 500);
      }
    }

    processing = false;
    if (scanQueue.length) processQueue();
  }

  // 4) Snapshot + send
  function captureAndSend() {
    const canvas = document.createElement('canvas');
    canvas.width  = videoEl.videoWidth;
    canvas.height = videoEl.videoHeight;
    canvas.getContext('2d').drawImage(videoEl,0,0);

    canvas.toBlob(blob => {
      const fd = new FormData();
      fd.append('image', blob, 'barcode.jpg');
      fetch('http://localhost:5000/scan', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(data => {
          resultEl.innerHTML = `
            <strong>Barcode:</strong> ${data.full_code}<br>
            <strong>Comic:</strong> ${data.comic_title}<br>
            <strong>Issue:</strong> ${data.issue_number}
          `;
        })
        .catch(e => {
          console.error('POST error', e);
          resultEl.innerHTML += '<br>❌ Server error';
        });
    }, 'image/jpeg');
  }

  // 5) Restart
  restartBt.addEventListener('click', () => {
    resultEl.innerHTML = 'Waiting for barcode scan…';
    scanQueue = []; stableUPC=''; upcCount=0;
    codeReader.reset();
    restartBt.style.display = 'none';
    initBarcodeScanner();
  });
}

// When the AJAX load completes, your main page does:
//   $('#barcode').load(..., () => initBarcodeScanner());
// so our function will now actually run.
</script>
