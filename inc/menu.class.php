<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Menü tanımı
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
 * Yönetim menüsü altında Zimmet menüsünü ve alt girişlerini tanımlar.
 */
class PluginZimmetMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('Zimmet', 'zimmet');
    }

    public static function getIcon()
    {
        return 'ti ti-clipboard-check';
    }

    /**
     * Menüde gösterilecek isim.
     *
     * @return string
     */
    public static function getMenuName()
    {
        return self::getTypeName();
    }

    /**
     * Tüm plugin sayfalarının üstünde gösterilen sabit gezinme çubuğu.
     * GLPI menü "links" ikonlarına bağımlı kalmadan tüm bölümlere erişim sağlar.
     *
     * @param string $current  Aktif bölüm: document|generate|template|config
     *
     * @return void
     */
    public static function showNavHeader($current = '')
    {
        $web = '/' . Plugin::getWebDir('zimmet', false);

        // Her sayfa için ortak başlık (başlık / açıklama / ikon)
        $heads = [
            'document' => ['Tutanaklar', 'Zimmet ve teslim-tesellüm tutanaklarını yönetin', 'ti ti-clipboard-check'],
            'add'      => ['Yeni Tutanak', 'Personel seçip zimmet/iade tutanağı oluşturun', 'ti ti-file-plus'],
            'generate' => ['Toplu Üretim', 'Kurum/grup bazında çoklu tutanak üretin', 'ti ti-users-group'],
            'template' => ['Şablonlar', 'Kurum bazlı başlık, doküman no ve taahhüt metni', 'ti ti-template'],
            'config'   => ['Ayarlar', 'Varlık türleri, PDF yazı tipi ve güncelleme merkezi', 'ti ti-settings'],
        ];
        $head = $heads[$current]
            ?? ['Zimmet & Teslim-Tesellüm', 'Kurumsal zimmet yönetimi', 'ti ti-clipboard-check'];

        // Tüm sayfaları tek tasarım kapsamına al
        echo "<div class='zimmet-app'>";

        echo "<div class='zimmet-page-head'>";
        echo "<div class='zimmet-page-icon'><i class='" . $head[2] . "'></i></div>";
        echo "<div><h1>" . htmlspecialchars($head[0]) . "</h1>"
            . "<p>" . htmlspecialchars($head[1]) . "</p></div>";
        echo "</div>";

        $tabs = [];
        $tabs['document'] = [
            'label' => 'Tutanaklar',
            'icon'  => 'ti ti-list-details',
            'url'   => $web . '/front/document.php',
            'right' => ['plugin_zimmet_document', READ],
        ];
        $tabs['generate'] = [
            'label' => 'Toplu Üretim',
            'icon'  => 'ti ti-users-group',
            'url'   => $web . '/front/generate.php',
            'right' => ['plugin_zimmet_document', CREATE],
        ];
        $tabs['template'] = [
            'label' => 'Şablonlar',
            'icon'  => 'ti ti-template',
            'url'   => $web . '/front/template.php',
            'right' => ['plugin_zimmet_config', UPDATE],
        ];
        $tabs['config'] = [
            'label' => 'Ayarlar',
            'icon'  => 'ti ti-settings',
            'url'   => $web . '/front/config.php',
            'right' => ['plugin_zimmet_config', UPDATE],
        ];
        // Sağ üstteki birincil eylem, bulunulan bölüme göre değişir
        $newDoc = [
            'label' => 'Yeni Tutanak',
            'icon'  => 'ti ti-plus',
            'url'   => $web . '/front/document.form.php',
            'right' => ['plugin_zimmet_document', CREATE],
        ];
        $newTpl = [
            'label' => 'Şablon Ekle',
            'icon'  => 'ti ti-plus',
            'url'   => $web . '/front/template.form.php',
            'right' => ['plugin_zimmet_config', UPDATE],
        ];
        $primaryMap = [
            'document' => $newDoc,
            'add'      => $newDoc,
            'generate' => $newDoc,
            'template' => $newTpl,
        ];
        $primaryAction = $primaryMap[$current] ?? null;

        echo "<div class='zimmet-nav'>";
        echo "<div class='zimmet-nav-main'>";
        foreach ($tabs as $key => $tab) {
            if (!Session::haveRight($tab['right'][0], $tab['right'][1])) {
                continue;
            }
            $active = ($key === $current) ? ' is-active' : '';
            echo "<a class='zimmet-nav-link" . $active . "' href='" . htmlspecialchars($tab['url']) . "'>"
                . "<i class='" . $tab['icon'] . "'></i><span>" . $tab['label'] . "</span></a>";
        }
        echo "</div>";
        if ($primaryAction !== null
            && Session::haveRight($primaryAction['right'][0], $primaryAction['right'][1])
        ) {
            echo "<a class='zimmet-nav-link zimmet-nav-primary' href='"
                . htmlspecialchars($primaryAction['url']) . "'>"
                . "<i class='" . $primaryAction['icon'] . "'></i><span>"
                . $primaryAction['label'] . "</span></a>";
        }
        echo "</div>";
    }

    /**
     * .zimmet-app kapsayıcısını kapatır. Her sayfada Html::footer() öncesi
     * çağrılır.
     *
     * @return void
     */
    public static function closeApp()
    {
        echo "</div>";
    }

    /**
     * Menü içeriği: ana sayfa, ekleme ve alt menü bağlantıları.
     *
     * @return array
     */
    public static function getMenuContent()
    {
        $menu = [];
        $web  = Plugin::getWebDir('zimmet', false);

        if (!Session::haveRight('plugin_zimmet_document', READ)) {
            return false;
        }

        $menu['title'] = self::getMenuName();
        $menu['page']  = '/' . $web . '/front/document.php';
        $menu['icon']  = self::getIcon();

        // Üstteki "Ekle/Arama" butonları KULLANILMAZ; gezinme kendi üst
        // çubuğumuzla (showNavHeader) yapılır. Bu yüzden 'links' tanımlanmaz.

        // Alt menü girişleri yalnızca breadcrumb/erişim için (buton üretmez)
        $menu['options']['document'] = [
            'title' => PluginZimmetDocument::getTypeName(2),
            'page'  => '/' . $web . '/front/document.php',
        ];
        $menu['options']['generate'] = [
            'title' => __('Bulk generation', 'zimmet'),
            'page'  => '/' . $web . '/front/generate.php',
        ];
        $menu['options']['template'] = [
            'title' => PluginZimmetTemplate::getTypeName(2),
            'page'  => '/' . $web . '/front/template.php',
        ];

        return $menu;
    }
}
