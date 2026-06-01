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

function toDateTimeLocalValue(?string $value): string
{
    if (!$value) {
        return '';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $ts);
}

$taskId = (int)($_GET['task_id'] ?? $_POST['task_id'] ?? 0);
if ($taskId <= 0) {
    header('Location: task_list.php?status=all&error=' . urlencode('タスクIDが不正です。'));
    exit;
}

$errorMessage = '';
$successMessage = '';
$task = null;
$stepMemos = [];
$stepDates = [];

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $successMessage = '工程の予定日時を保存しました。';
}
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = '進捗を更新しました。';
}
if (isset($_GET['completed']) && $_GET['completed'] === '1') {
    $successMessage = 'タスクを完了にしました。';
}
if (!empty($_GET['error'])) {
    $errorMessage = (string)$_GET['error'];
}

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $taskStmt = $pdo->prepare("
            SELECT id, title, progress_max, current_progress, start_datetime, due_datetime
            FROM tasks
            WHERE id = :id
        ");
        $taskStmt->execute([':id' => $taskId]);
        $taskForSave = $taskStmt->fetch();

        if (!$taskForSave) {
            throw new PDOException('対象のタスクが見つかりません。');
        }

        $progressMax = (int)$taskForSave['progress_max'];
        $postedStepDates = $_POST['step_dates'] ?? [];
        $errors = [];

        $memoStmt = $pdo->prepare("
            SELECT step_no, memo
            FROM task_step_memos
            WHERE task_id = :task_id
            ORDER BY step_no ASC
        ");
        $memoStmt->execute([':task_id' => $taskId]);
        $memoRows = $memoStmt->fetchAll();

        $stepMemoMap = [];
        foreach ($memoRows as $memoRow) {
            $stepMemoMap[(int)$memoRow['step_no']] = $memoRow['memo'];
        }

        $normalizedStepDates = [];

        for ($i = 1; $i <= $progressMax; $i++) {
            $dateValue = trim((string)($postedStepDates[$i] ?? ''));
            $normalizedStepDates[$i] = $dateValue;

            if ($dateValue === '') {
                continue;
            }

            $stepTimestamp = strtotime($dateValue);
            if ($stepTimestamp === false) {
                $errors[] = '工程 ' . $i . ' の日時形式が不正です。';
                continue;
            }

            if (
                $stepTimestamp < strtotime($taskForSave['start_datetime']) ||
                $stepTimestamp > strtotime($taskForSave['due_datetime'])
            ) {
                $errors[] = '工程 ' . $i . ' の日時は開始日時から終了予定日時までの間で設定してください。';
            }
        }

        if (empty($errors)) {
            $pdo->beginTransaction();

            $upsertStmt = $pdo->prepare("
                INSERT INTO task_calendar_assignments (task_id, step_no, assignment_date, step_memo)
                VALUES (:task_id, :step_no, :assignment_date, :step_memo)
                ON DUPLICATE KEY UPDATE
                    assignment_date = VALUES(assignment_date),
                    step_memo = VALUES(step_memo)
            ");

            $deleteStmt = $pdo->prepare("
                DELETE FROM task_calendar_assignments
                WHERE task_id = :task_id AND step_no = :step_no
            ");

            for ($i = 1; $i <= $progressMax; $i++) {
                $stepMemo = trim((string)($stepMemoMap[$i] ?? ''));
                if ($stepMemo === '') {
                    $stepMemo = '工程 ' . $i;
                }

                if ($normalizedStepDates[$i] === '') {
                    $deleteStmt->execute([
                        ':task_id' => $taskId,
                        ':step_no' => $i,
                    ]);
                } else {
                    $upsertStmt->execute([
                        ':task_id' => $taskId,
                        ':step_no' => $i,
                        ':assignment_date' => date('Y-m-d H:i:s', strtotime($normalizedStepDates[$i])),
                        ':step_memo' => $stepMemo,
                    ]);
                }
            }

            $pdo->commit();

            header('Location: task_edit.php?task_id=' . $taskId . '&saved=1');
            exit;
        } else {
            $errorMessage = implode(' / ', $errors);
        }
    }

    $taskStmt = $pdo->prepare("
        SELECT *
        FROM tasks
        WHERE id = :id
    ");
    $taskStmt->execute([':id' => $taskId]);
    $task = $taskStmt->fetch();

    if (!$task) {
        throw new PDOException('対象のタスクが見つかりません。');
    }

    $memoStmt = $pdo->prepare("
        SELECT step_no, memo
        FROM task_step_memos
        WHERE task_id = :task_id
        ORDER BY step_no ASC
    ");
    $memoStmt->execute([':task_id' => $taskId]);
    foreach ($memoStmt->fetchAll() as $row) {
        $stepMemos[(int)$row['step_no']] = $row['memo'];
    }

    $assignmentStmt = $pdo->prepare("
        SELECT step_no, assignment_date
        FROM task_calendar_assignments
        WHERE task_id = :task_id
        ORDER BY step_no ASC
    ");
    $assignmentStmt->execute([':task_id' => $taskId]);
    foreach ($assignmentStmt->fetchAll() as $row) {
        $stepDates[(int)$row['step_no']] = $row['assignment_date'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['step_dates'])) {
        for ($i = 1; $i <= (int)$task['progress_max']; $i++) {
            $posted = trim((string)($_POST['step_dates'][$i] ?? ''));
            $stepDates[$i] = $posted;
        }
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = 'データの読み込みに失敗しました: ' . $e->getMessage();
}

$progressMax = (int)($task['progress_max'] ?? 0);
$currentProgress = (int)($task['current_progress'] ?? 0);
$startDateTimeLocal = !empty($task['start_datetime']) ? date('Y-m-d\TH:i', strtotime($task['start_datetime'])) : '';
$dueDateTimeLocal = !empty($task['due_datetime']) ? date('Y-m-d\TH:i', strtotime($task['due_datetime'])) : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工程日付編集 | タスク管理アプリ</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-section {
            margin-bottom: 32px;
        }

        .task-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .task-info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px;
        }

        .task-info-box strong {
            display: block;
            margin-bottom: 6px;
            color: #1e293b;
        }

        .step-edit-list {
            display: grid;
            gap: 16px;
        }

        .step-edit-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
        }

        .step-edit-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .step-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
        }

        .step-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .step-memo-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 12px;
            color: #334155;
        }

        .step-date-label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .step-date-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            background: #fff;
        }

        .form-note {
            margin-top: 12px;
            font-size: 0.92rem;
            color: #64748b;
        }

        .top-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .readonly-note {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 0.9rem;
            font-weight: 700;
        }

        @media (max-width: 600px) {
            .step-edit-card {
                padding: 14px;
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
            <h2>工程の日時を編集</h2>
            <p>タスクごとの工程に対して、予定日時を後から設定・変更できます。空欄で保存すると、その工程は「日時未設定」になります。</p>

            <div class="top-actions">
                <a href="task_list.php" class="btn btn-secondary">← タスク一覧へ戻る</a>
                <a href="index.php" class="btn btn-secondary">ホームへ戻る</a>
            </div>
        </section>

        <?php if ($successMessage): ?>
            <section class="message success-message page-section">
                <p><?php echo h($successMessage); ?></p>
            </section>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <section class="message error-message page-section">
                <p><?php echo h($errorMessage); ?></p>
            </section>
        <?php endif; ?>

        <?php if ($task): ?>
            <section class="card page-section">
                <div class="task-header">
                    <div>
                        <h2 class="section-title" style="margin-bottom: 10px;"><?php echo h($task['title']); ?></h2>
                        <div class="meta-row">
                            <span class="<?php echo h(getStatusBadgeClass((string)$task['status'])); ?>">
                                <?php echo h(getStatusLabel((string)$task['status'])); ?>
                            </span>
                            <span class="task-progress">
                                進捗: <?php echo $currentProgress; ?> / <?php echo $progressMax; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="task-info-grid">
                    <div class="task-info-box">
                        <strong>開始日時</strong>
                        <span><?php echo h(formatDateTimeValue($task['start_datetime'])); ?></span>
                    </div>
                    <div class="task-info-box">
                        <strong>終了予定日時</strong>
                        <span><?php echo h(formatDateTimeValue($task['due_datetime'])); ?></span>
                    </div>
                    <div class="task-info-box">
                        <strong>編集可能な日時範囲</strong>
                        <span><?php echo h(formatDateTimeValue($task['start_datetime'])); ?> ～ <?php echo h(formatDateTimeValue($task['due_datetime'])); ?></span>
                    </div>
                    <div class="task-info-box">
                        <strong>説明</strong>
                        <span><?php echo nl2br(h($task['description'] ?? '説明はありません。')); ?></span>
                    </div>
                </div>

                <div class="action-section" style="margin-top: 20px;">
                    <h4>進捗操作</h4>
                    <div class="action-row">
                        <form action="task_update_progress.php" method="post" class="inline-form">
                            <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                            <input type="hidden" name="action_type" value="decrease">
                            <input type="hidden" name="return_to" value="task_edit">
                            <button type="submit" class="btn btn-secondary mini-btn" <?php echo $currentProgress <= 0 ? 'disabled' : ''; ?>>-1</button>
                        </form>

                        <form action="task_update_progress.php" method="post" class="inline-form">
                            <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                            <input type="hidden" name="action_type" value="increase">
                            <input type="hidden" name="return_to" value="task_edit">
                            <button type="submit" class="btn mini-btn" <?php echo $currentProgress >= $progressMax ? 'disabled' : ''; ?>>+1</button>
                        </form>

                        <form action="task_update_progress.php" method="post" class="inline-form">
                            <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                            <input type="hidden" name="action_type" value="complete">
                            <input type="hidden" name="return_to" value="task_edit">
                            <button type="submit" class="btn mini-btn" <?php echo $task['status'] === 'completed' ? 'disabled' : ''; ?>>完了</button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="card page-section">
                <h2 class="section-title">工程ごとの予定日時編集</h2>

                <form action="task_edit.php" method="post">
                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">

                    <div class="step-edit-list">
                        <?php for ($i = 1; $i <= $progressMax; $i++): ?>
                            <?php
                            $memo = trim((string)($stepMemos[$i] ?? ''));
                            if ($memo === '') {
                                $memo = '工程 ' . $i;
                            }

                            $assignedDate = $stepDates[$i] ?? '';
                            $isCompletedStep = ($i <= $currentProgress);
                            ?>
                            <div class="step-edit-card">
                                <div class="step-edit-top">
                                    <div class="step-title">工程 <?php echo $i; ?></div>
                                    <div class="step-meta">
                                        <?php if ($isCompletedStep): ?>
                                            <span class="badge badge-green">完了済み工程</span>
                                        <?php else: ?>
                                            <span class="badge badge-blue">未完了工程</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="step-memo-box">
                                    <strong style="display:block; margin-bottom:6px;">工程内容</strong>
                                    <span><?php echo h($memo); ?></span>
                                </div>

                                <label for="step_date_<?php echo $i; ?>" class="step-date-label">
                                    工程 <?php echo $i; ?> の予定日時
                                </label>
                                <input
                                    type="datetime-local"
                                    id="step_date_<?php echo $i; ?>"
                                    name="step_dates[<?php echo $i; ?>]"
                                    value="<?php echo h(!empty($assignedDate) ? toDateTimeLocalValue($assignedDate) : ''); ?>"
                                    min="<?php echo h($startDateTimeLocal); ?>"
                                    max="<?php echo h($dueDateTimeLocal); ?>"
                                    class="step-date-input"
                                >

                                <p class="form-note">
                                    開始日時〜終了予定日時の範囲で設定してください。空欄で保存すると日時未設定になります。
                                </p>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="button-group" style="margin-top: 24px;">
                        <button type="submit" class="btn">保存する</button>
                        <a href="task_list.php" class="btn btn-secondary">キャンセル</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
