<?php
$pageTitle = $pageTitle ?? 'タスク追加';
$appName = $appName ?? 'タスク管理・進捗管理アプリ';
$formAction = $formAction ?? ($_SERVER['SCRIPT_NAME'] . '?controller=task&action=store');
$links = $links ?? [];
$errors = $errors ?? [];
$successMessage = $successMessage ?? '';
$errorMessage = $errorMessage ?? '';
$old = $old ?? [];

if (!function_exists('view_task_create_h')) {
    function view_task_create_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('view_task_create_old')) {
    function view_task_create_old(array $old, string $key, $default = '')
    {
        return $old[$key] ?? $default;
    }
}

if (!function_exists('view_task_create_old_step')) {
    function view_task_create_old_step(array $old, string $group, int $stepNo, $default = ''): string
    {
        if (!isset($old[$group]) || !is_array($old[$group])) {
            return (string)$default;
        }
        return (string)($old[$group][$stepNo] ?? $default);
    }
}

if (!function_exists('view_task_create_normalize_datetime_local')) {
    function view_task_create_normalize_datetime_local(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/task_app/public/index.php');
$mvcHomeUrl = $links['home'] ?? ($scriptName . '?controller=home&action=index');
$mvcListUrl = $links['list'] ?? ($scriptName . '?controller=task&action=index');

$nowMinute = strtotime(date('Y-m-d H:i'));
$defaultStart = date('Y-m-d\TH:i', $nowMinute);
$defaultDue = date('Y-m-d\TH:i', strtotime('+1 hour', $nowMinute));

$title = (string)view_task_create_old($old, 'title', '');
$description = (string)view_task_create_old($old, 'description', '');
$progressMax = (int)view_task_create_old($old, 'progress_max', 5);
if ($progressMax < 1 || $progressMax > 10) {
    $progressMax = 5;
}

$startDatetimeInput = view_task_create_normalize_datetime_local(
    (string)view_task_create_old($old, 'start_datetime', $defaultStart)
);
$dueDatetimeInput = view_task_create_normalize_datetime_local(
    (string)view_task_create_old($old, 'due_datetime', $defaultDue)
);

if ($startDatetimeInput === '') {
    $startDatetimeInput = $defaultStart;
}
if ($dueDatetimeInput === '') {
    $dueDatetimeInput = $defaultDue;
}

if ($successMessage === '' && isset($_GET['success']) && $_GET['success'] === '1') {
    $successMessage = 'タスクを登録しました。';
}
if ($errorMessage !== '' && empty($errors)) {
    $errors[] = $errorMessage;
}
?>

<style>
    .vtc-page {
        width: min(1100px, 92%);
        margin: 40px auto 70px;
        font-family: "Yu Gothic", "Hiragino Sans", "Meiryo", sans-serif;
        color: #1e293b;
    }
    .vtc-hero,
    .vtc-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }
    .vtc-hero {
        padding: 36px;
        margin-bottom: 28px;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        border: 1px solid #dbeafe;
    }
    .vtc-eyebrow {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 0.88rem;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .vtc-title {
        margin: 0 0 12px;
        font-size: 2rem;
        line-height: 1.4;
        color: #0f172a;
    }
    .vtc-text {
        margin: 0;
        color: #475569;
        line-height: 1.9;
    }
    .vtc-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 20px;
    }
    .vtc-btn {
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
    .vtc-btn:hover {
        transform: translateY(-1px);
    }
    .vtc-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
    }
    .vtc-btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }
    .vtc-message {
        padding: 16px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-weight: 700;
        line-height: 1.8;
    }
    .vtc-message-success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .vtc-message-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .vtc-card {
        padding: 28px;
    }
    .vtc-form-grid {
        display: grid;
        gap: 22px;
    }
    .vtc-field label,
    .vtc-step-label,
    .vtc-section-title {
        display: block;
        font-weight: 700;
        margin-bottom: 8px;
        color: #0f172a;
    }
    .vtc-input,
    .vtc-textarea,
    .vtc-select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        font-size: 0.95rem;
        box-sizing: border-box;
        background: #ffffff;
        color: #0f172a;
    }
    .vtc-textarea {
        resize: vertical;
        min-height: 130px;
    }
    .vtc-two-col {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }
    .vtc-note {
        margin-top: 6px;
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.7;
    }
    .vtc-steps-wrap {
        display: grid;
        gap: 14px;
    }
    .vtc-step-item {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
        gap: 14px;
        align-items: end;
        padding: 16px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .vtc-step-title {
        margin: 0 0 8px;
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
    }
    .vtc-bottom-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 8px;
    }
    .vtc-error-list {
        margin: 0;
        padding-left: 20px;
    }
    .vtc-error-list li {
        margin-bottom: 4px;
    }
    @media (max-width: 860px) {
        .vtc-two-col,
        .vtc-step-item {
            grid-template-columns: 1fr;
        }
        .vtc-hero,
        .vtc-card {
            padding: 22px;
        }
        .vtc-title {
            font-size: 1.6rem;
        }
    }
</style>

<div class="vtc-page">
    <section class="vtc-hero">
        <span class="vtc-eyebrow">MVC タスク追加</span>
        <h1 class="vtc-title"><?php echo view_task_create_h($pageTitle); ?></h1>
        <p class="vtc-text">
            タスク名・開始日時・終了予定日時・工程内容・工程ごとの予定日時を登録できます。
            登録した工程日時は、カレンダー表示用データとして利用できる構成です。
        </p>
        <div class="vtc-actions">
            <a href="<?php echo view_task_create_h($mvcHomeUrl); ?>" class="vtc-btn vtc-btn-secondary">MVCホームへ戻る</a>
            <a href="<?php echo view_task_create_h($mvcListUrl); ?>" class="vtc-btn vtc-btn-secondary">タスク一覧へ</a>
        </div>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="vtc-message vtc-message-success">
            <?php echo view_task_create_h($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="vtc-message vtc-message-error">
            <ul class="vtc-error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo view_task_create_h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="vtc-card">
        <form action="<?php echo view_task_create_h($formAction); ?>" method="post" id="taskCreateForm">
            <div class="vtc-form-grid">
                <div class="vtc-field">
                    <label for="title">タスク名</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        class="vtc-input"
                        maxlength="100"
                        required
                        value="<?php echo view_task_create_h($title); ?>"
                        placeholder="例：5月月次レポート作成"
                    >
                    <p class="vtc-note">100文字以内で入力してください。</p>
                </div>

                <div class="vtc-field">
                    <label for="description">詳細</label>
                    <textarea
                        id="description"
                        name="description"
                        class="vtc-textarea"
                        maxlength="1000"
                        placeholder="タスクの詳細や補足事項を入力できます。"><?php echo view_task_create_h($description); ?></textarea>
                    <p class="vtc-note">任意入力です。1000文字以内。</p>
                </div>

                <div class="vtc-field">
                    <label for="progress_max">工程数</label>
                    <select id="progress_max" name="progress_max" class="vtc-select" required>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($progressMax === $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> 工程
                            </option>
                        <?php endfor; ?>
                    </select>
                    <p class="vtc-note">1〜10 の範囲で選択してください。</p>
                </div>

                <div class="vtc-two-col">
                    <div class="vtc-field">
                        <label for="start_datetime">開始日時</label>
                        <input
                            type="datetime-local"
                            id="start_datetime"
                            name="start_datetime"
                            class="vtc-input"
                            required
                            min="<?php echo view_task_create_h($defaultStart); ?>"
                            value="<?php echo view_task_create_h($startDatetimeInput); ?>"
                        >
                        <p class="vtc-note">現在時刻以降を指定してください。</p>
                    </div>

                    <div class="vtc-field">
                        <label for="due_datetime">終了予定日時</label>
                        <input
                            type="datetime-local"
                            id="due_datetime"
                            name="due_datetime"
                            class="vtc-input"
                            required
                            min="<?php echo view_task_create_h($startDatetimeInput); ?>"
                            value="<?php echo view_task_create_h($dueDatetimeInput); ?>"
                        >
                        <p class="vtc-note">開始日時以降を指定してください。</p>
                    </div>
                </div>

                <div>
                    <h2 class="vtc-section-title">工程内容と予定日時</h2>
                    <p class="vtc-note" style="margin-bottom: 14px;">
                        工程ごとのメモと予定日時を設定できます。予定日時を登録すると、後でカレンダー表示に反映しやすくなります。
                    </p>

                    <div class="vtc-steps-wrap">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <?php
                            $stepMemoValue = view_task_create_old_step($old, 'step_memos', $i, '');
                            $stepDateValue = view_task_create_normalize_datetime_local(
                                view_task_create_old_step($old, 'step_dates', $i, '')
                            );
                            ?>
                            <div class="vtc-step-item" data-step-no="<?php echo $i; ?>" id="step_item_<?php echo $i; ?>">
                                <div class="vtc-field">
                                    <h3 class="vtc-step-title">工程 <?php echo $i; ?></h3>
                                    <label for="step_memo_<?php echo $i; ?>" class="vtc-step-label">工程メモ</label>
                                    <input
                                        type="text"
                                        id="step_memo_<?php echo $i; ?>"
                                        name="step_memos[<?php echo $i; ?>]"
                                        class="vtc-input"
                                        maxlength="30"
                                        value="<?php echo view_task_create_h($stepMemoValue); ?>"
                                        placeholder="例：下書き作成"
                                    >
                                    <p class="vtc-note">未入力なら保存時に「工程 <?php echo $i; ?>」として扱える構成です。</p>
                                </div>

                                <div class="vtc-field">
                                    <label for="step_date_<?php echo $i; ?>" class="vtc-step-label">予定日時</label>
                                    <input
                                        type="datetime-local"
                                        id="step_date_<?php echo $i; ?>"
                                        name="step_dates[<?php echo $i; ?>]"
                                        class="vtc-input step-date-input"
                                        value="<?php echo view_task_create_h($stepDateValue); ?>"
                                        min="<?php echo view_task_create_h($startDatetimeInput); ?>"
                                        max="<?php echo view_task_create_h($dueDatetimeInput); ?>"
                                    >
                                    <p class="vtc-note">開始日時〜終了予定日時の範囲で設定してください。空欄でも登録できます。</p>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="vtc-bottom-actions">
                    <button type="submit" class="vtc-btn vtc-btn-primary">登録する</button>
                    <a href="<?php echo view_task_create_h($mvcListUrl); ?>" class="vtc-btn vtc-btn-secondary">一覧へ戻る</a>
                    <a href="<?php echo view_task_create_h($mvcHomeUrl); ?>" class="vtc-btn vtc-btn-secondary">ホームへ戻る</a>
                </div>
            </div>
        </form>
    </section>
</div>

<script>
(function () {
    const startInput = document.getElementById('start_datetime');
    const dueInput = document.getElementById('due_datetime');
    const progressMaxSelect = document.getElementById('progress_max');
    const stepItems = document.querySelectorAll('.vtc-step-item');
    const stepDateInputs = document.querySelectorAll('.step-date-input');

    function syncDueMin() {
        const startValue = startInput.value || '';
        if (startValue !== '') {
            dueInput.min = startValue;
            if (!dueInput.value || dueInput.value < startValue) {
                dueInput.value = startValue;
            }
        }
    }

    function syncStepDateRange() {
        const startValue = startInput.value || '';
        const dueValue = dueInput.value || '';

        stepDateInputs.forEach((input) => {
            input.min = startValue;
            input.max = dueValue;

            if (input.value) {
                if (startValue && input.value < startValue) {
                    input.value = startValue;
                }
                if (dueValue && input.value > dueValue) {
                    input.value = dueValue;
                }
            }
        });
    }

    function toggleStepItems() {
        const max = parseInt(progressMaxSelect.value, 10) || 1;

        stepItems.forEach((item) => {
            const stepNo = parseInt(item.dataset.stepNo, 10);
            const inputs = item.querySelectorAll('input');

            if (stepNo <= max) {
                item.style.display = 'grid';
                inputs.forEach((input) => {
                    input.disabled = false;
                });
            } else {
                item.style.display = 'none';
                inputs.forEach((input) => {
                    input.disabled = true;
                    if (input.type !== 'hidden') {
                        input.value = '';
                    }
                });
            }
        });
    }

    startInput.addEventListener('change', function () {
        syncDueMin();
        syncStepDateRange();
    });

    startInput.addEventListener('input', function () {
        syncDueMin();
        syncStepDateRange();
    });

    dueInput.addEventListener('change', syncStepDateRange);
    dueInput.addEventListener('input', syncStepDateRange);
    progressMaxSelect.addEventListener('change', toggleStepItems);

    window.addEventListener('DOMContentLoaded', function () {
        syncDueMin();
        syncStepDateRange();
        toggleStepItems();
    });
})();
</script>
