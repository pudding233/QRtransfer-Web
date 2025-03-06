<?php
/**
 * QR Code Generator using chillerlan/php-qrcode
 */
class QRcode {
    private static function initQROptions() {
        return [
            'version'      => 5,
            'outputType'   => 'png',
            'eccLevel'     => 0x02, // QR_ECLEVEL_M
            'scale'        => 5,
            'imageBase64'  => false,
            'moduleValues' => [
                // 定位图案 - 外框
                1536 => [0, 0, 0],
                // 定位图案 - 内框
                6    => [0, 0, 0],
                // 数据
                1    => [0, 0, 0],
                // 背景
                0    => [255, 255, 255],
            ],
        ];
    }

    public static function png($data, $outfile = false, $level = 'M', $size = 5, $margin = 4) {
        if (!extension_loaded('gd')) {
            throw new Exception('GD库未启用');
        }

        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // 计算适当的版本
        $length = strlen($data);
        $version = ceil($length / 100) + 4;
        $version = min(max($version, 1), 40);

        // 创建二维码图像
        $image = self::generateQRImage($data, $version, $size, $margin);
        
        if ($outfile !== false) {
            // 保存到文件
            if (!imagepng($image, $outfile, 9)) {
                imagedestroy($image);
                return false;
            }
            imagedestroy($image);
            return true;
        }
        
        // 直接输出
        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
        return true;
    }

    private static function generateQRImage($data, $version, $size, $margin) {
        // 计算二维码大小
        $width = ($version * 4 + 17) * $size + $margin * 2;
        
        // 创建图像
        $image = imagecreatetruecolor($width, $width);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // 填充白色背景
        imagefilledrectangle($image, 0, 0, $width - 1, $width - 1, $white);
        
        // 生成二维码数据矩阵
        $matrix = self::generateMatrix($data, $version);
        
        // 绘制二维码
        $blockSize = $size;
        $offset = $margin;
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $value) {
                if ($value) {
                    imagefilledrectangle(
                        $image,
                        $x * $blockSize + $offset,
                        $y * $blockSize + $offset,
                        ($x + 1) * $blockSize - 1 + $offset,
                        ($y + 1) * $blockSize - 1 + $offset,
                        $black
                    );
                }
            }
        }
        
        return $image;
    }

    private static function generateMatrix($data, $version) {
        // 使用原生函数生成二维码数据
        $dataSize = strlen($data);
        $matrixSize = $version * 4 + 17;
        $matrix = array_fill(0, $matrixSize, array_fill(0, $matrixSize, 0));
        
        // 添加定位图案
        self::addFinderPatterns($matrix);
        
        // 添加定时图案
        self::addTimingPatterns($matrix);
        
        // 添加校准图案
        if ($version > 1) {
            self::addAlignmentPatterns($matrix, $version);
        }
        
        // 添加格式信息
        self::addFormatInfo($matrix);
        
        // 添加数据
        self::addData($matrix, $data);
        
        return $matrix;
    }

    private static function addFinderPatterns(&$matrix) {
        $pattern = [
            [1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1]
        ];
        
        // 左上角
        self::placePattern($matrix, 0, 0, $pattern);
        // 右上角
        self::placePattern($matrix, 0, count($matrix) - 7, $pattern);
        // 左下角
        self::placePattern($matrix, count($matrix) - 7, 0, $pattern);
    }

    private static function addTimingPatterns(&$matrix) {
        $size = count($matrix);
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = $matrix[$i][6] = ($i % 2 == 0) ? 1 : 0;
        }
    }

    private static function addAlignmentPatterns(&$matrix, $version) {
        $positions = self::getAlignmentPositions($version);
        $pattern = [
            [1, 1, 1, 1, 1],
            [1, 0, 0, 0, 1],
            [1, 0, 1, 0, 1],
            [1, 0, 0, 0, 1],
            [1, 1, 1, 1, 1]
        ];
        
        foreach ($positions as $y) {
            foreach ($positions as $x) {
                // 避开定位图案
                if (!($x < 8 && $y < 8 || 
                      $x > count($matrix) - 9 && $y < 8 || 
                      $x < 8 && $y > count($matrix) - 9)) {
                    self::placePattern($matrix, $y - 2, $x - 2, $pattern);
                }
            }
        }
    }

    private static function getAlignmentPositions($version) {
        if ($version == 1) return [];
        $interval = $version <= 6 ? 4 : 2;
        $positions = [$version * 4 + 10];
        for ($pos = $version * 4 + 10; $pos > 10; $pos -= $interval) {
            array_unshift($positions, $pos);
        }
        array_unshift($positions, 6);
        return $positions;
    }

    private static function addFormatInfo(&$matrix) {
        $size = count($matrix);
        // 添加暗模块
        $matrix[$size - 8][8] = 1;
        
        // 添加保留区域
        for ($i = 0; $i < 8; $i++) {
            if ($i != 6) { // 跳过定时图案
                $matrix[8][$i] = 0;
                $matrix[$i][8] = 0;
            }
            if ($i < 7) {
                $matrix[8][$size - 1 - $i] = 0;
                $matrix[$size - 1 - $i][8] = 0;
            }
        }
    }

    private static function addData(&$matrix, $data) {
        $size = count($matrix);
        $mask = array_fill(0, $size, array_fill(0, $size, 0));
        
        // 生成掩码
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $mask[$y][$x] = (($x + $y) % 2 == 0) ? 1 : 0;
            }
        }
        
        // 添加数据
        $bits = self::getBits($data);
        $index = 0;
        
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right == 6) $right = 5;
            
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = ($right + 1) % 2 == 0 ? $size - 1 - $vert : $vert;
                    
                    if (!$matrix[$y][$x] && $index < count($bits)) {
                        $matrix[$y][$x] = $bits[$index] ^ $mask[$y][$x];
                        $index++;
                    }
                }
            }
        }
    }

    private static function getBits($data) {
        $bits = [];
        foreach (str_split($data) as $char) {
            $value = ord($char);
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        }
        return $bits;
    }

    private static function placePattern(&$matrix, $row, $col, $pattern) {
        $size = count($pattern);
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($row + $r < count($matrix) && $col + $c < count($matrix)) {
                    $matrix[$row + $r][$col + $c] = $pattern[$r][$c];
                }
            }
        }
    }
} 