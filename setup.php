<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet (Asset Handover) plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * Zimmet
 *
 * Personele atanmış GLPI varlıklarından, ISO 27001 uyumlu
 * Zimmet Teslim Tutanağı ve Teslim-Tesellüm Tutanağı PDF'leri üretir.
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @author    Artsution
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

define('PLUGIN_ZIMMET_VERSION', '1.4.8');

// Desteklenen GLPI sürüm aralığı
define('PLUGIN_ZIMMET_MIN_GLPI', '10.0.0');
define('PLUGIN_ZIMMET_MAX_GLPI', '10.0.99');

/**
 * Plugin başlatma: hook'lar, menü, sınıf kayıtları
 *
 * @return void
 */
function plugin_init_zimmet()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // CSRF korumalı
    $PLUGIN_HOOKS['csrf_compliant']['zimmet'] = true;

    // Sınıf kayıtları
    Plugin::registerClass(
        'PluginZimmetProfile',
        ['addtabon' => ['Profile']]
    );
    Plugin::registerClass('PluginZimmetDocument');
    Plugin::registerClass('PluginZimmetTemplate');
    Plugin::registerClass('PluginZimmetConfig');

    // Üretilen tutanakları kullanıcı kartında göster
    Plugin::registerClass(
        'PluginZimmetDocument',
        ['addtabon' => ['User']]
    );

    $plugin = new Plugin();
    if (!$plugin->isActivated('zimmet')) {
        return;
    }

    plugin_zimmet_ensure_schema();

    $web_dir = '/' . Plugin::getWebDir('zimmet', false);

    // Ana menü girişi (Yönetim sekmesi altında)
    if (Session::haveRight('plugin_zimmet_document', READ)) {
        $PLUGIN_HOOKS['menu_toadd']['zimmet'] = [
            'management' => 'PluginZimmetMenu',
        ];
    }

    // Yapılandırma sayfası (sadece config yetkisi olanlar)
    if (Session::haveRight('plugin_zimmet_config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['zimmet'] = 'front/config.php';
    }

    // Profil silinince yetki temizliği
    $PLUGIN_HOOKS['pre_item_purge']['zimmet'] = [
        'Profile' => ['PluginZimmetProfile', 'cleanProfiles'],
    ];

    // CSS / JS
    $PLUGIN_HOOKS['add_css']['zimmet']        = 'css/zimmet.css';
    $PLUGIN_HOOKS['add_javascript']['zimmet'] = 'js/zimmet.js';
}

/**
 * Dosya tabanlı güncellemelerde eksik kolonları tamamlar.
 */
function plugin_zimmet_ensure_schema()
{
    /** @var DBmysql $DB */
    global $DB;

    $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

    if ($DB->tableExists('glpi_plugin_zimmet_documents')
        && !$DB->fieldExists('glpi_plugin_zimmet_documents', 'document_date')
    ) {
        $DB->doQuery(
            "ALTER TABLE `glpi_plugin_zimmet_documents`
             ADD `document_date` date DEFAULT NULL AFTER `revision`"
        );
    }

    if ($DB->tableExists('glpi_plugin_zimmet_documents')
        && !$DB->fieldExists('glpi_plugin_zimmet_documents', 'plugin_zimmet_templates_id')
    ) {
        $DB->doQuery(
            "ALTER TABLE `glpi_plugin_zimmet_documents`
             ADD `plugin_zimmet_templates_id` int {$default_key_sign} NOT NULL DEFAULT 0 AFTER `tech_users_id`"
        );
    }

    if ($DB->tableExists('glpi_plugin_zimmet_templates')
        && !$DB->fieldExists('glpi_plugin_zimmet_templates', 'is_default')
    ) {
        $DB->doQuery(
            "ALTER TABLE `glpi_plugin_zimmet_templates`
             ADD `is_default` tinyint NOT NULL DEFAULT 0 AFTER `is_active`"
        );
    }
}

/**
 * Plugin sürüm ve meta bilgileri
 *
 * @return array
 */
function plugin_version_zimmet()
{
    return [
        'name'           => 'Zimmet',
        'shortname'      => 'zimmet',
        'version'        => PLUGIN_ZIMMET_VERSION,
        'author'         => 'Artsution',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/erdogankamar/zimmet',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_ZIMMET_MIN_GLPI,
                'max' => PLUGIN_ZIMMET_MAX_GLPI,
            ],
            'php' => [
                'min' => '7.4',
            ],
        ],
    ];
}

/**
 * Kurulum ön koşulları
 *
 * @return boolean
 */
function plugin_zimmet_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_ZIMMET_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_ZIMMET_MAX_GLPI, 'gt')
    ) {
        echo sprintf(
            __('This plugin requires GLPI >= %1$s and < %2$s', 'zimmet'),
            PLUGIN_ZIMMET_MIN_GLPI,
            PLUGIN_ZIMMET_MAX_GLPI
        );
        return false;
    }

    return true;
}

/**
 * Yapılandırma kontrolü
 *
 * @param boolean $verbose
 *
 * @return boolean
 */
function plugin_zimmet_check_config($verbose = false)
{
    return true;
}
