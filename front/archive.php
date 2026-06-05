<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tutanağı arşivle (imzalı kopya kaydı)
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

$zimmetArchiveObLevel = ob_get_level();
ob_start();

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_document', UPDATE);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$returnToList = ($_GET['return'] ?? $_POST['return'] ?? '') === 'list';

$doc = new PluginZimmetDocument();
if ($id <= 0 || !$doc->getFromDB($id)) {
    Html::displayErrorAndDie(__('Document not found', 'zimmet'));
}
if (!$doc->canUpdateItem()) {
    Html::displayRightError();
}

$docID = PluginZimmetArchive::archive($id, true);

if ($docID) {
    Session::addMessageAfterRedirect(
        'Tutanak arşive alındı. PDF bütünlük izi başarıyla kaydedildi.'
    );
} else {
    Session::addMessageAfterRedirect(
        'Tutanak arşivlenemedi. Lütfen PDF üretimi ve dosya izinlerini kontrol edin.',
        false,
        ERROR
    );
}

$target = Plugin::getWebDir('zimmet') . ($returnToList
    ? '/front/document.php'
    : '/front/document.form.php?id=' . $id);

while (ob_get_level() > $zimmetArchiveObLevel) {
    ob_end_clean();
}

if (!headers_sent()) {
    header('Location: ' . $target);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

echo "<!doctype html><html><head><meta charset='utf-8'>"
    . "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . "'>"
    . "<title>Arşivleme tamamlandı</title></head>"
    . "<body style='font-family:Arial,sans-serif;color:#1f2937;padding:24px'>"
    . "<p>İşlem tamamlandı. Zimmet listesine yönlendiriliyorsunuz.</p>"
    . "<script>window.location.replace(" . json_encode($target) . ");</script>"
    . "</body></html>";
exit;
