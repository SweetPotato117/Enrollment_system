<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$database = getenv('DB_NAME') ?: 'enrollment_system';
$port = filter_var(getenv('DB_PORT') ?: '4000', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);

try {
    if (!$host || !$user || $password === false || $password === '' || !$port) {
        throw new RuntimeException('Missing database configuration');
    }

    $ca = getenv('DB_SSL_CA');
    if (!$ca) {
        $locations = openssl_get_cert_locations();
        foreach ([ini_get('openssl.cafile'), $locations['default_cert_file'],
                  '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt'] as $candidate) {
            if ($candidate && is_readable($candidate)) {
                $ca = $candidate;
                break;
            }
        }
    }
    if (!$ca || !is_readable($ca)) {
        throw new RuntimeException('Missing database CA bundle');
    }

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $conn->ssl_set(null, null, $ca, null, null);
    if (!$conn->real_connect($host, $user, $password, $database, $port, null, MYSQLI_CLIENT_SSL)
        || !$conn->set_charset('utf8mb4')) {
        throw new RuntimeException('Database connection failed');
    }
} catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}
