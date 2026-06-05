<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Arayüzden ZIP ile güncelleme sayfası
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_config', UPDATE);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// Yükleme işlemi (CSRF kontrolü GLPI tarafından otomatik yapılır)
if (isset($_POST['do_update'])) {
    $result = PluginZimmetUpdate::applyZip($_FILES['plugin_zip'] ?? []);
    $_SESSION['plugin_zimmet_update_result'] = $result;
    if (!empty($result['success'])) {
        $target = Plugin::getWebDir('zimmet') . '/front/document.php';
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (!headers_sent()) {
            header('Location: ' . $target);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo "<!doctype html><html><head><meta charset='utf-8'>"
            . "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . "'>"
            . "<title>Güncelleme tamamlandı</title></head>"
            . "<body style='font-family:Arial,sans-serif;color:#1f2937;padding:24px'>"
            . "<p>Güncelleme tamamlandı. Zimmet ana sayfasına yönlendiriliyorsunuz.</p>"
            . "<script>window.location.replace(" . json_encode($target) . ");</script>"
            . "</body></html>";
        exit;
    }
    Html::redirect(Plugin::getWebDir('zimmet') . '/front/update.php');
}

Html::header(
    __('Update plugin', 'zimmet'),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginZimmetMenu',
    'update'
);

PluginZimmetMenu::showNavHeader('update');

$pluginDir = Plugin::getPhpDir('zimmet');
$writable  = is_writable($pluginDir);
$hasZip    = class_exists('ZipArchive');
$updateResult = $_SESSION['plugin_zimmet_update_result'] ?? null;
unset($_SESSION['plugin_zimmet_update_result']);

echo "<div class='card'><div class='card-body'>";

if (is_array($updateResult)) {
    $panelClass = $updateResult['success'] ? 'success' : 'error';
    $icon = $updateResult['success'] ? 'ti ti-circle-check' : 'ti ti-alert-triangle';
    $title = $updateResult['success'] ? 'Güncelleme tamamlandı' : 'Güncelleme tamamlanamadı';
    echo "<div class='zimmet-update-panel $panelClass'>";
    echo "<h4><i class='" . $icon . "'></i> " . $title . "</h4>";
    echo "<ul class='zimmet-update-list'>";
    echo "<li><span>İşlem durumu</span><span>" . htmlspecialchars($updateResult['message'] ?? '') . "</span></li>";
    if (!empty($updateResult['current_version'])) {
        echo "<li><span>Önceki sürüm</span><span>" . htmlspecialchars($updateResult['current_version']) . "</span></li>";
    }
    if (!empty($updateResult['version'])) {
        echo "<li><span>Kurulu sürüm</span><span>" . htmlspecialchars($updateResult['version']) . "</span></li>";
    }
    if (!empty($updateResult['backup'])) {
        echo "<li><span>Alınan yedek</span><span>" . htmlspecialchars($updateResult['backup']) . "</span></li>";
    }
    if (!empty($updateResult['completed_at'])) {
        echo "<li><span>İşlem zamanı</span><span>" . Html::convDateTime($updateResult['completed_at']) . "</span></li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Durum bilgileri
echo "<div class='zimmet-update-grid'>";
echo "<div class='zimmet-update-metric'><div class='label'>Kurulu sürüm</div><div class='value'>"
    . htmlspecialchars(PLUGIN_ZIMMET_VERSION) . "</div></div>";
echo "<div class='zimmet-update-metric'><div class='label'>Eklenti klasörü</div><div class='value'>"
    . ($writable ? "<span class='text-success'><i class='ti ti-check'></i> Yazılabilir</span>"
                 : "<span class='text-danger'><i class='ti ti-x'></i> Yazılamıyor</span>")
    . "</div></div>";
echo "<div class='zimmet-update-metric'><div class='label'>ZipArchive</div><div class='value'>"
    . ($hasZip ? "<span class='text-success'><i class='ti ti-check'></i> Etkin</span>"
              : "<span class='text-danger'><i class='ti ti-x'></i> Etkin değil</span>")
    . "</div></div>";
echo "</div>";

if ($writable && $hasZip) {
    // Yükleme formu
    $maxUpload = ini_get('upload_max_filesize');
    echo "<div class='zimmet-update-note'>";
    echo "<strong>İşlem sırası:</strong> Paket seçilir, sistem paketi doğrular, mevcut sürüm otomatik yedeklenir ve dosyalar güncellenir. "
        . "İşlem tamamlandığında bu ekranda sonuç özeti görüntülenir.";
    echo "</div>";

    echo "<form id='zimmet-update-form' method='post' enctype='multipart/form-data' action='"
        . Plugin::getWebDir('zimmet') . "/front/update.php' style='margin-top:16px'>";
    echo "<div class='zimmet-update-panel'>";
    echo "<h4>Yeni paketi yükle</h4>";
    echo "<div id='zimmet-update-inline-error' class='alert alert-warning' style='display:none;margin-bottom:10px'></div>";
    echo "<input id='zimmet-plugin-zip' type='file' name='plugin_zip' accept='.zip' required class='form-control' style='max-width:520px'>";
    echo "<div class='text-muted' style='font-size:.85rem'>"
        . "Sunucu yükleme limiti: " . htmlspecialchars($maxUpload) . "</div>";
    echo "<div class='zimmet-update-actions'>";
    echo "<button type='button' id='zimmet-open-update-modal' class='btn btn-primary'>"
        . "<i class='ti ti-cloud-upload'></i> Paketi doğrula ve güncelle</button>";
    echo "<span class='text-muted' style='font-size:.86rem'>Beklenen paket yapısı: <code>zimmet/setup.php</code></span>";
    echo "</div>";
    echo "</div>";
    echo "<input type='hidden' name='do_update' value='1'>";
    Html::closeForm();

    echo "<div id='zimmet-update-modal' class='zimmet-modal-backdrop' role='dialog' aria-modal='true'>";
    echo "<div class='zimmet-modal'>";
    echo "<div class='zimmet-modal-head'><h3><i class='ti ti-shield-check'></i> Güncelleme onayı</h3></div>";
    echo "<div class='zimmet-modal-body'>";
    echo "<p>Seçilen paket uygulanmadan önce mevcut eklenti klasörü otomatik olarak yedeklenecek.</p>";
    echo "<ul class='zimmet-update-list'>";
    echo "<li><span>Kurulu sürüm</span><span>" . htmlspecialchars(PLUGIN_ZIMMET_VERSION) . "</span></li>";
    echo "<li><span>Seçilen paket</span><span id='zimmet-selected-package'>-</span></li>";
    echo "<li><span>Yedekleme</span><span>Otomatik</span></li>";
    echo "</ul>";
    echo "</div>";
    echo "<div class='zimmet-modal-foot'>";
    echo "<button type='button' id='zimmet-cancel-update' class='btn btn-outline-secondary'>Vazgeç</button>";
    echo "<button type='button' id='zimmet-confirm-update' class='btn btn-primary'>Güncellemeyi başlat</button>";
    echo "</div></div></div>";

    echo Html::scriptBlock("
        $(function() {
            var form = $('#zimmet-update-form');
            var file = $('#zimmet-plugin-zip');
            var modal = $('#zimmet-update-modal');
            var inlineError = $('#zimmet-update-inline-error');
            $('#zimmet-open-update-modal').on('click', function() {
                if (!file.val()) {
                    inlineError.text('Lütfen zimmet.zip güncelleme paketini seçin.').show();
                    return;
                }
                inlineError.hide();
                var name = file.val().split('\\\\').pop();
                $('#zimmet-selected-package').text(name);
                modal.css('display', 'flex');
            });
            $('#zimmet-cancel-update').on('click', function() {
                modal.hide();
            });
            $('#zimmet-confirm-update').on('click', function() {
                $(this).prop('disabled', true).html('<i class=\"ti ti-loader\"></i> Güncelleniyor...');
                form.trigger('submit');
            });
        });
    ");
} else {
    echo "<div class='zimmet-update-panel error'>";
    echo "<h4><i class='ti ti-alert-triangle'></i> Otomatik güncelleme kullanılamıyor</h4>";
    echo "<p>Eklenti klasörü yazılabilir olmalı ve PHP ZipArchive eklentisi etkin olmalıdır. Bu koşullar sağlanmadan dosya yükleyerek güncelleme yapılamaz.</p>";
    echo "</div>";
}

// Son yedekler
$backupDir = GLPI_PLUGIN_DOC_DIR . '/zimmet/backups';
$backups = is_dir($backupDir) ? glob($backupDir . '/zimmet_*.zip') : [];
if ($backups) {
    usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $visibleBackups = array_slice($backups, 0, 5);
    echo "<div class='zimmet-update-backups'>";
    echo "<div class='zimmet-update-backups-head'>";
    echo "<h4><i class='ti ti-database-export'></i> Son yedekler</h4>";
    echo "<span>" . count($visibleBackups) . " son kayıt görüntüleniyor</span>";
    echo "</div>";
    echo "<div class='table-responsive'>";
    echo "<table class='zimmet-document-table'>";
    echo "<colgroup><col style='width:55%'><col style='width:25%'><col style='width:20%'></colgroup>";
    echo "<thead><tr><th>Dosya</th><th>Tarih</th><th>Boyut</th></tr></thead>";
    echo "<tbody>";
    foreach ($visibleBackups as $b) {
        echo "<tr><td class='backup-file'>" . htmlspecialchars(basename($b)) . "</td>"
            . "<td>" . Html::convDateTime(date('Y-m-d H:i:s', filemtime($b))) . "</td>"
            . "<td>" . Toolbox::getSize(filesize($b)) . "</td></tr>";
    }
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "<div class='backup-path'>"
        . "<i class='ti ti-folder'></i> Yedekler sunucuda şu dizinde saklanır: "
        . htmlspecialchars($backupDir) . "</div>";
    echo "</div>";
}

echo "</div></div>";

PluginZimmetMenu::closeApp();
Html::footer();
