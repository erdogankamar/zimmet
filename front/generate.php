<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Toplu üretim sihirbazı
 * -------------------------------------------------------------------------
 *
 * Adım 1: Kurum + isteğe bağlı grup + belge tipi filtresi
 * Adım 2: Eşleşen personel listesi + çoklu seçim
 * Adım 3: Seçilenler için tutanak kayıtları oluştur + indirme bağlantılarını hazırla
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_document', CREATE);

$web = Plugin::getWebDir('zimmet');

/**
 * Verilen kurumda yetkilendirilmiş/atanmış ve üzerinde zimmetli cihaz olan
 * personelleri cihaz sayılarıyla birlikte döndürür.
 */
function zimmet_findCandidateUsers($entities_id, $groups_id = 0)
{
    /** @var DBmysql $DB */
    global $DB;

    $candidateUsers = [];

    if ($DB->tableExists('glpi_profiles_users')) {
        foreach ($DB->request([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_profiles_users',
            'WHERE'  => [
                'entities_id' => (int) $entities_id,
                'users_id'    => ['>', 0],
            ],
        ]) as $row) {
            $candidateUsers[(int) $row['users_id']] = true;
        }
    }

    $userWhere = [
        'entities_id' => (int) $entities_id,
        'id'          => ['>', 0],
    ];
    if ($DB->fieldExists('glpi_users', 'is_deleted')) {
        $userWhere['is_deleted'] = 0;
    }
    if ($DB->fieldExists('glpi_users', 'is_active')) {
        $userWhere['is_active'] = 1;
    }
    foreach ($DB->request([
        'SELECT' => 'id',
        'FROM'   => 'glpi_users',
        'WHERE'  => $userWhere,
    ]) as $row) {
        $candidateUsers[(int) $row['id']] = true;
    }

    if (empty($candidateUsers)) {
        return [];
    }

    // Grup filtresi isteğe bağlıdır; boş bırakılırsa tüm gruplar dahil edilir.
    if ($groups_id > 0) {
        $inGroup = [];
        foreach ($DB->request([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['groups_id' => (int) $groups_id],
        ]) as $g) {
            $inGroup[(int) $g['users_id']] = true;
        }
        $candidateUsers = array_intersect_key($candidateUsers, $inGroup);
    }

    if (empty($candidateUsers)) {
        return [];
    }

    $userCounts = [];
    foreach (array_keys($candidateUsers) as $uid) {
        $assets = PluginZimmetDocument::getUserAssets((int) $uid);
        if (!empty($assets)) {
            $userCounts[(int) $uid] = count($assets);
        }
    }

    $result = [];
    foreach (array_keys($userCounts) as $uid) {
        $info = PluginZimmetDocument::getUserInfo($uid);
        if (empty($info['fullname'])) {
            continue;
        }
        $result[] = [
            'users_id'   => $uid,
            'fullname'   => $info['fullname'],
            'department' => $info['title'],
            'asset_count' => $userCounts[$uid],
        ];
    }

    usort($result, fn($a, $b) => strcmp($a['fullname'], $b['fullname']));
    return $result;
}

/**
 * İndirme paketlerinde güvenli dosya adı üretir.
 */
function zimmet_safeFilename($name)
{
    $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '_', $name);
    $name = preg_replace('/\s+/u', '_', trim($name));
    $name = trim($name, '._-');

    return $name !== '' ? $name : 'zimmet';
}

/**
 * Oluşturulan tutanakları kişi bazlı ayrı PDF dosyaları olarak ZIP'e koyar.
 */
function zimmet_sendDocumentsZip(array $createdIds)
{
    if (!class_exists('ZipArchive')) {
        Session::addMessageAfterRedirect('Toplu indirme hazırlanamadı. Sunucuda PHP ZipArchive eklentisi etkin olmalıdır.', false, ERROR);
        Html::back();
    }

    if (!is_dir(GLPI_TMP_DIR)) {
        mkdir(GLPI_TMP_DIR, 0755, true);
    }

    $tmpFile = tempnam(GLPI_TMP_DIR, 'zimmet_toplu_');
    if ($tmpFile === false) {
        Session::addMessageAfterRedirect('Toplu indirme için geçici dosya oluşturulamadı.', false, ERROR);
        Html::back();
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpFile);
        Session::addMessageAfterRedirect('Toplu indirme paketi oluşturulamadı.', false, ERROR);
        Html::back();
    }

    $added = 0;
    $usedNames = [];
    foreach ($createdIds as $id) {
        $content = PluginZimmetDocument::generatePdf([(int) $id]);
        if ($content === false) {
            continue;
        }

        $doc = new PluginZimmetDocument();
        $baseName = 'zimmet_' . (int) $id;
        if ($doc->getFromDB((int) $id)) {
            $baseName = zimmet_safeFilename(($doc->fields['document_no'] ?? '') . '_' . ($doc->fields['name'] ?? ('zimmet_' . $id)));
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
        Session::addMessageAfterRedirect('Seçilen personeller için PDF üretimi tamamlanamadı. Şablon ve ekipman kayıtlarını kontrol edin.', false, ERROR);
        Html::back();
    }

    $filename = 'zimmet_toplu_' . date('Ymd_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

/**
 * Toplu üretim sonucunu oturumda saklar; çıktı linkleri bu kayıtları kullanır.
 */
function zimmet_storeBatch(array $createdIds, $doc_type)
{
    if (empty($_SESSION['plugin_zimmet_batches']) || !is_array($_SESSION['plugin_zimmet_batches'])) {
        $_SESSION['plugin_zimmet_batches'] = [];
    }

    $key = bin2hex(random_bytes(12));
    $_SESSION['plugin_zimmet_batches'][$key] = [
        'ids'        => array_values(array_map('intval', $createdIds)),
        'doc_type'   => (string) $doc_type,
        'created_at' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
    ];

    return $key;
}

/**
 * Oturumdaki toplu üretim sonucunu döndürür.
 */
function zimmet_getBatch($key)
{
    $key = (string) $key;
    if ($key === '' || empty($_SESSION['plugin_zimmet_batches'][$key])) {
        return null;
    }

    return $_SESSION['plugin_zimmet_batches'][$key];
}

/**
 * Oluşturulmuş kayıtları tek birleşik PDF olarak gönderir.
 */
function zimmet_sendCombinedPdf(array $createdIds)
{
    $content = PluginZimmetDocument::generatePdf($createdIds);
    if ($content === false) {
        Session::addMessageAfterRedirect('PDF üretimi tamamlanamadı. Şablon ve ekipman kayıtlarını kontrol edin.', false, ERROR);
        Html::back();
    }

    $filename = 'zimmet_toplu_' . date('Ymd_His') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

// =========================================================================
// İndirme: mevcut toplu üretim kayıtlarından çıktı alır, yeni kayıt oluşturmaz.
// =========================================================================
if (isset($_GET['download'], $_GET['batch'])) {
    $batch = zimmet_getBatch($_GET['batch']);
    if (empty($batch['ids'])) {
        Session::addMessageAfterRedirect('Toplu üretim sonucu bulunamadı. Lütfen personel listesinden yeniden üretim başlatın.', false, ERROR);
        Html::redirect($web . '/front/generate.php');
    }

    if ($_GET['download'] === 'combined') {
        zimmet_sendCombinedPdf($batch['ids']);
    }

    zimmet_sendDocumentsZip($batch['ids']);
}

// =========================================================================
// ADIM 3: Üretim
// =========================================================================
if (isset($_POST['generate'])) {
    $selected = $_POST['sel_users'] ?? [];
    $doc_type = $_POST['doc_type'] ?? 'zimmet';

    if (empty($selected)) {
        Session::addMessageAfterRedirect('Toplu üretim için en az bir personel seçilmelidir.', false, ERROR);
        Html::back();
    }

    $createdIds = [];
    foreach ($selected as $uid) {
        $id = PluginZimmetDocument::createForUser((int) $uid, $doc_type);
        if ($id) {
            $createdIds[] = $id;
        }
    }

    if (empty($createdIds)) {
        Session::addMessageAfterRedirect('Seçilen personeller için tutanak oluşturulamadı. Kişi ve zimmet kayıtlarını kontrol edin.', false, ERROR);
        Html::back();
    }

    $batchKey = zimmet_storeBatch($createdIds, $doc_type);
    Html::redirect($web . '/front/generate.php?batch=' . urlencode($batchKey));
}

// =========================================================================
// Sayfa başlığı (Adım 1 & 2)
// =========================================================================
Html::header(
    __('Bulk generation', 'zimmet'),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginZimmetMenu',
    'generate'
);

PluginZimmetMenu::showNavHeader('generate');

$entities_id = isset($_POST['entities_id']) ? (int) $_POST['entities_id'] : -1;
$groups_id   = (isset($_POST['groups_id']) && (int) $_POST['groups_id'] > 0)
    ? (int) $_POST['groups_id']
    : 0;
$doc_type    = $_POST['doc_type'] ?? 'zimmet';
$batchKey    = $_GET['batch'] ?? '';
$batch       = $batchKey !== '' ? zimmet_getBatch($batchKey) : null;

// ----- ADIM 1: Filtre formu (tek sıra) -----
echo "<div class='card'>";
echo "<div class='zimmet-card-head'><i class='ti ti-filter'></i><h3>Adım 1: Filtre</h3></div>";
echo "<div class='card-body'>";
echo "<form method='post' action='" . $web . "/front/generate.php'>";

echo "<div class='zimmet-genfilter'>";

echo "<div class='zimmet-filter-field'><label>" . Entity::getTypeName(1)
    . " <span class='red'>*</span></label>";
Entity::dropdown([
    'name'   => 'entities_id',
    'value'  => $entities_id >= 0 ? $entities_id : $_SESSION['glpiactive_entity'],
    'entity' => $_SESSION['glpiactiveentities'],
]);
echo "</div>";

echo "<div class='zimmet-filter-field'><label>" . Group::getTypeName(1)
    . " <span class='zimmet-optional'>(opsiyonel)</span></label>";
Group::dropdown([
    'name'                => 'groups_id',
    'value'               => $groups_id,
    'entity'              => $_SESSION['glpiactiveentities'],
    'condition'           => [],
    'display_emptychoice' => true,
    'emptylabel'          => 'Tüm gruplar',
]);
echo "</div>";

echo "<div class='zimmet-filter-field'><label>" . __('Document type', 'zimmet') . "</label>";
Dropdown::showFromArray('doc_type', PluginZimmetTemplate::getDocTypes(), ['value' => $doc_type]);
echo "</div>";

echo "<div class='zimmet-genfilter-action'>";
echo "<button type='submit' name='list_users' class='btn btn-primary'>"
    . "<i class='ti ti-users'></i> " . __('List personnel', 'zimmet') . "</button>";
echo "</div>";

echo "</div>"; // zimmet-genfilter

echo "<div class='form-text text-muted' style='margin-top:8px'>"
    . "Grup seçimi zorunlu değildir; boş bırakılırsa tüm gruplar listelenir.</div>";
Html::closeForm();
echo "</div></div>";

// ----- ADIM 2: Personel listesi + çoklu seçim -----
if (isset($_POST['list_users']) && $entities_id >= 0) {
    $candidates = zimmet_findCandidateUsers($entities_id, $groups_id);

    echo "<div class='card'>";
    echo "<div class='zimmet-card-head'><i class='ti ti-list-check'></i>"
        . "<h3>Adım 2: Personel seçimi (" . count($candidates) . ")</h3></div>";
    echo "<div class='card-body'>";

    if (empty($candidates)) {
        echo "<div class='alert alert-info mb-0'>"
            . "Seçilen birim ve grup filtresine uygun zimmetli personel bulunamadı. "
            . "Grup seçimini boş bırakarak tekrar deneyebilir veya personelin birim yetkisini ve zimmetli varlıklarını kontrol edebilirsiniz."
            . "</div>";
    } else {
        echo "<form method='post' action='" . $web . "/front/generate.php'>";
        echo Html::hidden('doc_type', ['value' => $doc_type]);

        echo "<div class='table-responsive'>";
        echo "<table class='tab_cadre_fixehov zimmet-document-table'>";
        echo "<thead><tr><th style='width:42px' class='center'>"
            . "<input type='checkbox' onclick='$(\".zimmet-usr\").prop(\"checked\", this.checked)' checked></th>";
        echo "<th>" . __('Name') . "</th><th>" . __('Department/Title', 'zimmet') . "</th>";
        echo "<th class='center'>" . __('Assigned assets', 'zimmet') . "</th></tr></thead><tbody>";

        foreach ($candidates as $c) {
            echo "<tr>";
            echo "<td class='center'><input type='checkbox' class='zimmet-usr' name='sel_users[]' value='"
                . $c['users_id'] . "' checked></td>";
            echo "<td>" . htmlspecialchars($c['fullname']) . "</td>";
            echo "<td>" . htmlspecialchars($c['department']) . "</td>";
            echo "<td class='center'><span class='badge zimmet-count'>" . $c['asset_count'] . "</span></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>";

        echo "<div class='mt-3 center'>";
        echo "<button type='submit' name='generate' value='1' class='btn btn-primary'>"
            . "<i class='ti ti-file-plus'></i> Tutanakları oluştur</button>";
        echo "<p class='text-muted mt-2'>"
            . 'Seçilen her personel için ayrı tutanak kaydı oluşturulur. Aşağıda ZIP ve birleşik PDF indirme bağlantıları sunulur.'
            . "</p>";
        echo "</div>";
        Html::closeForm();
    }
    echo "</div></div>";
}

// ----- TOPLU ÜRETİM SONUCU (en altta) -----
if ($batchKey !== '') {
    echo "<div class='card'>";
    echo "<div class='zimmet-card-head'><i class='ti ti-circle-check'></i>"
        . "<h3>Toplu üretim sonucu</h3></div>";
    echo "<div class='card-body'>";
    if (!empty($batch['ids'])) {
        $zipUrl = $web . '/front/generate.php?download=zip&batch=' . urlencode($batchKey);
        $pdfUrl = $web . '/front/generate.php?download=combined&batch=' . urlencode($batchKey);

        echo "<div class='alert alert-success mb-3'>"
            . "<i class='ti ti-circle-check'></i> "
            . count($batch['ids']) . " personel için tutanak kaydı oluşturuldu. "
            . "Aşağıdaki bağlantılar yalnızca mevcut kayıtlar üzerinden çıktı üretir; tekrar kayıt oluşturmaz."
            . "</div>";
        echo "<div class='d-flex gap-2 flex-wrap'>";
        echo "<a class='btn btn-success' href='" . htmlspecialchars($zipUrl) . "'>"
            . "<i class='ti ti-file-zip'></i> Ayrı PDF'leri ZIP olarak indir</a>";
        echo "<a class='btn btn-outline-primary' target='_blank' href='" . htmlspecialchars($pdfUrl) . "'>"
            . "<i class='ti ti-file-type-pdf'></i> Birleşik PDF üret</a>";
        echo "<a class='btn btn-outline-secondary' href='" . htmlspecialchars($web . '/front/generate.php') . "'>"
            . "<i class='ti ti-refresh'></i> Yeni toplu üretim</a>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning mb-0'>"
            . "Toplu üretim sonucu bulunamadı. Lütfen personel listesinden yeniden üretim başlatın."
            . "</div>";
    }
    echo "</div></div>";
}

PluginZimmetMenu::closeApp();
Html::footer();
