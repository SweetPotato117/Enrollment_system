<?php
include 'dbconn.php';

function insertRecord($table, $data)
{
    global $conn;
    $columns = implode(", ", array_keys($data));
    // Escape each value
    $escapedValues = array_map(function ($value) use ($conn) {
        return mysqli_real_escape_string($conn, $value);
    }, array_values($data));
    $values = implode("', '", $escapedValues);
    $query = "INSERT INTO $table ($columns) VALUES ('$values')";
    return mysqli_query($conn, $query);
}

function insertOrder($table, $orderData)
{
    global $conn;

    $columns = implode(", ", array_keys($orderData));
    $escapedValues = array_map(function ($value) use ($conn) {
        if ($value === null || $value === '') {
            return 'NULL'; // unquoted NULL for SQL
        }
        return "'" . mysqli_real_escape_string($conn, $value) . "'";
    }, array_values($orderData));

    $values = implode(", ", $escapedValues); // note: no extra quotes now
    $query = "INSERT INTO `$table` ($columns) VALUES ($values)";

    if (!mysqli_query($conn, $query)) {
        throw new Exception("Failed to create order in database: " . mysqli_error($conn));
    }

    return mysqli_insert_id($conn);
}

function editRecord($table, $data, $condition)
{
    global $conn;
    $updateData = [];
    foreach ($data as $column => $value) {
        $escapedValue = mysqli_real_escape_string($conn, $value);
        $updateData[] = "$column = '$escapedValue'";
    }
    $updateString = implode(", ", $updateData);
    $query = "UPDATE $table SET $updateString WHERE $condition";
    return mysqli_query($conn, $query);
}

function deleteRecord($table, $condition)
{
    global $conn;
    // WARNING: $condition must NOT contain unsanitized user input!
    // This function cannot be made safe if $condition is user-controlled.
    $query = "DELETE FROM $table WHERE $condition";
    return mysqli_query($conn, $query);
}

function getAllRecords($table, $condition = '')
{
    global $conn;
    // WARNING: $condition must be safe (e.g., "AND status = 'active'")
    // Do NOT pass raw user input here.
    $query = "SELECT * FROM $table $condition";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getRecord($table, $condition)
{
    global $conn;
    // Same warning: $condition must be pre-validated
    $query = "SELECT * FROM $table WHERE $condition";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

function getRecordMultiTable($table1, $table2, $onCondition, $whereCondition)
{
    global $conn;
    // All parameters assumed safe (not user-controlled)
    $query = "SELECT * FROM $table1 LEFT JOIN $table2 ON $onCondition WHERE $whereCondition";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

function countAllRecords($table, $whereCondition = '1')
{
    global $conn;
    // $whereCondition must be safe
    $query = "SELECT COUNT(*) as total FROM $table WHERE $whereCondition";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'];
}

function updateRecord($table, $data, $condition)
{
    global $conn;
    $updates = [];
    foreach ($data as $key => $value) {
        if ($value === null || $value === 'NULL') {
            $updates[] = "$key = NULL";
        } else {
            $updates[] = "$key = '" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }
    $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE $condition";
    return mysqli_query($conn, $sql);
}

// Transaction functions unchanged (they don't handle data)
function startTransaction()
{
    global $conn;
    mysqli_autocommit($conn, false);
    mysqli_begin_transaction($conn);
}

function commitTransaction()
{
    global $conn;
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);
}

function rollbackTransaction()
{
    global $conn;
    mysqli_rollback($conn);
    mysqli_autocommit($conn, true);
}

function hideNameGCashStyle($firstName, $lastName)
{
    $hiddenParts = [];

    // Process firstName (can contain multiple names)
    if (!empty($firstName)) {
        $firstNames = explode(' ', trim($firstName));

        foreach ($firstNames as $name) {
            if (empty($name)) {
                continue;
            }

            $length = strlen($name);

            if ($length <= 2) {
                // Short names: show first character only
                $hiddenParts[] = $name[0] . str_repeat('*', $length - 1);
            } else {
                // Longer names: show first, hide middle, show last
                $hiddenParts[] = $name[0] . str_repeat('*', $length - 2) . $name[$length - 1];
            }
        }
    }

    // Process lastName - always show first character with period
    if (!empty($lastName)) {
        $hiddenParts[] = $lastName[0] . '.';
    }

    return implode(' ', $hiddenParts);
}