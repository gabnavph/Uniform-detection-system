<?php
// Archive Helper Functions
// Include this file in pages that need archive functionality

/**
 * Archive a record (soft delete)
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $id Record ID
 * @return bool Success status
 */
function archive_record($conn, $table, $id) {
    $id = intval($id);
    $table = $conn->real_escape_string($table);
    
    // Check if table has archived_at column
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'archived_at'");
    if (!$check || $check->num_rows === 0) {
        return false; // Table doesn't support archiving
    }
    
    $sql = "UPDATE `$table` SET archived_at = NOW() WHERE id = $id AND archived_at IS NULL";
    return $conn->query($sql) && $conn->affected_rows > 0;
}

/**
 * Restore an archived record
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $id Record ID
 * @return bool Success status
 */
function restore_record($conn, $table, $id) {
    $id = intval($id);
    $table = $conn->real_escape_string($table);
    
    $sql = "UPDATE `$table` SET archived_at = NULL WHERE id = $id AND archived_at IS NOT NULL";
    return $conn->query($sql) && $conn->affected_rows > 0;
}

/**
 * Permanently delete an archived record
 * @param mysqli $conn Database connection  
 * @param string $table Table name
 * @param int $id Record ID
 * @return bool Success status
 */
function permanently_delete_record($conn, $table, $id) {
    $id = intval($id);
    $table = $conn->real_escape_string($table);
    
    $sql = "DELETE FROM `$table` WHERE id = $id AND archived_at IS NOT NULL";
    return $conn->query($sql) && $conn->affected_rows > 0;
}

/**
 * Get archived records for a table
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $limit Records per page
 * @param int $offset Offset for pagination
 * @return array|false Results or false on error
 */
function get_archived_records($conn, $table, $limit = 10, $offset = 0) {
    $table = $conn->real_escape_string($table);
    $limit = intval($limit);
    $offset = intval($offset);
    
    $sql = "SELECT * FROM `$table` WHERE archived_at IS NOT NULL ORDER BY archived_at DESC LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    
    if (!$result) return false;
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    return $records;
}

/**
 * Count archived records for a table
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @return int Count of archived records
 */
function count_archived_records($conn, $table) {
    $table = $conn->real_escape_string($table);
    
    $result = $conn->query("SELECT COUNT(*) FROM `$table` WHERE archived_at IS NOT NULL");
    if (!$result) return 0;
    
    $row = $result->fetch_row();
    return intval($row[0]);
}

/**
 * Get supported tables for archiving
 * @param mysqli $conn Database connection
 * @return array List of tables that support archiving
 */
function get_archivable_tables($conn) {
    $tables = ['students']; // Add more tables as needed
    $supported = [];
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'archived_at'");
        if ($check && $check->num_rows > 0) {
            $supported[] = $table;
        }
    }
    
    return $supported;
}
?>