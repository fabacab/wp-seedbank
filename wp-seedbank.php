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
    Text Domain: wp-seedbank
    Domain Path: /languages
*/

class WP_SeedBank {
    private $post_type = 'seedbank';
    private $textdomain = 'wp-seedbank';
    private $taxonomies = array(
        array('exchange_type'),
        array('common_name'),
        array('seed_genus', 'plural' => 'seed_genera'),
        array('exchange_status', 'plural' => 'exchange_statuses')
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
        add_action('save_post', array($this, 'saveMeta'));
        add_action('admin_init', array($this, 'registerCustomSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenus'));
        add_action('admin_enqueue_scripts', array($this, 'registerAdminScripts'));
        add_action('admin_head', array($this, 'registerCustomHelp'));

        add_filter('the_content', array($this, 'displayContent'));
    }

    public function registerL10n () {
        load_plugin_textdomain($this->textdomain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
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
            'menu_icon' => plugins_url(basename(__DIR__) . '/images/seedexchange_icon.png'),
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
            __('Display Seed Exchange details using', $this->textdomain),
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
        <option value="2"<?php if (2 === $options['display_meta']) { print ' selected="selected"'; }?>><?php _e('this plugin (below content)', $this->textdomain);?></option>
        <option value="1"<?php if (1 === $options['display_meta']) { print ' selected="selected"'; }?>><?php _e('this plugin (above content)', $this->textdomain);?></option>
        <option value="0"<?php if (0 === $options['display_meta']) { print ' selected="selected"'; }?>><?php _e('my own template', $this->textdomain);?></option>
    </select>
    <p class="description"><?php _e('Choosing your own template without writing your own template code may result in the Seed Exchange details not appearing on your website.', $this->textdomain);?></p>
<?php
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
        <label>These seeds will expire on or about <input name="<?php print $this->post_type;?>_seed_expiry_date" class="datepicker" value="<?php print esc_attr($custom["{$this->post_type}_seed_expiry_date"][0]);?>" placeholder="<?php _e('enter a date', $this->textdomain);?>" />.</label> <span class="description">(<?php _e('If these seeds are in a packet, the wrapping might have an expiration date. Put that here.', $this->textdomain);?>)</span>
    </p>
    <p>
        <label>If I don't hear from anyone by <input name="<?php print $this->post_type;?>_exchange_expiry_date" class="datepicker" value="<?php print esc_attr($custom["{$this->post_type}_exchange_expiry_date"][0]);?>" placeholder="<?php _e('enter a date', $this->textdomain);?>" />, I'll stop being available to make this exchange.</label> <span class="description">(<?php _e("If you don't get a response by this date, your request will automatically close.", $this->textdomain);?>)</span>
    </p>
    <p>
        <label>This seed exchange is <?php print $status_select;?>.</label> <span class="description">(<?php foreach ($status_options as $x) :?>The <code><?php _e($x->name, $this->textdomain);?></code> type is for <?php print strtolower($x->description);?> <?php endforeach;?>)</span>
    </p>
<?php
    }

    public function saveMeta ($post_id) {
        if ($this->post_type !== $_POST['post_type']) { return; }
        if (!wp_verify_nonce($_POST[$this->post_type . '_meta_box_details_nonce'], 'editing_' . $this->post_type)) { return; }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        foreach ($this->meta_fields as $f) {
            if (isset($_REQUEST[$this->post_type . '_' . $f])) {
                update_post_meta($post_id, $this->post_type . '_' . $f, sanitize_text_field($_REQUEST[$this->post_type . '_' . $f]));
                wp_set_object_terms($post_id, sanitize_text_field($_REQUEST[$this->post_type . '_' . $f]), $this->post_type . '_' . $f);
            }
        }
    }

    public function displayContent ($content) {
        global $post;
        $options = get_option($this->post_type . '_settings');
        if ($this->post_type === get_post_type($post->ID)) {
            $append .= '<ul id="' . esc_attr($this->post_type . '-meta-' . $post->ID) . '" class="' . esc_attr($this->post_type) . '-meta">';
            $custom = get_post_custom($post->ID);
            foreach ($this->meta_fields as $f) {
                $x = $custom[$this->post_type . '_' . $f];
                if ($x) {
                    $append .= '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $f))) . ':</strong> ' . esc_html($x[0]) . ' </li>';
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
        wp_register_script('wp-seedbank', plugins_url(basename(__DIR__) . '/wp-seedbank.js'), array('jquery', 'jquery-ui-datepicker'));
        wp_enqueue_script('wp-seedbank');
        $x = $wp_scripts->query('jquery-ui-core');
        wp_enqueue_style('jquery-ui-smoothness', "//ajax.googleapis.com/ajax/libs/jqueryui/{$x->ver}/themes/smoothness/jquery-ui.min.css", false, null);
    }

    // TODO: i18n this.
    public function registerCustomHelp () {
        $screen = get_current_screen();
        if ($screen->post_type !== $this->post_type) { return; }
        // Tabs for specific screens.
        switch ($screen->id) {
            case 'seedbank':
                $html = <<<END_HTML
<p>Make a new Seed Exchange on this page. A Seed Exchange is just like a blog post, but tailored specifically for the Seedbank. Have some seeds to share, or trying to find seeds to grow? Let others know by posting here!</p>
<p>To make a new Seed Exchange, follow these steps:</p>
<ol>
    <li><strong>Write a title.</strong> Short, descriptive summaries are best. <a href="#" onclick="document.getElementById('title').focus()">Click here and then type your title.</a></li>
    <li><strong>Explain your request or offer.</strong> In your own words, describe what you're looking for or what you're hoping to get, or both. Be sure to include any important words you think others might use to find your post when they're searching the website. Include any additional information relevant to your posting. <a href="#" onclick="document.getElementById('content').focus()">Click here and then type your message.</a></strong></li>
    <li><strong>Fill in the details.</strong> In the "<a href="#{$this->post_type}-details-meta">Seed Exchange Details</a>" box, there are some fields you should fill in to help other people find your post by organizing it in a sensible place in the Seedbank. Simply complete the sentences of the fill-in-the-blank paragraph.</li>
</ol>
<p>When you've done this click the "Publish" (or "Submit for review") button. Congratulations! And thank you for spreading the seed love!</p>
END_HTML;
                $screen->add_help_tab(array(
                    'id' => $this->post_type . '-' . $screen->base . '-help',
                    'title' => __('Adding a Seed Exchange', $this->textdomain),
                    'content' => $html
                ));
            break;
            default:
            break;
        }
        // Tabs for all screens.
        $html = <<<END_HTML
<p><a href="https://wordpress.org/plugins/wp-seedbank/" title="WP-SeedBank on the WordPress Plugin Repository" rel="bookmark">The WP-SeedBank plugin</a> is a labor of love, and passion. Conceived by <a href="http://www.hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/" title="The HummingBird Project's WP-SeedBank Initiative">The HummingBird Project</a> and <a href="http://permaculturenews.org/2013/09/25/an-open-source-community-model-to-save-seeds-a-wordpress-seedbank-plugin/" title="An Open Source Community Model to Save Seeds – a WordPress Seedbank Plugin" rel="bookmark">initially developed at Cleveland GiveCamp</a>, it is maintained by <a href="http://meitar.moscovitz.name/" title="Who is this person?">a houseless, jobless, nomadic "vigilante programmer"</a> who loves fresh food and hates Monsanto. <a href="http://wordpress.org/plugins/wp-seedbank/other_notes/" title="Credits for WP-SeedBank">Donations are appreciated</a>.</p>
END_HTML;
        $screen->add_help_tab(array(
            'id' => $this->post_type . '-' . $screen->base . '-about-help',
            'title' => __('About the WP-SeedBank', $this->textdomain),
            'content' => $html
        ));
    }

    public function activate () {
        $this->createDataTypes(); // This registers new taxonomies.

        // If the old version 0.2.3 exists
        if ('0.2.3' === get_option('wp_seedbank_version')) {
            global $wpdb;
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
            // Now we need to associate the posts with our taxonomies.
            $sql = $wpdb->prepare(
                "SELECT * FROM {$wpdb->postmeta} WHERE meta_key LIKE '%s'",
                like_escape($this->post_type) . '%'
            );
            $results = $wpdb->get_results($sql);
            foreach ($results as $row) {
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
        wp_insert_term(__('Pepper', $this->textdomain), $this->post_type . '_common_name', array( 'slug' => 'pepper' ) );
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

    public function registerAdminMenus () {
        add_submenu_page(
            'edit.php?post_type=' . $this->post_type,
            'Batch Seed Exchange',
            'Batch Exchange',
            'edit_posts',
            $this->post_type . '_batch_exchange',
            array($this, 'dispatchBatchExchangePages')
        );
    }

    public function dispatchBatchExchangePages () {
        $step = (int) $_POST[$this->post_type . '-batch-exchange-step'];
        if (0 === $step) {
            self::printBatchExchangeForm();
        } else if (1 === $step) {
            self::processBatchExchangeForm($_POST);
        }
    }

    // Produce HTML for showing the submenu page.
    // TODO: i18n this.
    public function printBatchExchangeForm () { ?>
<h2>Batch Seed Exchange</h2>
<p>This page allows you to upload a comma-separated values (CSV) file that will be translated to seed exchange requests or offers. The CSV file should have the structure like <a href="#wp-seedbank-batch-exchange-example">the example shown in the table below</a>.</p>
<form id="<?php print esc_attr($this->post_type)?>-batch-exchange-form" name="<?php print esc_attr($this->post_type);?>_batch_exchange" action="<?php print esc_url($_SERVER['PHP_SELF'] . '?post_type=' . $this->post_type . '&amp;page=' . $this->post_type . '_batch_exchange');?>" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field($this->post_type . '-batch-exchange', 'batch-exchange');?>
    <input type="hidden" name="<?php print esc_attr($this->post_type);?>-batch-exchange-step" value="1" />
    <p>
        My batch exchange file is located on
        <select id="seedbank-batch-exchange-data-source">
            <option>another website</option>
            <option>my computer</option>
        </select>.
        It
        <select name="<?php print esc_attr($this->post_type);?>-batch-exchange-strip-headers">
                <option value="1">has</option>
                <option value="0">does not have</option>
        </select> column labels (a header row).
    </p>
    <fieldset id="seedbank-batch-exchange-web-fetch"><legend>Web fetch options</legend>
        <p>The address of the file containing my seed exchange data is <input name="<?php print esc_attr($this->post_type);?>-batch-exchange-file-url" value="" placeholder="http://mysite.com/file.csv" />.</p>
    </fieldset>
    <fieldset id="seedbank-batch-exchange-file-upload"><legend>File upload options</legend>
        <p>The file on my computer containing my seed exchange data is <input type="file" name="<?php print esc_attr($this->post_type);?>-batch-exchange-file-data" value="" />.</p>
    </fieldset>
    <p><label><input type="checkbox" name="<?php print esc_attr($this->post_type);?>-batch-exchange-post_status" value="draft" /> Let me review each seed exchange before publishing.</label></p>
    <p><input type="submit" name="<?php print esc_attr($this->post_type);?>-batch-exchange-submit" value="Make Seed Exchanges" /></p>
</form>
<table summary="Example of batch seed exchange data." id="wp-seedbank-batch-exchange-example">
    <thead>
        <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Common Name</th>
            <th>Unit label</th>
            <th>Seed expiration date</th>
            <th>Exchange expiration date</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Looking to swap peppers for carrots</td>
            <td>Swap</td>
            <td>5</td>
            <td>Pepper</td>
            <td>seeds</td>
            <td>2016-05-01</td>
            <td>2014-05-01</td>
            <td>Ideally, I'd like to receive carrot seeds in exchange. Thanks!</td>
        </tr>
        <tr>
            <td>For sale: tomato seed packets, negotiable price</td>
            <td>Sell</td>
            <td>100</td>
            <td>Tomato</td>
            <td>seed packets</td>
            <td>2017-01-01</td>
            <td>2015-06-01</td>
            <td>Price is negotiable. Reply here or by phone at (555) 555-5555 if interested.</td>
        </tr>
        <tr>
            <td colspan="8">&hellip;</td>
        </tr>
        <tr>
            <td>These are the best bean seeds!</td>
            <td>Swap</td>
            <td>20</td>
            <td>Bean</td>
            <td>packets</td>
            <td>2015-03-30</td>
            <td>2014-05-01</td>
            <td>These beans are kidney beans. They're delicious and nutritious, but taste nothing like chicken.</td>
        </tr>
    <tbody>
</table>
<?php
    }

    // TODO: i18n this.
    public function processBatchExchangeForm ($fields) {
        if (!wp_verify_nonce($_POST['batch-exchange'], $this->post_type . '-batch-exchange')) { ?>
            <p>Your batch exchange request has expired or is invalid. Please <a href="<?php print admin_url('edit.php?post_type=' . $this->post_type . '&page=' . $this->post_type . '_batch_exchange');?>">start again</a>.</p>
<? 
            return;
        }

        $where = ($_FILES[$this->post_type . '-batch-exchange-file-data']['tmp_name']) ?
            $_FILES[$this->post_type . '-batch-exchange-file-data']['tmp_name'] :
            $_POST[$this->post_type . '-batch-exchange-file-url'];
        if (!$where) { ?>
            <p>Please let us know where to find your data. You'll need to <a href="<?php print admin_url('edit.php?post_type=' . $this->post_type . '&page=' . $this->post_type . '_batch_exchange');?>">start again</a>.</p>
<?php
            return;
        }

        $strip = ($_POST[$this->post_type . '-batch-exchange-strip-headers']) ? true : false; 
        $post_status = ($_POST[$this->post_type . '-batch-exchange-post_status']) ? 'draft' : 'publish';

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
                $body
            ) = $x;
            // convert it into a new seed exchange post.
            $taxs = array();
            foreach ($this->taxonomies as $taxonomy) {
                $taxs[$this->post_type . '_' . $taxonomy[0]] = $$taxonomy[0];
            }
            $post = array(
                'comment_status' => 'open',
                'ping_status' => 'open', 
                'post_author' => get_current_user_id(), // TODO: Get the user ID.
                'post_content' => $body,
//                'post_date' => , // should be "now"?
//                'post_date_gmt' => , // should be "now"?
//                'post_name' => , // automatic?
//                'post_parent' => , // automatic?
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
                update_post_meta($p, $this->post_type . '_exchange_status', sanitize_text_field('Active')); // New posts are always active?
                update_post_meta($p, $this->post_type . '_exchange_type', sanitize_text_field($exchange_type));
                update_post_meta($p, $this->post_type . '_unit', sanitize_text_field($unit));
                // TODO: Change the format of these dates to unix timestamps?
                update_post_meta($p, $this->post_type . '_seed_expiry_date', date('m/d/Y', strtotime($seed_expiry_date)));
                update_post_meta($p, $this->post_type . '_exchange_expiry_date', date('m/d/Y', strtotime($exchange_expiry_date)));
            }
        }

        // Display success message.
        $n = count($new_post_ids);
        if ($n) { ?>
            <p>Successfully imported <?php print $n;?> new <a href="<?php print admin_url('edit.php?post_type=' . $this->post_type);?>">seed exchange posts</a>.</p>
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
