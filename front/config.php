<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Yapılandırma + Güncelleme Merkezi
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

// -------------------------------------------------------------------------
// Güncelleme sonrası boş (beyaz) sayfa koruması
// -------------------------------------------------------------------------
// 1) Ölümcül hatada boş sayfa yerine okunur mesaj göster. Sınıf yüklemeden
//    ÖNCE kaydedilir ki yükleme sırasında oluşacak hata da yakalansın.
register_shutdown_function(static function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8', true, 500);
        }
        echo "<div style='font-family:Arial,sans-serif;max-width:760px;margin:40px auto;"
            . "padding:20px 24px;border:1px solid #e3b7b7;border-radius:10px;background:#fff6f6;color:#7a1f1f'>"
            . "<h2 style='margin:0 0 8px'>Ayarlar sayfası yüklenemedi</h2>"
            . "<p>Birkaç saniye bekleyip sayfayı yenileyin; sorun sürerse aşağıdaki teknik detayı paylaşın.</p>"
            . "<pre style='white-space:pre-wrap;background:#fff;border:1px solid #eee;border-radius:6px;"
            . "padding:10px;font-size:12px;color:#444'>"
            . htmlspecialchars($e['message'] . "\n" . ($e['file'] ?? '') . ':' . ($e['line'] ?? ''), ENT_QUOTES, 'UTF-8')
            . "</pre></div>";
    }
});

// 2) Güncelleme sonrası anlık geçişte GLPI eklenti sınıflarını otomatik
//    yüklemeyebilir. Gereken sınıfları YALNIZCA tanımlı değillerse yükle
//    (kör glob+require yeniden tanımlama hatasına yol açabiliyordu).
if (!defined('PLUGIN_ZIMMET_VERSION') && is_file(__DIR__ . '/../setup.php')) {
    include_once __DIR__ . '/../setup.php';
}
foreach ([
    'PluginZimmetConfig' => 'config.class.php',
    'PluginZimmetMenu'   => 'menu.class.php',
    'PluginZimmetUpdate' => 'update.class.php',
] as $zClass => $zFile) {
    if (!class_exists($zClass)) {
        $zPath = __DIR__ . '/../inc/' . $zFile;
        if (is_file($zPath)) {
            require_once $zPath;
        }
    }
}

Session::checkRight('plugin_zimmet_config', UPDATE);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$pageUrl = Plugin::getWebDir('zimmet') . '/front/config.php';

// --- Ayarları kaydet ---
if (isset($_POST['update'])) {
    $types = $_POST['asset_types'] ?? [];
    PluginZimmetConfig::setValue('asset_types', implode(',', $types));

    if (isset($_POST['pdf_font'])) {
        PluginZimmetConfig::setValue('pdf_font', $_POST['pdf_font']);
    }

    Session::addMessageAfterRedirect('Zimmet yapılandırması başarıyla kaydedildi.');
    Html::back();
}

// --- Güncelleme eylemleri (ZIP / GitHub) — işlem varsa yönlendirir ve durur ---
PluginZimmetUpdate::handleActions($pageUrl);

Html::header(
    PluginZimmetConfig::getTypeName(),
    $_SERVER['PHP_SELF'],
    'config',
    'pluginzimmetconfig'
);

PluginZimmetMenu::showNavHeader('config');

$config = new PluginZimmetConfig();
$config->showConfigForm();

// Ayarların altında: Güncelleme Merkezi
PluginZimmetUpdate::renderCenter($pageUrl);

PluginZimmetMenu::closeApp();
Html::footer();
