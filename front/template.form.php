<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Şablon oluştur / düzenle / sil
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_config', UPDATE);

$tpl = new PluginZimmetTemplate();

/**
 * Yüklenen logo dosyasını plugin belge dizinine taşır ve göreli yolu döndürür.
 *
 * @return string|null
 */
function zimmet_handleLogoUpload()
{
    if (
        empty($_FILES['logo_file']['name'])
        || ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
    ) {
        return null;
    }

    $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        Session::addMessageAfterRedirect('Logo dosyası desteklenmiyor. Lütfen PNG, JPG, JPEG veya GIF formatında dosya yükleyin.', false, ERROR);
        return null;
    }

    // MIME doğrulaması
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['logo_file']['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        Session::addMessageAfterRedirect('Logo dosyası doğrulanamadı. Dosya türü ile içerik formatı uyumlu olmalıdır.', false, ERROR);
        return null;
    }

    $dir = GLPI_PLUGIN_DOC_DIR . '/zimmet/logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fname = 'logo_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . '/' . $fname)) {
        Session::addMessageAfterRedirect('Logo dosyası kaydedilemedi. Lütfen eklenti dosya izinlerini kontrol edin.', false, ERROR);
        return null;
    }

    return 'logos/' . $fname;
}

if (isset($_POST['add'])) {
    $tpl->check(-1, CREATE, $_POST);
    $logo = zimmet_handleLogoUpload();
    if ($logo !== null) {
        $_POST['logo_path'] = $logo;
    }
    $newID = $tpl->add($_POST);
    Html::redirect(Plugin::getWebDir('zimmet') . '/front/template.form.php?id=' . $newID);
} elseif (isset($_POST['update'])) {
    $tpl->check($_POST['id'], UPDATE);
    $logo = zimmet_handleLogoUpload();
    if ($logo !== null) {
        $_POST['logo_path'] = $logo;
    } elseif (!empty($_POST['_delete_logo'])) {
        $_POST['logo_path'] = '';
    }
    $tpl->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $tpl->check($_POST['id'], PURGE);
    $tpl->delete($_POST, true);
    $tpl->redirectToList();
} else {
    $id = (int) ($_GET['id'] ?? 0);

    Html::header(
        PluginZimmetTemplate::getTypeName(1),
        $_SERVER['PHP_SELF'],
        'management',
        'PluginZimmetMenu',
        'template'
    );

    PluginZimmetMenu::showNavHeader('template');

    $tpl->display(['id' => $id]);

    PluginZimmetMenu::closeApp();
    Html::footer();
}
