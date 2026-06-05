<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Şablon listesi
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_config', UPDATE);

// Varsayılan şablonları (yeniden) oluştur
if (isset($_GET['seed'])) {
    /** @var DBmysql $DB */
    global $DB;

    if (!$DB->tableExists(PluginZimmetTemplate::getTable())) {
        Session::addMessageAfterRedirect(
            'Şablon tablosu bulunamadı. Eklenti kurulumunu veya güncelleme paketini yeniden uygulayın.',
            false,
            ERROR
        );
    } else {
        $n = PluginZimmetTemplate::createDefaultTemplates();
        if ($n > 0) {
            Session::addMessageAfterRedirect(
                sprintf('%d varsayılan şablon oluşturuldu.', $n)
            );
        } elseif (countElementsInTable(PluginZimmetTemplate::getTable()) > 0) {
            Session::addMessageAfterRedirect('Varsayılan şablonlar zaten mevcut. Yeni kayıt oluşturulmadı.');
        } else {
            Session::addMessageAfterRedirect(
                'Varsayılan şablonlar oluşturulamadı. Veritabanı hatası: ' . $DB->error(),
                false,
                ERROR
            );
        }
    }
    Html::redirect(Plugin::getWebDir('zimmet') . '/front/template.php');
}

Html::header(
    PluginZimmetTemplate::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginZimmetMenu',
    'template'
);

PluginZimmetMenu::showNavHeader('template');

// Liste boşsa: varsayılan şablonları tek tıkla oluşturma kartı
if (countElementsInTable(PluginZimmetTemplate::getTable()) == 0) {
    $seedUrl = Plugin::getWebDir('zimmet') . '/front/template.php?seed=1';
    echo "<div class='alert alert-info' style='margin:0 0 14px'>";
    echo "<h4><i class='ti ti-info-circle'></i> Şablon tanımı bulunmuyor</h4>";
    echo "<p>PDF üretimi için en az bir aktif şablon gerekir. Varsayılan şablonları oluşturabilir veya Ekle düğmesiyle kuruma özel şablon tanımlayabilirsiniz.</p>";
    echo "<a class='btn btn-primary' href='" . htmlspecialchars($seedUrl) . "'>"
        . "<i class='ti ti-wand'></i> Varsayılan şablonları oluştur</a>";
    echo "</div>";
}

/** @var DBmysql $DB */
global $DB;

$types = PluginZimmetTemplate::getDocTypes();
$formUrl = Plugin::getWebDir('zimmet') . '/front/template.form.php';
$rows = $DB->request([
    'FROM'  => PluginZimmetTemplate::getTable(),
    'ORDER' => [
        PluginZimmetTemplate::getTable() . '.entities_id ASC',
        PluginZimmetTemplate::getTable() . '.doc_type ASC',
        PluginZimmetTemplate::getTable() . '.is_default DESC',
        PluginZimmetTemplate::getTable() . '.name ASC',
    ],
]);

$total = is_countable($rows) ? count($rows) : 0;

echo "<div class='card zimmet-template'>";
echo "<div class='zimmet-list-head'>";
echo "<div class='zimmet-list-title'><i class='ti ti-template'></i> Şablon Listesi</div>";
echo "<div class='zimmet-list-count'>" . (int) $total . " kayıt</div>";
echo "</div>";
echo "<div class='table-responsive'>";
echo "<table class='tab_cadre_fixehov zimmet-document-table'>";
echo "<colgroup>";
echo "<col class='zimmet-template-col-seq'>";
echo "<col class='zimmet-template-col-name'>";
echo "<col class='zimmet-template-col-type'>";
echo "<col class='zimmet-template-col-entity'>";
echo "<col class='zimmet-template-col-default'>";
echo "<col class='zimmet-template-col-docno'>";
echo "<col class='zimmet-template-col-revision'>";
echo "<col class='zimmet-template-col-revdate'>";
echo "<col class='zimmet-template-col-created'>";
echo "<col class='zimmet-template-col-updated'>";
echo "<col class='zimmet-template-col-active'>";
echo "</colgroup>";
echo "<thead>";
echo "<tr>";
echo "<th class='zimmet-template-seq'>Sıra</th>";
echo "<th class='zimmet-template-name'>Ad</th>";
echo "<th class='zimmet-template-type'>Belge tipi</th>";
echo "<th class='zimmet-template-entity'>Birim</th>";
echo "<th class='zimmet-template-default'>Varsayılan</th>";
echo "<th class='zimmet-template-docno'>Doküman No</th>";
echo "<th class='zimmet-template-revision'>Revizyon</th>";
echo "<th class='zimmet-template-revdate'>Revizyon tarihi</th>";
echo "<th class='zimmet-template-created'>Oluşturma tarihi</th>";
echo "<th class='zimmet-template-updated'>Güncelleme tarihi</th>";
echo "<th class='zimmet-template-active'>Etkin</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$count = 0;
foreach ($rows as $row) {
    $count++;
    $id = (int) $row['id'];
    $entity_id = (int) ($row['entities_id'] ?? 0);
    $entity = $entity_id > 0
        ? html_entity_decode(Dropdown::getDropdownName('glpi_entities', $entity_id), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : __('Root entity');
    $doc_type = $types[$row['doc_type']] ?? $row['doc_type'];
    $editUrl = $formUrl . '?id=' . $id;
    $isDefault = !empty($row['is_default']);
    $isActive = !empty($row['is_active']);

    echo "<tr>";
    echo "<td class='zimmet-template-seq'>" . (int) $count . "</td>";
    echo "<td class='zimmet-template-name'><a href='" . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . "'>"
        . htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') . "</a></td>";
    echo "<td class='zimmet-template-type'>" . htmlspecialchars($doc_type, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='zimmet-template-entity'>" . htmlspecialchars($entity, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='zimmet-template-default'>";
    echo $isDefault
        ? "<span class='badge zimmet-badge-yes'>Evet</span>"
        : "<span class='badge zimmet-badge-no'>Hayır</span>";
    echo "</td>";
    echo "<td class='zimmet-template-docno'>" . htmlspecialchars($row['document_no'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='zimmet-template-revision'>" . htmlspecialchars($row['revision'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='zimmet-template-revdate'>" . (!empty($row['revision_date']) ? Html::convDate($row['revision_date']) : '') . "</td>";
    echo "<td class='zimmet-template-created'>" . (!empty($row['date_creation']) ? Html::convDate($row['date_creation']) : '') . "</td>";
    echo "<td class='zimmet-template-updated'>" . (!empty($row['date_mod']) ? Html::convDate($row['date_mod']) : '') . "</td>";
    echo "<td class='zimmet-template-active'>";
    echo $isActive
        ? "<span class='badge zimmet-badge-yes'>Evet</span>"
        : "<span class='badge zimmet-badge-no'>Hayır</span>";
    echo "</td>";
    echo "</tr>";
}

if ($count === 0) {
    echo "<tr><td colspan='11' class='center text-muted'>Kayıt bulunamadı</td></tr>";
}

echo "</tbody>";
echo "</table>";
echo "</div>";
echo "<div class='zimmet-list-footer'>";
echo "<div>" . ($total > 0 ? '1' : '0') . " - " . (int) $total . " / " . (int) $total . " kayıt görüntüleniyor</div>";
echo "<div class='zimmet-pager'></div>";
echo "</div>";
echo "</div>";

PluginZimmetMenu::closeApp();
Html::footer();
