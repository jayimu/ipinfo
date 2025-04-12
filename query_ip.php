<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置响应头为JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

// 获取客户端IP
function getClientIP() {
    $headers = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    );
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $potential_ip) {
                $potential_ip = trim($potential_ip);
                // 验证是否为有效的IPv4地址
                if (filter_var($potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    // 检查是否为公网IP
                    if (filter_var($potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $potential_ip;
                    }
                }
            }
        }
    }
    
    // 如果没有找到公网IP，返回第一个有效的IP（可能是内网IP）
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $potential_ip) {
                $potential_ip = trim($potential_ip);
                if (filter_var($potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $potential_ip;
                }
            }
        }
    }
    
    return '';
}

// 获取本地网络接口信息
function getLocalIPs() {
    $interfaces = array();
    if (PHP_OS === 'Darwin') { // macOS
        $cmd = "ifconfig | grep inet | grep -v inet6 | awk '{print \$2}'";
    } else { // Linux
        $cmd = "ip -4 addr | grep inet | awk '{print \$2}' | cut -d/ -f1";
    }
    exec($cmd, $output);
    $localIPs = array();
    foreach ($output as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $localIPs[] = $line;
        }
    }
    return $localIPs;
}

// 获取IP地理位置信息
function getIPLocation($ip) {
    // 如果是内网IP，直接返回本地信息
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return array(
            'country' => '本地网络',
            'area' => '内部网络'
        );
    }

    // 尝试使用ip-api.com获取IP信息
    $curl = curl_init("http://ip-api.com/json/" . $ip . "?fields=status,message,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,isp,org,as,mobile,proxy,hosting&lang=zh-CN");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            return array(
                'country' => $data['country'] ?? '',
                'area' => ($data['regionName'] ?? '') . ' ' . ($data['city'] ?? '')
            );
        }
    }

    // 如果主要API失败，尝试备用API
    $curl = curl_init("https://ipapi.co/" . $ip . "/json/");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && !isset($data['error'])) {
            return array(
                'country' => $data['country_name'] ?? '',
                'area' => ($data['region'] ?? '') . ' ' . ($data['city'] ?? '')
            );
        }
    }

    return array(
        'country' => '未知',
        'area' => '未知'
    );
}

// 获取客户端IP
$clientIP = getClientIP();

// 获取本地网络接口信息
$localIPs = getLocalIPs();

// 获取IP地理位置信息
$location = getIPLocation($clientIP);

// 构建响应数据
$response = array(
    'client_ip' => $clientIP,
    'local_ips' => $localIPs,
    'location' => $location
);

// 输出JSON响应
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);