<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Comic Search</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; background-color: #f4f4f4; padding: 20px; }
        .container { margin: 20px auto; width: 90%; max-width: 400px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .comic-result {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px;
            display: block;
            text-align: center;
            background: white;
            border-radius: 8px;
        }
        .comic-result img {
            max-width: 100%;
            display: block;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        button, input[type=file] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: block;
        }
        button { background-color: #007BFF; color: white; }
        button:active { background-color: #0056b3; }
        #cameraInput, #fileInput { display: none; }
        #loading {
            display: none;
            font-size: 18px;
            font-weight: bold;
            color: #007BFF;
        }
        #loading span {
            display: inline-block;
            animation: dots 1.5s infinite;
        }
        #loading span:nth-child(2) { animation-delay: 0.3s; }
        #loading span:nth-child(3) { animation-delay: 0.6s; }

        @keyframes dots {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Find Similar Comics</h2>

        <button onclick="openCamera()">📷 Take a Photo</button>
        <input type="file" id="cameraInput" accept="image/*" capture="environment">

        <button onclick="document.getElementById('fileInput').click()">📁 Choose a File</button>
        <input type="file" id="fileInput" accept="image/*">

        <div id="loading">Matching<span>.</span><span>.</span><span>.</span></div>
        <div id="result"></div>
    </div>

    <script>
        function openCamera() {
            document.getElementById("cameraInput").click();
        }

        function handleFileUpload(file) {
            if (!file) return;

            let formData = new FormData();
            formData.append("file", file);

            document.getElementById("loading").style.display = "block";
            document.getElementById("result").innerHTML = "";

            fetch("http://192.168.86.46:5000/search", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById("loading").style.display = "none";
                let resultDiv = document.getElementById("result");
                resultDiv.innerHTML = "<h3>Top Matches:</h3>";

                if (!Array.isArray(data) || data.length === 0) {
                    resultDiv.innerHTML += "<p>No matches found.</p>";
                } else {
                    data.forEach(comic => {
                        let imageUrl = comic.Image_Path.replace("FAISS/images/", "images/");
                        resultDiv.innerHTML += `
                            <div class="comic-result">
                                <img src="http://192.168.86.46/comicsmp/${imageUrl}" alt="Comic Cover">
                                <p><strong>${comic.Comic_Title}</strong></p>
                                <p>Issue: ${comic.Issue_Number}</p>
                                <p>Variant: ${comic.Variant ? comic.Variant : "Standard"}</p>
                            </div>
                        `;
                    });
                }
            })
            .catch(error => {
                console.error("Error:", error);
                document.getElementById("loading").style.display = "none";
                document.getElementById("result").innerHTML = "Error: " + error.message;
            });
        }

        document.getElementById("cameraInput").addEventListener("change", function() {
            handleFileUpload(this.files[0]);
        });

        document.getElementById("fileInput").addEventListener("change", function() {
            handleFileUpload(this.files[0]);
        });
    </script>
</body>
</html>
