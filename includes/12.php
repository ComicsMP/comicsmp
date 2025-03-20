<?php
// 12.php – Geolocation Test with Google API
?>
<h2>Testing 12.php inside Dashboard (With API)</h2>
<p>Click the button to detect your location and city:</p>
<button id="detectLocationBtn">Detect My Location</button>
<div id="locationResult"></div>

<script>
document.getElementById("detectLocationBtn").addEventListener("click", function() {
  if (!navigator.geolocation) {
    alert("Geolocation is not supported by this browser.");
    return;
  }
  
  navigator.geolocation.getCurrentPosition(
    function(position) {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      
      // Display coordinates
      document.getElementById("locationResult").innerText = 
        "Coordinates: " + lat + ", " + lng + " (Fetching city...)";

      // Call Google Maps API to get city
      fetch(`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=AIzaSyBQ_S-MNLPXfeguaEQ1dOpww8vAo9bXJIw`)
        .then(response => response.json())
        .then(data => {
          if (data.status === "OK") {
            let city = "Unknown City";

            // Extract city name from API response
            for (let i = 0; i < data.results.length; i++) {
              let components = data.results[i].address_components;
              for (let j = 0; j < components.length; j++) {
                if (components[j].types.includes("locality")) {
                  city = components[j].long_name;
                  break;
                }
              }
              if (city !== "Unknown City") break;
            }

            document.getElementById("locationResult").innerText = 
              "Coordinates: " + lat + ", " + lng + " | City: " + city;
          } else {
            document.getElementById("locationResult").innerText = 
              "Coordinates: " + lat + ", " + lng + " | Error getting city.";
          }
        })
        .catch(error => {
          document.getElementById("locationResult").innerText = 
            "Coordinates: " + lat + ", " + lng + " | API Error: " + error.message;
        });

    },
    function(error) {
      alert("Error: " + error.message);
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
});
</script>

<!-- Include Google Maps API -->
<script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_API_KEY&libraries=places"></script>
