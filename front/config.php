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
// Güncelleme uygulandıktan hemen sonra dosya sürümü ile veritabanı sürümü
// arasındaki anlık geçiş + OPcache nedeniyle GLPI eklentiyi kısa süre
// "yüklenmedi" sayıp sınıfları otomatik yüklemeyebilir. Bu sayfa güncelleme
// merkezini de barındırdığından, sabit ve sınıfları doğrudan yükleyerek her
// durumda render edilmesini garanti ediyoruz.
if (!defined('PLUGIN_ZIMMET_VERSION') && is_file(__DIR__ . '/../setup.php')) {
    include_once __DIR__ . '/../setup.php';
}
foreach (glob(__DIR__ . '/../inc/*.class.php') ?: [] as $zimmetClassFile) {
    require_once $zimmetClassFile;
}

// Ölümcül hatada boş sayfa yerine okunur mesaj (yalnızca config yetkililerine açık)
register_shutdown_function(static function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8', true, 500);
        }
        echo "<div style='font-family:Arial,sans-serif;max-width:760px;margin:40px auto;"
            . "padding:20px 24px;border:1px solid #e3b7b7;border-radius:10px;background:#fff6f6;color:#7a1f1f'>"
            . "<h2 style='margin:0 0 8px'>Ayarlar sayfası yüklenemedi</h2>"
            . "<p>İşlem büyük olasılıkla tamamlandı ancak sayfa render edilirken bir hata oluştu. "
            . "Birkaç saniye bekleyip sayfayı yenileyin; sorun sürerse aşağıdaki teknik detayı paylaşın.</p>"
            . "<pre style='white-space:pre-wrap;background:#fff;border:1px solid #eee;border-radius:6px;"
            . "padding:10px;font-size:12px;color:#444'>"
            . htmlspecialchars($e['message'] . "\n" . ($e['file'] ?? '') . ':' . ($e['line'] ?? ''), ENT_QUOTES, 'UTF-8')
            . "</pre></div>";
    }
});

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
