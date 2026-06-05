<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Tutanak (Document) ana sınıfı
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
 * Zimmet / Teslim-Tesellüm tutanağı.
 *
 * Bir personele (users_id) ait, belirli bir andaki cihaz durumunu
 * dondurarak (snapshot) saklayan ve PDF üretilebilen tutanak kaydı.
 */
class PluginZimmetDocument extends CommonDBTM
{
    public static $rightname = 'plugin_zimmet_document';

    public $dohistory = true;

    // Entity desteği
    public static function getTypeName($nb = 0)
    {
        return _n('Handover record', 'Handover records', $nb, 'zimmet');
    }

    public static function getIcon()
    {
        return 'ti ti-clipboard-check';
    }

    public function isEntityAssign()
    {
        return true;
    }

    public function maybeRecursive()
    {
        return false;
    }

    /**
     * Belge durum etiketleri.
     *
     * @return array
     */
    public static function getStatuses()
    {
        return [
            'draft'           => __('Draft', 'zimmet'),
            'printed'         => __('Printed', 'zimmet'),
            'signed_archived' => __('Signed & archived', 'zimmet'),
            'returned'        => __('Returned', 'zimmet'),
        ];
    }

    public static function getNextReceiptNo($prefix = 'ENVT')
    {
        /** @var DBmysql $DB */
        global $DB;

        $prefix = trim((string) $prefix, " .\t\n\r\0\x0B");
        if ($prefix === '') {
            $prefix = 'ENVT';
        }
        $prefix .= '.';
        $max = 0;

        if ($DB->tableExists(self::getTable())) {
            $iterator = $DB->request([
                'SELECT' => ['document_no'],
                'FROM'   => self::getTable(),
                'WHERE'  => ['document_no' => ['LIKE', $prefix . '%']],
            ]);
            foreach ($iterator as $row) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $row['document_no'], $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Şablon doküman numarasından tutanak fiş serisi üretir.
     *
     * Örn: ZT.v01 -> ZT, ENVT.001 -> ENVT.
     */
    private static function getReceiptPrefixFromTemplate($tpl, $entities_id = 0)
    {
        $doc_no = '';
        if ($tpl && !empty($tpl->fields['document_no'])) {
            $doc_no = trim((string) $tpl->fields['document_no']);
        }

        if ($doc_no !== '') {
            $prefix = preg_replace('/[.\-_\/ ](?:v|rev|r)\d+(?:[.\-_\/ ]*\d+)*$/i', '', $doc_no);
            $prefix = preg_replace('/(?:[.\-_\/ ]?\d+)$/', '', (string) $prefix);
            $prefix = trim((string) $prefix, " .-_/\t\n\r\0\x0B");
            if ($prefix !== '') {
                return $prefix;
            }
        }

        $entity_name = $entities_id > 0
            ? Dropdown::getDropdownName('glpi_entities', (int) $entities_id)
            : 'DOC';
        $entity_name = html_entity_decode(strip_tags($entity_name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $entity_name = preg_replace('/[^A-Za-z0-9]+/', '', $entity_name);
        $code = strtoupper(substr($entity_name ?: 'DOC', 0, 3));

        return $code . '.ENVT';
    }

    /**
     * Bir kullanıcıya zimmetli (users_id) tüm varlıkları toplar.
     *
     * Yapılandırılmış varlık türlerini (Computer, Phone, Monitor,
     * Peripheral, Printer) tarar; her biri için seri no, envanter/stok no
     * ve durum bilgisini döndürür.
     *
     * @param integer $users_id
     * @param integer $entities_id  Yalnızca bu entity (null = tüm görünür)
     *
     * @return array  itemtype/items_id/name/serial/otherserial/state ...
     */
    public static function getUserAssets($users_id, $entities_id = null)
    {
        /** @var DBmysql $DB */
        global $DB;

        $assets = [];

        foreach (PluginZimmetConfig::getAssetTypes() as $itemtype) {
            if (!class_exists($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }

            $item  = new $itemtype();
            $table = $item->getTable();

            // Sadece users_id alanı olan varlık türlerini işleyebiliriz
            if (!$DB->fieldExists($table, 'users_id')) {
                continue;
            }

            $where = [
                "$table.users_id"   => $users_id,
                "$table.is_deleted" => 0,
            ];
            if ($DB->fieldExists($table, 'is_template')) {
                $where["$table.is_template"] = 0;
            }
            if ($entities_id !== null && $DB->fieldExists($table, 'entities_id')) {
                $where["$table.entities_id"] = $entities_id;
            }

            $select = [
                "$table.id",
                "$table.name",
            ];
            $joins = [];

            // Seri no
            if ($DB->fieldExists($table, 'serial')) {
                $select[] = "$table.serial";
            }
            // Envanter / stok no
            if ($DB->fieldExists($table, 'otherserial')) {
                $select[] = "$table.otherserial";
            }
            // Durum
            $has_state = $DB->fieldExists($table, 'states_id');
            if ($has_state) {
                $select[] = 'glpi_states.name AS state_name';
                $joins['glpi_states'] = [
                    'ON' => [
                        'glpi_states' => 'id',
                        $table        => 'states_id',
                    ],
                ];
            }

            // Marka (üretici)
            $has_manufacturer = $DB->fieldExists($table, 'manufacturers_id');
            if ($has_manufacturer) {
                $select[] = 'glpi_manufacturers.name AS manufacturer_name';
                $joins['glpi_manufacturers'] = [
                    'ON' => [
                        'glpi_manufacturers' => 'id',
                        $table               => 'manufacturers_id',
                    ],
                ];
            }

            // Model
            $model_field = strtolower($itemtype) . 'models_id';
            $model_table = 'glpi_' . strtolower($itemtype) . 'models';
            $has_model = $DB->fieldExists($table, $model_field) && $DB->tableExists($model_table);
            if ($has_model) {
                $select[] = "$model_table.name AS model_name";
                $joins[$model_table] = [
                    'ON' => [
                        $model_table => 'id',
                        $table       => $model_field,
                    ],
                ];
            }

            $criteria = [
                'SELECT' => $select,
                'FROM'   => $table,
                'WHERE'  => $where,
            ];
            if (!empty($joins)) {
                $criteria['LEFT JOIN'] = $joins;
            }

            foreach ($DB->request($criteria) as $row) {
                $manufacturer = $row['manufacturer_name'] ?? '';
                $model        = $row['model_name'] ?? '';

                // Ekipman adı: "Tür: Marka Model" (yoksa "Tür: Ad") — tek tür öneki
                $brandModel = trim($manufacturer . ' ' . $model);
                $baseName   = trim($row['name'] ?? '');
                $typeName   = $itemtype::getTypeName(1);
                if ($brandModel !== '') {
                    $fullName = $typeName . ': ' . $brandModel;
                } elseif ($baseName !== '') {
                    $fullName = $typeName . ': ' . $baseName;
                } else {
                    $fullName = $typeName;
                }

                $assets[] = [
                    'itemtype'     => $itemtype,
                    'items_id'     => (int) $row['id'],
                    'type_label'   => $itemtype::getTypeName(1),
                    'item_name'    => $fullName,
                    'manufacturer' => $manufacturer,
                    'model'        => $model,
                    'serial'       => $row['serial'] ?? '',
                    'otherserial'  => $row['otherserial'] ?? '',
                    'state_name'   => $row['state_name'] ?? '',
                ];
            }
        }

        return $assets;
    }

    /**
     * Bir kullanıcının entity'sini ve görünen adını döndürür.
     *
     * @param integer $users_id
     *
     * @return array ['entities_id' => int, 'fullname' => string, 'title' => string]
     */
    public static function getUserInfo($users_id)
    {
        $user = new User();
        if (!$user->getFromDB($users_id)) {
            return ['entities_id' => 0, 'fullname' => '', 'title' => ''];
        }

        /** @var DBmysql $DB */
        global $DB;

        // Görev: kullanıcının ünvanı (UserTitle)
        $title = '';
        if ($user->fields['usertitles_id']) {
            $ut = new UserTitle();
            if ($ut->getFromDB($user->fields['usertitles_id'])) {
                $title = $ut->fields['name'];
            }
        }

        // Departman: kullanıcının ilk grubu (Group)
        $department = '';
        $grow = $DB->request([
            'SELECT'    => 'glpi_groups.name AS gname',
            'FROM'      => 'glpi_groups_users',
            'LEFT JOIN' => [
                'glpi_groups' => [
                    'ON' => ['glpi_groups' => 'id', 'glpi_groups_users' => 'groups_id'],
                ],
            ],
            'WHERE'     => ['glpi_groups_users.users_id' => $users_id],
            'ORDER'     => ['glpi_groups_users.id ASC'],
            'LIMIT'     => 1,
        ])->current();
        if ($grow && !empty($grow['gname'])) {
            $department = $grow['gname'];
        }

        // Departman + Görev birleşik gösterim
        $deptTitle = trim($department . ($department && $title ? ' / ' : '') . $title);

        return [
            'entities_id' => self::getUserTemplateEntity($users_id, (int) $user->fields['entities_id']),
            'fullname'    => formatUserName(
                $user->fields['id'],
                $user->fields['name'],
                $user->fields['realname'],
                $user->fields['firstname']
            ),
            'title'       => $deptTitle,
            'department'  => $department,
            'job_title'   => $title,
        ];
    }

    /**
     * Şablon seçimi için kullanıcının firma/birim bilgisini bulur.
     *
     * GLPI'de kullanıcının "Birimler > Kullanıcılar" sekmesinden atanması
     * glpi_users.entities_id değerini her zaman değiştirmez. Bu yüzden önce
     * profil-yetkilendirme kayıtlarındaki en derin birimi, yoksa kullanıcı
     * kartındaki ana birimi kullanıyoruz.
     */
    private static function getUserTemplateEntity($users_id, $fallback_entity = 0)
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($DB->tableExists('glpi_profiles_users')) {
            $iterator = $DB->request([
                'SELECT'    => [
                    'glpi_profiles_users.entities_id',
                    'glpi_entities.level',
                ],
                'FROM'      => 'glpi_profiles_users',
                'LEFT JOIN' => [
                    'glpi_entities' => [
                        'ON' => ['glpi_profiles_users' => 'entities_id', 'glpi_entities' => 'id'],
                    ],
                ],
                'WHERE'     => [
                    'glpi_profiles_users.users_id'     => (int) $users_id,
                    'glpi_profiles_users.entities_id' => ['>', 0],
                ],
                'ORDER'     => [
                    'glpi_entities.level DESC',
                    'glpi_profiles_users.id DESC',
                ],
                'LIMIT'     => 1,
            ]);

            if (count($iterator)) {
                return (int) $iterator->current()['entities_id'];
            }
        }

        return (int) $fallback_entity;
    }

    /**
     * Kullanıcı kartında "Zimmet Tutanakları" sekmesi başlığı.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof User && Session::haveRight('plugin_zimmet_document', READ)) {
            $count = countElementsInTable(
                self::getTable(),
                ['users_id' => $item->getID()]
            );
            return self::createTabEntry(self::getTypeName(2), $count);
        }
        return '';
    }

    /**
     * Kullanıcı kartı sekme içeriği: bu kişiye ait tutanaklar.
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if ($item instanceof User) {
            self::showForUser($item->getID());
        }
        return true;
    }

    /**
     * Bir kullanıcıya ait tutanak listesini gösterir.
     *
     * @param integer $users_id
     *
     * @return void
     */
    public static function showForUser($users_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        $web        = Plugin::getWebDir('zimmet');
        $doc_types  = PluginZimmetTemplate::getDocTypes();
        $statuses   = self::getStatuses();

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['users_id' => $users_id],
            'ORDER' => ['date_creation DESC'],
        ]);

        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'><th colspan='5'>" . self::getTypeName(2) . "</th></tr>";
        echo "<tr><th>" . __('Name') . "</th><th>" . __('Document type', 'zimmet')
            . "</th><th>" . __('Status') . "</th><th>" . __('Date') . "</th><th></th></tr>";

        if (!count($iterator)) {
            echo "<tr class='tab_bg_1'><td colspan='5' class='center'>"
                . __('No item to display') . "</td></tr>";
        }

        foreach ($iterator as $row) {
            $url = $web . '/front/document.form.php?id=' . $row['id'];
            echo "<tr class='tab_bg_1'>";
            echo "<td><a href='" . htmlspecialchars($url) . "'>"
                . htmlspecialchars($row['name'] ?: ('#' . $row['id'])) . "</a></td>";
            echo "<td>" . ($doc_types[$row['doc_type']] ?? $row['doc_type']) . "</td>";
            echo "<td>" . ($statuses[$row['status']] ?? $row['status']) . "</td>";
            echo "<td>" . Html::convDateTime($row['date_creation']) . "</td>";
            echo "<td><a class='btn btn-sm btn-outline-secondary' target='_blank' href='"
                . htmlspecialchars($web . '/front/document.pdf.php?id=' . $row['id'])
                . "'><i class='ti ti-file-type-pdf'></i> PDF</a></td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "</div>";
    }

    /**
     * Ekleme öncesi girdiyi hazırlar: kişiye göre şablon, fiş no,
     * eksik isim ve entity bilgisini doldurur.
     */
    public function prepareInputForAdd($input)
    {
        $doc_type = $input['doc_type'] ?? 'zimmet';

        // Kişiye göre entity'yi otomatik belirle
        if (!empty($input['users_id']) && empty($input['entities_id'])) {
            $info = self::getUserInfo($input['users_id']);
            $input['entities_id'] = $info['entities_id'];
        }

        $tpl = PluginZimmetTemplate::getForEntity($input['entities_id'] ?? 0, $doc_type);
        if ($tpl) {
            $input['plugin_zimmet_templates_id'] = $tpl->getID();
        }

        if (empty($input['document_no'])) {
            $input['document_no'] = self::getNextReceiptNo(
                self::getReceiptPrefixFromTemplate($tpl, $input['entities_id'] ?? 0)
            );
        }
        if (empty($input['document_date'])) {
            $input['document_date'] = date('Y-m-d');
        }

        if (empty($input['name']) && !empty($input['users_id'])) {
            $info = self::getUserInfo($input['users_id']);
            $label = ($doc_type === 'tesellum')
                ? __('Return record', 'zimmet')
                : __('Handover record', 'zimmet');
            $input['name'] = $label . ' — ' . $info['fullname'] . ' (' . date('d.m.Y') . ')';
        }

        if (empty($input['tech_users_id'])) {
            $input['tech_users_id'] = Session::getLoginUserID();
        }

        $input['date_creation'] = $input['date_creation'] ?? $_SESSION['glpi_currenttime'];

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (!empty($input['users_id'])) {
            $doc_type = $input['doc_type'] ?? ($this->fields['doc_type'] ?? 'zimmet');
            $info = self::getUserInfo($input['users_id']);
            $input['entities_id'] = $info['entities_id'];

            $tpl = PluginZimmetTemplate::getForEntity($input['entities_id'], $doc_type);
            if ($tpl) {
                $input['plugin_zimmet_templates_id'] = $tpl->getID();
            }

            $prefix = self::getReceiptPrefixFromTemplate($tpl, $input['entities_id']);
            $current_no = $input['document_no'] ?? ($this->fields['document_no'] ?? '');
            if (empty($current_no) || strpos((string) $current_no, $prefix . '.') !== 0) {
                $input['document_no'] = self::getNextReceiptNo($prefix);
            }
        }

        return $input;
    }

    /**
     * Ekleme sonrası cihaz satırlarını (snapshot) kaydeder.
     */
    public function post_addItem()
    {
        if (isset($this->input['lines']) && is_array($this->input['lines'])) {
            self::saveLines($this->getID(), $this->input['lines']);
        }
    }

    /**
     * Güncelleme sonrası satırları yeniden yazar (varsa).
     */
    public function post_updateItem($history = 1)
    {
        if (isset($this->input['lines']) && is_array($this->input['lines'])) {
            // Mevcut satırları temizle, yeniden yaz
            $child = new PluginZimmetDocumentItem();
            $child->deleteByCriteria(['plugin_zimmet_documents_id' => $this->getID()], true);
            self::saveLines($this->getID(), $this->input['lines']);
        }
    }

    /**
     * Form girdisinden cihaz satırlarını snapshot olarak kaydeder.
     *
     * @param integer $documents_id
     * @param array   $lines
     *
     * @return integer  kaydedilen satır sayısı
     */
    public static function saveLines($documents_id, array $lines)
    {
        $child = new PluginZimmetDocumentItem();
        $order = 0;
        $count = 0;

        foreach ($lines as $line) {
            // İşaretlenmemiş satırları atla
            if (empty($line['use'])) {
                continue;
            }
            // Boş manuel satırları atla
            if (empty($line['item_name']) && empty($line['serial']) && empty($line['otherserial'])) {
                continue;
            }

            $asset = [
                'itemtype'    => !empty($line['is_manual']) ? null : ($line['itemtype'] ?? null),
                'items_id'    => $line['items_id'] ?? 0,
                'item_name'   => $line['item_name'] ?? '',
                'serial'      => $line['serial'] ?? '',
                'otherserial' => $line['otherserial'] ?? '',
                'state_name'  => $line['state_name'] ?? '',
            ];
            $quantity = isset($line['quantity']) ? (float) $line['quantity'] : 1;
            $unit     = $line['unit'] ?? 'Adet';

            $snapshot = PluginZimmetDocumentItem::buildSnapshot(
                $asset,
                $documents_id,
                $order++,
                $quantity,
                $unit
            );
            if ($child->add($snapshot)) {
                $count++;
            }
        }

        return $count;
    }

    private static function formatQuantityInput($quantity)
    {
        $quantity = (float) $quantity;
        if ($quantity == (int) $quantity) {
            return (string) (int) $quantity;
        }

        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }

    /**
     * Oluşturma / düzenleme formu.
     *
     * @param integer $ID
     * @param array   $options
     *
     * @return boolean
     */
    public function showForm($ID, array $options = [])
    {
        if (!Session::haveRight('plugin_zimmet_document', $ID > 0 ? READ : CREATE)) {
            return false;
        }

        $web = Plugin::getWebDir('zimmet');

        if ($ID > 0) {
            $this->getFromDB($ID);
        } else {
            $this->getEmpty();
            $this->fields['doc_type'] = $options['doc_type'] ?? 'zimmet';
            $this->fields['document_date'] = date('Y-m-d');
        }

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $doc_types = PluginZimmetTemplate::getDocTypes();
        $statuses  = self::getStatuses();

        // Belge tipi
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Document type', 'zimmet') . "</td><td>";
        Dropdown::showFromArray('doc_type', $doc_types, [
            'value' => $this->fields['doc_type'],
        ]);
        echo "</td>";

        // Durum
        echo "<td>" . __('Status') . "</td><td>";
        Dropdown::showFromArray('status', $statuses, [
            'value' => $this->fields['status'] ?: 'draft',
        ]);
        echo "</td></tr>";

        // Fiş no / tutanak tarihi
        echo "<tr class='tab_bg_1'>";
        echo "<td>Fiş No</td><td>";
        if ($ID > 0) {
            echo "<input type='text' class='form-control' readonly value='"
                . htmlspecialchars($this->fields['document_no'] ?? '') . "'>";
        } else {
            echo "<input type='text' class='form-control' readonly value='Kaydedilince otomatik atanır'>";
        }
        echo "</td>";
        echo "<td>Tarih <span class='red'>*</span></td><td>";
        Html::showDateField('document_date', [
            'value' => $this->fields['document_date'] ?? date('Y-m-d'),
        ]);
        echo "</td></tr>";

        // Personel (zimmetli)
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Assigned to', 'zimmet') . " <span class='red'>*</span></td><td>";
        User::dropdown([
            'name'   => 'users_id',
            'value'  => $this->fields['users_id'],
            'right'  => 'all',
            'entity' => $_SESSION['glpiactiveentities'],
        ]);
        echo "</td>";

        // Teslim eden (teknik personel)
        echo "<td>" . __('Delivered by', 'zimmet') . "</td><td>";
        User::dropdown([
            'name'  => 'tech_users_id',
            'value' => $this->fields['tech_users_id'] ?: Session::getLoginUserID(),
            'right' => 'all',
        ]);
        echo "</td></tr>";

        // Departman / Görev (otomatik, kişiden gelir — salt bilgi)
        $deptVal = '';
        if ($this->fields['users_id']) {
            $uinfo = self::getUserInfo($this->fields['users_id']);
            $deptVal = $uinfo['title'];
        }
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Department/Title', 'zimmet') . "</td><td colspan='3'>";
        echo "<input type='text' id='zimmet-dept-display' class='form-control' readonly "
            . "value='" . htmlspecialchars($deptVal) . "' "
            . "placeholder='" . __('Auto-filled from selected person', 'zimmet') . "'>";
        echo "</td></tr>";

        // Cihaz tablosu
        echo "<tr class='tab_bg_1'><td colspan='4'>";
        echo "<div class='zimmet-asset-wrap'>";
        echo "<div class='zimmet-asset-head'>";
        echo "<h4>" . __('Equipment', 'zimmet') . "</h4>";
        echo "<button type='button' class='btn btn-sm btn-outline-primary' "
            . "onclick='ZimmetPlugin.addManualRow(\"#zimmet-asset-table\")'>"
            . "<i class='ti ti-plus'></i> " . __('Add manual line', 'zimmet')
            . "</button>";
        echo "</div>";

        echo "<table class='tab_cadre_fixe zimmet-asset-table' id='zimmet-asset-table'>";
        echo "<thead><tr>";
        echo "<th style='width:40px'>" . __('Use') . "</th>";
        echo "<th>" . __('Equipment name', 'zimmet') . "</th>";
        echo "<th>" . __('Serial number') . "</th>";
        echo "<th>" . __('Inventory/Stock number', 'zimmet') . "</th>";
        echo "<th>" . __('Status') . "</th>";
        echo "<th>" . __('Quantity', 'zimmet') . "</th>";
        echo "<th>" . __('Unit', 'zimmet') . "</th>";
        echo "<th style='width:50px'></th>";
        echo "</tr></thead>";
        echo "<tbody id='zimmet-asset-body'>";

        if ($ID > 0) {
            // Mevcut snapshot satırlarını göster
            foreach (PluginZimmetDocumentItem::getForDocument($ID) as $row) {
                $key = 'saved_' . $row['id'];
                echo "<tr class='tab_bg_1" . ($row['is_manual'] ? " zimmet-manual-row" : "") . "'>";
                echo "<td class='center'><input type='checkbox' name='lines[$key][use]' value='1' checked>"
                    . "<input type='hidden' name='lines[$key][itemtype]' value='" . htmlspecialchars((string) $row['itemtype']) . "'>"
                    . "<input type='hidden' name='lines[$key][items_id]' value='" . (int) $row['items_id'] . "'>"
                    . "<input type='hidden' name='lines[$key][is_manual]' value='" . (int) $row['is_manual'] . "'></td>";
                echo "<td><input type='text' class='form-control form-control-sm' name='lines[$key][item_name]' value='" . htmlspecialchars((string) $row['item_name']) . "'></td>";
                echo "<td><input type='text' class='form-control form-control-sm' name='lines[$key][serial]' value='" . htmlspecialchars((string) $row['serial']) . "'></td>";
                echo "<td><input type='text' class='form-control form-control-sm' name='lines[$key][otherserial]' value='" . htmlspecialchars((string) $row['otherserial']) . "'></td>";
                echo "<td><input type='text' class='form-control form-control-sm' name='lines[$key][state_name]' value='" . htmlspecialchars((string) $row['state_name']) . "'></td>";
                echo "<td><input type='number' step='0.01' class='form-control form-control-sm' name='lines[$key][quantity]' value='" . htmlspecialchars(self::formatQuantityInput($row['quantity'])) . "' style='width:80px'></td>";
                echo "<td><input type='text' class='form-control form-control-sm' name='lines[$key][unit]' value='" . htmlspecialchars((string) $row['unit']) . "' style='width:90px'></td>";
                echo "<td class='center'><button type='button' class='btn btn-sm btn-outline-danger' onclick='ZimmetPlugin.removeRow(this)'><i class='ti ti-trash'></i></button></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr class='tab_bg_1'><td colspan='8' class='center'>"
                . __('Select a person to load assigned assets', 'zimmet') . "</td></tr>";
        }

        echo "</tbody></table>";
        echo "</div>";
        echo "</td></tr>";

        // Personel seçilince cihazları yükle (AJAX)
        $rooturl = json_encode($web);
        echo Html::scriptBlock("
            $(function() {
                $('select[name=users_id]').on('change', function() {
                    var uid = $(this).val();
                    if (uid > 0) {
                        ZimmetPlugin.loadUserAssets($rooturl, uid, '#zimmet-asset-body');
                    }
                });
            });
        ");

        if ($ID > 0) {
            $pdfUrl     = $web . '/front/document.pdf.php?id=' . $ID;
            $archiveUrl = $web . '/front/archive.php?id=' . $ID;

            echo "</table>";
            echo "<div class='card-body mx-n2 mb-4 border-top zimmet-form-footer-actions'>";
            echo "<div class='zimmet-pdf-actions zimmet-form-actions'>";
            echo "<a class='btn btn-primary' target='_blank' href='" . htmlspecialchars($pdfUrl) . "'>"
                . "<i class='ti ti-file-type-pdf'></i> " . __('View / Print PDF', 'zimmet') . "</a>";

            if (Session::haveRight('plugin_zimmet_document', UPDATE)) {
                echo "<button type='button' class='btn btn-success' id='zimmet-open-archive-modal'>"
                    . "<i class='ti ti-archive'></i> " . __('Archive signed copy', 'zimmet') . "</button>";
            }
            if (Session::haveRight('plugin_zimmet_document', PURGE)) {
                echo "<button type='button' class='btn btn-outline-danger' id='zimmet-open-delete-modal'>"
                    . "<i class='ti ti-trash'></i> Kalıcı Olarak Sil</button>";
            }
            echo "</div>";

            if ($this->can($ID, UPDATE)) {
                echo "<button class='btn btn-primary zimmet-save-button' type='submit' name='update' value='1'>"
                    . "<i class='far fa-save'></i> <span>" . _x('button', 'Save') . "</span></button>";
            }
            echo "</div>";

            if ($this->isField('date_mod')) {
                echo "<input type='hidden' name='_read_date_mod' value='" . htmlspecialchars((string) $this->fields['date_mod'], ENT_QUOTES, 'UTF-8') . "'>";
            }
            echo "<input type='hidden' name='id' value='" . (int) $ID . "'>";
            echo "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'>";
            echo "</div>";
            echo "</form>";

            if (!empty($this->fields['pdf_hash'])) {
                echo "<div class='zimmet-integrity-note text-muted'>"
                    . "<i class='ti ti-shield-check'></i> SHA-256: <code>"
                    . htmlspecialchars(substr($this->fields['pdf_hash'], 0, 24)) . "...</code>";
                if (!empty($this->fields['generated_at'])) {
                    echo " - " . Html::convDateTime($this->fields['generated_at']);
                }
                echo "</div>";
            }

            if (Session::haveRight('plugin_zimmet_document', UPDATE)) {
                echo "<div id='zimmet-archive-modal' class='zimmet-confirm-backdrop' role='dialog' aria-modal='true'>";
                echo "<div class='zimmet-confirm-modal'>";
                echo "<div class='zimmet-confirm-head'><h3><i class='ti ti-shield-check'></i> İmzalı kopyayı arşivle</h3></div>";
                echo "<div class='zimmet-confirm-body'>";
                echo "<p>Bu işlem mevcut tutanağın PDF çıktısını arşive kaydeder ve bütünlük doğrulaması için SHA-256 izi oluşturur.</p>";
                echo "<ul class='zimmet-confirm-list'>";
                echo "<li><span>Tutanak</span><span>" . htmlspecialchars($this->fields['name'] ?? ('#' . $ID)) . "</span></li>";
                echo "<li><span>İşlem türü</span><span>Arşivleme</span></li>";
                echo "<li><span>Bütünlük kaydı</span><span>Otomatik</span></li>";
                echo "</ul>";
                echo "</div>";
                echo "<div class='zimmet-confirm-foot'>";
                echo "<button type='button' id='zimmet-cancel-archive' class='btn btn-outline-secondary'>Vazgeç</button>";
                echo "<a class='btn btn-success' href='" . htmlspecialchars($archiveUrl) . "'><i class='ti ti-archive'></i> Arşivlemeyi başlat</a>";
                echo "</div></div></div>";

                echo Html::scriptBlock("
                    $(function() {
                        var modal = $('#zimmet-archive-modal');
                        $('#zimmet-open-archive-modal').on('click', function() {
                            modal.css('display', 'flex');
                        });
                        $('#zimmet-cancel-archive').on('click', function() {
                            modal.hide();
                        });
                        modal.on('click', function(e) {
                            if (e.target === this) {
                                modal.hide();
                            }
                        });
                    });
                ");
            }

            if (Session::haveRight('plugin_zimmet_document', PURGE)) {
                echo "<form method='post' action='" . htmlspecialchars($web . '/front/document.form.php', ENT_QUOTES, 'UTF-8') . "' id='zimmet-delete-form'>";
                echo "<input type='hidden' name='id' value='" . (int) $ID . "'>";
                echo "<input type='hidden' name='purge' value='1'>";
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
                echo "</form>";

                echo "<div id='zimmet-delete-modal' class='zimmet-confirm-backdrop' role='dialog' aria-modal='true'>";
                echo "<div class='zimmet-confirm-modal'>";
                echo "<div class='zimmet-confirm-head'><h3><i class='ti ti-alert-triangle'></i> Kalıcı silme onayı</h3></div>";
                echo "<div class='zimmet-confirm-body'>";
                echo "<p>Bu tutanak kalıcı olarak silinecektir. İşlem tamamlandıktan sonra geri alınamaz.</p>";
                echo "<ul class='zimmet-confirm-list'>";
                echo "<li><span>Tutanak</span><span>" . htmlspecialchars($this->fields['name'] ?? ('#' . $ID)) . "</span></li>";
                echo "<li><span>İşlem türü</span><span>Kalıcı silme</span></li>";
                echo "<li><span>Geri alma</span><span>Mümkün değil</span></li>";
                echo "</ul>";
                echo "</div>";
                echo "<div class='zimmet-confirm-foot'>";
                echo "<button type='button' id='zimmet-cancel-delete' class='btn btn-outline-secondary'>Vazgeç</button>";
                echo "<button type='button' id='zimmet-confirm-delete' class='btn btn-danger'><i class='ti ti-trash'></i> Kalıcı Olarak Sil</button>";
                echo "</div></div></div>";

                echo Html::scriptBlock("
                    $(function() {
                        var modal = $('#zimmet-delete-modal');
                        $('#zimmet-open-delete-modal').on('click', function() {
                            modal.css('display', 'flex');
                        });
                        $('#zimmet-cancel-delete').on('click', function() {
                            modal.hide();
                        });
                        $('#zimmet-confirm-delete').on('click', function() {
                            $('#zimmet-delete-form').trigger('submit');
                        });
                        modal.on('click', function(e) {
                            if (e.target === this) {
                                modal.hide();
                            }
                        });
                    });
                ");
            }
        } else {
            $this->showFormButtons($options);
        }

        return true;
    }

    /**
     * Arama motoru seçenekleri.
     */
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];

        $tab[] = [
            'id'       => '1',
            'table'    => self::getTable(),
            'field'    => 'name',
            'name'     => 'Tutanak',
            'datatype' => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'    => '2',
            'table' => self::getTable(),
            'field' => 'doc_type',
            'name'  => __('Document type', 'zimmet'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        $tab[] = [
            'id'       => '3',
            'table'    => 'glpi_users',
            'field'    => 'name',
            'linkfield' => 'users_id',
            'name'     => __('Assigned to', 'zimmet'),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id'       => '4',
            'table'    => 'glpi_users',
            'field'    => 'name',
            'linkfield' => 'tech_users_id',
            'name'     => __('Delivered by', 'zimmet'),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id'    => '5',
            'table' => self::getTable(),
            'field' => 'status',
            'name'  => __('Status'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        $tab[] = [
            'id'    => '6',
            'table' => self::getTable(),
            'field' => 'document_no',
            'name'  => 'Fiş No',
        ];
        $tab[] = [
            'id'       => '7',
            'table'    => self::getTable(),
            'field'    => 'document_date',
            'name'     => 'Tarih',
            'datatype' => 'date',
        ];
        $tab[] = [
            'id'       => '90',
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => 'Personel',
            'datatype' => 'specific',
            'massiveaction' => false,
            'nosort'   => true,
        ];
        $tab[] = [
            'id'       => '91',
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => 'Ekipman',
            'datatype' => 'specific',
            'massiveaction' => false,
            'nosort'   => true,
        ];
        $tab[] = [
            'id'       => '92',
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => 'İşlemler',
            'datatype' => 'specific',
            'massiveaction' => false,
            'nosort'   => true,
        ];
        $tab[] = [
            'id'       => '19',
            'table'    => self::getTable(),
            'field'    => 'date_mod',
            'name'     => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => '121',
            'table'    => self::getTable(),
            'field'    => 'date_creation',
            'name'     => __('Creation date'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => '80',
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => Entity::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    /**
     * Arama sonuçlarında özel alan görünümü.
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'id':
                $searchId = (int) ($options['searchopt']['id'] ?? 0);
                $id = (int) ($values['id'] ?? ($values[$field] ?? 0));
                if ($id <= 0) {
                    return '';
                }

                if ($searchId === 90) {
                    $doc = new self();
                    if (!$doc->getFromDB($id) || empty($doc->fields['users_id'])) {
                        return '';
                    }
                    $info = self::getUserInfo((int) $doc->fields['users_id']);
                    return htmlspecialchars($info['fullname'] ?? '', ENT_QUOTES, 'UTF-8');
                }

                if ($searchId === 91) {
                    $count = countElementsInTable(
                        PluginZimmetDocumentItem::getTable(),
                        ['plugin_zimmet_documents_id' => $id]
                    );
                    return "<span class='badge bg-blue'>" . (int) $count . "</span>";
                }

                if ($searchId === 92) {
                    $web = Plugin::getWebDir('zimmet');
                    $pdfUrl = $web . '/front/document.pdf.php?id=' . $id;
                    $printUrl = $web . '/front/document.print.php?id=' . $id;
                    $editUrl = $web . '/front/document.form.php?id=' . $id;
                    $archiveUrl = $web . '/front/archive.php?id=' . $id;

                    $actions = "<div class='zimmet-list-actions'>";
                    $actions .= "<a class='btn btn-sm btn-outline-primary' target='_blank' title='PDF görüntüle' href='"
                        . htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-file-type-pdf'></i></a>";
                    $actions .= "<a class='btn btn-sm btn-outline-secondary' target='_blank' title='Yazdır' href='"
                        . htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-printer'></i></a>";
                    $actions .= "<a class='btn btn-sm btn-outline-primary' title='Düzenle' href='"
                        . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-edit'></i></a>";
                    if (Session::haveRight('plugin_zimmet_document', UPDATE)) {
                        $actions .= "<a class='btn btn-sm btn-outline-success' title='Arşivle' href='"
                            . htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-archive'></i></a>";
                    }
                    $actions .= "</div>";

                    return $actions;
                }
                break;
            case 'doc_type':
                $types = PluginZimmetTemplate::getDocTypes();
                return $types[$values[$field]] ?? $values[$field];
            case 'status':
                $statuses = self::getStatuses();
                return $statuses[$values[$field]] ?? $values[$field];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Bir tutanak için PDF veri dizisini hazırlar (şablon + kişi + satırlar).
     *
     * @param integer $id
     *
     * @return array|false
     */
    public static function buildPdfData($id)
    {
        $doc = new self();
        if (!$doc->getFromDB($id)) {
            return false;
        }

        $doc_type = $doc->fields['doc_type'];
        $tpl = null;
        if (!empty($doc->fields['plugin_zimmet_templates_id'])) {
            $tpl = new PluginZimmetTemplate();
            if (!$tpl->getFromDB((int) $doc->fields['plugin_zimmet_templates_id'])) {
                $tpl = null;
            }
        }
        if (!$tpl) {
            $tpl = PluginZimmetTemplate::getForEntity($doc->fields['entities_id'], $doc_type);
        }

        $userinfo = self::getUserInfo($doc->fields['users_id']);

        // Teslim eden adı + ünvanı
        $techName  = '';
        $techTitle = '';
        if ($doc->fields['tech_users_id']) {
            $techInfo  = self::getUserInfo($doc->fields['tech_users_id']);
            $techName  = $techInfo['fullname'];
            $techTitle = $techInfo['job_title'];
        }

        // Satırlar
        $lines = PluginZimmetDocumentItem::getForDocument($id);

        // Tarih: tutanak tarihi
        if (!empty($doc->fields['document_date'])) {
            $dateStr = date('d.m.Y', strtotime($doc->fields['document_date']));
        } elseif (!empty($doc->fields['date_creation'])) {
            $dateStr = date('d.m.Y', strtotime($doc->fields['date_creation']));
        } else {
            $dateStr = date('d.m.Y');
        }

        // Taahhütname metni: şablondan al, GLPI sanitize'ını geri al (TCPDF
        // ham HTML bekler), boşsa varsayılan metne düş.
        $commitment = $tpl ? (string) $tpl->fields['commitment_text'] : '';
        if (class_exists('\\Glpi\\Toolbox\\Sanitizer')) {
            $commitment = \Glpi\Toolbox\Sanitizer::unsanitize($commitment);
        } else {
            $commitment = html_entity_decode($commitment, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (trim(strip_tags($commitment)) === '') {
            $commitment = ($doc_type === 'tesellum')
                ? PluginZimmetTemplate::getDefaultReturnText()
                : PluginZimmetTemplate::getDefaultCommitmentText();
        }

        // Logo: şablonda tanımlıysa tam dosya yolunu çöz
        $logoFull = '';
        if ($tpl && !empty($tpl->fields['logo_path'])) {
            $candidate = GLPI_PLUGIN_DOC_DIR . '/zimmet/' . $tpl->fields['logo_path'];
            if (is_file($candidate)) {
                $logoFull = $candidate;
            }
        }

        return [
            'doc_type'        => $doc_type,
            'header_title'    => $tpl ? $tpl->fields['header_title'] : 'Şirket Adı A.Ş.',
            'header_subtitle' => $tpl ? $tpl->fields['header_subtitle'] : 'BİLGİ İŞLEM & İNSAN KAYNAKLARI',
            'logo_path'       => $logoFull,
            'receipt_no'      => $doc->fields['document_no'] ?: '',
            'document_no'     => $tpl ? $tpl->fields['document_no'] : '',
            'revision'        => $tpl ? $tpl->fields['revision'] : '',
            'revision_date'   => $tpl && $tpl->fields['revision_date']
                ? date('d.m.Y', strtotime($tpl->fields['revision_date'])) : '',
            'commitment_text' => $commitment,
            'fullname'        => $userinfo['fullname'],
            'department'      => $userinfo['department'],
            'job_title'       => $userinfo['job_title'],
            'tech_name'       => $techName,
            'tech_title'      => $techTitle,
            'date_str'        => $dateStr,
            'lines'           => $lines,
        ];
    }

    /**
     * Bir kişi için, o anki zimmetli cihazlarını otomatik çekerek
     * tutanak kaydı (+ snapshot satırları) oluşturur. Toplu üretimde kullanılır.
     *
     * @param integer $users_id
     * @param string  $doc_type
     * @param integer $tech_users_id  Teslim eden (0 = oturum kullanıcısı)
     *
     * @return integer|false  Oluşturulan tutanak ID'si
     */
    public static function createForUser($users_id, $doc_type = 'zimmet', $tech_users_id = 0)
    {
        $assets = self::getUserAssets($users_id);

        $doc = new self();
        $newID = $doc->add([
            'users_id'      => $users_id,
            'doc_type'      => $doc_type,
            'tech_users_id' => $tech_users_id ?: Session::getLoginUserID(),
            'status'        => 'draft',
        ]);

        if (!$newID) {
            return false;
        }

        // Cihaz satırlarını snapshot olarak yaz
        $order = 0;
        $child = new PluginZimmetDocumentItem();
        foreach ($assets as $asset) {
            $child->add(PluginZimmetDocumentItem::buildSnapshot($asset, $newID, $order++, 1, 'Adet'));
        }

        return $newID;
    }

    /**
     * Bir veya birden çok tutanağı tek PDF olarak üretir.
     *
     * @param array $ids  Tutanak ID dizisi
     *
     * @return string|false  PDF içeriği (binary string)
     */
    public static function generatePdf(array $ids)
    {
        if (empty($ids)) {
            return false;
        }

        $font = PluginZimmetConfig::getValue('pdf_font', 'dejavusans');
        $pdf  = new PluginZimmetPdf($font);

        $added = 0;
        foreach ($ids as $id) {
            $data = self::buildPdfData((int) $id);
            if ($data === false) {
                continue;
            }
            $pdf->addDocumentPage($data);
            $added++;
        }

        if ($added === 0) {
            return false;
        }

        return $pdf->getContent();
    }
}
