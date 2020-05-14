<?php
/**
 * Plugin Name: WP-SeedBank
 * Plugin URI: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/
 * Description: Add a seed exchange post type to turn your WordPress website into a community seedbank or seed library! :D
 * Author: <a href="http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/#authors">The Hummingbird Project</a>
 * Version: 0.4.4
 * Text Domain: wp-seedbank
 * Domain Path: /languages
 */

class WP_SeedBank {
    private $post_type = 'seedbank';
    private $taxonomies = array(
        array('exchange_type'),
        array('common_name'),
        array('unit'),
        array('scientific_name'),
        // TODO: Exchange statuses shouldn't be taxonomies?
        array('exchange_status')
    );
    private $meta_fields = array(
        'exchange_type',
        'quantity',
        'common_name',
        'unit',
        'seed_expiry_date',
        'exchange_expiry_date',
        'exchange_status'
    );

    public function __construct () {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('init', array($this, 'createDataTypes'));
        add_action('add_meta_boxes_' . $this->post_type, array($this, 'addMetaBoxes'));
        add_action('save_post', array($this, 'savePost'));
        add_action('admin_init', array($this, 'registerCustomSettings'));
        foreach (array('post-new.php', 'post.php', 'edit.php') as $hook) {
            add_action("load-$hook", array($this, 'radioIzeTaxonomyInterface'));
        }
        add_action('admin_menu', array($this, 'registerAdminMenus'));
        add_action('pre_get_posts', array($this, 'registerCustomOrdering'));
        add_action('admin_enqueue_scripts', array($this, 'registerAdminScripts'));
        add_action('admin_head', array($this, 'registerCustomHelp'));

        add_action($this->post_type . '_expire_exchange', array($this, 'expireExchangePost'));

        add_action('manage_' . $this->post_type . '_posts_custom_column', array($this, 'displayCustomColumn'), 10, 2);
        add_filter('manage_' . $this->post_type . '_posts_columns', array($this, 'registerCustomColumns'));
        add_filter('manage_edit-' . $this->post_type . '_sortable_columns', array($this, 'registerSortableColumns'));

        add_filter('the_content', array($this, 'displayContent'));
    }

    public function registerL10n () {
        load_plugin_textdomain('wp-seedbank', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function createDataTypes () {
        $this->registerCustomPostType();
        $this->registerCustomTaxonomies();
    }

    private function registerCustomPostType () {
        $labels = array(
            'name'               => __('Seed Exchanges', 'wp-seedbank'),
            'singular_name'      => __('Seed Exchange', 'wp-seedbank'),
            'add_new'            => __('Add Seed Exchange', 'wp-seedbank'),
            'add_new_item'       => __('Add Seed Exchange', 'wp-seedbank'),
            'edit'               => __('Edit Seed Exchange', 'wp-seedbank'),
            'edit_item'          => __('Edit Seed Exchange', 'wp-seedbank'),
            'new_item'           => __('New Seed Exchange', 'wp-seedbank'),
            'view'               => __('View Seed Exchange', 'wp-seedbank'),
            'view_item'          => __('View Seed Exchange', 'wp-seedbank'),
            'search'             => __('Search Seed Exchanges', 'wp-seedbank'),
            'not_found'          => __('No Seed Exchanges found', 'wp-seedbank'),
            'not_found_in_trash' => __('No Seed Exchanges found in trash', 'wp-seedbank')
        );
        $url_rewrites = array(
            'slug' => 'seed-exchange'
        );
        $args = array(
            'labels' => $labels,
            'description' => __('Postings to the SeedBank', 'wp-seedbank'),
            'public' => true,
            'menu_icon' => plugins_url('images/seedexchange_icon.png', __FILE__),
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
        register_taxonomy($this->post_type . '_exchange_type', $this->post_type, array(
                'labels' => array(
                    'name'          => __('Exchange Types', 'wp-seedbank'),
                    'singular_name' => __('Exchange Type', 'wp-seedbank'),
                    'all_items'     => __('All Exchange Types', 'wp-seedbank'),
                    'edit_item'     => __('Edit Exchange Type', 'wp-seedbank'),
                    'update_item'   => __('Update Exchange Type', 'wp-seedbank'),
                    'add_new_item'  => __('Add New Exchange Type', 'wp-seedbank'),
                    'new_item_name' => __('New Exchange Type', 'wp-seedbank'),
                    'search_items'  => __('Search Exchange Types', 'wp-seedbank'),
                ),
                'show_admin_column' => true
            )
        );
        register_taxonomy_for_object_type($this->post_type . '_exchange_type', $this->post_type);

        register_taxonomy($this->post_type . '_common_name', $this->post_type, array(
                'labels' => array(
                    'name'          => __('Common Names', 'wp-seedbank'),
                    'singular_name' => __('Common Name', 'wp-seedbank'),
                    'all_items'     => __('All Common Names', 'wp-seedbank'),
                    'edit_item'     => __('Edit Common Name', 'wp-seedbank'),
                    'update_item'   => __('Update Common Name', 'wp-seedbank'),
                    'add_new_item'  => __('Add New Common Name', 'wp-seedbank'),
                    'new_item_name' => __('New Common Name', 'wp-seedbank'),
                    'search_items'  => __('Search Common Names', 'wp-seedbank'),
                ),
                'show_admin_column' => true
            )
        );
        register_taxonomy_for_object_type($this->post_type . '_common_name', $this->post_type);

        register_taxonomy($this->post_type . '_unit', $this->post_type, array(
                'labels' => array(
                    'menu_name'     => __('Inventory Units', 'wp-seedbank'),
                    'name'          => __('Units', 'wp-seedbank'),
                    'singular_name' => __('Unit', 'wp-seedbank'),
                    'all_items'     => __('All Units', 'wp-seedbank'),
                    'edit_item'     => __('Edit Unit', 'wp-seedbank'),
                    'update_item'   => __('Update Unit', 'wp-seedbank'),
                    'add_new_item'  => __('Add New Units', 'wp-seedbank'),
                    'new_item_name' => __('New Unit', 'wp-seedbank'),
                    'search_items'  => __('Search Units', 'wp-seedbank'),
                ),
                'show_admin_column' => true
            )
        );
        register_taxonomy_for_object_type($this->post_type . '_unit', $this->post_type);

        register_taxonomy($this->post_type . '_scientific_name', $this->post_type, array(
                'labels' => array(
                    'name'          => __('Scientific Names', 'wp-seedbank'),
                    'singular_name' => __('Scientific Name', 'wp-seedbank'),
                    'all_items'     => __('All Scientific Names', 'wp-seedbank'),
                    'edit_item'     => __('Edit Scientific Name', 'wp-seedbank'),
                    'update_item'   => __('Update Scientific Name', 'wp-seedbank'),
                    'add_new_item'  => __('Add New Scientific Name', 'wp-seedbank'),
                    'new_item_name' => __('New Scientific Name', 'wp-seedbank'),
                    'parent_item'   => __('Parent Classification', 'wp-seedbank'),
                    'parent_item_colon' => __('Parent Classification:', 'wp-seedbank'),
                    'search_items'  => __('Search Scientific Names', 'wp-seedbank'),
                ),
                'hierarchical' => true,
                'rewrite' => array(
                    'slug' => 'scientific-name'
                ),
                'show_admin_column' => true
            )
        );
        register_taxonomy_for_object_type($this->post_type . '_scientific_name', $this->post_type);

        register_taxonomy($this->post_type . '_exchange_status', $this->post_type, array(
                'labels' => array(
                    'name'          => __('Exchange Statuses', 'wp-seedbank'),
                    'singular_name' => __('Exchange Status', 'wp-seedbank'),
                    'all_items'     => __('All Exchange Statuses', 'wp-seedbank'),
                    'edit_item'     => __('Edit Exchange Status', 'wp-seedbank'),
                    'update_item'   => __('Update Exchange Status', 'wp-seedbank'),
                    'add_new_item'  => __('Add New Exchange Status', 'wp-seedbank'),
                    'new_item_name' => __('New Exchange Status', 'wp-seedbank'),
                    'search_items'  => __('Search Exchange Statuses', 'wp-seedbank'),
                ),
                'show_ui' => false,
                'show_admin_column' => true
            )
        );
        register_taxonomy_for_object_type($this->post_type . '_exchange_status', $this->post_type);
    }

    public function registerCustomSettings () {
        register_setting($this->post_type . '_settings', $this->post_type . '_settings', array($this, 'validateSettings'));

        add_settings_section(
            $this->post_type . '_settings',
            ucwords(str_replace('_', ' ', $this->post_type . '_settings')),
            array($this, 'renderReadingSettingsSection'),
            'reading'
        );

        add_settings_field(
            $this->post_type . '_display_meta',
            __('Display Seed Exchange details using', 'wp-seedbank'),
            array($this, 'renderDisplayMetaSetting'),
            'reading',
            $this->post_type . '_settings'
        );
    }

    public function validateSettings ($input) {
        switch ($input['display_meta']) {
            case '2':
            case '1':
            case '0':
            default:
                $input['display_meta'] = (int) $input['display_meta'];
            break;
        }
        return $input;
    }

    public function renderReadingSettingsSection () {
        settings_fields($this->post_type . '_settings');
    }
    public function renderDisplayMetaSetting () {
        $options = get_option($this->post_type . '_settings');
?>
    <select name="<?php print esc_attr($this->post_type);?>_settings[display_meta]">
        <option value="2"<?php if (2 === $options['display_meta']) { print ' selected="selected"'; }?>><?php esc_html_e('this plugin (below content)', 'wp-seedbank');?></option>
        <option value="1"<?php if (1 === $options['display_meta']) { print ' selected="selected"'; }?>><?php esc_html_e('this plugin (above content)', 'wp-seedbank');?></option>
        <option value="0"<?php if (0 === $options['display_meta']) { print ' selected="selected"'; }?>><?php esc_html_e('my own template', 'wp-seedbank');?></option>
    </select>
    <p class="description"><?php esc_html_e('Choosing your own template without writing your own template code may result in the Seed Exchange details not appearing on your website.', 'wp-seedbank');?></p>
<?php
    }

    public function addMetaBoxes ($post) {
        add_meta_box(
            $this->post_type . '-details-meta',
            __('Seed Exchange Details', 'wp-seedbank'),
            array($this, 'renderMetaBoxDetails'),
            $this->post_type,
            'normal',
            'high'
        );

        // Since we create the above "Seed Exchange Details" meta box
        // ourselves, we can remove the default meta boxes WordPress
        // gives us with our default taxonomies.
        foreach ($this->taxonomies as $taxonomy) {
            remove_meta_box('tagsdiv-' . $this->post_type . '_' . $taxonomy[0], $this->post_type, 'side');
        }
    }

    // TODO: Fix the i18n of the fill-in-the-blank web form?
    public function renderMetaBoxDetails () {
        global $post;

        wp_nonce_field('editing_' . $this->post_type, $this->post_type . '_meta_box_details_nonce');

        // Retrieve meta data fields, or set to initial blank slates.
        $custom = get_post_custom($post->ID);
        foreach ($this->meta_fields as $f) {
            if (!isset($custom[$this->post_type . '_' . $f])) {
                $custom[$this->post_type . '_' . $f] = array('');
            }
        }

        // Create HTML
        $select_elements = array();
        $datalists = array();
        foreach ($this->taxonomies as $taxonomy) {
            $select_elements[$taxonomy[0]] = $this->taxonomyAsHtmlSelect($taxonomy[0], $post);
            $datalists[$taxonomy[0]] = $this->taxonomyAsHtmlDatalist($taxonomy[0], $post);
        }
?>
    <p>
        <label><?php esc_html_e('I would like to', 'wp-seedbank');?> <?php print $select_elements['exchange_type'];?></label>
        <input name="<?php print esc_attr($this->post_type);?>_quantity" value="<?php print esc_attr($custom["{$this->post_type}_quantity"][0]);?>" placeholder="<?php esc_attr_e('enter a number', 'wp-seedbank');?>" />
        <?php print $datalists['common_name'];?>
        <?php print $select_elements['unit'];?>.
    </p>
    <p>
        <label><?php esc_html_e('These seeds will expire on or about', 'wp-seedbank');?> <input id="<?php print esc_attr($this->post_type);?>_seed_expiry_date" name="<?php print esc_attr($this->post_type);?>_seed_expiry_date" class="datepicker" value="<?php (empty($custom["{$this->post_type}_seed_expiry_date"][0])) ? '' : print esc_attr(date(get_option('date_format'), $custom["{$this->post_type}_seed_expiry_date"][0]));?>" placeholder="<?php esc_attr_e('enter a date', 'wp-seedbank');?>" />.</label> <span class="description"><?php esc_html_e('(If you are requesting seeds, you can leave this blank.)', 'wp-seedbank');?></span>
    </p>
    <p>
        <label><?php esc_html_e('If I don\'t hear from anyone by', 'wp-seedbank');?> <input name="<?php print $this->post_type;?>_exchange_expiry_date" class="datepicker" value="<?php (empty($custom["{$this->post_type}_exchange_expiry_date"][0])) ? print '' : print esc_attr(date(get_option('date_format'), $custom["{$this->post_type}_exchange_expiry_date"][0]));?>" placeholder="<?php esc_attr_e('enter a date', 'wp-seedbank');?>" required="required" />, <?php esc_html_e('I\'ll stop being available to make this exchange.', 'wp-seedbank');?></label> <span class="description"><?php esc_html_e('(If you do not get a response by this date, your request will automatically close.)', 'wp-seedbank');?></span>
    </p>
    <p>
        <?php // TODO: i18n this ?>
        <label><?php esc_html_e('This seed exchange is', 'wp-seedbank');?> <?php print $select_elements['exchange_status'];?>.</label> <span class="description">(<?php foreach (get_terms($this->post_type . '_exchange_status', 'hide_empty=0&order=ASC') as $x) :?>The <code><?php print esc_html(strtolower($x->name), 'wp-seedbank');?></code> type is for <?php print esc_html(strtolower($x->description));?> <?php endforeach;?>)</span>
    </p>
<?php
    }

    private function taxonomyAsHtmlSelect ($taxonomy, $post) {
        $custom = get_post_custom($post->ID);
        $these_options = get_terms($this->post_type . '_' . $taxonomy, 'hide_empty=0&order=ASC');

        ob_start();
        print '<select id="' . esc_attr($this->post_type) . '-' . esc_attr(str_replace('_', '-', $taxonomy)) . '" '
            . 'name="' . esc_attr($this->post_type) . '_' . esc_attr($taxonomy) . '">';
        foreach ($these_options as $t) {
            print '<option ';
            if (isset($custom["{$this->post_type}_{$taxonomy}"][0]) && $t->name == $custom["{$this->post_type}_{$taxonomy}"][0]) {
                print 'selected="selected" ';
            }
            print 'value="' . esc_attr($t->name) .'">' . esc_html(strtolower($t->name)) . '</option>' . PHP_EOL;
        }
        print '</select>';
        $el = ob_get_contents();
        ob_end_clean();

        return $el;
    }

    private function taxonomyAsHtmlDatalist ($taxonomy, $post) {
        $custom = get_post_custom($post->ID);
        $these_options = get_terms($this->post_type . '_' . $taxonomy, 'hide_empty=0&order=ASC');

        ob_start();
        print '<input list="' . esc_attr($this->post_type) . '-' . esc_attr($taxonomy) . '-datalist" ';
        print 'name="'. esc_attr($this->post_type) .'_' . esc_attr($taxonomy) . '" ';
        print 'value="';
        (empty($custom[$this->post_type . '_' . $taxonomy][0])) ? print '' : print esc_attr($custom[$this->post_type . '_' . $taxonomy][0]);
        print '" ';
        print 'placeholder="' . esc_attr($these_options[0]->name) . '" />' . PHP_EOL;
        print '<datalist id="' . esc_attr($this->post_type) . '-' . esc_attr($taxonomy). '-datalist">';
        foreach ($these_options as $t) {
            print '<option value="' . esc_attr($t->name) . '" />' . PHP_EOL;
        }
        print '</datalist>';
        $el = ob_get_contents();
        ob_end_clean();

        return $el;
    }

    /**
     * Utility function to determine whether this meta field is one
     * of our "date" or "time" fields.
     *
     * @param string $key The meta field key to check.
     * @return bool True if the field is one we're using for a date or time, false otherwise.
     */
    private function isDateOrTimeMeta ($key) {
        $x = substr($key, -5);
        return ($x === '_date' || $x === '_time') ? true : false;
    }

    /**
     * Utility function to determine whether this name is one of our
     * taxonomies. Useful for detecting duplication between existing
     * meta fields and custom taxonomies (until that's worked out).
     */
    private function isTaxonomy ($key) {
       return (in_array(array($key), $this->taxonomies)) ? true : false;
    }

    public function radioIzeTaxonomyInterface () {
        $screen = get_current_screen();
        if (false === strpos($screen->id, 'seedbank')) { return; }
        ob_start(array($this, 'swapOutCheckboxes'));
    }

    // Modified from
    // https://github.com/pressupinc/only-one-category/blob/master/main.php
    public function swapOutCheckboxes ($content) {
        $content = str_replace(
            'type="checkbox" name="tax_input[seedbank_scientific_name][]"',
            'type="radio" name="tax_input[seedbank_scientific_name][]""',
            $content
        );
        foreach (get_terms($this->post_type . '_scientific_name') as $t) {
            $content = str_replace(
                'id="in-popular-seedbank_scientific_name-' . $t->term_id . '" type="checkbox"',
                'id="in-popular-seedbank_scientific_name-' . $t->term_id . '" type="radio"',
                $content
            );
        }
        return $content;
    }

    /**
     * Runs when we save a Seed Exchange post.
     */
    public function savePost ($post_id) {
        if (!isset($_POST['post_type']) || $this->post_type !== $_POST['post_type']) { return; }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!wp_verify_nonce($_POST[$this->post_type . '_meta_box_details_nonce'], 'editing_' . $this->post_type)) { return; }
        $this->saveMeta($post_id);
        $this->scheduleExpiration($post_id);
    }

    private function saveMeta ($post_id) {
        foreach ($this->meta_fields as $f) {
            if (isset($_REQUEST[$this->post_type . '_' . $f])) {
                $val = $_REQUEST[$this->post_type . '_' . $f];
                // For meta fields that end in '_date' or '_time',
                if ($this->isDateOrTimeMeta($f)) {
                    // convert their value to a UTC'ed unix timestamp
                    $val = gmdate('U', strtotime($val));
                }
                update_post_meta($post_id, $this->post_type . '_' . $f, sanitize_text_field($val));
                // TODO: Only do wp_set_object_terms on taxonomies.
                wp_set_object_terms($post_id, sanitize_text_field($_REQUEST[$this->post_type . '_' . $f]), $this->post_type . '_' . $f);
            }
        }
    }

    /**
     * Uses WP Cron to schedule setting exchange_status to 'Deleted'
     * once the time in exchange_expiry_date meta field is reached.
     */
    private function scheduleExpiration ($post_id) {
        wp_clear_scheduled_hook($this->post_type . '_expire_exchange', array($post_id));

        $time = (int) get_post_meta($post_id, $this->post_type . '_exchange_expiry_date', true);
        wp_schedule_single_event($time, $this->post_type . '_expire_exchange', array($post_id));
    }

    public function expireExchangePost ($post_id) {
        update_post_meta($post_id, $this->post_type . '_exchange_status', __('Deleted', 'wp-seedbank'));
        wp_set_object_terms(
            $post_id,
            sanitize_text_field(get_post_meta($post_id, $this->post_type . '_exchange_status', true)),
            $this->post_type . '_exchange_status'
        );
    }

    public function displayContent ($content) {
        global $post;
        $options = get_option($this->post_type . '_settings');
        if ($this->post_type === get_post_type($post->ID)) {
            $append = '<ul id="' . esc_attr($this->post_type . '-meta-' . $post->ID) . '" class="' . esc_attr($this->post_type) . '-meta">';
            if ($val = get_the_term_list($post->ID, $this->post_type . '_scientific_name')) {
                $append .= '<li><strong>' . __('Scientific Name:', 'wp-seedbank') . '</strong> ' . $val . '</li>';
            }
            $custom = get_post_custom($post->ID);
            foreach ($this->meta_fields as $f) {
                $val = $custom[$this->post_type . '_' . $f][0];
                // For meta fields that end in '_date' or '_time',
                if ($this->isDateOrTimeMeta($f)) {
                    // convert their value to blog's local date format
                    $val = date(get_option('date_format'), $val);
                }
                if ($val) {
                    $append .= '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $f))) . ':</strong> ';
                    if ($this->isTaxonomy($f)) {
                        $append .= get_the_term_list($post->ID, $this->post_type . '_' . $this->taxonomies[array_search(array($f), $this->taxonomies)][0]);
                    } else {
                        $append .= esc_html($val);
                    }
                    $append .= '</li>';
                }
            }
            $append .= '</ul>';
            switch ($options['display_meta']) {
                case 1: // Position meta list above content.
                    $content = $append . $content;
                    break;
                case 2: // Position meta list below content.
                    $content = $content . $append;
                case 0:
                default:
                    // Don't modify $content at all.
                break;
            }
        }
        return $content;
    }

    public function registerAdminScripts () {
        global $wp_scripts;
        $screen = get_current_screen();
        // Only load this plugin's JS on this plugin's own screens.
        if (false === strpos($screen->id, $this->post_type)) { return; }
        wp_register_script('wp-seedbank', plugins_url('wp-seedbank.js', __FILE__), array('jquery', 'jquery-ui-datepicker'));
        wp_enqueue_script('wp-seedbank');
        $x = $wp_scripts->query('jquery-ui-core');
        wp_enqueue_style('jquery-ui-smoothness', "//ajax.googleapis.com/ajax/libs/jqueryui/{$x->ver}/themes/smoothness/jquery-ui.min.css", false, null);
    }

    public function registerCustomColumns ($columns) {
        $my_columns = array();
        foreach ($this->meta_fields as $f) {
            switch ($f) {
                case 'quantity':
                    $my_columns[$this->post_type . '_quantity'] = esc_html__('Quantity', 'wp-seedbank');
                    break;
                case 'seed_expiry_date':
                    $my_columns[$this->post_type . '_seed_expiry_date'] = esc_html__('Seed Expiry Date', 'wp-seedbank');
                    break;
                case 'exchange_expiry_date':
                    $my_columns[$this->post_type . '_exchange_expiry_date'] = esc_html__('Exchange Expiry Date', 'wp-seedbank');
                    break;
            }
        }
        return array_merge($columns, $my_columns);
    }

    public function displayCustomColumn ($column, $post_id) {
        foreach ($this->meta_fields as $f) {
            if ($column !== $this->post_type . '_' . $f) {
                continue;
            }
            if ($this->isDateOrTimeMeta($f)) {
                print esc_html(date(get_option('date_format'), get_post_meta($post_id, $this->post_type . '_' . $f, true)));
            } else {
                print esc_html(get_post_meta($post_id, $this->post_type . '_' . $f, true));
            }
        }
    }

    public function registerSortableColumns ($columns) {
        $my_columns = array();
        foreach ($this->meta_fields as $f) {
            $my_columns[$this->post_type . '_' . $f] = $this->post_type . '_' . $f;
        }
        return array_merge($columns, $my_columns);
    }

    public function registerCustomOrdering ($query) {
        switch ($query->get('orderby')) {
            case $this->post_type . '_quantity':
            case $this->post_type . '_seed_expiry_date':
            case $this->post_type . '_exchange_expiry_date':
                $query->set('meta_key', $query->get('orderby'));
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }

    public function registerCustomHelp () {
        $screen = get_current_screen();
        if ($screen->post_type !== $this->post_type) { return; }
        // Tabs for specific screens.
        switch ($screen->id) {
            case 'seedbank':
                $p1 = __('Make a new Seed Exchange on this page. A Seed Exchange is just like a blog post, but tailored specifically for the Seedbank. Have some seeds to share, or trying to find seeds to grow? Let others know by posting here!', 'wp-seedbank');
                $p2 = __('To make a new Seed Exchange, follow these steps:', 'wp-seedbank');
                $ol1_str1 = esc_html__('Write a title.', 'wp-seedbank');
                $ol1_str2 = esc_html__('Short, descriptive summaries are best.', 'wp-seedbank');
                $ol1_str3 = esc_html__('Click here and then type your title.', 'wp-seedbank');
                $ol1 = "<strong>$ol1_str1</strong> $ol1_str2 <a href=\"#\" onclick=\"document.getElementById('title').focus()\">$ol1_str3</a>";
                $ol2_str1 = esc_html__('Explain your request or offer.', 'wp-seedbank');
                $ol2_str2 = esc_html__("In your own words, describe what you're looking for or what you're hoping to get, or both. Be sure to include any important words you think others might use to find your post when they're searching the website. Include any additional information relevant to your posting.", 'wp-seedbank');
                $ol2_str3 = esc_html__('Click here and then type your message.', 'wp-seedbank');
                $ol2 = "<strong>$ol2_str1</strong> $ol2_str2 <a href=\"#\" onclick=\"document.getElementById('content').focus()\">$ol2_str3</a>";
                $ol3_str1 = esc_html__('Fill in the details.', 'wp-seedbank');
                $ol3_str2 = sprintf(
                    esc_html__('In the %1$s box, there are some fields you should fill in to help other people find your post by organizing it in a sensible place in the Seedbank. Simply complete the sentences of the fill-in-the-blank paragraph.', 'wp-seedbank'),
                    '<a href="#' . $this->post_type . '-details-meta">' . __('Seed Exchange Details', 'wp-seedbank') . '</a>'
                );
                $ol3 = "<strong>$ol3_str1</strong> $ol3_str2";
                $ul1 = sprintf(
                    esc_html__('If your seeds are in a packet, the wrapping might have an expiration date. Put that in the "%s" field.', 'wp-seedbank'),
                    '<a href="#seedbank_seed_expiry_date">' . esc_html__('These seeds will expire on or about', 'wp-seedbank') . '</a>'
                );
                $p3 = sprintf(
                    esc_html__('If you know the scientific name (genus, species, variety, etc.) of your seed, you can also select it from the list of %s.', 'wp-seedbank'),
                    '<a href="#seedbank_scientific_namediv">' . esc_html__('Scientific Names', 'wp-seedbank') . '</a>'
                );
                $p4 = esc_html__('When you have done this, click the "Publish" (or "Submit for review") button. Congratulations! And thank you for spreading the seed love!', 'wp-seedbank');
                $html = <<<END_HTML
<p>$p1</p>
<p>$p2</p>
<ol>
    <li>$ol1</li>
    <li>$ol2</li>
    <li>
        $ol3
        <ul>
            <li>$ul1</li>
        </ul>
    </li>
</ol>
<p>$p3</p>
<p>$p4</p>
END_HTML;
                $screen->add_help_tab(array(
                    'id' => $this->post_type . '-' . $screen->base . '-help',
                    'title' => __('Adding a Seed Exchange', 'wp-seedbank'),
                    'content' => $html
                ));
                break;
            case 'edit-seedbank_scientific_name':
                $html = '<p>'
                    . esc_html__('You can use scientific names to communicate about the kind of seed you have in a more precise way. A scientific name contains information about the genus and species of your seed, as well as any applicable more specific classification (called an "infraspecific name"). The WordPress SeedBank plugin expects scientific names to match the accepted names in the International Code of Nomenclature for Cultivated Plants (ICNCP). Several dozen genera and various species, subspecies, varieties, and cultivar Groups are already included.', 'wp-seedbank')
                    . '</p>';
                $screen->add_help_tab(array(
                    'id' => $this->post_type . '-' . $screen->id . '-help-overview',
                    'title' => __('Overview', 'wp-seedbank'),
                    'content' => $html
                ));
                $html = '<p>'
                    . esc_html__('To add a new scientific name on this screen, enter the following information:', 'wp-seedbank')
                    . '</p>';
                $html .= '<ol>';
                $html .= '<li>'
                    . sprintf(
                        '<strong>' . esc_html__('Name:', 'wp-seedbank') . '</strong> '
                        . esc_html__('The name is the full scientific name for your seed. Be as specific as you know how and try to follow the examples already listed.', 'wp-seedbank')
                    )
                    . '</li>';
                $html .= '<li>'
                    . sprintf(
                        '<strong>' . esc_html__('Slug:', 'wp-seedbank') . '</strong> '
                        . esc_html__('The slug is the URL-friendly version of the scientific name. You can leave this blank to automatically fill it in from the Name field you entered in the previous step.', 'wp-seedbank')
                    )
                    . '</li>';
                $html .= '<li>'
                    . sprintf(
                        '<strong>' . esc_html__('Parent:', 'wp-seedbank') . '</strong> '
                        . esc_html__('The parent is the higher-level rank for this scientific name. Each new scientific name should be added to the proper place in the hierarchy of biological classification. For instance, if you are adding a new species, like %1$s, be sure to set the correct genus (%2$s) as its parent. If the appropriate higher-level rank does not yet exist, create it first and then come back to enter the lower-level scientific name. (WP-SeedBank expects the top-level category to be the seed genus.)', 'wp-seedbank'),
                            '<em class="scientific_name">' . esc_html__('Allium cepa', 'wp-seedbank') . '</em>',
                            '<em class="scientific_name">' . esc_html__('Allium', 'wp-seedbank') . '</em>'
                    )
                    . '</li>';
                $html .= '<li>'
                    . sprintf(
                        '<strong>' . esc_html__('Description:', 'wp-seedbank') . '</strong> '
                        . esc_html__('You can enter a short explanation of what this scientific name means for laypeople who are more familiar with common vernacular than scientific terms. You can also provide any extra information about this specific seed, such as scientific name synonyms or other custom notes. This provides a good teaching opportunity for those in your community who want to learn more about seed saving. You can also leave this field blank.', 'wp-seedbank')
                    )
                    . '</li>';
                $html .= '</ol>';
                $screen->add_help_tab(array(
                    'id' => $this->post_type . '-' . $screen->id . '-help-adding',
                    'title' => __('Adding new Scientific Names', 'wp-seedbank'),
                    'content' => $html
                ));
                break;
            default:
                break;
        }
        // Tabs for all screens.
        $screen->add_help_tab(array(
            'id' => $this->post_type . '-' . $screen->base . '-about-help',
            'title' => __('About the WP-SeedBank', 'wp-seedbank'),
            'content' => '<p>' . sprintf(
                esc_html__('The %1$s is a labor of love, and passion. Conceived by %2$s and %3$s, it is maintained by %4$s who loves fresh food and hates Monsanto. %5$s'),
                '<a href="https://wordpress.org/plugins/wp-seedbank/" title="' . __('WP-SeedBank on the WordPress Plugin Repository', 'wp-seedbank') . '">' . __('WP-SeedBank plugin', 'wp-seedbank') . '</a>',
                '<a href="http://www.hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/" title="' . __('The HummingBird Project\'s WP-SeedBank Initiative', 'wp-seedbank') . '">' . __('The Hummingbird Project', 'wp-seedbank') . '</a>',
                '<a href="http://permaculturenews.org/2013/09/25/an-open-source-community-model-to-save-seeds-a-wordpress-seedbank-plugin/" title="An Open Source Community Model to Save Seeds &mdash; a WordPress Seedbank Plugin" rel="bookmark">' . __('initially developed at Cleveland GiveCamp', 'wp-seedbank') . '</a>',
                '<a href="http://wordpress.org/plugins/wp-seedbank/other_notes/" title="' . __('Credits for WP-SeedBank', 'wp-seedbank') . '">' . __('Donations are appreciated.', 'wp-seedbank') . '</a>'
            ) . '</p>'
        ));

        $sidebar = '<p><strong>' . esc_html__('More WP-SeedBank help:', 'wp-seedbank') . '</strong></p>';
        $sidebar .= '<p><a href="https://wordpress.org/support/plugin/wp-seedbank" target="_blank">' . esc_html__('WP-SeedBank support forum', 'wp-seedbank') . '</a></p>';
        $sidebar .= '<p><a href="https://github.org/fabacab/wp-seedbank/issues/new" target="_blank">' . esc_html__('WP-SeedBank bug report form (for programmers)', 'wp-seedbank') . '</a></p>';
        $sidebar .= '<p>' . sprintf(
            esc_html__('WP-SeedBank is free software, but sadly grocery stores do not offer free food. Please consider %sdonating some food to the plugin maintainer%s. %s', 'wp-seedbank'),
            '<strong><a href="http://maybemaimed.com/cyberbusking/#food">', '</a></strong>', '&hearts;'
        ) . '</p>';
        $screen->set_help_sidebar($screen->get_help_sidebar() . $sidebar);
    }

    public function activate () {
        $this->registerL10n();
        $this->createDataTypes(); // This registers new taxonomies.

        global $wpdb;
        // If any old 0.2.x versions exist
        if ('0.2' === substr(get_option('wp_seedbank_version'), 0, 3)) {
            $errors = array();

            // we need to do some database & option cleanup that looks
            // something like the following:
            // delete the no longer used "addInfo" meta key globally.
            delete_post_meta_by_key('wp_seedbank_addInfo');
            // NOTE: This SQL is a bit blunt. Any safer ways to do it?
            // UPDATE wp_posts SET post_type = 'seedbank' WHERE post_type = 'wp_seedbank';
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
                $this->post_type,
                'wp_seedbank'
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_term_taxonomy SET taxonomy = REPLACE(taxonomy, 'wp_seedbank', 'seedbank');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->term_taxonomy} SET taxonomy = REPLACE(taxonomy, %s, %s)",
                'wp_seedbank',
                $this->post_type
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_term_taxonomy SET taxonomy = REPLACE(taxonomy, 'seedbank_type', 'seedbank_exchange_type');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->term_taxonomy} SET taxonomy = REPLACE(taxonomy, %s, %s)",
                $this->post_type . '_type',
                $this->post_type . '_exchange_type'
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_term_taxonomy SET taxonomy = REPLACE(taxonomy, 'seedbank_genus', 'seedbank_seed_genus');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->term_taxonomy} SET taxonomy = REPLACE(taxonomy, %s, %s)",
                $this->post_type . '_genus',
                $this->post_type . '_seed_genus'
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_term_taxonomy SET taxonomy = REPLACE(taxonomy, 'seedbank_status', 'seedbank_exchange_status');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->term_taxonomy} SET taxonomy = REPLACE(taxonomy, %s, %s)",
                $this->post_type . '_status',
                $this->post_type . '_exchange_status'
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_postmeta SET meta_key = REPLACE(meta_key, 'wp_seedbank', 'seedbank');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, %s, %s)",
                'wp_seedbank',
                $this->post_type
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_postmeta SET meta_key = REPLACE(meta_key, 'seedbank_type', 'seedbank_exchange_type');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, %s, %s)",
                $this->post_type . '_type',
                $this->post_type . '_exchange_type'
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // UPDATE wp_postmeta SET meta_key = REPLACE(meta_key, 'seedbank_status', 'seedbank_exchange_status');
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, %s, %s)",
                $this->post_type . '_status',
                $this->post_type . '_exchange_status'
            );
            if (false === $wpdb->query($sql)) {
                $errors[] = array($wpdb->last_query, $wpdb->last_error);
            }
            // Now we need to associate the posts with our taxonomies,
            // and update expiry date fields to their Unix timestamps.
            $sql = $wpdb->prepare(
                "SELECT * FROM {$wpdb->postmeta} WHERE meta_key LIKE '%s'",
                like_escape($this->post_type) . '%'
            );
            $results = $wpdb->get_results($sql);
            foreach ($results as $row) {
                if ($this->isDateOrTimeMeta($row->meta_key)) {
                    update_post_meta(
                        (int) $row->post_id,
                        $row->meta_key,
                        gmdate('U', strtotime($row->meta_value))
                    );
                }
                // Find all posts with one of our taxonomies,
                // and for each of those posts, relate them to terms.
                foreach ($this->taxonomies as $taxonomy) {
                    $key = $this->post_type . '_' . $taxonomy[0];
                    if ($row->meta_key === $key) {
                        wp_set_object_terms(
                            (int) $row->post_id,
                            sanitize_text_field($row->meta_value),
                            sanitize_text_field($this->post_type . '_' . $taxonomy[0])
                        );
                    }
                }
            }

            if (empty($errors)) {
                delete_option('wp_seedbank_version'); // No longer used.
            } else {
                // TODO: Handle errors?
            }
        }

        // Detect existence of obsolete taxonomy and remove it.
        $sql = $wpdb->prepare(
            "SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s",
            $this->post_type . '_seed_genus'
        );
        $results = $wpdb->get_results($sql);
        if (!empty($results)) {
            register_taxonomy($this->post_type . '_seed_genus', $this->post_type);
            register_taxonomy_for_object_type($this->post_type . '_exchange_type', $this->post_type);
            $terms = get_terms($this->post_type . '_seed_genus', array('hide_empty' => false));
            foreach ($terms as $t) {
                wp_delete_term($t->term_id, $this->post_type . '_seed_genus');
            }
            global $wp_taxonomies; // hacky way to unregister taxonomy
            unset($wp_taxonomies[$this->post_type . '_seed_genus']);
        }

        // Detect lack of unit taxonomy and copy any unit post meta values.
        $unit_terms = get_terms($this->post_type . '_unit', 'hide_empty=0');
        if (empty($unit_terms)) {
            $posts_with_unit_meta = get_posts(array(
                'posts_per_page' => -1,
                'post_type' => $this->post_type,
                'meta_key' => $this->post_type . '_unit'
            ));
            foreach ($posts_with_unit_meta as $p) {
                wp_set_object_terms($p->ID, get_post_meta($p->ID, $this->post_type . '_unit', true), $this->post_type . '_unit');
            }
        }

        flush_rewrite_rules();

        // Exchange Types (verbs)
        wp_insert_term(_x( 'Swap', 'verb', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering seeds for other seeds.', 'wp-seedbank')));
        wp_insert_term(_x( 'Sell', 'verb', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering seeds for money.', 'wp-seedbank')));
        wp_insert_term(_x( 'Give', 'verb', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering free seeds being given away.', 'wp-seedbank')));
        wp_insert_term(_x( 'Get', 'verb', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges requesting seeds of a variety not already listed.', 'wp-seedbank')));

        // Scientific names (as defined by ICNCP, 8th Ed.)
        // International Code of Nomenclature for Cultivated Plants 8th Ed.
        // That means hierarchical levels map to the following ranks:
        //
        //     Genus (top-level "category" or "rank")
        //        |
        //        -- species
        //                |
        //                -- Group (cultivar Group) OR subspecies OR variety
        //                       |
        //                       -- cultivar
        //
        // Empty slugs to calculate the slug from the i18n'd term name.
        if ($genus = wp_insert_term(__('Abelmoschus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                // WordPress taxonomy cache is still not invalidating properly.
                // See
                //     https://wordpress.stackexchange.com/questions/8357/inserting-terms-in-an-hierarchical-taxonomy/8921#8921
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Abelmoschus esculentus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Agastache', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Allium', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Allium cepa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Allium porrum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Allium sativum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Allium schoenoprasum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Amaranthus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Amaranthus tricolor', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Anagallis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Anethum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Anthenum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Antirrhinum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Apium', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                if ($species = wp_insert_term(__('Apium graveolens', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']))) {
                    if (is_array($species)) {
                        delete_option($this->post_type . '_scientific_name_children');
                        wp_insert_term(__('Apium graveolens var. dulce', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                        wp_insert_term(__('Apium graveolens var. rapaceum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                    }
                }
            }
        }
        if ($genus = wp_insert_term(__('Arachis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Arachis hypogaea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Asparagus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Asparagus officinalis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Asclepias', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        if ($genus = wp_insert_term(__('Basella', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Basella rubra', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Beta', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                if ($species = wp_insert_term(__('Beta vulgaris', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']))) {
                    if (is_array($species)) {
                        delete_option($this->post_type . '_scientific_name_children');
                        wp_insert_term(__('Beta vulgaris subsp. cicla', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                    }
                }
            }
        }
        if ($genus = wp_insert_term(__('Brassica', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Brassica napus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                if ($species = wp_insert_term(__('Brassica oleracea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']))) {
                    if (is_array($species)) {
                        delete_option($this->post_type . '_scientific_name_children');
                        wp_insert_term(__('Brassica oleracea Acephala Group', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                        wp_insert_term(__('Brassica oleracea Botrytis Group', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                        wp_insert_term(__('Brassica oleracea Capitata Group', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                        wp_insert_term(__('Brassica oleracea Gemmifera Group', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                        wp_insert_term(__('Brassica oleracea Gongylodes Group', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                        wp_insert_term(__('Brassica oleracea Italica Group', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                    }
                }
                wp_insert_term(__('Brassica rapa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        wp_insert_term(__('Calendula', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Capsicum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Capsicum annuum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Cardiospermum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Centaurea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Chrysanthemum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Cichorium', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Cichorium endivia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Cichorium intybus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Citrullus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Citrullus lanatus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Cleome', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Cobaea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Consolida', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Convolvulus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Coreopsis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Coriandrum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Cosmos', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Cucumis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Cucumis melo', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Cucumis sativa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Cucurbita', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Cucurbita pepo', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Cucurbita maxima', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Cynara', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Cynara scolymus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        wp_insert_term(__('Dalea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Daucus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                if ($species = wp_insert_term(__('Daucus carota', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']))) {
                    if (is_array($species)) {
                        delete_option($this->post_type . '_scientific_name_children');
                        wp_insert_term(__('Daucus carota subsp. sativus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                    }
                }
            }
        }
        wp_insert_term(__('Diplotaxis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Dolichos', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        wp_insert_term(__('Echinacea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Eruca', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                if ($species = wp_insert_term(__('Eruca vesicaria', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']))) {
                    if (is_array($species)) {
                        delete_option($this->post_type . '_scientific_name_children');
                        wp_insert_term(__('Eruca vesicaria subsp. sativa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                    }
                }
            }
        }
        wp_insert_term(__('Eschscholzia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        if ($genus = wp_insert_term(__('Foeniculum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                if ($species = wp_insert_term(__('Foeniculum vulgare', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']))) {
                    if (is_array($species)) {
                        delete_option($this->post_type . '_scientific_name_children');
                        wp_insert_term(__('Foeniculum vulgare var. azoricum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $species['term_id']));
                    }
                }
            }
        }
        wp_insert_term(__('Fragaria', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        wp_insert_term(__('Gaillardia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Glycine', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        if ($genus = wp_insert_term(__('Helianthus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Helianthus tuberosus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        if ($genus = wp_insert_term(__('Ipomoea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Ipomoea batatas', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        wp_insert_term(__('Koeleria', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        if ($genus = wp_insert_term(__('Lactuca', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Lactuca sativa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Lagenaria', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Lathyrus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Lupinus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Lycopersicon', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Lycopersicon esculentum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        wp_insert_term(__('Malope', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Matricaria', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Mentha', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Mirabilis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Momordica', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Momordica charantia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        wp_insert_term(__('Nigella', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        wp_insert_term(__('Ocimum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Origanum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        wp_insert_term(__('Papaver', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Pastinaca', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Pastinaca sativa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Passiflora', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Penstemon', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Petroselinum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Phaseolus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Phaseolus lunatus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Phaseolus vulgaris', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Physalis', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Physalis ixocarpa', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Pisum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Pisum sativum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Poterium', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        if ($genus = wp_insert_term(__('Rhaphanus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Rhaphanus sativus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Rheum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Rheum rhabarbarum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Rosmarinus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Rudbeckia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        wp_insert_term(__('Salvia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Scorpiurus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Solanum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Solanum melongena', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
                wp_insert_term(__('Solanum tuberosum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Spinacia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Spinacia oleracea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        wp_insert_term(__('Tagetes', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        if ($genus = wp_insert_term(__('Tetragonia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Tetragonia tetragonioides', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Thunbergia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Thymus', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Triticum ', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));
        wp_insert_term(__('Tropaeolum', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        if ($genus = wp_insert_term(__('Valerianella', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Valerianella locusta', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        if ($genus = wp_insert_term(__('Vigna', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Vigna unguiculata', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }

        if ($genus = wp_insert_term(__('Zea', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''))) {
            if (is_array($genus)) {
                delete_option($this->post_type . '_scientific_name_children');
                wp_insert_term(__('Zea mays', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => '', 'parent' => $genus['term_id']));
            }
        }
        wp_insert_term(__('Zinnia', 'wp-seedbank'), $this->post_type . '_scientific_name', array('slug' => ''));

        // Common Names
        wp_insert_term(__('Asian Vegetable', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Bean', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Beet', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Berry', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Broccoli', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Brussels Sprout', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Cabbage', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Carrot', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Cauliflower', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Chard', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Corn', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Collard', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Cover Crop', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Eggplant', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Cucumber', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Fava', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Flower', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Gourd', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Green', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Herb', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Kale', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Kohlrabi', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Legume', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Lettuce', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Melon', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Mustard', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Okra', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Onion', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Parsnip/Root Parsley', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Potato', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Pea', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Pepper', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Pumpkin', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Radish', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Strawberry', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Root', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Rutabaga', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Spinach', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Summer Squash', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Tomato', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Turnip', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));
        wp_insert_term(__('Winter Squash', 'wp-seedbank'), $this->post_type . '_common_name', array('slug' => ''));

        // Inventory units
        wp_insert_term(__( 'Packets', 'wp-seedbank'), $this->post_type . '_unit', array('description' => __('A package containing enough seeds for a single germination attempt.', 'wp-seedbank')));

        // Exchange statuses
        wp_insert_term(__( 'Active', 'wp-seedbank'), $this->post_type . '_exchange_status', array('description' => __('New or open seed exchange requests or offers.', 'wp-seedbank')));
        wp_insert_term(__( 'Deleted', 'wp-seedbank'), $this->post_type . '_exchange_status', array('description' => __('Expired or completed seed exchanges.', 'wp-seedbank')));
    }

    public function registerAdminMenus () {
        add_submenu_page(
            'edit.php?post_type=' . $this->post_type,
            __('Batch Seed Exchange', 'wp-seedbank'),
            __('Batch Exchange', 'wp-seedbank'),
            'edit_posts',
            $this->post_type . '_batch_exchange',
            array($this, 'dispatchBatchExchangePages')
        );

        // Hacky way to add a direct link to the admin menu.
        // TODO: Should this actually go into the admin bar?
        global $submenu;
        $submenu['edit.php?post_type=' . $this->post_type][] = array(
            __('My Seeds', 'wp-seedbank'),
            'edit_posts',
            $this->getMySeedsURL()
        );
    }

    private function getMySeedsURL () {
        return admin_url(
            'edit.php?post_type=' . $this->post_type . '&author=' . get_current_user_id()
        );
    }

    public function dispatchBatchExchangePages () {
        $step = (int) $_POST[$this->post_type . '-batch-exchange-step'];
        if (0 === $step) {
            $this->printBatchExchangeForm();
        } else if (1 === $step) {
            $this->processBatchExchangeForm($_POST);
        }
    }

    // Produce HTML for showing the submenu page.
    private function printBatchExchangeForm () {
?>
<h2><?php esc_html_e('Batch Seed Exchange', 'wp-seedbank');?></h2>
<p><?php esc_html_e('This page allows you to upload a comma-separated values (CSV) file that will be translated to seed exchange requests or offers.', 'wp-seedbank');?> <?php print sprintf(esc_html__('The CSV file should have the structure like %sthe example shown in the table below%s.', 'wp-seedbank'), '<a href="#wp-seedbank-batch-exchange-example">', '</a>');?></p>
<form id="<?php print esc_attr($this->post_type)?>-batch-exchange-form" name="<?php print esc_attr($this->post_type);?>_batch_exchange" action="<?php print esc_url($_SERVER['PHP_SELF'] . '?post_type=' . $this->post_type . '&amp;page=' . $this->post_type . '_batch_exchange');?>" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field($this->post_type . '-batch-exchange', 'batch-exchange');?>
    <input type="hidden" name="<?php print esc_attr($this->post_type);?>-batch-exchange-step" value="1" />
    <p>
        <?php esc_html_e('My batch exchange file is located on', 'wp-seedbank');?>
        <select id="seedbank-batch-exchange-data-source">
            <option value="another website"><?php _e('another website', 'wp-seedbank');?></option>
            <option value="my computer"><?php _e('my computer', 'wp-seedbank');?></option>
        </select>.
        <?php
        /*
           TRANSLATORS:
           This string becomes a Web form. The placeholder (%s) will
           contain words that indicate whether or not the file being
           uploaded contains column labels. When you translate this
           string, put the placeholder in the appropriate spot for
           either "has" or "does not have" column labels.
         */
        print sprintf(
            esc_html__('It %s column labels (a header row).', 'wp-seedbank'),
            '<select name="' . esc_attr($this->post_type) . '-batch-exchange-strip-headers">'
            . '<option value="1">' . esc_html_x('has', 'This is the "has" in "It has column labels (a header row).', 'wp-seedbank') . '</option>'
            . '<option value="0">' . esc_html_x('does not have', 'This is the "does not have" in "It does not have column labels (a header row).', 'wp-seedbank') . '</option>'
            . '</select>'
        );?>
    </p>
    <fieldset id="seedbank-batch-exchange-web-fetch"><legend><?php _e('Web fetch options', 'wp-seedbank');?></legend>
        <p><label><?php esc_html_e('The address of the file containing my seed exchange data is', 'wp-seedbank');?> <input name="<?php print esc_attr($this->post_type);?>-batch-exchange-file-url" value="" placeholder="<?php esc_attr_e('http://mysite.com/file.csv', 'wp-seedbank');?>" />.</label></p>
    </fieldset>
    <fieldset id="seedbank-batch-exchange-file-upload"><legend><?php _e('File upload options', 'wp-seedbank');?></legend>
        <p><label><?php esc_html_e('The file on my computer containing my seed exchange data is', 'wp-seedbank');?> <input type="file" name="<?php print esc_attr($this->post_type);?>-batch-exchange-file-data" value="" />.</label></p>
        <p><span class="description"><?php esc_html_e('For the best results, ensure your file is encoded as "Unicode" or "UTF-8," especially if it contains diacritics or special characters. UTF-8 encoding is usually available as one of the "Character encoding" options in the "Save As" window of the application you used to create this file.');?></span></p>
    </fieldset>
    <p><label><input type="checkbox" name="<?php print esc_attr($this->post_type);?>-batch-exchange-post_status" value="draft" /> <?php esc_html_e('Let me review each seed exchange before publishing.', 'wp-seedbank');?></label></p>
    <p><input type="submit" name="<?php print esc_attr($this->post_type);?>-batch-exchange-submit" value="<?php esc_attr_e('Make Seed Exchanges', 'wp-seedbank');?>" /></p>
</form>
<table summary="<?php esc_attr_e('Example of batch seed exchange data.', 'wp-seedbank');?>" id="wp-seedbank-batch-exchange-example">
    <thead>
        <tr>
            <th><?php esc_html_e('Title', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Type', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Quantity', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Common Name', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Unit label', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Seed expiration date', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Exchange expiration date', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Notes', 'wp-seedbank');?></th>
            <th><?php esc_html_e('Scientific Name', 'wp-seedbank');?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?php esc_html_e('Looking to swap peppers for carrots', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Swap', 'wp-seedbank');?></td>
            <td><?php esc_html_e('5', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Pepper', 'wp-seedbank');?></td>
            <td><?php esc_html_e('seeds', 'wp-seedbank');?></td>
            <td><?php esc_html_e('2016-05-01', 'wp-seedbank');?></td>
            <td><?php esc_html_e('2014-05-01', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Ideally, I would like to receive carrot seeds in exchange. Thanks!', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Capsicum', 'wp-seedbank');?></td>
        </tr>
        <tr>
            <td><?php esc_html_e('For sale: tomato seed packets, negotiable price', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Sell', 'wp-seedbank');?></td>
            <td><?php esc_html_e('100', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Tomato', 'wp-seedbank');?></td>
            <td><?php esc_html_e('seed packets', 'wp-seedbank');?></td>
            <td><?php esc_html_e('2017-01-01', 'wp-seedbank');?></td>
            <td><?php esc_html_e('2015-06-01', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Price is negotiable. Reply here or by phone at (555) 555-5555 if interested.', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Solanum', 'wp-seedbank');?></td>
        </tr>
        <tr>
            <td colspan="8">&hellip;</td>
        </tr>
        <tr>
            <td><?php esc_html_e('These are the best bean seeds!', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Swap', 'wp-seedbank');?></td>
            <td><?php esc_html_e('20', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Bean', 'wp-seedbank');?></td>
            <td><?php esc_html_e('packets', 'wp-seedbank');?></td>
            <td><?php esc_html_e('2015-03-30', 'wp-seedbank');?></td>
            <td><?php esc_html_e('2014-05-01', 'wp-seedbank');?></td>
            <td><?php esc_html_e('These beans are kidney beans. They are delicious and nutritious, but taste nothing like chicken.', 'wp-seedbank');?></td>
            <td><?php esc_html_e('Phaseolus', 'wp-seedbank');?></td>
        </tr>
    <tbody>
</table>
<?php
    }

    public function processBatchExchangeForm ($fields) {
        $error_msgs = array(
            'bad_nonce' => sprintf(
                /*
                   TRANSLATORS:
                   Ignore these placeholders, they are for HTML code.
                 */
                esc_html__('Your batch exchange request has expired or is invalid. Please %sstart again%s.', 'wp-seedbank'),
                '<a href="' . admin_url('edit.php?post_type=' . $this->post_type . '&page=' . $this->post_type . '_batch_exchange') . '">',
                '</a>'
            ),
            'no_source' => sprintf(
                /*
                   TRANSLATORS:
                   Ignore these placeholders, they are for HTML code.
                 */
                __('Please let us know where to find your data. You will need to %sstart again%s.', 'wp-seedbank'),
                '<a href="' . admin_url('edit.php?post_type=' . $this->post_type . '&page=' . $this->post_type . '_batch_exchange') . '">',
                '</a>'
            )

        );
        if (!wp_verify_nonce($_POST['batch-exchange'], $this->post_type . '-batch-exchange')) {
            print $error_msgs['bad_nonce'];
            return;
        }

        $where = ($_FILES[$this->post_type . '-batch-exchange-file-data']['tmp_name']) ?
            $_FILES[$this->post_type . '-batch-exchange-file-data']['tmp_name'] :
            $_POST[$this->post_type . '-batch-exchange-file-url'];
        if (!$where) {
            print $error_msgs['no_source'];
            return;
        }

        $strip = (isset($_POST[$this->post_type . '-batch-exchange-strip-headers'])) ? true : false;
        $post_status = (isset($_POST[$this->post_type . '-batch-exchange-post_status'])) ? 'draft' : 'publish';

        $data = WP_SeedBank_Utilities::csvToMultiArray($where, $strip); // true means "strip headers"

        $new_post_ids = array();
        // For each line in the CSV,
        foreach ($data as $x) {
            // Parse CSV field positions.
            list(
                $title,
                $exchange_type,
                $quantity,
                $common_name,
                $unit,
                $seed_expiry_date,
                $exchange_expiry_date,
                $body,
                $scientific_name
            ) = $x;
            // convert it into a new seed exchange post.
            $taxs = array();
            foreach ($this->taxonomies as $taxonomy) {
                $taxs[$this->post_type . '_' . $taxonomy[0]] = trim($$taxonomy[0]);
            }
            // Convert Scientific Name string to category ID.
            if (!empty($taxs[$this->post_type . '_scientific_name'])) {
                // search by name or, if that's not found, by slug
                if ($t = get_term_by('name', $taxs[$this->post_type . '_scientific_name'], $this->post_type . '_scientific_name')) {
                    $taxs[$this->post_type . '_scientific_name'] = $t->term_id;
                } else if ($t = get_term_by('slug', sanitize_title_with_dashes($taxs[$this->post_type . '_scientific_name']), $this->post_type . '_scientific_name')) {
                    $taxs[$this->post_type . '_scientific_name'] = $t->term_id;
                }
            }
            $post = array(
                'comment_status' => 'open',
                'ping_status' => 'open', 
                'post_author' => get_current_user_id(), // TODO: Get the user ID.
                'post_content' => $body,
//                'post_date' => , // should be "now"?
//                'post_date_gmt' => , // should be "now"?
                'post_status' => $post_status,
                'post_title' => $title,
                'post_type' => $this->post_type,
                'tax_input' => $taxs
            );
            $p = wp_insert_post($post);
            if (!$p) {
                // TODO: Handle error?
            } else {
                $new_post_ids[] = $p;
                update_post_meta($p, $this->post_type . '_common_name', sanitize_text_field($common_name));
                update_post_meta($p, $this->post_type . '_quantity', sanitize_text_field($quantity));
                // New posts are always active?
                update_post_meta($p, $this->post_type . '_exchange_status', sanitize_text_field(__('Active', 'wp-seedbank')));
                if (empty($post['tax_input'][$this->post_type . '_exchange_status'])) {
                    $tx = get_term_by('name', __('Active', 'wp-seedbank'), $this->post_type . '_exchange_status');
                    wp_set_object_terms($p, $tx->term_id, $this->post_type . '_exchange_status');
                }
                update_post_meta($p, $this->post_type . '_exchange_type', sanitize_text_field($exchange_type));
                update_post_meta($p, $this->post_type . '_unit', sanitize_text_field($unit));
                update_post_meta($p, $this->post_type . '_seed_expiry_date', gmdate('U', strtotime($seed_expiry_date)));
                update_post_meta($p, $this->post_type . '_exchange_expiry_date', gmdate('U', strtotime($exchange_expiry_date)));
            }
        }

        // Display success message.
        $n = count($new_post_ids);
        if ($n) { ?>
            <p><?php print sprintf(esc_html(_n('Successfully imported %d new Seed Exchange Post.', 'Successfully imported %d new Seed Exchange Posts.', $n, 'wp-seedbank')), $n);?></p>
            <p><a href="<?php print admin_url('edit.php?post_type=' . $this->post_type);?>"><?php esc_html_e('All Seed Exchanges', 'wp-seedbank');?></a>.</p>
<?php
        }
    }

}
$WP_SeedBank = new WP_SeedBank();

class WP_SeedBank_Utilities {
    public function __construct () {
        // Do nothing.
    }

    public function csvToMultiArray ($infile, $strip_headers = false) {
        $f = fopen($infile, 'r');
        $r = array();
        while (($data = fgetcsv($f)) !== false) {
            $r[] = $data;
        }
        if ($strip_headers) {
            array_shift($r);
        }
        return $r;
    }
}
