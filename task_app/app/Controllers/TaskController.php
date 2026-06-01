<?php

require_once dirname(__DIR__) . '/Models/Task.php';

class TaskController
{
    private Task $taskModel;

    public function __construct()
    {
        $this->taskModel = new Task();
    }

    public function index(): void
    {
        $filterStatus = trim((string)($_GET['status'] ?? 'all'));
        $allowedStatuses = ['all', 'not_started', 'in_progress', 'completed'];

        if (!in_array($filterStatus, $allowedStatuses, true)) {
            $filterStatus = 'all';
        }

        try {
            $summary = $this->taskModel->getSummaryCounts();
            $tasks = $this->taskModel->getAll($filterStatus);

            if (!$this->viewExists('tasks/list')) {
                $this->renderSimplePage(
                    'タスク一覧Viewが見つかりません',
                    'app/Views/tasks/list.php を確認してください。',
                    [
                        'ホームへ戻る' => $this->url('home', 'index'),
                        'タスク追加へ' => $this->url('task', 'create'),
                    ]
                );
                return;
            }

            View::render('tasks/list', [
                'pageTitle' => 'タスク一覧',
                'appName' => 'タスク管理・進捗管理アプリ',
                'links' => [
                    'home' => $this->url('home', 'index'),
                    'create' => $this->url('task', 'create'),
                ],
                'summary' => $summary,
                'tasks' => $tasks,
                'filterStatus' => $filterStatus,
                'successMessage' => $this->resolveListSuccessMessage(),
                'errorMessage' => trim((string)($_GET['error'] ?? '')),
                'statusLinks' => [
                    'all' => $this->url('task', 'index', ['status' => 'all']),
                    'not_started' => $this->url('task', 'index', ['status' => 'not_started']),
                    'in_progress' => $this->url('task', 'index', ['status' => 'in_progress']),
                    'completed' => $this->url('task', 'index', ['status' => 'completed']),
                ],
            ]);
        } catch (Throwable $e) {
            $this->renderSimplePage(
                'タスク一覧の取得に失敗しました',
                $e->getMessage(),
                [
                    'ホームへ戻る' => $this->url('home', 'index'),
                ]
            );
        }
    }

    public function create(): void
    {
        $successMessage = '';
        if (isset($_GET['success']) && $_GET['success'] === '1') {
            $successMessage = 'タスクを登録しました。';
        }

        $this->renderCreateView([], [], $successMessage, trim((string)($_GET['error'] ?? '')));
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url('task', 'create'));
            return;
        }

        $old = $this->collectCreateInputFromPost();
        $errors = $this->validateCreateInput($old);

        if (!empty($errors)) {
            $this->renderCreateView($old, $errors, '', '');
            return;
        }

        try {
            $taskId = $this->taskModel->create([
                'title' => $old['title'],
                'description' => $old['description'],
                'progress_max' => (int)$old['progress_max'],
                'start_datetime' => $old['start_datetime'],
                'due_datetime' => $old['due_datetime'],
                'step_memos' => $old['step_memos'],
                'step_dates' => $old['step_dates'],
            ]);

            $this->redirect($this->url('task', 'create', [
                'success' => '1',
                'task_id' => $taskId,
            ]));
        } catch (Throwable $e) {
            $this->renderCreateView(
                $old,
                ['タスク登録に失敗しました: ' . $e->getMessage()],
                '',
                ''
            );
        }
    }

    public function edit(): void
    {
        $taskId = (int)($_GET['task_id'] ?? 0);

        if ($taskId <= 0) {
            $this->redirect($this->url('task', 'index', [
                'error' => 'タスクIDが不正です。',
            ]));
            return;
        }

        $this->renderEditView(
            $taskId,
            $this->resolveEditSuccessMessage(),
            trim((string)($_GET['error'] ?? ''))
        );
    }

    public function updateschedule(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url('task', 'index'));
            return;
        }

        $taskId = (int)($_POST['task_id'] ?? 0);

        if ($taskId <= 0) {
            $this->redirect($this->url('task', 'index', [
                'error' => 'タスクIDが不正です。',
            ]));
            return;
        }

        $postedStepDates = $_POST['step_dates'] ?? [];
        if (!is_array($postedStepDates)) {
            $postedStepDates = [];
        }

        try {
            $this->taskModel->updateStepSchedules($taskId, $postedStepDates);

            $this->redirect($this->url('task', 'edit', [
                'task_id' => $taskId,
                'saved' => '1',
            ]));
        } catch (Throwable $e) {
            $this->renderEditView(
                $taskId,
                '',
                $e->getMessage(),
                $postedStepDates
            );
        }
    }

    public function updateprogress(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url('task', 'index'));
            return;
        }

        $taskId = (int)($_POST['task_id'] ?? 0);
        $actionType = trim((string)($_POST['action_type'] ?? ''));
        $statusFilter = trim((string)($_POST['status_filter'] ?? 'all'));

        if ($taskId <= 0) {
            $this->redirect($this->url('task', 'index', [
                'status' => $statusFilter,
                'error' => 'タスクIDが不正です。',
            ]));
            return;
        }

        try {
            $this->taskModel->updateProgressByAction($taskId, $actionType);

            $query = ['status' => $statusFilter];
            if ($actionType === 'complete') {
                $query['completed'] = '1';
            } else {
                $query['updated'] = '1';
            }

            $this->redirect($this->url('task', 'index', $query));
        } catch (Throwable $e) {
            $this->redirect($this->url('task', 'index', [
                'status' => $statusFilter,
                'error' => $e->getMessage(),
            ]));
        }
    }

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url('task', 'index'));
            return;
        }

        $taskId = (int)($_POST['task_id'] ?? 0);
        $statusFilter = trim((string)($_POST['status_filter'] ?? 'all'));

        if ($taskId <= 0) {
            $this->redirect($this->url('task', 'index', [
                'status' => $statusFilter,
                'error' => 'タスクIDが不正です。',
            ]));
            return;
        }

        try {
            $this->taskModel->delete($taskId);

            $this->redirect($this->url('task', 'index', [
                'status' => $statusFilter,
                'deleted' => '1',
            ]));
        } catch (Throwable $e) {
            $this->redirect($this->url('task', 'index', [
                'status' => $statusFilter,
                'error' => $e->getMessage(),
            ]));
        }
    }

    private function renderCreateView(
        array $old = [],
        array $errors = [],
        string $successMessage = '',
        string $errorMessage = ''
    ): void {
        if (!$this->viewExists('tasks/create')) {
            $this->renderSimplePage(
                'タスク追加Viewが見つかりません',
                'app/Views/tasks/create.php を確認してください。',
                [
                    'ホームへ戻る' => $this->url('home', 'index'),
                    'タスク一覧へ' => $this->url('task', 'index'),
                ]
            );
            return;
        }

        View::render('tasks/create', [
            'pageTitle' => 'タスク追加',
            'appName' => 'タスク管理・進捗管理アプリ',
            'formAction' => $this->url('task', 'store'),
            'links' => [
                'home' => $this->url('home', 'index'),
                'list' => $this->url('task', 'index'),
            ],
            'errors' => $errors,
            'successMessage' => $successMessage,
            'errorMessage' => $errorMessage,
            'old' => $old,
        ]);
    }

    private function renderEditView(
        int $taskId,
        string $successMessage = '',
        string $errorMessage = '',
        array $postedStepDates = []
    ): void {
        if (!$this->viewExists('tasks/edit')) {
            $this->renderSimplePage(
                '工程日時編集Viewが見つかりません',
                'app/Views/tasks/edit.php を確認してください。',
                [
                    'タスク一覧へ戻る' => $this->url('task', 'index'),
                ]
            );
            return;
        }

        try {
            $task = $this->taskModel->findById($taskId);

            if (!$task) {
                $this->redirect($this->url('task', 'index', [
                    'error' => '対象のタスクが見つかりません。',
                ]));
                return;
            }

            if (!isset($task['step_assignments']) || !is_array($task['step_assignments'])) {
                $task['step_assignments'] = [];
            }

            if (!empty($postedStepDates)) {
                $progressMax = (int)($task['progress_max'] ?? 0);

                for ($i = 1; $i <= $progressMax; $i++) {
                    $value = trim((string)($postedStepDates[$i] ?? ''));

                    if ($value === '') {
                        unset($task['step_assignments'][$i]);
                        continue;
                    }

                    $timestamp = strtotime($value);

                    $task['step_assignments'][$i] = [
                        'assignment_date' => $timestamp === false ? $value : date('Y-m-d H:i:s', $timestamp),
                        'step_memo' => $task['step_memos'][$i] ?? ('工程 ' . $i),
                    ];
                }
            }

            View::render('tasks/edit', [
                'pageTitle' => '工程日時編集',
                'appName' => 'タスク管理・進捗管理アプリ',
                'task' => $task,
                'taskId' => $taskId,
                'formAction' => $this->url('task', 'updateschedule'),
                'links' => [
                    'home' => $this->url('home', 'index'),
                    'list' => $this->url('task', 'index'),
                ],
                'successMessage' => $successMessage,
                'errorMessage' => $errorMessage,
            ]);
        } catch (Throwable $e) {
            $this->renderSimplePage(
                '工程編集画面の表示に失敗しました',
                $e->getMessage(),
                [
                    'タスク一覧へ戻る' => $this->url('task', 'index'),
                ]
            );
        }
    }

    private function collectCreateInputFromPost(): array
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $progressMax = (int)($_POST['progress_max'] ?? 5);
        $startDatetime = $this->normalizeDateTimeLocalInput((string)($_POST['start_datetime'] ?? ''));
        $dueDatetime = $this->normalizeDateTimeLocalInput((string)($_POST['due_datetime'] ?? ''));

        if ($progressMax < 1 || $progressMax > 10) {
            $progressMax = 5;
        }

        $postedStepMemos = $_POST['step_memos'] ?? [];
        $postedStepDates = $_POST['step_dates'] ?? [];

        $stepMemos = [];
        $stepDates = [];

        for ($i = 1; $i <= 10; $i++) {
            $stepMemos[$i] = trim((string)($postedStepMemos[$i] ?? ''));
            $stepDates[$i] = $this->normalizeDateTimeLocalInput((string)($postedStepDates[$i] ?? ''));
        }

        return [
            'title' => $title,
            'description' => $description,
            'progress_max' => $progressMax,
            'start_datetime' => $startDatetime,
            'due_datetime' => $dueDatetime,
            'step_memos' => $stepMemos,
            'step_dates' => $stepDates,
        ];
    }

    private function validateCreateInput(array $input): array
    {
        $errors = [];

        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $progressMax = (int)($input['progress_max'] ?? 0);
        $startDatetime = trim((string)($input['start_datetime'] ?? ''));
        $dueDatetime = trim((string)($input['due_datetime'] ?? ''));
        $stepMemos = $input['step_memos'] ?? [];
        $stepDates = $input['step_dates'] ?? [];

        if ($title === '') {
            $errors[] = 'タスク名を入力してください。';
        } elseif (mb_strlen($title) > 100) {
            $errors[] = 'タスク名は100文字以内で入力してください。';
        }

        if ($description !== '' && mb_strlen($description) > 1000) {
            $errors[] = '詳細は1000文字以内で入力してください。';
        }

        if ($progressMax < 1 || $progressMax > 10) {
            $errors[] = '工程数は1〜10の範囲で入力してください。';
        }

        if ($startDatetime === '') {
            $errors[] = '開始日時を入力してください。';
        }

        if ($dueDatetime === '') {
            $errors[] = '終了予定日時を入力してください。';
        }

        $startTimestamp = ($startDatetime !== '') ? strtotime($startDatetime) : false;
        $dueTimestamp = ($dueDatetime !== '') ? strtotime($dueDatetime) : false;

        if ($startDatetime !== '' && $startTimestamp === false) {
            $errors[] = '開始日時の形式が正しくありません。';
        }

        if ($dueDatetime !== '' && $dueTimestamp === false) {
            $errors[] = '終了予定日時の形式が正しくありません。';
        }

        $nowTimestamp = strtotime(date('Y-m-d H:i'));

        if ($startTimestamp !== false && $startTimestamp < $nowTimestamp) {
            $errors[] = '開始日時には現在より前の日時を指定できません。';
        }

        if ($startTimestamp !== false && $dueTimestamp !== false && $startTimestamp > $dueTimestamp) {
            $errors[] = '終了予定日時は開始日時以降にしてください。';
        }

        if ($progressMax >= 1 && $progressMax <= 10) {
            for ($i = 1; $i <= $progressMax; $i++) {
                $memoValue = trim((string)($stepMemos[$i] ?? ''));
                if ($memoValue !== '' && mb_strlen($memoValue) > 30) {
                    $errors[] = '工程 ' . $i . ' のメモは30文字以内で入力してください。';
                }

                $dateValue = trim((string)($stepDates[$i] ?? ''));
                if ($dateValue === '') {
                    continue;
                }

                $stepTimestamp = strtotime($dateValue);
                if ($stepTimestamp === false) {
                    $errors[] = '工程 ' . $i . ' の日時形式が不正です。';
                    continue;
                }

                if ($startTimestamp !== false && $dueTimestamp !== false) {
                    if ($stepTimestamp < $startTimestamp || $stepTimestamp > $dueTimestamp) {
                        $errors[] = '工程 ' . $i . ' の日時は開始日時から終了予定日時までの間で設定してください。';
                    }
                }
            }
        }

        return $errors;
    }

    private function normalizeDateTimeLocalInput(?string $value): string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d\TH:i', $timestamp);
    }

    private function resolveListSuccessMessage(): string
    {
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            return '進捗を更新しました。';
        }
        if (isset($_GET['completed']) && $_GET['completed'] === '1') {
            return 'タスクを完了にしました。';
        }
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            return 'タスクを削除しました。';
        }
        return '';
    }

    private function resolveEditSuccessMessage(): string
    {
        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            return '工程日時を保存しました。';
        }
        return '';
    }

    private function url(string $controller, string $action = 'index', array $params = []): string
    {
        $query = array_merge([
            'controller' => $controller,
            'action' => $action,
        ], $params);

        return $this->publicEntryUrl() . '?' . http_build_query($query);
    }

    private function publicEntryUrl(): string
    {
        return $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
    }

    private function viewExists(string $view): bool
    {
        $path = dirname(__DIR__) . '/Views/' . $view . '.php';
        return file_exists($path) && filesize($path) > 0;
    }

    private function redirect(string $url, int $statusCode = 302): void
    {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }

    private function renderSimplePage(string $title, string $message, array $links = []): void
    {
        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</title><style>
                body{margin:0;font-family:"Yu Gothic","Meiryo",sans-serif;background:#f5f7fb;color:#1e293b;}
                .wrap{max-width:960px;margin:60px auto;padding:0 20px;}
                .card{background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.08);}
                h1{margin-top:0;color:#2563eb;}
                p{line-height:1.8;white-space:pre-wrap;}
                .links{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px;}
                a{display:inline-block;text-decoration:none;background:#2563eb;color:#fff;padding:12px 18px;border-radius:10px;font-weight:700;}
            </style></head><body><div class="wrap"><div class="card"><h1>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';

        if (!empty($links)) {
            echo '<div class="links">';
            foreach ($links as $label => $url) {
                echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8')
                    . '</a>';
            }
            echo '</div>';
        }

        echo '</div></div></body></html>';
    }
}
