<?php
$pageTitle = $pageTitle ?? 'ホーム';
$appName = $appName ?? 'タスク管理・進捗管理アプリ';
$message = $message ?? 'カレンダー付きホーム画面です。';
$links = $links ?? [];

if (!function_exists('view_home_h')) {
    function view_home_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('view_home_format_datetime')) {
    function view_home_format_datetime(?string $value): string
    {
        if (!$value) {
            return '未設定';
        }
        $ts = strtotime($value);
        return $ts === false ? (string)$value : date('Y/m/d H:i', $ts);
    }
}

if (!function_exists('view_home_format_short_datetime')) {
    function view_home_format_short_datetime(?string $value): string
    {
        if (!$value) {
            return '未設定';
        }
        $ts = strtotime($value);
        return $ts === false ? (string)$value : date('m/d H:i', $ts);
    }
}

if (!function_exists('view_home_format_japanese_date')) {
    function view_home_format_japanese_date(?string $value): string
    {
        if (!$value) {
            return '未設定';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return (string)$value;
        }
        $weekMap = ['日', '月', '火', '水', '木', '金', '土'];
        return date('Y年n月j日', $ts) . '（' . $weekMap[(int)date('w', $ts)] . '）';
    }
}

if (!function_exists('view_home_status_label')) {
    function view_home_status_label(string $status): string
    {
        return match ($status) {
            'not_started' => '未着手',
            'in_progress' => '進行中',
            'completed' => '完了',
            default => '不明',
        };
    }
}

if (!function_exists('view_home_status_class')) {
    function view_home_status_class(string $status): string
    {
        return match ($status) {
            'not_started' => 'vhm-badge vhm-badge-gray',
            'in_progress' => 'vhm-badge vhm-badge-blue',
            'completed' => 'vhm-badge vhm-badge-green',
            default => 'vhm-badge vhm-badge-gray',
        };
    }
}

if (!function_exists('view_home_build_url')) {
    function view_home_build_url(string $scriptName, string $controller, string $action = 'index', array $params = []): string
    {
        $query = array_merge([
            'controller' => $controller,
            'action' => $action,
        ], $params);

        return $scriptName . '?' . http_build_query($query);
    }
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/task_app/public/index.php');

$mvcHomeUrl = $links['home'] ?? view_home_build_url($scriptName, 'home', 'index');
$mvcTaskListUrl = $links['task_list'] ?? $links['list'] ?? view_home_build_url($scriptName, 'task', 'index');
$mvcTaskCreateUrl = $links['task_create'] ?? $links['create'] ?? view_home_build_url($scriptName, 'task', 'create');

$summary = $summary ?? [
    'total' => 0,
    'not_started' => 0,
    'in_progress' => 0,
    'completed' => 0,
];

$todayTasks = (isset($todayTasks) && is_array($todayTasks)) ? $todayTasks : [];
$selectedDateTasks = (isset($selectedDateTasks) && is_array($selectedDateTasks)) ? $selectedDateTasks : [];
$calendarTasksByDate = (isset($calendarTasksByDate) && is_array($calendarTasksByDate)) ? $calendarTasksByDate : [];

$today = date('Y-m-d');

$viewYear = isset($viewYear) ? (int)$viewYear : (int)($_GET['year'] ?? date('Y'));
$viewMonth = isset($viewMonth) ? (int)$viewMonth : (int)($_GET['month'] ?? date('n'));

if ($viewMonth < 1 || $viewMonth > 12) {
    $viewMonth = (int)date('n');
}
if ($viewYear < 2000 || $viewYear > 2100) {
    $viewYear = (int)date('Y');
}

$selectedDate = isset($selectedDate) ? (string)$selectedDate : (string)($_GET['date'] ?? $today);
if (strtotime($selectedDate) === false) {
    $selectedDate = $today;
}

$monthStart = sprintf('%04d-%02d-01', $viewYear, $viewMonth);
$monthStartTimestamp = strtotime($monthStart);
$daysInMonth = (int)date('t', $monthStartTimestamp);
$startWeekday = (int)date('w', $monthStartTimestamp);

$totalCells = (int)ceil(($startWeekday + $daysInMonth) / 7) * 7;
$prevMonthTimestamp = strtotime('-1 month', $monthStartTimestamp);
$nextMonthTimestamp = strtotime('+1 month', $monthStartTimestamp);

$prevYear = (int)date('Y', $prevMonthTimestamp);
$prevMonth = (int)date('n', $prevMonthTimestamp);
$nextYear = (int)date('Y', $nextMonthTimestamp);
$nextMonth = (int)date('n', $nextMonthTimestamp);

$prevMonthUrl = view_home_build_url($scriptName, 'home', 'index', [
    'year' => $prevYear,
    'month' => $prevMonth,
    'date' => sprintf('%04d-%02d-01', $prevYear, $prevMonth),
]);

$nextMonthUrl = view_home_build_url($scriptName, 'home', 'index', [
    'year' => $nextYear,
    'month' => $nextMonth,
    'date' => sprintf('%04d-%02d-01', $nextYear, $nextMonth),
]);

$todayHomeUrl = view_home_build_url($scriptName, 'home', 'index', [
    'year' => (int)date('Y'),
    'month' => (int)date('n'),
    'date' => $today,
]);

$errorMessage = trim((string)($errorMessage ?? ($_GET['error'] ?? '')));
$successMessage = trim((string)($successMessage ?? ''));

$weekLabels = ['日', '月', '火', '水', '木', '金', '土'];
?>

<style>
    .vhm-page {
        width: min(1280px, 94%);
        margin: 36px auto 80px;
        font-family: "Yu Gothic", "Hiragino Sans", "Meiryo", sans-serif;
        color: #1e293b;
    }
    .vhm-hero,
    .vhm-card,
    .vhm-stat-card {
        background: #ffffff;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }
    .vhm-hero {
        padding: 34px;
        margin-bottom: 24px;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        border: 1px solid #dbeafe;
    }
    .vhm-eyebrow {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.88rem;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .vhm-title {
        margin: 0 0 12px;
        font-size: 2rem;
        line-height: 1.4;
        color: #0f172a;
    }
    .vhm-text {
        margin: 0;
        color: #475569;
        line-height: 1.9;
    }
    .vhm-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 20px;
    }
    .vhm-btn {
        display: inline-block;
        text-decoration: none;
        padding: 12px 18px;
        border-radius: 12px;
        font-weight: 700;
        transition: 0.2s ease;
    }
    .vhm-btn:hover {
        transform: translateY(-1px);
    }
    .vhm-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
    }
    .vhm-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }
    .vhm-message {
        padding: 16px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-weight: 700;
        line-height: 1.8;
    }
    .vhm-message-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .vhm-message-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .vhm-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }
    .vhm-stat-card {
        padding: 24px;
        text-align: center;
    }
    .vhm-stat-label {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 12px;
    }
    .vhm-stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #2563eb;
    }
    .vhm-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.95fr);
        gap: 22px;
        align-items: start;
    }
    .vhm-card {
        padding: 24px;
    }
    .vhm-section-title {
        margin: 0 0 14px;
        font-size: 1.2rem;
        color: #0f172a;
    }
    .vhm-subtext {
        margin: 0 0 16px;
        color: #64748b;
        line-height: 1.8;
        font-size: 0.92rem;
    }
    .vhm-calendar-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .vhm-calendar-title {
        margin: 0;
        font-size: 1.35rem;
        color: #0f172a;
    }
    .vhm-calendar-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .vhm-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 10px;
    }
    .vhm-week {
        text-align: center;
        font-size: 0.88rem;
        font-weight: 700;
        color: #64748b;
        padding: 10px 6px;
        background: #f8fafc;
        border-radius: 12px;
    }
    .vhm-day-cell {
        min-height: 150px;
        padding: 10px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-sizing: border-box;
    }
    .vhm-day-other {
        background: #f8fafc;
        opacity: 0.52;
    }
    .vhm-day-selected {
        border: 2px solid #60a5fa;
        background: #eff6ff;
    }
    .vhm-day-today {
        border: 2px solid #2563eb;
        background: #dbeafe;
    }
    .vhm-day-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }
    .vhm-day-number-link {
        text-decoration: none;
        color: #0f172a;
        font-weight: 800;
        font-size: 1rem;
        display: inline-block;
        padding: 2px 6px;
        border-radius: 8px;
    }
    .vhm-day-number-link:hover {
        background: rgba(37, 99, 235, 0.08);
    }
    .vhm-day-label {
        font-size: 0.72rem;
        color: #2563eb;
        font-weight: 700;
    }
    .vhm-calendar-task-list {
        display: grid;
        gap: 6px;
        margin-top: 2px;
    }
    .vhm-calendar-task-mini {
        background: #ffffff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 6px 7px;
        display: grid;
        gap: 2px;
    }
    .vhm-calendar-task-name,
    .vhm-calendar-task-due,
    .vhm-calendar-task-memo {
        font-size: 0.72rem;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .vhm-calendar-task-name {
        font-weight: 700;
        color: #1e3a8a;
    }
    .vhm-calendar-task-due {
        color: #334155;
    }
    .vhm-calendar-task-memo {
        color: #475569;
    }
    .vhm-calendar-more {
        font-size: 0.74rem;
        color: #64748b;
        font-weight: 700;
        padding-left: 2px;
    }
    .vhm-side {
        display: grid;
        gap: 22px;
    }
    .vhm-task-list {
        display: grid;
        gap: 14px;
    }
    .vhm-task-card {
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 16px;
        padding: 16px;
    }
    .vhm-task-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .vhm-task-name {
        margin: 0;
        font-size: 1rem;
        color: #0f172a;
        line-height: 1.6;
    }
    .vhm-badge {
        display: inline-block;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
    }
    .vhm-badge-gray {
        background: #e2e8f0;
        color: #334155;
    }
    .vhm-badge-blue {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .vhm-badge-green {
        background: #dcfce7;
        color: #166534;
    }
    .vhm-task-meta {
        display: grid;
        gap: 5px;
        color: #475569;
        font-size: 0.9rem;
        line-height: 1.7;
        margin-bottom: 12px;
    }
    .vhm-task-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .vhm-mini-btn {
        display: inline-block;
        text-decoration: none;
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 0.88rem;
        font-weight: 700;
    }
    .vhm-mini-btn-primary {
        background: #2563eb;
        color: #ffffff;
    }
    .vhm-mini-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }
    .vhm-empty {
        padding: 16px 18px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
        line-height: 1.8;
    }
    .vhm-links-block {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
    }
    @media (max-width: 1080px) {
        .vhm-layout {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 760px) {
        .vhm-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .vhm-hero,
        .vhm-card,
        .vhm-stat-card {
            padding: 20px;
        }
        .vhm-title {
            font-size: 1.6rem;
        }
        .vhm-calendar-grid {
            gap: 8px;
        }
        .vhm-day-cell {
            min-height: 128px;
            padding: 8px;
        }
        .vhm-calendar-task-name,
        .vhm-calendar-task-due,
        .vhm-calendar-task-memo {
            font-size: 0.68rem;
        }
    }
</style>

<div class="vhm-page">
    <section class="vhm-hero">
        <span class="vhm-eyebrow">MVC ホーム</span>
        <h1 class="vhm-title"><?php echo view_home_h($appName); ?></h1>
        <p class="vhm-text"><?php echo view_home_h($message); ?></p>

        <div class="vhm-actions">
            <a href="<?php echo view_home_h($mvcHomeUrl); ?>" class="vhm-btn vhm-btn-primary">ホーム</a>
            <a href="<?php echo view_home_h($mvcTaskListUrl); ?>" class="vhm-btn vhm-btn-secondary">タスク一覧</a>
            <a href="<?php echo view_home_h($mvcTaskCreateUrl); ?>" class="vhm-btn vhm-btn-secondary">タスク追加</a>
        </div>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="vhm-message vhm-message-success">
            <?php echo view_home_h($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="vhm-message vhm-message-error">
            <?php echo view_home_h($errorMessage); ?>
        </div>
    <?php endif; ?>

    <section class="vhm-stats">
        <div class="vhm-stat-card">
            <div class="vhm-stat-label">全タスク数</div>
            <div class="vhm-stat-value"><?php echo (int)($summary['total'] ?? 0); ?></div>
        </div>
        <div class="vhm-stat-card">
            <div class="vhm-stat-label">未着手</div>
            <div class="vhm-stat-value"><?php echo (int)($summary['not_started'] ?? 0); ?></div>
        </div>
        <div class="vhm-stat-card">
            <div class="vhm-stat-label">進行中</div>
            <div class="vhm-stat-value"><?php echo (int)($summary['in_progress'] ?? 0); ?></div>
        </div>
        <div class="vhm-stat-card">
            <div class="vhm-stat-label">完了</div>
            <div class="vhm-stat-value"><?php echo (int)($summary['completed'] ?? 0); ?></div>
        </div>
    </section>

    <div class="vhm-layout">
        <section class="vhm-card">
            <div class="vhm-calendar-head">
                <div>
                    <h2 class="vhm-calendar-title"><?php echo $viewYear; ?>年<?php echo $viewMonth; ?>月 カレンダー</h2>
                    <p class="vhm-subtext">
                        カレンダーには「タスク名1行・終了予定日時1行・メモ1行」で表示します。
                    </p>
                </div>

                <div class="vhm-calendar-nav">
                    <a href="<?php echo view_home_h($prevMonthUrl); ?>" class="vhm-btn vhm-btn-secondary">← 前月</a>
                    <a href="<?php echo view_home_h($todayHomeUrl); ?>" class="vhm-btn vhm-btn-primary">今月へ戻る</a>
                    <a href="<?php echo view_home_h($nextMonthUrl); ?>" class="vhm-btn vhm-btn-secondary">次月 →</a>
                </div>
            </div>

            <div class="vhm-calendar-grid" style="margin-bottom: 10px;">
                <?php foreach ($weekLabels as $weekLabel): ?>
                    <div class="vhm-week"><?php echo view_home_h($weekLabel); ?></div>
                <?php endforeach; ?>
            </div>

            <div class="vhm-calendar-grid">
                <?php for ($cell = 0; $cell < $totalCells; $cell++): ?>
                    <?php
                    $dayOffset = $cell - $startWeekday + 1;
                    $cellTimestamp = strtotime(sprintf('%04d-%02d-01 %+d days', $viewYear, $viewMonth, $dayOffset - 1));
                    $cellDate = date('Y-m-d', $cellTimestamp);
                    $cellDay = (int)date('j', $cellTimestamp);
                    $isCurrentMonth = ((int)date('n', $cellTimestamp) === $viewMonth);
                    $isToday = ($cellDate === $today);
                    $isSelected = ($cellDate === $selectedDate);

                    $dayClasses = ['vhm-day-cell'];
                    if (!$isCurrentMonth) {
                        $dayClasses[] = 'vhm-day-other';
                    }
                    if ($isToday) {
                        $dayClasses[] = 'vhm-day-today';
                    }
                    if ($isSelected) {
                        $dayClasses[] = 'vhm-day-selected';
                    }

                    $cellUrl = view_home_build_url($scriptName, 'home', 'index', [
                        'year' => (int)date('Y', $cellTimestamp),
                        'month' => (int)date('n', $cellTimestamp),
                        'date' => $cellDate,
                    ]);

                    $cellTasks = $calendarTasksByDate[$cellDate] ?? [];
                    ?>
                    <div class="<?php echo view_home_h(implode(' ', $dayClasses)); ?>">
                        <div class="vhm-day-top">
                            <a href="<?php echo view_home_h($cellUrl); ?>" class="vhm-day-number-link">
                                <?php echo $cellDay; ?>
                            </a>
                            <?php if ($isToday): ?>
                                <span class="vhm-day-label">今日</span>
                            <?php elseif ($isSelected): ?>
                                <span class="vhm-day-label">選択中</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($cellTasks)): ?>
                            <div class="vhm-calendar-task-list">
                                <?php foreach (array_slice($cellTasks, 0, 2) as $calendarTask): ?>
                                    <?php
                                    $calendarTaskTitle = (string)($calendarTask['title'] ?? '名称未設定');
                                    $calendarTaskDue = (string)($calendarTask['due_datetime'] ?? '');
                                    $calendarTaskMemo = trim((string)($calendarTask['step_memo'] ?? ''));
                                    if ($calendarTaskMemo === '') {
                                        $calendarTaskMemo = '工程メモ未設定';
                                    }
                                    ?>
                                    <div class="vhm-calendar-task-mini">
                                        <div class="vhm-calendar-task-name"><?php echo view_home_h($calendarTaskTitle); ?></div>
                                        <div class="vhm-calendar-task-due">終了: <?php echo view_home_h(view_home_format_short_datetime($calendarTaskDue)); ?></div>
                                        <div class="vhm-calendar-task-memo">メモ: <?php echo view_home_h($calendarTaskMemo); ?></div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($cellTasks) > 2): ?>
                                    <div class="vhm-calendar-more">+<?php echo count($cellTasks) - 2; ?> 件</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <aside class="vhm-side">
            <section class="vhm-card">
                <h2 class="vhm-section-title">本日のタスク</h2>
                <p class="vhm-subtext"><?php echo view_home_h(view_home_format_japanese_date($today)); ?></p>

                <?php if (empty($todayTasks)): ?>
                    <div class="vhm-empty">
                        本日の対象タスクはありません。
                    </div>
                <?php else: ?>
                    <div class="vhm-task-list">
                        <?php foreach ($todayTasks as $taskItem): ?>
                            <?php
                            $taskId = (int)($taskItem['id'] ?? 0);
                            $taskTitle = (string)($taskItem['title'] ?? '名称未設定');
                            $taskStatus = (string)($taskItem['status'] ?? 'not_started');
                            $taskCurrent = (int)($taskItem['current_progress'] ?? 0);
                            $taskMax = (int)($taskItem['progress_max'] ?? 0);
                            $taskDue = (string)($taskItem['due_datetime'] ?? '');
                            $taskMemo = trim((string)($taskItem['step_memo'] ?? ($taskItem['next_step_memo'] ?? '')));
                            if ($taskMemo === '') {
                                $taskMemo = '工程メモ未設定';
                            }
                            $taskEditUrl = ($taskId > 0)
                                ? view_home_build_url($scriptName, 'task', 'edit', ['task_id' => $taskId])
                                : $mvcTaskListUrl;
                            ?>
                            <div class="vhm-task-card">
                                <div class="vhm-task-top">
                                    <h3 class="vhm-task-name"><?php echo view_home_h($taskTitle); ?></h3>
                                    <span class="<?php echo view_home_h(view_home_status_class($taskStatus)); ?>">
                                        <?php echo view_home_h(view_home_status_label($taskStatus)); ?>
                                    </span>
                                </div>

                                <div class="vhm-task-meta">
                                    <div>終了予定日時: <?php echo view_home_h(view_home_format_datetime($taskDue)); ?></div>
                                    <div>メモ: <?php echo view_home_h($taskMemo); ?></div>
                                    <div>進捗: <?php echo $taskCurrent; ?> / <?php echo $taskMax; ?></div>
                                </div>

                                <div class="vhm-task-actions">
                                    <a href="<?php echo view_home_h($taskEditUrl); ?>" class="vhm-mini-btn vhm-mini-btn-primary">工程日時編集</a>
                                    <a href="<?php echo view_home_h($mvcTaskListUrl); ?>" class="vhm-mini-btn vhm-mini-btn-secondary">一覧を見る</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="vhm-card">
                <h2 class="vhm-section-title">選択日のタスク</h2>
                <p class="vhm-subtext"><?php echo view_home_h(view_home_format_japanese_date($selectedDate)); ?></p>

                <?php if (empty($selectedDateTasks)): ?>
                    <div class="vhm-empty">
                        選択日の対象タスクはありません。
                    </div>
                <?php else: ?>
                    <div class="vhm-task-list">
                        <?php foreach ($selectedDateTasks as $taskItem): ?>
                            <?php
                            $taskId = (int)($taskItem['id'] ?? 0);
                            $taskTitle = (string)($taskItem['title'] ?? '名称未設定');
                            $taskStatus = (string)($taskItem['status'] ?? 'not_started');
                            $taskCurrent = (int)($taskItem['current_progress'] ?? 0);
                            $taskMax = (int)($taskItem['progress_max'] ?? 0);
                            $taskDue = (string)($taskItem['due_datetime'] ?? '');
                            $taskMemo = trim((string)($taskItem['step_memo'] ?? ($taskItem['next_step_memo'] ?? '')));
                            if ($taskMemo === '') {
                                $taskMemo = '工程メモ未設定';
                            }
                            $taskEditUrl = ($taskId > 0)
                                ? view_home_build_url($scriptName, 'task', 'edit', ['task_id' => $taskId])
                                : $mvcTaskListUrl;
                            ?>
                            <div class="vhm-task-card">
                                <div class="vhm-task-top">
                                    <h3 class="vhm-task-name"><?php echo view_home_h($taskTitle); ?></h3>
                                    <span class="<?php echo view_home_h(view_home_status_class($taskStatus)); ?>">
                                        <?php echo view_home_h(view_home_status_label($taskStatus)); ?>
                                    </span>
                                </div>

                                <div class="vhm-task-meta">
                                    <div>終了予定日時: <?php echo view_home_h(view_home_format_datetime($taskDue)); ?></div>
                                    <div>メモ: <?php echo view_home_h($taskMemo); ?></div>
                                    <div>進捗: <?php echo $taskCurrent; ?> / <?php echo $taskMax; ?></div>
                                </div>

                                <div class="vhm-task-actions">
                                    <a href="<?php echo view_home_h($taskEditUrl); ?>" class="vhm-mini-btn vhm-mini-btn-primary">工程日時編集</a>
                                    <a href="<?php echo view_home_h($mvcTaskListUrl); ?>" class="vhm-mini-btn vhm-mini-btn-secondary">一覧を見る</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="vhm-card">
                <h2 class="vhm-section-title">クイック導線</h2>
                <p class="vhm-subtext">タスクの作成や一覧確認にすぐ移動できます。</p>
                <div class="vhm-links-block">
                    <a href="<?php echo view_home_h($mvcTaskCreateUrl); ?>" class="vhm-btn vhm-btn-primary">タスクを追加する</a>
                    <a href="<?php echo view_home_h($mvcTaskListUrl); ?>" class="vhm-btn vhm-btn-secondary">タスク一覧へ</a>
                </div>
            </section>
        </aside>
    </div>
</div>
