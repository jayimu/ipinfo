<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP信息查询系统</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-4xl font-bold text-center text-gray-800 mb-8">
            <i class="fas fa-globe mr-2"></i>IP信息查询系统
        </h1>
        
        <!-- 当前IP信息卡片 -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-semibold text-gray-800">
                    <i class="fas fa-user-shield mr-2"></i>您的IP信息
                </h2>
                <button onclick="refreshMyIP()" class="text-blue-500 hover:text-blue-600">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div id="myIpInfo" class="space-y-4">
                <div class="animate-pulse flex space-x-4">
                    <div class="flex-1 space-y-4 py-1">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="space-y-2">
                            <div class="h-4 bg-gray-200 rounded"></div>
                            <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- IP查询卡片 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-search mr-2"></i>查询其他IP
            </h2>
            <div class="text-sm text-gray-600 mb-4">
                <i class="fas fa-info-circle mr-1"></i>
                每分钟最多查询10次
            </div>
            <div class="flex space-x-4 mb-4">
                <div class="flex-1 relative">
                    <input type="text" id="ipInput" 
                           class="w-full border rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="输入IP地址"
                           onkeypress="if(event.keyCode === 13) queryIP()">
                    <span class="absolute right-3 top-2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
                <button onclick="queryIP()"
                        class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center">
                    <i class="fas fa-search mr-2"></i>查询
                </button>
            </div>
            <div id="ipQueryResult" class="hidden">
                <h3 class="text-xl font-semibold mb-2 text-gray-800">查询结果</h3>
                <pre id="queryResult" class="bg-gray-100 p-4 rounded-lg overflow-x-auto text-sm"></pre>
            </div>
        </div>

        <!-- 命令行提示 -->
        <div class="mt-8 text-center">
            <div class="inline-block bg-gray-800 text-gray-200 rounded-lg p-4 text-sm font-mono">
                <i class="fas fa-terminal mr-2"></i>
                curl ip.m0rk.top
            </div>
        </div>
    </div>

    <script>
        const API_BASE = 'https://ip.m0rk.top';

        function refreshMyIP() {
            fetchMyIP();
        }

        function fetchMyIP() {
            document.getElementById('myIpInfo').innerHTML = `
                <div class="animate-pulse flex space-x-4">
                    <div class="flex-1 space-y-4 py-1">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="space-y-2">
                            <div class="h-4 bg-gray-200 rounded"></div>
                            <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                        </div>
                    </div>
                </div>`;

            fetch('/api.php?action=myip')
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.request_info || !data.ip_details) {
                        throw new Error('数据结构不完整');
                    }

                    if (data.ip_details.error) {
                        document.getElementById('myIpInfo').innerHTML = 
                            `<p class="text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>${data.ip_details.error}</p>`;
                        return;
                    }

                    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                    
                    // 请求信息部分
                    if (data.request_info && typeof data.request_info === 'object') {
                        html += '<div class="space-y-2">';
                        html += '<h3 class="font-semibold text-gray-700 border-b pb-2">请求信息</h3>';
                        for (const [key, value] of Object.entries(data.request_info)) {
                            if (value !== null && value !== undefined && value !== '') {
                                html += `<div class="grid grid-cols-3 gap-2">
                                    <span class="font-medium text-gray-600">${key}:</span>
                                    <span class="col-span-2 text-gray-800">${value}</span>
                                </div>`;
                            }
                        }
                        html += '</div>';
                    }
                    
                    // IP详细信息部分
                    if (data.ip_details && typeof data.ip_details === 'object') {
                        html += '<div class="space-y-2">';
                        html += '<h3 class="font-semibold text-gray-700 border-b pb-2">IP详细信息</h3>';
                        for (const [key, value] of Object.entries(data.ip_details)) {
                            if (key !== 'error' && value !== null && value !== undefined && value !== '') {
                                if (Array.isArray(value)) {
                                    html += `<div class="grid grid-cols-3 gap-2">
                                        <span class="font-medium text-gray-600">${key}:</span>
                                        <span class="col-span-2 text-gray-800">${value.join(', ')}</span>
                                    </div>`;
                                } else {
                                    html += `<div class="grid grid-cols-3 gap-2">
                                        <span class="font-medium text-gray-600">${key}:</span>
                                        <span class="col-span-2 text-gray-800">${value}</span>
                                    </div>`;
                                }
                            }
                        }
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    document.getElementById('myIpInfo').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('myIpInfo').innerHTML = 
                        `<p class="text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>${error.message}</p>`;
                });
        }

        function queryIP() {
            const ip = document.getElementById('ipInput').value.trim();
            if (!ip) {
                alert('请输入IP地址');
                return;
            }

            // 验证IPv4或IPv6格式
            const ipv4Regex = /^(\d{1,3}\.){3}\d{1,3}$/;
            const ipv6Regex = /^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$|^::$|^::1$|^([0-9a-fA-F]{1,4}:){1,7}:$|^([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}$|^([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}$|^([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}$|^([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}$|^([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}$|^[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})$|^:((:[0-9a-fA-F]{1,4}){1,7}|:)$/;

            if (!ipv4Regex.test(ip) && !ipv6Regex.test(ip)) {
                alert('请输入有效的IPv4或IPv6地址');
                return;
            }

            // 如果是IPv4，验证每个数字是否在0-255范围内
            if (ipv4Regex.test(ip)) {
                const parts = ip.split('.');
                for (let part of parts) {
                    const num = parseInt(part);
                    if (num < 0 || num > 255) {
                        alert('IPv4地址的每个数字必须在0-255之间');
                        return;
                    }
                }
            }

            document.getElementById('ipQueryResult').classList.remove('hidden');
            document.getElementById('queryResult').innerHTML = `
                <div class="animate-pulse flex space-x-4">
                    <div class="flex-1 space-y-4 py-1">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="space-y-2">
                            <div class="h-4 bg-gray-200 rounded"></div>
                            <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                        </div>
                    </div>
                </div>`;

            fetch(`/api.php?action=query&ip=${encodeURIComponent(ip)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.ip_details && data.ip_details.error) {
                        document.getElementById('queryResult').innerHTML = 
                            `<p class="text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>${data.ip_details.error}</p>`;
                        return;
                    }
                    
                    let html = '<div class="space-y-4">';
                    
                    // IP详细信息
                    if (data.ip_details && typeof data.ip_details === 'object') {
                        for (const [key, value] of Object.entries(data.ip_details)) {
                            if (key !== 'error' && value !== null && value !== undefined && value !== '') {
                                html += `<div class="grid grid-cols-3 gap-2">
                                    <span class="font-medium text-gray-600">${key}:</span>
                                    <span class="col-span-2 text-gray-800">${value}</span>
                                </div>`;
                            }
                        }
                    }
                    
                    html += '</div>';
                    document.getElementById('queryResult').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('queryResult').innerHTML = 
                        `<p class="text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>${error.message}</p>`;
                });
        }

        // 页面加载时获取IP信息
        document.addEventListener('DOMContentLoaded', fetchMyIP);
    </script>
</body>
</html>