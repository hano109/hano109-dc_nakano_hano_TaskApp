<?php
$pageTitle = $pageTitle ?? 'タスク一覧';
$appName = $appName ?? 'タスク管理・進捗管理アプリ';
$links = $links ?? [];
$summary = $summary ?? [
    'total' => 0,
    'not_started' => 0,
    'in_progress' => 0,
    'completed' => 0,
];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$filterStatus = $filterStatus ?? 'all';
$successMessage = $successMessage ?? '';
$errorMessage = $errorMessage ?? '';
$statusLinks = $statusLinks ?? [];

if (!function_exists('view_task_list_h')) {
    function view_task_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('view_task_list_format_datetime')) {
    function view_task_list_format_datetime(?string $value): string
    {
        if (!$value) {
            return '未設定';
        }
        $ts = strtotime($value);
        return $ts === false ? (string)$value : date('Y/m/d H:i', $ts);
    }
}

if (!function_exists('view_task_list_format_short_datetime')) {
    function view_task_list_format_short_datetime(?string $value): string
    {
        if (!$value) {
            return '未設定';
        }
        $ts = strtotime($value);
        return $ts === false ? (string)$value : date('m/d H:i', $ts);
    }
}

if (!function_exists('view_task_list_status_label')) {
    function view_task_list_status_label(string $status): string
    {
        return match ($status) {
            'not_started' => '未着手',
            'in_progress' => '進行中',
            'completed' => '完了',
            default => '不明',
        };
    }
}

if (!function_exists('view_task_list_status_class')) {
    function view_task_list_status_class(string $status): string
    {
        return match ($status) {
            'not_started' => 'vtl-badge vtl-badge-gray',
            'in_progress' => 'vtl-badge vtl-badge-blue',
            'completed' => 'vtl-badge vtl-badge-green',
            default => 'vtl-badge vtl-badge-gray',
        };
    }
}

if (!function_exists('view_task_list_build_url')) {
    function view_task_list_build_url(string $scriptName, string $controller, string $action = 'index', array $params = []): string
    {
        $query = array_merge([
            'controller' => $controller,
            'action' => $action,
        ], $params);

        return $scriptName . '?' . http_build_query($query);
    }
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/task_app/public/index.php');

$mvcHomeUrl = $links['home'] ?? view_task_list_build_url($scriptName, 'home', 'index');
$mvcCreateUrl = $links['create'] ?? view_task_list_build_url($scriptName, 'task', 'create');

$allUrl = $statusLinks['all'] ?? view_task_list_build_url($scriptName, 'task', 'index', ['status' => 'all']);
$notStartedUrl = $statusLinks['not_started'] ?? view_task_list_build_url($scriptName, 'task', 'index', ['status' => 'not_started']);
$inProgressUrl = $statusLinks['in_progress'] ?? view_task_list_build_url($scriptName, 'task', 'index', ['status' => 'in_progress']);
$completedUrl = $statusLinks['completed'] ?? view_task_list_build_url($scriptName, 'task', 'index', ['status' => 'completed']);

$updateProgressUrl = view_task_list_build_url($scriptName, 'task', 'updateprogress');
$deleteUrl = view_task_list_build_url($scriptName, 'task', 'delete');

$filterLabel = match ($filterStatus) {
    'not_started' => '未着手',
    'in_progress' => '進行中',
    'completed' => '完了',
    default => 'すべて',
};
?>

<style>
    .vtl-page {
        width: min(1200px, 94%);
        margin: 40px auto 70px;
        font-family: "Yu Gothic", "Hiragino Sans", "Meiryo", sans-serif;
        color: #1e293b;
    }

    .vtl-hero,
    .vtl-card,
    .vtl-summary-card,
    .vtl-task-card {
        background: #ffffff;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }

    .vtl-hero {
        padding: 34px;
        margin-bottom: 24px;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        border: 1px solid #dbeafe;
    }

    .vtl-eyebrow {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.88rem;
        font-weight: 700;
        margin-bottom: 14px;
    }

    .vtl-title {
        margin: 0 0 12px;
        font-size: 2rem;
        line-height: 1.4;
        color: #0f172a;
    }

    .vtl-text {
        margin: 0;
        color: #475569;
        line-height: 1.9;
    }

    .vtl-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 20px;
    }

    .vtl-btn {
        display: inline-block;
        text-decoration: none;
        padding: 12px 18px;
        border-radius: 12px;
        font-weight: 700;
        transition: 0.2s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .vtl-btn:hover {
        transform: translateY(-1px);
    }

    .vtl-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
    }

    .vtl-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }

    .vtl-btn-danger {
        background: #dc2626;
        color: #ffffff;
    }

    .vtl-message {
        padding: 16px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-weight: 700;
        line-height: 1.8;
    }

    .vtl-message-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }

    .vtl-message-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .vtl-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }

    .vtl-summary-card {
        padding: 22px;
        text-align: center;
    }

    .vtl-summary-label {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 12px;
    }

    .vtl-summary-value {
        font-size: 2rem;
        font-weight: 800;
        color: #2563eb;
    }

    .vtl-card {
        padding: 24px;
        margin-bottom: 24px;
    }

    .vtl-section-title {
        margin: 0 0 12px;
        font-size: 1.2rem;
        color: #0f172a;
    }

    .vtl-subtext {
        margin: 0;
        color: #64748b;
        line-height: 1.8;
        font-size: 0.92rem;
    }

    .vtl-filter-links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .vtl-filter-link {
        display: inline-block;
        text-decoration: none;
        padding: 10px 14px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.92rem;
        background: #e2e8f0;
        color: #334155;
        transition: 0.2s ease;
    }

    .vtl-filter-link:hover {
        transform: translateY(-1px);
    }

    .vtl-filter-link-active {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
    }

    .vtl-empty {
        padding: 20px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
        line-height: 1.9;
    }

    .vtl-task-list {
        display: grid;
        gap: 18px;
    }

    .vtl-task-card {
        padding: 22px;
    }

    .vtl-task-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .vtl-task-title-wrap {
        display: grid;
        gap: 8px;
    }

    .vtl-task-title {
        margin: 0;
        font-size: 1.28rem;
        color: #0f172a;
        line-height: 1.5;
        word-break: break-word;
    }

    .vtl-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .vtl-badge-gray {
        background: #e2e8f0;
        color: #334155;
    }

    .vtl-badge-blue {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .vtl-badge-green {
        background: #dcfce7;
        color: #166534;
    }

    .vtl-progress-wrap {
        margin: 14px 0 16px;
    }

    .vtl-progress-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
        font-size: 0.92rem;
        color: #475569;
        font-weight: 700;
    }

    .vtl-progress-bar {
        width: 100%;
        height: 12px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .vtl-progress-inner {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
    }

    .vtl-task-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .vtl-info-box {
        padding: 14px 16px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .vtl-info-label {
        display: block;
        font-size: 0.82rem;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .vtl-info-value {
        color: #0f172a;
        line-height: 1.8;
        word-break: break-word;
    }

    .vtl-next-step {
        margin-bottom: 16px;
        padding: 16px 18px;
        border-radius: 16px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }

    .vtl-next-step-title {
        margin: 0 0 8px;
        font-size: 0.95rem;
        font-weight: 800;
        color: #1d4ed8;
    }

    .vtl-next-step-text {
        margin: 0;
        color: #1e293b;
        line-height: 1.8;
    }

    .vtl-action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
    }

    .vtl-inline-form {
        margin: 0;
    }

    .vtl-mini-btn {
        display: inline-block;
        border: none;
        cursor: pointer;
        text-decoration: none;
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 700;
        transition: 0.2s ease;
    }

    .vtl-mini-btn:hover {
        transform: translateY(-1px);
    }

    .vtl-mini-btn-primary {
        background: #2563eb;
        color: #ffffff;
    }

    .vtl-mini-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }

    .vtl-mini-btn-success {
        background: #16a34a;
        color: #ffffff;
    }

    .vtl-mini-btn-danger {
        background: #dc2626;
        color: #ffffff;
    }

    .vtl-details {
        margin-top: 14px;
        border-top: 1px solid #e2e8f0;
        padding-top: 14px;
    }

    .vtl-details summary {
        cursor: pointer;
        font-weight: 700;
        color: #1d4ed8;
        list-style: none;
    }

    .vtl-details summary::-webkit-details-marker {
        display: none;
    }

    .vtl-details-body {
        margin-top: 16px;
        display: grid;
        gap: 16px;
    }

    .vtl-step-list {
        display: grid;
        gap: 10px;
    }

    .vtl-step-item {
        padding: 14px 16px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: grid;
        gap: 6px;
    }

    .vtl-step-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .vtl-step-title {
        font-weight: 800;
        color: #0f172a;
    }

    .vtl-step-status-done {
        color: #166534;
        font-weight: 700;
        font-size: 0.86rem;
    }

    .vtl-step-status-todo {
        color: #1d4ed8;
        font-weight: 700;
        font-size: 0.86rem;
    }

    .vtl-step-meta {
        color: #475569;
        line-height: 1.7;
        font-size: 0.92rem;
    }

    .vtl-description {
        white-space: pre-wrap;
    }

    @media (max-width: 900px) {
        .vtl-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .vtl-task-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .vtl-page {
            width: min(96%, 96%);
        }

        .vtl-hero,
        .vtl-card,
        .vtl-summary-card,
        .vtl-task-card {
            padding: 18px;
        }

        .vtl-title {
            font-size: 1.55rem;
        }

        .vtl-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="vtl-page">
    <section class="vtl-hero">
        <span class="vtl-eyebrow">MVC タスク一覧</span>
        <h1 class="vtl-title"><?php echo view_task_list_h($pageTitle); ?></h1>
        <p class="vtl-text">
            登録済みタスクの一覧確認、進捗更新、工程日時編集、削除ができます。現在の表示フィルタは
            <strong><?php echo view_task_list_h($filterLabel); ?></strong> です。
        </p>
        <div class="vtl-actions">
            <a href="<?php echo view_task_list_h($mvcCreateUrl); ?>" class="vtl-btn vtl-btn-primary">＋ タスクを追加する</a>
            <a href="<?php echo view_task_list_h($mvcHomeUrl); ?>" class="vtl-btn vtl-btn-secondary">MVCホームへ戻る</a>
        </div>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="vtl-message vtl-message-success">
            <?php echo view_task_list_h($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="vtl-message vtl-message-error">
            <?php echo view_task_list_h($errorMessage); ?>
        </div>
    <?php endif; ?>

    <section class="vtl-summary-grid">
        <div class="vtl-summary-card">
            <div class="vtl-summary-label">全タスク数</div>
            <div class="vtl-summary-value"><?php echo (int)($summary['total'] ?? 0); ?></div>
        </div>
        <div class="vtl-summary-card">
            <div class="vtl-summary-label">未着手</div>
            <div class="vtl-summary-value"><?php echo (int)($summary['not_started'] ?? 0); ?></div>
        </div>
        <div class="vtl-summary-card">
            <div class="vtl-summary-label">進行中</div>
            <div class="vtl-summary-value"><?php echo (int)($summary['in_progress'] ?? 0); ?></div>
        </div>
        <div class="vtl-summary-card">
            <div class="vtl-summary-label">完了</div>
            <div class="vtl-summary-value"><?php echo (int)($summary['completed'] ?? 0); ?></div>
        </div>
    </section>

    <section class="vtl-card">
        <h2 class="vtl-section-title">表示フィルタ</h2>
        <p class="vtl-subtext">ステータスごとに一覧を切り替えられます。</p>

        <div class="vtl-filter-links">
            <a href="<?php echo view_task_list_h($allUrl); ?>" class="vtl-filter-link <?php echo $filterStatus === 'all' ? 'vtl-filter-link-active' : ''; ?>">すべて</a>
            <a href="<?php echo view_task_list_h($notStartedUrl); ?>" class="vtl-filter-link <?php echo $filterStatus === 'not_started' ? 'vtl-filter-link-active' : ''; ?>">未着手</a>
            <a href="<?php echo view_task_list_h($inProgressUrl); ?>" class="vtl-filter-link <?php echo $filterStatus === 'in_progress' ? 'vtl-filter-link-active' : ''; ?>">進行中</a>
            <a href="<?php echo view_task_list_h($completedUrl); ?>" class="vtl-filter-link <?php echo $filterStatus === 'completed' ? 'vtl-filter-link-active' : ''; ?>">完了</a>
        </div>
    </section>

    <section class="vtl-card">
        <h2 class="vtl-section-title">一覧表示</h2>
        <p class="vtl-subtext">
            タスク件数: <strong><?php echo count($tasks); ?> 件</strong>
        </p>

        <?php if (empty($tasks)): ?>
            <div class="vtl-empty">
                この条件に一致するタスクはありません。<br>
                新しいタスクを登録するか、別のフィルタを選択してください。
            </div>
        <?php else: ?>
            <div class="vtl-task-list">
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $taskId = (int)($task['id'] ?? 0);
                    $title = (string)($task['title'] ?? '');
                    $description = (string)($task['description'] ?? '');
                    $status = (string)($task['status'] ?? 'not_started');
                    $currentProgress = (int)($task['current_progress'] ?? 0);
                    $progressMax = (int)($task['progress_max'] ?? 0);
                    $startDatetime = (string)($task['start_datetime'] ?? '');
                    $dueDatetime = (string)($task['due_datetime'] ?? '');
                    $completionRate = (int)($task['completion_rate'] ?? (($progressMax > 0) ? floor(($currentProgress / $progressMax) * 100) : 0));
                    $remainingSteps = (int)($task['remaining_steps'] ?? max($progressMax - $currentProgress, 0));
                    $nextStepNo = $task['next_step_no'] ?? null;
                    $nextStepMemo = (string)($task['next_step_memo'] ?? '');
                    $nextStepAssignment = (string)($task['next_step_assignment'] ?? '');

                    $stepMemos = is_array($task['step_memos'] ?? null) ? $task['step_memos'] : [];
                    $stepAssignments = is_array($task['step_assignments'] ?? null) ? $task['step_assignments'] : [];

                    $editUrl = view_task_list_build_url($scriptName, 'task', 'edit', ['task_id' => $taskId]);

                    if ($completionRate < 0) {
                        $completionRate = 0;
                    }
                    if ($completionRate > 100) {
                        $completionRate = 100;
                    }
                    ?>
                    <article class="vtl-task-card">
                        <div class="vtl-task-head">
                            <div class="vtl-task-title-wrap">
                                <h3 class="vtl-task-title"><?php echo view_task_list_h($title !== '' ? $title : '名称未設定'); ?></h3>
                                <div>
                                    <span class="<?php echo view_task_list_h(view_task_list_status_class($status)); ?>">
                                        <?php echo view_task_list_h(view_task_list_status_label($status)); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="vtl-progress-wrap">
                            <div class="vtl-progress-meta">
                                <span>進捗 <?php echo $currentProgress; ?> / <?php echo $progressMax; ?></span>
                                <span><?php echo $completionRate; ?>%</span>
                            </div>
                            <div class="vtl-progress-bar">
                                <div class="vtl-progress-inner" style="width: <?php echo $completionRate; ?>%;"></div>
                            </div>
                        </div>

                        <?php if ($nextStepNo !== null && $nextStepNo !== '' && $status !== 'completed'): ?>
                            <div class="vtl-next-step">
                                <h4 class="vtl-next-step-title">次の工程</h4>
                                <p class="vtl-next-step-text">
                                    工程 <?php echo (int)$nextStepNo; ?> :
                                    <?php echo view_task_list_h($nextStepMemo !== '' ? $nextStepMemo : ('工程 ' . (int)$nextStepNo)); ?><br>
                                    予定日時:
                                    <?php echo view_task_list_h(view_task_list_format_datetime($nextStepAssignment)); ?>
                                </p>
                            </div>
                        <?php elseif ($status === 'completed'): ?>
                            <div class="vtl-next-step">
                                <h4 class="vtl-next-step-title">状態</h4>
                                <p class="vtl-next-step-text">このタスクはすべての工程が完了しています。</p>
                            </div>
                        <?php endif; ?>

                        <div class="vtl-task-grid">
                            <div class="vtl-info-box">
                                <span class="vtl-info-label">開始日時</span>
                                <div class="vtl-info-value"><?php echo view_task_list_h(view_task_list_format_datetime($startDatetime)); ?></div>
                            </div>

                            <div class="vtl-info-box">
                                <span class="vtl-info-label">終了予定日時</span>
                                <div class="vtl-info-value"><?php echo view_task_list_h(view_task_list_format_datetime($dueDatetime)); ?></div>
                            </div>

                            <div class="vtl-info-box">
                                <span class="vtl-info-label">残り工程数</span>
                                <div class="vtl-info-value"><?php echo $remainingSteps; ?> 件</div>
                            </div>

                            <div class="vtl-info-box">
                                <span class="vtl-info-label">説明</span>
                                <div class="vtl-info-value vtl-description">
                                    <?php echo $description !== '' ? nl2br(view_task_list_h($description)) : '未入力'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="vtl-action-row">
                            <?php if ($currentProgress > 0): ?>
                                <form action="<?php echo view_task_list_h($updateProgressUrl); ?>" method="post" class="vtl-inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="decrease">
                                    <input type="hidden" name="status_filter" value="<?php echo view_task_list_h($filterStatus); ?>">
                                    <button type="submit" class="vtl-mini-btn vtl-mini-btn-secondary">-1</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($currentProgress < $progressMax): ?>
                                <form action="<?php echo view_task_list_h($updateProgressUrl); ?>" method="post" class="vtl-inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="increase">
                                    <input type="hidden" name="status_filter" value="<?php echo view_task_list_h($filterStatus); ?>">
                                    <button type="submit" class="vtl-mini-btn vtl-mini-btn-primary">+1</button>
                                </form>

                                <form action="<?php echo view_task_list_h($updateProgressUrl); ?>" method="post" class="vtl-inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="complete">
                                    <input type="hidden" name="status_filter" value="<?php echo view_task_list_h($filterStatus); ?>">
                                    <button type="submit" class="vtl-mini-btn vtl-mini-btn-success">完了にする</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($currentProgress > 0): ?>
                                <form action="<?php echo view_task_list_h($updateProgressUrl); ?>" method="post" class="vtl-inline-form">
                                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                    <input type="hidden" name="action_type" value="reset">
                                    <input type="hidden" name="status_filter" value="<?php echo view_task_list_h($filterStatus); ?>">
                                    <button type="submit" class="vtl-mini-btn vtl-mini-btn-secondary">リセット</button>
                                </form>
                            <?php endif; ?>

                            <a href="<?php echo view_task_list_h($editUrl); ?>" class="vtl-mini-btn vtl-mini-btn-secondary">工程日時編集</a>

                            <form action="<?php echo view_task_list_h($deleteUrl); ?>" method="post" class="vtl-inline-form" onsubmit="return confirm('このタスクを削除しますか？');">
                                <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                <input type="hidden" name="status_filter" value="<?php echo view_task_list_h($filterStatus); ?>">
                                <button type="submit" class="vtl-mini-btn vtl-mini-btn-danger">削除</button>
                            </form>
                        </div>

                        <details class="vtl-details">
                            <summary>詳細を表示</summary>
                            <div class="vtl-details-body">
                                <div class="vtl-step-list">
                                    <?php for ($i = 1; $i <= $progressMax; $i++): ?>
                                        <?php
                                        $memo = trim((string)($stepMemos[$i] ?? ''));
                                        if ($memo === '') {
                                            $memo = '工程 ' . $i;
                                        }

                                        $assignmentDate = (string)($stepAssignments[$i]['assignment_date'] ?? '');
                                        $isCompleted = ($i <= $currentProgress);
                                        ?>
                                        <div class="vtl-step-item">
                                            <div class="vtl-step-head">
                                                <div class="vtl-step-title">工程 <?php echo $i; ?></div>
                                                <?php if ($isCompleted): ?>
                                                    <div class="vtl-step-status-done">完了済み</div>
                                                <?php else: ?>
                                                    <div class="vtl-step-status-todo">未完了</div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="vtl-step-meta">メモ: <?php echo view_task_list_h($memo); ?></div>
                                            <div class="vtl-step-meta">予定日時: <?php echo view_task_list_h(view_task_list_format_datetime($assignmentDate)); ?></div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
