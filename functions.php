<?php
// functions.php

/**
 * 解析 GET 参数或上传的数据，标准化为统一数组格式
 */
function parse_reading_data($params) {
    $year = isset($params['year']) ? intval($params['year']) : date('Y');
    $month = isset($params['month']) ? intval($params['month']) : date('m');
    $goal = isset($params['goal']) ? intval($params['goal']) : 30; 

    // 1. 提取每日数据 (key 为 1-31 的数字)
    $daily_data = [];
    $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
    
    for ($i = 1; $i <= $days_in_month; $i++) {
        $daily_data[$i] = isset($params[$i]) ? intval($params[$i]) : 0;
    }

    // 2. 提取今日每2小时分布 (d1 - d12)
    $today_dist = [];
    for ($i = 1; $i <= 12; $i++) {
        $key = 'd' . $i;
        $today_dist[] = isset($params[$key]) ? intval($params[$key]) : 0;
    }

    return [
        'year' => $year,
        'month' => $month,
        'goal' => $goal,
        'daily_data' => $daily_data,
        'today_dist' => $today_dist,
    ];
}

/**
 * 计算概览统计数据
 */
function calculate_stats($data) {
    $daily = $data['daily_data'];
    $goal = $data['goal'];

    $total_month_time = array_sum($daily);
    $goal_reached_days = 0;
    $current_streak = 0;
    
    foreach ($daily as $minutes) {
        if ($minutes >= $goal) {
            $goal_reached_days++;
        }
    }

    // 简化的连续计算逻辑（倒序查找）
    $reversed_daily = array_reverse($daily, true);
    foreach ($reversed_daily as $day => $minutes) {
        if ($minutes >= $goal) {
            $current_streak++;
        } else {
            // 如果遇到0或者未达标，且不是未来的日子(简单判断：只要有数据且不达标就断)
            if ($minutes < $goal && $minutes > 0) break; 
            // 如果单纯是0且在月末，可能还没发生，这里为演示简单处理为断开
            if($minutes < $goal) break;
        }
    }

    $today_total = array_sum($data['today_dist']);

    return [
        'total_month' => $total_month_time,
        'today_total' => $today_total,
        'goal_days' => $goal_reached_days,
        'streak' => $current_streak
    ];
}

/**
 * 生成点评
 */
function generate_comment($type, $data_array, $labels = []) {
    if (empty($data_array)) return "暂无数据。";
    $max_val = max($data_array);
    if ($max_val == 0) return "这段时间没有阅读记录，继续加油！";

    $max_keys = array_keys($data_array, $max_val);
    $best_idx = $max_keys[0];

    if ($type === 'today') {
        $period = isset($labels[$best_idx]) ? $labels[$best_idx] : '某时段';
        return "太棒了！你在 <strong>{$period}</strong> 精力最集中，阅读了 {$max_val} 分钟。";
    } elseif ($type === 'week') {
        return "本周 <strong>{$best_idx}号</strong> 是你的阅读巅峰，达到了 {$max_val} 分钟！";
    }
    return "阅读是最好的投资。";
}
?>