<?php
try {
    $conn = mysqli_connect('localhost', 'root', '', 'enrollment_system');
} catch (mysqli_sql_exception $exception) {
    $conn = false;
}

if (!$conn) {
    if (defined('DB_CONNECTION_FAIL_SILENT') && DB_CONNECTION_FAIL_SILENT) {
        return;
    }

    die('Connection Failed');
}

mysqli_set_charset($conn, "utf8");