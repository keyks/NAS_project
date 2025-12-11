<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// 自动检测操作系统并选择合适的根目录
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows系统 - 使用当前脚本所在磁盘
    $scriptPath = realpath(__FILE__);
    $drive = substr($scriptPath, 0, 2); // 获取类似 "C:" 这样的磁盘根目录
} else {
    // 安卓手机系统 - 使用根目录
    $drive = '/storage/emulated/0/';
}

// 获取存储信息
$total = disk_total_space($drive);
$free = disk_free_space($drive);
$used = $total - $free;

if ($total === false || $free === false) {
    echo json_encode([
        'success' => false,
        'message' => '获取磁盘信息失败，请检查权限或存储设备是否存在'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percent' => round(($used / $total) * 100, 1)
    ]);
}
?>