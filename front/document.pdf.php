<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tek tutanak PDF çıktısı
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_document', READ);

$id = (int) ($_GET['id'] ?? 0);

$doc = new PluginZimmetDocument();
if ($id <= 0 || !$doc->getFromDB($id)) {
    Html::displayErrorAndDie(__('Document not found', 'zimmet'));
}
if (!$doc->canViewItem()) {
    Html::displayRightError();
}

$content = PluginZimmetDocument::generatePdf([$id]);
if ($content === false) {
    Html::displayErrorAndDie(__('PDF could not be generated', 'zimmet'));
}

$filename = 'zimmet_' . $id . '_' . date('Ymd_His') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;
