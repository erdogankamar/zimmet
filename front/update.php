<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — (Eski) Güncelleme sayfası → Ayarlar'a yönlendirir
 *
 * Güncelleme Merkezi artık Ayarlar (config.php) sayfasının altına gömülüdür.
 * Bu dosya, eski yer imleri ve önceki sürümlerden gelen form gönderimleri
 * (do_update / do_github_update) için geriye dönük uyumluluk sağlar ve her
 * durumda görünür kalır (asla boş/beyaz sayfa göstermez).
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

// Güncelleme sonrası eklenti sınıfları otomatik yüklenmemiş olabilir;
// gerekli sınıfı YALNIZCA tanımlı değilse yükle (yeniden tanımlamayı önler)
if (!defined('PLUGIN_ZIMMET_VERSION') && is_file(__DIR__ . '/../setup.php')) {
    include_once __DIR__ . '/../setup.php';
}
if (!class_exists('PluginZimmetUpdate') && is_file(__DIR__ . '/../inc/update.class.php')) {
    require_once __DIR__ . '/../inc/update.class.php';
}

$configUrl = '/plugins/zimmet/front/config.php';
if (class_exists('Plugin')) {
    $configUrl = Plugin::getWebDir('zimmet') . '/front/config.php';
}

// Ölümcül hata olsa bile boş sayfa yerine görünür bir yönlendirme göster
register_shutdown_function(static function () use ($configUrl) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8', true, 200);
        }
        echo "<!doctype html><html lang='tr'><head><meta charset='utf-8'>"
            . "<meta http-equiv='refresh' content='1;url=" . htmlspecialchars($configUrl, ENT_QUOTES, 'UTF-8') . "'>"
            . "<title>Güncelleme Merkezi</title></head>"
            . "<body style='font-family:Arial,sans-serif;color:#1f2937;padding:32px'>"
            . "<p>Güncelleme Merkezi artık <b>Ayarlar</b> sayfasının altındadır.</p>"
            . "<p><a href='" . htmlspecialchars($configUrl, ENT_QUOTES, 'UTF-8') . "'>Ayarlar → Güncelleme Merkezi'ne git</a></p>"
            . "<script>setTimeout(function(){location.replace(" . json_encode($configUrl) . ");},800);</script>"
            . "</body></html>";
    }
});

if (class_exists('Session')) {
    Session::checkRight('plugin_zimmet_config', UPDATE);
}

// Eski sürümlerden gelen POST eylemlerini işle, sonra Ayarlar'a dön
if (class_exists('PluginZimmetUpdate')) {
    PluginZimmetUpdate::handleActions($configUrl);
}

// GET: Ayarlar sayfasına yönlendir (sürüm kontrolü parametresini koru)
$target = $configUrl . (isset($_GET['ghcheck']) ? '?ghcheck=1' : '');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (!headers_sent()) {
    // 302 (kalıcı değil) — tarayıcı önbelleğine takılmaması için
    header('Location: ' . $target, true, 302);
}

// Görünür gövde: otomatik yönlendirme + tıklanabilir bağlantı (asla boş kalmaz)
echo "<!doctype html><html lang='tr'><head><meta charset='utf-8'>"
    . "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . "'>"
    . "<title>Yönlendiriliyor…</title></head>"
    . "<body style='font-family:Arial,sans-serif;color:#1f2937;padding:32px'>"
    . "<p>Güncelleme Merkezi <b>Ayarlar</b> sayfasına taşındı. Yönlendiriliyorsunuz…</p>"
    . "<p><a href='" . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . "'>Otomatik yönlenmezseniz buraya tıklayın</a></p>"
    . "<script>window.location.replace(" . json_encode($target) . ");</script>"
    . "</body></html>";
