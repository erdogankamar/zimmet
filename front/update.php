<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — (Eski) Güncelleme sayfası → Ayarlar'a yönlendirir
 *
 * Güncelleme Merkezi artık Ayarlar (config.php) sayfasının altına gömülüdür.
 * Bu dosya, eski yer imleri ve önceki sürümlerden gelen form gönderimleri
 * (do_update / do_github_update) için geriye dönük uyumluluk sağlar.
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

// Güncelleme sonrası eklenti sınıfları otomatik yüklenmemiş olabilir
if (!defined('PLUGIN_ZIMMET_VERSION') && is_file(__DIR__ . '/../setup.php')) {
    include_once __DIR__ . '/../setup.php';
}
foreach (glob(__DIR__ . '/../inc/*.class.php') ?: [] as $zimmetClassFile) {
    require_once $zimmetClassFile;
}

Session::checkRight('plugin_zimmet_config', UPDATE);

$configUrl = Plugin::getWebDir('zimmet') . '/front/config.php';

// Eski sürümlerden gelen POST eylemlerini işle, sonra Ayarlar'a dön
PluginZimmetUpdate::handleActions($configUrl);

// GET: doğrudan Ayarlar sayfasına yönlendir (sürüm kontrolü parametresini koru)
$target = $configUrl . (isset($_GET['ghcheck']) ? '?ghcheck=1' : '');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (!headers_sent()) {
    header('Location: ' . $target, true, 301);
}
echo "<!doctype html><meta charset='utf-8'>"
    . "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . "'>"
    . "<script>window.location.replace(" . json_encode($target) . ");</script>";
