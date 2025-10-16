<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header('Content-Type: application/json');
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$clientId = $_POST['clientId'] ?? '';
$clientName = $_POST['clientName'] ?? '';
$isValid = $_POST['isValid'] ?? '';
// $macAddress = $_POST['macAddress'] ?? '';

if (empty($clientId)) {
    echo json_encode(['success' => false, 'message' => 'Client ID and MAC Address are required']);
    exit;
}

// Prepare data for API
$postData = [
    'clientId' => (int)$clientId,
    'clientName' => $clientName,
    // 'macAddress' => $macAddress,
    'isValid' => $isValid === 'true'
];

// Call the update API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, CLIENT_UPDATE_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $responseData = json_decode($response, true);
    echo json_encode([
        'success' => true,
        'message' => 'Client updated successfully',
        'data' => $responseData
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update client. API Error: ' . $error . ' (HTTP Code: ' . $httpCode . ')',
        'debug' => $response
    ]);
}
?>