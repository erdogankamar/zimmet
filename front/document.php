<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tutanak listesi
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_document', READ);

/** @var DBmysql $DB */
global $DB;

$web = Plugin::getWebDir('zimmet');
$table = PluginZimmetDocument::getTable();
$lineTable = PluginZimmetDocumentItem::getTable();
$statuses = PluginZimmetDocument::getStatuses();
$docTypes = PluginZimmetTemplate::getDocTypes();

function zimmet_document_safeFilename($name)
{
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '_', $name);
    $name = preg_replace('/\s+/u', '_', trim($name));
    $name = trim($name, '._-');

    return $name !== '' ? $name : 'zimmet';
}

function zimmet_document_filterIdsForRead(array $ids)
{
    $filtered = [];
    $doc = new PluginZimmetDocument();
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && $doc->getFromDB($id) && $doc->canViewItem()) {
            $filtered[] = $id;
        }
    }

    return array_values(array_unique($filtered));
}

function zimmet_document_filterIdsForAction(array $ids, $right)
{
    $filtered = [];
    $doc = new PluginZimmetDocument();
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && $doc->getFromDB($id) && $doc->can($id, $right)) {
            $filtered[] = $id;
        }
    }

    return array_values(array_unique($filtered));
}

function zimmet_document_redirectToList($web)
{
    $returnUrl = (string) ($_POST['return_url'] ?? '');
    if ($returnUrl !== '' && preg_match('~^/plugins/zimmet/front/document\.php(?:\?.*)?$~', $returnUrl)) {
        Html::redirect($returnUrl);
    }

    Html::redirect($web . '/front/document.php');
}

function zimmet_document_archiveIds(array $ids, $web)
{
    Session::checkRight('plugin_zimmet_document', UPDATE);
    $ids = zimmet_document_filterIdsForAction($ids, UPDATE);
    if (empty($ids)) {
        Session::addMessageAfterRedirect('İşlem yapılabilecek tutanak seçilmelidir.', false, ERROR);
        zimmet_document_redirectToList($web);
    }

    $success = 0;
    $failed = 0;
    foreach ($ids as $id) {
        if (PluginZimmetArchive::archive((int) $id, true)) {
            $success++;
        } else {
            $failed++;
        }
    }

    if ($success > 0) {
        Session::addMessageAfterRedirect(
            sprintf('%d tutanak arşive alındı. PDF bütünlük izleri kaydedildi.', $success)
        );
    }
    if ($failed > 0) {
        Session::addMessageAfterRedirect(
            sprintf('%d tutanak arşivlenemedi. PDF üretimi ve dosya izinlerini kontrol edin.', $failed),
            false,
            ERROR
        );
    }

    zimmet_document_redirectToList($web);
}

function zimmet_document_changeStatusIds(array $ids, $status, array $statuses, $web)
{
    Session::checkRight('plugin_zimmet_document', UPDATE);
    if (!isset($statuses[$status])) {
        Session::addMessageAfterRedirect('Geçerli bir durum seçilmelidir.', false, ERROR);
        zimmet_document_redirectToList($web);
    }

    $ids = zimmet_document_filterIdsForAction($ids, UPDATE);
    if (empty($ids)) {
        Session::addMessageAfterRedirect('İşlem yapılabilecek tutanak seçilmelidir.', false, ERROR);
        zimmet_document_redirectToList($web);
    }

    $doc = new PluginZimmetDocument();
    $success = 0;
    $failed = 0;
    foreach ($ids as $id) {
        if ($doc->update(['id' => (int) $id, 'status' => $status])) {
            $success++;
        } else {
            $failed++;
        }
    }

    if ($success > 0) {
        Session::addMessageAfterRedirect(
            sprintf('%d tutanağın durumu "%s" olarak güncellendi.', $success, $statuses[$status])
        );
    }
    if ($failed > 0) {
        Session::addMessageAfterRedirect(
            sprintf('%d tutanak için durum güncellenemedi.', $failed),
            false,
            ERROR
        );
    }

    zimmet_document_redirectToList($web);
}

function zimmet_document_deleteIds(array $ids, $web)
{
    Session::checkRight('plugin_zimmet_document', PURGE);
    $ids = zimmet_document_filterIdsForAction($ids, PURGE);
    if (empty($ids)) {
        Session::addMessageAfterRedirect('Silinebilecek tutanak seçilmelidir.', false, ERROR);
        zimmet_document_redirectToList($web);
    }

    $doc = new PluginZimmetDocument();
    $success = 0;
    $failed = 0;
    foreach ($ids as $id) {
        if ($doc->delete(['id' => (int) $id], true)) {
            $success++;
        } else {
            $failed++;
        }
    }

    if ($success > 0) {
        Session::addMessageAfterRedirect(sprintf('%d tutanak kalıcı olarak silindi.', $success));
    }
    if ($failed > 0) {
        Session::addMessageAfterRedirect(
            sprintf('%d tutanak silinemedi. Yetki veya bağlı kayıtları kontrol edin.', $failed),
            false,
            ERROR
        );
    }

    zimmet_document_redirectToList($web);
}

function zimmet_document_sendCombinedPdf(array $ids)
{
    $ids = zimmet_document_filterIdsForRead($ids);
    if (empty($ids)) {
        Session::addMessageAfterRedirect('Toplu işlem için geçerli tutanak seçilmelidir.', false, ERROR);
        Html::redirect(Plugin::getWebDir('zimmet') . '/front/document.php');
    }

    $content = PluginZimmetDocument::generatePdf($ids);
    if ($content === false) {
        Session::addMessageAfterRedirect('PDF üretimi tamamlanamadı. Şablon ve ekipman kayıtlarını kontrol edin.', false, ERROR);
        Html::redirect(Plugin::getWebDir('zimmet') . '/front/document.php');
    }

    $filename = 'zimmet_secili_' . date('Ymd_His') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

function zimmet_document_sendZip(array $ids)
{
    $ids = zimmet_document_filterIdsForRead($ids);
    if (empty($ids)) {
        Session::addMessageAfterRedirect('Toplu işlem için geçerli tutanak seçilmelidir.', false, ERROR);
        Html::redirect(Plugin::getWebDir('zimmet') . '/front/document.php');
    }
    if (!class_exists('ZipArchive')) {
        Session::addMessageAfterRedirect('Toplu indirme hazırlanamadı. Sunucuda PHP ZipArchive eklentisi etkin olmalıdır.', false, ERROR);
        Html::redirect(Plugin::getWebDir('zimmet') . '/front/document.php');
    }
    if (!is_dir(GLPI_TMP_DIR)) {
        mkdir(GLPI_TMP_DIR, 0755, true);
    }

    $tmpFile = tempnam(GLPI_TMP_DIR, 'zimmet_secili_');
    $zip = new ZipArchive();
    if ($tmpFile === false || $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        if ($tmpFile) {
            @unlink($tmpFile);
        }
        Session::addMessageAfterRedirect('Toplu indirme paketi oluşturulamadı.', false, ERROR);
        Html::redirect(Plugin::getWebDir('zimmet') . '/front/document.php');
    }

    $doc = new PluginZimmetDocument();
    $usedNames = [];
    $added = 0;
    foreach ($ids as $id) {
        $content = PluginZimmetDocument::generatePdf([(int) $id]);
        if ($content === false) {
            continue;
        }
        $baseName = 'zimmet_' . (int) $id;
        if ($doc->getFromDB((int) $id)) {
            $baseName = zimmet_document_safeFilename(($doc->fields['document_no'] ?? '') . '_' . ($doc->fields['name'] ?? ('zimmet_' . $id)));
        }
        $filename = $baseName . '.pdf';
        $suffix = 2;
        while (isset($usedNames[$filename])) {
            $filename = $baseName . '_' . $suffix++ . '.pdf';
        }
        $usedNames[$filename] = true;
        $zip->addFromString($filename, $content);
        $added++;
    }
    $zip->close();

    if ($added === 0) {
        @unlink($tmpFile);
        Session::addMessageAfterRedirect('Seçili tutanaklar için PDF üretimi tamamlanamadı.', false, ERROR);
        Html::redirect(Plugin::getWebDir('zimmet') . '/front/document.php');
    }

    $filename = 'zimmet_secili_' . date('Ymd_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

if (isset($_POST['bulk_action'])) {
    $action = (string) $_POST['bulk_action'];
    $ids = $_POST['ids'] ?? [];
    if (!empty($_POST['single_id'])) {
        $ids = [(int) $_POST['single_id']];
    }

    if ($action === 'combined_pdf') {
        Session::checkRight('plugin_zimmet_document', READ);
        zimmet_document_sendCombinedPdf($ids);
    }
    if ($action === 'zip') {
        Session::checkRight('plugin_zimmet_document', READ);
        zimmet_document_sendZip($ids);
    }
    if ($action === 'archive') {
        zimmet_document_archiveIds($ids, $web);
    }
    if ($action === 'change_status') {
        zimmet_document_changeStatusIds($ids, (string) ($_POST['bulk_status'] ?? ''), $statuses, $web);
    }
    if ($action === 'delete') {
        zimmet_document_deleteIds($ids, $web);
    }
    Session::addMessageAfterRedirect('Geçersiz toplu işlem seçildi.', false, ERROR);
    zimmet_document_redirectToList($web);
}

$q = trim((string) ($_GET['q'] ?? ''));
$docType = (string) ($_GET['doc_type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$entitiesId = isset($_GET['entities_id']) && (int) $_GET['entities_id'] >= 0
    ? (int) $_GET['entities_id']
    : -1;
$start = max(0, (int) ($_GET['start'] ?? 0));
$limit = max(1, (int) ($_GET['limit'] ?? ($_SESSION['glpilist_limit'] ?? 15)));

$where = [];
if ($DB->fieldExists($table, 'is_deleted')) {
    $where[] = "d.is_deleted = 0";
}
$activeEntities = array_map('intval', $_SESSION['glpiactiveentities'] ?? []);
if (!empty($activeEntities)) {
    $where[] = "d.entities_id IN (" . implode(',', $activeEntities) . ")";
}
if ($docType !== '' && isset($docTypes[$docType])) {
    $where[] = "d.doc_type = '" . $DB->escape($docType) . "'";
}
if ($status !== '' && isset($statuses[$status])) {
    $where[] = "d.status = '" . $DB->escape($status) . "'";
}
if ($entitiesId >= 0) {
    $where[] = "d.entities_id = " . $entitiesId;
}
if ($q !== '') {
    $like = "'%" . $DB->escape($q) . "%'";
    $where[] = "("
        . "d.name LIKE $like OR "
        . "d.document_no LIKE $like OR "
        . "u.name LIKE $like OR "
        . "u.realname LIKE $like OR "
        . "u.firstname LIKE $like"
        . ")";
}

$whereSql = !empty($where) ? implode(' AND ', $where) : '1=1';

$countSql = "
    SELECT COUNT(*) AS cnt
    FROM `$table` d
    LEFT JOIN `glpi_users` u ON u.id = d.users_id
    WHERE $whereSql
";
$total = 0;
$countRes = $DB->query($countSql);
if ($countRes && ($countRow = $DB->fetchAssoc($countRes))) {
    $total = (int) $countRow['cnt'];
}
if ($start >= $total && $total > 0) {
    $start = max(0, $total - ($total % $limit ?: $limit));
}

$sql = "
    SELECT
        d.id,
        d.name,
        d.document_no,
        d.doc_type,
        d.status,
        d.document_date,
        d.entities_id,
        u.name AS user_name,
        u.realname AS user_realname,
        u.firstname AS user_firstname,
        e.completename AS entity_name,
        COUNT(li.id) AS equipment_count
    FROM `$table` d
    LEFT JOIN `glpi_users` u ON u.id = d.users_id
    LEFT JOIN `glpi_entities` e ON e.id = d.entities_id
    LEFT JOIN `$lineTable` li ON li.plugin_zimmet_documents_id = d.id
    WHERE $whereSql
    GROUP BY d.id
    ORDER BY d.id DESC
    LIMIT $start, $limit
";

$rows = [];
$res = $DB->query($sql);
if ($res) {
    while ($row = $DB->fetchAssoc($res)) {
        $rows[] = $row;
    }
}

Html::header(
    PluginZimmetDocument::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginZimmetMenu',
    'document'
);

PluginZimmetMenu::showNavHeader('document');

echo "<div class='zimmet-document-list'>";

echo "<div class='card mb-3 zimmet-filter-card'><div class='card-body'>";
echo "<form method='get' action='" . htmlspecialchars($web . '/front/document.php', ENT_QUOTES, 'UTF-8') . "'>";
echo "<div class='zimmet-filter-grid zimmet-filter-grid-wide'>";
echo "<div class='zimmet-filter-field zimmet-filter-search'><label>Arama</label>";
echo "<input type='text' name='q' class='form-control' value='" . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . "' "
    . "placeholder='Fiş no veya personel ara'></div>";

echo "<div class='zimmet-filter-field'><label>Belge tipi</label>";
Dropdown::showFromArray('doc_type', ['' => 'Tümü'] + $docTypes, ['value' => $docType]);
echo "</div>";

echo "<div class='zimmet-filter-field'><label>Durum</label>";
Dropdown::showFromArray('status', ['' => 'Tümü'] + $statuses, ['value' => $status]);
echo "</div>";

echo "<div class='zimmet-filter-field zimmet-filter-entity'><label>Birim</label>";
Entity::dropdown([
    'name'                => 'entities_id',
    'value'               => $entitiesId,
    'entity'              => $_SESSION['glpiactiveentities'],
    'display_emptychoice' => true,
    'emptylabel'          => 'Tüm birimler',
]);
echo "</div>";

echo "<div class='zimmet-filter-actions zimmet-filter-actions-primary'>";
echo "<button type='submit' class='btn btn-primary'><i class='ti ti-search'></i> Filtrele</button>";
echo "<a class='btn btn-outline-secondary' href='" . htmlspecialchars($web . '/front/document.php', ENT_QUOTES, 'UTF-8') . "'>"
    . "<i class='ti ti-refresh'></i> Temizle</a>";
echo "</div>";
echo "</div>";
echo "</form>";
echo "</div></div>";

$returnUrl = $_SERVER['REQUEST_URI'] ?? ($web . '/front/document.php');
echo "<form method='post' id='zimmet-document-bulk-form' action='" . htmlspecialchars($web . '/front/document.php', ENT_QUOTES, 'UTF-8') . "'>";
echo "<input type='hidden' name='bulk_action' id='zimmet-bulk-action' value=''>";
echo "<input type='hidden' name='single_id' id='zimmet-single-id' value=''>";
echo "<input type='hidden' name='return_url' value='" . htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') . "'>";
echo "<div class='card'>";
echo "<div class='zimmet-list-head'>";
echo "<div class='zimmet-list-title'><i class='ti ti-clipboard-check'></i> Tutanak Listesi</div>";
echo "<div class='zimmet-list-count'>" . (int) $total . " kayıt</div>";
echo "</div>";
echo "<div class='zimmet-bulkbar'>";
echo "<div class='zimmet-bulk-summary'><strong><span id='zimmet-selected-count'>0</span></strong> tutanak seçildi</div>";
echo "<div class='zimmet-bulk-actions'>";
echo "<div class='zimmet-bulk-group'>";
echo "<button type='button' data-action='zip' class='btn btn-outline-success btn-sm zimmet-bulk-trigger' "
    . "data-title='ZIP paketi oluştur' data-message='Seçili tutanaklar için ayrı PDF dosyaları hazırlanır ve tek ZIP paketi olarak indirilir.'>"
    . "<i class='ti ti-file-zip'></i> ZIP indir</button>";
echo "<button type='button' data-action='combined_pdf' data-target='_blank' class='btn btn-outline-primary btn-sm zimmet-bulk-trigger' "
    . "data-title='Birleşik PDF üret' data-message='Seçili tutanaklar kişi bazlı sayfa numarası korunarak tek PDF içinde oluşturulur.'>"
    . "<i class='ti ti-file-type-pdf'></i> Birleşik PDF</button>";
echo "</div>";
if (Session::haveRight('plugin_zimmet_document', UPDATE)) {
    echo "<div class='zimmet-bulk-group'>";
    echo "<button type='button' data-action='archive' class='btn btn-outline-success btn-sm zimmet-bulk-trigger' "
        . "data-title='Seçili tutanakları arşivle' data-message='Seçili tutanakların PDF çıktısı arşive alınır ve bütünlük izi kaydedilir.'>"
        . "<i class='ti ti-archive'></i> Arşivle</button>";
    echo "<div class='zimmet-status-action'>";
    Dropdown::showFromArray('bulk_status', $statuses, [
        'value' => 'printed',
        'width' => '160px',
    ]);
    echo "<button type='button' data-action='change_status' class='btn btn-outline-secondary btn-sm zimmet-bulk-trigger' "
        . "data-title='Durum değiştir' data-message='Seçili tutanakların durumu seçilen değerle güncellenecektir.'>"
        . "<i class='ti ti-arrows-exchange'></i> Durumu Güncelle</button>";
    echo "</div>";
    echo "</div>";
}
if (Session::haveRight('plugin_zimmet_document', PURGE)) {
    echo "<div class='zimmet-bulk-group zimmet-bulk-group-danger'>";
    echo "<button type='button' data-action='delete' class='btn btn-outline-danger btn-sm zimmet-bulk-trigger' "
        . "data-danger='1' data-title='Kalıcı silme onayı' data-message='Seçili tutanaklar kalıcı olarak silinecektir. Bu işlem geri alınamaz.'>"
        . "<i class='ti ti-trash'></i> Sil</button>";
    echo "</div>";
}
echo "</div>";
echo "</div>";
echo "<div class='table-responsive'>";
echo "<table class='tab_cadre_fixehov zimmet-document-table'>";
echo "<colgroup>";
echo "<col class='zimmet-col-check'>";
echo "<col class='zimmet-col-no'>";
echo "<col class='zimmet-col-person'>";
echo "<col class='zimmet-col-entity'>";
echo "<col class='zimmet-col-type'>";
echo "<col class='zimmet-col-status'>";
echo "<col class='zimmet-col-date'>";
echo "<col class='zimmet-col-count'>";
echo "<col class='zimmet-col-actions'>";
echo "</colgroup>";
echo "<thead><tr>";
echo "<th class='center zimmet-cell-check'><input type='checkbox' id='zimmet-check-all'></th>";
echo "<th class='zimmet-cell-no'>Fiş No</th>";
echo "<th class='zimmet-cell-person'>Personel</th>";
echo "<th class='zimmet-cell-entity'>Birim</th>";
echo "<th class='zimmet-cell-type'>Belge Tipi</th>";
echo "<th class='zimmet-cell-status'>Durum</th>";
echo "<th class='zimmet-cell-date'>Tarih</th>";
echo "<th class='center zimmet-cell-count'>Ekipman</th>";
echo "<th class='zimmet-actions-head'>İşlemler</th>";
echo "</tr></thead><tbody>";

if (empty($rows)) {
    echo "<tr class='tab_bg_1'><td colspan='9' class='center text-muted'>Kayıt bulunamadı.</td></tr>";
}

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $person = formatUserName(
        0,
        $row['user_name'] ?? '',
        $row['user_realname'] ?? '',
        $row['user_firstname'] ?? ''
    );
    $formUrl = $web . '/front/document.form.php?id=' . $id;
    $pdfUrl = $web . '/front/document.pdf.php?id=' . $id;

    echo "<tr class='tab_bg_1'>";
    echo "<td class='center zimmet-cell-check'><input type='checkbox' class='zimmet-row-check' name='ids[]' value='" . $id . "'></td>";
    echo "<td class='zimmet-cell-no'><a href='" . htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') . "'>"
        . htmlspecialchars($row['document_no'] ?: ('#' . $id), ENT_QUOTES, 'UTF-8') . "</a></td>";
    echo "<td class='zimmet-cell-person'>" . htmlspecialchars($person, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='zimmet-cell-entity'>" . htmlspecialchars(
        html_entity_decode((string) ($row['entity_name'] ?: '-'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ENT_QUOTES,
        'UTF-8'
    ) . "</td>";
    echo "<td class='zimmet-cell-type'>" . htmlspecialchars($docTypes[$row['doc_type']] ?? $row['doc_type'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='zimmet-cell-status'><span class='badge zimmet-badge-" . htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') . "'>"
        . htmlspecialchars($statuses[$row['status']] ?? $row['status'], ENT_QUOTES, 'UTF-8') . "</span></td>";
    echo "<td class='zimmet-cell-date'>" . htmlspecialchars(Html::convDate($row['document_date']), ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td class='center zimmet-cell-count'><span class='badge zimmet-count'>" . (int) $row['equipment_count'] . "</span></td>";
    echo "<td class='zimmet-actions-cell'><div class='zimmet-list-actions'>";
    echo "<a class='btn btn-sm btn-outline-primary' target='_blank' title='PDF görüntüle' href='"
        . htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-file-type-pdf'></i></a>";
    echo "<a class='btn btn-sm btn-outline-primary' title='Düzenle' href='"
        . htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-edit'></i></a>";
    echo "</div></td>";
    echo "</tr>";
}

echo "</tbody></table>";
echo "</div>";

$from = $total ? $start + 1 : 0;
$to = min($start + $limit, $total);
$baseParams = [
    'q'           => $q,
    'doc_type'    => $docType,
    'status'      => $status,
    'entities_id' => $entitiesId,
    'limit'       => $limit,
];
$baseUrl = $web . '/front/document.php?' . http_build_query(array_filter(
    $baseParams,
    fn($v) => $v !== '' && $v !== -1
));
$sep = strpos($baseUrl, '?') === false || substr($baseUrl, -1) === '?' ? '' : '&';
$prevStart = max(0, $start - $limit);
$nextStart = $start + $limit;

echo "<div class='zimmet-list-footer'>";
echo "<div>" . $from . " - " . $to . " / " . $total . " kayıt görüntüleniyor</div>";
echo "<div class='zimmet-pager'>";
if ($start > 0) {
    echo "<a class='btn btn-sm btn-outline-secondary' href='" . htmlspecialchars($baseUrl . $sep . 'start=' . $prevStart, ENT_QUOTES, 'UTF-8') . "'>"
        . "<i class='ti ti-chevron-left'></i> Önceki</a>";
}
if ($nextStart < $total) {
    echo "<a class='btn btn-sm btn-outline-secondary' href='" . htmlspecialchars($baseUrl . $sep . 'start=' . $nextStart, ENT_QUOTES, 'UTF-8') . "'>"
        . "Sonraki <i class='ti ti-chevron-right'></i></a>";
}
echo "</div>";
echo "</div>";

echo "</div>";
Html::closeForm();
echo "</div>";

echo Html::scriptBlock("
    $(function() {
        var pendingAction = null;
        var modal = $('#zimmet-bulk-confirm-modal');
        var form = $('#zimmet-document-bulk-form');

        function updateSelectedCount() {
            $('#zimmet-selected-count').text($('.zimmet-row-check:checked').length);
        }
        function selectedCount() {
            return $('.zimmet-row-check:checked').length;
        }
        function openModal(opts) {
            $('#zimmet-confirm-title').text(opts.title || 'İşlem onayı');
            $('#zimmet-confirm-message').text(opts.message || 'Seçili kayıtlar için işlem uygulanacaktır.');
            $('#zimmet-confirm-count').text(opts.count || 0);
            $('#zimmet-confirm-action-label').text(opts.actionLabel || 'Toplu işlem');
            $('#zimmet-confirm-submit')
                .toggleClass('btn-danger', !!opts.danger)
                .toggleClass('btn-primary', !opts.danger);
            modal.css('display', 'flex');
        }
        function closeModal() {
            pendingAction = null;
            modal.hide();
        }
        $('#zimmet-check-all').on('change', function() {
            $('.zimmet-row-check').prop('checked', this.checked);
            updateSelectedCount();
        });
        $('.zimmet-row-check').on('change', updateSelectedCount);
        $('.zimmet-bulk-trigger, .zimmet-row-action').on('click', function() {
            var btn = $(this);
            var singleId = btn.data('single-id') || '';
            var count = singleId ? 1 : selectedCount();
            if (count === 0) {
                pendingAction = null;
                openModal({
                    title: 'Seçim gerekli',
                    message: 'İşleme devam etmek için listeden en az bir tutanak seçin.',
                    count: 0,
                    actionLabel: 'İşlem başlatılmadı'
                });
                $('#zimmet-confirm-submit').hide();
                return;
            }
            $('#zimmet-confirm-submit').show();
            pendingAction = {
                action: btn.data('action'),
                target: btn.data('target') || '',
                singleId: singleId
            };
            openModal({
                title: btn.data('title'),
                message: btn.data('message'),
                count: count,
                actionLabel: btn.text().trim(),
                danger: btn.data('danger') == 1
            });
        });
        $('#zimmet-confirm-cancel').on('click', closeModal);
        $('#zimmet-confirm-submit').on('click', function() {
            if (!pendingAction || !pendingAction.action) {
                closeModal();
                return;
            }
            $('#zimmet-bulk-action').val(pendingAction.action);
            $('#zimmet-single-id').val(pendingAction.singleId || '');
            form.attr('target', pendingAction.target || '');
            form.trigger('submit');
        });
        modal.on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        updateSelectedCount();
    });
");

echo "<div id='zimmet-bulk-confirm-modal' class='zimmet-confirm-backdrop' role='dialog' aria-modal='true'>";
echo "<div class='zimmet-confirm-modal'>";
echo "<div class='zimmet-confirm-head'><h3><i class='ti ti-shield-check'></i> <span id='zimmet-confirm-title'>İşlem onayı</span></h3></div>";
echo "<div class='zimmet-confirm-body'>";
echo "<p id='zimmet-confirm-message'>Seçili kayıtlar için işlem uygulanacaktır.</p>";
echo "<ul class='zimmet-confirm-list'>";
echo "<li><span>Kayıt sayısı</span><span id='zimmet-confirm-count'>0</span></li>";
echo "<li><span>İşlem</span><span id='zimmet-confirm-action-label'>Toplu işlem</span></li>";
echo "<li><span>Onay durumu</span><span>Manuel onay gerekli</span></li>";
echo "</ul>";
echo "</div>";
echo "<div class='zimmet-confirm-foot'>";
echo "<button type='button' id='zimmet-confirm-cancel' class='btn btn-outline-secondary'>Vazgeç</button>";
echo "<button type='button' id='zimmet-confirm-submit' class='btn btn-primary'>İşlemi başlat</button>";
echo "</div></div></div>";

PluginZimmetMenu::closeApp();
Html::footer();
