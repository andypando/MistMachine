<?php
/**
 * Mist Site Management Application
 * 
 * This script allows users to select a global region, authenticate with their
 * Org_ID and API_Token, retrieve sites from the organization, and perform
 * operations on selected sites.
 */

// Start session to maintain state between pages
session_start();

// Determine which step of the process we're on
$step = isset($_POST['step']) ? $_POST['step'] : 1;

// Initialize variables
$error = '';
$sites = [];
$baseUrl = '';
$deleteResults = [];

// Process form submissions based on the current step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Step 1: User provided Global Region, Org_ID and API_Token
    if ($step == 1 && isset($_POST['global_region']) && isset($_POST['org_id']) && isset($_POST['api_token'])) {
        $_SESSION['global_region'] = $_POST['global_region'];
        $_SESSION['org_id'] = $_POST['org_id'];
        $_SESSION['api_token'] = $_POST['api_token'];
        
        // Determine the base URL based on the selected global region
        switch ($_SESSION['global_region']) {
            case 'global01':
                $baseUrl = 'https://api.mist.com';
                break;
            case 'global02':
                $baseUrl = 'https://api.gc1.mist.com';
                break;
            case 'global03':
                $baseUrl = 'https://api.ac2.mist.com';
                break;
            case 'global04':
                $baseUrl = 'https://api.gc2.mist.com';
                break;
        }
        $_SESSION['base_url'] = $baseUrl;
        
        // Make API call to get sites
        $sites = getSites($baseUrl, $_SESSION['org_id'], $_SESSION['api_token']);
        
        if (isset($sites['error'])) {
            $error = $sites['error'];
            $step = 1; // Stay on step 1 if there's an error
        } else {
            $_SESSION['sites'] = $sites;
            $step = 2;
        }
    }
    
    // Step 2: User selected sites to upgrade
    else if ($step == 2 && isset($_POST['selected_sites'])) {
        $_SESSION['selected_sites'] = $_POST['selected_sites'];
        $step = 3; // Go to confirmation step
    }
    
    // Step 3: User confirmed the deletion
    else if ($step == 3 && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        $selectedSites = $_SESSION['selected_sites'];
        $baseUrl = $_SESSION['base_url'];
        $apiToken = $_SESSION['api_token'];
        
        // Process each selected site
        foreach ($selectedSites as $siteId) {
            $result = deleteSite($baseUrl, $siteId, $apiToken);
            $deleteResults[$siteId] = $result;
        }
        
        $step = 4; // Final results step
    }
    // User canceled the deletion
    else if ($step == 3 && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'no') {
        $step = 2; // Go back to site selection
    }
}

/**
 * Function to retrieve sites from the Mist API
 * 
 * @param string $baseUrl The base URL for the API endpoint
 * @param string $orgId The organization ID
 * @param string $apiToken The API token for authentication
 * @return array Array of sites or error information
 */
function getSites($baseUrl, $orgId, $apiToken) {
    // Create the API endpoint URL
    $url = "$baseUrl/api/v1/orgs/$orgId/sites";
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $apiToken,
        'Content-Type: application/json'
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['error' => 'API connection error: ' . curl_error($ch)];
    }
    
    curl_close($ch);
    
    // Process the response
    if ($httpCode == 200) {
        return json_decode($response, true);
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = isset($errorData['detail']) ? $errorData['detail'] : 'HTTP Error ' . $httpCode;
        return ['error' => 'API error: ' . $errorMsg];
    }
}

/**
 * Function to delete a site via the Mist API
 * 
 * @param string $baseUrl The base URL for the API endpoint
 * @param string $siteId The site ID to delete
 * @param string $apiToken The API token for authentication
 * @return array Result information
 */
function deleteSite($baseUrl, $siteId, $apiToken) {
    // Create the API endpoint URL
    $url = "$baseUrl/api/v1/sites/$siteId";
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $apiToken,
        'Content-Type: application/json'
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['success' => false, 'message' => 'Connection error: ' . curl_error($ch)];
    }
    
    curl_close($ch);
    
    // Process the response
    if ($httpCode == 204 || $httpCode == 200) {
        return ['success' => true, 'message' => 'Site successfully deleted'];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = isset($errorData['detail']) ? $errorData['detail'] : 'HTTP Error ' . $httpCode;
        return ['success' => false, 'message' => 'API error: ' . $errorMsg];
    }
}

/**
 * Function to get site name by ID from the session sites array
 * 
 * @param string $siteId The site ID to look up
 * @return string The site name or "Unknown Site" if not found
 */
function getSiteNameById($siteId) {
    if (!isset($_SESSION['sites']) || empty($_SESSION['sites'])) {
        return "Unknown Site";
    }
    
    foreach ($_SESSION['sites'] as $site) {
        if ($site['id'] === $siteId) {
            return $site['name'];
        }
    }
    
    return "Unknown Site";
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
            max-width: 900px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #333;
        }
        form {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="checkbox"]
        {
            vertical-align:middle;
            display: inline-block;
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
        button.cancel {
            background-color: #f44336;
        }
        button.cancel:hover {
            background-color: #d32f2f;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .site-list {
            margin-top: 20px;
        }
        .site-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        .site-item:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .site-item label
        {
            position: relative;
            top: -2px;
        }
        .select-all {
            margin-bottom: 10px;
        }
        .result-success {
            color: green;
        }
        .result-error {
            color: red;
        }
        .confirmation-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warning {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mist Org. Management</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <!-- Step 1: Combined Global Region Selection and Authentication -->
            <h2>Enter Connection Details</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label for="global_region">Select Global Region:</label>
                    <select id="global_region" name="global_region" required>
                        <option value="">-- Select Region --</option>
                        <option value="global01">Global 01</option>
                        <option value="global02">Global 02</option>
                        <option value="global03">Global 03</option>
                        <option value="global04">Global 04</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="org_id">Organization ID:</label>
                    <input type="text" id="org_id" name="org_id" required>
                </div>
                <div class="form-group">
                    <label for="api_token">API Token:</label>
                    <input type="password" id="api_token" name="api_token" required>
                </div>
                <input type="hidden" name="step" value="1">
                <button type="submit">Retrieve Sites</button>
            </form>
            
        <?php elseif ($step == 2): ?>
            <!-- Step 2: Select Sites to Delete -->
            <h2>Select Sites to Upgrade</h2>
            <form method="post" action="">
                <div class="select-all">
                    <input type="checkbox" id="select_all" onchange="toggleSelectAll(this)">
                    <label for="select_all">Select All Sites</label>
                </div>
                
                <div class="site-list">
                    <?php if (empty($_SESSION['sites'])): ?>
                        <p>No sites found.</p>
                    <?php else: ?>
                        <?php foreach ($_SESSION['sites'] as $site): ?>
                            <div class="site-item">
                                <input type="checkbox" name="selected_sites[]" value="<?php echo htmlspecialchars($site['id']); ?>" id="site_<?php echo htmlspecialchars($site['id']); ?>" class="site-checkbox">
                                <label for="site_<?php echo htmlspecialchars($site['id']); ?>">
                                    <?php echo htmlspecialchars($site['name']); ?>
                                    (ID: <?php echo htmlspecialchars($site['id']); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" name="step" value="2">
                <button type="submit">Delete Selected Sites</button>
            </form>
            
        <?php elseif ($step == 3): ?>
            <!-- Step 3: Confirmation before deletion -->
            <h2>Confirm Site Deletion</h2>
            
            <div class="confirmation-box warning">
                <p><strong>WARNING:</strong> You are about to permanently delete the following sites. This action cannot be undone.</p>
            </div>
            
            <div class="site-list">
                <h3>Sites to be deleted:</h3>
                <?php if (empty($_SESSION['selected_sites'])): ?>
                    <p>No sites were selected.</p>
                <?php else: ?>
                    <?php foreach ($_SESSION['selected_sites'] as $siteId): ?>
                        <div class="site-item">
                            <strong><?php echo htmlspecialchars(getSiteNameById($siteId)); ?></strong>
                            <div>ID: <?php echo htmlspecialchars($siteId); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form method="post" action="">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="confirm_delete" value="yes">
                <button type="submit">Confirm Deletion</button>
                <button type="submit" name="confirm_delete" value="no" class="cancel">Cancel</button>
            </form>
            
        <?php elseif ($step == 4): ?>
            <!-- Step 4: Show Results -->
            <h2>Upgrade Results</h2>
            
            <?php if (empty($deleteResults)): ?>
                <p>No sites were processed.</p>
            <?php else: ?>
                <div class="site-list">
                    <?php foreach ($deleteResults as $siteId => $result): ?>
                        <div class="site-item">
                            <strong><?php echo htmlspecialchars(getSiteNameById($siteId)); ?></strong>
                            <div>ID: <?php echo htmlspecialchars($siteId); ?></div>
                            <?php if ($result['success']): ?>
                                <span class="result-success">✓ <?php echo htmlspecialchars($result['message']); ?></span>
                            <?php else: ?>
                                <span class="result-error">✗ <?php echo htmlspecialchars($result['message']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">Start Over</a></p>
        <?php endif; ?>
    </div>
    
    <script>
        /**
         * Toggle select all checkboxes
         * This function selects or deselects all site checkboxes based on the "Select All" checkbox
         */
        function toggleSelectAll(source) {
            var checkboxes = document.getElementsByClassName('site-checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</body>
</html>
