<?php
// ==========================================================
// ⚙️ 配置选项 (Configuration Options)
// ==========================================================

// 设置为 false (不显示) 可以隐藏源站服务器 IP (源站 IP 属于敏感信息)
$SHOW_SERVER_IP = true; 

// 设置时区为 UTC (计算和显示标准时间)
date_default_timezone_set('UTC');

// 1. 获取时间戳
$timestamp_generated = time();
$readable_time = date('Y-m-d H:i:s T', $timestamp_generated);

// 2. IP & 地理位置函数
// --- 获取源站服务器 IP ---
function getServerIP() {
    return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : (gethostbyname(gethostname()) ?: '未知');
}

// --- 获取 CDN 节点/代理 IP ---
function getNodeIP() {
    return $_SERVER['REMOTE_ADDR'];
}

// --- 获取访客真实 IP (Snapshot) ---
function getVisitorIP() {
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (isset($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP']; 
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_list[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

// --- 获取 IP 地理位置 (PHP 后端查询，用于缓存 IP) ---
function getGeoLocation($ip) {
    if (strpos($ip, '172.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '192.168') === 0 || $ip === '127.0.0.1') {
        return '内部/局域网 IP';
    }
    $ctx = stream_context_create(['http'=> ['timeout' => 2]]);
    $json = @file_get_contents("http://ip-api.com/json/{$ip}?lang=zh-CN", false, $ctx);
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['status']) && $data['status'] == 'success') {
            return $data['country'] . ' ' . $data['regionName'] . ' ' . $data['city'] . ' (' . $data['isp'] . ')';
        }
    }
    return '未知位置';
}

// --- CDN 厂商检测 (包含又拍云 shanks 修正) ---
function detectProvider() {
    if (isset($_SERVER['HTTP_X_VIA'])) {
        $x_via = strtolower($_SERVER['HTTP_X_VIA']);
        if (strpos($x_via, 'shanks') !== false) return 'Upyun (又拍云) [Via Shanks]';
    }

    $unique_headers = [
        'HTTP_CF_RAY'            => 'Cloudflare',
        'HTTP_X_AMZ_CF_ID'       => 'AWS CloudFront',
        'HTTP_ALI_SWIFT_GLOBAL_SAVED_STORE' => 'Aliyun (阿里云)'
    ];

    foreach ($unique_headers as $key => $val) {
        if (isset($_SERVER[$key])) return $val;
    }

    if (isset($_SERVER['HTTP_VIA'])) {
        $via = strtolower($_SERVER['HTTP_VIA']);
        if (strpos($via, 'upyun') !== false) return 'Upyun (又拍云)';
    }
    
    if (getVisitorIP() === getNodeIP()) return '无 (直连)';

    return 'Unknown / Custom (未知/自定义)';
}


// --- 核心数据获取 ---
$server_ip = getServerIP();
$node_ip   = getNodeIP();
$visitor_ip= getVisitorIP();

$is_direct = ($node_ip === $visitor_ip);
$provider = detectProvider();

$visitor_geo = getGeoLocation($visitor_ip);
$node_geo    = $is_direct ? '同上 (直连)' : getGeoLocation($node_ip);
// PHP 代码结束
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>完整网络链路诊断面板</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f0f0f; color: #ccc; padding: 20px; display: flex; justify-content: center; }
        .container { max-width: 600px; width: 100%; }
        
        .card { background: #1e1e1e; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #333; }
        .connector { text-align: center; font-size: 1.5rem; color: #555; margin: -10px 0 10px 0; }
        h2 { margin: 0 0 10px 0; font-size: 1rem; color: #888; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #333; padding-bottom: 5px; }
        
        .ip-display { font-size: 1.4rem; color: #fff; font-family: monospace; font-weight: bold; margin: 5px 0; }
        .geo-display { font-size: 0.9rem; color: #d4a373; margin-top: 5px; }
        
        .c-visitor { border-left: 5px solid #4caf50; } 
        .c-node    { border-left: 5px solid #2196f3; } 
        .c-server  { border-left: 5px solid #f44336; } 

        .time-box { text-align: center; padding: 15px; background: #181818; border-radius: 8px; border: 1px dashed #444; }
        .status-old { color: #ff5252; font-weight: bold; }
        .status-fresh { color: #4caf50; font-weight: bold; }
        
        .realtime-ip-box { background: #333; padding: 10px; border-radius: 6px; margin-top: 10px; }
        .realtime-ip-box span { color: #69f0ae; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">

    <div class="card" style="text-align: center; background: #252525;">
        <h2>CDN 服务商: <span style="color: #64b5f6;"><?php echo $provider; ?></span></h2>
        <p style="font-size: 0.9rem; color: #aaa;">页面生成时间 (UTC): <?php echo $readable_time; ?></p>
    </div>

    <div class="card c-visitor">
        <h2>1. 访客真实 IP (Snapshot)</h2>
        <div class="ip-display"><?php echo $visitor_ip; ?></div>
        <div class="geo-display">📍 <?php echo $visitor_geo; ?></div>
        <div style="font-size: 0.8rem; color: #666; margin-top: 10px;">
            (这是<b>缓存生成时</b>的访客 IP)
        </div>
    </div>

    <div class="connector">⬇️ 请求发送给 CDN ⬇️</div>

    <div class="card c-node">
        <h2>2. 传输节点 IP (CDN Edge)</h2>
        <div class="ip-display"><?php echo $node_ip; ?></div>
        <div class="geo-display">📍 <?php echo $node_geo; ?></div>
    </div>

    <?php if ($SHOW_SERVER_IP): // <-- 条件判断开始 ?>
        <div class="connector">⬇️ 回源请求 ⬇️</div>

        <div class="card c-server">
            <h2>3. 源站服务器 IP (Origin Server)</h2>
            <div class="ip-display"><?php echo $server_ip; ?></div>
            <div class="geo-display">🖥️ 运行此 PHP 脚本的主机 IP (通常为内部 IP)</div>
        </div>
    <?php endif; // <-- 条件判断结束 ?>

    <div class="time-box">
        <div style="font-size: 0.9rem; color: #888;">缓存已存在时长 (5小时阈值)</div>
        <div id="timer-result" style="margin-top: 5px;">计算缓存时间中...</div>
        
        <hr style="border-color: #444; margin: 15px 0;">

        <div style="font-size: 0.9rem; color: #888;">当前实时访客 IP (绕过缓存)</div>
        <div class="realtime-ip-box">
            <div class="label">IP: <span id="real-time-ip">查询中...</span></div>
            <div class="label">位置: <span id="real-time-geo">等待 IP...</span></div>
        </div>
    </div>

</div>

<script>
    // --- 缓存时间监控逻辑 ---
    const serverTimeMs = <?php echo $timestamp_generated; ?> * 1000;
    const LIMIT_HOURS = 5;

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return `${h}小时 ${m}分 ${s}秒`;
        return `${m}分 ${s}秒`;
    }

    // 每秒更新缓存时间
    setInterval(() => {
        const now = new Date().getTime();
        let diff = Math.floor((now - serverTimeMs) / 1000);
        if (diff < 0) diff = 0;

        const el = document.getElementById('timer-result');
        const timeStr = formatTime(diff);

        if (diff >= LIMIT_HOURS * 3600) {
            el.innerHTML = `<span class="status-old">⚠️ 缓存已过期: ${timeStr}</span>`;
        } else {
            el.innerHTML = `<span class="status-fresh">✅ 缓存有效: ${timeStr}</span>`;
        }
    }, 1000);


    // --- 实时 IP 获取逻辑 (调用本地代理文件 ip_proxy.php) ---
    function getRealTimeIP() {
        const ipEl = document.getElementById('real-time-ip');
        const geoEl = document.getElementById('real-time-geo');
        
        const proxyUrl = './ip_proxy.php'; 

        fetch(proxyUrl) 
            .then(response => {
                if (!response.ok) {
                    throw new Error('Proxy request failed: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                const currentIP = data.ip;
                ipEl.innerText = currentIP;

                const location = `${data.country || ''} ${data.region || ''} ${data.city || ''}`;
                const org = data.org ? `(${data.org})` : '';

                let displayLocation = location.trim().replace('China', '中国');
                
                geoEl.innerText = `${displayLocation} ${org}`;
            })
            .catch(error => {
                console.error('Frontend IP/Geo Error:', error);
                ipEl.innerText = '获取失败 (代理或API错误)';
                geoEl.innerText = '请检查 ip_proxy.php 文件配置。';
            });
    }

    getRealTimeIP();
</script>

</body>
</html>
