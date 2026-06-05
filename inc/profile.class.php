<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — Profil / yetki yönetimi
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
 * Profil sekmesinde zimmet yetkilerini gösterir ve yönetir.
 */
class PluginZimmetProfile extends Profile
{
    public static $rightname = 'profile';

    /**
     * Bu plugin'in tanımladığı tüm yetkiler.
     *
     * @return array
     */
    public function getAllRights()
    {
        return [
            [
                'rights' => Profile::getRightsFor('PluginZimmetDocument'),
                'label'  => __('Handover documents', 'zimmet'),
                'field'  => 'plugin_zimmet_document',
            ],
            [
                'rights' => [UPDATE => __('Update')],
                'label'  => __('Manage configuration', 'zimmet'),
                'field'  => 'plugin_zimmet_config',
            ],
        ];
    }

    /**
     * Profile nesnesi için sekme başlığı.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return self::createTabEntry(__('Zimmet', 'zimmet'));
        }
        return '';
    }

    /**
     * Profile sekmesi içeriği.
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if ($item instanceof Profile && $item->getField('id')) {
            $profile = new self();
            $profile->showForm($item->getField('id'));
        }
        return true;
    }

    /**
     * Yetki düzenleme formu.
     */
    public function showForm($profiles_id = 0, array $options = [])
    {
        echo "<div class='spaced'>";
        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        $rights = $this->getAllRights();
        $profile->displayRightsChoiceMatrix($rights, [
            'canedit'       => self::canUpdate(),
            'default_class' => 'tab_bg_2',
            'title'         => __('Zimmet', 'zimmet'),
        ]);
        echo "</div>";
    }

    /**
     * Kurulumda yetkileri tüm profillere ekler, aktif profile tam yetki verir.
     *
     * @return void
     */
    public static function initProfile()
    {
        $pfProfile = new self();
        foreach ($pfProfile->getAllRights() as $data) {
            if (!countElementsInTable(
                'glpi_profilerights',
                ['name' => $data['field']]
            )) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

        // Aktif (kuran) profile tam yetki ver
        if (isset($_SESSION['glpiactiveprofile']['id'])) {
            self::addDefaultProfileInfos(
                $_SESSION['glpiactiveprofile']['id'],
                [
                    'plugin_zimmet_document' => ALLSTANDARDRIGHT | READNOTE | UPDATENOTE,
                    'plugin_zimmet_config'   => UPDATE,
                ],
                true
            );
        }
    }

    /**
     * Bir profile varsayılan yetkileri ekler.
     *
     * @param integer $profiles_id
     * @param array   $rights
     * @param boolean $drop_existing
     *
     * @return void
     */
    public static function addDefaultProfileInfos(
        $profiles_id,
        $rights,
        $drop_existing = false
    ) {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (countElementsInTable(
                'glpi_profilerights',
                ['profiles_id' => $profiles_id, 'name' => $right]
            ) && $drop_existing) {
                $profileRight->deleteByCriteria([
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                ]);
            }
            if (!countElementsInTable(
                'glpi_profilerights',
                ['profiles_id' => $profiles_id, 'name' => $right]
            )) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $value,
                ]);
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    /**
     * Kaldırmada tüm yetkileri siler.
     *
     * @return void
     */
    public static function removeRights()
    {
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
            ProfileRight::deleteProfileRights([$right['field']]);
        }
    }

    /**
     * Profil silinince yetki temizliği (pre_item_purge kancası).
     *
     * @param Profile $profile
     *
     * @return void
     */
    public static function cleanProfiles(Profile $profile)
    {
        $profileRight = new ProfileRight();
        $profileRight->deleteByCriteria([
            'profiles_id' => $profile->getField('id'),
            'name'        => ['LIKE', 'plugin_zimmet_%'],
        ]);
    }
}
