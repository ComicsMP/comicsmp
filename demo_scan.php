<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dynamsoft EAN-5 Scanner</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            text-align: center;
        }
        #video-container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            border: 2px solid #333;
        }
        #result {
            margin-top: 20px;
            font-size: 1.5em;
            color: #007bff;
        }
        button {
            padding: 10px 20px;
            font-size: 1em;
            cursor: pointer;
        }
    </style>
    <!-- Load Dynamsoft Barcode Reader SDK from CDN -->
    <script src="https://unpkg.com/dynamsoft-javascript-barcode@latest/dist/dbr.js"></script>
</head>
<body>
    <h2>Dynamsoft EAN-5 Scanner</h2>
    <div id="video-container"></div>
    <div id="result">Waiting for EAN-5 scan...</div>
    <button id="restartScan">Restart Scan</button>
    
    <script>
        // Set the resource path for Dynamsoft engine resources.
        Dynamsoft.BarcodeReader.engineResourcePath = "https://unpkg.com/dynamsoft-javascript-barcode@latest/dist/";
        // Optional: Insert your Dynamsoft license key if you have one.
        // Dynamsoft.BarcodeReader.productKeys = "YOUR_LICENSE_KEY";

        let scannerInstance;

        function initializeScanner() {
            // Create a new scanner instance.
            Dynamsoft.BarcodeScanner.createInstance().then(scanner => {
                scannerInstance = scanner;
                // Bind the video UI container.
                scannerInstance.setUIElement(document.getElementById("video-container"));

                // Callback when a barcode is read from a frame.
                scannerInstance.onFrameRead = results => {
                    for (let i = 0; i < results.length; i++) {
                        const result = results[i];
                        console.log("Format:", result.barcodeFormatString, "Text:", result.barcodeText);
                        // Only act if an EAN-5 code is found.
                        if(result.barcodeFormatString === "EAN-5") {
                            document.getElementById("result").innerText = "EAN-5 Code: " + result.barcodeText;
                            // Pause further scanning upon a successful read.
                            scannerInstance.pause();
                            break;
                        }
                    }
                };

                // Start the scanner (shows video feed and begins scanning).
                scannerInstance.show();
            }).catch(err => {
                console.error("Failed to initialize scanner:", err);
                document.getElementById("result").innerText = "Error initializing scanner: " + err;
            });
        }

        // Initialize scanner when the page loads.
        window.addEventListener('load', function() {
            initializeScanner();
            
            // Restart button to resume scanning.
            document.getElementById("restartScan").addEventListener("click", function() {
                document.getElementById("result").innerText = "Waiting for EAN-5 scan...";
                if (scannerInstance) {
                    scannerInstance.resume();
                } else {
                    initializeScanner();
                }
            });
        });
    </script>
</body>
</html>
