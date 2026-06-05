<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Yapılandırma sayfası
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_config', UPDATE);

// Kaydetme
if (isset($_POST['update'])) {
    $types = $_POST['asset_types'] ?? [];
    PluginZimmetConfig::setValue('asset_types', implode(',', $types));

    if (isset($_POST['pdf_font'])) {
        PluginZimmetConfig::setValue('pdf_font', $_POST['pdf_font']);
    }

    Session::addMessageAfterRedirect('Zimmet yapılandırması başarıyla kaydedildi.');
    Html::back();
}

Html::header(
    PluginZimmetConfig::getTypeName(),
    $_SERVER['PHP_SELF'],
    'config',
    'pluginzimmetconfig'
);

PluginZimmetMenu::showNavHeader('config');

$config = new PluginZimmetConfig();
$config->showConfigForm();

PluginZimmetMenu::closeApp();
Html::footer();
