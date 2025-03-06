<?php
// 错误处理设置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// 确保输出为JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('只支持POST请求');
    }

    if (!isset($_FILES['file'])) {
        throw new Exception('未上传文件');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败: ' . $file['error']);
    }

    // 创建临时目录
    $tempDir = 'temp/' . uniqid('upload_');
    if (!mkdir($tempDir, 0777, true)) {
        throw new Exception('无法创建临时目录');
    }

    // 读取文件内容
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        throw new Exception('无法读取文件内容');
    }

    // 计算块大小和数量
    $contentLength = strlen($content);
    $chunkSize = 512; // 减小到512字节，提高扫描成功率
    $chunks = ceil($contentLength / $chunkSize);

    // 分割数据并保存
    for ($i = 0; $i < $chunks; $i++) {
        $chunk = substr($content, $i * $chunkSize, $chunkSize);
        $chunkData = [
            'name' => $file['name'],
            'total_size' => $contentLength,
            'chunk_index' => $i,
            'total_chunks' => $chunks,
            'data' => base64_encode($chunk)
        ];

        $chunkFile = $tempDir . '/chunk_' . $i . '.json';
        if (file_put_contents($chunkFile, json_encode($chunkData)) === false) {
            throw new Exception('无法保存块数据');
        }
    }

    // 保存会话数据
    session_start();
    $_SESSION['file_info'] = [
        'name' => $file['name'],
        'size' => $contentLength,
        'chunks' => $chunks,
        'dir' => $tempDir
    ];

    echo json_encode([
        'success' => true,
        'chunks' => $chunks,
        'file_info' => [
            'name' => $file['name'],
            'size' => $contentLength
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 