<?php
// Initialize variables
$error = "";
$success = "";
$sites = [];
$org_id = "";
$api_key = "";
$global_instance = "";
$selected_sites = [];
$upgrade_payload = null;
$day_of_week = "";
$hour_of_day = "";

// AP models array for firmware versions
$ap_models = [
    'AP12' => '',
    'AP24' => '',
    'AP32/E' => '',
    'AP33' => '',
    'AP34' => '',
    'AP41/E' => '',
    'AP43/E' => '',
    'AP45/E' => '',
    'AP61/E' => '',
    'AP63/E' => '',
    'AP64' => ''
];

// Process form submission for API site retrieval
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'retrieve_sites') {
    // Get form data
    $global_instance = isset($_POST["global_instance"]) ? $_POST["global_instance"] : "";
    $org_id = isset($_POST["org_id"]) ? trim($_POST["org_id"]) : "";
    $api_key = isset($_POST["api_key"]) ? trim($_POST["api_key"]) : "";
    
    // Validate inputs
    if (empty($global_instance)) {
        $error = "Please select a Global instance.";
    } elseif (empty($org_id)) {
        $error = "Organization ID is required.";
    } elseif (empty($api_key)) {
        $error = "API Key is required.";
    } else {
        // Determine API URL based on selected Global instance
        $base_url = "";
        switch ($global_instance) {
            case "global01":
                $base_url = "https://api.mist.com";
                break;
            case "global02":
                $base_url = "https://api.gc1.mist.com";
                break;
            case "global03":
                $base_url = "https://api.ac2.mist.com";
                break;
            case "global04":
                $base_url = "https://api.gc2.mist.com";
                break;
            default:
                $error = "Invalid Global instance selected.";
                break;
        }
        
        if (!empty($base_url)) {
            // Build the API URL
            $api_url = "{$base_url}/api/v1/orgs/{$org_id}/sites";
            
            // Set up cURL request
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Token {$api_key}",
                "Content-Type: application/json"
            ]);
            
            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Check for errors
            if (curl_errno($ch)) {
                $error = "cURL Error: " . curl_error($ch);
            } elseif ($http_code != 200) {
                $error = "API Error: HTTP Status {$http_code}";
                if ($response) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['detail'])) {
                        $error .= " - " . $response_data['detail'];
                    }
                }
            } else {
                // Process successful response
                $sites = json_decode($response, true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    $error = "Error parsing API response: " . json_last_error_msg();
                    $sites = [];
                }
            }
            
            curl_close($ch);
        }
    }
}

// Process form submission for firmware upgrade
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'upgrade_firmware') {
    // Get form data
    $global_instance = isset($_POST["global_instance"]) ? $_POST["global_instance"] : "";
    $org_id = isset($_POST["org_id"]) ? trim($_POST["org_id"]) : "";
    $api_key = isset($_POST["api_key"]) ? trim($_POST["api_key"]) : "";
    $selected_sites = isset($_POST["selected_sites"]) ? $_POST["selected_sites"] : [];
    $day_of_week = isset($_POST["day"]) ? $_POST["day"] : "";
    $hour_of_day = isset($_POST["hour"]) ? $_POST["hour"] : "";
    
    // Get firmware versions for each AP model
    $firmware_versions = [];
    foreach ($ap_models as $model => $default_version) {
        $model_key = str_replace('/', '_', $model);
        $firmware_versions[$model] = isset($_POST["firmware_" . $model_key]) ? trim($_POST["firmware_" . $model_key]) : '';
    }
    
    // Validate inputs
    if (empty($global_instance) || empty($org_id) || empty($api_key)) {
        $error = "Missing required fields. Please go back and ensure all fields are filled.";
    } elseif (empty($selected_sites)) {
        $error = "Please select at least one site to upgrade.";
    } else {
        // Build the custom versions object
        $custom_versions = [
            "AP12" => $firmware_versions['AP12'],
            "AP24" => $firmware_versions['AP24'],
            "AP32" => $firmware_versions['AP32/E'],
            "AP32E" => $firmware_versions['AP32/E'],
            "AP33" => $firmware_versions['AP33'],
            "AP34" => $firmware_versions['AP34'],
            "AP41" => $firmware_versions['AP41/E'],
            "AP41E" => $firmware_versions['AP41/E'],
            "AP43" => $firmware_versions['AP43/E'],
            "AP43E" => $firmware_versions['AP43/E'],
            "AP45" => $firmware_versions['AP45/E'],
            "AP45E" => $firmware_versions['AP45/E'],
            "AP61" => $firmware_versions['AP61/E'],
            "AP61E" => $firmware_versions['AP61/E'],
            "AP63" => $firmware_versions['AP63/E'],
            "AP63E" => $firmware_versions['AP63/E'],
            "AP64" => $firmware_versions['AP64']
        ];
        
        // Build the payload
        $payload = [
            "auto_upgrade" => [
                "enabled" => true,
                "version" => "custom",
                "time_of_day" => $hour_of_day . ":00",
                "custom_versions" => $custom_versions,
                "day_of_week" => $day_of_week
            ]
        ];
        
        // Store the payload for display
        $upgrade_payload = json_encode($payload, JSON_PRETTY_PRINT);
        
        // Determine API URL based on selected Global instance
        $base_url = "";
        switch ($global_instance) {
            case "global01":
                $base_url = "https://api.mist.com";
                break;
            case "global02":
                $base_url = "https://api.gc1.mist.com";
                break;
            case "global03":
                $base_url = "https://api.ac2.mist.com";
                break;
            default:
                $error = "Invalid Global instance selected.";
                break;
        }
        
        if (!empty($base_url)) {
            // Keep track of successful upgrades
            $successful_sites = [];
            $failed_sites = [];
            
            foreach ($selected_sites as $site_id) {
                // Build the API URL for each site
                $api_url = "{$base_url}/api/v1/sites/{$site_id}/setting";
                
                // Set up cURL request
                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Token {$api_key}",
                    "Content-Type: application/json"
                ]);
                
                // Execute the request
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                // Check result
                if (curl_errno($ch) || $http_code != 200) {
                    $site_error = "Failed for site ID {$site_id}";
                    if (curl_errno($ch)) {
                        $site_error .= ": " . curl_error($ch);
                    } elseif ($response) {
                        $response_data = json_decode($response, true);
                        if (isset($response_data['detail'])) {
                            $site_error .= ": " . $response_data['detail'];
                        } else {
                            $site_error .= ": HTTP Status {$http_code}";
                        }
                    }
                    $failed_sites[$site_id] = $site_error;
                } else {
                    $successful_sites[] = $site_id;
                }
                
                curl_close($ch);
            }
            
            // Report results
            if (!empty($successful_sites)) {
                $success = "Successfully updated firmware settings for " . count($successful_sites) . " site(s)";
            }
            
            if (!empty($failed_sites)) {
                $error = "Failed to update " . count($failed_sites) . " site(s):<br>";
                foreach ($failed_sites as $site_id => $site_error) {
                    $error .= "- " . htmlspecialchars($site_error) . "<br>";
                }
            }
            
            // Reload sites to show updated state
            $api_url = "{$base_url}/api/v1/orgs/{$org_id}/sites";
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Token {$api_key}",
                "Content-Type: application/json"
            ]);
            $response = curl_exec($ch);
            if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
                $sites = json_decode($response, true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    $sites = [];
                }
            }
            curl_close($ch);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mist Machine</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
       .home-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .home-button:hover {
            background-color: #45a049;
        }
        h1, h2 {
            color: #333;
            margin-top: 0;
        }
        form {
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], select {
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
            margin-right: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: #f44336;
            margin-bottom: 15px;
        }
        .success {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .site-count {
            margin-top: 20px;
            font-weight: bold;
        }
        .api-url {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
        }
        .firmware-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .json-display {
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            margin-top: 20px;
            overflow-x: auto;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
        }
        .checkbox-container input[type="checkbox"] {
            margin-right: 8px;
        }
        .select-all-container {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="./images/mist.png" alt="Mist Logo" style="float: right; margin-left: 20px;">
        <h1>Advanced Auto-Upgrade Config Tool</h1>
        
        <!-- Site Retrieval Form -->
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="action" value="retrieve_sites">
            
            <div class="form-group">
                <label for="global_instance">Select Global Instance:</label>
                <select id="global_instance" name="global_instance" required>
                    <option value="" <?php echo empty($global_instance) ? 'selected' : ''; ?>>-- Select Global Instance --</option>
                    <option value="global01" <?php echo $global_instance === 'global01' ? 'selected' : ''; ?>>Global 01</option>
                    <option value="global02" <?php echo $global_instance === 'global02' ? 'selected' : ''; ?>>Global 02</option>
                    <option value="global03" <?php echo $global_instance === 'global03' ? 'selected' : ''; ?>>Global 03</option>
                    <option value="global03" <?php echo $global_instance === 'global04' ? 'selected' : ''; ?>>Global 04</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="org_id">Organization ID:</label>
                <input type="text" id="org_id" name="org_id" value="<?php echo htmlspecialchars($org_id); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="api_key">API Key:</label>
                <input type="password" id="api_key" name="api_key" value="<?php echo htmlspecialchars($api_key); ?>" required>
            </div>
            
            <button type="submit">Retrieve Sites</button>
        </form>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error) && !empty($sites)): ?>
            <div class="api-url">
                API URL: <?php 
                    $base_url = ($global_instance === 'global01') ? 'https://api.mist.com' : 
                              (($global_instance === 'global02') ? 'https://api.gc1.mist.com' : 'https://api.ac2.mist.com');
                    echo htmlspecialchars("{$base_url}/api/v1/orgs/{$org_id}/sites"); 
                ?>
            </div>
            
            <div class="site-count">
                Found <?php echo count($sites); ?> site(s)
            </div>
            
            <!-- Firmware Upgrade Form -->
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="action" value="upgrade_firmware">
                <input type="hidden" name="global_instance" value="<?php echo htmlspecialchars($global_instance); ?>">
                <input type="hidden" name="org_id" value="<?php echo htmlspecialchars($org_id); ?>">
                <input type="hidden" name="api_key" value="<?php echo htmlspecialchars($api_key); ?>">
                
                <h2>Select Sites to Upgrade</h2>
                
                <div class="select-all-container">
                    <div class="checkbox-container">
                        <input type="checkbox" id="select-all" onclick="toggleAllSites()">
                        <label for="select-all">Select All Sites</label>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Address</th>
                            <th>Country Code</th>
                            <th>Timezone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_sites[]" value="<?php echo htmlspecialchars($site['id']); ?>" 
                                        <?php echo isset($_POST['selected_sites']) && in_array($site['id'], $_POST['selected_sites']) ? 'checked' : ''; ?> 
                                        class="site-checkbox">
                                </td>
                                <td><?php echo htmlspecialchars($site['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($site['id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($site['address'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($site['country_code'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($site['timezone'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h2>Firmware Versions</h2>
                <p>Enter the firmware version for each AP model:</p>
                
                <div class="firmware-inputs">
                    <?php foreach ($ap_models as $model => $default_version): ?>
                        <?php $model_key = str_replace('/', '_', $model); ?>
                        <div class="form-group">
                            <label for="firmware_<?php echo $model_key; ?>"><?php echo htmlspecialchars($model); ?>:</label>
                            <input type="text" id="firmware_<?php echo $model_key; ?>" name="firmware_<?php echo $model_key; ?>" 
                                value="<?php echo isset($_POST['firmware_' . $model_key]) ? htmlspecialchars($_POST['firmware_' . $model_key]) : ''; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
               
            <label for="day">Select a day of the week:</label>
            <select name="day" id="day">
                <option value="mon">Monday</option>
                <option value="tue">Tuesday</option>
                <option value="wed">Wednesday</option>
                <option value="thu">Thursday</option>
                <option value="fri">Friday</option>
                <option value="sat">Saturday</option>
                <option value="sun">Sunday</option>
            </select>
                
 <label for="hour">Select hour of day:</label>
        <select name="hour" id="hour">
            <?php
            // Generate options for all 24 hours
            for ($i = 0; $i < 24; $i++) {
                $formatted_i = sprintf("%02d", $i);
                $selected = ("02" == $formatted_i) ? "selected" : "";
                // Format hour display
                $display_hour = $formatted_i . ":00";
                echo "<option value=\"$formatted_i\" $selected>$display_hour</option>";
            }
            ?>
        </select>
               
                <div style="margin-top: 20px;">
                    <button type="submit">Upgrade Selected Sites</button>
                </div>
            </form>
            
            <?php if (!empty($upgrade_payload)): ?>
                <h2>Generated Payload</h2>
                <div class="json-display"><?php echo htmlspecialchars($upgrade_payload); ?></div>
            <?php endif; ?>
            
        <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)): ?>
            <div class="site-count">No sites found.</div>
        <?php endif; ?>

    <a href="./index.html" class="home-button">Return to Home</a>

    </div>
    
    <script>
        function toggleAllSites() {
            var selectAll = document.getElementById('select-all');
            var checkboxes = document.getElementsByClassName('site-checkbox');
            
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = selectAll.checked;
            }
        }
    </script>
</body>
</html>
