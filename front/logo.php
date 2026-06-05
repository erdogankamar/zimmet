<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Şablon logosunu güvenli şekilde sunar
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_config', UPDATE);

$id = (int) ($_GET['id'] ?? 0);

$tpl = new PluginZimmetTemplate();
if ($id <= 0 || !$tpl->getFromDB($id) || empty($tpl->fields['logo_path'])) {
    http_response_code(404);
    exit;
}

// Yol gezinmesini (path traversal) engelle
$rel = $tpl->fields['logo_path'];
if (strpos($rel, '..') !== false) {
    http_response_code(400);
    exit;
}

$path = GLPI_PLUGIN_DOC_DIR . '/zimmet/' . $rel;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
