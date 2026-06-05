<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Arşivleme, bütünlük (SHA-256) ve denetim izi
 * -------------------------------------------------------------------------
 *
 * ISO 27001 (A.5.9 / A.5.11 / A.8.1) gereği üretilen tutanak PDF'i:
 *  - GLPI Document olarak kaydedilir,
 *  - ilgili personele ve tutanağa bağlanır (Document_Item),
 *  - SHA-256 özeti ve üretim zamanı tutanak kaydına işlenir,
 *  - olay denetim günlüğüne yazılır.
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginZimmetArchive
{
    /**
     * Bir tutanağı arşivler: PDF üret, hash al, GLPI Document oluştur,
     * personele bağla, tutanak kaydını güncelle ve günlüğe yaz.
     *
     * @param integer $document_id    Tutanak ID'si
     * @param boolean $mark_signed     Durumu "imzalı & arşivlendi" yap
     *
     * @return integer|false  Oluşturulan GLPI Document ID'si
     */
    public static function archive($document_id, $mark_signed = true)
    {
        $zdoc = new PluginZimmetDocument();
        if (!$zdoc->getFromDB($document_id)) {
            return false;
        }

        // 1) PDF üret
        $content = PluginZimmetDocument::generatePdf([$document_id]);
        if ($content === false) {
            return false;
        }

        // 2) Bütünlük özeti
        $hash = hash('sha256', $content);

        // 3) Geçici dosyaya yaz (GLPI taşıma mekanizması için)
        if (!is_dir(GLPI_TMP_DIR)) {
            mkdir(GLPI_TMP_DIR, 0755, true);
        }
        $tmpname = 'zimmet_' . $document_id . '_' . date('YmdHis') . '.pdf';
        $tmppath = GLPI_TMP_DIR . '/' . $tmpname;
        if (file_put_contents($tmppath, $content) === false) {
            return false;
        }

        // 4) GLPI Document oluştur
        $glpiDoc = new Document();
        $docID = $glpiDoc->add([
            'name'                    => $zdoc->fields['name'] ?: ('Zimmet #' . $document_id),
            'entities_id'             => $zdoc->fields['entities_id'],
            'is_recursive'            => 0,
            '_filename'               => [$tmpname],
            '_only_if_upload_succeed' => 1,
            'comment'                 => sprintf(
                __('Auto-generated handover record. SHA-256: %s', 'zimmet'),
                $hash
            ),
        ]);

        if (!$docID) {
            @unlink($tmppath);
            return false;
        }

        // 5) Personele bağla
        if ($zdoc->fields['users_id']) {
            $di = new Document_Item();
            $di->add([
                'documents_id' => $docID,
                'itemtype'     => 'User',
                'items_id'     => $zdoc->fields['users_id'],
                'entities_id'  => $zdoc->fields['entities_id'],
                'is_recursive' => 0,
            ]);
        }

        // 6) Tutanağın kendisine bağla (kullanıcı kartı + tutanak izlenebilirliği)
        $di2 = new Document_Item();
        $di2->add([
            'documents_id' => $docID,
            'itemtype'     => 'PluginZimmetDocument',
            'items_id'     => $document_id,
            'entities_id'  => $zdoc->fields['entities_id'],
            'is_recursive' => 0,
        ]);

        // 7) Tutanak kaydını güncelle (hash, doc bağı, zaman, durum)
        $update = [
            'id'           => $document_id,
            'pdf_hash'     => $hash,
            'documents_id' => $docID,
            'generated_at' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ];
        if ($mark_signed) {
            $update['status'] = 'signed_archived';
        }
        $zdoc->update($update);

        // 8) Denetim günlüğü
        Event::log(
            $document_id,
            'PluginZimmetDocument',
            4,
            'inventory',
            sprintf(
                __('%1$s archived handover record #%2$d (SHA-256: %3$s)', 'zimmet'),
                $_SESSION['glpiname'] ?? 'system',
                $document_id,
                substr($hash, 0, 16) . '…'
            )
        );

        return $docID;
    }

    /**
     * Arşivlenmiş bir PDF'in bütünlüğünü doğrular (hash karşılaştırması).
     *
     * @param integer $document_id
     *
     * @return boolean|null  true=geçerli, false=bozulmuş, null=arşiv yok
     */
    public static function verifyIntegrity($document_id)
    {
        $zdoc = new PluginZimmetDocument();
        if (!$zdoc->getFromDB($document_id) || empty($zdoc->fields['documents_id'])) {
            return null;
        }

        $glpiDoc = new Document();
        if (!$glpiDoc->getFromDB($zdoc->fields['documents_id'])) {
            return null;
        }

        $path = GLPI_DOC_DIR . '/' . $glpiDoc->fields['filepath'];
        if (!is_file($path)) {
            return null;
        }

        return hash('sha256', (string) file_get_contents($path)) === $zdoc->fields['pdf_hash'];
    }
}
