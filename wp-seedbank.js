WP_SEEDBANK = {};
WP_SEEDBANK.UI = {};

// User Interface functions.
WP_SEEDBANK.UI.attachDatepicker = function () {
    var x = jQuery('.datepicker');
    if (x.length) {
        x.each(function () {
            jQuery(this).datepicker();
        });
    }
};

WP_SEEDBANK.UI.hideDeletedExchangeRequests = function () {
    var el = document.createElement('button');
    el.innerHTML = 'Hide Deleted Seed Exchanges';
    jQuery(el).click(function (e) {
        e.preventDefault();
        jQuery('.seedbank_exchange_status').each(function() {
            if(jQuery(this).text() != "Active") {
                jQuery(this).parent().hide();
            }
        });
        WP_SEEDBANK.UI.toggleDeletedExchangePostVisibility('show');
        jQuery(this).remove();
    });
    var x = document.getElementById('post-query-submit');
    if (x && x.parentNode) {
        x.parentNode.appendChild(el);
    }
};
WP_SEEDBANK.UI.showDeletedExchangeRequests = function () {
    var el = document.createElement('button');
    el.innerHTML = 'Show Deleted Seed Exchanges';
    jQuery(el).click(function (e) {
        e.preventDefault();
        jQuery('.seedbank_exchange_status').each(function() {
            if(jQuery(this).text() == "Deleted") {
                jQuery(this).parent().show();
            }
        });
        jQuery(this).remove();
        WP_SEEDBANK.UI.toggleDeletedExchangePostVisibility('hide');
    });
    var x = document.getElementById('post-query-submit');
    if (x && x.parentNode) {
        x.parentNode.appendChild(el);
    }
};

WP_SEEDBANK.UI.toggleDeletedExchangePostVisibility = function (state) {
    switch (state) {
        case 'hide':
            WP_SEEDBANK.UI.hideDeletedExchangeRequests();
        break;
        case 'show':
            WP_SEEDBANK.UI.showDeletedExchangeRequests();
        break;
    }
};

WP_SEEDBANK.UI.toggleBatchExchangeDataSource = function () {
    jQuery('#seedbank-batch-exchange-file-upload').hide();
    jQuery('#seedbank-batch-exchange-data-source').change(function (e) {
        var x = e.target.querySelectorAll('option');
        for (var i = 0; i < x.length; i++) {
            if (x[i].selected) {
                switch (x[i].value) {
                    case 'another website':
                        jQuery('#seedbank-batch-exchange-file-upload').hide();
                        jQuery('#seedbank-batch-exchange-web-fetch').show();
                    break;
                    case 'my computer':
                        jQuery('#seedbank-batch-exchange-file-upload').show();
                        jQuery('#seedbank-batch-exchange-web-fetch').hide();
                    break;
                }
            }
        }
    });
}

WP_SEEDBANK.UI.prefillScientificName = function () {
    jQuery('.taxonomy-seedbank_scientific_name #parent').change(function () {
        var x = jQuery(this);
        var n = x.children('option:selected').text().trim();
        var t = jQuery('#tag-name');
        // If not "None" or does not begin with what's being selected
        if (x.val().trim() !== '-1' && t.val().trim().indexOf(n) !== 0) {
            t.val(n + ' ' + t.val());
        }
    });
};

WP_SEEDBANK.init = function () {
    // TODO: Run these only on the appropriate page.
    WP_SEEDBANK.UI.attachDatepicker();
    WP_SEEDBANK.UI.toggleBatchExchangeDataSource();
    // TODO: Filter these out of the result set from the PHP at some point, eh?
    WP_SEEDBANK.UI.toggleDeletedExchangePostVisibility('hide');
    WP_SEEDBANK.UI.prefillScientificName();
};

window.addEventListener('DOMContentLoaded', WP_SEEDBANK.init);
