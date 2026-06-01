<?php
if (!function_exists('layout_footer_h')) {
    function layout_footer_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$appName = $appName ?? 'タスク管理・進捗管理アプリ';
$currentYear = date('Y');
?>
        </div>
    </main>

    <footer class="layout-footer">
        <div class="layout-footer-inner">
            <div class="layout-footer-top">
                <div class="layout-footer-brand">
                    <div class="layout-footer-title"><?php echo layout_footer_h($appName); ?></div>
                    <div class="layout-footer-text">MVC構成で整理されたタスク管理アプリケーション</div>
                </div>
                <div class="layout-footer-note">
                    工程日時・進捗・カレンダー表示に対応
                </div>
            </div>

            <div class="layout-footer-bottom">
                <span><?php echo layout_footer_h((string)$currentYear); ?> <?php echo layout_footer_h($appName); ?></span>
                <span>Built with PHP / MVC</span>
            </div>
        </div>
    </footer>
</div>

<style>
    .layout-footer {
        margin-top: 28px;
        border-top: 1px solid rgba(226, 232, 240, 0.9);
        background: linear-gradient(180deg, rgba(255,255,255,0.88) 0%, rgba(248,250,252,1) 100%);
    }

    .layout-footer-inner {
        width: min(1280px, 96%);
        margin: 0 auto;
        padding: 22px 0 30px;
    }

    .layout-footer-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .layout-footer-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .layout-footer-text,
    .layout-footer-note,
    .layout-footer-bottom {
        color: #64748b;
        font-size: 0.9rem;
        line-height: 1.8;
    }

    .layout-footer-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding-top: 14px;
        border-top: 1px solid #e2e8f0;
    }

    @media (max-width: 700px) {
        .layout-footer-bottom {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
</body>
</html>
