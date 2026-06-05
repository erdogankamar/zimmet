<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Kurum bazlı şablon (başlık, doküman no, taahhüt metni)
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
 * Zimmet / Teslim-Tesellüm tutanağı şablonu.
 *
 * Kurum (entity) bazlı başlık, logo, doküman numarası, revizyon ve
 * taahhütname metnini tutar. Metinler arayüzden düzenlenebilir.
 */
class PluginZimmetTemplate extends CommonDBTM
{
    public static $rightname = 'plugin_zimmet_config';

    public $dohistory = true;

    // config yetkisi yalnızca UPDATE bit'i içerir; READ tabanlı varsayılan
    // can* kontrolleri false döner. Bu yüzden görüntüleme/oluşturmayı da
    // UPDATE yetkisine bağlıyoruz (aksi halde formlar boş gelir).
    public static function canView()
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function canCreate()
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public function canViewItem()
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Handover template', 'Handover templates', $nb, 'zimmet');
    }

    public static function getIcon()
    {
        return 'ti ti-file-text';
    }

    public function isEntityAssign()
    {
        return true;
    }

    /**
     * Belge tiplerinin etiketleri.
     *
     * @return array
     */
    public static function getDocTypes()
    {
        return [
            'zimmet'   => __('Asset handover record', 'zimmet'),
            'tesellum' => __('Asset return record', 'zimmet'),
        ];
    }

    /**
     * Verilen entity ve belge tipi için en uygun aktif şablonu döndürür.
     * Önce tam entity eşleşmesi, yoksa üst entity'ler (recursive), yoksa
     * genel (entities_id = 0) şablon seçilir.
     *
     * @param integer $entities_id
     * @param string  $doc_type
     *
     * @return PluginZimmetTemplate|null
     */
    public static function getForEntity($entities_id, $doc_type = 'zimmet')
    {
        /** @var DBmysql $DB */
        global $DB;

        $entities_id = (int) $entities_id;
        $ancestors = getAncestorsOf('glpi_entities', $entities_id);
        $candidates = [$entities_id];
        foreach ($ancestors as $ancestor_id) {
            $ancestor_id = (int) $ancestor_id;
            if ($ancestor_id !== $entities_id && $ancestor_id !== 0) {
                $candidates[] = $ancestor_id;
            }
        }
        $candidates[] = 0;
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $entity_id) {
            foreach ([1, 0] as $default_only) {
                $where = [
                    'doc_type'    => $doc_type,
                    'is_active'   => 1,
                    'entities_id' => $entity_id,
                ];
                if ($default_only) {
                    $where['is_default'] = 1;
                }

                $iterator = $DB->request([
                    'FROM'  => self::getTable(),
                    'WHERE' => $where,
                    'ORDER' => ['id ASC'],
                    'LIMIT' => 1,
                ]);

                if (count($iterator)) {
                    $tpl = new self();
                    $tpl->getFromResultSet($iterator->current());
                    return $tpl;
                }
            }
        }

        return null;
    }

    /**
     * Kurulumda/elle varsayılan şablonları oluşturur (genel + iki belge tipi).
     *
     * @return integer  Oluşturulan şablon sayısı
     */
    public static function createDefaultTemplates()
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return 0;
        }
        if (countElementsInTable(self::getTable()) > 0) {
            return 0;
        }

        $now  = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $rows = [
            [
                'name'            => 'Varsayılan Zimmet Şablonu',
                'entities_id'     => 0,
                'is_recursive'    => 1,
                'doc_type'        => 'zimmet',
                'header_title'    => 'Şirket Adı A.Ş.',
                'header_subtitle' => 'BİLGİ İŞLEM & İNSAN KAYNAKLARI',
                'document_no'     => 'ZT.001',
                'revision'        => '00',
                'revision_date'   => date('Y-m-d'),
                'commitment_text' => self::getDefaultCommitmentText(),
                'is_active'       => 1,
                'is_default'      => 1,
                'date_creation'   => $now,
                'date_mod'        => $now,
            ],
            [
                'name'            => 'Varsayılan Teslim-Tesellüm Şablonu',
                'entities_id'     => 0,
                'is_recursive'    => 1,
                'doc_type'        => 'tesellum',
                'header_title'    => 'Şirket Adı A.Ş.',
                'header_subtitle' => 'BİLGİ İŞLEM & İNSAN KAYNAKLARI',
                'document_no'     => 'TT.001',
                'revision'        => '00',
                'revision_date'   => date('Y-m-d'),
                'commitment_text' => self::getDefaultReturnText(),
                'is_active'       => 1,
                'is_default'      => 1,
                'date_creation'   => $now,
                'date_mod'        => $now,
            ],
        ];

        // CommonDBTM::add() yerine doğrudan DB insert: kurulum/sunucu
        // farklılıklarından (rights, prepareInput, history) bağımsız çalışır.
        $created = 0;
        foreach ($rows as $row) {
            if ($DB->insert(self::getTable(), $row)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Zimmet tutanağı varsayılan taahhütname metni.
     *
     * @return string
     */
    public static function getDefaultCommitmentText()
    {
        return <<<'TEXT'
<p>Aşağıda yer alan iş telefonu ve hattını görev tanımım kapsamında ve görevim gerekleri doğrultusunda kullanmak üzere çalışır, sağlam ve hasarsız durumda teslim aldım.</p>
<p>Tarafıma teslim ve zimmet edilen bilgisayar, telefon ve hattı ile yasal mevzuatlarda belirlenen suç veya kabahat sayılan eylemleri (İşbu sayımla sınırlı kalmamak kaydıyla hakaret, tehdit, haksız rekabet suçlarını meydana getirebilecek eylemler, dolandırıcılık, pornografi, terör veya herhangi bir oluşumu destekleyecek içerikli eylemler, fikir sanat eserlerinin telif haklarına aykırı olarak yüklenmesi vb.) işlemeyeceğimi, İşveren'imin ticari itibarını zedeleyici herhangi bir eylem veya fiilde bulunmayacağımı, bunun yanında iş telefonu veya hattının hukuka aykırı kullanımının gerçekleşmesine engel olacak tüm tedbirleri alacağımı, bu tip ve/veya benzer eylemler sonucunda her türlü hukuki, cezai ve idari sorumluluğun tarafıma ait olduğunu, İşveren'im tarafından yalnızca görevimin gereklerini ifa etmem için tarafıma teslim edilen iş telefonu veya hattımı, görev tanımımın kapsamı dışında ve hukuka uygun olmayan fiillerde kullanmam nedeniyle İşveren'imin hiçbir hukuki, cezai ve idari sorumluluğunun bulunmadığını kabul beyan ve taahhüt ederim.</p>
<p>İşbu taahhütlerime aykırı herhangi bir davranışım ve hukuka aykırı eylemlerim nedeniyle İşveren'ime herhangi bir cezai veya idari yaptırım uygulanması, her ne ad altında olursa olsun üçüncü kişi veya kuruluşlara bir ödeme yapılması halinde, bu ödemeyi ve İşveren'imin her türlü maddi ve manevi zararını hiçbir ihtar ve ihbara gerek kalmaksızın İşveren'ime ödeyeceğimi ve tazmin edeceğimi, bu miktarların ücretimden İşveren'imin takdirine göre tek bir seferde yahut peyderpey kesilebileceğini bildiğimi ve bu hususta ücretinden kesinti yapılmasına peşinen muvafakat ettiğimi, İşveren'imin iş akdimi haklı nedenle, tazminatsız ve derhal feshetme hakkı ile tarafıma her türlü rücu hakkının bulunduğunu, tüm bunların yanında işbu taahhüde aykırı herhangi bir davranış ve eylemde bulunmam nedeniyle, en son almakta olduğum brüt ücretin 12 (oniki) aylık tutarını ayrıca bir ihtara lüzum kalmaksızın cezai şart olarak İşveren'ime ödeyeceğimi kabul, beyan ve taahhüt ederim.</p>
<p>İşbu Bilgisayar, İş Telefonu ve Hattı Zimmet Tutanağı ve Taahhütnameyi okudum, anladım ve bir suretini tebliğ aldım. Tutanak ve taahhütnamede açıklanan kurallara ve sonuçlarına eksiksiz olarak uyacağımı kabul, beyan ve taahhüt ederim. Aksi takdirde doğabilecek her türlü hukuki, cezai ve idari sorumluluklar ile İşveren'imin maddi ve manevi zararlarının tazmini tarafıma ait olacaktır. İşbu tutanak ve taahhütnameyi tam sıhhatte olarak, kendi rızamla isteyerek ve bilerek imzaladım.</p>
TEXT;
    }

    /**
     * Teslim-Tesellüm (iade) tutanağı varsayılan metni.
     *
     * @return string
     */
    public static function getDefaultReturnText()
    {
        return <<<'TEXT'
<p>Aşağıda cinsi ve özellikleri belirtilen, tarafıma zimmetlenmiş ekipmanları çalışır / belirtilen durumda, eksiksiz olarak İşveren'ime iade ettim.</p>
<p>İade edilen ekipmanların teslim anındaki durumu işbu tutanakta belirtilmiş olup, taraflar tutanağın içeriğini okuyarak ve kabul ederek imzalamıştır.</p>
TEXT;
    }

    /**
     * Şablon oluşturma / düzenleme formu (TinyMCE ile taahhüt metni).
     *
     * @param integer $ID
     * @param array   $options
     *
     * @return boolean
     */
    public function showForm($ID, array $options = [])
    {
        // config yetkisi yalnızca UPDATE bit'i içeriyor; canView() READ arar
        // ve false döner. Bu yüzden doğrudan UPDATE kontrol ediyoruz.
        if (!Session::haveRight(self::$rightname, UPDATE)) {
            return false;
        }

        if ($ID > 0) {
            $this->getFromDB($ID);
        } else {
            $this->getEmpty();
            $this->fields['commitment_text'] = self::getDefaultCommitmentText();
            $this->fields['document_no']     = 'BGYSFR.43';
            $this->fields['revision']        = '00';
            $this->fields['header_subtitle'] = 'BİLGİ İŞLEM & İNSAN KAYNAKLARI';
            $this->fields['is_default']      = 0;
            $this->fields['is_active']       = 1;
        }

        // Logo yüklemesi için form multipart olmalı
        $options['formoptions'] = "enctype='multipart/form-data'";

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Ad
        echo "<tr class='tab_bg_1'><td>" . __('Name') . " <span class='red'>*</span></td><td>";
        echo Html::input('name', ['value' => $this->fields['name']]);
        echo "</td>";

        // Belge tipi
        echo "<td>" . __('Document type', 'zimmet') . "</td><td>";
        Dropdown::showFromArray('doc_type', self::getDocTypes(), [
            'value' => $this->fields['doc_type'],
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . Entity::getTypeName(1) . "</td><td>";
        Entity::dropdown([
            'name'  => 'entities_id',
            'value' => $this->fields['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0),
        ]);
        echo "</td>";
        echo "<td>Varsayılan şablon</td><td>";
        Dropdown::showYesNo('is_default', $this->fields['is_default'] ?? 0);
        echo "<div class='text-muted' style='font-size:.85rem'>Seçilen firma ve belge tipi için PDF'te otomatik kullanılacak şablon.</div>";
        echo "</td></tr>";

        // Başlık / alt başlık
        echo "<tr class='tab_bg_1'><td>" . __('Header title', 'zimmet') . "</td><td>";
        echo Html::input('header_title', ['value' => $this->fields['header_title'], 'size' => 50]);
        echo "</td>";
        echo "<td>" . __('Header subtitle', 'zimmet') . "</td><td>";
        echo Html::input('header_subtitle', ['value' => $this->fields['header_subtitle'], 'size' => 50]);
        echo "</td></tr>";

        // Doküman no / revizyon / revizyon tarihi
        echo "<tr class='tab_bg_1'><td>" . __('Document number', 'zimmet') . "</td><td>";
        echo Html::input('document_no', ['value' => $this->fields['document_no']]);
        echo "</td>";
        echo "<td>" . __('Revision', 'zimmet') . "</td><td>";
        echo Html::input('revision', ['value' => $this->fields['revision']]);
        echo "&nbsp;&nbsp;" . __('Revision date', 'zimmet') . "&nbsp;";
        Html::showDateField('revision_date', ['value' => $this->fields['revision_date']]);
        echo "</td></tr>";

        // Logo (kurum logosu — PDF başlığında görünür)
        echo "<tr class='tab_bg_1'><td>" . __('Company logo', 'zimmet') . "</td><td>";
        if (!empty($this->fields['logo_path'])) {
            $logoUrl = Plugin::getWebDir('zimmet') . '/front/logo.php?id=' . $ID;
            echo "<div class='mb-2'><img src='" . htmlspecialchars($logoUrl)
                . "' style='max-height:60px;max-width:200px;border:1px solid #ddd;padding:4px'></div>";
            echo "<label><input type='checkbox' name='_delete_logo' value='1'> "
                . __('Remove current logo', 'zimmet') . "</label><br>";
        }
        echo "<input type='file' name='logo_file' accept='image/png,image/jpeg,image/gif'>";
        echo "<div class='text-muted' style='font-size:.85rem'>"
            . __('PNG/JPG/GIF. Shown at the top-left of the PDF header.', 'zimmet') . "</div>";
        echo "</td>";

        // Aktif
        echo "<td>" . __('Active') . "</td><td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active']);
        echo "</td></tr>";

        // Taahhütname metni (zengin metin editörü)
        echo "<tr class='tab_bg_1'><td colspan='4'><strong>"
            . __('Commitment / declaration text', 'zimmet') . "</strong></td></tr>";
        echo "<tr class='tab_bg_1'><td colspan='4'>";
        Html::textarea([
            'name'            => 'commitment_text',
            'value'           => $this->fields['commitment_text'],
            'enable_richtext' => true,
            'enable_images'   => false,
            'editor_id'       => 'zimmet_commitment_' . $ID,
            'rows'            => 12,
            'cols'            => 120,
        ]);
        echo "</td></tr>";

        $this->showFormButtons($options);

        return true;
    }

    public function post_addItem()
    {
        $this->normalizeDefaultTemplate();
    }

    public function post_updateItem($history = 1)
    {
        $this->normalizeDefaultTemplate();
    }

    private function normalizeDefaultTemplate()
    {
        if (empty($this->fields['is_default'])) {
            return;
        }

        /** @var DBmysql $DB */
        global $DB;

        $DB->update(
            self::getTable(),
            ['is_default' => 0],
            [
                'entities_id' => (int) ($this->fields['entities_id'] ?? 0),
                'doc_type'    => $this->fields['doc_type'] ?? 'zimmet',
                'id'          => ['<>', $this->getID()],
            ]
        );
    }

    /**
     * Arama seçenekleri.
     */
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];

        $tab[] = [
            'id'            => '1',
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
            'display'       => true,
        ];
        $tab[] = [
            'id'         => '2',
            'table'      => self::getTable(),
            'field'      => 'doc_type',
            'name'       => __('Document type', 'zimmet'),
            'datatype'   => 'specific',
            'searchtype' => ['equals', 'notequals'],
            'display'    => true,
        ];
        $tab[] = [
            'id'            => '3',
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => Entity::getTypeName(1),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
            'display'       => true,
        ];
        $tab[] = [
            'id'      => '4',
            'table'   => self::getTable(),
            'field'   => 'document_no',
            'name'    => __('Document number', 'zimmet'),
            'display' => true,
        ];
        $tab[] = [
            'id'       => '5',
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
            'display'  => true,
        ];
        $tab[] = [
            'id'       => '6',
            'table'    => self::getTable(),
            'field'    => 'is_default',
            'name'     => 'Varsayılan şablon',
            'datatype' => 'bool',
            'display'  => true,
        ];
        $tab[] = [
            'id'      => '7',
            'table'   => self::getTable(),
            'field'   => 'revision',
            'name'    => __('Revision', 'zimmet'),
            'display' => true,
        ];
        $tab[] = [
            'id'            => '8',
            'table'         => self::getTable(),
            'field'         => 'revision_date',
            'name'          => __('Revision date', 'zimmet'),
            'datatype'      => 'date',
            'massiveaction' => false,
            'display'       => true,
        ];
        $tab[] = [
            'id'            => '19',
            'table'         => self::getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
            'display'       => true,
        ];
        $tab[] = [
            'id'            => '121',
            'table'         => self::getTable(),
            'field'         => 'date_creation',
            'name'          => __('Creation date'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
            'display'       => true,
        ];

        return $tab;
    }

    /**
     * Arama sonuçlarında teknik değerleri okunur etiketlere çevirir.
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        if ($field === 'doc_type') {
            $types = self::getDocTypes();
            return $types[$values[$field]] ?? $values[$field];
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Şablon listesi ilk açıldığında kurumsal takip kolonlarını göster.
     */
    public static function getDefaultSearchRequest()
    {
        return [
            'sort'  => 3,
            'order' => 'ASC',
        ];
    }
}
