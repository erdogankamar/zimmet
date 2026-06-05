<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Genel yapılandırma (anahtar/değer)
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
 * Plugin genel ayarları: zimmetlenebilir varlık türleri, PDF fontu vb.
 */
class PluginZimmetConfig extends CommonDBTM
{
    public static $rightname = 'plugin_zimmet_config';

    public static function getTypeName($nb = 0)
    {
        return __('Zimmet configuration', 'zimmet');
    }

    public static function getIcon()
    {
        return 'ti ti-settings';
    }

    /**
     * Bir ayar değerini getirir.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function getValue($name, $default = null)
    {
        /** @var DBmysql $DB */
        global $DB;

        $row = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['name' => $name],
            'LIMIT' => 1,
        ])->current();

        return $row['value'] ?? $default;
    }

    /**
     * Bir ayar değerini kaydeder (upsert).
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public static function setValue($name, $value)
    {
        /** @var DBmysql $DB */
        global $DB;

        if (countElementsInTable(self::getTable(), ['name' => $name])) {
            $DB->update(self::getTable(), ['value' => $value], ['name' => $name]);
        } else {
            $DB->insert(self::getTable(), ['name' => $name, 'value' => $value]);
        }
    }

    /**
     * Zimmetlenebilir varlık türleri (itemtype dizisi).
     *
     * @return array
     */
    public static function getAssetTypes()
    {
        $value = self::getValue('asset_types', 'Computer,Monitor,Peripheral,Phone,Printer');
        return array_filter(array_map('trim', explode(',', $value)));
    }

    /**
     * Yapılandırma formu.
     */
    public function showConfigForm()
    {
        if (!self::canUpdate()) {
            return false;
        }

        $asset_types = self::getValue('asset_types', 'Computer,Monitor,Peripheral,Phone,Printer');
        $pdf_font    = self::getValue('pdf_font', 'dejavusans');

        $all_types = [
            'Computer'   => Computer::getTypeName(1),
            'Monitor'    => Monitor::getTypeName(1),
            'Peripheral' => Peripheral::getTypeName(1),
            'Phone'      => Phone::getTypeName(1),
            'Printer'    => Printer::getTypeName(1),
        ];
        $selected = array_filter(array_map('trim', explode(',', $asset_types)));

        echo "<form method='post' action='" . Plugin::getWebDir('zimmet') . "/front/config.php'>";
        echo "<div class='card'><div class='card-body'>";
        echo "<h3>" . __('Zimmet configuration', 'zimmet') . "</h3>";

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><td>" . __('Asset types to include', 'zimmet') . "</td><td>";
        foreach ($all_types as $type => $label) {
            $checked = in_array($type, $selected, true) ? 'checked' : '';
            echo "<label class='d-block'><input type='checkbox' name='asset_types[]' value='"
                . $type . "' $checked> " . $label . "</label>";
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('PDF font (Unicode/Turkish)', 'zimmet') . "</td><td>";
        Dropdown::showFromArray('pdf_font', [
            'dejavusans'      => 'DejaVu Sans (önerilen)',
            'dejavusansmono'  => 'DejaVu Sans Mono',
            'freesans'        => 'FreeSans',
        ], ['value' => $pdf_font]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
        echo "<input type='submit' name='update' class='btn btn-primary' value='" . _sx('button', 'Save') . "'>";
        echo "</td></tr>";
        echo "</table>";
        echo "</div></div>";
        Html::closeForm();

        return true;
    }
}
