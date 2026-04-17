<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// 自动检测操作系统并选择合适的根目录
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows系统 - 使用当前脚本所在磁盘
    $scriptPath = realpath(__FILE__);
    $drive = substr($scriptPath, 0, 2); // 获取类似 "C:" 这样的磁盘根目录
} elseif(is_dir('/storage/emulated/0/')) {
    // 安卓手机系统 - 使用根目录
    $drive = '/storage/emulated/0/';
} else {
    //普通Linux系统 - 使用当前脚本所在目录
    $drive = realpath(__DIR__ . '/../../');
}

// 获取存储信息
$total = disk_total_space($drive);
$free = disk_free_space($drive);

// 如果获取失败，尝试使用当前脚本所在目录作为备选目录
if ($total === false || $free === false || $total <= 0) {
    $fallback = realpath(__DIR__ . '/../../');
    $total = disk_total_space($fallback);
    $free = disk_free_space($fallback);
}

$used = $total - $free;

if ($total === false || $free === false || $total <= 0) {
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