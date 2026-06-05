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
