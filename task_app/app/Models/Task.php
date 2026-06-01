<?php

require_once dirname(__DIR__) . '/Core/Database.php';

class Task
{
    public function getSummaryCounts(): array
    {
        $pdo = Database::getConnection();

        $summary = [
            'total' => 0,
            'not_started' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];

        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) AS not_started,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
            FROM tasks
        ";

        $stmt = $pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $summary['total'] = (int)($row['total'] ?? 0);
            $summary['not_started'] = (int)($row['not_started'] ?? 0);
            $summary['in_progress'] = (int)($row['in_progress'] ?? 0);
            $summary['completed'] = (int)($row['completed'] ?? 0);
        }

        return $summary;
    }

    public function getAll(string $status = 'all'): array
    {
        $pdo = Database::getConnection();

        $allowedStatuses = ['all', 'not_started', 'in_progress', 'completed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $sql = "SELECT * FROM tasks";
        $params = [];

        if ($status !== 'all') {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tasks)) {
            return [];
        }

        $taskIds = array_map(static fn($task) => (int)$task['id'], $tasks);

        $stepMemoMapByTask = $this->fetchStepMemoMapByTaskIds($taskIds);
        $assignmentMapByTask = $this->fetchAssignmentMapByTaskIds($taskIds);

        foreach ($tasks as &$task) {
            $taskId = (int)$task['id'];
            $progressMax = (int)($task['progress_max'] ?? 0);
            $currentProgress = (int)($task['current_progress'] ?? 0);

            $task['step_memos'] = $stepMemoMapByTask[$taskId] ?? [];
            $task['step_assignments'] = $assignmentMapByTask[$taskId] ?? [];

            $task['completion_rate'] = ($progressMax > 0)
                ? (int)floor(($currentProgress / $progressMax) * 100)
                : 0;

            $task['remaining_steps'] = max($progressMax - $currentProgress, 0);

            $nextStepNo = null;
            if ($currentProgress < $progressMax) {
                $nextStepNo = $currentProgress + 1;
            }

            $task['next_step_no'] = $nextStepNo;
            $task['next_step_memo'] = $nextStepNo !== null
                ? ($task['step_memos'][$nextStepNo] ?? ('工程 ' . $nextStepNo))
                : '';
            $task['next_step_assignment'] = $nextStepNo !== null
                ? ($task['step_assignments'][$nextStepNo]['assignment_date'] ?? '')
                : '';
        }
        unset($task);

        return $tasks;
    }

    public function findById(int $taskId): ?array
    {
        $task = $this->findRawById($taskId);

        if (!$task) {
            return null;
        }

        $progressMax = (int)($task['progress_max'] ?? 0);
        $currentProgress = (int)($task['current_progress'] ?? 0);

        $task['step_memos'] = $this->getStepMemoMapByTaskId($taskId);
        $task['step_assignments'] = $this->getAssignmentMapByTaskId($taskId);
        $task['completion_rate'] = ($progressMax > 0)
            ? (int)floor(($currentProgress / $progressMax) * 100)
            : 0;
        $task['remaining_steps'] = max($progressMax - $currentProgress, 0);

        $orderedSteps = [];
        for ($i = 1; $i <= $progressMax; $i++) {
            $orderedSteps[$i] = [
                'step_no' => $i,
                'memo' => $task['step_memos'][$i] ?? ('工程 ' . $i),
                'assignment_date' => $task['step_assignments'][$i]['assignment_date'] ?? '',
                'step_memo' => $task['step_assignments'][$i]['step_memo'] ?? ($task['step_memos'][$i] ?? ('工程 ' . $i)),
                'is_completed' => ($i <= $currentProgress),
            ];
        }
        $task['ordered_steps'] = $orderedSteps;

        return $task;
    }

    public function create(array $data): int
    {
        $pdo = Database::getConnection();

        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $progressMax = (int)($data['progress_max'] ?? 0);
        $startDatetime = trim((string)($data['start_datetime'] ?? ''));
        $dueDatetime = trim((string)($data['due_datetime'] ?? ''));
        $stepMemos = is_array($data['step_memos'] ?? null) ? $data['step_memos'] : [];
        $stepDates = is_array($data['step_dates'] ?? null) ? $data['step_dates'] : [];

        if ($title === '') {
            throw new InvalidArgumentException('タスク名を入力してください。');
        }

        if (mb_strlen($title) > 100) {
            throw new InvalidArgumentException('タスク名は100文字以内で入力してください。');
        }

        if ($description !== '' && mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('詳細は1000文字以内で入力してください。');
        }

        if ($progressMax < 1 || $progressMax > 10) {
            throw new InvalidArgumentException('工程数は1〜10の範囲で指定してください。');
        }

        $startDatetimeForDb = $this->normalizeDateTimeForDb($startDatetime, '開始日時');
        $dueDatetimeForDb = $this->normalizeDateTimeForDb($dueDatetime, '終了予定日時');

        $startTimestamp = strtotime($startDatetimeForDb);
        $dueTimestamp = strtotime($dueDatetimeForDb);

        if ($startTimestamp === false || $dueTimestamp === false) {
            throw new InvalidArgumentException('開始日時または終了予定日時の形式が正しくありません。');
        }

        if ($startTimestamp > $dueTimestamp) {
            throw new InvalidArgumentException('終了予定日時は開始日時以降にしてください。');
        }

        try {
            $pdo->beginTransaction();

            $userId = $this->getOrCreateDevelopmentUserId($pdo);

            $taskSql = "
                INSERT INTO tasks (
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
                )
            ";
            $taskStmt = $pdo->prepare($taskSql);
            $taskStmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':description' => $description !== '' ? $description : null,
                ':progress_max' => $progressMax,
                ':current_progress' => 0,
                ':status' => 'not_started',
                ':start_datetime' => $startDatetimeForDb,
                ':due_datetime' => $dueDatetimeForDb,
            ]);

            $taskId = (int)$pdo->lastInsertId();

            $memoSql = "
                INSERT INTO task_step_memos (
                    task_id,
                    step_no,
                    memo
                ) VALUES (
                    :task_id,
                    :step_no,
                    :memo
                )
            ";
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
                $memo = trim((string)($stepMemos[$i] ?? ''));
                if ($memo === '') {
                    $memo = '工程 ' . $i;
                }
                if (mb_strlen($memo) > 30) {
                    $memo = mb_substr($memo, 0, 30);
                }

                $memoStmt->execute([
                    ':task_id' => $taskId,
                    ':step_no' => $i,
                    ':memo' => $memo,
                ]);

                $stepDateValue = trim((string)($stepDates[$i] ?? ''));
                if ($stepDateValue === '') {
                    continue;
                }

                $assignmentDatetime = $this->normalizeDateTimeForDb($stepDateValue, '工程 ' . $i . ' の日時');
                $assignmentTimestamp = strtotime($assignmentDatetime);

                if ($assignmentTimestamp === false) {
                    throw new InvalidArgumentException('工程 ' . $i . ' の日時形式が不正です。');
                }

                if ($assignmentTimestamp < $startTimestamp || $assignmentTimestamp > $dueTimestamp) {
                    throw new InvalidArgumentException('工程 ' . $i . ' の日時は開始日時から終了予定日時までの間で設定してください。');
                }

                $assignmentStmt->execute([
                    ':task_id' => $taskId,
                    ':step_no' => $i,
                    ':assignment_date' => $assignmentDatetime,
                    ':step_memo' => $memo,
                ]);
            }

            $pdo->commit();
            return $taskId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateStepSchedules(int $taskId, array $stepDates): void
    {
        $pdo = Database::getConnection();

        $task = $this->findRawById($taskId);
        if (!$task) {
            throw new InvalidArgumentException('対象のタスクが見つかりません。');
        }

        $progressMax = (int)($task['progress_max'] ?? 0);
        $startDatetime = (string)($task['start_datetime'] ?? '');
        $dueDatetime = (string)($task['due_datetime'] ?? '');
        $startTimestamp = strtotime($startDatetime);
        $dueTimestamp = strtotime($dueDatetime);

        if ($startTimestamp === false || $dueTimestamp === false) {
            throw new RuntimeException('タスクの開始日時または終了予定日時が不正です。');
        }

        $stepMemoMap = $this->getStepMemoMapByTaskId($taskId);

        try {
            $pdo->beginTransaction();

            $deleteStepStmt = $pdo->prepare("
                DELETE FROM task_calendar_assignments
                WHERE task_id = :task_id AND step_no = :step_no
            ");

            $insertStepStmt = $pdo->prepare("
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
            ");

            for ($i = 1; $i <= $progressMax; $i++) {
                $dateValue = trim((string)($stepDates[$i] ?? ''));

                $deleteStepStmt->execute([
                    ':task_id' => $taskId,
                    ':step_no' => $i,
                ]);

                if ($dateValue === '') {
                    continue;
                }

                $assignmentDatetime = $this->normalizeDateTimeForDb($dateValue, '工程 ' . $i . ' の日時');
                $assignmentTimestamp = strtotime($assignmentDatetime);

                if ($assignmentTimestamp === false) {
                    throw new InvalidArgumentException('工程 ' . $i . ' の日時形式が不正です。');
                }

                if ($assignmentTimestamp < $startTimestamp || $assignmentTimestamp > $dueTimestamp) {
                    throw new InvalidArgumentException('工程 ' . $i . ' の日時は開始日時から終了予定日時までの間で設定してください。');
                }

                $memo = trim((string)($stepMemoMap[$i] ?? ''));
                if ($memo === '') {
                    $memo = '工程 ' . $i;
                }

                $insertStepStmt->execute([
                    ':task_id' => $taskId,
                    ':step_no' => $i,
                    ':assignment_date' => $assignmentDatetime,
                    ':step_memo' => $memo,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateProgressByAction(int $taskId, string $actionType): void
    {
        $pdo = Database::getConnection();

        $allowedActions = ['increase', 'decrease', 'complete', 'reset'];
        if (!in_array($actionType, $allowedActions, true)) {
            throw new InvalidArgumentException('更新方法が不正です。');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, current_progress, progress_max
                FROM tasks
                WHERE id = :id
                FOR UPDATE
            ");
            $stmt->execute([':id' => $taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                throw new InvalidArgumentException('対象のタスクが見つかりません。');
            }

            $current = (int)($task['current_progress'] ?? 0);
            $max = (int)($task['progress_max'] ?? 0);

            if ($max <= 0) {
                throw new RuntimeException('タスクの工程数が不正です。');
            }

            $newProgress = $current;

            switch ($actionType) {
                case 'increase':
                    $newProgress = min($current + 1, $max);
                    break;
                case 'decrease':
                    $newProgress = max($current - 1, 0);
                    break;
                case 'complete':
                    $newProgress = $max;
                    break;
                case 'reset':
                    $newProgress = 0;
                    break;
            }

            $status = $this->buildStatusFromProgress($newProgress, $max);

            $updateStmt = $pdo->prepare("
                UPDATE tasks
                SET current_progress = :current_progress,
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':current_progress' => $newProgress,
                ':status' => $status,
                ':id' => $taskId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $taskId): void
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                DELETE FROM task_calendar_assignments
                WHERE task_id = :task_id
            ")->execute([
                ':task_id' => $taskId,
            ]);

            $pdo->prepare("
                DELETE FROM task_step_memos
                WHERE task_id = :task_id
            ")->execute([
                ':task_id' => $taskId,
            ]);

            $stmt = $pdo->prepare("
                DELETE FROM tasks
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $taskId,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('対象のタスクが見つかりません。');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function findRawById(int $taskId): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT *
            FROM tasks
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $taskId,
        ]);

        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        return $task ?: null;
    }

    private function fetchStepMemoMapByTaskIds(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $pdo = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

        $sql = "
            SELECT task_id, step_no, memo
            FROM task_step_memos
            WHERE task_id IN ($placeholders)
            ORDER BY task_id ASC, step_no ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($taskIds);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $taskId = (int)$row['task_id'];
            $stepNo = (int)$row['step_no'];
            $map[$taskId][$stepNo] = $row['memo'];
        }

        return $map;
    }

    private function fetchAssignmentMapByTaskIds(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $pdo = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

        $sql = "
            SELECT task_id, step_no, assignment_date, step_memo
            FROM task_calendar_assignments
            WHERE task_id IN ($placeholders)
            ORDER BY task_id ASC, step_no ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($taskIds);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $taskId = (int)$row['task_id'];
            $stepNo = (int)$row['step_no'];
            $map[$taskId][$stepNo] = [
                'assignment_date' => $row['assignment_date'],
                'step_memo' => $row['step_memo'],
            ];
        }

        return $map;
    }

    private function getStepMemoMapByTaskId(int $taskId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT step_no, memo
            FROM task_step_memos
            WHERE task_id = :task_id
            ORDER BY step_no ASC
        ");
        $stmt->execute([
            ':task_id' => $taskId,
        ]);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$row['step_no']] = $row['memo'];
        }

        return $map;
    }

    private function getAssignmentMapByTaskId(int $taskId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT step_no, assignment_date, step_memo
            FROM task_calendar_assignments
            WHERE task_id = :task_id
            ORDER BY step_no ASC
        ");
        $stmt->execute([
            ':task_id' => $taskId,
        ]);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$row['step_no']] = [
                'assignment_date' => $row['assignment_date'],
                'step_memo' => $row['step_memo'],
            ];
        }

        return $map;
    }

    private function normalizeDateTimeForDb(?string $value, string $label = '日時'): string
    {
        $value = trim((string)$value);

        if ($value === '') {
            throw new InvalidArgumentException($label . 'を入力してください。');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException($label . 'の形式が正しくありません。');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function buildStatusFromProgress(int $currentProgress, int $progressMax): string
    {
        if ($currentProgress <= 0) {
            return 'not_started';
        }

        if ($currentProgress >= $progressMax) {
            return 'completed';
        }

        return 'in_progress';
    }

    private function getOrCreateDevelopmentUserId(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && isset($user['id'])) {
            return (int)$user['id'];
        }

        $insertSql = "
            INSERT INTO users (
                name,
                email,
                password_hash
            ) VALUES (
                :name,
                :email,
                :password_hash
            )
        ";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':name' => '開発用ユーザー',
            ':email' => 'demo@example.com',
            ':password_hash' => password_hash('demo1234', PASSWORD_DEFAULT),
        ]);

        return (int)$pdo->lastInsertId();
    }
}
