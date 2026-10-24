<?php

/**
 * Helper script to append a new prompt to USER_PROMPTS.txt
 *
 * Usage:
 *   php scripts/log_user_prompt.php "New prompt text" [conversation_id]
 */

$logFile = dirname(__DIR__) . '/USER_PROMPTS.txt';
if (!file_exists($logFile)) {
    echo "USER_PROMPTS.txt not found.\n";
    exit(1);
}

$promptText = $argv[1] ?? null;
if (!$promptText) {
    // Read from standard input if piped
    $promptText = stream_get_contents(STDIN);
}
$promptText = trim($promptText);

if (empty($promptText)) {
    echo "No prompt provided.\n";
    exit(1);
}

$sessionId = $argv[2] ?? (getenv('CONVERSATION_ID') ?: 'active-session');
$nowUtc = gmdate('Y-m-d\TH:i:s\Z');
$tz = new DateTimeZone('Asia/Karachi');
$nowLocal = (new DateTime('now', $tz))->format('Y-m-d\TH:i:sP');

$content = file_get_contents($logFile);

// Find current total prompts
if (preg_match('/Total Prompts Logged:\s*(\d+)/', $content, $m)) {
    $currentCount = (int)$m[1];
    $newCount = $currentCount + 1;
} else {
    // Fallback: count PROMPT #
    preg_match_all('/PROMPT #(\d+)/', $content, $pm);
    $currentCount = !empty($pm[1]) ? max(array_map('intval', $pm[1])) : 0;
    $newCount = $currentCount + 1;
}

// Update header
$content = preg_replace('/Total Prompts Logged:\s*\d+/', "Total Prompts Logged: " . $newCount, $content, 1);
$content = preg_replace('/Latest Prompt Date\s*:\s*[^\r\n]+/', "Latest Prompt Date : " . $nowLocal . " (UTC: " . $nowUtc . ")", $content, 1);
$content = preg_replace('/File Last Updated\s*:\s*[^\r\n]+/', "File Last Updated  : " . date('Y-m-d H:i:s') . " (PKT)", $content, 1);

// Format new entry
$entry = "--------------------------------------------------------------------------------\r\n";
$entry .= sprintf("PROMPT #%03d\r\n", $newCount);
$entry .= "Date & Time (Local) : " . $nowLocal . "\r\n";
$entry .= "Date & Time (UTC)   : " . $nowUtc . "\r\n";
$entry .= "Session ID          : " . $sessionId . "\r\n";
$entry .= "--------------------------------------------------------------------------------\r\n";
$entry .= str_replace(["\r\n", "\r", "\n"], "\r\n", $promptText) . "\r\n\r\n";

// Insert before the END block
$endBlock = "================================================================================\r\n                         END OF CURRENT PROMPTS LOG";
if (strpos($content, $endBlock) !== false) {
    $content = str_replace($endBlock, $entry . $endBlock, $content);
} else {
    $content .= $entry;
}

file_put_contents($logFile, $content);

echo "Successfully logged prompt #$newCount to USER_PROMPTS.txt\n";
