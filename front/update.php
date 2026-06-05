<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Güncelleme Merkezi (ZIP yükleme + GitHub'dan güncelleme)
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

// -------------------------------------------------------------------------
// Güncelleme sonrası boş (beyaz) sayfa korumasI
// -------------------------------------------------------------------------
// Güncelleme uygulandıktan hemen sonra, GLPI dosya sürümü ile veritabanı
// sürümü arasındaki anlık geçiş + OPcache nedeniyle eklentiyi kısa süreliğine
// "yüklenmedi" sayabilir; bu durumda eklenti sınıfları otomatik yüklenmez ve
// bu sayfa "class not found" ile boş kalırdı. Aşağıda eklenti sabit ve
// sınıflarını doğrudan yükleyerek sayfanın HER durumda render edilmesini
// garanti ediyoruz.
if (!defined('PLUGIN_ZIMMET_VERSION') && is_file(__DIR__ . '/../setup.php')) {
    include_once __DIR__ . '/../setup.php';
}
foreach (glob(__DIR__ . '/../inc/*.class.php') ?: [] as $zimmetClassFile) {
    require_once $zimmetClassFile;
}

// Beklenmedik bir ölümcül hata olursa boş sayfa yerine okunur bir mesaj göster
// (bu sayfa yalnızca config yetkisi olan yöneticilere açıktır).
register_shutdown_function(static function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8', true, 500);
        }
        echo "<div style='font-family:Arial,sans-serif;max-width:760px;margin:40px auto;"
            . "padding:20px 24px;border:1px solid #e3b7b7;border-radius:10px;background:#fff6f6;color:#7a1f1f'>"
            . "<h2 style='margin:0 0 8px'>Güncelleme sayfası yüklenemedi</h2>"
            . "<p>İşlem büyük olasılıkla tamamlandı ancak sayfa render edilirken bir hata oluştu. "
            . "Birkaç saniye bekleyip sayfayı yenileyin; sorun sürerse aşağıdaki teknik detayı paylaşın.</p>"
            . "<pre style='white-space:pre-wrap;background:#fff;border:1px solid #eee;border-radius:6px;"
            . "padding:10px;font-size:12px;color:#444'>"
            . htmlspecialchars($e['message'] . "\n" . ($e['file'] ?? '') . ':' . ($e['line'] ?? ''), ENT_QUOTES, 'UTF-8')
            . "</pre></div>";
    }
});

Session::checkRight('plugin_zimmet_config', UPDATE);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$updateUrl = Plugin::getWebDir('zimmet') . '/front/update.php';

/**
 * İşlem sonrası daima Güncelleme Merkezi'ne döner; sonuç oturumda taşınır.
 * (Dosyalar değiştikten sonra temiz bir yeni istekte yeni sürüm yüklenir.)
 */
$redirectBack = static function () use ($updateUrl) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (!headers_sent()) {
        header('Location: ' . $updateUrl, true, 303);
    }
    echo "<!doctype html><html lang='tr'><head><meta charset='utf-8'>"
        . "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($updateUrl, ENT_QUOTES, 'UTF-8') . "'>"
        . "<title>Yönlendiriliyor…</title></head>"
        . "<body style='font-family:Arial,sans-serif;color:#1f2937;padding:24px'>"
        . "<p>İşlem tamamlandı. Güncelleme Merkezi'ne dönülüyor…</p>"
        . "<script>window.location.replace(" . json_encode($updateUrl) . ");</script>"
        . "</body></html>";
    exit;
};

// --- ZIP ile güncelleme ---
if (isset($_POST['do_update'])) {
    $_SESSION['plugin_zimmet_update_result'] = PluginZimmetUpdate::applyZip($_FILES['plugin_zip'] ?? []);
    $redirectBack();
}

// --- GitHub'dan güncelleme ---
if (isset($_POST['do_github_update'])) {
    $_SESSION['plugin_zimmet_update_result'] = PluginZimmetUpdate::applyFromGithub();
    $redirectBack();
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
$canUpdate = $writable && $hasZip;

$updateResult = $_SESSION['plugin_zimmet_update_result'] ?? null;
unset($_SESSION['plugin_zimmet_update_result']);

// GitHub sürüm kontrolü yalnızca kullanıcı istediğinde yapılır (ağ çağrısı)
$latest = null;
if (isset($_GET['ghcheck'])) {
    $latest = PluginZimmetUpdate::getLatestRelease();
}

echo "<div class='card'><div class='card-body'>";

// ---- Son işlem sonucu paneli ----
if (is_array($updateResult)) {
    $panelClass = $updateResult['success'] ? 'success' : 'error';
    $icon = $updateResult['success'] ? 'ti ti-circle-check' : 'ti ti-alert-triangle';
    $title = $updateResult['success'] ? 'Güncelleme tamamlandı' : 'Güncelleme tamamlanamadı';
    echo "<div class='zimmet-update-panel $panelClass'>";
    echo "<h4><i class='" . $icon . "'></i> " . $title . "</h4>";
    echo "<ul class='zimmet-update-list'>";
    echo "<li><span>İşlem durumu</span><span>" . htmlspecialchars($updateResult['message'] ?? '') . "</span></li>";
    if (!empty($updateResult['source'])) {
        $srcLabel = $updateResult['source'] === 'github' ? 'GitHub' : 'ZIP paketi';
        echo "<li><span>Kaynak</span><span>" . $srcLabel . "</span></li>";
    }
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

// ---- Durum bilgileri ----
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

// ====================================================================
//  GÜNCELLEME SEÇENEKLERİ (yan yana: GitHub / Manuel)
// ====================================================================
echo "<div class='zimmet-update-cols'>";

// ---- Sütun 1: GitHub'dan güncelle ----
echo "<div class='zimmet-update-col'>";
echo "<div class='zimmet-update-panel'>";
echo "<h4><i class='ti ti-brand-github'></i> GitHub'dan güncelle</h4>";
echo "<p class='text-muted' style='margin:.2rem 0 .8rem'>"
    . "En son sürümü doğrudan resmi depodan indirip uygular: "
    . "<a href='https://github.com/" . PluginZimmetUpdate::GITHUB_REPO . "' target='_blank' rel='noopener'>"
    . htmlspecialchars(PluginZimmetUpdate::GITHUB_REPO) . "</a></p>";

if (is_array($latest)) {
    if (!empty($latest['error'])) {
        echo "<div class='alert alert-warning' style='margin-bottom:10px'>"
            . "<i class='ti ti-alert-triangle'></i> " . htmlspecialchars($latest['error']) . "</div>";
    } else {
        $latestVer = $latest['version'];
        $isBranch  = !empty($latest['is_branch']);
        $isNewer   = !$isBranch && version_compare($latestVer, PLUGIN_ZIMMET_VERSION, '>');

        echo "<ul class='zimmet-update-list' style='margin-bottom:12px'>";
        echo "<li><span>Kurulu sürüm</span><span>" . htmlspecialchars(PLUGIN_ZIMMET_VERSION) . "</span></li>";
        echo "<li><span>GitHub'daki sürüm</span><span>"
            . htmlspecialchars($isBranch ? ($latest['tag'] . ' (dal)') : $latest['tag']) . "</span></li>";
        if (!empty($latest['published_at'])) {
            echo "<li><span>Yayın tarihi</span><span>" . Html::convDateTime(date('Y-m-d H:i:s', strtotime($latest['published_at']))) . "</span></li>";
        }
        echo "</ul>";

        if ($isBranch) {
            echo "<div class='alert alert-info' style='margin-bottom:10px'>"
                . "<i class='ti ti-git-branch'></i> Depoda yayınlanmış sürüm/etiket bulunamadı; "
                . "<strong>" . htmlspecialchars(PluginZimmetUpdate::GITHUB_BRANCH) . "</strong> dalının güncel hali uygulanacak.</div>";
        } elseif ($isNewer) {
            echo "<div class='alert alert-success' style='margin-bottom:10px'>"
                . "<i class='ti ti-arrow-up-circle'></i> Yeni sürüm mevcut: <strong>"
                . htmlspecialchars($latest['tag']) . "</strong></div>";
        } else {
            echo "<div class='alert alert-secondary' style='margin-bottom:10px'>"
                . "<i class='ti ti-circle-check'></i> En güncel sürümü kullanıyorsunuz. İsterseniz yeniden kurabilirsiniz.</div>";
        }

        if ($canUpdate) {
            echo "<button type='button' id='zimmet-open-github-modal' class='btn "
                . ($isNewer ? 'btn-primary' : 'btn-outline-primary') . "' "
                . "data-version='" . htmlspecialchars($latest['tag'], ENT_QUOTES) . "'>"
                . "<i class='ti ti-cloud-download'></i> "
                . ($isNewer ? 'GitHub\'dan güncelle' : 'Yeniden kur / güncelle') . "</button>";
        } else {
            echo "<div class='text-danger' style='font-size:.88rem'><i class='ti ti-x'></i> "
                . "Güncelleme için eklenti klasörü yazılabilir ve ZipArchive etkin olmalıdır.</div>";
        }
    }
} else {
    echo "<a href='" . htmlspecialchars($updateUrl . '?ghcheck=1') . "' class='btn btn-outline-primary'>"
        . "<i class='ti ti-refresh'></i> Sürümü kontrol et</a>";
}
echo "</div>"; // github paneli
echo "</div>"; // github sütunu

// ---- Sütun 2: Manuel Güncelle (ZIP) ----
if ($canUpdate) {
    $maxUpload = ini_get('upload_max_filesize');
    echo "<form id='zimmet-update-form' method='post' enctype='multipart/form-data' action='"
        . $updateUrl . "' class='zimmet-update-col'>";
    echo "<div class='zimmet-update-panel'>";
    echo "<h4><i class='ti ti-package-import'></i> Manuel Güncelle</h4>";
    echo "<p class='text-muted' style='margin:.2rem 0 .8rem'>"
        . "Bir <code>zimmet.zip</code> paketi seçin; sistem doğrular, mevcut sürümü otomatik yedekler ve dosyaları günceller.</p>";
    echo "<div id='zimmet-update-inline-error' class='alert alert-warning' style='display:none;margin-bottom:10px'></div>";
    echo "<input id='zimmet-plugin-zip' type='file' name='plugin_zip' accept='.zip' required class='form-control' style='max-width:520px'>";
    echo "<div class='text-muted' style='font-size:.85rem'>"
        . "Sunucu yükleme limiti: " . htmlspecialchars($maxUpload) . "</div>";
    echo "<div class='zimmet-update-actions'>";
    echo "<button type='button' id='zimmet-open-update-modal' class='btn btn-primary'>"
        . "<i class='ti ti-cloud-upload'></i> Paketi doğrula ve güncelle</button>";
    echo "<span class='text-muted' style='font-size:.86rem'>Beklenen yapı: <code>zimmet/setup.php</code></span>";
    echo "</div>";
    echo "</div>"; // panel
    echo "<input type='hidden' name='do_update' value='1'>";
    Html::closeForm();
} else {
    echo "<div class='zimmet-update-col'>";
    echo "<div class='zimmet-update-panel error'>";
    echo "<h4><i class='ti ti-alert-triangle'></i> Manuel Güncelle</h4>";
    echo "<p>Otomatik güncelleme için eklenti klasörü yazılabilir olmalı ve PHP ZipArchive eklentisi etkin olmalıdır.</p>";
    echo "</div></div>";
}

echo "</div>"; // .zimmet-update-cols

// Gizli formlar + onay pencereleri (grid dışında tutulur)
if ($canUpdate) {
    echo "<form id='zimmet-github-form' method='post' action='" . $updateUrl . "' style='display:none'>";
    echo "<input type='hidden' name='do_github_update' value='1'>";
    Html::closeForm();

    echo "<div id='zimmet-github-modal' class='zimmet-modal-backdrop' role='dialog' aria-modal='true'>";
    echo "<div class='zimmet-modal'>";
    echo "<div class='zimmet-modal-head'><h3><i class='ti ti-brand-github'></i> GitHub güncelleme onayı</h3></div>";
    echo "<div class='zimmet-modal-body'>";
    echo "<p>En son sürüm GitHub'dan indirilip uygulanacak. İşlemden önce mevcut eklenti klasörü otomatik yedeklenir.</p>";
    echo "<ul class='zimmet-update-list'>";
    echo "<li><span>Kurulu sürüm</span><span>" . htmlspecialchars(PLUGIN_ZIMMET_VERSION) . "</span></li>";
    echo "<li><span>İndirilecek sürüm</span><span id='zimmet-gh-version'>-</span></li>";
    echo "<li><span>Yedekleme</span><span>Otomatik</span></li>";
    echo "</ul>";
    echo "</div>";
    echo "<div class='zimmet-modal-foot'>";
    echo "<button type='button' id='zimmet-gh-cancel' class='btn btn-outline-secondary'>Vazgeç</button>";
    echo "<button type='button' id='zimmet-gh-confirm' class='btn btn-primary'>İndir ve güncelle</button>";
    echo "</div></div></div>";

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
}

echo Html::scriptBlock("
    $(function() {
        // ZIP güncelleme akışı
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
            $('#zimmet-selected-package').text(file.val().split('\\\\').pop());
            modal.css('display', 'flex');
        });
        $('#zimmet-cancel-update').on('click', function() { modal.hide(); });
        $('#zimmet-confirm-update').on('click', function() {
            $(this).prop('disabled', true).html('<i class=\"ti ti-loader\"></i> Güncelleniyor...');
            form.trigger('submit');
        });

        // GitHub güncelleme akışı
        var ghModal = $('#zimmet-github-modal');
        var ghForm = $('#zimmet-github-form');
        $('#zimmet-open-github-modal').on('click', function() {
            $('#zimmet-gh-version').text($(this).data('version') || 'en son');
            ghModal.css('display', 'flex');
        });
        $('#zimmet-gh-cancel').on('click', function() { ghModal.hide(); });
        $('#zimmet-gh-confirm').on('click', function() {
            $(this).prop('disabled', true).html('<i class=\"ti ti-loader\"></i> İndiriliyor...');
            ghForm.trigger('submit');
        });
    });
");

// ====================================================================
//  SON YEDEKLER
// ====================================================================
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
