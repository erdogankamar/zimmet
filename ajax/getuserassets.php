<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — AJAX: bir kullanıcının zimmetli cihazlarını getir
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_zimmet_document', READ);

header('Content-Type: text/html; charset=UTF-8');

$users_id = (int) ($_GET['users_id'] ?? 0);

if ($users_id <= 0) {
    echo "<tr class='tab_bg_1'><td colspan='8' class='center'>"
        . __('Please select a person', 'zimmet') . "</td></tr>";
    return;
}

$doc_type = $_GET['doc_type'] ?? 'zimmet';

$assets   = PluginZimmetDocument::getUserAssets($users_id);
$userinfo = PluginZimmetDocument::getUserInfo($users_id);

// Gizli veri satırı: formun departman alanını doldurur
echo "<tr style='display:none' data-docinfo "
    . "data-department='" . htmlspecialchars((string) $userinfo['title']) . "'></tr>";

if (empty($assets)) {
    echo "<tr class='tab_bg_1'><td colspan='8' class='center'>"
        . sprintf(
            __('No asset assigned to %s. You may add manual lines.', 'zimmet'),
            htmlspecialchars($userinfo['fullname'])
        )
        . "</td></tr>";
    return;
}

$i = 0;
foreach ($assets as $asset) {
    $key = $asset['itemtype'] . '_' . $asset['items_id'];
    echo "<tr class='tab_bg_1' data-key='" . htmlspecialchars($key) . "'>";

    // Seç
    echo "<td class='center'>"
        . "<input type='checkbox' name='lines[$key][use]' value='1' checked>"
        . "<input type='hidden' name='lines[$key][itemtype]' value='"
        . htmlspecialchars($asset['itemtype']) . "'>"
        . "<input type='hidden' name='lines[$key][items_id]' value='"
        . (int) $asset['items_id'] . "'>"
        . "<input type='hidden' name='lines[$key][is_manual]' value='0'>"
        . "</td>";

    // Ekipman adı (getUserAssets zaten "Tür: Marka Model" biçiminde verir)
    $name = $asset['item_name'];
    echo "<td><input type='text' class='form-control form-control-sm' "
        . "name='lines[$key][item_name]' value='" . htmlspecialchars($name) . "'></td>";

    // Seri No
    echo "<td><input type='text' class='form-control form-control-sm' "
        . "name='lines[$key][serial]' value='" . htmlspecialchars($asset['serial']) . "'></td>";

    // Stok / Envanter No
    echo "<td><input type='text' class='form-control form-control-sm' "
        . "name='lines[$key][otherserial]' value='" . htmlspecialchars($asset['otherserial']) . "'></td>";

    // Durum
    echo "<td><input type='text' class='form-control form-control-sm' "
        . "name='lines[$key][state_name]' value='" . htmlspecialchars($asset['state_name']) . "'></td>";

    // Miktar
    echo "<td><input type='number' step='0.01' class='form-control form-control-sm' "
        . "name='lines[$key][quantity]' value='1' style='width:80px'></td>";

    // Cinsi
    echo "<td><input type='text' class='form-control form-control-sm' "
        . "name='lines[$key][unit]' value='Adet' style='width:90px'></td>";

    // Sil
    echo "<td class='center'><button type='button' class='btn btn-sm btn-outline-danger' "
        . "onclick='ZimmetPlugin.removeRow(this)'><i class='ti ti-trash'></i></button></td>";

    echo "</tr>";
    $i++;
}
