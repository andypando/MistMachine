<?php
// Initialize variables and handle form submissions
session_start();
$error = '';
$message = '';
$sites = [];
$selected_global = isset($_POST['global']) ? $_POST['global'] : '';
$org_id = isset($_POST['org_id']) ? $_POST['org_id'] : '';
$api_token = isset($_POST['api_token']) ? $_POST['api_token'] : '';
$upgrade_results = [];

// API base URLs for different global regions
$api_urls = [
    'Global 01' => 'https://api.mist.com',
    'Global 02' => 'https://api.gc1.mist.com',
    'Global 03' => 'https://api.ac2.mist.com',
    'Global 04' => 'https://api.gc2.mist.com'
];

// Set default values for upgrade parameters
$default_canary_phases = [1, 10, 50, 100];
$strategy = isset($_POST['strategy']) ? $_POST['strategy'] : 'serial';
$canary_phases = isset($_POST['canary_phases']) ? $_POST['canary_phases'] : implode(',', $default_canary_phases);
$max_failure_percentage = isset($_POST['max_failure_percentage']) ? $_POST['max_failure_percentage'] : 10;
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';

// Function to make API calls
function callMistAPI($url, $api_token, $method = 'GET', $data = null) {
    $curl = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $api_token,
            'Content-Type: application/json'
        ],
    ];
    
    if ($data && $method === 'POST') {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    if ($err) {
        return ['error' => "cURL Error: $err", 'status' => 0];
    }
    
    return [
        'data' => json_decode($response, true),
        'status' => $httpCode
    ];
}

// Handle form submission to get sites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_sites') {
    if (empty($selected_global) || empty($org_id) || empty($api_token)) {
        $error = 'All fields are required: Global region, Organization ID, and API Token.';
    } else {
        $base_url = $api_urls[$selected_global];
        $sites_url = "$base_url/api/v1/orgs/$org_id/sites";
        
        $result = callMistAPI($sites_url, $api_token);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } elseif ($result['status'] !== 200) {
            $error = "API Error: HTTP Status {$result['status']}";
        } else {
            $sites = $result['data'];
            $_SESSION['global'] = $selected_global;
            $_SESSION['org_id'] = $org_id;
            $_SESSION['api_token'] = $api_token;
            $_SESSION['sites'] = $sites;
        }
    }
}

// Handle form submission to perform upgrades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upgrade_sites') {
    // Retrieve session variables
    $selected_global = $_SESSION['global'] ?? '';
    $org_id = $_SESSION['org_id'] ?? '';
    $api_token = $_SESSION['api_token'] ?? '';
    $sites = $_SESSION['sites'] ?? [];
    
    // Get selected sites
    $selected_sites = isset($_POST['selected_sites']) ? $_POST['selected_sites'] : [];
    
    // Validate input
    if (empty($selected_sites)) {
        $error = 'Please select at least one site for upgrade.';
    } else {
        // Process canary phases input
        $canary_phases_array = explode(',', $canary_phases);
        $canary_phases_array = array_map('intval', $canary_phases_array);
        
        // Convert start time to epoch
        $epoch_start_time = empty($start_time) ? time() : strtotime($start_time);
        
        $base_url = $api_urls[$selected_global];
        
        // Prepare upgrade data
        $upgrade_data = [
            'strategy' => $strategy,
            'canary_phases' => $canary_phases_array,
            'max_failure_percentage' => (int)$max_failure_percentage,
            'start_time' => $epoch_start_time
        ];
        
        // Perform upgrade API calls for each selected site
        foreach ($selected_sites as $site_id) {
            $upgrade_url = "$base_url/api/v1/sites/$site_id/devices/upgrade";
            $result = callMistAPI($upgrade_url, $api_token, 'POST', $upgrade_data);
            
            // Find the site name for the result display
            $site_name = 'Unknown Site';
            foreach ($sites as $site) {
                if ($site['id'] === $site_id) {
                    $site_name = $site['name'];
                    break;
                }
            }
            
            if (isset($result['error'])) {
                $upgrade_results[] = [
                    'site_id' => $site_id,
                    'site_name' => $site_name,
                    'success' => false,
                    'message' => $result['error']
                ];
            } elseif ($result['status'] !== 200) {
                $upgrade_results[] = [
                    'site_id' => $site_id,
                    'site_name' => $site_name, 
                    'success' => false,
                    'message' => "API Error: HTTP Status {$result['status']}"
                ];
            } else {
                $upgrade_results[] = [
                    'site_id' => $site_id,
                    'site_name' => $site_name,
                    'success' => true,
                    'message' => 'Upgrade initiated successfully'
                ];
            }
        }
        
        $message = 'Upgrade commands have been sent. See results below.';
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
            margin: 0 auto;
            padding: 20px;
            max-width: 1200px;
            color: #333;
            background-color: #f5f5f5;
        }
        h1, h2 {
            color: #333;
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
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], input[type="number"], input[type="datetime-local"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
        }
        .success {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .checkbox-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 15px;
        }
        .select-all-container {
            margin-bottom: 10px;
            padding: 5px;
            background-color: #f8f8f8;
        }
        .upgrade-results {
            margin-top: 20px;
        }
        .result-success {
            color: #27ae60;
        }
        .result-error {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
    <img src="./images/mist.png" alt="Mist Logo" style="float: right; margin-left: 20px;">
        <h1>Mist Site Upgrade Tool</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($sites)): ?>
            <!-- Step 1: Get organization details and fetch sites -->
            <form method="post" action="">
                <input type="hidden" name="action" value="get_sites">
                
                <div class="form-group">
                    <label for="global">Select Global Region:</label>
                    <select id="global" name="global" required>
                        <option value="" disabled <?php echo empty($selected_global) ? 'selected' : ''; ?>>-- Select Global Region --</option>
                        <?php foreach ($api_urls as $name => $url): ?>
                            <option value="<?php echo $name; ?>" <?php echo $selected_global === $name ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="org_id">Organization ID:</label>
                    <input type="text" id="org_id" name="org_id" value="<?php echo htmlspecialchars($org_id); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="api_token">API Token:</label>
                    <input type="password" id="api_token" name="api_token" value="<?php echo htmlspecialchars($api_token); ?>" required>
                </div>
                
                <button type="submit">Get Sites</button>
            </form>
            
        <?php else: ?>
            <!-- Step 2: Display sites and upgrade options -->
            <h2>Organization Sites</h2>
            <p>Found <?php echo count($sites); ?> sites in organization. Select sites to upgrade:</p>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="upgrade_sites">
                
                <div class="select-all-container">
                    <label>
                        <input type="checkbox" id="select-all"> Select All Sites
                    </label>
                </div>
                
                <div class="checkbox-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px">Select</th>
                                <th>Site Name</th>
                                <th>Site ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_sites[]" value="<?php echo $site['id']; ?>" class="site-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($site['name']); ?></td>
                                    <td><?php echo htmlspecialchars($site['id']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <h2>Upgrade Options</h2>
                
                <div class="form-group">
                    <label for="strategy">Strategy:</label>
                    <select id="strategy" name="strategy">
                        <option value="big_bang" <?php echo $strategy === 'big_bang' ? 'selected' : ''; ?>>Big Bang</option>
                        <option value="serial" <?php echo $strategy === 'serial' ? 'selected' : ''; ?>>Serial</option>
                        <option value="canary" <?php echo $strategy === 'canary' ? 'selected' : ''; ?>>Canary</option>
                        <option value="rrm" <?php echo $strategy === 'rrm' ? 'selected' : ''; ?>>RRM</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="canary_phases">Canary Phases (comma-separated integers):</label>
                    <input type="text" id="canary_phases" name="canary_phases" value="<?php echo htmlspecialchars($canary_phases); ?>" placeholder="1,10,50,100">
                </div>
                
                <div class="form-group">
                    <label for="max_failure_percentage">Maximum Failure Percentage (0-99):</label>
                    <input type="number" id="max_failure_percentage" name="max_failure_percentage" min="0" max="99" value="<?php echo htmlspecialchars($max_failure_percentage); ?>">
                </div>
                
                <div class="form-group">
                    <label for="start_time">Start Time:</label>
                    <input type="datetime-local" id="start_time" name="start_time" value="<?php echo !empty($start_time) ? date('Y-m-d\TH:i', strtotime($start_time)) : ''; ?>">
                    <small>Leave blank to start immediately</small>
                </div>
                
                <button type="submit">Initiate Upgrades</button>
            </form>
            
            <!-- Display back button to return to the first step -->
            <div style="margin-top: 15px;">
                <form method="post" action="">
                    <button type="submit">Start Over</button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Display upgrade results if available -->
        <?php if (!empty($upgrade_results)): ?>
            <div class="upgrade-results">
                <h2>Upgrade Results</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Site Name</th>
                            <th>Site ID</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upgrade_results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['site_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['site_id']); ?></td>
                                <td class="<?php echo $result['success'] ? 'result-success' : 'result-error'; ?>">
                                    <?php echo $result['success'] ? 'Success' : 'Failed'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($result['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
  <br>
    <a href="./index.html" class="home-button">Return to Home</a>

    </div>
    
    <script>
        // JavaScript for select all functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const siteCheckboxes = document.querySelectorAll('.site-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    siteCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = isChecked;
                    });
                });
            }
            
            // Update "Select All" checkbox state based on individual checkboxes
            siteCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(siteCheckboxes).every(function(cb) {
                        return cb.checked;
                    });
                    const someChecked = Array.from(siteCheckboxes).some(function(cb) {
                        return cb.checked;
                    });
                    
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                    }
                });
            });
        });
    </script>
</body>
</html>
