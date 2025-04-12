<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置响应头为JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

// 缓存目录
define('CACHE_DIR', __DIR__ . '/cache');
if (!file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0777, true);
}

// 获取本地网络接口信息
function getNetworkInterface($ip) {
    $interfaces = array();
    if (PHP_OS === 'Darwin') { // macOS
        $cmd = "ifconfig | grep inet";
    } else { // Linux
        $cmd = "ip addr | grep inet";
    }
    exec($cmd, $output);
    foreach ($output as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $interfaces[] = $line;
        }
    }
    return implode('\n', $interfaces);
}

// 获取MAC地址
function getMacAddress($ip) {
    if (PHP_OS === 'Darwin') { // macOS
        $cmd = "arp -n " . escapeshellarg($ip);
    } else { // Linux
        $cmd = "ip neigh show " . escapeshellarg($ip);
    }
    exec($cmd, $output);
    $mac = '';
    if (!empty($output[0])) {
        preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $output[0], $matches);
        if (!empty($matches[0])) {
            $mac = $matches[0];
        }
    }
    return $mac;
}

// 获取IP地址，优先获取IPv4公网地址
function getClientIP() {
    $ip = '';
    $headers = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    );
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $potential_ip) {
                $potential_ip = trim($potential_ip);
                // 验证是否为有效的IPv4地址，明确排除IPv6地址
                if (filter_var($potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !filter_var($potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    // 检查是否为公网IP
                    if (filter_var($potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $potential_ip;
                    } elseif (empty($ip)) {
                        // 如果还没有找到任何IP，先保存这个内网IP
                        $ip = $potential_ip;
                    }
                }
            }
        }
    }
    
    // 如果没有找到任何IPv4地址，返回空字符串
    return $ip;
}

// 获取本机所有IP地址
function getAllLocalIPs() {
    $ips = array();
    $cmd = PHP_OS === 'Darwin' ? "ifconfig | grep inet" : "ip addr | grep inet";
    exec($cmd, $output);
    foreach ($output as $line) {
        if (preg_match('/inet6?(?:\s+addr:?\s*)?(\S+)/', $line, $matches)) {
            $ip = $matches[1];
            // 排除IPv6、localhost和非内网IP
            if ($ip != '127.0.0.1' && strpos($ip, ':') === false) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    // 只保留内网IP
                    if (
                        strpos($ip, '192.168.') === 0 ||
                        strpos($ip, '10.') === 0 ||
                        strpos($ip, '172.') === 0
                    ) {
                        $ips[] = $ip;
                    }
                }
            }
        }
    }
    return $ips;
}

// 获取IP信息
function getIPInfo($ip) {
    // 构建请求信息
    $request_info = array(
        '编码格式' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        'IP地址' => getClientIP(),  // 改为IP地址
        '语言设置' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        '请求方式' => $_SERVER['REQUEST_METHOD'] ?? '',
        '内容类型' => $_SERVER['HTTP_ACCEPT'] ?? '',
        '浏览器标识' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    );

    // 检查缓存
    $cacheFile = CACHE_DIR . '/' . md5($ip) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
        $cached_data = json_decode(file_get_contents($cacheFile), true);
        return array(
            'request_info' => $request_info,
            'ip_details' => $cached_data
        );
    }

    // 如果是内网IP，直接返回本地信息
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $localInfo = array(
            '本机地址' => $ip,  // 改为本机地址
            '城市' => '本地网络',
            '地区' => '内部网络',
            '国家' => '本地',
            '位置坐标' => '0,0',
            '网络' => '本地网络',
            '时区' => date_default_timezone_get()
        );
        file_put_contents($cacheFile, json_encode($localInfo));
        return array(
            'request_info' => $request_info,
            'ip_details' => $localInfo
        );
    }

    // 使用 ipinfo.io API
    $token = '45e576d21a01bb';
    $curl = curl_init("https://ipinfo.io/{$ip}?token={$token}");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && !isset($data['error'])) {
            // 定义国家代码到中文名称的映射
            $countryMap = array(
                'CN' => '中国',
                'HK' => '香港',
                'TW' => '台湾',
                'US' => '美国',
                'JP' => '日本',
                'KR' => '韩国',
                'SG' => '新加坡'
            );

            $result = array(
                '本机地址' => $ip,  // 改为本机地址
                '城市' => $data['city'] ?? '未知',
                '地区' => $data['region'] ?? '未知',
                '国家' => isset($data['country']) ? ($countryMap[$data['country']] ?? $data['country']) : '未知',
                '位置坐标' => $data['loc'] ?? '未知',
                '运营商' => $data['org'] ?? '未知',
                '时区' => $data['timezone'] ?? '未知'
            );
            // 确保缓存中也不包含主机名信息
            unset($data['hostname']);
            file_put_contents($cacheFile, json_encode($result));
            return array(
                'request_info' => $request_info,
                'ip_details' => $result
            );
        }
    }

    return array(
        'request_info' => $request_info,
        'ip_details' => array(
            'error' => '无法获取IP信息',
            '本机地址' => $ip  // 改为本机地址
        )
    );
}

// 处理API请求
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'myip':
        $requestedIP = getClientIP();
        break;
    case 'query':
        $requestedIP = $_GET['ip'] ?? '';
        break;
    default:
        echo json_encode(['error' => '无效的操作请求'], JSON_UNESCAPED_UNICODE);
        exit;
}

// 验证IP格式
if (!filter_var($requestedIP, FILTER_VALIDATE_IP)) {
    echo json_encode([
        'request_info' => null,
        'ip_details' => ['error' => '无效的IP地址']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取并返回IP信息
$ipInfo = getIPInfo($requestedIP);
echo json_encode($ipInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);