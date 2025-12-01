<?php
// search_config_loader.php
// Loads dynamic search config for a given table and scope

function load_search_config($pdo, $table_name, $scope_type = 'global', $scope_value = null) {
    // Try to load the most specific config first, then fallback to global
    $sql = "SELECT config_json FROM search_config WHERE table_name = ? AND scope_type = ? AND 
            (scope_value = ? OR (scope_value IS NULL AND ? IS NULL))
            ORDER BY scope_type DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table_name, $scope_type, $scope_value, $scope_value]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['config_json'])) {
        return json_decode($row['config_json'], true);
    }
    // Fallback to global config
    $sql = "SELECT config_json FROM search_config WHERE table_name = ? AND scope_type = 'global' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table_name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['config_json'])) {
        return json_decode($row['config_json'], true);
    }
    return null; // No config found
}
