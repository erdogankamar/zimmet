<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tutanak cihaz satırı (snapshot)
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Bir tutanağa ait cihaz satırı.
 *
 * Tutanak üretildiği anda cihazın seri no / envanter no / durum bilgisi
 * buraya kopyalanır (snapshot). Sonradan GLPI'de varlık değişse bile
 * imzalanmış tutanak değişmez — ISO 27001 bütünlük gereği.
 *
 * Manuel (serbest) satırlar: itemtype = NULL, items_id = 0, is_manual = 1.
 */
class PluginZimmetDocumentItem extends CommonDBChild
{
    public static $itemtype = 'PluginZimmetDocument';
    public static $items_id = 'plugin_zimmet_documents_id';

    public $dohistory = true;

    public static $rightname = 'plugin_zimmet_document';

    public static function getTypeName($nb = 0)
    {
        return _n('Equipment line', 'Equipment lines', $nb, 'zimmet');
    }

    /**
     * Bir tutanağa ait tüm cihaz satırlarını sıralı getirir.
     *
     * @param integer $documents_id
     *
     * @return array
     */
    public static function getForDocument($documents_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        $rows = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_zimmet_documents_id' => $documents_id],
            'ORDER' => ['line_order ASC', 'id ASC'],
        ]);
        foreach ($iterator as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Bir varlık satırını snapshot dizisine dönüştürür.
     *
     * @param array   $asset       getUserAssets() çıktısındaki satır
     * @param integer $documents_id
     * @param integer $order
     * @param float   $quantity
     * @param string  $unit
     *
     * @return array  insert için hazır dizi
     */
    public static function buildSnapshot(
        array $asset,
        $documents_id,
        $order = 0,
        $quantity = 1,
        $unit = 'Adet'
    ) {
        return [
            'plugin_zimmet_documents_id' => $documents_id,
            'itemtype'    => $asset['itemtype'] ?? null,
            'items_id'    => (int) ($asset['items_id'] ?? 0),
            'is_manual'   => empty($asset['itemtype']) ? 1 : 0,
            'item_name'   => $asset['item_name'] ?? '',
            'serial'      => $asset['serial'] ?? '',
            'otherserial' => $asset['otherserial'] ?? '',
            'state_name'  => $asset['state_name'] ?? '',
            'quantity'    => $quantity,
            'unit'        => $unit,
            'line_order'  => $order,
        ];
    }
}
