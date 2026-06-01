<?php

class View
{
    public static function render(string $view, array $data = [], bool $useLayout = true): void
    {
        $viewPath = dirname(__DIR__) . '/Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo 'View が見つかりません: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            exit;
        }

        extract($data, EXTR_SKIP);

        $headerPath = dirname(__DIR__) . '/Views/layouts/header.php';
        $footerPath = dirname(__DIR__) . '/Views/layouts/footer.php';

        $hasHeader = file_exists($headerPath) && filesize($headerPath) > 0;
        $hasFooter = file_exists($footerPath) && filesize($footerPath) > 0;

        if ($useLayout && $hasHeader && $hasFooter) {
            require $headerPath;
            require $viewPath;
            require $footerPath;
            return;
        }

        $pageTitle = $data['pageTitle'] ?? 'Task App';
        $lang = $data['lang'] ?? 'ja';

        echo '<!DOCTYPE html>';
        echo '<html lang="' . htmlspecialchars((string)$lang, ENT_QUOTES, 'UTF-8') . '">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . htmlspecialchars((string)$pageTitle, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '</head>';
        echo '<body style="margin:0;background:#f5f7fb;">';

        require $viewPath;

        echo '</body>';
        echo '</html>';
    }
}
