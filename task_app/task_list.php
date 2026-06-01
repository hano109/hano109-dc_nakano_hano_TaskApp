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

function formatDateValue(?string $value): string
{
    if (!$value) {
        return '未設定';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }

    return date('Y/m/d', $ts);
}

function getStatusLabel(string $status): string
{
    if ($status === 'not_started') {
        return '未着手';
    }
    if ($status === 'in_progress') {
        return '進行中';
    }
    if ($status === 'completed') {
        return '完了';
    }
    return '不明';
}

function getStatusBadgeClass(string $status): string
{
    if ($status === 'not_started') {
        return 'badge badge-gray';
    }
    if ($status === 'in_progress') {
        return 'badge badge-blue';
    }
    if ($status === 'completed') {
        return 'badge badge-green';
    }
    return 'badge badge-gray';
}

$allowedStatuses = ['all', 'not_started', 'in_progress', 'completed'];
$filterStatus = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}

$summary = [
    'total' => 0,
    'not_started' => 0,
    'in_progress' => 0,
    'completed' => 0,
];

$tasks = [];
$stepMemoMapByTask = [];
$assignmentMapByTask = [];
$assignmentsByTask = [];

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

    $summarySql = "
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) AS not_started_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM tasks
    ";
    $summaryStmt = $pdo->query($summarySql);
    $summaryRow = $summaryStmt->fetch();

    if ($summaryRow) {
        $summary['total'] = (int)($summaryRow['total_count'] ?? 0);
        $summary['not_started'] = (int)($summaryRow['not_started_count'] ?? 0);
        $summary['in_progress'] = (int)($summaryRow['in_progress_count'] ?? 0);
        $summary['completed'] = (int)($summaryRow['completed_count'] ?? 0);
    }

    $taskSql = "
        SELECT
            t.id,
            t.user_id,
            t.title,
            t.description,
            t.progress_max,
            t.current_progress,
            t.status,
            t.start_datetime,
            t.due_datetime,
            t.created_at,
            t.updated_at
        FROM tasks t
    ";

    $taskParams = [];
    if ($filterStatus !== 'all') {
        $taskSql .= " WHERE t.status = :status ";
        $taskParams[':status'] = $filterStatus;
    }

    $taskSql .= " ORDER BY t.created_at DESC, t.id DESC ";

    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute($taskParams);
    $tasks = $taskStmt->fetchAll();

    if (!empty($tasks)) {
        $taskIds = array_map(static function ($task) {
            return (int)$task['id'];
        }, $tasks);

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

        $stepMemoSql = "
            SELECT
                task_id,
                step_no,
                memo
            FROM task_step_memos
            WHERE task_id IN ($placeholders)
            ORDER BY task_id ASC, step_no ASC
        ";
        $stepMemoStmt = $pdo->prepare($stepMemoSql);
        $stepMemoStmt->execute($taskIds);

        foreach ($stepMemoStmt->fetchAll() as $row) {
            $taskId = (int)$row['task_id'];
            $stepNo = (int)$row['step_no'];
            $stepMemoMapByTask[$taskId][$stepNo] = $row['memo'];
        }

        $assignmentSql = "
            SELECT
                task_id,
                step_no,
                assignment_date,
                step_memo
            FROM task_calendar_assignments
            WHERE task_id IN ($placeholders)
            ORDER BY task_id ASC, step_no ASC
        ";
        $assignmentStmt = $pdo->prepare($assignmentSql);
        $assignmentStmt->execute($taskIds);

        foreach ($assignmentStmt->fetchAll() as $row) {
            $taskId = (int)$row['task_id'];
            $stepNo = (int)$row['step_no'];

            $assignmentMapByTask[$taskId][$stepNo] = [
                'assignment_date' => $row['assignment_date'],
                'step_memo' => $row['step_memo'],
            ];

            $assignmentsByTask[$taskId][] = [
                'step_no' => $stepNo,
                'assignment_date' => $row['assignment_date'],
                'step_memo' => $row['step_memo'],
            ];
        }
    }
} catch (PDOException $e) {
    $errorMessage = 'タスク一覧の取得に失敗しました: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タスク一覧 | タスク管理アプリ</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .filter-link {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-weight: 700;
            transition: 0.3s;
        }

        .filter-link:hover {
            background: #cbd5e1;
        }

        .filter-link.active {
            background: #2563eb;
            color: #fff;
        }

        .task-card-list {
            display: grid;
            gap: 24px;
        }

        .task-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .task-top-left {
            display: grid;
            gap: 10px;
        }

        .task-top-right {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .small-muted {
            color: #64748b;
            font-size: 0.92rem;
        }

        .next-step-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .next-step-title {
            font-weight: 700;
            color: #1d4ed8;
            margin-bottom: 6px;
        }

        .next-step-body {
            color: #1e293b;
        }

        .sub-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .sub-info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px;
        }

        .sub-info-card strong {
            display: block;
            margin-bottom: 6px;
            color: #1e293b;
        }

        .details-section {
            display: grid;
            gap: 16px;
        }

        .step-list {
            display: grid;
            gap: 10px;
        }

        .step-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            padding: 12px 14px;
        }

        .step-row.completed {
            background: #f0fdf4;
            border-color: #86efac;
        }

        .step-row.current {
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .step-row.upcoming {
            background: #ffffff;
        }

        .step-main {
            flex: 1 1 280px;
        }

        .step-name {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .step-date {
            flex: 0 0 auto;
            min-width: 130px;
            text-align: right;
            color: #334155;
            font-weight: 700;
        }

        .step-note {
            font-size: 0.92rem;
            color: #64748b;
        }

        .schedule-list {
            display: grid;
            gap: 10px;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .schedule-item-left {
            color: #1e293b;
            font-weight: 700;
        }

        .schedule-item-right {
            color: #2563eb;
            font-weight: 700;
        }

        .empty-note {
            color: #64748b;
        }

        @media (max-width: 768px) {
            .step-date {
                text-align: left;
                min-width: auto;
            }

            .task-top-row {
                align-items: stretch;
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
        <section class="hero">
            <h2>タスク一覧</h2>
            <p>登録済みタスクの状態確認、進捗更新、工程日付編集、削除ができます。</p>
            <div class="button-group">
                <a href="task_create.php" class="btn">＋ タスクを追加する</a>
                <a href="index.php" class="btn btn-secondary">ホームへ戻る</a>
            </div>
        </section>

        <?php if ($successMessage): ?>
            <section class="message success-message">
                <p><?php echo h($successMessage); ?></p>
            </section>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <section class="message error-message">
                <p><?php echo h($errorMessage); ?></p>
            </section>
        <?php endif; ?>

        <section class="summary">
            <div class="section-header">
                <h2 class="section-title no-margin">タスク概要</h2>
            </div>

            <div class="card-grid card-grid-4">
                <div class="card summary-card">
                    <h3>全タスク数</h3>
                    <p class="count"><?php echo $summary['total']; ?></p>
                </div>
                <div class="card summary-card">
                    <h3>未着手</h3>
                    <p class="count"><?php echo $summary['not_started']; ?></p>
                </div>
                <div class="card summary-card">
                    <h3>進行中</h3>
                    <p class="count"><?php echo $summary['in_progress']; ?></p>
                </div>
                <div class="card summary-card">
                    <h3>完了</h3>
                    <p class="count"><?php echo $summary['completed']; ?></p>
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom: 32px;">
            <h2 class="section-title" style="margin-bottom: 8px;">表示フィルタ</h2>
            <p class="small-muted">ステータスごとに一覧を切り替えられます。</p>

            <div class="filter-row">
                <a href="task_list.php?status=all" class="filter-link <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">すべて</a>
                <a href="task_list.php?status=not_started" class="filter-link <?php echo $filterStatus === 'not_started' ? 'active' : ''; ?>">未着手</a>
                <a href="task_list.php?status=in_progress" class="filter-link <?php echo $filterStatus === 'in_progress' ? 'active' : ''; ?>">進行中</a>
                <a href="task_list.php?status=completed" class="filter-link <?php echo $filterStatus === 'completed' ? 'active' : ''; ?>">完了</a>
            </div>
        </section>

        <section>
            <h2 class="section-title">一覧表示</h2>

            <?php if (empty($tasks)): ?>
                <div class="card">
                    <p>条件に一致するタスクはありません。</p>
                </div>
            <?php else: ?>
                <div class="task-card-list">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $taskId = (int)$task['id'];
                        $title = (string)$task['title'];
                        $description = (string)($task['description'] ?? '');
                        $progressMax = (int)$task['progress_max'];
                        $currentProgress = (int)$task['current_progress'];
                        $status = (string)$task['status'];

                        $progressPercent = $progressMax > 0 ? ($currentProgress / $progressMax) * 100 : 0;
                        if ($progressPercent < 0) {
                            $progressPercent = 0;
                        }
                        if ($progressPercent > 100) {
                            $progressPercent = 100;
                        }

                        $nextStepNo = null;
                        if ($currentProgress < $progressMax) {
                            $nextStepNo = $currentProgress + 1;
                        }

                        $completedStepCount = min($currentProgress, $progressMax);
                        $remainingStepCount = max($progressMax - $currentProgress, 0);

                        $assignedRemainingCount = 0;
                        $unassignedRemainingCount = 0;

                        for ($i = $currentProgress + 1; $i <= $progressMax; $i++) {
                            if (!empty($assignmentMapByTask[$taskId][$i]['assignment_date'])) {
                                $assignedRemainingCount++;
                            } else {
                                $unassignedRemainingCount++;
                            }
                        }

                        $currentStepMemo = '未着手です';
                        if ($currentProgress > 0) {
                            $currentStepMemo = trim((string)($stepMemoMapByTask[$taskId][$currentProgress] ?? ''));
                            if ($currentStepMemo === '') {
                                $currentStepMemo = '工程 ' . $currentProgress;
                            }
                        }

                        $nextStepMemo = '完了済みです';
                        $nextStepDate = '';
                        if ($nextStepNo !== null) {
                            $nextStepMemo = trim((string)($stepMemoMapByTask[$taskId][$nextStepNo] ?? ''));
                            if ($nextStepMemo === '') {
                                $nextStepMemo = trim((string)($assignmentMapByTask[$taskId][$nextStepNo]['step_memo'] ?? ''));
                            }
                            if ($nextStepMemo === '') {
                                $nextStepMemo = '工程 ' . $nextStepNo;
                            }

                            $nextStepDate = (string)($assignmentMapByTask[$taskId][$nextStepNo]['assignment_date'] ?? '');
                        }
                        ?>
                        <article class="card task-item">
                            <div class="task-top-row">
                                <div class="task-top-left">
                                    <h3 class="task-title"><?php echo h($title); ?></h3>
                                    <div class="meta-row">
                                        <span class="<?php echo h(getStatusBadgeClass($status)); ?>">
                                            <?php echo h(getStatusLabel($status)); ?>
                                        </span>
                                        <span class="task-progress">
                                            進捗: <?php echo $currentProgress; ?> / <?php echo $progressMax; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="task-top-right">
                                    <span class="small-muted">作成日: <?php echo h(formatDateTimeValue($task['created_at'])); ?></span>
                                </div>
                            </div>

                            <div class="next-step-box">
                                <?php if ($nextStepNo !== null): ?>
                                    <div class="next-step-title">次の未完了工程</div>
                                    <div class="next-step-body">
                                        工程 <?php echo $nextStepNo; ?>：<?php echo h($nextStepMemo); ?>
                                        <?php if ($nextStepDate !== ''): ?>
                                            <span class="badge badge-blue" style="margin-left:8px;">予定日 <?php echo h(formatDateValue($nextStepDate)); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-gray" style="margin-left:8px;">日付未設定</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="next-step-title">次の未完了工程</div>
                                    <div class="next-step-body">このタスクはすべて完了しています。</div>
                                <?php endif; ?>
                            </div>

                            <p class="task-memo">
                                現在工程メモ:
                                <?php echo h($currentStepMemo); ?>
                            </p>

                            <div class="progress-bar progress-margin">
                                <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                            </div>

                            <div class="sub-info-grid">
                                <div class="sub-info-card">
                                    <strong>開始日時</strong>
                                    <span><?php echo h(formatDateTimeValue($task['start_datetime'])); ?></span>
                                </div>
                                <div class="sub-info-card">
                                    <strong>終了予定日時</strong>
                                    <span><?php echo h(formatDateTimeValue($task['due_datetime'])); ?></span>
                                </div>
                                <div class="sub-info-card">
                                    <strong>完了済み工程数</strong>
                                    <span><?php echo $completedStepCount; ?> 件</span>
                                </div>
                                <div class="sub-info-card">
                                    <strong>未完了工程数</strong>
                                    <span><?php echo $remainingStepCount; ?> 件</span>
                                </div>
                                <div class="sub-info-card">
                                    <strong>日付設定済み未完了工程</strong>
                                    <span><?php echo $assignedRemainingCount; ?> 件</span>
                                </div>
                                <div class="sub-info-card">
                                    <strong>日付未設定未完了工程</strong>
                                    <span><?php echo $unassignedRemainingCount; ?> 件</span>
                                </div>
                            </div>

                            <div class="action-section">
                                <h4>操作</h4>
                                <div class="action-row">
                                    <form action="task_update_progress.php" method="post" class="inline-form">
                                        <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                        <input type="hidden" name="action_type" value="decrease">
                                        <input type="hidden" name="return_to" value="task_list">
                                        <input type="hidden" name="status_filter" value="<?php echo h($filterStatus); ?>">
                                        <button type="submit" class="btn btn-secondary mini-btn" <?php echo $currentProgress <= 0 ? 'disabled' : ''; ?>>
                                            -1
                                        </button>
                                    </form>

                                    <form action="task_update_progress.php" method="post" class="inline-form">
                                        <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                        <input type="hidden" name="action_type" value="increase">
                                        <input type="hidden" name="return_to" value="task_list">
                                        <input type="hidden" name="status_filter" value="<?php echo h($filterStatus); ?>">
                                        <button type="submit" class="btn mini-btn" <?php echo $currentProgress >= $progressMax ? 'disabled' : ''; ?>>
                                            +1
                                        </button>
                                    </form>

                                    <form action="task_update_progress.php" method="post" class="inline-form">
                                        <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                        <input type="hidden" name="action_type" value="complete">
                                        <input type="hidden" name="return_to" value="task_list">
                                        <input type="hidden" name="status_filter" value="<?php echo h($filterStatus); ?>">
                                        <button type="submit" class="btn mini-btn" <?php echo $status === 'completed' ? 'disabled' : ''; ?>>
                                            完了
                                        </button>
                                    </form>

                                    <a href="task_edit.php?task_id=<?php echo $taskId; ?>" class="btn btn-secondary mini-btn">
                                        工程日付編集
                                    </a>

                                    <form action="task_delete.php" method="post" class="inline-form" onsubmit="return confirm('このタスクを削除します。よろしいですか？');">
                                        <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                        <input type="hidden" name="return_to" value="task_list">
                                        <input type="hidden" name="status_filter" value="<?php echo h($filterStatus); ?>">
                                        <button type="submit" class="btn danger-btn mini-btn">
                                            削除
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <details class="details-box">
                                <summary class="details-summary">詳細を見る</summary>

                                <div class="details-content details-section">
                                    <div>
                                        <h4 class="details-title">タスク詳細</h4>
                                        <p class="details-text">
                                            <?php echo nl2br(h($description !== '' ? $description : '詳細はありません。')); ?>
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="details-title">全工程一覧</h4>
                                        <div class="step-list">
                                            <?php for ($i = 1; $i <= $progressMax; $i++): ?>
                                                <?php
                                                $stepName = trim((string)($stepMemoMapByTask[$taskId][$i] ?? ''));
                                                if ($stepName === '') {
                                                    $stepName = trim((string)($assignmentMapByTask[$taskId][$i]['step_memo'] ?? ''));
                                                }
                                                if ($stepName === '') {
                                                    $stepName = '工程 ' . $i;
                                                }

                                                $assignedDate = (string)($assignmentMapByTask[$taskId][$i]['assignment_date'] ?? '');

                                                $rowClass = 'upcoming';
                                                if ($i <= $currentProgress) {
                                                    $rowClass = 'completed';
                                                } elseif ($i === $currentProgress + 1) {
                                                    $rowClass = 'current';
                                                }
                                                ?>
                                                <div class="step-row <?php echo $rowClass; ?>">
                                                    <div class="step-main">
                                                        <div class="step-name">
                                                            工程 <?php echo $i; ?>：<?php echo h($stepName); ?>
                                                        </div>
                                                        <div class="step-note">
                                                            <?php if ($i <= $currentProgress): ?>
                                                                完了済み
                                                            <?php elseif ($i === $currentProgress + 1): ?>
                                                                次の工程
                                                            <?php else: ?>
                                                                これからの工程
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="step-date">
                                                        <?php if ($assignedDate !== ''): ?>
                                                            <?php echo h(formatDateValue($assignedDate)); ?>
                                                        <?php else: ?>
                                                            日付未設定
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="details-title">日付割り当て済み工程一覧</h4>
                                        <?php if (!empty($assignmentsByTask[$taskId])): ?>
                                            <div class="schedule-list">
                                                <?php foreach ($assignmentsByTask[$taskId] as $assignment): ?>
                                                    <div class="schedule-item">
                                                        <div class="schedule-item-left">
                                                            工程 <?php echo (int)$assignment['step_no']; ?>：
                                                            <?php
                                                            $assignmentName = trim((string)($assignment['step_memo'] ?? ''));
                                                            if ($assignmentName === '') {
                                                                $assignmentName = trim((string)($stepMemoMapByTask[$taskId][(int)$assignment['step_no']] ?? ''));
                                                            }
                                                            if ($assignmentName === '') {
                                                                $assignmentName = '工程 ' . (int)$assignment['step_no'];
                                                            }
                                                            echo h($assignmentName);
                                                            ?>
                                                        </div>
                                                        <div class="schedule-item-right">
                                                            <?php echo h(formatDateValue($assignment['assignment_date'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="empty-note">日付が設定されている工程はまだありません。</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
