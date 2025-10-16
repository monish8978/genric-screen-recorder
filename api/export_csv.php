<?php
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

include '../includes/auth.php';
include '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

ob_clean();

$activeTab = $_POST['tab'] ?? 'user';
$filters = json_decode($_POST['filters'] ?? '[]', true) ?? [];

// Function to fetch data with same logic as dashboard
function callAPI($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }
    return [];
}

// Fetch data
if ($activeTab === 'user') {
    $apiData = callAPI(USER_API_URL);
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $data = $apiData['data'];
    } elseif (isset($apiData[0])) {
        $data = $apiData;
    } else {
        $data = [];
    }
} else {
    $apiData = callAPI(CLIENT_API_URL);
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $data = $apiData['data'];
    } elseif (isset($apiData[0])) {
        $data = $apiData;
    } elseif (!empty($apiData)) {
        $data = [$apiData];
    } else {
        $data = [];
    }
}

// Apply same filters as dashboard
if (!empty($data) && !empty($filters)) {
    $filteredData = array_filter($data, function($item) use ($filters, $activeTab) {
        $match = true;
        
        if (!empty($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $found = false;
            
            if ($activeTab === 'user') {
                $fieldsToSearch = ['sessionId', 'agentId', 'campaignName', 'hostAddress', 'systemIP', 'phoneNo'];
                foreach ($fieldsToSearch as $field) {
                    if (isset($item[$field]) && stripos($item[$field], $searchTerm) !== false) {
                        $found = true;
                        break;
                    }
                }
            } else {
                $fieldsToSearch = ['clientId', 'clientName', 'hostAddress', 'macAddress'];
                foreach ($fieldsToSearch as $field) {
                    if (isset($item[$field]) && stripos((string)$item[$field], $searchTerm) !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) $match = false;
        }
        
        if (!empty($filters['status'])) {
            if ($activeTab === 'user') {
                if (($item['uploadStatus'] ?? '') !== $filters['status']) {
                    $match = false;
                }
            } else {
                $expectedStatus = $filters['status'] === 'true';
                if (($item['isValid'] ?? false) !== $expectedStatus) {
                    $match = false;
                }
            }
        }
        
        if (!empty($filters['date_from']) && isset($item['logTime'])) {
            try {
                $logDate = new DateTime($item['logTime']);
                $fromDate = new DateTime($filters['date_from']);
                if ($logDate < $fromDate) $match = false;
            } catch (Exception $e) {
                // Date parsing error - skip filter
            }
        }
        
        if (!empty($filters['date_to']) && isset($item['logTime'])) {
            try {
                $logDate = new DateTime($item['logTime']);
                $toDate = new DateTime($filters['date_to']);
                $toDate->modify('+1 day');
                if ($logDate >= $toDate) $match = false;
            } catch (Exception $e) {
                // Date parsing error - skip filter
            }
        }
        
        return $match;
    });
    $data = array_values($filteredData);
}

// Final buffer clean
ob_clean();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $activeTab . '_report_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Write headers
if ($activeTab === 'user') {
    fputcsv($output, [
        'Session ID',
        'Agent ID', 
        'Campaign Name',
        'Host Address',
        'System IP',
        // 'Video File',
        'Duration (sec)',
        'File Size (KB)',
        'Phone No',
        'Upload Status',
        'Log Time'
    ]);
} else {
    fputcsv($output, [
        'Client ID',
        'Client Name',
        'Host Address',
        'MAC Address', 
        'Is Valid',
        'Validation Status'
    ]);
}

// Write data
foreach ($data as $item) {
    if ($activeTab === 'user') {
        fputcsv($output, [
            $item['sessionId'] ?? '',
            $item['agentId'] ?? '',
            $item['campaignName'] ?? '',
            $item['hostAddress'] ?? '',
            $item['systemIP'] ?? '',
            // $item['videoFile'] ?? '',
            $item['durationSeconds'] ?? 0,
            $item['fileSizeKB'] ?? 0,
            $item['phoneNo'] ?? '',
            $item['uploadStatus'] ?? '',
            $item['logTime'] ?? ''
        ]);
    } else {
        $isValid = filter_var($item['isValid'] ?? false, FILTER_VALIDATE_BOOLEAN);
        fputcsv($output, [
            $item['clientId'] ?? '',
            $item['clientName'] ?? '',
            $item['hostAddress'] ?? '',
            $item['macAddress'] ?? '',
            $isValid ? 'true' : 'false',
            $isValid ? 'Valid' : 'Invalid'
        ]);
    }
}

fclose($output);
exit;
?>