<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Arayüzden ZIP / GitHub ile kendini güncelleme
 * -------------------------------------------------------------------------
 *
 * Yetkili kullanıcı, eklentinin yeni sürümünü .zip olarak yükleyebilir veya
 * doğrudan GitHub deposundan en son sürümü indirip uygulayabilir. Sistem
 * mevcut sürümü yedekler, dosyaları güvenli şekilde değiştirir ve OPcache'i
 * sıfırlar. (Sunucuya/cPanel'e gerek kalmaz.)
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginZimmetUpdate
{
    /** Zip içinde beklenen üst klasör adı */
    const PLUGIN_FOLDER = 'zimmet';

    /** GitHub deposu (owner/repo) */
    const GITHUB_REPO = 'erdogankamar/zimmet';

    /** GitHub varsayılan dalı (sürüm/etiket yoksa) */
    const GITHUB_BRANCH = 'main';

    // ====================================================================
    //  GİRİŞ NOKTALARI
    // ====================================================================

    /**
     * Yüklenen zip dosyasını uygular.
     *
     * @param array $file  $_FILES['plugin_zip'] dizisi
     *
     * @return array
     */
    public static function applyZip(array $file)
    {
        if (!class_exists('ZipArchive')) {
            return self::fail('Güncelleme başlatılamadı. Sunucuda PHP ZipArchive eklentisi etkin olmalıdır.');
        }
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return self::fail('Geçerli bir güncelleme paketi yüklenmedi.');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
            return self::fail('Paket türü uygun değil. Lütfen .zip uzantılı Zimmet güncelleme paketini yükleyin.');
        }

        return self::applyArchive($file['tmp_name'], 'zip');
    }

    /**
     * GitHub deposundan en son sürümü indirip uygular.
     *
     * @return array
     */
    public static function applyFromGithub()
    {
        $latest = self::getLatestRelease();
        if (!empty($latest['error'])) {
            return self::fail($latest['error']);
        }

        $data = self::httpGet($latest['zip_url'], false);
        if ($data === null || strlen($data) < 100) {
            return self::fail('GitHub paketi indirilemedi. Sunucunun internet erişimini ve HTTPS/SSL ayarlarını kontrol edin.');
        }

        if (!is_dir(GLPI_TMP_DIR)) {
            @mkdir(GLPI_TMP_DIR, 0755, true);
        }
        $tmpZip = GLPI_TMP_DIR . '/zimmet_gh_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
        if (@file_put_contents($tmpZip, $data) === false) {
            return self::fail('İndirilen paket geçici dizine yazılamadı: ' . GLPI_TMP_DIR);
        }

        return self::applyArchive($tmpZip, 'github', $tmpZip);
    }

    // ====================================================================
    //  ARAYÜZ (Ayarlar sayfasına gömülü güncelleme merkezi)
    // ====================================================================

    /**
     * Güncelleme POST eylemlerini işler (ZIP / GitHub) ve sayfaya geri döner.
     * Eylem varsa işlem yapıp yönlendirir ve betiği sonlandırır.
     *
     * @param string $pageUrl  İşlem sonrası dönülecek sayfa (config.php)
     */
    public static function handleActions($pageUrl)
    {
        if (isset($_POST['do_update'])) {
            $_SESSION['plugin_zimmet_update_result'] = self::applyZip($_FILES['plugin_zip'] ?? []);
        } elseif (isset($_POST['do_github_update'])) {
            $_SESSION['plugin_zimmet_update_result'] = self::applyFromGithub();
        } else {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (!headers_sent()) {
            header('Location: ' . $pageUrl, true, 303);
        }
        echo "<!doctype html><html lang='tr'><head><meta charset='utf-8'>"
            . "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . "'>"
            . "<title>Yönlendiriliyor…</title></head>"
            . "<body style='font-family:Arial,sans-serif;color:#1f2937;padding:24px'>"
            . "<p>İşlem tamamlandı. Ayarlar sayfasına dönülüyor…</p>"
            . "<script>window.location.replace(" . json_encode($pageUrl) . ");</script>"
            . "</body></html>";
        exit;
    }

    /**
     * Güncelleme merkezini (durum, GitHub, Manuel, yedekler) render eder.
     * Ayarlar sayfasının altına gömülür.
     *
     * @param string $pageUrl  Form/yönlendirme hedefi (config.php)
     */
    public static function renderCenter($pageUrl)
    {
        $pluginDir = Plugin::getPhpDir('zimmet');
        $writable  = is_writable($pluginDir);
        $hasZip    = class_exists('ZipArchive');
        $canUpdate = $writable && $hasZip;

        $updateResult = $_SESSION['plugin_zimmet_update_result'] ?? null;
        unset($_SESSION['plugin_zimmet_update_result']);

        // GitHub sürüm kontrolü yalnızca kullanıcı istediğinde (ağ çağrısı)
        $latest = isset($_GET['ghcheck']) ? self::getLatestRelease() : null;

        $checkUrl = $pageUrl . '?ghcheck=1';

        echo "<div class='card' style='margin-top:16px'><div class='card-body'>";
        echo "<div class='zimmet-card-head'><i class='ti ti-cloud-upload'></i><h3>Güncelleme Merkezi</h3></div>";

        // ---- Son işlem sonucu ----
        if (is_array($updateResult)) {
            $panelClass = $updateResult['success'] ? 'success' : 'error';
            $icon = $updateResult['success'] ? 'ti ti-circle-check' : 'ti ti-alert-triangle';
            $title = $updateResult['success'] ? 'Güncelleme tamamlandı' : 'Güncelleme tamamlanamadı';
            echo "<div class='zimmet-update-panel $panelClass'>";
            echo "<h4><i class='" . $icon . "'></i> " . $title . "</h4>";
            echo "<ul class='zimmet-update-list'>";
            echo "<li><span>İşlem durumu</span><span>" . htmlspecialchars($updateResult['message'] ?? '') . "</span></li>";
            if (!empty($updateResult['source'])) {
                echo "<li><span>Kaynak</span><span>" . ($updateResult['source'] === 'github' ? 'GitHub' : 'ZIP paketi') . "</span></li>";
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
            echo "</ul></div>";
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

        // ---- Yan yana: GitHub / Manuel ----
        echo "<div class='zimmet-update-cols'>";

        // Sütun 1: GitHub
        echo "<div class='zimmet-update-col'>";
        echo "<div class='zimmet-update-panel'>";
        echo "<h4><i class='ti ti-brand-github'></i> GitHub'dan güncelle</h4>";
        echo "<p class='text-muted' style='margin:.2rem 0 .8rem'>"
            . "En son sürümü doğrudan resmi depodan indirip uygular: "
            . "<a href='https://github.com/" . self::GITHUB_REPO . "' target='_blank' rel='noopener'>"
            . htmlspecialchars(self::GITHUB_REPO) . "</a></p>";

        if (is_array($latest)) {
            if (!empty($latest['error'])) {
                echo "<div class='alert alert-warning' style='margin-bottom:10px'>"
                    . "<i class='ti ti-alert-triangle'></i> " . htmlspecialchars($latest['error']) . "</div>";
                echo "<a href='" . htmlspecialchars($checkUrl) . "' class='btn btn-outline-primary'>"
                    . "<i class='ti ti-refresh'></i> Yeniden denetle</a>";
            } else {
                $isBranch = !empty($latest['is_branch']);
                $isNewer  = !$isBranch && version_compare($latest['version'], PLUGIN_ZIMMET_VERSION, '>');

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
                        . "<strong>" . htmlspecialchars(self::GITHUB_BRANCH) . "</strong> dalının güncel hali uygulanacak.</div>";
                } elseif ($isNewer) {
                    echo "<div class='alert alert-success' style='margin-bottom:10px'>"
                        . "<i class='ti ti-arrow-up-circle'></i> Yeni sürüm mevcut: <strong>"
                        . htmlspecialchars($latest['tag']) . "</strong></div>";
                } else {
                    echo "<div class='alert alert-secondary' style='margin-bottom:10px'>"
                        . "<i class='ti ti-circle-check'></i> En güncel sürümü kullanıyorsunuz. İsterseniz yeniden kurabilirsiniz.</div>";
                }

                echo "<div class='zimmet-update-actions'>";
                if ($canUpdate) {
                    echo "<button type='button' id='zimmet-open-github-modal' class='btn "
                        . ($isNewer ? 'btn-primary' : 'btn-outline-primary') . "' "
                        . "data-version='" . htmlspecialchars($latest['tag'], ENT_QUOTES) . "'>"
                        . "<i class='ti ti-cloud-download'></i> "
                        . ($isNewer ? 'GitHub\'dan güncelle' : 'Yeniden kur / güncelle') . "</button>";
                }
                echo "<a href='" . htmlspecialchars($checkUrl) . "' class='btn btn-outline-secondary'>"
                    . "<i class='ti ti-refresh'></i> Yeniden denetle</a>";
                echo "</div>";
                if (!$canUpdate) {
                    echo "<div class='text-danger' style='font-size:.88rem;margin-top:8px'><i class='ti ti-x'></i> "
                        . "Güncelleme için eklenti klasörü yazılabilir ve ZipArchive etkin olmalıdır.</div>";
                }
            }
        } else {
            echo "<a href='" . htmlspecialchars($checkUrl) . "' class='btn btn-outline-primary'>"
                . "<i class='ti ti-refresh'></i> Sürümü kontrol et</a>";
        }
        echo "</div></div>"; // panel + sütun

        // Sütun 2: Manuel
        if ($canUpdate) {
            $maxUpload = ini_get('upload_max_filesize');
            echo "<form id='zimmet-update-form' method='post' enctype='multipart/form-data' action='"
                . $pageUrl . "' class='zimmet-update-col'>";
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
            echo "</div>";
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

        // Gizli formlar + onay pencereleri
        if ($canUpdate) {
            echo "<form id='zimmet-github-form' method='post' action='" . $pageUrl . "' style='display:none'>";
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

        // ---- Son yedekler ----
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
            echo "</tbody></table></div>";
            echo "<div class='backup-path'>"
                . "<i class='ti ti-folder'></i> Yedekler sunucuda şu dizinde saklanır: "
                . htmlspecialchars($backupDir) . "</div>";
            echo "</div>";
        }

        echo "</div></div>"; // card-body + card
    }

    // ====================================================================
    //  GITHUB YARDIMCILARI
    // ====================================================================

    /**
     * GitHub'daki en son yayın/etiket bilgisini döndürür.
     *
     * @return array  ['version','tag','zip_url','published_at','is_branch'] veya ['error']
     */
    public static function getLatestRelease()
    {
        $base   = 'https://api.github.com/repos/' . self::GITHUB_REPO;
        $apiOk  = false;

        // 1) Yayınlanmış release (varsa en güveniliri)
        $rel = self::httpGet($base . '/releases/latest', true);
        if ($rel !== null) {
            $apiOk = true;
            $j = json_decode($rel, true);
            if (is_array($j) && !empty($j['tag_name'])) {
                return self::tagInfo($j['tag_name'], $j['published_at'] ?? '');
            }
        }

        // 2) Release yoksa etiketlerden en yükseğini seç
        $tags = self::httpGet($base . '/tags', true);
        if ($tags !== null) {
            $apiOk = true;
            $j = json_decode($tags, true);
            if (is_array($j) && !empty($j)) {
                usort($j, static function ($a, $b) {
                    return version_compare(
                        ltrim((string) ($b['name'] ?? '0'), 'vV'),
                        ltrim((string) ($a['name'] ?? '0'), 'vV')
                    );
                });
                if (!empty($j[0]['name'])) {
                    return self::tagInfo($j[0]['name'], '');
                }
            }
        }

        // 3) API'ye erişilemediyse hata; erişildi ama sürüm yoksa dala düş
        if (!$apiOk) {
            return ['error' => 'GitHub API\'sine erişilemedi. Sunucunun internet/HTTPS erişimini ve (varsa) proxy ayarlarını kontrol edin.'];
        }

        return [
            'version'      => self::GITHUB_BRANCH,
            'tag'          => self::GITHUB_BRANCH,
            'zip_url'      => 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip',
            'published_at' => '',
            'is_branch'    => true,
        ];
    }

    /**
     * Bir etiket adından sürüm bilgisini ve indirme bağlantısını üretir.
     */
    private static function tagInfo($tag, $publishedAt)
    {
        $tag = (string) $tag;
        return [
            'version'      => ltrim($tag, 'vV'),
            'tag'          => $tag,
            'zip_url'      => 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/tags/' . rawurlencode($tag) . '.zip',
            'published_at' => (string) $publishedAt,
            'is_branch'    => false,
        ];
    }

    /**
     * HTTP GET — curl varsa onu, yoksa stream wrapper kullanır.
     * GLPI proxy ayarları varsa uygular.
     *
     * @return string|null  Gövde, veya hata/HTTP başarısızlığında null
     */
    private static function httpGet($url, $isApi)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $headers = ['User-Agent: GLPI-Zimmet-Plugin'];
        if ($isApi) {
            $headers[] = 'Accept: application/vnd.github+json';
            $headers[] = 'X-GitHub-Api-Version: 2022-11-28';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => $headers,
            ]);

            // GLPI proxy desteği
            if (!empty($CFG_GLPI['proxy_name'])) {
                curl_setopt($ch, CURLOPT_PROXY, $CFG_GLPI['proxy_name']);
                if (!empty($CFG_GLPI['proxy_port'])) {
                    curl_setopt($ch, CURLOPT_PROXYPORT, (int) $CFG_GLPI['proxy_port']);
                }
                if (!empty($CFG_GLPI['proxy_user'])) {
                    $pass = '';
                    if (!empty($CFG_GLPI['proxy_passwd']) && class_exists('GLPIKey')) {
                        try {
                            $pass = (new GLPIKey())->decrypt($CFG_GLPI['proxy_passwd']);
                        } catch (\Throwable $e) {
                            $pass = '';
                        }
                    }
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $CFG_GLPI['proxy_user'] . ':' . $pass);
                }
            }

            $data = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($data === false || $code < 200 || $code >= 300) {
                return null;
            }
            return $data;
        }

        // curl yoksa stream wrapper (allow_url_fopen gerekir)
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'timeout' => 90,
            ],
            'ssl'  => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        return $data === false ? null : $data;
    }

    // ====================================================================
    //  ÇEKİRDEK UYGULAMA
    // ====================================================================

    /**
     * Bir zip dosyasını (yerel yol) güvenle uygular.
     *
     * @param string      $zipPath   Açılacak zip dosyasının yolu
     * @param string      $source    'zip' | 'github'
     * @param string|null $cleanup   İşlem sonunda silinecek geçici zip (varsa)
     *
     * @return array
     */
    private static function applyArchive($zipPath, $source, $cleanup = null)
    {
        $currentVersion = defined('PLUGIN_ZIMMET_VERSION') ? PLUGIN_ZIMMET_VERSION : '';
        $rm = static function () use ($cleanup) {
            if ($cleanup && is_file($cleanup)) {
                @unlink($cleanup);
            }
        };

        if (!class_exists('ZipArchive')) {
            $rm();
            return self::fail('Güncelleme başlatılamadı. Sunucuda PHP ZipArchive eklentisi etkin olmalıdır.');
        }

        $pluginDir   = Plugin::getPhpDir(self::PLUGIN_FOLDER);   // .../plugins/zimmet
        $pluginsRoot = dirname($pluginDir);                       // .../plugins

        if (!is_writable($pluginsRoot) || !is_writable($pluginDir)) {
            $rm();
            return self::fail('Güncelleme uygulanamadı. Eklenti klasörü yazılabilir olmalıdır: ' . $pluginDir);
        }

        // 1) Zip'i aç + zip-slip ön kontrolü
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $rm();
            return self::fail('Güncelleme paketi açılamadı. Dosyanın bütünlüğünü kontrol edip tekrar deneyin.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && (strpos($name, '..') !== false || strpos($name, "\0") !== false)) {
                $zip->close();
                $rm();
                return self::fail('Paket güvenlik kontrolünden geçemedi. Güvenli olmayan dosya yolu tespit edildi.');
            }
        }

        // 2) İzole geçici dizine çıkar
        if (!is_dir(GLPI_TMP_DIR)) {
            @mkdir(GLPI_TMP_DIR, 0755, true);
        }
        $stage = GLPI_TMP_DIR . '/zimmet_stage_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        if (!@mkdir($stage, 0755, true)) {
            $zip->close();
            $rm();
            return self::fail('Geçici çıkarma dizini oluşturulamadı: ' . GLPI_TMP_DIR);
        }
        if (!$zip->extractTo($stage)) {
            $zip->close();
            self::rrmdir($stage);
            $rm();
            return self::fail('Güncelleme dosyaları çıkarılamadı. Disk alanı ve yazma izinlerini kontrol edin.');
        }
        $zip->close();
        $rm(); // indirilen/yüklenen zip artık gerekmiyor

        // 3) Paket içindeki eklenti kökünü bul (zimmet/ veya zimmet-1.3.0/ olabilir)
        $root = self::locatePluginRoot($stage);
        if ($root === null) {
            self::rrmdir($stage);
            return self::fail('Paket doğrulanamadı. Geçerli bir Zimmet eklenti paketi değil (zimmet/setup.php bulunamadı).');
        }

        // Yeni sürümü oku
        $newVersion = null;
        $setupContent = @file_get_contents($root . '/setup.php');
        if ($setupContent && preg_match("/PLUGIN_ZIMMET_VERSION'\\s*,\\s*'([^']+)'/", $setupContent, $m)) {
            $newVersion = $m[1];
        }

        // 4) Mevcut sürümü yedekle
        $backupFile = self::backupCurrent($pluginDir);

        // 5) Yeni dosyaları eklenti klasörüne kopyala (üzerine yaz)
        if (!self::rcopy($root, $pluginDir)) {
            self::rrmdir($stage);
            return self::fail(
                'Güncelleme dosyaları kopyalanamadı. Mevcut yedek korundu: ' . ($backupFile ? basename($backupFile) : '-'),
                $currentVersion,
                $newVersion,
                $backupFile
            );
        }
        self::rrmdir($stage);

        // 6) OPcache'i temizle — yeni dosyaların bir sonraki istekte yüklenmesi için ŞART
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (function_exists('clearstatcache')) {
            clearstatcache(true);
        }

        // 7) GLPI'deki kayıtlı sürümü yeni dosyalarla eşitle (yetki/erişim kaybını önler)
        if ($newVersion !== null) {
            /** @var DBmysql $DB */
            global $DB;
            if ($DB->tableExists('glpi_plugins')) {
                $DB->update(
                    'glpi_plugins',
                    ['version' => $newVersion],
                    ['directory' => self::PLUGIN_FOLDER]
                );
            }
        }

        // 8) Denetim günlüğü
        Event::log(
            0,
            'PluginZimmetDocument',
            4,
            'setup',
            sprintf(
                __('%1$s updated the Zimmet plugin to version %2$s', 'zimmet'),
                $_SESSION['glpiname'] ?? 'system',
                $newVersion ?: '?'
            )
        );

        return [
            'success'         => true,
            'message'         => $source === 'github'
                ? 'GitHub üzerinden güncelleme başarıyla tamamlandı.'
                : 'Güncelleme başarıyla tamamlandı.',
            'current_version' => $currentVersion,
            'version'         => $newVersion,
            'backup'          => $backupFile ? basename($backupFile) : '',
            'completed_at'    => date('Y-m-d H:i:s'),
            'source'          => $source,
        ];
    }

    /**
     * Çıkarılan dizinde geçerli eklenti kökünü (setup.php içeren) bulur.
     * Hem zimmet/ hem zimmet-<sürüm>/ gibi GitHub arşiv yapılarını destekler.
     *
     * @return string|null
     */
    private static function locatePluginRoot($dir)
    {
        if (self::isZimmetSetup($dir . '/setup.php')) {
            return $dir;
        }
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
            if (self::isZimmetSetup($sub . '/setup.php')) {
                return $sub;
            }
        }
        return null;
    }

    private static function isZimmetSetup($file)
    {
        if (!is_file($file)) {
            return false;
        }
        $c = @file_get_contents($file);
        return $c !== false && strpos($c, 'plugin_version_zimmet') !== false;
    }

    /**
     * Kaynak dizini hedefe özyinelemeli kopyalar (üzerine yazar/birleştirir).
     *
     * @return boolean
     */
    private static function rcopy($src, $dst)
    {
        if (!is_dir($dst) && !@mkdir($dst, 0755, true)) {
            return false;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $rel    = substr($item->getPathname(), strlen($src) + 1);
            $target = $dst . '/' . $rel;
            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true)) {
                    return false;
                }
            } elseif (!@copy($item->getPathname(), $target)) {
                return false;
            } elseif (substr($target, -4) === '.php' && function_exists('opcache_invalidate')) {
                // Değiştirilen her PHP dosyasını anında geçersiz kıl (FPM'de
                // global opcache_reset gecikebildiğinden bayat bytecode'u önler)
                @opcache_invalidate($target, true);
            }
        }
        return true;
    }

    /**
     * Bir dizini özyinelemeli siler.
     */
    private static function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    /**
     * Mevcut eklenti klasörünü zip olarak yedekler.
     *
     * @return string|null  Yedek dosyasının yolu
     */
    private static function backupCurrent($pluginDir)
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }

        $backupDir = GLPI_PLUGIN_DOC_DIR . '/zimmet/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $curVer = defined('PLUGIN_ZIMMET_VERSION') ? PLUGIN_ZIMMET_VERSION : 'x';
        $backupFile = $backupDir . '/zimmet_' . $curVer . '_' . date('Ymd_His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $base = dirname($pluginDir);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $localPath = substr($item->getPathname(), strlen($base) + 1);
            if ($item->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($item->getPathname(), $localPath);
            }
        }
        $zip->close();

        // Son 5 yedeği tut, eskileri sil
        self::pruneBackups($backupDir, 5);

        return $backupFile;
    }

    /**
     * Eski yedekleri temizler (yalnızca en yeni $keep tanesini tutar).
     */
    private static function pruneBackups($dir, $keep)
    {
        $files = glob($dir . '/zimmet_*.zip');
        if (!$files || count($files) <= $keep) {
            return;
        }
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    private static function fail($message, $currentVersion = null, $newVersion = null, $backupFile = null)
    {
        return [
            'success'         => false,
            'message'         => $message,
            'current_version' => $currentVersion,
            'version'         => $newVersion,
            'backup'          => $backupFile ? basename($backupFile) : '',
            'completed_at'    => date('Y-m-d H:i:s'),
        ];
    }
}
