<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tutanak oluştur / düzenle / sil
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_document', READ);

$doc = new PluginZimmetDocument();

if (isset($_POST['add'])) {
    $doc->check(-1, CREATE, $_POST);
    $newID = $doc->add($_POST);
    Html::redirect(
        Plugin::getWebDir('zimmet') . '/front/document.form.php?id=' . $newID
    );
} elseif (isset($_POST['update'])) {
    $doc->check($_POST['id'], UPDATE);
    $doc->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $doc->check($_POST['id'], PURGE);
    $doc->delete($_POST, true);
    $doc->redirectToList();
} else {
    // Görüntüleme / ekleme formu
    $id        = (int) ($_GET['id'] ?? 0);
    $doc_type  = $_GET['doc_type'] ?? 'zimmet';

    Html::header(
        PluginZimmetDocument::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'management',
        'PluginZimmetMenu',
        'document'
    );

    PluginZimmetMenu::showNavHeader($id > 0 ? 'document' : 'add');

    $doc->display([
        'id'       => $id,
        'doc_type' => $doc_type,
    ]);

    PluginZimmetMenu::closeApp();
    Html::footer();
}
