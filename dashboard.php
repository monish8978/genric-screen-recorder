<?php
// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Fetch data based on active tab
$activeTab = $_GET['tab'] ?? 'user';
$data = [];
$rawResponse = '';
$filteredData = [];

// Pagination settings
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

function callAPI($url, $method = 'GET', $postData = null) {
    global $rawResponse;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST' && $postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $rawResponse = "HTTP Code: $httpCode, Error: $error, Response: " . $response;
    
    if ($httpCode === 200 && $response) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }
    return [];
}

// Handle Add Client form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $clientData = [
        'clientId' => intval($_POST['clientId']),
        'hostAddress' => $_POST['hostAddress'] ?? '',
        'macAddress' => $_POST['macAddress'] ?? '',
        'clientName' => $_POST['clientName'] ?? '',
        'CreationDate' => $_POST['CreationDate'] ?? '',
        'isValid' => $_POST['isValid'] === 'true'
    ];
    
    $result = callAPI(CLIENT_API_URL_ADD, 'POST', $clientData);
    
    // Always return JSON response
    header('Content-Type: application/json');
    
    if (!empty($result)) {
        // Use the actual API response
        echo json_encode([
            'status' => $result['status'] ?? 200,
            'message' => $result['message'] ?? 'Client added successfully!',
            'data' => $result['data'] ?? []
        ]);
    } else {
        echo json_encode([
            'status' => 400,
            'message' => 'Failed to add client. API returned empty response.',
            'data' => []
        ]);
    }
    exit;
}

// Fetch and process data
if ($activeTab === 'user') {
    $apiData = callAPI(USER_API_URL);
    
    // Debug API response
    // echo "<pre>User API Response: "; print_r($apiData); echo "</pre>";
    
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $data = $apiData['data'];
    } elseif (isset($apiData[0])) {
        $data = $apiData;
    } elseif (!empty($apiData) && !isset($apiData['data'])) {
        // If API returns direct array without 'data' key
        $data = $apiData;
    }
    
} elseif ($activeTab === 'client') {
    $apiData = callAPI(CLIENT_API_URL);
    
    // Debug API response
    // echo "<pre>Client API Response: "; print_r($apiData); echo "</pre>";
    
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $data = $apiData['data'];
    } elseif (isset($apiData[0])) {
        $data = $apiData;
    } elseif (!empty($apiData)) {
        $data = [$apiData];
    }
}

// Debug: Check what data we have
// echo "<pre>Final Data: "; print_r($data); echo "</pre>";

// Apply filters
if (!empty($data)) {
    $filteredData = array_filter($data, function($item) use ($filters, $activeTab) {
        $match = true;
        
        // Search filter - Case insensitive search across multiple fields
        if (!empty($filters['search'])) {
            $searchTerm = strtolower(trim($filters['search']));
            $found = false;
            
            if ($activeTab === 'user') {
                $fieldsToSearch = ['sessionId', 'agentId', 'campaignName', 'hostAddress', 'systemIP', 'phoneNo'];
                foreach ($fieldsToSearch as $field) {
                    if (isset($item[$field]) && stripos(strtolower($item[$field]), $searchTerm) !== false) {
                        $found = true;
                        break;
                    }
                }
            } else {
                $fieldsToSearch = ['clientId', 'hostAddress', 'macAddress'];
                foreach ($fieldsToSearch as $field) {
                    if (isset($item[$field]) && stripos(strtolower((string)$item[$field]), $searchTerm) !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) {
                $match = false;
            }
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            if ($activeTab === 'user') {
                if (($item['uploadStatus'] ?? '') !== $filters['status']) {
                    $match = false;
                }
            } else {
                $expectedStatus = $filters['status'] === 'true';
                $actualStatus = filter_var($item['isValid'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($actualStatus !== $expectedStatus) {
                    $match = false;
                }
            }
        }
        
        // Date filter - Handle different date formats
        if (!empty($filters['date_from']) && isset($item['logTime'])) {
            try {
                $logDate = new DateTime($item['logTime']);
                $fromDate = new DateTime($filters['date_from']);
                if ($logDate < $fromDate) {
                    $match = false;
                }
            } catch (Exception $e) {
                // Date parsing failed, skip date filter
            }
        }
        
        if (!empty($filters['date_to']) && isset($item['logTime'])) {
            try {
                $logDate = new DateTime($item['logTime']);
                $toDate = new DateTime($filters['date_to']);
                $toDate->modify('+1 day'); // Include the entire end date
                if ($logDate >= $toDate) {
                    $match = false;
                }
            } catch (Exception $e) {
                // Date parsing failed, skip date filter
            }
        }
        
        return $match;
    });
    
    // Reindex array
    $filteredData = array_values($filteredData);
} else {
    $filteredData = [];
}

// Get display data (filtered or all)
$displayData = !empty($filteredData) ? $filteredData : $data;

// Calculate pagination
$totalRecords = count($displayData);
$totalPages = ceil($totalRecords / $perPage);
$offset = ($currentPage - 1) * $perPage;
$paginatedData = array_slice($displayData, $offset, $perPage);

// Get unique values for dropdowns
$uniqueStatuses = [];
$uniqueCampaigns = [];
$uniqueAgents = [];

if ($activeTab === 'user' && !empty($data)) {
    foreach ($data as $item) {
        if (isset($item['uploadStatus'])) {
            $uniqueStatuses[$item['uploadStatus']] = true;
        }
        if (isset($item['campaignName'])) {
            $uniqueCampaigns[$item['campaignName']] = true;
        }
        if (isset($item['agentId'])) {
            $uniqueAgents[$item['agentId']] = true;
        }
    }
    $uniqueStatuses = array_keys($uniqueStatuses);
    $uniqueCampaigns = array_keys($uniqueCampaigns);
    $uniqueAgents = array_keys($uniqueAgents);
}

// Build pagination URL
function buildPaginationUrl($page, $activeTab, $filters) {
    $params = ['tab' => $activeTab, 'page' => $page];
    if (!empty($filters['search'])) $params['search'] = $filters['search'];
    if (!empty($filters['status'])) $params['status'] = $filters['status'];
    if (!empty($filters['date_from'])) $params['date_from'] = $filters['date_from'];
    if (!empty($filters['date_to'])) $params['date_to'] = $filters['date_to'];
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="flex h-screen">
        <div class="w-64 bg-white shadow-lg">
            <div class="p-6 border-b">
                <h1 class="text-xl font-bold text-gray-800">Dashboard</h1>
                <p class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>
            
            <!-- Sidebar ke links update karo -->
<nav class="mt-6">
    <a href="dashboard?tab=user" class="flex items-center px-6 py-3 text-gray-700 <?php echo $activeTab === 'user' ? 'bg-blue-50 border-r-4 border-blue-500 text-blue-700' : 'hover:bg-gray-50'; ?>">
        <i class="fas fa-users mr-3"></i>
        <span>USER</span>
    </a>
    
    <a href="dashboard?tab=client" class="flex items-center px-6 py-3 text-gray-700 <?php echo $activeTab === 'client' ? 'bg-blue-50 border-r-4 border-blue-500 text-blue-700' : 'hover:bg-gray-50'; ?>">
        <i class="fas fa-user-tie mr-3"></i>
        <span>CLIENT</span>
    </a>
    
    <a href="logout" class="flex items-center px-6 py-3 text-gray-700 hover:bg-gray-50">
        <i class="fas fa-sign-out-alt mr-3"></i>
        <span>Logout</span>
    </a>
</nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo ucfirst($activeTab); ?> Reports</h1>
                    <p class="text-gray-600">Comprehensive overview of all <?php echo $activeTab; ?> activities and status</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                                <i class="fas fa-database text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Records</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($data); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Filtered Records</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($filteredData); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                                <i class="fas fa-chart-line text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Completed</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php 
                                    $completedCount = 0;
                                    $dataToCount = !empty($filteredData) ? $filteredData : $data;
                                    if ($activeTab === 'user') {
                                        foreach ($dataToCount as $item) {
                                            if (($item['uploadStatus'] ?? '') === 'Done') $completedCount++;
                                        }
                                    } else {
                                        foreach ($dataToCount as $item) {
                                            if (filter_var($item['isValid'] ?? false, FILTER_VALIDATE_BOOLEAN)) $completedCount++;
                                        }
                                    }
                                    echo $completedCount;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                <i class="fas fa-sync-alt text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Last Updated</p>
                                <p class="text-2xl font-bold text-gray-900">Just now</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="bg-white rounded-lg shadow mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Filters</h3>
                        <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                            <p class="text-sm text-green-600 mt-1">
                                <i class="fas fa-filter mr-1"></i>
                                Active filters applied
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                            
                            <!-- Search Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                    placeholder="Search across all fields..." 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <!-- Status Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Status</option>
                                    <?php if ($activeTab === 'user'): ?>
                                        <?php foreach ($uniqueStatuses as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="true" <?php echo $filters['status'] === 'true' ? 'selected' : ''; ?>>Valid</option>
                                        <option value="false" <?php echo $filters['status'] === 'false' ? 'selected' : ''; ?>>Invalid</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <!-- Date From Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <!-- Date To Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <!-- Filter Buttons -->
                            <div class="md:col-span-2 lg:col-span-4 flex space-x-3 justify-end">
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 flex items-center">
                                    <i class="fas fa-filter mr-2"></i>
                                    Apply Filters
                                </button>
                                <a href="?tab=<?php echo $activeTab; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 flex items-center">
                                    <i class="fas fa-times mr-2"></i>
                                    Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">
                                <?php echo ucfirst($activeTab); ?> Data 
                                <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                                    <span class="text-sm text-green-600 ml-2">
                                        <i class="fas fa-filter mr-1"></i>
                                        Filtered
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <div class="flex space-x-3">
                             
                                
                                <!-- Export Button -->
                                <?php if (!empty($displayData)): ?>
                                <form method="POST" action="api/export_csv.php" class="inline">
                                    <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                                    <input type="hidden" name="filters" value="<?php echo htmlspecialchars(json_encode($filters)); ?>">
                                    <button type="submit" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 flex items-center">
                                        <i class="fas fa-download mr-2"></i>
                                        Export CSV
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($filteredData) && empty($data)): ?>
                        <div class="p-12 text-center">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-inbox text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No data available</h3>
                            <p class="text-gray-500 mb-6">There are no <?php echo $activeTab; ?> records to display at the moment.</p>
                            <?php if (!empty($rawResponse)): ?>
                                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg max-w-2xl mx-auto">
                                    <div class="flex">
                                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1 mr-3"></i>
                                        <div>
                                            <h4 class="text-sm font-medium text-yellow-800">API Response</h4>
                                            <pre class="text-xs text-yellow-700 mt-1 whitespace-pre-wrap"><?php echo htmlspecialchars($rawResponse); ?></pre>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif (empty($filteredData) && !empty($data)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No matching records found</h3>
                            <p class="text-gray-500">Try adjusting your filters to see more results.</p>
                            <div class="mt-4">
                                <a href="?tab=<?php echo $activeTab; ?>" class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-md hover:bg-blue-100">
                                    Clear all filters
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <?php if ($activeTab === 'user'): ?>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Session Id</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Agent Id</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Campaign Name</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Host Address</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">System IP</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Video File</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Duration & Size</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Phone No</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Time</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                        <?php else: ?>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Client Id</th>
                                             <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Client Name</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Host Address</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Mac Address</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Creation Date</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Validation</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($paginatedData as $item): ?>
                                        <?php if (is_array($item)): ?>
                                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                                <?php if ($activeTab === 'user'): ?>
                                                    <!-- User Report Columns -->
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['sessionId'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['agentId'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['campaignName'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['hostAddress'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['systemIP'] ?? 'N/A'); ?></div>
                                                    </td>
                                                 <td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm font-medium text-gray-900" title="<?php echo htmlspecialchars(basename($item['videoFile'] ?? 'N/A')); ?>">
        <?php
        $text = $item['videoFile'] ?? 'N/A';
        $n = 7;

        if ($text === '' || $text === 'N/A') {
            echo htmlspecialchars($text);
        } else {
            // sirf file name (path hata kar)
            $fileName = basename($text);

            if (preg_match_all('/[A-Za-z0-9]+/u', $fileName, $m, PREG_OFFSET_CAPTURE)) {
                $tokens = $m[0];
                $count = count($tokens);

                if ($count <= $n) {
                    $visible = $fileName;
                } else {
                    $start = $tokens[$count - $n][1];
                    $start2 = $start;
                    while ($start2 > 0 && !preg_match('/[A-Za-z0-9]/u', $fileName[$start2 - 1])) {
                        $start2--;
                    }
                    $visible = mb_substr($fileName, $start2);
                }
            } else {
                $visible = mb_substr($fileName, -100);
            }

            echo htmlspecialchars($visible);
        }
        ?>
    </div>
</td>

                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo ($item['durationSeconds'] ?? 0) . ' sec'; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo ($item['fileSizeKB'] ?? 0) . ' KB'; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['phoneNo'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">
                                                           <?php 
$logTime = $item['logTime'] ?? '';

if (!empty($logTime)) {
    try {
        // Set timezone (India)
        $date = new DateTime($logTime, new DateTimeZone('UTC')); // if original is UTC
        $date->setTimezone(new DateTimeZone('Asia/Kolkata')); // convert to IST

        // Format: Oct 23, 2025 - 12:45 PM
        echo $date->format('M j, Y - g:i A');
    } catch (Exception $e) {
        echo htmlspecialchars($logTime);
    }
} else {
    echo 'N/A';
}
?>

                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status = $item['uploadStatus'] ?? 'Unknown';
                                                        $statusColor = 'gray';
                                                        if ($status === 'Done') $statusColor = 'green';
                                                        elseif ($status === 'Processing') $statusColor = 'blue';
                                                        elseif ($status === 'Failed') $statusColor = 'red';
                                                        ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                                            <span class="w-2 h-2 bg-<?php echo $statusColor; ?>-400 rounded-full mr-2"></span>
                                                            <?php echo htmlspecialchars($status); ?>
                                                        </span>
                                                    </td>
                                                <?php else: ?>
                                                    <!-- Client Report Columns -->
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['clientId'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['clientName'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['hostAddress'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['macAddress'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['CreationDate'] ?? 'N/A'); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $isValid = filter_var($item['isValid'] ?? false, FILTER_VALIDATE_BOOLEAN);
                                                        $validText = $isValid ? 'Valid' : 'Invalid';
                                                        $validColor = $isValid ? 'green' : 'red';
                                                        ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $validColor; ?>-100 text-<?php echo $validColor; ?>-800">
                                                            <i class="fas <?php echo $isValid ? 'fa-check' : 'fa-times'; ?> mr-2 text-xs"></i>
                                                            <?php echo $validText; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 bg-blue-50 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150 flex items-center">
                                                            <i class="fas fa-edit mr-2"></i>
                                                            Update
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="bg-white px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing 
                                    <span class="font-medium"><?php echo $offset + 1; ?></span> 
                                    to 
                                    <span class="font-medium"><?php echo min($offset + $perPage, $totalRecords); ?></span> 
                                    of 
                                    <span class="font-medium"><?php echo $totalRecords; ?></span> 
                                    results
                                    <?php if (!empty($filteredData) && count($filteredData) !== count($data)): ?>
                                        <span class="text-gray-500">(filtered from <?php echo count($data); ?> total records)</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <!-- Previous Button -->
                                    <?php if ($currentPage > 1): ?>
                                        <a href="<?php echo buildPaginationUrl($currentPage - 1, $activeTab, $filters); ?>" 
                                           class="px-3 py-1 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors duration-150">
                                            Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                                            Previous
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Page Numbers -->
                                    <?php 
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $currentPage + 2);
                                    
                                    for ($page = $startPage; $page <= $endPage; $page++): 
                                    ?>
                                        <a href="<?php echo buildPaginationUrl($page, $activeTab, $filters); ?>" 
                                           class="px-3 py-1 text-sm font-medium rounded-md transition-colors duration-150 <?php echo $page == $currentPage ? 'text-white bg-blue-600 border border-blue-600' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?>">
                                            <?php echo $page; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <!-- Next Button -->
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="<?php echo buildPaginationUrl($currentPage + 1, $activeTab, $filters); ?>" 
                                           class="px-3 py-1 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors duration-150">
                                            Next
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                                            Next
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>



    <!-- Update Modal for Client -->
    <?php if ($activeTab === 'client' && !empty($paginatedData)): ?>
    <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl p-6 m-4 max-w-md w-full transform transition-transform duration-300">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Update Client Information</h3>
                <button onclick="closeUpdateModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-150">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form id="updateForm" method="POST" action="api/update_client.php">
                <input type="hidden" id="update_clientId" name="clientId">
                
                <div class="space-y-4 mb-6">
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                        <input type="number" id="update_clientId_display" name="clientId" disabled 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150">
                    </div> -->
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client Name</label>
                        <input type="text" id="clientName" name="clientName" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150">
                    </div>
                    
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Host Address</label>
                        <input type="text" id="update_hostAddress" name="hostAddress" disabled 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150">
                    </div> -->
                    
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">MAC Address</label>
                        <input type="text" id="update_macAddress" name="macAddress" disabled 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150">
                    </div> -->
                    
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Creation Date</label>
                        <input type="date" id="update_CreationDate" name="CreationDate" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150">
                    </div> -->
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Validation Status</label>
                        <select id="update_isValid" name="isValid" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150">
                            <option value="true">Valid - Active Client</option>
                            <option value="false">Invalid - Inactive Client</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors duration-150">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors duration-150 flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Update Client
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
 

    // Update Client Modal Functions
    function openUpdateModal(client) {
        console.log(client);
        // Populate all fields with client data
        document.getElementById('update_clientId').value = client.clientId;
        // document.getElementById('update_clientId_display').value = client.clientId;
        document.getElementById('clientName').value = client.clientName || '';
        // document.getElementById('update_hostAddress').value = client.hostAddress || '';
        // document.getElementById('update_macAddress').value = client.macAddress || '';
        // document.getElementById('update_CreationDate').value = client.CreationDate || '';
        document.getElementById('update_isValid').value = client.isValid ? 'true' : 'false';
        
        // Show the modal
        document.getElementById('updateModal').classList.remove('hidden');
        document.getElementById('updateModal').classList.add('flex');
    }

    function closeUpdateModal() {
        document.getElementById('updateModal').classList.add('hidden');
        document.getElementById('updateModal').classList.remove('flex');
    }

    // Function to show message toast
    function showMessage(message, type) {
        // Remove existing message if any
        const existingMessage = document.getElementById('messageToast');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.id = 'messageToast';
        messageDiv.className = `fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        
        // Add icon based on type
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-3"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(messageDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Handle Update Client form submission
    <?php if ($activeTab === 'client'): ?>
    document.getElementById('updateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Client information updated successfully!');
                closeUpdateModal();
                location.reload();
            } else {
                alert('Error updating client: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error updating client');
            console.error('Error:', error);
        });
    });
    <?php endif; ?>
    </script>

     <!-- Logout Handler -->
    <?php
    if (isset($_GET['logout'])) {
        logout();
    }
    ?>
</body>
</html>
