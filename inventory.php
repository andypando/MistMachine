<?php
// Initialize variables
$error = '';
$success = '';
$inventory = null;
$sites = null;
$csvFiles = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form inputs
    $globalOption = $_POST['global_option'] ?? '';
    $orgId = $_POST['org_id'] ?? '';
    $apiToken = $_POST['api_token'] ?? '';
    
    // Validate inputs
    if (empty($globalOption) || empty($orgId) || empty($apiToken)) {
        $error = "All fields are required.";
    } else {
        // Determine API endpoints based on global option
        $inventoryEndpoint = '';
        $sitesEndpoint = '';
        switch ($globalOption) {
            case 'global_01':
                $inventoryEndpoint = "https://api.mist.com/api/v1/orgs/{$orgId}/inventory";
                $sitesEndpoint = "https://api.mist.com/api/v1/orgs/{$orgId}/sites";
                break;
            case 'global_02':
                $inventoryEndpoint = "https://api.gc1.mist.com/api/v1/orgs/{$orgId}/inventory";
                $sitesEndpoint = "https://api.gc1.mist.com/api/v1/orgs/{$orgId}/sites";
                break;
            case 'global_03':
                $inventoryEndpoint = "https://api.ac2.mist.com/api/v1/orgs/{$orgId}/inventory";
                $sitesEndpoint = "https://api.ac2.mist.com/api/v1/orgs/{$orgId}/sites";
                break;
            case 'global_04':
                $inventoryEndpoint = "https://api.gc2.mist.com/api/v1/orgs/{$orgId}/inventory";
                $sitesEndpoint = "https://api.gc2.mist.com/api/v1/orgs/{$orgId}/sites";
                break;
            default:
                $error = "Invalid global option selected.";
                break;
        }
        
        if (!empty($inventoryEndpoint) && !empty($sitesEndpoint)) {
            // 1. First, fetch sites data
            $siteData = makeApiRequest($sitesEndpoint, $apiToken);
            
            if ($siteData['success']) {
                $sites = $siteData['data'];
                
                // Create site lookup map for faster access
                $siteLookup = [];
                foreach ($sites as $site) {
                    if (isset($site['id']) && isset($site['name'])) {
                        $siteLookup[$site['id']] = $site['name'];
                    }
                }
                
                // 2. Then, fetch inventory data
                $inventoryData = makeApiRequest($inventoryEndpoint, $apiToken);
                
                if ($inventoryData['success']) {
                    $inventory = $inventoryData['data'];
                    
                    if (is_array($inventory) && !empty($inventory)) {
                        // Group items by type
                        $groupedItems = [];
                        foreach ($inventory as $item) {

                            //Special Processing for Switches
                            if ($item['type'] === 'switch') {
                                // Remove the vc_mac field if it exists
                                if (isset($item['vc_mac'])) {
                                    unset($item['vc_mac']);
                                }
                                // Handle Missing fields for unassigned
                                if (!isset($item['hostname'])) {
                                    $item['hostname'] = '';
                                }
                                if (!isset($item['chassis_mac'])) {
                                    $item['chassis_mac'] = '';
                                }
                                if (!isset($item['chassis_serial'])) {
                                    $item['chassis_serial'] = '';
                                }
                                if (!isset($item['name'])) {
                                    $item['name'] = '';
                                }
                                if (!isset($item['deviceprofile_id'])) {
                                    $item['deviceprofile_id'] = '';
                                }
                                if (!isset($item['connected'])) {
                                    $item['connected'] = '0';
                                }
                            }
                            
                            // Special Processing for APs
                            if ($item['type'] === 'ap') {
                                // Remove jsi field
                                if (isset($item['jsi'])) {
                                    unset($item['jsi']);
                                }
                                // Handle Missing fields for unassigned
                                if (!isset($item['name'])) {
                                    $item['name'] = '';
                                }
                                if (!isset($item['deviceprofile_id'])) {
                                    $item['deviceprofile_id'] = '';
                                }
                                if (!isset($item['connected'])) {
                                    $item['connected'] = '0';
                                }
                            }
                            
                            $type = $item['type'] ?? 'unknown';
                            if (!isset($groupedItems[$type])) {
                                $groupedItems[$type] = [];
                            }
                              
                            // Add site name to item if site_id exists and matches
                            if (isset($item['site_id']) && isset($siteLookup[$item['site_id']])) {
                                $item['site_name'] = $siteLookup[$item['site_id']];
                            } else {
                                $item['site_name'] = 'Unassigned'; // Empty if no match
                            }
                            
                            $groupedItems[$type][] = $item;
                        }
                        
                        // Create CSV files for each type
                        $csvDir = 'csv_exports';
                        if (!is_dir($csvDir)) {
                            mkdir($csvDir, 0755, true);
                        }
                        
                        foreach ($groupedItems as $type => $items) {
                            if (!empty($items)) {
                                $filename = $csvDir . '/' . sanitizeFilename($type) . '_' . time() . '.csv';
                                $csvFile = fopen($filename, 'w');
                                
                                // Write headers
                                $headers = array_keys($items[0]);
                                fputcsv($csvFile, $headers);
                                
                                // Write data rows
                                foreach ($items as $item) {
                                    // Convert nested arrays/objects to JSON strings
                                    foreach ($item as $key => $value) {
                                        if (is_array($value) || is_object($value)) {
                                            $item[$key] = json_encode($value);
                                        }
                                    }
                                    fputcsv($csvFile, $item);
                                }
                                
                                fclose($csvFile);
                                $csvFiles[] = [
                                    'type' => $type,
                                    'filename' => basename($filename),
                                    'path' => $filename,
                                    'count' => count($items)
                                ];
                            }
                        }
                        
                        $success = "Successfully retrieved inventory and site data. Created CSV files with site names.";
                    } else {
                        $error = "No inventory items found or invalid response format.";
                    }
                } else {
                    $error = "Inventory API request failed: " . $inventoryData['error'];
                }
            } else {
                $error = "Sites API request failed: " . $siteData['error'];
            }
        }
    }
}

// Helper function to make API requests
function makeApiRequest($endpoint, $apiToken) {
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token {$apiToken}",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = "CURL Error: " . curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $error];
    }
    
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return ['success' => true, 'data' => $data];
    } else {
        return ['success' => false, 'error' => "HTTP Error: {$httpCode}"];
    }
}

// Helper function to sanitize filenames
function sanitizeFilename($filename) {
    // Replace non-alphanumeric characters with underscores
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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
        
        h1 {
            color: #333;
            text-align: center;
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        .error {
            color: #f44336;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 4px;
        }
        .success {
            color: #4CAF50;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .download-link {
            color: #2196F3;
            text-decoration: none;
        }
        .download-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
    <img src="./images/mist.png" alt="Mist Logo" style="float: right; margin-left: 20px;">
        <h1>Mist Inventory Tool</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div>
                <label for="global_option">Select Global Region:</label>
                <select id="global_option" name="global_option" required>
                    <option value="" disabled <?php echo empty($_POST['global_option']) ? 'selected' : ''; ?>>-- Select Global Region --</option>
                    <option value="global_01" <?php echo (isset($_POST['global_option']) && $_POST['global_option'] === 'global_01') ? 'selected' : ''; ?>>Global 01</option>
                    <option value="global_02" <?php echo (isset($_POST['global_option']) && $_POST['global_option'] === 'global_02') ? 'selected' : ''; ?>>Global 02</option>
                    <option value="global_03" <?php echo (isset($_POST['global_option']) && $_POST['global_option'] === 'global_03') ? 'selected' : ''; ?>>Global 03</option>
                    <option value="global_04" <?php echo (isset($_POST['global_option']) && $_POST['global_option'] === 'global_04') ? 'selected' : ''; ?>>Global 04</option>
                </select>
            </div>
            
            <div>
                <label for="org_id">Organization ID:</label>
                <input type="text" id="org_id" name="org_id" value="<?php echo htmlspecialchars($_POST['org_id'] ?? ''); ?>" required>
            </div>
            
            <div>
                <label for="api_token">API Token:</label>
                <input type="password" id="api_token" name="api_token" value="<?php echo htmlspecialchars($_POST['api_token'] ?? ''); ?>" required>
            </div>
            
            <div>
                <input type="submit" value="Fetch Inventory">
            </div>
        </form>
        
        <?php if (!empty($csvFiles)): ?>
            <h2>Generated CSV Files</h2>
            <table>
                <thead>
                    <tr>
                        <th>Device Type</th>
                        <th>Item Count</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($csvFiles as $file): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file['type']); ?></td>
                            <td><?php echo $file['count']; ?></td>
                            <td><a href="<?php echo htmlspecialchars($file['path']); ?>" class="download-link" download>Download CSV</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <br>
    <a href="./index.html" class="home-button">Return to Home</a>
    </div>
</body>
</html>
