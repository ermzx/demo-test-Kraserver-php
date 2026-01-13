/**
 * Kindle Reading GTK - Dashboard JavaScript
 * 用户管理界面交互逻辑
 */

// ============================================
// 配置
// ============================================
const CONFIG = {
    API_BASE: '/api',
    LOGS_PER_PAGE: 20
};

// ============================================
// API 调用封装
// ============================================
const API = {
    /**
     * 获取请求头
     */
    getHeaders() {
        return {
            'Authorization': `Bearer ${window.userToken}`,
            'Content-Type': 'application/json'
        };
    },

    /**
     * 通用请求方法
     */
    async request(url, options = {}) {
        const defaultOptions = {
            headers: this.getHeaders()
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: '请求失败' }));
            throw new Error(error.message || '请求失败');
        }

        return response.json();
    },

    /**
     * GET 请求
     */
    async get(url) {
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST 请求
     */
    async post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT 请求
     */
    async put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE 请求
     */
    async delete(url) {
        return this.request(url, { method: 'DELETE' });
    },

    // ============================================
    // 用户相关 API
    // ============================================
    
    /**
     * 获取用户信息
     */
    async getUserProfile() {
        return this.get(`${CONFIG.API_BASE}/user/profile`);
    },

    /**
     * 获取设备列表
     */
    async getDevices() {
        return this.get(`${CONFIG.API_BASE}/user/devices`);
    },

    /**
     * 更新设备名称
     */
    async updateDeviceName(deviceId, deviceName) {
        return this.put(`${CONFIG.API_BASE}/user/devices/${deviceId}`, { device_name: deviceName });
    },

    /**
     * 解绑设备
     */
    async unbindDevice(deviceId) {
        return this.delete(`${CONFIG.API_BASE}/user/devices/${deviceId}`);
    },

    /**
     * 获取日志列表
     */
    async getLogs(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.get(`${CONFIG.API_BASE}/user/logs?${queryString}`);
    },

    /**
     * 下载日志文件
     */
    async downloadLog(logId) {
        const url = `${CONFIG.API_BASE}/user/logs/${logId}/download`;
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${window.userToken}`
            }
        });

        if (!response.ok) {
            throw new Error('下载失败');
        }

        return response.blob();
    },

    /**
     * 删除日志文件
     */
    async deleteLog(logId) {
        return this.delete(`${CONFIG.API_BASE}/user/logs/${logId}`);
    }
};

// ============================================
// UI 工具函数
// ============================================
const UI = {
    /**
     * 显示加载遮罩
     */
    showLoading() {
        document.getElementById('loadingOverlay').classList.add('active');
    },

    /**
     * 隐藏加载遮罩
     */
    hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    },

    /**
     * 显示提示消息
     */
    showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} active`;

        setTimeout(() => {
            toast.classList.remove('active');
        }, 3000);
    },

    /**
     * 格式化文件大小
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    /**
     * 格式化日期时间
     */
    formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * 格式化日期
     */
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    },

    /**
     * 显示空状态
     */
    showEmptyState(tableBody, message) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div class="empty-state-text">${message}</div>
                </td>
            </tr>
        `;
    }
};

// ============================================
// 应用状态
// ============================================
const AppState = {
    devices: [],
    logs: [],
    currentPage: 1,
    totalPages: 1,
    totalLogs: 0,
    selectedDeviceId: '',
    stats: {
        devicesCount: 0,
        logsCount: 0,
        totalSize: 0
    }
};

// ============================================
// 用户信息管理
// ============================================
const UserManager = {
    /**
     * 加载用户信息
     */
    async loadUserInfo() {
        try {
            const response = await API.getUserProfile();
            const user = response.data;

            // 更新用户信息显示
            document.getElementById('username').textContent = user.username;
            document.getElementById('createdAt').textContent = UI.formatDate(user.created_at);
            document.getElementById('lastLoginAt').textContent = UI.formatDateTime(user.last_login_at);

            // 更新头像
            const avatarContainer = document.getElementById('userAvatar');
            if (user.avatar_url) {
                avatarContainer.innerHTML = `<img src="${user.avatar_url}" alt="${user.username}">`;
            } else {
                avatarContainer.innerHTML = `<div class="avatar-placeholder">${user.username.charAt(0).toUpperCase()}</div>`;
            }

            // 更新设备数量
            AppState.stats.devicesCount = user.devices_count || 0;
            document.getElementById('devicesCount').textContent = AppState.stats.devicesCount;

        } catch (error) {
            console.error('加载用户信息失败:', error);
            UI.showToast('加载用户信息失败', 'error');
        }
    }
};

// ============================================
// 设备管理
// ============================================
const DeviceManager = {
    /**
     * 加载设备列表
     */
    async loadDevices() {
        try {
            UI.showLoading();
            const response = await API.getDevices();
            AppState.devices = response.data.devices || [];

            this.renderDevices();
            this.updateDeviceFilter();

        } catch (error) {
            console.error('加载设备列表失败:', error);
            UI.showToast('加载设备列表失败', 'error');
            UI.showEmptyState(document.getElementById('devicesTableBody'), '加载设备列表失败');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * 渲染设备列表
     */
    renderDevices() {
        const tableBody = document.getElementById('devicesTableBody');

        if (AppState.devices.length === 0) {
            UI.showEmptyState(tableBody, '暂无设备');
            return;
        }

        tableBody.innerHTML = AppState.devices.map(device => `
            <tr>
                <td>
                    <strong>${this.escapeHtml(device.device_name || '未命名设备')}</strong>
                </td>
                <td>
                    <span class="device-id">${this.escapeHtml(device.device_id)}</span>
                </td>
                <td>
                    <span class="time">${UI.formatDate(device.created_at)}</span>
                </td>
                <td>
                    <span class="time">${UI.formatDateTime(device.last_sync_at)}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-primary" onclick="DeviceManager.openEditModal(${device.id}, '${this.escapeHtml(device.device_name || '')}')">
                            编辑
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="DeviceManager.unbindDevice(${device.id})">
                            解绑
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * 更新设备筛选下拉框
     */
    updateDeviceFilter() {
        const select = document.getElementById('deviceFilter');
        select.innerHTML = '<option value="">所有设备</option>';

        AppState.devices.forEach(device => {
            const option = document.createElement('option');
            option.value = device.device_id;
            option.textContent = device.device_name || device.device_id;
            select.appendChild(option);
        });
    },

    /**
     * 打开编辑模态框
     */
    openEditModal(deviceId, deviceName) {
        document.getElementById('editDeviceId').value = deviceId;
        document.getElementById('editDeviceName').value = deviceName;
        document.getElementById('editDeviceModal').classList.add('active');
    },

    /**
     * 关闭编辑模态框
     */
    closeEditModal() {
        document.getElementById('editDeviceModal').classList.remove('active');
        document.getElementById('editDeviceName').value = '';
    },

    /**
     * 保存设备名称
     */
    async saveDeviceName() {
        const deviceId = document.getElementById('editDeviceId').value;
        const deviceName = document.getElementById('editDeviceName').value.trim();

        if (!deviceName) {
            UI.showToast('请输入设备名称', 'warning');
            return;
        }

        try {
            UI.showLoading();
            await API.updateDeviceName(deviceId, deviceName);
            UI.showToast('设备名称更新成功');
            this.closeEditModal();
            await this.loadDevices();

        } catch (error) {
            console.error('更新设备名称失败:', error);
            UI.showToast('更新设备名称失败', 'error');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * 解绑设备
     */
    async unbindDevice(deviceId) {
        if (!confirm('确定要解绑此设备吗？解绑后该设备将无法再上传日志。')) {
            return;
        }

        try {
            UI.showLoading();
            await API.unbindDevice(deviceId);
            UI.showToast('设备解绑成功');
            await this.loadDevices();
            await LogManager.loadLogs(); // 重新加载日志列表

        } catch (error) {
            console.error('解绑设备失败:', error);
            UI.showToast('解绑设备失败', 'error');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * HTML 转义
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// ============================================
// 日志管理
// ============================================
const LogManager = {
    /**
     * 加载日志列表
     */
    async loadLogs(page = 1) {
        try {
            UI.showLoading();
            AppState.currentPage = page;

            const params = {
                page: page,
                limit: CONFIG.LOGS_PER_PAGE
            };

            if (AppState.selectedDeviceId) {
                params.device_id = AppState.selectedDeviceId;
            }

            const response = await API.getLogs(params);
            AppState.logs = response.data.logs || [];
            AppState.totalPages = response.data.pagination?.total_pages || 1;
            AppState.totalLogs = response.data.pagination?.total || 0;

            // 更新统计信息
            AppState.stats.logsCount = AppState.totalLogs;
            document.getElementById('logsCount').textContent = AppState.totalLogs;

            // 计算总存储大小
            const totalSize = AppState.logs.reduce((sum, log) => sum + log.file_size, 0);
            AppState.stats.totalSize = totalSize;
            document.getElementById('totalSize').textContent = UI.formatFileSize(totalSize);

            this.renderLogs();
            this.updatePagination();

        } catch (error) {
            console.error('加载日志列表失败:', error);
            UI.showToast('加载日志列表失败', 'error');
            UI.showEmptyState(document.getElementById('logsTableBody'), '加载日志列表失败');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * 渲染日志列表
     */
    renderLogs() {
        const tableBody = document.getElementById('logsTableBody');

        if (AppState.logs.length === 0) {
            UI.showEmptyState(tableBody, '暂无日志文件');
            return;
        }

        tableBody.innerHTML = AppState.logs.map(log => `
            <tr>
                <td>
                    <strong>${this.escapeHtml(log.file_name)}</strong>
                </td>
                <td>
                    ${this.escapeHtml(log.device_name || log.device_id)}
                </td>
                <td>
                    <span class="file-size">${UI.formatFileSize(log.file_size)}</span>
                </td>
                <td>
                    <span class="time">${UI.formatDateTime(log.upload_at)}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-primary" onclick="LogManager.downloadLog(${log.id}, '${this.escapeHtml(log.file_name)}')">
                            下载
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="LogManager.deleteLog(${log.id})">
                            删除
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * 更新分页
     */
    updatePagination() {
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        const pageInfo = document.getElementById('paginationInfo');

        pageInfo.textContent = `第 ${AppState.currentPage} / ${AppState.totalPages} 页`;
        prevBtn.disabled = AppState.currentPage <= 1;
        nextBtn.disabled = AppState.currentPage >= AppState.totalPages;
    },

    /**
     * 下载日志文件
     */
    async downloadLog(logId, fileName) {
        try {
            UI.showLoading();
            const blob = await API.downloadLog(logId);
            
            // 创建下载链接
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            UI.showToast('下载成功');

        } catch (error) {
            console.error('下载失败:', error);
            UI.showToast('下载失败', 'error');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * 删除日志文件
     */
    async deleteLog(logId) {
        if (!confirm('确定要删除此日志文件吗？此操作不可恢复。')) {
            return;
        }

        try {
            UI.showLoading();
            await API.deleteLog(logId);
            UI.showToast('删除成功');
            await this.loadLogs(AppState.currentPage);

        } catch (error) {
            console.error('删除失败:', error);
            UI.showToast('删除失败', 'error');
        } finally {
            UI.hideLoading();
        }
    },

    /**
     * HTML 转义
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// ============================================
// 事件监听
// ============================================
const EventListeners = {
    /**
     * 初始化事件监听
     */
    init() {
        // 退出登录
        document.getElementById('logoutBtn').addEventListener('click', () => {
            if (confirm('确定要退出登录吗？')) {
                window.location.href = '/api/auth.php?action=logout';
            }
        });

        // 设备筛选
        document.getElementById('deviceFilter').addEventListener('change', (e) => {
            AppState.selectedDeviceId = e.target.value;
            LogManager.loadLogs(1);
        });

        // 分页
        document.getElementById('prevPage').addEventListener('click', () => {
            if (AppState.currentPage > 1) {
                LogManager.loadLogs(AppState.currentPage - 1);
            }
        });

        document.getElementById('nextPage').addEventListener('click', () => {
            if (AppState.currentPage < AppState.totalPages) {
                LogManager.loadLogs(AppState.currentPage + 1);
            }
        });

        // 编辑设备模态框
        document.getElementById('closeEditModal').addEventListener('click', () => {
            DeviceManager.closeEditModal();
        });

        document.getElementById('cancelEdit').addEventListener('click', () => {
            DeviceManager.closeEditModal();
        });

        document.getElementById('saveDeviceName').addEventListener('click', () => {
            DeviceManager.saveDeviceName();
        });

        // 点击模态框外部关闭
        document.getElementById('editDeviceModal').addEventListener('click', (e) => {
            if (e.target.id === 'editDeviceModal') {
                DeviceManager.closeEditModal();
            }
        });

        // ESC 键关闭模态框
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                DeviceManager.closeEditModal();
            }
        });
    }
};

// ============================================
// 应用初始化
// ============================================
const App = {
    /**
     * 初始化应用
     */
    async init() {
        try {
            // 初始化事件监听
            EventListeners.init();

            // 加载用户信息
            await UserManager.loadUserInfo();

            // 加载设备列表
            await DeviceManager.loadDevices();

            // 加载日志列表
            await LogManager.loadLogs();

        } catch (error) {
            console.error('应用初始化失败:', error);
            UI.showToast('应用初始化失败', 'error');
        }
    }
};

// ============================================
// 页面加载完成后初始化
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});