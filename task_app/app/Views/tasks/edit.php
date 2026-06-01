<?php
$pageTitle = $pageTitle ?? '工程日時編集';
$appName = $appName ?? 'タスク管理・進捗管理アプリ';
$task = $task ?? [];
$taskId = (int)($taskId ?? ($task['id'] ?? 0));
$formAction = $formAction ?? ($_SERVER['SCRIPT_NAME'] . '?controller=task&action=updateschedule');
$links = $links ?? [];
$successMessage = $successMessage ?? '';
$errorMessage = $errorMessage ?? '';

if (!function_exists('view_task_edit_h')) {
    function view_task_edit_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('view_task_edit_format_datetime')) {
    function view_task_edit_format_datetime(?string $value): string
    {
        if (!$value) {
            return '未設定';
        }
        $ts = strtotime($value);
        return $ts === false ? (string)$value : date('Y/m/d H:i', $ts);
    }
}

if (!function_exists('view_task_edit_datetime_local')) {
    function view_task_edit_datetime_local(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }
}

if (!function_exists('view_task_edit_status_label')) {
    function view_task_edit_status_label(string $status): string
    {
        return match ($status) {
            'not_started' => '未着手',
            'in_progress' => '進行中',
            'completed' => '完了',
            default => '不明',
        };
    }
}

if (!function_exists('view_task_edit_status_class')) {
    function view_task_edit_status_class(string $status): string
    {
        return match ($status) {
            'not_started' => 'vte-badge vte-badge-gray',
            'in_progress' => 'vte-badge vte-badge-blue',
            'completed' => 'vte-badge vte-badge-green',
            default => 'vte-badge vte-badge-gray',
        };
    }
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/task_app/public/index.php');
$mvcHomeUrl = $links['home'] ?? ($scriptName . '?controller=home&action=index');
$mvcListUrl = $links['list'] ?? ($scriptName . '?controller=task&action=index');

$title = (string)($task['title'] ?? '');
$description = (string)($task['description'] ?? '');
$status = (string)($task['status'] ?? 'not_started');
$currentProgress = (int)($task['current_progress'] ?? 0);
$progressMax = (int)($task['progress_max'] ?? 0);

$startDateTime = (string)($task['start_datetime'] ?? '');
$dueDateTime = (string)($task['due_datetime'] ?? '');

$startDateTimeLocal = view_task_edit_datetime_local($startDateTime);
$dueDateTimeLocal = view_task_edit_datetime_local($dueDateTime);

$stepMemos = (is_array($task['step_memos'] ?? null)) ? $task['step_memos'] : [];
$stepAssignments = (is_array($task['step_assignments'] ?? null)) ? $task['step_assignments'] : [];

$progressPercent = 0;
if ($progressMax > 0) {
    $progressPercent = (int)floor(($currentProgress / $progressMax) * 100);
    if ($progressPercent < 0) {
        $progressPercent = 0;
    }
    if ($progressPercent > 100) {
        $progressPercent = 100;
    }
}
?>

<style>
    .vte-page {
        width: min(1100px, 92%);
        margin: 40px auto 70px;
        font-family: "Yu Gothic", "Hiragino Sans", "Meiryo", sans-serif;
        color: #1e293b;
    }
    .vte-hero,
    .vte-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }
    .vte-hero {
        padding: 36px;
        margin-bottom: 28px;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        border: 1px solid #dbeafe;
    }
    .vte-eyebrow {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.88rem;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .vte-title {
        margin: 0 0 10px;
        font-size: 2rem;
        line-height: 1.4;
        color: #0f172a;
    }
    .vte-text {
        margin: 0;
        color: #475569;
        line-height: 1.9;
    }
    .vte-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 20px;
    }
    .vte-btn {
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
    .vte-btn:hover {
        transform: translateY(-1px);
    }
    .vte-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
    }
    .vte-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }
    .vte-message {
        padding: 16px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-weight: 700;
        line-height: 1.8;
    }
    .vte-message-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .vte-message-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .vte-grid {
        display: grid;
        grid-template-columns: 360px minmax(0, 1fr);
        gap: 22px;
        align-items: start;
    }
    .vte-card {
        padding: 24px;
    }
    .vte-section-title {
        margin: 0 0 16px;
        font-size: 1.2rem;
        color: #0f172a;
    }
    .vte-info-list {
        display: grid;
        gap: 14px;
    }
    .vte-info-item {
        padding: 14px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }
    .vte-info-label {
        display: block;
        font-size: 0.82rem;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 6px;
    }
    .vte-info-value {
        color: #0f172a;
        line-height: 1.8;
        word-break: break-word;
    }
    .vte-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .vte-badge-gray {
        background: #e2e8f0;
        color: #334155;
    }
    .vte-badge-blue {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .vte-badge-green {
        background: #dcfce7;
        color: #166534;
    }
    .vte-progress-wrap {
        margin-top: 10px;
    }
    .vte-progress-text {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 8px;
        font-size: 0.92rem;
        color: #475569;
        font-weight: 700;
    }
    .vte-progress-bar {
        width: 100%;
        height: 12px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .vte-progress-bar-inner {
        height: 100%;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
        border-radius: 999px;
    }
    .vte-form {
        display: grid;
        gap: 18px;
    }
    .vte-step-list {
        display: grid;
        gap: 14px;
    }
    .vte-step-card {
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 16px;
        padding: 16px;
    }
    .vte-step-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }
    .vte-step-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .vte-step-memo-box {
        display: grid;
        gap: 5px;
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #dbeafe;
    }
    .vte-step-memo-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: #64748b;
    }
    .vte-step-memo-value {
        color: #1e293b;
        line-height: 1.8;
        word-break: break-word;
    }
    .vte-field label {
        display: block;
        font-weight: 700;
        margin-bottom: 8px;
        color: #0f172a;
    }
    .vte-input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        font-size: 0.95rem;
        box-sizing: border-box;
        background: #ffffff;
        color: #0f172a;
    }
    .vte-note {
        margin-top: 6px;
        font-size: 0.84rem;
        color: #64748b;
        line-height: 1.7;
    }
    .vte-bottom-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 6px;
    }
    .vte-desc {
        white-space: pre-wrap;
    }
    @media (max-width: 920px) {
        .vte-grid {
            grid-template-columns: 1fr;
        }
        .vte-hero,
        .vte-card {
            padding: 22px;
        }
        .vte-title {
            font-size: 1.6rem;
        }
    }
</style>

<div class="vte-page">
    <section class="vte-hero">
        <span class="vte-eyebrow">MVC 工程日時編集</span>
        <h1 class="vte-title"><?php echo view_task_edit_h($pageTitle); ?></h1>
        <p class="vte-text">
            タスクの工程ごとの予定日時を編集できます。保存した日時は、カレンダー表示や工程管理に利用できる構成です。
        </p>
        <div class="vte-actions">
            <a href="<?php echo view_task_edit_h($mvcListUrl); ?>" class="vte-btn vte-btn-secondary">タスク一覧へ戻る</a>
            <a href="<?php echo view_task_edit_h($mvcHomeUrl); ?>" class="vte-btn vte-btn-secondary">MVCホームへ戻る</a>
        </div>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="vte-message vte-message-success">
            <?php echo view_task_edit_h($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="vte-message vte-message-error">
            <?php echo view_task_edit_h($errorMessage); ?>
        </div>
    <?php endif; ?>

    <div class="vte-grid">
        <section class="vte-card">
            <h2 class="vte-section-title">タスク情報</h2>

            <div class="vte-info-list">
                <div class="vte-info-item">
                    <span class="vte-info-label">タスク名</span>
                    <div class="vte-info-value"><?php echo view_task_edit_h($title); ?></div>
                </div>

                <div class="vte-info-item">
                    <span class="vte-info-label">ステータス</span>
                    <div class="vte-info-value">
                        <span class="<?php echo view_task_edit_h(view_task_edit_status_class($status)); ?>">
                            <?php echo view_task_edit_h(view_task_edit_status_label($status)); ?>
                        </span>
                    </div>
                </div>

                <div class="vte-info-item">
                    <span class="vte-info-label">進捗</span>
                    <div class="vte-progress-wrap">
                        <div class="vte-progress-text">
                            <span><?php echo $currentProgress; ?> / <?php echo $progressMax; ?></span>
                            <span><?php echo $progressPercent; ?>%</span>
                        </div>
                        <div class="vte-progress-bar">
                            <div class="vte-progress-bar-inner" style="width: <?php echo $progressPercent; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <div class="vte-info-item">
                    <span class="vte-info-label">開始日時</span>
                    <div class="vte-info-value"><?php echo view_task_edit_h(view_task_edit_format_datetime($startDateTime)); ?></div>
                </div>

                <div class="vte-info-item">
                    <span class="vte-info-label">終了予定日時</span>
                    <div class="vte-info-value"><?php echo view_task_edit_h(view_task_edit_format_datetime($dueDateTime)); ?></div>
                </div>

                <div class="vte-info-item">
                    <span class="vte-info-label">説明</span>
                    <div class="vte-info-value vte-desc">
                        <?php echo $description !== '' ? nl2br(view_task_edit_h($description)) : '未入力'; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="vte-card">
            <h2 class="vte-section-title">工程ごとの予定日時編集</h2>

            <form action="<?php echo view_task_edit_h($formAction); ?>" method="post" class="vte-form">
                <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">

                <div class="vte-step-list">
                    <?php for ($i = 1; $i <= $progressMax; $i++): ?>
                        <?php
                        $memo = trim((string)($stepMemos[$i] ?? ''));
                        if ($memo === '') {
                            $memo = '工程 ' . $i;
                        }

                        $assignment = $stepAssignments[$i] ?? [];
                        $assignedDateTime = (string)($assignment['assignment_date'] ?? '');
                        $assignedDateTimeLocal = view_task_edit_datetime_local($assignedDateTime);

                        $isCompletedStep = ($i <= $currentProgress);
                        ?>
                        <div class="vte-step-card">
                            <div class="vte-step-head">
                                <h3 class="vte-step-title">工程 <?php echo $i; ?></h3>
                                <?php if ($isCompletedStep): ?>
                                    <span class="vte-badge vte-badge-green">完了済み工程</span>
                                <?php else: ?>
                                    <span class="vte-badge vte-badge-blue">未完了工程</span>
                                <?php endif; ?>
                            </div>

                            <div class="vte-step-memo-box">
                                <span class="vte-step-memo-label">工程メモ</span>
                                <div class="vte-step-memo-value"><?php echo view_task_edit_h($memo); ?></div>
                            </div>

                            <div class="vte-field">
                                <label for="step_date_<?php echo $i; ?>">工程 <?php echo $i; ?> の予定日時</label>
                                <input
                                    type="datetime-local"
                                    id="step_date_<?php echo $i; ?>"
                                    name="step_dates[<?php echo $i; ?>]"
                                    class="vte-input step-date-input"
                                    value="<?php echo view_task_edit_h($assignedDateTimeLocal); ?>"
                                    min="<?php echo view_task_edit_h($startDateTimeLocal); ?>"
                                    max="<?php echo view_task_edit_h($dueDateTimeLocal); ?>"
                                >
                                <p class="vte-note">
                                    開始日時〜終了予定日時の範囲で設定してください。空欄で保存すると、その工程の日時は未設定になります。
                                </p>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="vte-bottom-actions">
                    <button type="submit" class="vte-btn vte-btn-primary">保存する</button>
                    <a href="<?php echo view_task_edit_h($mvcListUrl); ?>" class="vte-btn vte-btn-secondary">キャンセル</a>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
(function () {
    const stepDateInputs = document.querySelectorAll('.step-date-input');
    const startValue = <?php echo json_encode($startDateTimeLocal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const dueValue = <?php echo json_encode($dueDateTimeLocal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    stepDateInputs.forEach((input) => {
        if (startValue) {
            input.min = startValue;
        }
        if (dueValue) {
            input.max = dueValue;
        }

        if (input.value) {
            if (startValue && input.value < startValue) {
                input.value = startValue;
            }
            if (dueValue && input.value > dueValue) {
                input.value = dueValue;
            }
        }
    });
})();
</script>
