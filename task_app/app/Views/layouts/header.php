<?php
$pageTitle = $pageTitle ?? 'タスク管理・進捗管理アプリ';
$appName = $appName ?? 'タスク管理・進捗管理アプリ';

if (!function_exists('layout_h')) {
    function layout_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/task_app/public/index.php');

$homeUrl = $scriptName . '?controller=home&action=index';
$taskListUrl = $scriptName . '?controller=task&action=index';
$taskCreateUrl = $scriptName . '?controller=task&action=create';

$currentController = trim((string)($_GET['controller'] ?? 'home'));
$currentAction = trim((string)($_GET['action'] ?? 'index'));

$isHomeActive = ($currentController === 'home');
$isTaskListActive = ($currentController === 'task' && $currentAction === 'index');
$isTaskCreateActive = ($currentController === 'task' && $currentAction === 'create');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo layout_h($pageTitle); ?> | <?php echo layout_h($appName); ?></title>
    <style>
        :root {
            --layout-bg: #f5f7fb;
            --layout-surface: #ffffff;
            --layout-text: #1e293b;
            --layout-subtext: #64748b;
            --layout-primary: #2563eb;
            --layout-primary-soft: #dbeafe;
            --layout-border: #e2e8f0;
            --layout-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            --layout-max-width: 1280px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--layout-bg);
            color: var(--layout-text);
            font-family: "Yu Gothic", "Hiragino Sans", "Meiryo", sans-serif;
            line-height: 1.6;
        }

        a {
            color: inherit;
        }

        .layout-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .layout-header {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(12px);
            background: rgba(245, 247, 251, 0.88);
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        }

        .layout-header-inner {
            width: min(var(--layout-max-width), 96%);
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 16px 0;
        }

        .layout-brand {
            min-width: 0;
        }

        .layout-brand-link {
            text-decoration: none;
            display: inline-flex;
            flex-direction: column;
            gap: 4px;
        }

        .layout-brand-badge {
            display: inline-block;
            width: fit-content;
            padding: 5px 10px;
            border-radius: 999px;
            background: var(--layout-primary-soft);
            color: var(--layout-primary);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .layout-brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .layout-nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .layout-nav-link {
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.92rem;
            color: #334155;
            background: transparent;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .layout-nav-link:hover {
            transform: translateY(-1px);
            background: #e2e8f0;
            color: #0f172a;
        }

        .layout-nav-link-active {
            background: var(--layout-primary);
            color: #ffffff;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
        }

        .layout-main {
            flex: 1;
        }

        .layout-main-inner {
            width: 100%;
        }

        @media (max-width: 860px) {
            .layout-header-inner {
                flex-direction: column;
                align-items: stretch;
            }

            .layout-brand-title {
                white-space: normal;
            }

            .layout-nav {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="layout-shell">
    <header class="layout-header">
        <div class="layout-header-inner">
            <div class="layout-brand">
                <a href="<?php echo layout_h($homeUrl); ?>" class="layout-brand-link">
                    <span class="layout-brand-badge">MVC TASK APP</span>
                    <span class="layout-brand-title"><?php echo layout_h($appName); ?></span>
                </a>
            </div>

            <nav class="layout-nav">
                <a href="<?php echo layout_h($homeUrl); ?>" class="layout-nav-link <?php echo $isHomeActive ? 'layout-nav-link-active' : ''; ?>">ホーム</a>
                <a href="<?php echo layout_h($taskListUrl); ?>" class="layout-nav-link <?php echo $isTaskListActive ? 'layout-nav-link-active' : ''; ?>">タスク一覧</a>
                <a href="<?php echo layout_h($taskCreateUrl); ?>" class="layout-nav-link <?php echo $isTaskCreateActive ? 'layout-nav-link-active' : ''; ?>">タスク追加</a>
            </nav>
        </div>
    </header>

    <main class="layout-main">
        <div class="layout-main-inner">
