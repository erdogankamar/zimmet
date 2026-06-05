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

        $icons = [
            'Computer'   => 'ti ti-device-desktop',
            'Monitor'    => 'ti ti-device-desktop-analytics',
            'Peripheral' => 'ti ti-devices',
            'Phone'      => 'ti ti-device-mobile',
            'Printer'    => 'ti ti-printer',
        ];

        echo "<form method='post' action='" . Plugin::getWebDir('zimmet') . "/front/config.php'>";
        echo "<div class='card'><div class='card-body'>";
        echo "<div class='zimmet-card-head'><i class='ti ti-settings'></i>"
            . "<h3>" . __('Zimmet configuration', 'zimmet') . "</h3></div>";

        // --- Varlık türleri ---
        echo "<div class='zimmet-config-row'>";
        echo "<div class='zimmet-config-label'>";
        echo "<span class='t'>" . __('Asset types to include', 'zimmet') . "</span>";
        echo "<span class='h'>Personel seçildiğinde bu türlerdeki zimmetli cihazlar otomatik listelenir.</span>";
        echo "</div>";
        echo "<div class='zimmet-config-control'><div class='zimmet-checkgrid'>";
        foreach ($all_types as $type => $label) {
            $checked = in_array($type, $selected, true) ? 'checked' : '';
            $icon = $icons[$type] ?? 'ti ti-box';
            echo "<label class='zimmet-check'>"
                . "<input type='checkbox' name='asset_types[]' value='" . $type . "' $checked>"
                . "<i class='" . $icon . "'></i><span>" . htmlspecialchars($label) . "</span></label>";
        }
        echo "</div></div>";
        echo "</div>";

        // --- PDF fontu ---
        echo "<div class='zimmet-config-row'>";
        echo "<div class='zimmet-config-label'>";
        echo "<span class='t'>" . __('PDF font (Unicode/Turkish)', 'zimmet') . "</span>";
        echo "<span class='h'>Türkçe karakterlerin sorunsuz görünmesi için Unicode yazı tipi.</span>";
        echo "</div>";
        echo "<div class='zimmet-config-control'>";
        Dropdown::showFromArray('pdf_font', [
            'dejavusans'      => 'DejaVu Sans (önerilen)',
            'dejavusansmono'  => 'DejaVu Sans Mono',
            'freesans'        => 'FreeSans',
        ], ['value' => $pdf_font, 'width' => '280px']);
        echo "</div>";
        echo "</div>";

        // --- Kaydet ---
        echo "<div class='zimmet-config-foot'>";
        echo "<button type='submit' name='update' class='btn btn-primary'>"
            . "<i class='ti ti-device-floppy'></i> " . _sx('button', 'Save') . "</button>";
        echo "</div>";

        echo "</div></div>";
        Html::closeForm();

        return true;
    }
}
