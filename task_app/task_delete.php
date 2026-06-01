<?php
require_once __DIR__ . '/config/db.php';

function buildRedirectUrl(string $returnTo, array $params = []): string
{
    if ($returnTo === 'index') {
        $base = 'index.php';
        $query = [];

        if (!empty($params['year'])) {
            $query['year'] = $params['year'];
        }
        if (!empty($params['month'])) {
            $query['month'] = $params['month'];
        }
        if (!empty($params['date'])) {
            $query['date'] = $params['date'];
        }

        if (!empty($params['deleted'])) {
            $query['deleted'] = 1;
        }
        if (!empty($params['error'])) {
            $query['error'] = $params['error'];
        }

        return $base . '?' . http_build_query($query);
    }

    $base = 'task_list.php';
    $query = [];

    if (!empty($params['status_filter'])) {
        $query['status'] = $params['status_filter'];
    } else {
        $query['status'] = 'all';
    }

    if (!empty($params['deleted'])) {
        $query['deleted'] = 1;
    }
    if (!empty($params['error'])) {
        $query['error'] = $params['error'];
    }

    return $base . '?' . http_build_query($query);
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
$returnTo = $_POST['return_to'] ?? 'task_list';
$statusFilter = $_POST['status_filter'] ?? 'all';
$year = (int)($_POST['year'] ?? 0);
$month = (int)($_POST['month'] ?? 0);
$date = $_POST['date'] ?? '';

if (!in_array($returnTo, ['index', 'task_list'], true)) {
    $returnTo = 'task_list';
}

if ($taskId <= 0) {
    redirectTo(buildRedirectUrl($returnTo, [
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'error' => 'タスクIDが不正です。',
    ]));
}

try {
    $pdo = getPDO();

    $sql = "DELETE FROM tasks WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $taskId]);

    redirectTo(buildRedirectUrl($returnTo, [
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'deleted' => 1,
    ]));

} catch (PDOException $e) {
    redirectTo(buildRedirectUrl($returnTo, [
        'status_filter' => $statusFilter,
        'year' => $year,
        'month' => $month,
        'date' => $date,
        'error' => 'タスク削除に失敗しました: ' . $e->getMessage(),
    ]));
}
