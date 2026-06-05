<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Arayüzden ZIP ile kendini güncelleme
 * -------------------------------------------------------------------------
 *
 * Yetkili kullanıcı, eklentinin yeni sürümünü .zip olarak yükler;
 * sistem mevcut sürümü yedekler ve dosyaları güvenli şekilde değiştirir.
 * (Sunucuya/cPanel'e gerek kalmaz.)
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

    /**
     * Yüklenen zip dosyasını uygular.
     *
     * @param array $file  $_FILES['plugin_zip'] dizisi
     *
     * @return array
     */
    public static function applyZip(array $file)
    {
        $currentVersion = defined('PLUGIN_ZIMMET_VERSION') ? PLUGIN_ZIMMET_VERSION : '';

        // 0) Ön kontroller
        if (!class_exists('ZipArchive')) {
            return self::fail('Güncelleme başlatılamadı. Sunucuda PHP ZipArchive eklentisi etkin olmalıdır.');
        }
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return self::fail('Geçerli bir güncelleme paketi yüklenmedi.');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
            return self::fail('Paket türü uygun değil. Lütfen .zip uzantılı Zimmet güncelleme paketini yükleyin.');
        }

        $pluginDir   = Plugin::getPhpDir(self::PLUGIN_FOLDER);   // .../plugins/zimmet
        $pluginsRoot = dirname($pluginDir);                       // .../plugins

        if (!is_writable($pluginsRoot) || !is_writable($pluginDir)) {
            return self::fail('Güncelleme uygulanamadı. Eklenti klasörü yazılabilir olmalıdır: ' . $pluginDir);
        }

        // 1) Zip'i aç
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return self::fail('Güncelleme paketi açılamadı. Dosyanın bütünlüğünü kontrol edip tekrar yükleyin.');
        }

        // 2) İçeriği doğrula: zimmet/setup.php bulunmalı ve geçerli olmalı
        $setupEntry = self::PLUGIN_FOLDER . '/setup.php';
        $setupContent = $zip->getFromName($setupEntry);
        if ($setupContent === false) {
            // Bazı zip'ler kök klasörsüz olabilir; setup.php köke bakalım
            $setupContent = $zip->getFromName('setup.php');
            if ($setupContent === false) {
                $zip->close();
                return self::fail('Paket doğrulanamadı. Paket içinde zimmet/setup.php dosyası bulunmalıdır.');
            }
            $zip->close();
            return self::fail('Paket yapısı uygun değil. Zip dosyası üst seviyede zimmet/ klasörü içermelidir.');
        }
        if (strpos($setupContent, 'plugin_version_zimmet') === false) {
            $zip->close();
            return self::fail('Paket doğrulanamadı. Yüklenen dosya geçerli bir Zimmet eklenti paketi değil.');
        }

        // Yeni sürümü oku (bilgi amaçlı)
        $newVersion = null;
        if (preg_match("/PLUGIN_ZIMMET_VERSION'\\s*,\\s*'([^']+)'/", $setupContent, $m)) {
            $newVersion = $m[1];
        }

        // 3) Zip-slip koruması: tüm girişler zimmet/ altında ve kök içinde olmalı
        $realRoot = realpath($pluginsRoot);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            // Yalnızca plugin klasörü içindekiler
            if (strpos($name, self::PLUGIN_FOLDER . '/') !== 0) {
                $zip->close();
                return self::fail('Paket güvenlik kontrolünden geçemedi. Eklenti klasörü dışında dosya bulundu: ' . $name);
            }
            if (strpos($name, '..') !== false) {
                $zip->close();
                return self::fail('Paket güvenlik kontrolünden geçemedi. Güvenli olmayan dosya yolu tespit edildi.');
            }
        }

        // 4) Mevcut sürümü yedekle
        $backupFile = self::backupCurrent($pluginDir);

        // 5) Çıkart (üzerine yaz)
        if (!$zip->extractTo($pluginsRoot)) {
            $zip->close();
            return self::fail(
                'Güncelleme dosyaları çıkarılamadı. Mevcut yedek korundu: ' . ($backupFile ? basename($backupFile) : '-'),
                $currentVersion,
                $newVersion,
                $backupFile
            );
        }
        $zip->close();

        // 5b) GLPI'deki kayıtlı sürümü yeni dosyalarla eşitle.
        // Aksi halde sürüm uyuşmazlığı eklentiyi "güncelleme gerekli"
        // durumuna alır, yetkiler yüklenmez ve sayfa "izin verilmiyor" verir.
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

        // 6) Denetim günlüğü
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
            'success' => true,
            'message' => 'Güncelleme başarıyla tamamlandı.',
            'current_version' => $currentVersion,
            'version' => $newVersion,
            'backup' => $backupFile ? basename($backupFile) : '',
            'completed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Mevcut eklenti klasörünü zip olarak yedekler.
     *
     * @param string $pluginDir
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
            'success' => false,
            'message' => $message,
            'current_version' => $currentVersion,
            'version' => $newVersion,
            'backup' => $backupFile ? basename($backupFile) : '',
            'completed_at' => date('Y-m-d H:i:s'),
        ];
    }
}
