<?php
// Initialize variables to store form data and results
$address = $city = $state = $zip = "";
$latitude = $longitude = $formattedAddress = "";
$errors = [];
$showResults = false;

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate address field
    if (empty($_POST["address"])) {
        $errors[] = "Street address is required";
    } else {
        $address = cleanInput($_POST["address"]);
    }
    
    // Validate city field
    if (empty($_POST["city"])) {
        $errors[] = "City is required";
    } else {
        $city = cleanInput($_POST["city"]);
    }
    
    // Validate state field - must be a valid US state
    if (empty($_POST["state"])) {
        $errors[] = "State is required";
    } else {
        $state = cleanInput($_POST["state"]);
        // Check if state is valid using the isValidState function
        if (!isValidState($state)) {
            $errors[] = "Please enter a valid US state (two-letter code or full name)";
        }
    }
    
    // Validate ZIP code - must be in valid US format (5 digits or 5+4)
    if (empty($_POST["zip"])) {
        $errors[] = "ZIP code is required";
    } else {
        $zip = cleanInput($_POST["zip"]);
        // Check if ZIP code is valid using the isValidZipCode function
        if (!isValidZipCode($zip)) {
            $errors[] = "Please enter a valid US ZIP code (5 digits or 5+4 format)";
        }
    }
    
    // If no errors, proceed with geocoding
    if (empty($errors)) {
        // Format the complete address for geocoding
        $fullAddress = $address . ", " . $city . ", " . $state . " " . $zip . ", USA";
        
        // Call the geocoding function
        $geocodeResult = geocodeAddress($fullAddress);
        
        // Check if geocoding was successful
        if ($geocodeResult["status"] === "OK") {
            $latitude = $geocodeResult["latitude"];
            $longitude = $geocodeResult["longitude"];
            $formattedAddress = $geocodeResult["formattedAddress"];
            $showResults = true;
        } else {
            $errors[] = "Could not geocode address: " . $geocodeResult["message"];
        }
    }
}

/**
 * Function to clean and validate input data
 * 
 * @param string $data The input data to clean
 * @return string The cleaned data
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Function to validate if the provided string is a valid US state
 * 
 * @param string $state The state code or name to validate
 * @return bool True if valid, false otherwise
 */
function isValidState($state) {
    // Array of valid US state codes
    $stateCodes = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
        'DC', 'AS', 'GU', 'MP', 'PR', 'VI'
    ];
    
    // Array of valid US state names
    $stateNames = [
        'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado',
        'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho',
        'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana',
        'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota',
        'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada',
        'New Hampshire', 'New Jersey', 'New Mexico', 'New York',
        'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon',
        'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota',
        'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington',
        'West Virginia', 'Wisconsin', 'Wyoming', 'District of Columbia',
        'American Samoa', 'Guam', 'Northern Mariana Islands', 'Puerto Rico',
        'Virgin Islands'
    ];
    
    // Check if input is a valid state code or name (case insensitive)
    return in_array(strtoupper($state), $stateCodes) || 
           in_array(ucwords(strtolower($state)), $stateNames);
}

/**
 * Function to validate if the provided string is a valid US ZIP code
 * 
 * @param string $zip The ZIP code to validate
 * @return bool True if valid, false otherwise
 */
function isValidZipCode($zip) {
    // Pattern for 5-digit ZIP code or ZIP+4 format
    $pattern = '/^[0-9]{5}(-[0-9]{4})?$/';
    return preg_match($pattern, $zip);
}

/**
 * Function to geocode an address using Nominatim (OpenStreetMap) service
 * 
 * @param string $address The full address to geocode
 * @return array Array containing geocoding results or error message
 */
function geocodeAddress($address) {
    // Prepare the result array
    $result = [
        "status" => "ERROR",
        "latitude" => null,
        "longitude" => null,
        "formattedAddress" => "",
        "message" => ""
    ];
    
    // Encode the address for URL
    $encodedAddress = urlencode($address);
    
    // Set a user agent as required by Nominatim Usage Policy
    $userAgent = "AddressValidator/1.0 (your@email.com)"; // You should replace with your email to comply with terms
    
    // Build the Nominatim API request URL
    $url = "https://nominatim.openstreetmap.org/search?q={$encodedAddress}&format=json&addressdetails=1&limit=1&countrycodes=us";
    
    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Set the user agent as required by Nominatim Usage Policy
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    
    // Add a small delay to respect Nominatim usage policy (max 1 request per second)
    sleep(1);
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $result["message"] = "cURL Error: " . curl_error($ch);
        curl_close($ch);
        return $result;
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Decode the JSON response
    $data = json_decode($response, true);
    
    // Check if results were returned
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        // Extract the latitude and longitude
        $latitude = $data[0]['lat'];
        $longitude = $data[0]['lon'];
        
        // Build a formatted address from available data
        $formattedAddress = $data[0]['display_name'];
        
        // Update the result array with successful data
        $result["status"] = "OK";
        $result["latitude"] = $latitude;
        $result["longitude"] = $longitude;
        $result["formattedAddress"] = $formattedAddress;
    } else {
        // Set error message if no results were found
        $result["message"] = "No results found for the provided address.";
    }
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>US Address Validator & Geocoder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .result {
            margin-top: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        .map-container {
            height: 400px;
            margin-top: 20px;
        }
    </style>
    <!-- Include Leaflet CSS and JavaScript for free maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>
<body>
    <h1>US Address Validator & Geocoder</h1>
    
    <!-- Display error messages if any -->
    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Address input form -->
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="form-group">
            <label for="address">Street Address:</label>
            <input type="text" id="address" name="address" value="<?php echo $address; ?>">
        </div>
        
        <div class="form-group">
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo $city; ?>">
        </div>
        
        <div class="form-group">
            <label for="state">State (2-letter code or full name):</label>
            <input type="text" id="state" name="state" value="<?php echo $state; ?>">
        </div>
        
        <div class="form-group">
            <label for="zip">ZIP Code:</label>
            <input type="text" id="zip" name="zip" value="<?php echo $zip; ?>">
        </div>
        
        <button type="submit">Validate & Geocode Address</button>
    </form>
    
    <!-- Display geocoding results if available -->
    <?php if ($showResults): ?>
        <div class="result">
            <h2>Address Validated!</h2>
            <p><strong>Formatted Address:</strong> <?php echo $formattedAddress; ?></p>
            <p><strong>Latitude:</strong> <?php echo $latitude; ?></p>
            <p><strong>Longitude:</strong> <?php echo $longitude; ?></p>
            
            <!-- Display the location on a map using Leaflet (free alternative to Google Maps) -->
            <h3>Map Location:</h3>
            <div id="map" class="map-container"></div>
            
            <script>
                // Initialize the Leaflet Map
                document.addEventListener("DOMContentLoaded", function() {
                    // Create map centered at the geocoded coordinates
                    const latitude = <?php echo $latitude; ?>;
                    const longitude = <?php echo $longitude; ?>;
                    const map = L.map('map').setView([latitude, longitude], 14);
                    
                    // Add OpenStreetMap tile layer
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);
                    
                    // Add marker at the geocoded location
                    L.marker([latitude, longitude])
                        .addTo(map)
                        .bindPopup("<?php echo str_replace('"', '\"', $formattedAddress); ?>")
                        .openPopup();
                });
            </script>
        </div>
    <?php endif; ?>
</body>
</html>
