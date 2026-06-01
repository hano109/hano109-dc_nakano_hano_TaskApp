<?php
require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Tokyo');

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getOldStepMemo(array $stepMemos, int $stepNo): string
{
    return isset($stepMemos[$stepNo]) ? (string)$stepMemos[$stepNo] : '';
}

function getOldStepDate(array $stepDates, int $stepNo): string
{
    return isset($stepDates[$stepNo]) ? (string)$stepDates[$stepNo] : '';
}

function getOrCreateDevelopmentUserId(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $user = $stmt->fetch();

    if ($user) {
        return (int)$user['id'];
    }

    $sql = "INSERT INTO users (name, email, password_hash)
            VALUES (:name, :email, :password_hash)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => '開発用ユーザー',
        ':email' => 'demo@example.com',
        ':password_hash' => password_hash('demo1234', PASSWORD_DEFAULT),
    ]);

    return (int)$pdo->lastInsertId();
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

$title = '';
$description = '';
$progressMax = 5;
$startDatetimeInput = '';
$dueDatetimeInput = '';
$stepMemos = [];
$stepDates = [];
$errors = [];
$successMessage = '';

$nowTimestamp = strtotime(date('Y-m-d H:i'));
$nowDateTimeLocal = date('Y-m-d\TH:i', $nowTimestamp);

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $successMessage = 'タスクを登録しました。';
}

for ($i = 1; $i <= 10; $i++) {
    $stepMemos[$i] = '';
    $stepDates[$i] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $progressMax = (int)($_POST['progress_max'] ?? 5);
    $startDatetimeInput = trim($_POST['start_datetime'] ?? '');
    $dueDatetimeInput = trim($_POST['due_datetime'] ?? '');
    $postedStepMemos = $_POST['step_memos'] ?? [];
    $postedStepDates = $_POST['step_dates'] ?? [];

    for ($i = 1; $i <= 10; $i++) {
        $stepMemos[$i] = trim((string)($postedStepMemos[$i] ?? ''));
        $stepDates[$i] = trim((string)($postedStepDates[$i] ?? ''));
    }

    if ($title === '') {
        $errors[] = 'タスク名を入力してください。';
    } elseif (mb_strlen($title) > 100) {
        $errors[] = 'タスク名は100文字以内で入力してください。';
    }

    if ($description !== '' && mb_strlen($description) > 1000) {
        $errors[] = '詳細は1000文字以内で入力してください。';
    }

    if ($progressMax < 1 || $progressMax > 10) {
        $errors[] = '最大メモリ数は1〜10の範囲で入力してください。';
    }

    if ($startDatetimeInput === '') {
        $errors[] = '開始日時を入力してください。';
    }

    if ($dueDatetimeInput === '') {
        $errors[] = '終了予定日時を入力してください。';
    }

    $startTimestamp = ($startDatetimeInput !== '') ? strtotime($startDatetimeInput) : false;
    $dueTimestamp = ($dueDatetimeInput !== '') ? strtotime($dueDatetimeInput) : false;

    if ($startDatetimeInput !== '' && $startTimestamp === false) {
        $errors[] = '開始日時の形式が正しくありません。';
    }

    if ($dueDatetimeInput !== '' && $dueTimestamp === false) {
        $errors[] = '終了予定日時の形式が正しくありません。';
    }

    if ($startTimestamp !== false && $startTimestamp < $nowTimestamp) {
        $errors[] = '開始日時には現在より前の日時を指定できません。';
    }

    if ($startTimestamp !== false && $dueTimestamp !== false && $startTimestamp > $dueTimestamp) {
        $errors[] = '終了予定日時は開始日時以降にしてください。';
    }

    for ($i = 1; $i <= $progressMax; $i++) {
        if ($stepMemos[$i] !== '' && mb_strlen($stepMemos[$i]) > 30) {
            $errors[] = '工程 ' . $i . ' は30文字以内で入力してください。';
        }

        $stepDateValue = trim((string)$stepDates[$i]);
        $stepDates[$i] = $stepDateValue;

        if ($stepDateValue === '') {
            continue;
        }

        $stepTimestamp = strtotime($stepDateValue);

        if ($stepTimestamp === false) {
            $errors[] = '工程 ' . $i . ' の予定日時の形式が正しくありません。';
            continue;
        }

        if ($startTimestamp !== false && $dueTimestamp !== false) {
            if ($stepTimestamp < $startTimestamp || $stepTimestamp > $dueTimestamp) {
                $errors[] = '工程 ' . $i . ' の予定日時は開始日時から終了予定日時までの範囲で指定してください。';
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $userId = getOrCreateDevelopmentUserId($pdo);

            $startDatetime = date('Y-m-d H:i:s', $startTimestamp);
            $dueDatetime = date('Y-m-d H:i:s', $dueTimestamp);

            $status = 'not_started';
            $currentProgress = 0;

            $sql = "INSERT INTO tasks (
                        user_id,
                        title,
                        description,
                        progress_max,
                        current_progress,
                        status,
                        start_datetime,
                        due_datetime
                    ) VALUES (
                        :user_id,
                        :title,
                        :description,
                        :progress_max,
                        :current_progress,
                        :status,
                        :start_datetime,
                        :due_datetime
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':description' => $description !== '' ? $description : null,
                ':progress_max' => $progressMax,
                ':current_progress' => $currentProgress,
                ':status' => $status,
                ':start_datetime' => $startDatetime,
                ':due_datetime' => $dueDatetime,
            ]);

            $taskId = (int)$pdo->lastInsertId();

            $memoSql = "INSERT INTO task_step_memos (task_id, step_no, memo)
                        VALUES (:task_id, :step_no, :memo)";
            $memoStmt = $pdo->prepare($memoSql);

            $assignmentSql = "
                INSERT INTO task_calendar_assignments (
                    task_id,
                    step_no,
                    assignment_date,
                    step_memo
                ) VALUES (
                    :task_id,
                    :step_no,
                    :assignment_date,
                    :step_memo
                )
            ";
            $assignmentStmt = $pdo->prepare($assignmentSql);

            for ($i = 1; $i <= $progressMax; $i++) {
                $stepMemo = $stepMemos[$i] !== '' ? $stepMemos[$i] : '工程 ' . $i;

                $memoStmt->execute([
                    ':task_id' => $taskId,
                    ':step_no' => $i,
                    ':memo' => $stepMemo,
                ]);

                if ($stepDates[$i] !== '') {
                    $assignmentStmt->execute([
                        ':task_id' => $taskId,
                        ':step_no' => $i,
                        ':assignment_date' => date('Y-m-d H:i:s', strtotime($stepDates[$i])),
                        ':step_memo' => $stepMemo,
                    ]);
                }
            }

            $pdo->commit();

            header('Location: task_create.php?success=1');
            exit;
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'タスク登録に失敗しました: ' . $e->getMessage();
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = '処理中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

if ($startDatetimeInput === '') {
    $startDatetimeInput = $nowDateTimeLocal;
}

if ($dueDatetimeInput === '') {
    $dueDatetimeInput = date('Y-m-d\TH:i', strtotime('+1 hour'));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タスク追加 | タスク管理アプリ</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            <h2>タスク追加</h2>
            <p>
                各工程ごとに内容と予定日時を設定できます。設定した工程は、その日時のカレンダーに反映されます。
            </p>
        </section>

        <?php if ($successMessage !== ''): ?>
            <section class="message" style="background:#dcfce7; color:#166534; border:1px solid #86efac; margin-bottom:24px;">
                <p><?php echo h($successMessage); ?></p>
            </section>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <section class="message error-message">
                <ul style="padding-left:20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="card">
            <form action="task_create.php" method="post" id="taskCreateForm">
                <div style="display:grid; gap:20px;">
                    <div>
                        <label for="title" style="display:block; font-weight:700; margin-bottom:8px;">タスク名</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="100"
                            value="<?php echo h($title); ?>"
                            required
                            style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px;"
                        >
                    </div>

                    <div>
                        <label for="description" style="display:block; font-weight:700; margin-bottom:8px;">詳細</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="5"
                            maxlength="1000"
                            style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px; resize:vertical;"
                        ><?php echo h($description); ?></textarea>
                    </div>

                    <div>
                        <label for="progress_max" style="display:block; font-weight:700; margin-bottom:8px;">最大メモリ数</label>
                        <select
                            id="progress_max"
                            name="progress_max"
                            required
                            style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px;"
                        >
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($progressMax === $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px;">
                        <div>
                            <label for="start_datetime" style="display:block; font-weight:700; margin-bottom:8px;">開始日時</label>
                            <input
                                type="datetime-local"
                                id="start_datetime"
                                name="start_datetime"
                                value="<?php echo h($startDatetimeInput); ?>"
                                min="<?php echo h($nowDateTimeLocal); ?>"
                                required
                                style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px;"
                            >
                        </div>

                        <div>
                            <label for="due_datetime" style="display:block; font-weight:700; margin-bottom:8px;">終了予定日時</label>
                            <input
                                type="datetime-local"
                                id="due_datetime"
                                name="due_datetime"
                                value="<?php echo h($dueDatetimeInput); ?>"
                                min="<?php echo h($startDatetimeInput !== '' ? $startDatetimeInput : $nowDateTimeLocal); ?>"
                                required
                                style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px;"
                            >
                        </div>
                    </div>

                    <div>
                        <h3 class="section-title" style="margin-bottom:12px;">工程内容と予定日時</h3>

                        <div style="display:grid; gap:14px;">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <div
                                    class="step-item"
                                    data-step-no="<?php echo $i; ?>"
                                    id="step_item_<?php echo $i; ?>"
                                    style="display:grid; grid-template-columns:minmax(0, 2fr) minmax(220px, 1fr); gap:12px; align-items:end; padding:14px; background:#f8fafc; border-radius:12px;"
                                >
                                    <div>
                                        <label for="step_memo_<?php echo $i; ?>" style="display:block; font-weight:700; margin-bottom:8px;">
                                            工程 <?php echo $i; ?>
                                        </label>
                                        <input
                                            type="text"
                                            id="step_memo_<?php echo $i; ?>"
                                            name="step_memos[<?php echo $i; ?>]"
                                            maxlength="30"
                                            value="<?php echo h(getOldStepMemo($stepMemos, $i)); ?>"
                                            placeholder="工程 <?php echo $i; ?> の内容"
                                            style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px;"
                                        >
                                    </div>

                                    <div>
                                        <label for="step_date_<?php echo $i; ?>" style="display:block; font-weight:700; margin-bottom:8px;">
                                            予定日時
                                        </label>
                                        <input
                                            type="datetime-local"
                                            id="step_date_<?php echo $i; ?>"
                                            name="step_dates[<?php echo $i; ?>]"
                                            class="step-date-input"
                                            value="<?php echo h(getOldStepDate($stepDates, $i)); ?>"
                                            min="<?php echo h($startDatetimeInput); ?>"
                                            max="<?php echo h($dueDatetimeInput); ?>"
                                            style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:10px;"
                                        >
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="button-group" style="margin-top:8px;">
                        <button
                            type="submit"
                            class="btn"
                            style="border:none; cursor:pointer;"
                        >
                            登録する
                        </button>
                        <a href="index.php" class="btn btn-secondary">ホームに戻る</a>
                        <a href="task_list.php" class="btn btn-secondary">タスク一覧へ</a>
                    </div>
                </div>
            </form>
        </section>
    </main>

    <script>
        const startInput = document.getElementById('start_datetime');
        const dueInput = document.getElementById('due_datetime');
        const progressMaxSelect = document.getElementById('progress_max');
        const stepItems = document.querySelectorAll('.step-item');
        const stepDateInputs = document.querySelectorAll('.step-date-input');

        function syncDueMin() {
            const startValue = startInput.value;

            if (startValue) {
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
            const max = parseInt(progressMaxSelect.value, 10);

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
                    });
                }
            });
        }

        startInput.addEventListener('change', () => {
            syncDueMin();
            syncStepDateRange();
        });

        startInput.addEventListener('input', () => {
            syncDueMin();
            syncStepDateRange();
        });

        dueInput.addEventListener('change', syncStepDateRange);
        dueInput.addEventListener('input', syncStepDateRange);
        progressMaxSelect.addEventListener('change', toggleStepItems);

        window.addEventListener('DOMContentLoaded', () => {
            syncDueMin();
            syncStepDateRange();
            toggleStepItems();
        });
    </script>
</body>
</html>
