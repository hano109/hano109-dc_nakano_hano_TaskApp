<?php
require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Tokyo');

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateTimeValue(?string $value): string
{
    if (!$value) {
        return '未設定';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }

    return date('Y/m/d H:i', $ts);
}

function formatShortDateTime(?string $value): string
{
    if (!$value) {
        return '未設定';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }

    return date('m/d H:i', $ts);
}

function formatJapaneseDate(?string $value): string
{
    if (!$value) {
        return '未設定';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }

    $weekNames = ['日', '月', '火', '水', '木', '金', '土'];
    return date('Y年n月j日', $ts) . '（' . $weekNames[(int)date('w', $ts)] . '）';
}

function getStatusLabel(string $status): string
{
    return match ($status) {
        'not_started' => '未着手',
        'in_progress' => '進行中',
        'completed' => '完了',
        default => '不明',
    };
}

function getStatusBadgeClass(string $status): string
{
    return match ($status) {
        'not_started' => 'badge badge-gray',
        'in_progress' => 'badge badge-blue',
        'completed' => 'badge badge-green',
        default => 'badge badge-gray',
    };
}

function buildIndexUrl(int $year, int $month, ?string $date = null, array $extra = []): string
{
    $query = [
        'year' => $year,
        'month' => $month,
    ];

    if ($date !== null && $date !== '') {
        $query['date'] = $date;
    }

    foreach ($extra as $key => $value) {
        if ($value !== null && $value !== '') {
            $query[$key] = $value;
        }
    }

    return 'index.php?' . http_build_query($query);
}

$currentDate = date('Y-m-d');
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

$viewYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$viewMonth = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;

if ($viewYear < 2000 || $viewYear > 2100) {
    $viewYear = $currentYear;
}
if ($viewMonth < 1 || $viewMonth > 12) {
    $viewMonth = $currentMonth;
}

$calendarBase = DateTime::createFromFormat('Y-n-j', $viewYear . '-' . $viewMonth . '-1');
if (!$calendarBase) {
    $calendarBase = new DateTime(date('Y-m-01'));
}

$requestedDate = trim((string)($_GET['date'] ?? ''));
$selectedDate = '';

if ($requestedDate !== '') {
    $requestedTs = strtotime($requestedDate);
    if ($requestedTs !== false) {
        $selectedDate = date('Y-m-d', $requestedTs);
    }
}

if ($selectedDate === '') {
    if ($viewYear === $currentYear && $viewMonth === $currentMonth) {
        $selectedDate = $currentDate;
    } else {
        $selectedDate = $calendarBase->format('Y-m-01');
    }
}

$selectedTs = strtotime($selectedDate);
if ($selectedTs === false) {
    $selectedDate = $calendarBase->format('Y-m-01');
    $selectedTs = strtotime($selectedDate);
}

if ((int)date('Y', $selectedTs) !== $viewYear || (int)date('n', $selectedTs) !== $viewMonth) {
    $selectedDate = $calendarBase->format('Y-m-01');
}

$monthStart = $calendarBase->format('Y-m-01');
$monthEnd = $calendarBase->format('Y-m-t');
$daysInMonth = (int)$calendarBase->format('t');
$startWeekday = (int)$calendarBase->format('w');

$prevMonthDate = (clone $calendarBase)->modify('-1 month');
$nextMonthDate = (clone $calendarBase)->modify('+1 month');

$totalCells = (int)ceil(($startWeekday + $daysInMonth) / 7) * 7;

$totalTasks = 0;
$inProgressTasks = 0;
$completedTasks = 0;

$calendarTasksByDate = [];
$todayTasks = [];
$selectedDateTasks = [];

$errorMessage = '';
$successMessage = '';

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = '進捗を更新しました。';
}
if (isset($_GET['completed']) && $_GET['completed'] === '1') {
    $successMessage = 'タスクを完了にしました。';
}
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $successMessage = 'タスクを削除しました。';
}
if (!empty($_GET['error'])) {
    $errorMessage = (string)$_GET['error'];
}

try {
    $pdo = getPDO();

    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tasks");
    $row = $stmt->fetch();
    $totalTasks = (int)($row['cnt'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tasks WHERE status = 'in_progress'");
    $row = $stmt->fetch();
    $inProgressTasks = (int)($row['cnt'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM tasks WHERE status = 'completed'");
    $row = $stmt->fetch();
    $completedTasks = (int)($row['cnt'] ?? 0);

    $taskByDateSql = "
        SELECT
            a.assignment_date,
            a.step_no,
            COALESCE(NULLIF(a.step_memo, ''), NULLIF(sm.memo, ''), CONCAT('工程 ', a.step_no)) AS display_memo,
            t.id,
            t.title,
            t.due_datetime,
            t.current_progress,
            t.progress_max,
            t.status
        FROM task_calendar_assignments a
        INNER JOIN tasks t
            ON a.task_id = t.id
        LEFT JOIN task_step_memos sm
            ON sm.task_id = t.id
           AND sm.step_no = a.step_no
        WHERE a.assignment_date BETWEEN :month_start AND :month_end
          AND a.step_no > t.current_progress
        ORDER BY a.assignment_date ASC, a.step_no ASC, t.id DESC
    ";
    $stmt = $pdo->prepare($taskByDateSql);
    $stmt->execute([
        ':month_start' => $monthStart . ' 00:00:00',
        ':month_end'   => $monthEnd . ' 23:59:59',
    ]);

    foreach ($stmt->fetchAll() as $row) {
        $dateKey = date('Y-m-d', strtotime($row['assignment_date']));
        $calendarTasksByDate[$dateKey][] = $row;
    }

    $todayAssignedSql = "
        SELECT
            t.id,
            t.title,
            t.description,
            t.current_progress,
            t.progress_max,
            t.status,
            t.start_datetime,
            t.due_datetime,
            a.step_no,
            a.assignment_date,
            COALESCE(NULLIF(a.step_memo, ''), NULLIF(sm.memo, ''), CONCAT('工程 ', a.step_no)) AS step_memo,
            0 AS is_fallback,
            0 AS remaining_unassigned_extra_count
        FROM task_calendar_assignments a
        INNER JOIN tasks t
            ON a.task_id = t.id
        LEFT JOIN task_step_memos sm
            ON sm.task_id = t.id
           AND sm.step_no = a.step_no
        WHERE DATE(a.assignment_date) = :target_date
          AND a.step_no > t.current_progress
        ORDER BY a.assignment_date ASC, a.step_no ASC, t.id DESC
    ";
    $stmt = $pdo->prepare($todayAssignedSql);
    $stmt->execute([':target_date' => $currentDate]);
    $todayAssignedTasks = $stmt->fetchAll();

    $todayFallbackSql = "
        SELECT
            t.id,
            t.title,
            t.description,
            t.current_progress,
            t.progress_max,
            t.status,
            t.start_datetime,
            t.due_datetime,
            first_unassigned.step_no,
            NULL AS assignment_date,
            COALESCE(NULLIF(sm.memo, ''), CONCAT('工程 ', first_unassigned.step_no)) AS step_memo,
            1 AS is_fallback,
            GREATEST(unassigned_counts.unassigned_count - 1, 0) AS remaining_unassigned_extra_count
        FROM tasks t
        INNER JOIN (
            SELECT
                m.task_id,
                MIN(m.step_no) AS step_no
            FROM task_step_memos m
            INNER JOIN tasks tx
                ON tx.id = m.task_id
            LEFT JOIN task_calendar_assignments a
                ON a.task_id = m.task_id
               AND a.step_no = m.step_no
            WHERE m.step_no > tx.current_progress
              AND a.id IS NULL
            GROUP BY m.task_id
        ) AS first_unassigned
            ON first_unassigned.task_id = t.id
        INNER JOIN (
            SELECT
                m.task_id,
                COUNT(*) AS unassigned_count
            FROM task_step_memos m
            INNER JOIN tasks tx
                ON tx.id = m.task_id
            LEFT JOIN task_calendar_assignments a
                ON a.task_id = m.task_id
               AND a.step_no = m.step_no
            WHERE m.step_no > tx.current_progress
              AND a.id IS NULL
            GROUP BY m.task_id
        ) AS unassigned_counts
            ON unassigned_counts.task_id = t.id
        LEFT JOIN task_step_memos sm
            ON sm.task_id = t.id
           AND sm.step_no = first_unassigned.step_no
        WHERE t.current_progress < t.progress_max
          AND NOT EXISTS (
              SELECT 1
              FROM task_calendar_assignments ta
              WHERE ta.task_id = t.id
                AND DATE(ta.assignment_date) = :target_date
                AND ta.step_no > t.current_progress
          )
        ORDER BY t.id DESC
    ";
    $stmt = $pdo->prepare($todayFallbackSql);
    $stmt->execute([':target_date' => $currentDate]);
    $todayFallbackTasks = $stmt->fetchAll();

    $todayTasks = array_merge($todayAssignedTasks, $todayFallbackTasks);

    $selectedDateSql = "
        SELECT
            t.id,
            t.title,
            t.description,
            t.current_progress,
            t.progress_max,
            t.status,
            t.start_datetime,
            t.due_datetime,
            a.step_no,
            a.assignment_date,
            COALESCE(NULLIF(a.step_memo, ''), NULLIF(sm.memo, ''), CONCAT('工程 ', a.step_no)) AS step_memo
        FROM task_calendar_assignments a
        INNER JOIN tasks t
            ON a.task_id = t.id
        LEFT JOIN task_step_memos sm
            ON sm.task_id = t.id
           AND sm.step_no = a.step_no
        WHERE DATE(a.assignment_date) = :target_date
          AND a.step_no > t.current_progress
        ORDER BY a.assignment_date ASC, a.step_no ASC, t.id DESC
    ";
    $stmt = $pdo->prepare($selectedDateSql);
    $stmt->execute([':target_date' => $selectedDate]);
    $selectedDateTasks = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = 'データベースの読み込みに失敗しました: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タスク管理アプリ | ホーム</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-section {
            margin-bottom: 32px;
        }

        .calendar-card {
            overflow: hidden;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .calendar-nav-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
        }

        .calendar-nav-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        .calendar-weekday {
            text-align: center;
            font-weight: 700;
            color: #64748b;
            padding: 8px 0;
        }

        .calendar-weekday.sun {
            color: #dc2626;
        }

        .calendar-weekday.sat {
            color: #2563eb;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .calendar-cell {
            min-height: 145px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 10px;
            position: relative;
            transition: 0.2s ease;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .calendar-cell:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        .calendar-cell.is-empty {
            background: #f8fafc;
            border-style: dashed;
            min-height: 145px;
        }

        .calendar-cell.is-today {
            border: 2px solid #2563eb;
            background: #eff6ff;
        }

        .calendar-cell.is-selected {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .calendar-day-link {
            display: block;
            width: 100%;
            height: 100%;
            color: inherit;
        }

        .calendar-day-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
        }

        .calendar-cell.is-today .calendar-day-number {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .calendar-task-list {
            margin-top: 6px;
            display: grid;
            gap: 6px;
        }

        .calendar-task-mini {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 6px;
            display: grid;
            gap: 2px;
        }

        .calendar-task-name,
        .calendar-task-due,
        .calendar-task-memo {
            font-size: 0.72rem;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .calendar-task-name {
            font-weight: 700;
            color: #1e3a8a;
        }

        .calendar-task-due {
            color: #334155;
        }

        .calendar-task-memo {
            color: #475569;
        }

        .calendar-more {
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 700;
            margin-top: 2px;
        }

        .calendar-empty-note {
            margin-top: 8px;
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .today-board {
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            border: 1px solid #bfdbfe;
        }

        .section-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .section-sub-note {
            color: #64748b;
            font-size: 0.95rem;
        }

        .today-task-list,
        .selected-task-list {
            display: grid;
            gap: 18px;
        }

        .status-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 8px;
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 16px 0;
        }

        .info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 6px;
            color: #1e293b;
        }

        .note-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .note-badge.gray {
            background: #e2e8f0;
            color: #334155;
        }

        .note-badge.blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .subtle-text {
            color: #64748b;
            font-size: 0.94rem;
        }

        .action-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .inline-form {
            margin: 0;
        }

        .mini-btn {
            min-width: 88px;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        .mini-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .danger-btn {
            background: #ef4444;
            color: #fff;
            box-shadow: 0 6px 14px rgba(239, 68, 68, 0.22);
        }

        .danger-btn:hover {
            background: #dc2626;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .empty-card-text {
            color: #64748b;
        }

        @media (max-width: 960px) {
            .calendar-grid,
            .calendar-weekdays {
                gap: 8px;
            }

            .calendar-cell {
                min-height: 125px;
                padding: 8px;
            }
        }

        @media (max-width: 768px) {
            .calendar-grid,
            .calendar-weekdays {
                grid-template-columns: repeat(2, 1fr);
            }

            .calendar-weekdays {
                display: none;
            }

            .calendar-cell {
                min-height: 150px;
            }
        }

        @media (max-width: 520px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }

            .calendar-cell {
                min-height: 140px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-inner">
            <h1 class="app-title">タスク管理・進捗管理アプリ</h1>
            <nav class="nav">
                <a href="index.php">ホーム</a>
                <a href="task_create.php">タスク追加</a>
                <a href="task_list.php">タスク一覧</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="hero page-section">
            <h2>ホーム</h2>
            <p>月間カレンダー、本日のタスク、選択日の工程予定をまとめて確認できます。</p>
            <div class="button-group">
                <a href="task_create.php" class="btn">＋ タスクを追加する</a>
                <a href="task_list.php" class="btn btn-secondary">タスク一覧を見る</a>
            </div>
        </section>

        <?php if ($successMessage !== ''): ?>
            <section class="message success-message page-section">
                <p><?php echo h($successMessage); ?></p>
            </section>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <section class="message error-message page-section">
                <p><?php echo h($errorMessage); ?></p>
            </section>
        <?php endif; ?>

        <section class="card today-board page-section">
            <div class="section-header-row">
                <div>
                    <h2 class="section-title" style="margin-bottom: 8px;">本日のタスク</h2>
                    <p class="section-sub-note"><?php echo h(formatJapaneseDate($currentDate)); ?> の予定です。</p>
                </div>
            </div>

            <?php if (empty($todayTasks)): ?>
                <div class="card">
                    <p class="empty-card-text">本日表示するタスクはありません。</p>
                </div>
            <?php else: ?>
                <div class="today-task-list">
                    <?php foreach ($todayTasks as $task): ?>
                        <?php
                        $taskId = (int)$task['id'];
                        $currentProgress = (int)$task['current_progress'];
                        $progressMax = (int)$task['progress_max'];
                        $status = (string)$task['status'];
                        $progressPercent = $progressMax > 0 ? ($currentProgress / $progressMax) * 100 : 0;
                        if ($progressPercent < 0) {
                            $progressPercent = 0;
                        }
                        if ($progressPercent > 100) {
                            $progressPercent = 100;
                        }
                        $isFallback = (int)($task['is_fallback'] ?? 0) === 1;
                        $extraCount = (int)($task['remaining_unassigned_extra_count'] ?? 0);
                        ?>
                        <article class="card task-item">
                            <div class="task-header">
                                <div>
                                    <h3 class="task-title">
                                        <?php echo h($task['title']); ?>
                                        ：
                                        <?php echo h($task['step_memo']); ?>
                                    </h3>
                                    <div class="status-row">
                                        <span class="<?php echo h(getStatusBadgeClass($status)); ?>">
                                            <?php echo h(getStatusLabel($status)); ?>
                                        </span>
                                        <span class="task-progress">進捗: <?php echo $currentProgress; ?> / <?php echo $progressMax; ?></span>
                                        <?php if ($isFallback): ?>
                                            <span class="note-badge gray">日付未設定の未完了工程から表示</span>
                                            <?php if ($extraCount > 0): ?>
                                                <span class="note-badge blue">+<?php echo $extraCount; ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-box">
                                    <strong>本日予定工程</strong>
                                    <span><?php echo (int)$task['step_no']; ?>. <?php echo h($task['step_memo']); ?></span>
                                </div>
                                <div class="info-box">
                                    <strong>終了予定日時</strong>
                                    <span><?php echo h(formatDateTimeValue($task['due_datetime'])); ?></span>
                                </div>
                                <div class="info-box">
                                    <strong>工程予定日時</strong>
                                    <span>
                                        <?php
                                        if (!empty($task['assignment_date'])) {
                                            echo h(formatDateTimeValue($task['assignment_date']));
                                        } else {
                                            echo '未設定';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="progress-bar progress-margin">
                                <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                            </div>

                            <div class="action-row">
                                <form action="task_update_progress.php" method="post" class="inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="increase">
                                    <input type="hidden" name="return_to" value="index">
                                    <input type="hidden" name="year" value="<?php echo $viewYear; ?>">
                                    <input type="hidden" name="month" value="<?php echo $viewMonth; ?>">
                                    <input type="hidden" name="date" value="<?php echo h($selectedDate); ?>">
                                    <button type="submit" class="btn mini-btn" <?php echo $currentProgress >= $progressMax ? 'disabled' : ''; ?>>
                                        進捗 +1
                                    </button>
                                </form>

                                <form action="task_update_progress.php" method="post" class="inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="complete">
                                    <input type="hidden" name="return_to" value="index">
                                    <input type="hidden" name="year" value="<?php echo $viewYear; ?>">
                                    <input type="hidden" name="month" value="<?php echo $viewMonth; ?>">
                                    <input type="hidden" name="date" value="<?php echo h($selectedDate); ?>">
                                    <button type="submit" class="btn mini-btn" <?php echo $status === 'completed' ? 'disabled' : ''; ?>>
                                        完了
                                    </button>
                                </form>

                                <form action="task_delete.php" method="post" class="inline-form" onsubmit="return confirm('このタスクを削除します。よろしいですか？');">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="return_to" value="index">
                                    <input type="hidden" name="year" value="<?php echo $viewYear; ?>">
                                    <input type="hidden" name="month" value="<?php echo $viewMonth; ?>">
                                    <input type="hidden" name="date" value="<?php echo h($selectedDate); ?>">
                                    <button type="submit" class="btn danger-btn mini-btn">消去</button>
                                </form>

                                <a href="task_edit.php?task_id=<?php echo $taskId; ?>" class="btn btn-secondary mini-btn">工程日付編集</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="summary page-section">
            <h2 class="section-title">タスク概要</h2>
            <div class="card-grid">
                <div class="card summary-card">
                    <h3>全タスク数</h3>
                    <p class="count"><?php echo $totalTasks; ?></p>
                </div>
                <div class="card summary-card">
                    <h3>進行中</h3>
                    <p class="count"><?php echo $inProgressTasks; ?></p>
                </div>
                <div class="card summary-card">
                    <h3>完了</h3>
                    <p class="count"><?php echo $completedTasks; ?></p>
                </div>
            </div>
        </section>

        <section class="card calendar-card page-section">
            <div class="calendar-nav">
                <div class="calendar-nav-title">
                    <?php echo $viewYear; ?>年 <?php echo $viewMonth; ?>月
                </div>
                <div class="calendar-nav-actions">
                    <a href="<?php echo h(buildIndexUrl((int)$prevMonthDate->format('Y'), (int)$prevMonthDate->format('n'), $prevMonthDate->format('Y-m-01'))); ?>" class="btn btn-secondary">← 前月</a>
                    <a href="<?php echo h(buildIndexUrl($currentYear, $currentMonth, $currentDate)); ?>" class="btn btn-secondary">今月</a>
                    <a href="<?php echo h(buildIndexUrl((int)$nextMonthDate->format('Y'), (int)$nextMonthDate->format('n'), $nextMonthDate->format('Y-m-01'))); ?>" class="btn btn-secondary">翌月 →</a>
                </div>
            </div>

            <div class="calendar-weekdays">
                <div class="calendar-weekday sun">日</div>
                <div class="calendar-weekday">月</div>
                <div class="calendar-weekday">火</div>
                <div class="calendar-weekday">水</div>
                <div class="calendar-weekday">木</div>
                <div class="calendar-weekday">金</div>
                <div class="calendar-weekday sat">土</div>
            </div>

            <div class="calendar-grid">
                <?php for ($cell = 0; $cell < $totalCells; $cell++): ?>
                    <?php
                    $dayNumber = $cell - $startWeekday + 1;
                    $isValidDay = ($dayNumber >= 1 && $dayNumber <= $daysInMonth);
                    ?>

                    <?php if (!$isValidDay): ?>
                        <div class="calendar-cell is-empty"></div>
                    <?php else: ?>
                        <?php
                        $cellDate = sprintf('%04d-%02d-%02d', $viewYear, $viewMonth, $dayNumber);
                        $isToday = ($cellDate === $currentDate);
                        $isSelected = ($cellDate === $selectedDate);

                        $cellClass = 'calendar-cell';
                        if ($isToday) {
                            $cellClass .= ' is-today';
                        }
                        if ($isSelected) {
                            $cellClass .= ' is-selected';
                        }
                        ?>
                        <div class="<?php echo h($cellClass); ?>">
                            <a href="<?php echo h(buildIndexUrl($viewYear, $viewMonth, $cellDate)); ?>" class="calendar-day-link">
                                <span class="calendar-day-number"><?php echo $dayNumber; ?></span>

                                <?php if (!empty($calendarTasksByDate[$cellDate])): ?>
                                    <div class="calendar-task-list">
                                        <?php foreach (array_slice($calendarTasksByDate[$cellDate], 0, 2) as $calendarTask): ?>
                                            <div class="calendar-task-mini">
                                                <div class="calendar-task-name">
                                                    <?php echo h($calendarTask['title']); ?>
                                                </div>
                                                <div class="calendar-task-due">
                                                    終了: <?php echo h(formatShortDateTime($calendarTask['due_datetime'])); ?>
                                                </div>
                                                <div class="calendar-task-memo">
                                                    メモ: <?php echo h($calendarTask['display_memo']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (count($calendarTasksByDate[$cellDate]) > 2): ?>
                                            <div class="calendar-more">
                                                +<?php echo count($calendarTasksByDate[$cellDate]) - 2; ?> 件
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="calendar-empty-note">予定なし</div>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </section>

        <section class="page-section">
            <div class="section-header-row">
                <div>
                    <h2 class="section-title" style="margin-bottom: 8px;">選択日のタスク</h2>
                    <p class="section-sub-note"><?php echo h(formatJapaneseDate($selectedDate)); ?> に予定されている工程です。</p>
                </div>
            </div>

            <?php if (empty($selectedDateTasks)): ?>
                <div class="card">
                    <p class="empty-card-text">この日に予定されている工程はありません。</p>
                </div>
            <?php else: ?>
                <div class="selected-task-list">
                    <?php foreach ($selectedDateTasks as $task): ?>
                        <?php
                        $taskId = (int)$task['id'];
                        $currentProgress = (int)$task['current_progress'];
                        $progressMax = (int)$task['progress_max'];
                        $status = (string)$task['status'];
                        $progressPercent = $progressMax > 0 ? ($currentProgress / $progressMax) * 100 : 0;
                        if ($progressPercent < 0) {
                            $progressPercent = 0;
                        }
                        if ($progressPercent > 100) {
                            $progressPercent = 100;
                        }
                        ?>
                        <article class="card task-item">
                            <div class="task-header">
                                <div>
                                    <h3 class="task-title">
                                        <?php echo h($task['title']); ?>
                                        ：
                                        <?php echo h($task['step_memo']); ?>
                                    </h3>
                                    <div class="status-row">
                                        <span class="<?php echo h(getStatusBadgeClass($status)); ?>">
                                            <?php echo h(getStatusLabel($status)); ?>
                                        </span>
                                        <span class="task-progress">進捗: <?php echo $currentProgress; ?> / <?php echo $progressMax; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-box">
                                    <strong>予定工程</strong>
                                    <span><?php echo (int)$task['step_no']; ?>. <?php echo h($task['step_memo']); ?></span>
                                </div>
                                <div class="info-box">
                                    <strong>工程予定日時</strong>
                                    <span><?php echo h(formatDateTimeValue($task['assignment_date'])); ?></span>
                                </div>
                                <div class="info-box">
                                    <strong>終了予定日時</strong>
                                    <span><?php echo h(formatDateTimeValue($task['due_datetime'])); ?></span>
                                </div>
                            </div>

                            <div class="progress-bar progress-margin">
                                <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                            </div>

                            <div class="action-row">
                                <form action="task_update_progress.php" method="post" class="inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="increase">
                                    <input type="hidden" name="return_to" value="index">
                                    <input type="hidden" name="year" value="<?php echo $viewYear; ?>">
                                    <input type="hidden" name="month" value="<?php echo $viewMonth; ?>">
                                    <input type="hidden" name="date" value="<?php echo h($selectedDate); ?>">
                                    <button type="submit" class="btn mini-btn" <?php echo $currentProgress >= $progressMax ? 'disabled' : ''; ?>>
                                        進捗 +1
                                    </button>
                                </form>

                                <form action="task_update_progress.php" method="post" class="inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="complete">
                                    <input type="hidden" name="return_to" value="index">
                                    <input type="hidden" name="year" value="<?php echo $viewYear; ?>">
                                    <input type="hidden" name="month" value="<?php echo $viewMonth; ?>">
                                    <input type="hidden" name="date" value="<?php echo h($selectedDate); ?>">
                                    <button type="submit" class="btn mini-btn" <?php echo $status === 'completed' ? 'disabled' : ''; ?>>
                                        完了
                                    </button>
                                </form>

                                <form action="task_delete.php" method="post" class="inline-form" onsubmit="return confirm('このタスクを削除します。よろしいですか？');">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="return_to" value="index">
                                    <input type="hidden" name="year" value="<?php echo $viewYear; ?>">
                                    <input type="hidden" name="month" value="<?php echo $viewMonth; ?>">
                                    <input type="hidden" name="date" value="<?php echo h($selectedDate); ?>">
                                    <button type="submit" class="btn danger-btn mini-btn">消去</button>
                                </form>

                                <a href="task_edit.php?task_id=<?php echo $taskId; ?>" class="btn btn-secondary mini-btn">工程日付編集</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
