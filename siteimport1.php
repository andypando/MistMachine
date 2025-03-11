
<?php
// Initialize variables
$error = '';
$success = '';
$csvData = [];
$columns = [];
$columnMapping = [];
$orgID = '';
$apiToken = '';

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if we're in the first step (file upload) or second step (processing)
    if (isset($_POST['submit_file']) && isset($_FILES['csvFile'])) {
        // First step - Handle file upload
        if ($_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['csvFile']['tmp_name'];
            $handle = fopen($tmpName, 'r');
            
            // Get headers (first row)
            $columns = fgetcsv($handle);
            
            // Read all data
            while (($data = fgetcsv($handle)) !== FALSE) {
                $csvData[] = $data;
            }
            
            fclose($handle);
            
            // Save data in session for next step
            session_start();
            $_SESSION['csvData'] = $csvData;
            $_SESSION['columns'] = $columns;
        } else {
            $error = 'Error uploading file. Code: ' . $_FILES['csvFile']['error'];
        }
    } elseif (isset($_POST['process_data'])) {
        // Second step - Process data and make API calls
        session_start();
        $csvData = $_SESSION['csvData'] ?? [];
        $columns = $_SESSION['columns'] ?? [];
        
        // Get column mappings from form
        $siteNameCol = $_POST['site_name_col'] ?? '';
        $siteAddrCol = $_POST['site_addr_col'] ?? '';
        $siteIDCol = $_POST['site_id_col'] ?? '';
        $siteCityCol = $_POST['site_addr_col'] ?? '';
        $siteStateCol = $_POST['site_addr_col'] ?? '';
        $siteZipCol = $_POST['site_addr_col'] ?? '';
        $orgID = $_POST['org_id'] ?? '';
        $apiToken = $_POST['api_token'] ?? '';
        $rfID = $_POST['rf_id'] ?? '';
        $cloud = $_POST['cloud'] ?? '';
        
        if (!empty($csvData) && !empty($orgID)) {
            $colIndexes = [
                'name' => array_search($siteNameCol, $columns),
                'address' => array_search($siteAddrCol, $columns),
                'id' => array_search($siteIDCol, $columns),
                'city' => array_search($siteCityCol, $columns),
                'state' => array_search($siteStateCol, $columns),
                'zip' => array_search($siteZipCol, $columns)
            ];
            
            $apiResults = [];
            $successCount = 0;
            $failCount = 0;
            
            // Process each row and make API calls
            foreach ($csvData as $row) {
                $postData = [
                    'site_name' => $row[$colIndexes['name']],
                    'site_address' => $row[$colIndexes['address']],
                    'site_id' => $row[$colIndexes['id']],
                    'site_city' => $row[$colIndexes['city']],
                    'site_state' => $row[$colIndexes['state']],
                    'site_zip' => $row[$colIndexes['zip']]
                ];
                
                // Make API call
                $result = makeApiCall($orgID, $apiToken, $postData, $cloud, $rfID);
                $apiResults[] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            
            $success = "Processed $successCount entries successfully. $failCount entries failed.";
            
            // Clear session data
            session_unset();
            
            // Store results for display
            $_SESSION['apiResults'] = $apiResults;
        } else {
            $error = 'Missing data or parameters';
        }
    }
}

/**
 * Function to make API calls to Mist API
 */
function makeApiCall($orgID, $apiToken, $postData, $cloud, $rfID) {
    $url = 'https://api.' . $cloud . 'mist.com/api/v1/orgs/' . $orgID .'/sites';
    
    // Prepare JSON payload with required fixed value
    $jsonPayload = '{"name":"' . $postData['site_name'] . '","country_code":"US","timezone":"America/New_York","address":"' .
                    $postData['site_address'] . ', USA","rftemplate_id":"' . $rfID  . '","notes":"' . $postData['site_id'] . '"}';
    file_put_contents('/var/www/log/error.txt', $url . "\n" . $rfID . "\n\n", FILE_APPEND);
    
    // Initialize cURL
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Token ' . $apiToken,
        'Content-Length: ' . strlen($jsonPayload)
    ]);

    
    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    $success = $httpCode >= 200 && $httpCode < 300;
    
    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'data' => $postData
    ];
}

// Load session if it exists
session_start();
$columns = $_SESSION['columns'] ?? [];
$csvData = $_SESSION['csvData'] ?? [];
$apiResults = $_SESSION['apiResults'] ?? [];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mist Machine</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
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
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            margin-bottom: 15px;
            padding: 8px;
            width: 100%;
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
            color: #f44336;
            font-weight: bold;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .preview {
            margin-top: 20px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
  <div class="container">
    <img src="./images/mist.png" alt="Mist Logo" style="float: right; margin-left: 20px;">
    <h1>Mist Site Builder<br><br></h1>
    
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>
    
    <?php if (empty($columns) && empty($success)): ?>
        <!-- Step 1: Upload CSV File -->
        <form method="post" enctype="multipart/form-data">
            <h2>Step 1: Upload CSV File</h2>
            <label for="csvFile">Select CSV File:  **Note that address needs to be in a single column,
             <br>but the column order is not important. You will pick fields in next step.<br><br></label>
            <input type="file" name="csvFile" id="csvFile" accept=".csv" required>
            <button type="submit" name="submit_file">Upload</button>
        </form>
    <?php elseif (empty($apiResults)): ?>
        <!-- Step 2: Select Columns and Process -->
        <form method="post">
            <h2>Step 2: Configure and Process Data</h2>
            
            <label for="site_name_col">Select Site Name Column:</label>
            <select name="site_name_col" id="site_name_col" required>
                <?php foreach ($columns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="site_addr_col">Select Site Address Column:</label>
            <select name="site_addr_col" id="site_addr_col" required>
                <?php foreach ($columns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="site_city_col">Select Site City Column:</label>
            <select name="site_city_col" id="site_city_col" required>
                <?php foreach ($columns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="site_state_col">Select Site State Column:</label>
            <select name="site_state_col" id="site_state_col" required>
                <?php foreach ($columns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="site_zip_col">Select Site Zip Column:</label>
            <select name="site_zip_col" id="site_zip_col" required>
                <?php foreach ($columns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="site_id_col">Select Site Notes Column:</label>
            <select name="site_id_col" id="site_id_col" required>
                <?php foreach ($columns as $column): ?>
                    <option value="<?php echo htmlspecialchars($column); ?>"><?php echo htmlspecialchars($column); ?></option>
                <?php endforeach; ?>
            </select>
            

            <label for="org_id">Organization ID:</label>
            <input type="text" name="org_id" id="org_id" required>
            
            <label for="api_token">API Token:</label>
            <input type="password" name="api_token" id="api_token" required>
            
            <label for="rf_id">RF Template ID:</label>
            <input type="text" name="rf_id" id="rf_id" required>
            
            <label for="cloud">Choose a cloud:</label>
            <select id="cloud">
               <option label="Global 01"></option>
               <option label="Global 02">gc1.</option>
               <option label="Global 03">ac2.</option>
               <option label="Global 04">gc2.</option>
            </select>

            
            <button type="submit" name="process_data">Process Data</button>
        </form>
        
        <!-- Preview of CSV Data -->
        <div class="preview">
            <h2>CSV Preview</h2>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?php echo htmlspecialchars($column); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Show up to 5 rows for preview
                    $previewRows = array_slice($csvData, 0, 5);
                    foreach ($previewRows as $row): 
                    ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo htmlspecialchars($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($csvData) > 5): ?>
                <p>Showing 5 of <?php echo count($csvData); ?> rows</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Step 3: Show Results -->
        <h2>API Call Results</h2>
        <button onclick="location.href='?reset=1'">Start Over</button>
        
        <table>
            <thead>
                <tr>
                    <th>Site Name</th>
                    <th>Site Address</th>
                    <th>Site ID</th>
                    <th>Status</th>
                    <th>Response</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apiResults as $result): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['data']['site_name']); ?></td>
                        <td><?php echo htmlspecialchars($result['data']['site_address']); ?></td>
                        <td><?php echo htmlspecialchars($result['data']['site_city']); ?></td>
                        <td><?php echo htmlspecialchars($result['data']['site_state']); ?></td>
                        <td><?php echo htmlspecialchars($result['data']['site_zip']); ?></td>
                        <td><?php echo htmlspecialchars($result['data']['site_id']); ?></td>
                        <td>
                            <?php if ($result['success']): ?>
                                <span style="color: green;">Success (<?php echo $result['http_code']; ?>)</span>
                            <?php else: ?>
                                <span style="color: red;">Failed (<?php echo $result['http_code']; ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($result['error'])) {
                                echo 'Error: ' . htmlspecialchars($result['error']);
                            } else {
                                echo htmlspecialchars(substr($result['response'], 0, 100));
                                if (strlen($result['response']) > 100) echo '...';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Reset if requested
        if (isset($_GET['reset'])) {
            session_unset();
            echo '<script>window.location.href = window.location.pathname;</script>';
        }
        ?>
    <?php endif; ?>
  <br>
    <a href="./index.html" class="home-button">Return to Home</a>
  </div>
</body>
</html>
