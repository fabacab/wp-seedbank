WP_SEEDBANK = {};
WP_SEEDBANK.UI = {};

// User Interface functions.
WP_SEEDBANK.UI.hideDeletedExchangeRequests = function () {
    var el = document.createElement('button');
    el.innerHTML = 'Hide Deleted Seed Exchanges';
    jQuery(el).click(function (e) {
        e.preventDefault();
        jQuery('.idealien_rideshare_status').each(function() {
            if(jQuery(this).text() != "Active") {
                jQuery(this).parent().hide();
            }
        });
    });
    var x = document.getElementById('post-query-submit')
    if (x && x.parentNode) {
        parentNode.appendChild(el);
    }
};

WP_SEEDBANK.UI.toggleBatchExchangeDataSource = function () {
    document.querySelectorAll('#wp-seedbank-batch-exchange-form fieldset')[1].style.display = 'none';
    jQuery('#wp-seedbank-batch-exchange-data-source').change(function (e) {
        var x = e.target.querySelectorAll('option');
        for (var i = 0; i < x.length; i++) {
            if (x[i].selected) {
                switch (x[i].value) {
                    case 'another website':
                        jQuery('#wp-seedbank-batch-exchange-file-upload').hide();
                        jQuery('#wp-seedbank-batch-exchange-web-fetch').show();
                    break;
                    case 'my computer':
                        console.log('my-comp');
                        jQuery('#wp-seedbank-batch-exchange-file-upload').show();
                        jQuery('#wp-seedbank-batch-exchange-web-fetch').hide();
                    break;
                }
            }
        }
    });
}

WP_SEEDBANK.init = function () {
    // TODO: Filter these out of the result set from the PHP at some point, eh?
    WP_SEEDBANK.UI.hideDeletedExchangeRequests();
    WP_SEEDBANK.UI.toggleBatchExchangeDataSource();
};

window.addEventListener('DOMContentLoaded', WP_SEEDBANK.init);
