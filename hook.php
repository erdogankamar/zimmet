<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — kurulum / kaldırma kancaları
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

/**
 * Plugin kurulumu: veritabanı tablolarını ve varsayılan kayıtları oluşturur.
 *
 * @return boolean
 */
function plugin_zimmet_install()
{
    /** @var DBmysql $DB */
    global $DB;

    $migration = new Migration(PLUGIN_ZIMMET_VERSION);

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // Üretilen PDF'lerin geçici klasörü
    if (!file_exists(GLPI_PLUGIN_DOC_DIR . '/zimmet')) {
        mkdir(GLPI_PLUGIN_DOC_DIR . '/zimmet', 0755, true);
    }

    // 1) Tutanak başlık tablosu --------------------------------------------
    if (!$DB->tableExists('glpi_plugin_zimmet_documents')) {
        $query = "CREATE TABLE `glpi_plugin_zimmet_documents` (
            `id`               int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name`             varchar(255)          DEFAULT NULL,
            `doc_type`         varchar(20)           NOT NULL DEFAULT 'zimmet',
            `entities_id`      int {$default_key_sign} NOT NULL DEFAULT 0,
            `is_recursive`     tinyint               NOT NULL DEFAULT 0,
            `users_id`         int {$default_key_sign} NOT NULL DEFAULT 0,
            `tech_users_id`    int {$default_key_sign} NOT NULL DEFAULT 0,
            `plugin_zimmet_templates_id` int {$default_key_sign} NOT NULL DEFAULT 0,
            `document_no`      varchar(60)           DEFAULT NULL,
            `revision`         varchar(20)           DEFAULT NULL,
            `document_date`    date                  DEFAULT NULL,
            `status`           varchar(30)           NOT NULL DEFAULT 'draft',
            `pdf_hash`         varchar(128)          DEFAULT NULL,
            `documents_id`     int {$default_key_sign} NOT NULL DEFAULT 0,
            `generated_at`     timestamp             NULL DEFAULT NULL,
            `comment`          text                  DEFAULT NULL,
            `date_creation`    timestamp             NULL DEFAULT NULL,
            `date_mod`         timestamp             NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `doc_type` (`doc_type`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `tech_users_id` (`tech_users_id`),
            KEY `status` (`status`),
            KEY `documents_id` (`documents_id`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die('zimmet: documents tablosu oluşturulamadı — ' . $DB->error());
    }

    // 2) Tutanak cihaz satırları (snapshot) --------------------------------
    if (!$DB->tableExists('glpi_plugin_zimmet_documentitems')) {
        $query = "CREATE TABLE `glpi_plugin_zimmet_documentitems` (
            `id`               int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `plugin_zimmet_documents_id` int {$default_key_sign} NOT NULL DEFAULT 0,
            `itemtype`         varchar(100)          DEFAULT NULL,
            `items_id`         int {$default_key_sign} NOT NULL DEFAULT 0,
            `is_manual`        tinyint               NOT NULL DEFAULT 0,
            `item_name`        varchar(255)          DEFAULT NULL,
            `serial`           varchar(255)          DEFAULT NULL,
            `otherserial`      varchar(255)          DEFAULT NULL,
            `state_name`       varchar(255)          DEFAULT NULL,
            `quantity`         decimal(10,2)         NOT NULL DEFAULT 1,
            `unit`             varchar(40)           DEFAULT 'Adet',
            `line_order`       int                   NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `plugin_zimmet_documents_id` (`plugin_zimmet_documents_id`),
            KEY `item` (`itemtype`,`items_id`),
            KEY `is_manual` (`is_manual`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die('zimmet: documentitems tablosu oluşturulamadı — ' . $DB->error());
    }

    // 3) Kurum bazlı şablonlar ---------------------------------------------
    if (!$DB->tableExists('glpi_plugin_zimmet_templates')) {
        $query = "CREATE TABLE `glpi_plugin_zimmet_templates` (
            `id`               int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name`             varchar(255)          DEFAULT NULL,
            `entities_id`      int {$default_key_sign} NOT NULL DEFAULT 0,
            `is_recursive`     tinyint               NOT NULL DEFAULT 1,
            `doc_type`         varchar(20)           NOT NULL DEFAULT 'zimmet',
            `header_title`     varchar(255)          DEFAULT NULL,
            `header_subtitle`  varchar(255)          DEFAULT NULL,
            `logo_path`        varchar(255)          DEFAULT NULL,
            `document_no`      varchar(60)           DEFAULT NULL,
            `revision`         varchar(20)           DEFAULT NULL,
            `revision_date`    date                  DEFAULT NULL,
            `commitment_text`  longtext              DEFAULT NULL,
            `is_active`        tinyint               NOT NULL DEFAULT 1,
            `is_default`       tinyint               NOT NULL DEFAULT 0,
            `date_creation`    timestamp             NULL DEFAULT NULL,
            `date_mod`         timestamp             NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `doc_type` (`doc_type`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die('zimmet: templates tablosu oluşturulamadı — ' . $DB->error());
    }

    // 4) Genel yapılandırma ------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_zimmet_configs')) {
        $query = "CREATE TABLE `glpi_plugin_zimmet_configs` (
            `id`               int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name`             varchar(150)          DEFAULT NULL,
            `value`            text                  DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die('zimmet: configs tablosu oluşturulamadı — ' . $DB->error());

        $DB->insert('glpi_plugin_zimmet_configs', [
            'name'  => 'asset_types',
            'value' => 'Computer,Monitor,Peripheral,Phone,Printer',
        ]);
        $DB->insert('glpi_plugin_zimmet_configs', [
            'name'  => 'pdf_font',
            'value' => 'dejavusans',
        ]);
    }

    // Yetkiler ve varsayılan şablonlar
    PluginZimmetProfile::initProfile();
    PluginZimmetTemplate::createDefaultTemplates();

    $migration->executeMigration();

    return true;
}

/**
 * Plugin kaldırma: tüm tabloları ve yetkileri siler.
 *
 * @return boolean
 */
function plugin_zimmet_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    $tables = [
        'glpi_plugin_zimmet_documents',
        'glpi_plugin_zimmet_documentitems',
        'glpi_plugin_zimmet_templates',
        'glpi_plugin_zimmet_configs',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`")
                or die("zimmet: $table silinemedi — " . $DB->error());
        }
    }

    // Profil yetkilerini temizle
    PluginZimmetProfile::removeRights();

    return true;
}
