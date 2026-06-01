<?php

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getPDO();
    echo 'MySQL接続成功';
} catch (PDOException $e) {
    echo '接続失敗: ' . $e->getMessage();
}
