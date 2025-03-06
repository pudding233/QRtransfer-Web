<?php
session_start();

if (!isset($_SESSION['received_chunks'])) {
    $_SESSION['received_chunks'] = [];
}

function initializeNewFile() {
    $_SESSION['received_chunks'] = [];
    $_SESSION['file_info'] = null;
}

function processChunk($data) {
    $chunk = json_decode($data, true);
    if (!$chunk) {
        return ['success' => false, 'message' => '无效的二维码数据'];
    }

    // 存储块数据
    $_SESSION['received_chunks'][$chunk['chunk_index']] = $chunk;

    // 更新文件信息
    if (!isset($_SESSION['file_info'])) {
        $_SESSION['file_info'] = [
            'name' => $chunk['file_name'],
            'total_chunks' => $chunk['total_chunks'],
        ];
    }

    // 检查是否所有块都已接收
    $received = count($_SESSION['received_chunks']);
    $total = $_SESSION['file_info']['total_chunks'];
    
    return [
        'success' => true,
        'received' => $received,
        'total' => $total,
        'is_complete' => $received == $total
    ];
}

function reconstructFile() {
    if (!isset($_SESSION['received_chunks']) || !isset($_SESSION['file_info'])) {
        return false;
    }

    $chunks = $_SESSION['received_chunks'];
    $fileInfo = $_SESSION['file_info'];
    
    // 检查是否所有块都已接收
    if (count($chunks) != $fileInfo['total_chunks']) {
        return false;
    }

    // 按顺序重建文件
    $fileContent = '';
    ksort($chunks);
    foreach ($chunks as $chunk) {
        $fileContent .= base64_decode($chunk['data']);
    }

    // 保存文件
    $outputDir = 'received_files';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    $outputPath = $outputDir . '/' . $fileInfo['name'];
    file_put_contents($outputPath, $fileContent);

    // 清理会话数据
    initializeNewFile();

    return $outputPath;
}

// 处理 AJAX 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'process_chunk':
                if (isset($_POST['data'])) {
                    echo json_encode(processChunk($_POST['data']));
                }
                break;
                
            case 'reconstruct':
                $result = reconstructFile();
                echo json_encode([
                    'success' => (bool)$result,
                    'file_path' => $result
                ]);
                break;
                
            case 'reset':
                initializeNewFile();
                echo json_encode(['success' => true]);
                break;
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>文件接收器</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            text-align: center;
        }
        #video {
            width: 100%;
            max-width: 640px;
            margin: 20px auto;
            background: #f0f0f0;
        }
        .progress {
            margin: 20px 0;
        }
        .buttons {
            margin: 20px 0;
        }
        .status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        .success { background-color: #dff0d8; }
        .error { background-color: #f2dede; }
        #browserInfo {
            margin: 10px 0;
            padding: 10px;
            background-color: #fff3cd;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>文件接收器</h1>
        
        <div id="browserInfo"></div>
        
        <div class="buttons">
            <button onclick="startScanning()" id="startButton">开始扫描</button>
            <button onclick="stopScanning()" id="stopButton" disabled>停止扫描</button>
            <button onclick="resetReceiver()" id="resetButton">重置</button>
        </div>

        <video id="video" playsinline></video>
        
        <div class="progress">
            接收进度: <span id="progress">0/0</span> 块
        </div>

        <div id="status"></div>
    </div>

    <script src="https://unpkg.com/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
        let video;
        let canvasElement;
        let canvas;
        let scanning = false;

        // 检查浏览器兼容性
        function checkBrowserSupport() {
            const browserInfo = document.getElementById('browserInfo');
            
            // 检查是否在HTTPS或localhost环境
            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                browserInfo.style.display = 'block';
                browserInfo.textContent = '警告：摄像头访问需要HTTPS连接或localhost环境！';
                return false;
            }

            // 检查getUserMedia支持
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                // 尝试使用老版本API
                navigator.getUserMedia = navigator.getUserMedia ||
                                      navigator.webkitGetUserMedia ||
                                      navigator.mozGetUserMedia ||
                                      navigator.msGetUserMedia;
                
                if (!navigator.getUserMedia) {
                    browserInfo.style.display = 'block';
                    browserInfo.textContent = '错误：您的浏览器不支持摄像头访问！请使用最新版本的Chrome、Firefox或Edge浏览器。';
                    return false;
                }
            }
            
            return true;
        }

        async function startScanning() {
            if (!checkBrowserSupport()) {
                showStatus('浏览器不支持摄像头访问', true);
                return;
            }

            video = document.getElementById('video');
            canvasElement = document.createElement('canvas');
            canvas = canvasElement.getContext('2d');

            try {
                // 先尝试后置摄像头
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' }
                    }
                }).catch(async () => {
                    // 如果后置摄像头失败，尝试任何可用摄像头
                    return await navigator.mediaDevices.getUserMedia({
                        video: true
                    });
                });

                video.srcObject = stream;
                video.setAttribute("playsinline", true); // 适配iOS
                video.play();
                requestAnimationFrame(tick);
                scanning = true;
                
                // 更新按钮状态
                document.getElementById('startButton').disabled = true;
                document.getElementById('stopButton').disabled = false;
                
                showStatus('摄像头已启动，请对准二维码', false);
            } catch (error) {
                showStatus('无法访问摄像头: ' + error.message, true);
                console.error('摄像头访问错误:', error);
            }
        }

        function stopScanning() {
            if (video && video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
                scanning = false;
                
                // 更新按钮状态
                document.getElementById('startButton').disabled = false;
                document.getElementById('stopButton').disabled = true;
                
                showStatus('扫描已停止', false);
            }
        }

        function tick() {
            if (!scanning) return;

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvasElement.height = video.videoHeight;
                canvasElement.width = video.videoWidth;
                canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
                const imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
                
                try {
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });

                    if (code) {
                        processQRCode(code.data);
                    }
                } catch (error) {
                    console.error('QR码处理错误:', error);
                }
            }
            requestAnimationFrame(tick);
        }

        async function processQRCode(data) {
            try {
                const response = await fetch('receiver.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=process_chunk&data=${encodeURIComponent(data)}`
                });
                
                const result = await response.json();
                if (result.success) {
                    document.getElementById('progress').textContent = `${result.received}/${result.total}`;
                    
                    if (result.is_complete) {
                        reconstructFile();
                    }
                }
            } catch (error) {
                showStatus('处理数据时出错: ' + error.message, true);
                console.error('数据处理错误:', error);
            }
        }

        async function reconstructFile() {
            try {
                const response = await fetch('receiver.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reconstruct'
                });
                
                const result = await response.json();
                if (result.success) {
                    showStatus(`文件重建成功！保存在: ${result.file_path}`, false);
                    stopScanning();
                }
            } catch (error) {
                showStatus('重建文件时出错: ' + error.message, true);
                console.error('文件重建错误:', error);
            }
        }

        async function resetReceiver() {
            try {
                await fetch('receiver.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reset'
                });
                
                document.getElementById('progress').textContent = '0/0';
                document.getElementById('status').innerHTML = '';
                stopScanning();
                showStatus('系统已重置', false);
            } catch (error) {
                showStatus('重置时出错: ' + error.message, true);
                console.error('重置错误:', error);
            }
        }

        function showStatus(message, isError) {
            const statusDiv = document.getElementById('status');
            statusDiv.className = 'status ' + (isError ? 'error' : 'success');
            statusDiv.textContent = message;
        }

        // 页面加载时检查浏览器兼容性
        window.addEventListener('load', checkBrowserSupport);
    </script>
</body>
</html> 