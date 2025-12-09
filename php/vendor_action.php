<?php
if ((isset($_POST['action']) && $_POST['action'] === 'fetch_thumanns_csv') || (isset($_GET['vendor']) && strtolower($_GET['vendor']) === 'thumanns' && isset($_GET['action']) && strtolower($_GET['action']) === 'fetch')) {
    header('Content-Type: application/json');
    $python = 'C:\\Users\\miket\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
    $script = 'd:\\xampp\\htdocs\\order_system\\python\\fetch_thumanns_csv_clean.py';
    $output = [];
    $return_var = 0;
    exec("\"$python\" \"$script\"", $output, $return_var);
    if ($return_var === 0) {
        $response = [
            "status" => "Thumanns CSV fetched successfully!",
            "preview" => "<em>CSV downloaded for Thumanns.</em>"
        ];
    } else {
        $response = [
            "status" => "Error fetching Thumanns CSV.",
            "preview" => implode("<br>", $output)
        ];
    }
    echo json_encode($response);
    exit;
}

if (isset($_GET['vendor']) && strtolower($_GET['vendor']) === 'thumanns' && isset($_GET['action']) && strtolower($_GET['action']) === 'import') {
    header('Content-Type: application/json');
    $php_path = 'D:\xampp\php\php.exe';
    $script = 'd:\\xampp\\htdocs\\order_system\\php\\import_thumanns_cli.php';
    $output = [];
    $return_var = 0;
    $cmd = "$php_path \"$script\" 2>&1";
    exec($cmd, $output, $return_var);
    $preview = implode("<br>", $output);
    $debug = [
        'cmd' => $cmd,
        'cwd' => getcwd(),
        'user' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['name'] : 'unknown',
        'output' => $output,
        'return_var' => $return_var
    ];
    if ($return_var === 0) {
        $response = [
            "status" => "Thumanns import completed!",
            "preview" => $preview,
            "debug" => $debug
        ];
    } else {
        $response = [
            "status" => "Error running Thumanns import.",
            "preview" => $preview,
            "debug" => $debug
        ];
    }
    echo json_encode($response);
    exit;
}
?>
<?php
header('Content-Type: application/json');
$vendor = isset($_GET['vendor']) ? $_GET['vendor'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$response = ["status" => "", "preview" => ""];

// Example stub logic for each action
switch (strtolower($action)) {
    case 'fetch':
    case 'extract':
    case 'import':
        $vendorList = [
            'fancy foods' => 'Fancy Foods',
            'thumanns' => 'Thumanns',
            'reddy raw' => 'Reddy Raw',
            'driscolls' => 'Driscolls',
            'foodirect' => 'FooDirect',
            'm&v' => 'M&V',
            'm&amp;v' => 'M&V'
        ];
        $vendorKey = strtolower($vendor);
        $vendorName = isset($vendorList[$vendorKey]) ? $vendorList[$vendorKey] : $vendor;
        $response['status'] = ucfirst($action) . "ed data for $vendorName.";
        $response['preview'] = "<em>Sample $action preview for $vendorName.</em>";
        break;
    default:
        $response['status'] = "Unknown action: $action";
}
echo json_encode($response);
