<?php

require_once dirname(__DIR__) . '/Models/Task.php';

class HomeController
{
    private Task $taskModel;

    public function __construct()
    {
        $this->taskModel = new Task();
    }

    public function index(): void
    {
        try {
            $today = date('Y-m-d');

            $viewYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $viewMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
            $selectedDate = trim((string)($_GET['date'] ?? $today));

            if ($viewYear < 2000 || $viewYear > 2100) {
                $viewYear = (int)date('Y');
            }

            if ($viewMonth < 1 || $viewMonth > 12) {
                $viewMonth = (int)date('n');
            }

            if ($selectedDate === '' || strtotime($selectedDate) === false) {
                $selectedDate = $today;
            } else {
                $selectedDate = date('Y-m-d', strtotime($selectedDate));
            }

            $monthStart = sprintf('%04d-%02d-01', $viewYear, $viewMonth);
            $monthEnd = date('Y-m-t', strtotime($monthStart));

            $summary = $this->taskModel->getSummaryCounts();
            $calendarTasksByDate = $this->getCalendarTasksByDate($monthStart, $monthEnd);
            $todayTasks = $this->getTasksByDate($today);
            $selectedDateTasks = ($selectedDate === $today)
                ? $todayTasks
                : $this->getTasksByDate($selectedDate);

            if (!$this->viewExists('home/index')) {
                $this->renderSimplePage(
                    'ホームViewが見つかりません',
                    'app/Views/home/index.php を確認してください。',
                    [
                        'タスク一覧へ' => $this->url('task', 'index'),
                        'タスク追加へ' => $this->url('task', 'create'),
                    ]
                );
                return;
            }

            View::render('home/index', [
                'pageTitle' => 'ホーム',
                'appName' => 'タスク管理・進捗管理アプリ',
                'message' => '工程日時を登録したタスクをカレンダーで確認できます。カレンダーには「タスク名1行・終了予定日時1行・メモ1行」で表示します。',
                'links' => [
                    'home' => $this->url('home', 'index'),
                    'task_list' => $this->url('task', 'index'),
                    'task_create' => $this->url('task', 'create'),
                    'list' => $this->url('task', 'index'),
                    'create' => $this->url('task', 'create'),
                ],
                'summary' => $summary,
                'calendarTasksByDate' => $calendarTasksByDate,
                'todayTasks' => $todayTasks,
                'selectedDateTasks' => $selectedDateTasks,
                'viewYear' => $viewYear,
                'viewMonth' => $viewMonth,
                'selectedDate' => $selectedDate,
                'successMessage' => $this->resolveSuccessMessage(),
                'errorMessage' => trim((string)($_GET['error'] ?? '')),
            ]);
        } catch (Throwable $e) {
            $this->renderSimplePage(
                'ホーム画面の表示に失敗しました',
                $e->getMessage(),
                [
                    'タスク一覧へ' => $this->url('task', 'index'),
                    'タスク追加へ' => $this->url('task', 'create'),
                ]
            );
        }
    }

    private function getCalendarTasksByDate(string $monthStart, string $monthEnd): array
    {
        $pdo = Database::getConnection();

        $sql = "
            SELECT
                a.assignment_date,
                a.step_no,
                a.step_memo,
                t.id,
                t.title,
                t.due_datetime,
                t.current_progress,
                t.progress_max,
                t.status
            FROM task_calendar_assignments a
            INNER JOIN tasks t
                ON a.task_id = t.id
            WHERE a.assignment_date BETWEEN :month_start AND :month_end
              AND a.step_no > t.current_progress
            ORDER BY a.assignment_date ASC, a.step_no ASC, t.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':month_start' => $monthStart . ' 00:00:00',
            ':month_end' => $monthEnd . ' 23:59:59',
        ]);

        $grouped = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $assignmentDate = (string)($row['assignment_date'] ?? '');
            $dateKey = $assignmentDate !== '' ? date('Y-m-d', strtotime($assignmentDate)) : '';

            if ($dateKey === '') {
                continue;
            }

            $grouped[$dateKey][] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'due_datetime' => (string)($row['due_datetime'] ?? ''),
                'status' => (string)($row['status'] ?? 'not_started'),
                'current_progress' => (int)($row['current_progress'] ?? 0),
                'progress_max' => (int)($row['progress_max'] ?? 0),
                'step_no' => (int)($row['step_no'] ?? 0),
                'step_memo' => (string)($row['step_memo'] ?? ''),
                'assignment_date' => $assignmentDate,
            ];
        }

        return $grouped;
    }

    private function getTasksByDate(string $targetDate): array
    {
        $pdo = Database::getConnection();

        $sql = "
            SELECT
                a.assignment_date,
                a.step_no,
                a.step_memo,
                t.id,
                t.title,
                t.due_datetime,
                t.current_progress,
                t.progress_max,
                t.status
            FROM task_calendar_assignments a
            INNER JOIN tasks t
                ON a.task_id = t.id
            WHERE DATE(a.assignment_date) = :target_date
              AND a.step_no > t.current_progress
            ORDER BY a.assignment_date ASC, a.step_no ASC, t.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':target_date' => $targetDate,
        ]);

        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'due_datetime' => (string)($row['due_datetime'] ?? ''),
                'status' => (string)($row['status'] ?? 'not_started'),
                'current_progress' => (int)($row['current_progress'] ?? 0),
                'progress_max' => (int)($row['progress_max'] ?? 0),
                'step_no' => (int)($row['step_no'] ?? 0),
                'step_memo' => (string)($row['step_memo'] ?? ''),
                'assignment_date' => (string)($row['assignment_date'] ?? ''),
            ];
        }

        return $tasks;
    }

    private function resolveSuccessMessage(): string
    {
        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            return '工程日時を保存しました。';
        }
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
