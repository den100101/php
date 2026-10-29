<?php

// Start the session before any output or HTML.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Load local .env file
|--------------------------------------------------------------------------
*/

$envFile = __DIR__ . "/.env";

if (is_file($envFile)) {
    $envValues = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if ($envValues === false) {
        error_log("Unable to parse .env file.");
        http_response_code(500);
        exit("Application configuration error.");
    }

    foreach ($envValues as $key => $value) {
        if (getenv($key) === false) {
            putenv($key . "=" . $value);
            $_ENV[$key] = $value;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Database configuration
|--------------------------------------------------------------------------
*/

$isProduction = strtolower(getenv("APP_ENV") ?: "development") === "production";

$dbHost = getenv("DB_HOST") ?: "";
$dbPort = (int)(getenv("DB_PORT") ?: 3306);
$dbName = getenv("DB_NAME") ?: "";
$dbUser = getenv("DB_USER") ?: "";
$dbPassword = getenv("DB_PASSWORD");

if ($dbPassword === false) {
    $dbPassword = "";
}

if (
    $dbHost === "" ||
    $dbName === "" ||
    $dbUser === "" ||
    ($isProduction && $dbPassword === "") ||
    $dbPort < 1 ||
    $dbPort > 65535
) {
    error_log("Missing or invalid database configuration.");
    http_response_code(500);
    exit("Database configuration error.");
}

/*
|--------------------------------------------------------------------------
| Connect to MySQL
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_init();

    if ($conn === false) {
        throw new RuntimeException("Unable to initialize MySQL connection.");
    }

    $sslEnabled = filter_var(
        getenv("DB_SSL") ?: "false",
        FILTER_VALIDATE_BOOLEAN
    );

    if ($isProduction && !$sslEnabled) {
        throw new RuntimeException(
            "SSL must be enabled for the production database connection."
        );
    }

    if ($sslEnabled) {
        $caFile = getenv("DB_SSL_CA");

        if ($caFile === false || $caFile === "") {
            if ($isProduction) {
                throw new RuntimeException(
                    "Production database CA certificate is not configured."
                );
            }
        } elseif (!is_file($caFile) || !is_readable($caFile)) {
            throw new RuntimeException(
                "Database CA certificate is missing or unreadable."
            );
        } else {
            $conn->ssl_set(null, null, $caFile, null, null);
        }
    }

    $flags = $sslEnabled ? MYSQLI_CLIENT_SSL : 0;

    $conn->real_connect(
        $dbHost,
        $dbUser,
        $dbPassword,
        $dbName,
        $dbPort,
        null,
        $flags
    );

    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to connect to the database. Please try again later.");
}
