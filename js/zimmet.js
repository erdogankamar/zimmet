/* HSC Zimmet plugin — istemci tarafı yardımcılar */
/* global $ */

var ZimmetPlugin = {

    /**
     * Manuel (serbest) cihaz satırı ekler.
     */
    addManualRow: function (tableSelector) {
        var $table = $(tableSelector + ' tbody');
        var idx = 'manual_' + Date.now();
        var html =
            '<tr class="zimmet-manual-row" data-key="' + idx + '">' +
            '  <td><input type="checkbox" name="lines[' + idx + '][use]" value="1" checked></td>' +
            '  <td><input type="text" class="form-control form-control-sm" name="lines[' + idx + '][item_name]" placeholder="Ekipman adı"></td>' +
            '  <td><input type="text" class="form-control form-control-sm" name="lines[' + idx + '][serial]" placeholder="Seri No"></td>' +
            '  <td><input type="text" class="form-control form-control-sm" name="lines[' + idx + '][otherserial]" placeholder="Stok No"></td>' +
            '  <td><input type="text" class="form-control form-control-sm" name="lines[' + idx + '][state_name]" placeholder="Durum"></td>' +
            '  <td><input type="number" step="0.01" class="form-control form-control-sm" name="lines[' + idx + '][quantity]" value="1" style="width:80px"></td>' +
            '  <td><input type="text" class="form-control form-control-sm" name="lines[' + idx + '][unit]" value="Adet" style="width:90px"></td>' +
            '  <td><input type="hidden" name="lines[' + idx + '][is_manual]" value="1">' +
            '      <button type="button" class="btn btn-sm btn-outline-danger" onclick="ZimmetPlugin.removeRow(this)"><i class="ti ti-trash"></i></button></td>' +
            '</tr>';
        $table.append(html);
    },

    removeRow: function (btn) {
        $(btn).closest('tr').remove();
    },

    /**
     * Personel seçilince zimmetli cihazları AJAX ile yükler.
     */
    loadUserAssets: function (rootUrl, usersId, targetSelector) {
        if (!usersId) {
            return;
        }
        var docType = $('select[name=doc_type]').val() || 'zimmet';
        $.ajax({
            url: rootUrl + '/ajax/getuserassets.php',
            type: 'GET',
            data: { users_id: usersId, doc_type: docType },
            dataType: 'html'
        }).done(function (html) {
            $(targetSelector).html(html);

            // Departman alanını otomatik doldur
            var $info = $(targetSelector).find('[data-docinfo]');
            if ($info.length) {
                $('#zimmet-dept-display').val($info.data('department') || '');
            }
        });
    }
};
