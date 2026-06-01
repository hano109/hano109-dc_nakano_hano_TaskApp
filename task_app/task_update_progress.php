<?php
require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Tokyo');

function buildRedirectUrl(string $returnTo, array $params = []): string
{
    if ($returnTo === 'index') {
        $query = [];

        if (!empty($params['year'])) {
            $query['year'] = (int)$params['year'];
        }
        if (!empty($params['month'])) {
            $query['month'] = (int)$params['month'];
        }
        if (!empty($params['date'])) {
            $query['date'] = $params['date'];
        }
        if (!empty($params['updated'])) {
            $query['updated'] = 1;
        }
        if (!empty($params['completed'])) {
            $query['completed'] = 1;
        }
        if (!empty($params['error'])) {
            $query['error'] = $params['error'];
        }

        return 'index.php' . (!empty($query) ? '?' . http_build_query($query) : '');
    }

    if ($returnTo === 'task_edit') {
        $query = [
            'task_id' => (int)($params['task_id'] ?? 0),
        ];

        if (!empty($params['updated'])) {
            $query['updated'] = 1;
        }
        if (!empty($params['completed'])) {
            $query['completed'] = 1;
        }
        if (!empty($params['error'])) {
            $query['error'] = $params['error'];
        }

        return 'task_edit.php?' . http_build_query($query);
    }

    $statusFilter = $params['status_filter'] ?? 'all';
    $query = [
        'status' => $statusFilter,
    ];

    if (!empty($params['updated'])) {
        $query['updated'] = 1;
    }
    if (!empty($params['completed'])) {
        $query['completed'] = 1;
    }
    if (!empty($params['error'])) {
        $query['error'] = $params['error'];
    }

    return 'task_list.php?' . http_build_query($query);
}

function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('task_list.php?status=all&error=' . urlencode('不正なアクセスです。'));
}

$taskId = (int)($_POST['task_id'] ?? 0);
$actionType = $_POST['action_type'] ?? '';
$returnTo = $_POST['return_to'] ?? 'task_list';
$statusFilter = $_POST['status_filter'] ?? 'all';

$year = (int)($_POST['year'] ?? 0);
$month = (int)($_POST['month'] ?? 0);
$date = trim((string)($_POST['date'] ?? ''));

$allowedReturnTo = ['index', 'task_list', 'task_edit'];
if (!in_array($returnTo, $allowedReturnTo, true)) {
    $returnTo = 'task_list';
}

$allowedStatusFilters = ['all', 'not_started', 'in_progress', 'completed'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$allowedActions = ['increase', 'decrease', 'complete', 'reset'];
if (!in_array($actionType, $allowedActions, true)) {
    redirectTo(buildRedirectUrl($returnTo, [
        'task_id' => $taskId,
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'error' => '更新方法が不正です。',
    ]));
}

if ($taskId <= 0) {
    redirectTo(buildRedirectUrl($returnTo, [
        'task_id' => $taskId,
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'error' => 'タスクIDが不正です。',
    ]));
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, title, current_progress, progress_max, status
        FROM tasks
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        $pdo->rollBack();
        redirectTo(buildRedirectUrl($returnTo, [
            'task_id' => $taskId,
            'status_filter' => $statusFilter,
            'year' => $year,
            'month' => $month,
            'date' => $date,
            'error' => '対象のタスクが見つかりません。',
        ]));
    }

    $currentProgress = (int)$task['current_progress'];
    $progressMax = (int)$task['progress_max'];

    $newProgress = $currentProgress;

    if ($actionType === 'increase') {
        $newProgress = min($currentProgress + 1, $progressMax);
    } elseif ($actionType === 'decrease') {
        $newProgress = max($currentProgress - 1, 0);
    } elseif ($actionType === 'complete') {
        $newProgress = $progressMax;
    } elseif ($actionType === 'reset') {
        $newProgress = 0;
    }

    if ($newProgress <= 0) {
        $newStatus = 'not_started';
    } elseif ($newProgress >= $progressMax) {
        $newStatus = 'completed';
    } else {
        $newStatus = 'in_progress';
    }

    $updateStmt = $pdo->prepare("
        UPDATE tasks
        SET current_progress = :current_progress,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $updateStmt->execute([
        ':current_progress' => $newProgress,
        ':status' => $newStatus,
        ':id' => $taskId,
    ]);

    $pdo->commit();

    if ($actionType === 'complete' || $newStatus === 'completed') {
        redirectTo(buildRedirectUrl($returnTo, [
            'task_id' => $taskId,
            'status_filter' => $statusFilter,
            'year' => $year,
            'month' => $month,
            'date' => $date,
            'completed' => 1,
        ]));
    }

    redirectTo(buildRedirectUrl($returnTo, [
        'task_id' => $taskId,
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'updated' => 1,
    ]));
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirectTo(buildRedirectUrl($returnTo, [
        'task_id' => $taskId,
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'error' => '進捗更新に失敗しました: ' . $e->getMessage(),
    ]));
}
?>
