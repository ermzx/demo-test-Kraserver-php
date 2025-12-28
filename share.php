<?php
require_once 'functions.php';

// 获取数据 (parse_reading_data 函数在 functions.php 中)
$data = parse_reading_data($_GET);
$stats = calculate_stats($data);

// JS 数据准备
$js_today_data = json_encode($data['today_dist']);
$js_today_labels = json_encode(["0-2点", "2-4点", "4-6点", "6-8点", "8-10点", "10-12点", "12-14点", "14-16点", "16-18点", "18-20点", "20-22点", "22-24点"]);

// 模拟本周数据（取最后7天）
$daily_values = array_values($data['daily_data']);
$daily_keys = array_keys($data['daily_data']);
$week_data = array_slice($daily_values, -7); 
$week_labels = array_map(function($d){ return $d."日"; }, array_slice($daily_keys, -7));
$js_week_data = json_encode($week_data);
$js_week_labels = json_encode($week_labels);

// 点评文本
$comment_today = generate_comment('today', $data['today_dist'], ["0-2点", "2-4点", "4-6点", "6-8点", "8-10点", "10-12点", "12-14点", "14-16点", "16-18点", "18-20点", "20-22点", "22-24点"]);
$week_assoc = array_combine(array_slice($daily_keys, -7), $week_data);
$comment_week = generate_comment('week', $week_assoc);
$comment_month = "本月目标 {$data['goal']} 分钟/天，已达标 {$stats['goal_days']} 天。";

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $data['year']; ?>年<?php echo $data['month']; ?>月阅读报告</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://www.tqhyg.net/wp-content/themes/Sakurairo/css/font-awesome-animation.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0d6efd;
            --bg-color: #f0f2f5;
        }
        body { background: var(--bg-color); padding-bottom: 100px; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .stat-card { background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02); }
        
        .highlight-num { font-size: 2rem; font-weight: 800; color: #2c3e50; line-height: 1.2; }
        .sub-label { color: #95a5a6; font-size: 0.85rem; font-weight: 500; }
        
        /* 按钮样式 */
        .btn-export { font-size: 0.85rem; padding: 0.375rem 0.75rem; border-radius: 8px; }

        /* Calendar Styles - 核心移动端适配 */
        .calendar-wrapper { width: 100%; overflow: hidden; }
        .calendar-grid { 
            display: grid; 
            grid-template-columns: repeat(7, 1fr); 
            gap: 8px; 
            margin-top: 15px; 
        }
        .calendar-day-header { 
            text-align: center; font-weight: bold; color: #bdc3c7; font-size: 0.8rem; padding-bottom: 5px; 
        }
        
        /* 日期单元格 */
        .day-cell { 
            background: #fff; 
            border: 1px solid #f1f3f5; 
            border-radius: 10px; 
            padding: 4px; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            align-items: center;
            justify-content: center;
            /* 保持正方形比例，防止移动端变扁 */
            aspect-ratio: 1 / 1; 
        }
        .day-cell.active { background-color: #f0f9ff; border-color: #b9e3fe; }
        .day-cell.goal-reached { border-color: #2ecc71; background-color: #eafaf1; }
        
        .day-num { font-size: 0.9rem; font-weight: bold; color: #34495e; margin-bottom: 2px; }
        .day-time { font-size: 0.85rem; font-weight: 600; color: var(--primary-color); }

        /* 移动端特定适配 (屏幕 < 576px) */
        @media (max-width: 576px) {
            .container { padding-left: 10px; padding-right: 10px; }
            .stat-card { padding: 15px; border-radius: 12px; }
            .highlight-num { font-size: 1.5rem; }
            
            /* 日历移动端紧凑模式 */
            .calendar-grid { gap: 4px; }
            .day-cell { border-radius: 6px; }
            .day-num { font-size: 0.75rem; }
            .day-time { font-size: 0.65rem; }
            /* 隐藏分钟单位 'm' 以节省空间，改用纯数字 */
            .unit-text { display: none; }
        }

        /* FAB */
        .fab-container { position: fixed; bottom: 30px; right: 20px; z-index: 999; }
        .btn-fab { border-radius: 50px; padding: 12px 24px; font-weight: 600; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3); }
    </style>
</head>
<body>

<div class="container mt-4" id="full-report">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-chart-pie text-primary me-2"></i>阅读报告</h4>
            <span class="text-muted small"><?php echo $data['year']; ?>年<?php echo $data['month']; ?>月</span>
        </div>
        <button class="btn btn-outline-secondary btn-export" onclick="exportSection('overview-section')">
            <i class="fa-solid fa-image me-1"></i> 导出图片
        </button>
    </div>

    <div id="overview-section" class="stat-card">
        <h6 class="border-bottom pb-2 mb-3 fw-bold text-secondary">
            <i class="fa-solid fa-gauge-high me-1"></i> 数据概览
        </h6>
        <div class="row text-center g-3">
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="highlight-num text-primary"><?php echo $stats['today_total']; ?><span class="fs-6 text-muted fw-normal"> m</span></div>
                    <div class="sub-label">今日阅读</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="highlight-num"><?php echo $stats['total_month']; ?><span class="fs-6 text-muted fw-normal"> m</span></div>
                    <div class="sub-label">本月累计</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="highlight-num text-success"><?php echo $stats['goal_days']; ?><span class="fs-6 text-muted fw-normal"> 天</span></div>
                    <div class="sub-label">达标 (><?php echo $data['goal']; ?>m)</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2">
                    <div class="highlight-num text-warning"><?php echo $stats['streak']; ?><span class="fs-6 text-muted fw-normal"> 天</span></div>
                    <div class="sub-label">当前连续</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="stat-card" id="today-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold text-secondary"><i class="fa-regular fa-clock me-1"></i> 今日分布</h6>
                    <button class="btn btn-sm btn-light text-muted btn-export border" onclick="exportSection('today-section')">
                        <i class="fa-solid fa-image"></i> 导出
                    </button>
                </div>
                <div style="position: relative; height: 200px;">
                    <canvas id="todayChart"></canvas>
                </div>
                <div class="alert alert-light mt-3 mb-0 border-0 bg-light small">
                    <i class="fa-solid fa-lightbulb text-warning me-1"></i> <?php echo $comment_today; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="stat-card" id="week-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-calendar-week me-1"></i> 近7天趋势</h6>
                    <button class="btn btn-sm btn-light text-muted btn-export border" onclick="exportSection('week-section')">
                        <i class="fa-solid fa-image"></i> 导出
                    </button>
                </div>
                <div style="position: relative; height: 200px;">
                    <canvas id="weekChart"></canvas>
                </div>
                <div class="alert alert-light mt-3 mb-0 border-0 bg-light small">
                    <i class="fa-solid fa-chart-line text-primary me-1"></i> <?php echo $comment_week; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="stat-card" id="month-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 fw-bold text-secondary"><i class="fa-regular fa-calendar-alt me-1"></i> 月度全景</h6>
            <button class="btn btn-sm btn-light text-muted btn-export border" onclick="exportSection('month-section')">
                <i class="fa-solid fa-image"></i> 导出图片
            </button>
        </div>
        
        <div class="calendar-wrapper">
            <div class="calendar-grid mb-1">
                <div class="calendar-day-header">日</div><div class="calendar-day-header">一</div>
                <div class="calendar-day-header">二</div><div class="calendar-day-header">三</div>
                <div class="calendar-day-header">四</div><div class="calendar-day-header">五</div>
                <div class="calendar-day-header">六</div>
            </div>

            <div class="calendar-grid">
                <?php
                $first_day_ts = mktime(0, 0, 0, $data['month'], 1, $data['year']);
                $first_day_of_week = date('w', $first_day_ts);
                $days_in_month = date('t', $first_day_ts);

                // 填充空白
                for ($k = 0; $k < $first_day_of_week; $k++) {
                    echo "<div></div>";
                }

                // 渲染每一天
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $minutes = isset($data['daily_data'][$day]) ? $data['daily_data'][$day] : 0;
                    $is_goal = $minutes >= $data['goal'];
                    
                    $class = '';
                    if ($minutes > 0) {
                        $class .= $is_goal ? 'goal-reached' : 'active';
                    }

                    echo "<div class='day-cell {$class}'>";
                    echo "<div class='day-num'>{$day}</div>";
                    
                    if ($minutes > 0) {
                        // 在移动端CSS中隐藏 unit-text class
                        echo "<div class='day-time'>{$minutes}<span class='unit-text'>m</span></div>";
                    } else {
                        echo "<div class='day-time text-muted fw-light' style='opacity:0.3'>-</div>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <div class="alert alert-light mt-3 mb-0 border-0 bg-light small">
            <i class="fa-solid fa-flag text-danger me-1"></i> <?php echo $comment_month; ?>
        </div>
    </div>

</div>

<div class="fab-container">
    <button class="btn btn-primary btn-fab" onclick="exportFullPage()">
        <i class="fa-solid fa-download me-2 faa-float animated"></i>下载长图
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<script>
    // Chart.js 配置 - 保持高度自适应
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false, // 允许图表填满容器
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
            x: { grid: { display: false } }
        }
    };

    new Chart(document.getElementById('todayChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_today_labels; ?>,
            datasets: [{
                data: <?php echo $js_today_data; ?>,
                backgroundColor: '#0d6efd',
                borderRadius: 4
            }]
        },
        options: commonOptions
    });

    new Chart(document.getElementById('weekChart'), {
        type: 'line',
        data: {
            labels: <?php echo $js_week_labels; ?>,
            datasets: [{
                data: <?php echo $js_week_data; ?>,
                fill: true,
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderColor: '#0d6efd',
                tension: 0.4,
                pointRadius: 3
            }]
        },
        options: commonOptions
    });

    function downloadCanvas(canvas, filename) {
        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = filename;
        link.click();
    }

    function exportSection(elementId) {
        const element = document.getElementById(elementId);
        // 临时增加 padding 防止截图边缘切断阴影
        const originalPadding = element.style.padding;
        
        html2canvas(element, { useCORS: true, scale: 2 }).then(canvas => {
            downloadCanvas(canvas, `reading-${elementId}.png`);
        });
    }

    function exportFullPage() {
        document.querySelector('.fab-container').style.display = 'none';
        const element = document.getElementById('full-report');
        
        html2canvas(element, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#f0f2f5' // 确保背景色正确
        }).then(canvas => {
            downloadCanvas(canvas, 'reading-full-report.png');
            document.querySelector('.fab-container').style.display = 'block';
        });
    }
</script>

</body>
</html>