<!DOCTYPE html>
<html>
<head>
    <title>文件传输二维码生成器 2.0</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            text-align: center;
            max-width: 1200px;
        }
        #qrcode {
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            min-height: 400px;
            height: 70vh;
            max-height: 800px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            position: relative;
        }
        #qrImage {
            max-width: 100%;
            max-height: 100%;
            height: auto;
            width: auto;
            object-fit: contain;
            display: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        #qrPlaceholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #666;
            font-size: 16px;
        }
        .controls {
            margin: 20px 0;
        }
        .progress-container {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            display: none;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            width: 0%;
            transition: width 0.3s ease;
        }
        .progress-text {
            position: absolute;
            width: 100%;
            text-align: center;
            line-height: 20px;
            color: #000;
            font-size: 12px;
        }
        .status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .error {
            background-color: #ffe6e6;
            color: #cc0000;
        }
        .success {
            background-color: #e6ffe6;
            color: #006600;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
        }
        button {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        #uploadForm {
            margin: 20px 0;
        }
        #systemCheck {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
        }
        .check-item {
            margin: 5px 0;
            padding: 5px;
        }
        .check-pass {
            color: #28a745;
        }
        .check-fail {
            color: #dc3545;
        }
        .transmission-info {
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>文件传输二维码生成器 2.0</h1>

        <div id="systemCheck">
            <h3>系统检查</h3>
            <?php
            // 检查PHP版本
            $phpVersion = phpversion();
            $phpCheck = version_compare($phpVersion, '7.0.0', '>=');
            echo '<div class="check-item ' . ($phpCheck ? 'check-pass' : 'check-fail') . '">';
            echo 'PHP版本: ' . $phpVersion . ($phpCheck ? ' ✓' : ' ✗');
            echo '</div>';

            // 检查GD库
            $gdCheck = extension_loaded('gd');
            echo '<div class="check-item ' . ($gdCheck ? 'check-pass' : 'check-fail') . '">';
            echo 'GD库: ' . ($gdCheck ? '已启用 ✓' : '未启用 ✗');
            if (!$gdCheck) {
                echo ' <span class="warning">(请在php.ini中启用GD库)</span>';
            }
            echo '</div>';

            // 检查目录权限
            $tempCheck = is_writable('temp') || mkdir('temp', 0777, true);
            echo '<div class="check-item ' . ($tempCheck ? 'check-pass' : 'check-fail') . '">';
            echo '临时目录权限: ' . ($tempCheck ? '正常 ✓' : '异常 ✗');
            echo '</div>';

            // 检查JSON支持
            $jsonCheck = function_exists('json_encode') && function_exists('json_decode');
            echo '<div class="check-item ' . ($jsonCheck ? 'check-pass' : 'check-fail') . '">';
            echo 'JSON支持: ' . ($jsonCheck ? '正常 ✓' : '异常 ✗');
            echo '</div>';

            // 系统总体状态
            $systemReady = $phpCheck && $gdCheck && $tempCheck && $jsonCheck;
            ?>
        </div>
        
        <form id="uploadForm" enctype="multipart/form-data" <?php if (!$systemReady) echo 'style="display:none;"'; ?>>
            <input type="file" name="file" id="file" required>
            <button type="submit" id="submitBtn">开始传输</button>
            <button type="button" id="stopBtn" style="margin-left: 10px; display: none;">停止传输</button>
        </form>

        <?php if (!$systemReady): ?>
        <div class="status error" style="display:block;">
            系统检查未通过，请解决上述问题后刷新页面。
        </div>
        <?php endif; ?>

        <div class="status" id="status"></div>

        <div class="progress-container" id="progressContainer">
            <h4>二维码生成进度</h4>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
                <div class="progress-text" id="progressText">0%</div>
            </div>
        </div>

        <div class="transmission-info" id="transmissionInfo" style="display: none;">
            <div>总块数: <span id="totalChunks">0</span></div>
            <div>已生成: <span id="generatedChunks">0</span></div>
            <div>循环次数: <span id="loopCount">0</span></div>
        </div>

        <div id="qrcode">
            <img id="qrImage" style="max-width: 100%; height: auto; display: none;">
            <p id="qrPlaceholder">请选择文件并点击开始传输</p>
        </div>
    </div>

    <script>
        const form = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submitBtn');
        const stopBtn = document.getElementById('stopBtn');
        const statusDiv = document.getElementById('status');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const qrImage = document.getElementById('qrImage');
        const qrPlaceholder = document.getElementById('qrPlaceholder');
        const transmissionInfo = document.getElementById('transmissionInfo');
        const totalChunksSpan = document.getElementById('totalChunks');
        const generatedChunksSpan = document.getElementById('generatedChunks');
        const loopCountSpan = document.getElementById('loopCount');

        let transmissionActive = false;
        let qrCodes = new Map(); // 存储生成的二维码
        let currentTotalChunks = 0;
        let loopCount = 0;
        let generatedCount = 0;
        let displayStarted = false;
        let displayAnimationFrame = null;
        let fountainEncoder = null;
        let fileData = null;

        // 喷泉码实现
        class FountainEncoder {
            constructor(sourceData, blockSize = 512) {
                this.sourceData = sourceData;
                this.blockSize = blockSize;
                this.totalBlocks = Math.ceil(sourceData.length / blockSize);
                this.sourceBlocks = this.splitSourceData();
            }

            splitSourceData() {
                const blocks = new Map();
                for (let i = 0; i < this.totalBlocks; i++) {
                    const start = i * this.blockSize;
                    const end = Math.min(start + this.blockSize, this.sourceData.length);
                    const blockData = new Uint8Array(this.blockSize);
                    blockData.set(this.sourceData.slice(start, end));
                    blocks.set(i, blockData);
                }
                return blocks;
            }

            generateEncodedBlock(seed) {
                const random = new Random(seed);
                // 使用Robust Soliton分布选择度
                const degree = this.getRobustSolitonDegree(random);
                
                // 随机选择源块
                const selectedBlocks = this.selectSourceBlocks(degree, random);
                
                // 异或选中的源块
                const encodedData = new Uint8Array(this.blockSize);
                for (const blockIndex of selectedBlocks) {
                    const sourceBlock = this.sourceBlocks.get(blockIndex);
                    for (let i = 0; i < this.blockSize; i++) {
                        encodedData[i] ^= sourceBlock[i];
                    }
                }

                return {
                    seed: seed,
                    degree: degree,
                    sourceBlocks: selectedBlocks,
                    data: encodedData
                };
            }

            getRobustSolitonDegree(random) {
                // 简化的Robust Soliton实现
                const c = 0.1;  // 参数c
                const delta = 0.05;  // 解码失败概率
                const R = c * Math.log(this.totalBlocks / delta) * Math.sqrt(this.totalBlocks);
                const maxDegree = Math.min(this.totalBlocks, Math.ceil(this.totalBlocks / R));
                return 1 + Math.floor(random.random() * maxDegree);
            }

            selectSourceBlocks(degree, random) {
                const selected = new Set();
                while (selected.size < degree) {
                    const index = random.nextInt(this.totalBlocks);
                    selected.add(index);
                }
                return Array.from(selected);
            }
        }

        // 随机数生成器
        class Random {
            constructor(seed) {
                this.seed = seed;
            }

            random() {
                this.seed = (1664525 * this.seed + 1013904223) >>> 0;
                return this.seed / 0xFFFFFFFF;
            }

            nextInt(max) {
                return Math.floor(this.random() * max);
            }
        }

        function showStatus(message, isError = false) {
            statusDiv.textContent = message;
            statusDiv.style.display = 'block';
            statusDiv.className = 'status ' + (isError ? 'error' : 'success');
        }

        function updateProgress(current, total) {
            const percentage = Math.round((current / total) * 100);
            requestAnimationFrame(() => {
                progressFill.style.width = percentage + '%';
                progressText.textContent = percentage + '%';
                generatedChunksSpan.textContent = current;
            });
        }

        // Fisher-Yates 洗牌算法
        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
            return array;
        }

        // 调整QR码大小的函数
        function adjustQRCodeSize() {
            const container = document.getElementById('qrcode');
            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;
            const qrImage = document.getElementById('qrImage');
            
            // 计算合适的尺寸（取容器宽高的较小值，并留出边距）
            const size = Math.min(containerWidth, containerHeight) - 40;
            
            // 设置图片尺寸
            qrImage.style.width = size + 'px';
            qrImage.style.height = size + 'px';
        }

        // 在窗口大小改变时调整QR码大小
        window.addEventListener('resize', adjustQRCodeSize);

        // 循环显示QR码
        function startQRDisplay() {
            if (displayStarted) return;
            
            displayStarted = true;
            let currentIndex = 0;
            let lastDisplayTime = 0;
            const displayInterval = 50; // 每秒20张
            let recentQRCodes = [];
            let isShowingRecent = false;
            let recentIndex = 0;
            
            function displayNextQR(timestamp) {
                if (!transmissionActive) {
                    displayStarted = false;
                    return;
                }

                const currentTime = performance.now();
                if (currentTime - lastDisplayTime >= displayInterval) {
                    if (!isShowingRecent) {
                        // 显示新的编码块
                        const keys = Array.from(qrCodes.keys());
                        const randomIndex = Math.floor(Math.random() * keys.length);
                        const qrCode = qrCodes.get(keys[randomIndex]);
                        
                        if (qrCode) {
                            qrImage.src = qrCode.src;
                            qrImage.style.display = 'block';
                            qrPlaceholder.style.display = 'none';
                            adjustQRCodeSize();

                            // 更新最近显示的编码块列表
                            recentQRCodes.unshift(qrCode);
                            if (recentQRCodes.length > 3) {
                                recentQRCodes.pop();
                            }

                            loopCount++;
                            loopCountSpan.textContent = loopCount;
                            
                            isShowingRecent = true;
                            recentIndex = 0;
                        }
                    } else {
                        // 显示最近的三个编码块
                        if (recentIndex < recentQRCodes.length) {
                            const recentQR = recentQRCodes[recentIndex];
                            qrImage.src = recentQR.src;
                            qrImage.style.display = 'block';
                            qrPlaceholder.style.display = 'none';
                            adjustQRCodeSize();
                            recentIndex++;
                        } else {
                            isShowingRecent = false;
                        }
                    }
                    lastDisplayTime = currentTime;
                }

                displayAnimationFrame = requestAnimationFrame(displayNextQR);
            }

            displayAnimationFrame = requestAnimationFrame(displayNextQR);
            console.log('QR码持续显示已启动，显示间隔：50ms（20张/秒）');
        }

        // 在页面加载完成后初始化大小
        window.addEventListener('load', adjustQRCodeSize);

        // 修改图片加载完成的处理
        async function preloadQRCode(blockIndex) {
            try {
                // 生成新的编码块
                const encodedBlock = fountainEncoder.generateEncodedBlock(blockIndex);
                
                // 构建数据包
                const packet = {
                    header: {
                        magic: "FLQR",
                        fileSize: fileData.size,
                        fileName: fileData.name,
                        blockIndex: blockIndex,
                        totalBlocks: fountainEncoder.totalBlocks,
                        checksum: 0,
                        reserved: 0
                    },
                    encoding: {
                        seed: encodedBlock.seed,
                        degree: encodedBlock.degree,
                        sourceBlocks: encodedBlock.sourceBlocks,
                        checksum: 0
                    },
                    payload: btoa(String.fromCharCode.apply(null, encodedBlock.data))
                };

                // 计算校验和
                const headerChecksum = calculateHeaderChecksum(packet.header);
                const encodingChecksum = calculateEncodingChecksum(packet.encoding);
                packet.header.checksum = headerChecksum;
                packet.encoding.checksum = encodingChecksum;

                // 打印调试信息
                console.log('发送的数据包:', packet);

                // 生成QR码
                const response = await fetch('generate_qr.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(packet)
                });

                const responseData = await response.json();
                console.log('服务器响应:', responseData);

                if (!response.ok || !responseData.success) {
                    throw new Error(responseData.message || 'QR码生成失败');
                }

                return {
                    index: blockIndex,
                    url: responseData.url
                };
            } catch (error) {
                console.error('生成QR码失败:', error);
                showStatus('生成QR码失败: ' + error.message, true);
                return null;
            }
        }

        async function handleFileUpload(event) {
            event.preventDefault();
            const file = document.getElementById('file').files[0];
            if (!file) {
                showStatus('请选择文件', true);
                return;
            }

            try {
                // 读取文件数据
                const arrayBuffer = await file.arrayBuffer();
                fileData = {
                    data: new Uint8Array(arrayBuffer),
                    size: file.size,
                    name: file.name
                };
                
                // 初始化喷泉码编码器
                fountainEncoder = new FountainEncoder(fileData.data);
                
                // 更新UI显示
                currentTotalChunks = fountainEncoder.totalBlocks;
                totalChunksSpan.textContent = currentTotalChunks;
                transmissionInfo.style.display = 'block';
                progressContainer.style.display = 'block';
                
                // 启动传输
                transmissionActive = true;
                stopBtn.style.display = 'inline-block';
                submitBtn.disabled = true;
                
                // 开始生成和显示QR码
                startQRDisplay();
                startContinuousGeneration();
                
                showStatus('文件处理完成，开始传输');
            } catch (error) {
                console.error('文件处理失败:', error);
                showStatus('文件处理失败: ' + error.message, true);
            }
        }

        async function startContinuousGeneration() {
            let nextBlockIndex = 0;
            
            while (transmissionActive) {
                try {
                    // 生成新的编码块
                    const newBlock = await preloadQRCode(nextBlockIndex++);
                    if (newBlock) {
                        qrCodes.set(newBlock.index, newBlock);
                        generatedCount++;
                        updateProgress(generatedCount, currentTotalChunks);
                        
                        // 维护固定大小的队列
                        if (qrCodes.size > 100) {
                            const oldestKey = qrCodes.keys().next().value;
                            qrCodes.delete(oldestKey);
                        }
                    }
                    
                    // 短暂延迟避免过度占用CPU
                    await new Promise(resolve => setTimeout(resolve, 50));
                } catch (error) {
                    console.error('生成新编码块失败:', error);
                }
            }
        }

        function startQRDisplay() {
            if (!transmissionActive || displayStarted) return;
            displayStarted = true;
            
            const displayLoop = () => {
                if (!transmissionActive) {
                    displayStarted = false;
                    qrImage.style.display = 'none';
                    qrPlaceholder.style.display = 'block';
                    return;
                }
                
                // 从现有块中随机选择一个显示
                const blocks = Array.from(qrCodes.values());
                if (blocks.length > 0) {
                    const randomBlock = blocks[Math.floor(Math.random() * blocks.length)];
                    qrImage.src = randomBlock.url;
                    qrImage.style.display = 'block';
                    qrPlaceholder.style.display = 'none';
                    loopCount++;
                    loopCountSpan.textContent = loopCount;
                }
                
                // 50ms后显示下一个（20帧/秒）
                setTimeout(() => {
                    displayAnimationFrame = requestAnimationFrame(displayLoop);
                }, 50);
            };
            
            displayLoop();
        }

        function stopTransmission() {
            transmissionActive = false;
            stopBtn.style.display = 'none';
            submitBtn.disabled = false;
            if (displayAnimationFrame) {
                cancelAnimationFrame(displayAnimationFrame);
                displayAnimationFrame = null;
            }
            showStatus('传输已停止');
        }

        // 添加校验和计算函数
        function calculateChecksum(data) {
            let checksum = 0;
            // 确保 JSON 字符串的格式一致性
            const jsonStr = JSON.stringify(data, null, 0);
            console.log('Calculating checksum for:', jsonStr); // 调试信息
            for (let i = 0; i < jsonStr.length; i += 2) {
                if (i + 1 < jsonStr.length) {
                    checksum ^= (jsonStr.charCodeAt(i) << 8) | jsonStr.charCodeAt(i + 1);
                } else {
                    checksum ^= (jsonStr.charCodeAt(i) << 8);
                }
            }
            return checksum & 0xFFFF;
        }

        // 计算头部校验和
        function calculateHeaderChecksum(header) {
            const headerCopy = {...header};
            headerCopy.checksum = 0;
            // 按照固定顺序排序键
            const orderedHeader = {
                magic: headerCopy.magic,
                fileSize: headerCopy.fileSize,
                fileName: headerCopy.fileName,
                blockIndex: headerCopy.blockIndex,
                totalBlocks: headerCopy.totalBlocks,
                checksum: headerCopy.checksum,
                reserved: headerCopy.reserved
            };
            return calculateChecksum(orderedHeader);
        }

        // 计算编码信息校验和
        function calculateEncodingChecksum(encoding) {
            const encodingCopy = {...encoding};
            encodingCopy.checksum = 0;
            // 按照固定顺序排序键
            const orderedEncoding = {
                seed: encodingCopy.seed,
                degree: encodingCopy.degree,
                sourceBlocks: encodingCopy.sourceBlocks,
                checksum: encodingCopy.checksum
            };
            return calculateChecksum(orderedEncoding);
        }

        stopBtn.onclick = function() {
            stopTransmission();
            qrImage.style.display = 'none';
            qrPlaceholder.style.display = 'block';
            progressContainer.style.display = 'none';
            transmissionInfo.style.display = 'none';
            qrCodes.clear();
            
            const qrImage = document.getElementById('qrImage');
            qrImage.style.width = '';
            qrImage.style.height = '';
        };

        form.addEventListener('submit', handleFileUpload);
        
        form.onsubmit = function(e) {
            e.preventDefault();
            return false;
        };
    </script>
</body>
</html>
