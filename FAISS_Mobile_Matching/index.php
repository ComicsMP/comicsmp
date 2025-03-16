<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comic Search</title>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; }
    .container { margin: 50px auto; width: 50%; }
    .comic-result {
      border: 1px solid #ddd;
      padding: 10px;
      margin: 10px;
      display: inline-block;
      text-align: center;
    }
    .comic-result img {
      max-width: 200px;
      display: block;
      margin-bottom: 5px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Find Similar Comics</h2>
    <form id="uploadForm" enctype="multipart/form-data">
      <input type="file" name="file" id="fileInput" required>
      <button type="submit">Search</button>
    </form>
    <div id="result"></div>
  </div>

  <script>
    document.getElementById("uploadForm").addEventListener("submit", function(event) {
      event.preventDefault();
      let formData = new FormData();
      let fileInput = document.getElementById("fileInput");

      if (fileInput.files.length === 0) {
        alert("Please select a file");
        return;
      }

      formData.append("file", fileInput.files[0]);
      document.getElementById("result").innerHTML = "Searching...";

      fetch("http://127.0.0.1:5000/search", {  
          method: "POST",
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          let resultDiv = document.getElementById("result");
          resultDiv.innerHTML = "<h3>Top Matches:</h3>";

          // Check if data is an array. If not, handle as an object with message/error.
          if (!Array.isArray(data)) {
              if(data.message) {
                  resultDiv.innerHTML += `<p>${data.message}</p>`;
              } else if(data.error) {
                  resultDiv.innerHTML += `<p>Error: ${data.error}</p>`;
              } else {
                  resultDiv.innerHTML += `<p>Unexpected response format.</p>`;
              }
          } else if (data.length === 0) {
              resultDiv.innerHTML += "<p>No matches found.</p>";
          } else {
              data.forEach(comic => {
                  // Fix the Image_Path to remove FAISS from the URL
                  let imageUrl = comic.Image_Path.replace("FAISS/images/", "images/");
                  resultDiv.innerHTML += `
                      <div class="comic-result">
                          <img src="http://localhost/comicsmp/${imageUrl}" alt="Comic Cover">
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
          document.getElementById("result").innerHTML = "Error: " + error.message;
      });
    });
  </script>
</body>
</html>
