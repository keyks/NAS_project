<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *"); // 允许跨域（本地环境安全）

error_reporting(0); // 禁用所有PHP原生错误输出
ini_set('display_errors', 0); // 关闭错误显示
ob_start(); // 开启输出缓冲，防止意外输出

// 定义根路径
define('ROOT_PATH', realpath(__DIR__ . '/../'));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');

// 确保上传目录存在并创建固定分类目录
$categories = ['文件', '图片', '视频','音乐', '压缩包', '其他'];
foreach ($categories as $cat) {
    $dir = UPLOAD_PATH . $cat;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// 创建临时分片目录
$tempRootDir = UPLOAD_PATH . 'temp/';
if (!is_dir($tempRootDir)) {
    mkdir($tempRootDir, 0777, true);
}

// 文件ID映射系统 - 安全存储文件路径映射
define('FILE_ID_MAP_PATH', UPLOAD_PATH . 'temp/.file_id_map.json');

/**
 * 获取或创建文件ID映射
 */
function getFileIdMap() {
    $mapFile = FILE_ID_MAP_PATH;
    if (file_exists($mapFile)) {
        $content = file_get_contents($mapFile);
        $map = json_decode($content, true);
        if (is_array($map)) {
            return $map;
        }
    }
    return [];
}

/**
 * 保存文件ID映射
 */
function saveFileIdMap($map) {
    $mapFile = FILE_ID_MAP_PATH;
    $mapDir = dirname($mapFile);
    if (!is_dir($mapDir)) {
        mkdir($mapDir, 0777, true);
    }
    file_put_contents($mapFile, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 根据文件路径生成唯一ID
 */
function generateFileId($filePath) {
    // 使用文件路径的哈希值 + 时间戳 + 随机数生成唯一ID
    $hash = md5($filePath . time() . mt_rand());
    return substr($hash, 0, 16); // 使用16位ID
}

/**
 * 获取文件的ID（如果不存在则创建）
 */
function getFileId($filePath) {
    $map = getFileIdMap();
    // 检查是否已存在该路径的ID
    foreach ($map as $id => $path) {
        if ($path === $filePath) {
            return $id;
        }
    }
    // 不存在则生成新ID
    $fileId = generateFileId($filePath);
    // 确保ID唯一
    while (isset($map[$fileId])) {
        $fileId = generateFileId($filePath);
    }
    $map[$fileId] = $filePath;
    saveFileIdMap($map);
    return $fileId;
}

/**
 * 根据ID获取文件路径
 */
function getFilePathById($fileId) {
    $map = getFileIdMap();
    return $map[$fileId] ?? null;
}

/**
 * 清理无效的ID映射（文件已不存在的映射）
 */
function cleanupFileIdMap() {
    $map = getFileIdMap();
    $cleaned = false;
    foreach ($map as $id => $path) {
        if (!file_exists($path)) {
            unset($map[$id]);
            $cleaned = true;
        }
    }
    if ($cleaned) {
        saveFileIdMap($map);
    }
}

// 处理请求
$action = $_GET['action'] ?? '';
$postData = [];
if (empty($action)) {
    $postData = json_decode(file_get_contents('php://input'), true);
    $action = $postData['action'] ?? '';
}

// 递归删除目录函数（提升作用域，避免重复定义）
function deleteDir($dir) {
    if (!is_dir($dir)) return false;
    
    // 先尝试删除目录中的所有文件，包括隐藏文件和锁文件
    $files = array_diff(scandir($dir), ['.', '..']);
    $allDeleted = true;
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            // 递归删除子目录
            if (!deleteDir($path)) {
                $allDeleted = false;
            }
        } else {
            // 删除文件，多次尝试
            $deleted = false;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (@unlink($path)) {
                    $deleted = true;
                    break;
                }
                // 尝试更改权限后删除
                @chmod($path, 0777);
                // Windows系统可能需要等待一下
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    usleep(100000); // 等待100毫秒
                }
            }
            if (!$deleted) {
                $allDeleted = false;
            }
        }
    }
    
    // 尝试删除目录本身（即使部分文件删除失败也尝试）
    @chmod($dir, 0777);
    $dirDeleted = false;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        if (@rmdir($dir)) {
            $dirDeleted = true;
            break;
        }
        // Windows系统可能需要等待一下
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            usleep(100000); // 等待100毫秒
        }
    }
    
    return $dirDeleted;
}

// 清理过期的临时目录
$cleanupFile = $tempRootDir . '.cleanup';
if (!file_exists($cleanupFile) || time() - filemtime($cleanupFile) > 86400) {
    // 清理24小时前的临时目录
    $dirs = glob($tempRootDir . '*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        // 跳过.cleanup文件本身
        if (basename($dir) === '.cleanup') {
            continue;
        }
        if (time() - filemtime($dir) > 86400) {
            // 先尝试删除锁文件
            $lockFile = $dir . '/.lock';
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }
            // 删除过期目录
            deleteDir($dir);
        }
    }
    touch($cleanupFile);
}

switch ($action) {
    case 'listFiles':
        listFiles($postData);
        break;
    case 'uploadFile':
        uploadFile();
        break;
    case 'uploadChunk':
        uploadChunk();
        break;
    case 'checkFileExists':
        checkFileExists($postData);
        break;
    case 'mergeChunks':
        mergeChunks($postData);
        break;
    case 'viewFile':
        viewFile($postData);
        break;
    case 'renameFile':
        renameFile($postData);
        break;
    case 'deleteFile':
        deleteFile($postData);
        break;
    case 'cleanupUpload':
        cleanupUpload($postData);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
        break;
}


/**
 * 列出文件
 */
function listFiles($data) {
    global $categories, $UPLOAD_PATH;
    $category = $data['category'] ?? 'all';
    $targetDate = $data['date'] ?? '';
    $keyword = $data['keyword'] ?? '';
    $sort = strtolower($data['sort'] ?? 'desc'); // asc | desc
    if (!in_array($sort, ['asc', 'desc'], true)) {
        $sort = 'desc';
    }
    $files = [];

    // 清理无效的ID映射
    cleanupFileIdMap();

    // 确定要扫描的目录
    $scanDirs = [];
    if ($category === 'all') {
        foreach ($categories as $cat) {
            $scanDirs[] = UPLOAD_PATH . $cat;
        }
    } elseif (in_array($category, $categories)) {
        $scanDirs[] = UPLOAD_PATH . $category;
    } else {
        echo json_encode(['success' => false, 'message' => '无效的分类']);
        return;
    }

    // 扫描目录
    foreach ($scanDirs as $dir) {
        if (!is_dir($dir)) continue;
        $dirIterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dirIterator);
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            
            $filePath = $file->getPathname();
            $fileName = $file->getFilename();
            $fileSize = $file->getSize();
            $fileMtime = $file->getMTime();
            $fileCategory = basename(dirname($filePath));

            // 日期筛选
            // if (!empty($targetDate)) {
            //     $fileDate = date('Y-m-d', $fileMtime);
            //     if ($fileDate !== $targetDate) continue;
            // }
            // 日期筛选 - 使用中国时区进行比较，与前端显示时间一致
            if (!empty($targetDate)) {
                // 设置为中国时区进行日期比较
                $originalTimezone = date_default_timezone_get();
                date_default_timezone_set('Asia/Shanghai');
                $fileDate = date('Y-m-d', $fileMtime);
                date_default_timezone_set($originalTimezone);
                
                if ($fileDate !== $targetDate) continue;
            }
            // 关键词筛选
            if (!empty($keyword) && stripos($fileName, $keyword) === false) {
                continue;
            }

            // 相对路径（用于后端内部使用，不返回给前端）
            $relativePath = str_replace(ROOT_PATH . '/', '', $filePath);
            
            // 获取或生成文件ID
            $fileId = getFileId($relativePath);

            $files[] = [
                'name' => $fileName,
                'fileId' => $fileId,  // 返回ID而不是path
                'size' => $fileSize,
                'mtime' => $fileMtime,
                'category' => $fileCategory
            ];
        }
    }

    // 按修改时间排序（最新在前）
    usort($files, function($a, $b) use ($sort) {
        if ($sort === 'asc') {
            return $a['mtime'] <=> $b['mtime'];
        }
        return $b['mtime'] <=> $a['mtime'];
    });

    echo json_encode(['success' => true, 'files' => $files]);
}
// 获取文件类型分类
function getFileTypes() {
    return [
        '图片' => ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tif', 'tiff', 
                   'psd', 'ai', 'eps', 'raw', 'cr2', 'nef', 'orf', 'sr2', 'heic', 'heif',
                   'ppm', 'pgm', 'pbm', 'pnm', 'webp', 'avif', 'jfif', 'pjpeg', 'pjp'],
        '视频' => ['mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'rmvb', 'rm',
                   'mpeg', 'mpg', 'm4v', '3gp', '3g2', 'ts', 'mts', 'm2ts', 'vob', 'ogv',
                   'f4v', 'asf', 'divx', 'xvid', 'mpe', 'mpv', 'mxf'],
        '音乐' => ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma', 'm4a', 'ape', 'acc', 'mid',
                   'midi', 'amr', 'awb', 'mp2', 'mp1', 'oga', 'opus', 'ra', 'ram',
                   'dts', 'ac3', 'wavpack', 'wv', 'aiff', 'aif', 'aifc', 'dsf', 'dff', 'tak'],
        '压缩包' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'z', 'lzma', 'cab',
                     'iso', 'img', 'dmg', 'tar.gz', 'tgz', 'tar.bz2', 'tbz2', 'tar.xz', 'txz',
                     'jar', 'war', 'ear', 'apk', 'ipa'],
        '文件' => ['txt', 'csv', 'json', 'xml', 'md', 'markdown', 'log', 'ini', 'conf', 'bat',
                   'sh', 'php', 'html', 'htm', 'css', 'js', 'py', 'java', 'c', 'cpp', 'h', 'hpp',
                   'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
                   'rtf', 'wps', 'et', 'dps', 'epub', 'mobi', 'azw', 'azw3', 'fb2'],
        '其他' => []
    ];
}
/**
 * 清理临时上传文件
 */
function cleanupUpload($data) {
    $fileId = $data['fileId'] ?? '';
    if (empty($fileId)) {
        echo json_encode(['success' => false, 'message' => '文件ID为空']);
        return;
    }
    
    $tempDir = UPLOAD_PATH . 'temp/' . $fileId . '/';
    
    // 先尝试删除锁文件（如果存在）
    $lockFile = $tempDir . '.lock';
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    
    if (is_dir($tempDir)) {
        $result = deleteDir($tempDir);
        if ($result) {
            echo json_encode(['success' => true, 'message' => '临时文件清理成功']);
        } else {
            // 即使删除失败，也尝试再次删除（可能文件已解锁）
            usleep(500000); // 等待0.5秒
            $result = deleteDir($tempDir);
            if ($result) {
                echo json_encode(['success' => true, 'message' => '临时文件清理成功（延迟清理）']);
            } else {
                echo json_encode(['success' => false, 'message' => '临时文件清理失败，部分文件可能被占用']);
            }
        }
    } else {
        echo json_encode(['success' => true, 'message' => '临时目录不存在，无需清理']);
    }
}
/**
 * 上传文件分片
 */
function uploadChunk() {
    // 检查必要参数
    if (!isset($_POST['fileId'], $_POST['chunkIndex'], $_POST['totalChunks'], $_FILES['chunk'])) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        return;
    }

    $fileId = $_POST['fileId'];
    $chunkIndex = (int)$_POST['chunkIndex'];
    $totalChunks = (int)$_POST['totalChunks'];
    
    // 检查分片大小是否为0
    $chunkSize = $_FILES['chunk']['size'];
    if ($chunkSize <= 0) {
        echo json_encode(['success' => false, 'message' => '分片文件为空', 'chunkIndex' => $chunkIndex]);
        return;
    }

    // 创建临时目录存储分片
    $tempDir = UPLOAD_PATH . 'temp/' . $fileId . '/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
        chmod($tempDir, 0777); // 确保权限
    }
    
    // 分片文件路径
    $chunkPath = $tempDir . $chunkIndex;
    
    // 移动上传的分片
    if (move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        echo json_encode([
            'success' => true,
            'message' => '分片上传成功',
            'chunkIndex' => $chunkIndex
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '分片保存失败',
            'chunkIndex' => $chunkIndex
        ]);
    }
}
/**
 * 检查文件是否已存在（根据文件名和大小判断）
 */
function checkFileExists($data) {
    $fileName = $data['fileName'] ?? '';
    $fileSize = $data['fileSize'] ?? 0;
    
    if (empty($fileName) || $fileSize <= 0) {
        echo json_encode(['success' => false, 'message' => '参数不完整']);
        return;
    }
    
    // 遍历上传目录查找相同文件（名称和大小均相同）
    $exists = false;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_PATH, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $fileName && $file->getSize() == $fileSize) {
            $exists = true;
            break;
        }
    }
    
    echo json_encode(['success' => true, 'exists' => $exists]);
}
/**
 * 合并文件分片
 */
function mergeChunks($data) {
    $fileId = $data['fileId'] ?? '';
    $fileName = $data['fileName'] ?? '';
    $totalChunks = $data['totalChunks'] ?? 0;

    // 先校验核心参数，避免后续变量未定义
    if (empty($fileId) || empty($fileName) || $totalChunks <= 0) {
        echo json_encode(['success' => false, 'message' => '合并参数不完整']);
        return;
    }
    
    // 定义临时目录（修复变量未定义问题）
    $tempDir = UPLOAD_PATH . 'temp/' . $fileId . '/';
    
    // 加锁防止并发合并
    $lockFile = $tempDir . '.lock';
    // 确保锁文件目录存在
    if (!is_dir($tempDir)) {
        echo json_encode(['success' => false, 'message' => '分片目录不存在']);
        return;
    }
    $lockHandle = fopen($lockFile, 'w');
    if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        echo json_encode(['success' => false, 'message' => '文件正在合并中']);
        if ($lockHandle) fclose($lockHandle);
        return;
    }
    
    // 安全过滤文件名
    $fileName = preg_replace('/[^\p{L}\p{N}\.\-_ ]/u', '', $fileName);
    if (empty($fileName)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        unlink($lockFile);
        echo json_encode(['success' => false, 'message' => '无效的文件名']);
        return;
    }
    
    // 确定文件分类
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileTypes = getFileTypes();
    
    $category = '其他';
    foreach ($fileTypes as $cat => $exts) {
        if (in_array($ext, $exts)) {
            $category = $cat;
            break;
        }
    }
    
    // 目标文件路径
    $targetDir = UPLOAD_PATH . $category;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
        chmod($targetDir, 0777); // 强制赋权
    }
    $targetPath = $targetDir . '/' . $fileName;
    
    // 处理文件名重复
    $i = 1;
    while (file_exists($targetPath)) {
        $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        $targetPath = $targetDir . '/' . $fileNameWithoutExt . '(' . $i . ').' . $ext;
        $i++;
    }
    
    // 合并分片
    try {
        // 合并分片
        $output = fopen($targetPath, 'wb');
        if (!$output) {
            throw new Exception('无法创建目标文件');
        }
        
        $success = true;
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $tempDir . $i;
            if (!file_exists($chunkPath)) {
                throw new Exception('分片缺失：' . $i);
            }
            
            $input = fopen($chunkPath, 'rb');
            if (!$input) {
                throw new Exception('无法打开分片：' . $i);
            }
            
            // 合并分片内容
            while ($buffer = fread($input, 8192)) {
                fwrite($output, $buffer);
            }
            
            fclose($input);
            unlink($chunkPath); // 删除已合并的分片
        }
        
        fclose($output);
        
        // 生成文件ID并保存映射（文件合并成功后）
        $relativePath = str_replace(ROOT_PATH . '/', '', $targetPath);
        getFileId($relativePath); // 自动生成并保存ID映射
        
        // 先释放锁并删除锁文件，再删除临时目录
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        @unlink($lockFile); // 删除锁文件
        
        // 删除临时目录（确保锁文件已删除）
        $deleteResult = deleteDir($tempDir);
        if (!$deleteResult) {
            // 如果删除失败，记录但不影响合并成功
            error_log("警告：临时目录删除失败: $tempDir");
        }

        echo json_encode(['success' => true, 'message' => '文件合并成功']);
    } catch (Exception $e) {
        // 异常处理：清理资源 + 返回JSON错误
        if (isset($output) && is_resource($output)) {
            fclose($output);
        }
        if (isset($lockHandle) && is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
        if (file_exists($targetPath)) {
            unlink($targetPath); // 删除不完整文件
        }
        echo json_encode([
            'success' => false,
            'message' => '合并失败：' . $e->getMessage()
        ]);
    }
}

/**
 * 上传文件
 */
function uploadFile() {
    // 检查是否为POST请求且包含文件信息
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['CONTENT_LENGTH'])) {
        echo json_encode(['success' => false, 'message' => '无效的上传请求']);
        return;
    }

    // 获取原始文件名（从请求头获取）
    $fileName = $_SERVER['HTTP_X_FILE_NAME'] ?? '';
    $fileName = urldecode($fileName);
    if (empty($fileName)) {
        echo json_encode(['success' => false, 'message' => '未获取到文件名']);
        return;
    }

    // 安全过滤文件名
    $fileName = preg_replace('/[^\p{L}\p{N}\.\-_ ]/u', '', $fileName);
    if (empty($fileName)) {
        echo json_encode(['success' => false, 'message' => '无效的文件名']);
        return;
    }
    
    // 定义文件类型分类
    $fileTypes = getFileTypes();

    // 获取文件扩展名（小写）
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $category = '其他';
    foreach ($fileTypes as $cat => $exts) {
        if (in_array($ext, $exts)) {
            $category = $cat;
            break;
        }
    }

    // 准备目标路径
    $targetDir = UPLOAD_PATH . $category;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
        chmod($targetDir, 0777); // 强制赋权
    }
    $targetPath = $targetDir . '/' . $fileName;

    // 处理文件名重复
    $i = 1;
    while (file_exists($targetPath)) {
        $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        $targetPath = $targetDir . '/' . $fileNameWithoutExt . '(' . $i . ').' . $ext;
        $i++;
    }

    // 流式写入文件
    $input = fopen('php://input', 'rb');
    $output = fopen($targetPath, 'wb');
    
    if (!$input || !$output) {
        echo json_encode(['success' => false, 'message' => '无法打开文件流']);
        return;
    }

    $success = false;
    $bufferSize = 8192; // 8KB缓冲区
    $bytesWritten = 0;
    $totalBytes = (int)$_SERVER['CONTENT_LENGTH'];

    // 流式读取并写入
    while (!feof($input) && $bytesWritten < $totalBytes) {
        $buffer = fread($input, $bufferSize);
        // 检查读取错误
        if ($buffer === false || $buffer === '') {
            break;
        }
        // 写入并检查结果
        $written = fwrite($output, $buffer);
        if ($written === false || $written === 0) {
            break;
        }
        $bytesWritten += $written;
    }

    // 关闭流
    fclose($input);
    fclose($output);
    $errorMessages = [];
    // 验证是否完整写入
    if ($bytesWritten === $totalBytes) {
        $success = true;
        $successCount = 1;
        $failCount = 0;
        // 生成文件ID并保存映射（文件上传成功后）
        $relativePath = str_replace(ROOT_PATH . '/', '', $targetPath);
        getFileId($relativePath); // 自动生成并保存ID映射
    } else {
        // 写入不完整，删除文件
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        $success = false;
        $successCount = 0;
        $failCount = 1;
        $errorMessages[] = "文件 $fileName 上传中断，已清理不完整文件（已写入 $bytesWritten / $totalBytes 字节）";
    }

    $response = [
        'success' => $success,
        'successCount' => $successCount,
        'failCount' => $failCount
    ];
    if (!empty($errorMessages)) {
        $response['message'] = implode('; ', $errorMessages);
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * 查看文件
 */
function viewFile($data) {
    $fileId = $data['fileId'] ?? '';
    if (empty($fileId)) {
        echo json_encode(['success' => false, 'message' => '文件ID为空']);
        return;
    }

    // 根据ID获取文件路径
    $relativePath = getFilePathById($fileId);
    if (empty($relativePath)) {
        echo json_encode(['success' => false, 'message' => '无效的文件ID']);
        return;
    }

    $filePath = ROOT_PATH . '/' . $relativePath;

    // 安全检查：确保文件在上传目录内
    if (!file_exists($filePath) || !is_file($filePath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        return;
    }
    if (!str_starts_with(realpath($filePath), realpath(UPLOAD_PATH))) {
        echo json_encode(['success' => false, 'message' => '非法文件路径']);
        return;
    }

    // 对于可预览的文本文件，返回内容；其他文件返回访问路径
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $textExts = ['txt', 'html', 'css', 'js', 'php', 'json', 'xml', 'md', 'csv'];

    if (in_array($ext, $textExts)) {
        $content = file_get_contents($filePath);
        echo json_encode(['success' => true, 'content' => $content]);
    } else {
        // 返回可访问的URL路径
        $url = '/uploads/' . str_replace(UPLOAD_PATH, '', $filePath);
        echo json_encode(['success' => true, 'content' => $url]);
    }
}

/**
 * 重命名文件
 */
function renameFile($data) {
    $fileId = $data['fileId'] ?? '';
    $newName = $data['newName'] ?? '';

    if (empty($fileId) || empty($newName)) {
        echo json_encode(['success' => false, 'message' => '文件ID或新文件名不能为空']);
        return;
    }

    // 根据ID获取文件路径
    $relativePath = getFilePathById($fileId);
    if (empty($relativePath)) {
        echo json_encode(['success' => false, 'message' => '无效的文件ID']);
        return;
    }

    $oldPath = ROOT_PATH . '/' . $relativePath;
    $newDir = dirname($oldPath);
    $newPath = $newDir . '/' . $newName;

    // 安全检查
    if (!file_exists($oldPath) || !is_file($oldPath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        return;
    }
    if (!str_starts_with(realpath($oldPath), realpath(UPLOAD_PATH))) {
        echo json_encode(['success' => false, 'message' => '非法文件路径']);
        return;
    }
    if (file_exists($newPath)) {
        echo json_encode(['success' => false, 'message' => '新文件名已存在']);
        return;
    }

    if (rename($oldPath, $newPath)) {
        // 更新ID映射中的路径
        $map = getFileIdMap();
        if (isset($map[$fileId])) {
            $newRelativePath = str_replace(ROOT_PATH . '/', '', $newPath);
            $map[$fileId] = $newRelativePath;
            saveFileIdMap($map);
        }
        echo json_encode(['success' => true, 'message' => '重命名成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '重命名失败，请检查权限']);
    }
}

/**
 * 删除文件
 */
function deleteFile($data) {
    $fileId = $data['fileId'] ?? '';
    if (empty($fileId)) {
        echo json_encode(['success' => false, 'message' => '文件ID为空']);
        return;
    }

    // 根据ID获取文件路径
    $relativePath = getFilePathById($fileId);
    if (empty($relativePath)) {
        echo json_encode(['success' => false, 'message' => '无效的文件ID']);
        return;
    }

    $filePath = ROOT_PATH . '/' . $relativePath;

    // 安全检查
    if (!file_exists($filePath) || !is_file($filePath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        return;
    }
    if (!str_starts_with(realpath($filePath), realpath(UPLOAD_PATH))) {
        echo json_encode(['success' => false, 'message' => '非法文件路径']);
        return;
    }

    // 获取文件信息，用于查找可能的临时目录
    $fileName = basename($filePath);
    $fileSize = filesize($filePath);
    
    // 删除文件
    if (unlink($filePath)) {
        // 从ID映射中删除该文件
        $map = getFileIdMap();
        if (isset($map[$fileId])) {
            unset($map[$fileId]);
            saveFileIdMap($map);
        }
        
        // 尝试清理可能残留的临时目录（根据文件名和大小生成可能的fileHash）
        // fileHash格式: fileName-fileSize-lastModified
        // 由于我们不知道lastModified，尝试查找所有可能的临时目录
        $tempRootDir = UPLOAD_PATH . 'temp/';
        if (is_dir($tempRootDir)) {
            // 查找所有以文件名开头的临时目录
            $pattern = $tempRootDir . $fileName . '-*';
            $dirs = glob($pattern, GLOB_ONLYDIR);
            foreach ($dirs as $tempDir) {
                // 尝试删除临时目录
                deleteDir($tempDir);
            }
        }
        
        echo json_encode(['success' => true, 'message' => '删除成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '删除失败，请检查权限']);
    }
}
?>