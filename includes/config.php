<?php
session_start();

// API Configuration
define('USER_API_URL', 'https://api-report.c-zentrix.com/user_report');
define('CLIENT_API_URL', 'https://api-report.c-zentrix.com/client_report');
define('CLIENT_UPDATE_API_URL', 'https://api-report.c-zentrix.com/client_report/update_validity');

// Default credentials
define('DEFAULT_EMAIL', 'admin@gmail.com');
define('DEFAULT_PASSWORD', 'Admin');

?>