<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');

require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/View.php';
require_once dirname(__DIR__) . '/app/Core/Router.php';

$helpersPath = dirname(__DIR__) . '/app/Helpers/functions.php';
if (file_exists($helpersPath)) {
    require_once $helpersPath;
}

try {
    $router = new Router();
    $router->dispatch();
} catch (Throwable $e) {
    http_response_code(500);

    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>アプリケーションエラー</title><style>
        body{margin:0;font-family:"Yu Gothic","Meiryo",sans-serif;background:#f5f7fb;color:#1e293b;}
        .wrap{max-width:1100px;margin:40px auto;padding:0 20px;}
        .card{background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.08);}
        h1{margin-top:0;color:#dc2626;}
        pre{background:#0f172a;color:#e2e8f0;padding:18px;border-radius:12px;overflow:auto;line-height:1.6;}
        .meta{margin:16px 0;color:#475569;line-height:1.8;}
        a{display:inline-block;margin-top:16px;text-decoration:none;background:#2563eb;color:#fff;padding:12px 18px;border-radius:10px;font-weight:700;}
    </style></head><body><div class="wrap"><div class="card"><h1>アプリケーションエラー</h1><p class="meta">処理中にエラーが発生しました。内容を確認して修正してください。</p><pre>'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . "\n\n"
        . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8')
        . ' : '
        . htmlspecialchars((string)$e->getLine(), ENT_QUOTES, 'UTF-8')
        . '</pre><a href="index.php?controller=home&action=index">ホームへ戻る</a></div></div></body></html>';
}
