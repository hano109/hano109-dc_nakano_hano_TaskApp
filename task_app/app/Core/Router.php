<?php

class Router
{
    public function dispatch(): void
    {
        $controllerName = $this->normalizeControllerName($_GET['controller'] ?? 'home');
        $actionName = $this->normalizeActionName($_GET['action'] ?? 'index');

        $className = ucfirst($controllerName) . 'Controller';
        $controllerFile = dirname(__DIR__) . '/Controllers/' . $className . '.php';

        if (!file_exists($controllerFile)) {
            $this->renderSimpleMessage(
                'コントローラーが見つかりません',
                $className . '.php を app/Controllers/ に作成してください。'
            );
            return;
        }

        require_once $controllerFile;

        if (!class_exists($className)) {
            $this->renderSimpleMessage(
                'コントローラークラスが見つかりません',
                $className . ' クラスを定義してください。'
            );
            return;
        }

        $controller = new $className();

        if (!method_exists($controller, $actionName)) {
            $this->renderSimpleMessage(
                'アクションが見つかりません',
                $className . '::' . $actionName . '() を定義してください。'
            );
            return;
        }

        if (str_starts_with($actionName, '__')) {
            $this->renderSimpleMessage(
                '不正なアクションです',
                'そのアクションは呼び出せません。'
            );
            return;
        }

        $controller->$actionName();
    }

    private function normalizeControllerName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]/', '', $name);

        return $name !== '' ? $name : 'home';
    }

    private function normalizeActionName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]/', '', $name);

        return $name !== '' ? $name : 'index';
    }

    private function renderSimpleMessage(string $title, string $message): void
    {
        http_response_code(404);

        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</title><style>
                body{margin:0;font-family:"Yu Gothic","Meiryo",sans-serif;background:#f5f7fb;color:#1e293b;}
                .wrap{max-width:960px;margin:60px auto;padding:0 20px;}
                .card{background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.08);}
                h1{margin-top:0;color:#2563eb;}
                p{line-height:1.8;white-space:pre-wrap;}
                a{display:inline-block;margin-top:20px;text-decoration:none;background:#2563eb;color:#fff;padding:12px 18px;border-radius:10px;font-weight:700;}
            </style></head><body><div class="wrap"><div class="card"><h1>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p><a href="index.php?controller=home&action=index">ホームへ戻る</a></div></div></body></html>';
    }
}
