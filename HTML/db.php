<?php
declare(strict_types=1);

function getDatabaseConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('STUDENTHUB_DB_PORT') ?: '3306');
    $database = getenv('STUDENTHUB_DB_NAME') ?: 'student_hub';
    $username = getenv('STUDENTHUB_DB_USER') ?: 'root';
    $password = getenv('STUDENTHUB_DB_PASSWORD');

    $connection = new mysqli(
        $host,
        $username,
        $password === false ? '' : $password,
        $database,
        $port
    );
    $connection->set_charset('utf8mb4');

    return $connection;
}
