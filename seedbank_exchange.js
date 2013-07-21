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

WP_SEEDBANK.init = function () {
    // TODO: Filter these out of the result set from the PHP at some point, eh?
    WP_SEEDBANK.UI.hideDeletedExchangeRequests();
};
jQuery(window).load(function() {
    WP_SEEDBANK.init();
});
