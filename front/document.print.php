<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tutanak hızlı yazdırma sayfası
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

$pdfUrl = Plugin::getWebDir('zimmet') . '/front/document.pdf.php?id=' . $id;

Html::header(
    'Tutanak yazdır',
    $_SERVER['PHP_SELF'],
    'management',
    'PluginZimmetMenu',
    'document'
);

echo "<div class='card'><div class='card-body'>";
echo "<div class='d-flex justify-content-between align-items-center mb-2'>";
echo "<h3 style='margin:0'>Tutanak yazdır</h3>";
echo "<a class='btn btn-outline-primary' target='_blank' href='" . htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') . "'>"
    . "<i class='ti ti-file-type-pdf'></i> PDF'i yeni sekmede aç</a>";
echo "</div>";
echo "<iframe id='zimmet-print-frame' src='" . htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') . "' "
    . "style='width:100%;height:78vh;border:1px solid #d8dee8;border-radius:6px;background:#fff'></iframe>";
echo "</div></div>";

echo Html::scriptBlock("
    $(function() {
        var frame = document.getElementById('zimmet-print-frame');
        frame.addEventListener('load', function() {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                window.open(" . json_encode($pdfUrl) . ", '_blank');
            }
        });
    });
");

Html::footer();
