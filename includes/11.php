<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Geolocation Test</title>
  <!-- Load jQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <!-- Load Google Maps API -->
  <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBQ_S-MNLPXfeguaEQ1dOpww8vAo9bXJIw&libraries=places"></script>
</head>
<body>
  <h1>Geolocation Test</h1>
  <p>Click the button below to detect your location and display your city:</p>
  <input type="text" id="city" placeholder="City will appear here" style="width: 300px; padding: 8px;">
  <button id="detectLocation" style="padding: 8px 16px;">Detect My Location</button>

  <script>
    $(document).ready(function() {
      $("#detectLocation").on("click", function() {
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(successCallback, errorCallback, {
            enableHighAccuracy: true,  // Get a more precise location
            timeout: 10000,
            maximumAge: 0
          });
        } else {
          alert("Geolocation is not supported by this browser.");
        }
      });

      function successCallback(position) {
        console.log("Position obtained:", position);
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        var geocoder = new google.maps.Geocoder();
        var latlng = { lat: lat, lng: lng };

        geocoder.geocode({ 'location': latlng }, function(results, status) {
          console.log("Geocoder status:", status, results);
          if (status === 'OK' && results.length > 0) {
            let city = "";

            results.forEach(result => {
              result.address_components.forEach(component => {
                if (component.types.includes("locality")) {
                  city = component.long_name; // Prioritize locality (actual city)
                }
              });
            });

            // Fallback to postal_town if locality is missing
            if (!city) {
              results.forEach(result => {
                result.address_components.forEach(component => {
                  if (component.types.includes("postal_town")) {
                    city = component.long_name;
                  }
                });
              });
            }

            // Fallback to administrative_area_level_2 (county) if city is still missing
            if (!city) {
              results.forEach(result => {
                result.address_components.forEach(component => {
                  if (component.types.includes("administrative_area_level_2")) {
                    city = component.long_name;
                  }
                });
              });
            }

            if (city) {
              $("#city").val(city);
              console.log("City found:", city);
            } else {
              alert("City not found.");
            }
          } else {
            alert("No results found.");
          }
        });
      }

      function errorCallback(error) {
        console.log("Geolocation error:", error);
        alert("Error getting location: " + error.message);
      }
    });
  </script>
</body>
</html>
