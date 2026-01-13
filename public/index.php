<?php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['user_token'])) {
    header('Location: /api/auth.php?action=login');
    exit;
}

$userToken = $_SESSION['user_token'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kindle Reading GTK - 用户管理</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="container">
        <!-- 头部 -->
        <header class="header">
            <h1>Kindle Reading GTK</h1>
            <button class="logout-btn" id="logoutBtn">退出登录</button>
        </header>

        <!-- 用户信息卡片 -->
        <section class="card user-info-card">
            <div class="user-avatar" id="userAvatar">
                <div class="avatar-placeholder">?</div>
            </div>
            <div class="user-details">
                <h2 id="username">加载中...</h2>
                <div class="user-meta">
                    <span>注册时间: <span id="createdAt">-</span></span>
                    <span>最后登录: <span id="lastLoginAt">-</span></span>
                </div>
            </div>
        </section>

        <!-- 统计信息卡片 -->
        <section class="card stats-card">
            <div class="stat-item">
                <div class="stat-icon">📱</div>
                <div class="stat-info">
                    <div class="stat-value" id="devicesCount">0</div>
                    <div class="stat-label">设备数量</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">📄</div>
                <div class="stat-info">
                    <div class="stat-value" id="logsCount">0</div>
                    <div class="stat-label">日志数量</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">💾</div>
                <div class="stat-info">
                    <div class="stat-value" id="totalSize">0 MB</div>
                    <div class="stat-label">总存储大小</div>
                </div>
            </div>
        </section>

        <!-- 设备管理卡片 -->
        <section class="card devices-card">
            <div class="card-header">
                <h3>设备管理</h3>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table" id="devicesTable">
                        <thead>
                            <tr>
                                <th>设备名称</th>
                                <th>设备 ID</th>
                                <th>绑定时间</th>
                                <th>最后同步</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="devicesTableBody">
                            <tr>
                                <td colspan="5" class="loading-row">加载中...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- 日志文件管理卡片 -->
        <section class="card logs-card">
            <div class="card-header">
                <h3>日志文件</h3>
                <div class="filter-group">
                    <label for="deviceFilter">筛选设备:</label>
                    <select id="deviceFilter">
                        <option value="">所有设备</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table" id="logsTable">
                        <thead>
                            <tr>
                                <th>文件名</th>
                                <th>设备</th>
                                <th>文件大小</th>
                                <th>上传时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr>
                                <td colspan="5" class="loading-row">加载中...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination">
                    <button class="pagination-btn" id="prevPage" disabled>上一页</button>
                    <span class="pagination-info" id="paginationInfo">第 1 页</span>
                    <button class="pagination-btn" id="nextPage" disabled>下一页</button>
                </div>
            </div>
        </section>
    </div>

    <!-- 编辑设备名称模态框 -->
    <div class="modal" id="editDeviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑设备名称</h3>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editDeviceId">
                <div class="form-group">
                    <label for="editDeviceName">设备名称</label>
                    <input type="text" id="editDeviceName" class="form-input" placeholder="请输入设备名称">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEdit">取消</button>
                <button class="btn btn-primary" id="saveDeviceName">保存</button>
            </div>
        </div>
    </div>

    <!-- 加载遮罩 -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- 提示消息 -->
    <div class="toast" id="toast"></div>

    <script>
        // 将用户令牌传递给 JavaScript
        window.userToken = '<?php echo $userToken; ?>';
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>