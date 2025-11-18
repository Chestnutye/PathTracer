<?php
// --- 安全加固：确保文件不是通过 require/include 意外运行 ---
if (php_sapi_name() !== 'cli' && !defined('STDIN') && basename(__FILE__) !== 'ip_proxy.php') {
    return;
}

// --- 配置区 ---
// ⚠ 必改项 1：将 'your-domain.com' 替换为您网站的实际域名
$ALLOWED_DOMAIN = 'your-domain.com'; 
// ⚠必改项 2：您的 ipinfo.io 令牌
$IPINFO_TOKEN = '111111111'; 

// --- 访客 IP 提取逻辑 ---
function getVisitorIP() {
    // 优先级顺序：Cloudflare > Real-IP > Forwarded-For > Remote_Addr
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP']; 
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_list[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

$visitor_ip = getVisitorIP();

// --- 安全检查：Referer 防护 ---
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

// 检查 Referer 是否包含允许的域名
if (empty($referer) || strpos($referer, $ALLOWED_DOMAIN) === false) {
    // 设置响应头并退出，防止非授权调用
    header('HTTP/1.0 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied: Invalid Referer.']);
    exit;
}

// --- 设置响应头 (CORS & Cache) ---
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate'); 
header('Access-Control-Allow-Origin: *'); // 允许所有前端访问此 JSON 结果

// --- API 调用执行 ---
$apiUrl = "https://ipinfo.io/{$visitor_ip}/json?token={$IPINFO_TOKEN}";

// 设置请求上下文
$context = stream_context_create([
    'http' => [
        'timeout' => 4, // 4秒超时
        'ignore_errors' => true 
    ],
    'ssl' => [
        'verify_peer' => false, // 绕过 SSL 证书验证，增强兼容性
        'verify_peer_name' => false
    ]
]);

$response = @file_get_contents($apiUrl, false, $context);

if ($response === FALSE) {
    // API 调用失败
    http_response_code(503);
    echo json_encode(['error' => 'API Service Unavailable.']);
} else {
    // 成功，直接输出 ipinfo.io 返回的数据
    echo $response;
}
?>