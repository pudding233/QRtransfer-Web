<?php
// 错误处理设置
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// 确保输出为JSON
header('Content-Type: application/json');

// 接收POST数据
$raw_post = file_get_contents('php://input');
$data = json_decode($raw_post, true);

// 记录解码后的数据
error_log("Decoded JSON data: " . print_r($data, true));

if (!$data) {
    $json_error = json_last_error_msg();
    error_log("JSON decode error: " . $json_error);
    echo json_encode([
        'success' => false,
        'message' => '无效的数据格式',
        'debug' => [
            'json_error' => $json_error,
            'raw_post' => substr($raw_post, 0, 1000)
        ]
    ]);
    exit;
}

// 验证数据包格式
if (!isset($data['header']) || !isset($data['encoding']) || !isset($data['payload'])) {
    error_log("Missing required fields in data packet");
    echo json_encode([
        'success' => false,
        'message' => '数据包格式错误',
        'debug' => [
            'missing_fields' => [
                'header' => !isset($data['header']),
                'encoding' => !isset($data['encoding']),
                'payload' => !isset($data['payload'])
            ]
        ]
    ]);
    exit;
}

// 生成QR码
try {
    // 确保临时目录存在
    if (!is_dir('temp')) {
        mkdir('temp', 0777, true);
    }

    // 生成唯一的文件名
    $filename = 'temp/qr_' . $data['header']['blockIndex'] . '_' . uniqid() . '.png';

    // 检查phpqrcode库
    if (!file_exists('lib/phpqrcode/qrlib.php')) {
        throw new Exception('QR码库文件不存在');
    }

    // 使用PHP QR Code库生成QR码
    require_once('lib/phpqrcode/qrlib.php');
    
    // 设置QR码参数
    $errorCorrectionLevel = 'L';
    $matrixPointSize = 8;
    
    // 生成QR码
    QRcode::png(json_encode($data), $filename, $errorCorrectionLevel, $matrixPointSize, 2);
    
    if (!file_exists($filename)) {
        throw new Exception('QR码文件生成失败');
    }
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'url' => $filename . '?t=' . time()
    ]);
} catch (Exception $e) {
    error_log("QR code generation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '生成QR码失败: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

// 图像处理类
class ImageProcessor {
    private $image;
    private $width;
    private $height;
    
    public function __construct($filename) {
        if (!extension_loaded('gd')) {
            throw new Exception('GD库未启用');
        }
        
        if (!file_exists($filename)) {
            throw new Exception('图像文件不存在: ' . $filename);
        }
        
        $this->image = @imagecreatefrompng($filename);
        if (!$this->image) {
            throw new Exception('无法加载图像文件: ' . error_get_last()['message']);
        }
        
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
        
        // 启用alpha通道
        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);
    }
    
    public function resize($newSize) {
        if ($newSize <= 0) {
            throw new Exception('无效的图像大小');
        }
        
        $newImage = imagecreatetruecolor($newSize, $newSize);
        if (!$newImage) {
            throw new Exception('无法创建新图像: ' . error_get_last()['message']);
        }
        
        // 设置透明背景
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        
        // 调整大小
        if (!imagecopyresampled(
            $newImage, $this->image,
            0, 0, 0, 0,
            $newSize, $newSize,
            $this->width, $this->height
        )) {
            imagedestroy($newImage);
            throw new Exception('调整图像大小失败: ' . error_get_last()['message']);
        }
        
        // 清理旧图像并更新引用
        imagedestroy($this->image);
        $this->image = $newImage;
        $this->width = $this->height = $newSize;
    }
    
    public function save($filename) {
        // 确保目标目录存在
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception('无法创建目标目录');
            }
        }
        
        // 保存图像
        if (!imagepng($this->image, $filename, 9)) { // 最高压缩质量
            throw new Exception('保存图像失败: ' . error_get_last()['message']);
        }
        
        // 验证保存的文件
        if (!file_exists($filename) || filesize($filename) === 0) {
            throw new Exception('保存的图像文件无效');
        }
    }
    
    public function __destruct() {
        if (is_resource($this->image)) {
            imagedestroy($this->image);
        }
    }
} 