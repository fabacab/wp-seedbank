<?php
/*
    Plugin Name: WP-SeedBank
    Plugin URI: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/
    Description: Add a seed exchange post type to turn your WordPress website into a community seedbank or seed library! :D
    Author: Cleveland GiveCamp Developers
    Version: 0.3
    Author URI: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/#authors
    License: GPL
    Requires at least: 3.5.2
    Stable tag: 0.3
*/

// WordPress SeedBank works by making a new post type, "wp_seedbank".
// This new post type has its own post interface that provides a more
// structured way to create a post. It stores the following data in
// post meta fields:
//
//     * exchange action ("type"):
//     ** Swap
//     ** Sell
//     ** Give
//     ** Get
//     * quantity (a number)
//     * common name (arugula, lettuce, etc.)
//     * unit (seeds, packets, etc.)
//     * seed expiration date
//     * posting expiration date
//     * status:
//     ** Active
//     ** Inactive ("deleted")
//
// It also uses tags and categories to organize postings.
//
// In addition to the meta fields described above, an open text field
// for post content is also available.
//
// A preference setting should allow a template with no special meta
// field variables to be visible in a seed exchange post either above
// or below the general post content.

class WP_SeedBank {
    private $post_type = 'seedbank';
    private $textdomain = 'wp-seedbank';
    private $taxonomies = array(
        array('exchange_type'),
        array('common_name'),
        array('seed_genus', 'plural' => 'seed_genera'),
        array('exchange_status', 'plural' => 'exchange_statuses')
    );
//    private $meta_fields = array(
//        'type',
//        'quantity',
//        'common_name',
//        'unit',
//        'seed_expiry_date',
//    );

    public function __construct () {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('init', array($this, 'createDataTypes'));
        add_action('add_meta_boxes_' . $this->post_type, array($this, 'addMetaBoxes'));
    }

    public function createDataTypes () {
        $this->registerCustomPostType();
        $this->registerCustomTaxonomies();
    }

    private function registerCustomPostType () {
        $labels = array(
            'name'               => __('Seed Exchanges', $this->textdomain),
            'singular_name'      => __('Seed Exchange', $this->textdomain),
            'add_new'            => __('Add Seed Exchange', $this->textdomain),
            'add_new_item'       => __('Add Seed Exchange', $this->textdomain),
            'edit'               => __('Edit Seed Exchange', $this->textdomain),
            'edit_item'          => __('Edit Seed Exchange', $this->textdomain),
            'new_item'           => __('New Seed Exchange', $this->textdomain),
            'view'               => __('View Seed Exchange', $this->textdomain),
            'view_item'          => __('View Seed Exchange', $this->textdomain),
            'search'             => __('Search Seed Exchanges', $this->textdomain),
            'not_found'          => __('No Seed Exchanges found', $this->textdomain),
            'not_found_in_trash' => __('No Seed Exchanges found in trash', $this->textdomain)
        );
        $url_rewrites = array(
            'slug' => 'seed-exchange'
        );
        $args = array(
            'labels' => $labels,
            'description' => __('Postings to the SeedBank', $this->textdomain),
            'public' => true,
            'menu_icon' => plugins_url(basename(__DIR__) . '/seedexchange_icon.png'),
            'has_archive' => true,
            'supports' => array(
                'title',
                'editor',
                'author',
                'comments'
            ),
            'rewrite' => $url_rewrites
        );
        register_post_type($this->post_type, $args);
    }

    private function registerCustomTaxonomies () {
        foreach ($this->taxonomies as $taxonomy) {
            $pluralize  = (isset($taxonomy['plural'])) ? $taxonomy['plural'] : "{$taxonomy[0]}s";
            $t_plural   = ucwords(str_replace('_', ' ', $pluralize));
            $t_singular = ucwords(str_replace('_', ' ', $taxonomy[0]));
            register_taxonomy($this->post_type . '_' . $taxonomy[0], $this->post_type, array(
                    'labels' => array(
                        'name'          => __($t_plural, $this->textdomain),
                        'singular_name' => __($t_singular, $this->textdomain),
                        'all_items'     => __("All $t_plural", $this->textdomain),
                        'edit_item'     => __("Edit $t_singular", $this->textdomain),
                        'update_item'   => __("Edit $t_singular", $this->textdomain),
                        'add_new_item'  => __("Add New $t_singular", $this->textdomain),
                        'new_item_name' => __("New $t_singular", $this->textdomain),
                    )
                )
            );
            register_taxonomy_for_object_type($taxonomy[0], $this->post_type);
        }
    }

    public function addMetaBoxes ($post) {
        add_meta_box(
            $this->post_type . '-details-meta',
            __('Seed Exchange Details', $this->textdomain),
            array($this, 'renderMetaBoxDetails'),
            $this->post_type,
            'normal',
            'high'
        );
    }

    // TODO: i18n this stuff
    public function renderMetaBoxDetails () {
        global $post;

        wp_nonce_field('editing_' . $this->post_type, $this->post_type . '_meta_box_details_nonce');

        // Retrieve meta data fields, or set to initial blank slates.
        $custom = get_post_custom($post->ID);
        //var_dump($custom);

        // Create HTML for the drop-down menus.
        ob_start();
        $type_options = get_terms($this->post_type . '_exchange_type', 'hide_empty=0&order=ASC');
        print '<select id="' . $this->post_type . '-exchange-type" name="' . $this->post_type . '_exchange_type">';
        foreach ($type_options as $type) {
            if ($type->name == $custom["{$this->post_type}_exchange_type"][0]) {
                print '<option selected="selected" value="' . esc_attr($type->name) . '">' . esc_html(strtolower($type->name)) . "</option>\n";
            } else {
                print '<option value="' . esc_attr($type->name) . '">' . esc_html(strtolower($type->name)) . "</option>\n";
            }
        }
        print '</select>';
        $type_select = ob_get_contents();
        ob_end_clean();

        ob_start();
        $common_name_options = get_terms($this->post_type . '_common_name', 'hide_empty=0&order=ASC');
        print '<select id="' . $this->post_type . '-common-name" name="' . $this->post_type . '_common_name">';
        foreach ($common_name_options as $common_name) {
            if ($common_name->name == $custom["{$this->post_type}_common_name"][0]) {
                print '<option selected="selected" value="' . esc_attr($common_name->name) . '">' . esc_html($common_name->name) . "</option>\n";
            } else {
                print '<option value="' . esc_attr($common_name->name) . '">' . esc_html($common_name->name) . "</option>\n";
            }
        }
        print '</select>';
        $common_name_select = ob_get_contents();
        ob_end_clean();

        ob_start();
        $status_options = get_terms($this->post_type . '_exchange_status', 'hide_empty=0&order=ASC');
        print '<select id="' . $this->post_type . '-exchange-status" name="' . $this->post_type . '_exchange_status">';
        foreach ($status_options as $status) {
            if ($status->name == $custom["{$this->post_type}_exchange_status"][0]) {
                print '<option selected="selected" value="' . esc_attr($status->name) . '">' . esc_html($status->name) . "</option>\n";
            } else {
                print '<option value="' . esc_attr($status->name) . '">' . esc_html($status->name) . "</option>\n";
            }
        }
        print '</select>';
        $status_select = ob_get_contents();
        ob_end_clean();
?>
    <p>
        <label>I would like to <?php print $type_select;?></label>
        <input name="<?php print $this->post_type;?>_quantity" value="<?php print esc_attr($custom["{$this->post_type}_quantity"][0]);?>" placeholder="<?php _e('enter a number', $this->textdomain);?>" />
        <?php print $common_name_select;?>
        <input name="<?php print $this->post_type;?>_unit" value="<?php print esc_attr($custom["{$this->post_type}_unit"][0]);?>" placeholder="<?php _e('packets', $this->textdomain);?>" />.
    </p>
    <p>
        <label>These seeds will expire on or about <input name="<?php print $this->post_type;?>_seed_expiry_date" class="datepicker" value="<?php print esc_attr($custom["{$this->post_type}_seed_expiry_date"][0]);?>" />.</label> <span class="description">(<?php _e('If these seeds are in a packet, the wrapping might have an expiration date. Put that here.', $this->textdomain);?>)</span>
    </p>
    <p>
        <label>If I don't hear from anyone by <input name="<?php print $this->post_type;?>_exchange_expiry_date" class="datepicker" value="<?php print esc_attr($custom["{$this->post_type}_exchange_expiry_date"][0]);?>" />, I'll stop being available to make this exchange.</label> <span class="description">(<?php _e("If you don't get a response by this date, your request will automatically close.", $this->textdomain);?>)</span>
    </p>
    <p>
        <label>This seed exchange is <?php print $status_select;?>.</label> <span class="description">(<?php foreach ($status_options as $x) :?>The <code><?php _e($x->name, $this->textdomain);?></code> type is for <?php print strtolower($x->description);?> <?php endforeach;?>)</span>
    </p>
<?php
    }

    public function activate () {
        $this->createDataTypes();
        flush_rewrite_rules();

        // Exchange Types (verbs)
        wp_insert_term(__( 'Swap', $this->textdomain), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering seeds for other seeds.', $this->textdomain)));
        wp_insert_term(__( 'Sell', $this->textdomain), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering seeds for money.', $this->textdomain)));
        wp_insert_term(__( 'Give', $this->textdomain), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering free seeds being given away.', $this->textdomain)));
        wp_insert_term(__( 'Get', $this->textdomain), $this->post_type . '_exchange_type', array('description' => __('Exchanges requesting seeds of a variety not already listed.', $this->textdomain)));

        // Genera
        wp_insert_term(__('Abelmoschus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'abelmoschus' ) );
        wp_insert_term(__('Agastache', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'agastache' ) );
        wp_insert_term(__('Allium', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'allium' ) );
        wp_insert_term(__('Amaranthus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'amaranthus' ) );
        wp_insert_term(__('Anagallis', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'anagallis' ) );
        wp_insert_term(__('Anethum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'anethum' ) );
        wp_insert_term(__('Anthenum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'anthenum' ) );
        wp_insert_term(__('Antirrhinum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'antirrhinum' ) );
        wp_insert_term(__('Apium', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'apium' ) );
        wp_insert_term(__('Asclepias', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'asclepias' ) );
        wp_insert_term(__('Basella', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'basella' ) );
        wp_insert_term(__('Beta', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'beta' ) );
        wp_insert_term(__('Brassica', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'brassica' ) );
        wp_insert_term(__('Calendula', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'calendula' ) );
        wp_insert_term(__('Capsicum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'capsicum' ) );
        wp_insert_term(__('Cardiospermum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cardiospermum' ) );
        wp_insert_term(__('Centaurea', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'centaurea' ) );
        wp_insert_term(__('Chrysanthemum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'chrysanthemum' ) );
        wp_insert_term(__('Cichorium', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cichorium' ) );
        wp_insert_term(__('Citrullus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'citrullus' ) );
        wp_insert_term(__('Cleome', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cleome' ) );
        wp_insert_term(__('Cobaea', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cobaea' ) );
        wp_insert_term(__('Consolida', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'consolida' ) );
        wp_insert_term(__('Convolvulus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'convolvulus' ) );
        wp_insert_term(__('Coreopsis', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'coreopsis' ) );
        wp_insert_term(__('Coriandrum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'coriandrum' ) );
        wp_insert_term(__('Cosmos', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cosmos' ) );
        wp_insert_term(__('Cucumis', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cucumis' ) );
        wp_insert_term(__('Cucurbita', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'cucurbita' ) );
        wp_insert_term(__('Dalea', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'dalea' ) );
        wp_insert_term(__('Daucus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'daucus' ) );
        wp_insert_term(__('Diplotaxis', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'diplotaxis' ) );
        wp_insert_term(__('Dolichos', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'dolichos' ) );
        wp_insert_term(__('Echinacea', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'echinacea' ) );
        wp_insert_term(__('Eruca', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'eruca' ) );
        wp_insert_term(__('Eschscholzia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'eschscholzia' ) );
        wp_insert_term(__('Foeniculum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'foeniculum' ) );
        wp_insert_term(__('Fragaria', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'fragaria' ) );
        wp_insert_term(__('Gaillardia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'gaillardia' ) );
        wp_insert_term(__('Glycine', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'glycine' ) );
        wp_insert_term(__('Helianthus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'helianthus' ) );
        wp_insert_term(__('Ipomoea', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'ipomoea' ) );
        wp_insert_term(__('Koeleria', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'koeleria' ) );
        wp_insert_term(__('Lactuca', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'lactuca' ) );
        wp_insert_term(__('Lagenaria', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'lagenaria' ) );
        wp_insert_term(__('Lathyrus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'lathyrus' ) );
        wp_insert_term(__('Lupinus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'lupinus' ) );
        wp_insert_term(__('Lycopersicon', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'lycopersicon' ) );
        wp_insert_term(__('Malope', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'malope' ) );
        wp_insert_term(__('Matricaria', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'matricaria' ) );
        wp_insert_term(__('Mentha', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'mentha' ) );
        wp_insert_term(__('Mirabilis', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'mirabilis' ) );
        wp_insert_term(__('Nigella', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'nigella' ) );
        wp_insert_term(__('Ocimum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'ocimum' ) );
        wp_insert_term(__('Origanum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'origanum' ) );
        wp_insert_term(__('Papaver', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'papaver' ) );
        wp_insert_term(__('Passiflora', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'passiflora' ) );
        wp_insert_term(__('Penstemon', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'penstemon' ) );
        wp_insert_term(__('Petrolselinum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'petrolselinum' ) );
        wp_insert_term(__('Phaseolus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'phaseolus' ) );
        wp_insert_term(__('Physalis', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'physalis' ) );
        wp_insert_term(__('Pisum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'pisum' ) );
        wp_insert_term(__('Poterium', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'poterium' ) );
        wp_insert_term(__('Raphanus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'raphanus' ) );
        wp_insert_term(__('Rosmarinus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'rosmarinus' ) );
        wp_insert_term(__('Rudbeckia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'rudbeckia' ) );
        wp_insert_term(__('Salvia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'salvia' ) );
        wp_insert_term(__('Scorpiurus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'scorpiurus' ) );
        wp_insert_term(__('Solanum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'solanum' ) );
        wp_insert_term(__('Spinachia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'spinachia' ) );
        wp_insert_term(__('Tagetes', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'tagetes' ) );
        wp_insert_term(__('Thunbergia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'thunbergia' ) );
        wp_insert_term(__('Thymus', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'thymus' ) );
        wp_insert_term(__('Triticum ', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'triticum ' ) );
        wp_insert_term(__('Tropaeolum', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'tropaeolum' ) );
        wp_insert_term(__('Zea', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'zea' ) );
        wp_insert_term(__('Zinnia', $this->textdomain), $this->post_type . '_seed_genus', array( 'slug' => 'zinnia' ) );

        // Common Names
        wp_insert_term(__('Asian Vegetable', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'asian-vegetable' ) );
        wp_insert_term(__('Bean', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'bean' ) );
        wp_insert_term(__('Beet', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'beet' ) );
        wp_insert_term(__('Berry', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'berry' ) );
        wp_insert_term(__('Broccoli', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'broccoli' ) );
        wp_insert_term(__('Brussels Sprout', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'brussels-sprout' ) );
        wp_insert_term(__('Cabbage', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'cabbage' ) );
        wp_insert_term(__('Carrot', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'carrot' ) );
        wp_insert_term(__('Cauliflower', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'cauliflower' ) );
        wp_insert_term(__('Chard', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'chard' ) );
        wp_insert_term(__('Corn', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'corn' ) );
        wp_insert_term(__('Collard', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'collard' ) );
        wp_insert_term(__('Cover Crop', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'cover-crop' ) );
        wp_insert_term(__('Eggplant', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'eggplant' ) );
        wp_insert_term(__('Cucumber', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'cucumber' ) );
        wp_insert_term(__('Fava', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'fava' ) );
        wp_insert_term(__('Flower', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'flower' ) );
        wp_insert_term(__('Gourd', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'gourd' ) );
        wp_insert_term(__('Green', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'green' ) );
        wp_insert_term(__('Herb', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'herb' ) );
        wp_insert_term(__('Kale', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'kale' ) );
        wp_insert_term(__('Kohlrabi', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'kohlrabi' ) );
        wp_insert_term(__('Legume', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'legume' ) );
        wp_insert_term(__('Lettuce', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'lettuce' ) );
        wp_insert_term(__('Melon', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'melon' ) );
        wp_insert_term(__('Mustard', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'mustard' ) );
        wp_insert_term(__('Okra', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'okra' ) );
        wp_insert_term(__('Onion', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'onion' ) );
        wp_insert_term(__('Parsnip/Root Parsley', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'parsnip-root-parsley' ) );
        wp_insert_term(__('Potato', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'potato' ) );
        wp_insert_term(__('Pea', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'pea' ) );
        wp_insert_term(__('Peppers', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'peppers' ) );
        wp_insert_term(__('Pumpkin', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'pumpkin' ) );
        wp_insert_term(__('Radish', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'radish' ) );
        wp_insert_term(__('Strawberry', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'strawberry' ) );
        wp_insert_term(__('Root', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'root' ) );
        wp_insert_term(__('Rutabaga', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'rutabaga' ) );
        wp_insert_term(__('Spinach', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'spinach' ) );
        wp_insert_term(__('Summer Squash', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'summer-squash' ) );
        wp_insert_term(__('Tomato', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'tomato' ) );
        wp_insert_term(__('Turnip', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'turnip' ) );
        wp_insert_term(__('Winter Squash', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'winter-squash' ) );

        // Exchange statuses
        wp_insert_term(__( 'Active', $this->textdomain), $this->post_type . '_exchange_status', array('description' => __('New or open seed exchange requests or offers.', $this->textdomain)));
        wp_insert_term(__( 'Deleted', $this->textdomain), $this->post_type . '_exchange_status', array('description' => __('Expired or completed seed exchanges.', $this->textdomain)));
    }

}
$WP_SeedBank = new WP_SeedBank();
