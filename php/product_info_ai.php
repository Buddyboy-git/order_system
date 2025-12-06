
<?php
// product_info_ai.php
header('Content-Type: application/json');

// Helper to load .env
function load_env($path) {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $val) = array_pad(explode('=', $line, 2), 2, null);
        $env[trim($key)] = trim($val);
    }
    return $env;
}

if (!isset($_GET['desc']) || strlen(trim($_GET['desc'])) < 2) {
    echo json_encode(['error' => 'No description provided.']);
    exit;
}
$desc = trim($_GET['desc']);
$env = load_env(__DIR__ . '/../.env');
$openai_key = $env['OPENAI_API_KEY'] ?? '';
$ai_summary = '';
$ai_title = $desc;

if ($openai_key && strlen($openai_key) > 10) {
    // Use OpenAI API for summary
    $prompt = "Provide a concise, helpful summary for a foodservice product with this description: '$desc'. Focus on what it is, typical uses, and any interesting facts.";
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful foodservice product assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 120,
        'temperature' => 0.7
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($result) {
        $json = json_decode($result, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $ai_summary = trim($json['choices'][0]['message']['content']);
        }
    }
    if (!$ai_summary && $err) {
        $ai_summary = 'AI error: ' . $err;
    }
}

// Fallback to Wikipedia if no AI summary
if (!$ai_summary) {
    $desc_url = urlencode($desc);
    $wikiApi = "https://en.wikipedia.org/api/rest_v1/page/summary/$desc_url";
    $wikiJson = @file_get_contents($wikiApi);
    $summary = '';
    $title = $desc;
    if ($wikiJson) {
        $wikiData = json_decode($wikiJson, true);
        if (isset($wikiData['extract'])) {
            $summary = $wikiData['extract'];
            $title = $wikiData['title'] ?? $title;
        }
    }
    if (!$summary) {
        $summary = "No Wikipedia summary found for this product.";
    }
    $ai_summary = $summary;
    $ai_title = $title;
}

// Unsplash image (free demo, no API key needed for basic search)
$imgUrl = "https://source.unsplash.com/400x300/?" . urlencode($desc);

echo json_encode([
    'title' => $ai_title,
    'summary' => $ai_summary,
    'image' => $imgUrl
]);
