<?php
/*
    Plugin Name: WP-SeedBank
    Plugin URI: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/
    Description: Add a seed exchange post type to turn your WordPress website into a community seedbank or seed library! :D
    Author: <a href="http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/#authors">The Hummingbird Project</a> and <a href="http://Cyberbusking.org/">Meitar "maymay" Moscovitz</a>
    Version: 0.3
    Text Domain: wp-seedbank
    Domain Path: /languages
*/

class WP_SeedBank {
    private $post_type = 'seedbank';
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
        add_action('save_post', array($this, 'savePost'));
        add_action('admin_init', array($this, 'registerCustomSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenus'));
        add_action('admin_enqueue_scripts', array($this, 'registerAdminScripts'));
        add_action('admin_head', array($this, 'registerCustomHelp'));

        add_action($this->post_type . '_expire_exchange', array($this, 'expireExchangePost'));

        add_action('manage_' . $this->post_type . '_posts_custom_column', array($this, 'displayCustomColumn'), 10, 2);
        add_filter('manage_' . $this->post_type . '_posts_columns', array($this, 'registerCustomColumns'));

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
                    // TODO: Will these variables cause an i18n bug? Unroll this loop?
                    'labels' => array(
                        'name'          => __($t_plural, 'wp-seedbank'),
                        'singular_name' => __($t_singular, 'wp-seedbank'),
                        'all_items'     => __("All $t_plural", 'wp-seedbank'),
                        'edit_item'     => __("Edit $t_singular", 'wp-seedbank'),
                        'update_item'   => __("Edit $t_singular", 'wp-seedbank'),
                        'add_new_item'  => __("Add New $t_singular", 'wp-seedbank'),
                        'new_item_name' => __("New $t_singular", 'wp-seedbank'),
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
        <option value="2"<?php if (2 === $options['display_meta']) { print ' selected="selected"'; }?>><?php _e('this plugin (below content)', 'wp-seedbank');?></option>
        <option value="1"<?php if (1 === $options['display_meta']) { print ' selected="selected"'; }?>><?php _e('this plugin (above content)', 'wp-seedbank');?></option>
        <option value="0"<?php if (0 === $options['display_meta']) { print ' selected="selected"'; }?>><?php _e('my own template', 'wp-seedbank');?></option>
    </select>
    <p class="description"><?php _e('Choosing your own template without writing your own template code may result in the Seed Exchange details not appearing on your website.', 'wp-seedbank');?></p>
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
    }

    // TODO: Fix the i18n of the fill-in-the-blank web form?
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
        <label><?php _e('I would like to', 'wp-seedbank');?> <?php print $type_select;?></label>
        <input name="<?php print $this->post_type;?>_quantity" value="<?php print esc_attr($custom["{$this->post_type}_quantity"][0]);?>" placeholder="<?php _e('enter a number', 'wp-seedbank');?>" />
        <?php print $common_name_select;?>
        <input name="<?php print $this->post_type;?>_unit" value="<?php print esc_attr($custom["{$this->post_type}_unit"][0]);?>" placeholder="<?php _e('packets', 'wp-seedbank');?>" />.
    </p>
    <p>
        <label><?php _e('These seeds will expire on or about', 'wp-seedbank');?> <input name="<?php print $this->post_type;?>_seed_expiry_date" class="datepicker" value="<?php print esc_attr(date(get_option('date_format'), $custom["{$this->post_type}_seed_expiry_date"][0]));?>" placeholder="<?php _e('enter a date', 'wp-seedbank');?>" />.</label> <span class="description">(<?php _e('If these seeds are in a packet, the wrapping might have an expiration date. Put that here.', 'wp-seedbank');?>)</span>
    </p>
    <p>
        <label><?php _e("If I don't hear from anyone by", 'wp-seedbank');?> <input name="<?php print $this->post_type;?>_exchange_expiry_date" class="datepicker" value="<?php print esc_attr(date(get_option('date_format'), $custom["{$this->post_type}_exchange_expiry_date"][0]));?>" placeholder="<?php _e('enter a date', 'wp-seedbank');?>" />, <?php _e("I'll stop being available to make this exchange.", 'wp-seedbank');?></label> <span class="description">(<?php _e("If you don't get a response by this date, your request will automatically close.", 'wp-seedbank');?>)</span>
    </p>
    <p>
        <?php // TODO: i18n this? See question concerning madlibs style forms, at function signature. ?>
        <label><?php _e('This seed exchange is', 'wp-seedbank');?> <?php print $status_select;?>.</label> <span class="description">(<?php foreach ($status_options as $x) :?>The <code><?php _e($x->name, 'wp-seedbank');?></code> type is for <?php print strtolower($x->description);?> <?php endforeach;?>)</span>
    </p>
<?php
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
     * Runs when we save a Seed Exchange post.
     */
    public function savePost ($post_id) {
        if ($this->post_type !== $_POST['post_type']) { return; }
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
        update_post_meta($post_id, $this->post_type . '_exchange_status', 'Deleted');
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
            $custom = get_post_custom($post->ID);
            foreach ($this->meta_fields as $f) {
                $val = $custom[$this->post_type . '_' . $f][0];
                // For meta fields that end in '_date' or '_time',
                if ($this->isDateOrTimeMeta($f)) {
                    // convert their value to blog's local date format
                    $val = date(get_option('date_format'), $val);
                }
                if ($val) {
                    $append .= '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $f))) . ':</strong> ' . esc_html($val) . ' </li>';
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

    // TODO: i18n these column names. Unroll taxonomy loop?
    public function registerCustomColumns ($columns) {
        $my_columns = array();
        foreach ($this->taxonomies as $taxonomy) {
            $my_columns[$this->post_type . '_' . $taxonomy[0]] = ucwords(str_replace('_', ' ', $taxonomy[0]));
        }
        return array_merge($columns, $my_columns);
    }

    public function displayCustomColumn ($column, $post_id) {
        foreach ($this->taxonomies as $taxonomy) {
            if ($column !== $this->post_type . '_' . $taxonomy[0]) {
                continue;
            }
            the_terms($post_id, $this->post_type . '_' . $taxonomy[0]);
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
                $p3 = esc_html__('When you have done this, click the "Publish" (or "Submit for review") button. Congratulations! And thank you for spreading the seed love!', 'wp-seedbank');
                $html = <<<END_HTML
<p>$p1</p>
<p>$p2</p>
<ol>
    <li>$ol1</li>
    <li>$ol2</li>
    <li>$ol3</li>
</ol>
<p>$p3</p>
END_HTML;
                $screen->add_help_tab(array(
                    'id' => $this->post_type . '-' . $screen->base . '-help',
                    'title' => __('Adding a Seed Exchange', 'wp-seedbank'),
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
                '<a href="http://meitar.moscovitz.name/" title="' . __('Who is this person?', 'wp-seedbank') . '">' . __('a houseless, jobless, nomadic "vigilante programmer"', 'wp-seedbank') . '</a>',
                '<a href="http://wordpress.org/plugins/wp-seedbank/other_notes/" title="' . __('Credits for WP-SeedBank', 'wp-seedbank') . '">' . __('Donations are appreciated.', 'wp-seedbank') . '</a>'
            ) . '</p>'
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

        flush_rewrite_rules();

        // Exchange Types (verbs)
        wp_insert_term(__( 'Swap', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering seeds for other seeds.', 'wp-seedbank')));
        wp_insert_term(__( 'Sell', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering seeds for money.', 'wp-seedbank')));
        wp_insert_term(__( 'Give', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges offering free seeds being given away.', 'wp-seedbank')));
        wp_insert_term(__( 'Get', 'wp-seedbank'), $this->post_type . '_exchange_type', array('description' => __('Exchanges requesting seeds of a variety not already listed.', 'wp-seedbank')));

        // Genera
        // Empty slugs so we calculate the slug form the i18n'd term name.
        wp_insert_term(__('Abelmoschus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Agastache', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Allium', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Amaranthus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Anagallis', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Anethum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Anthenum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Antirrhinum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Apium', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Asclepias', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Basella', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Beta', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Brassica', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Calendula', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Capsicum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cardiospermum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Centaurea', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Chrysanthemum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cichorium', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Citrullus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cleome', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cobaea', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Consolida', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Convolvulus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Coreopsis', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Coriandrum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cosmos', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cucumis', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Cucurbita', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Dalea', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Daucus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Diplotaxis', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Dolichos', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Echinacea', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Eruca', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Eschscholzia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Foeniculum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Fragaria', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Gaillardia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Glycine', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Helianthus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Ipomoea', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Koeleria', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Lactuca', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Lagenaria', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Lathyrus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Lupinus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Lycopersicon', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Malope', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Matricaria', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Mentha', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Mirabilis', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Nigella', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Ocimum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Origanum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Papaver', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Passiflora', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Penstemon', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Petrolselinum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Phaseolus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Physalis', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Pisum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Poterium', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Raphanus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Rosmarinus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Rudbeckia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Salvia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Scorpiurus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Solanum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Spinachia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Tagetes', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Thunbergia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Thymus', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Triticum ', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Tropaeolum', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Zea', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));
        wp_insert_term(__('Zinnia', 'wp-seedbank'), $this->post_type . '_seed_genus', array('slug' => ''));

        // Common Names
        // Empty slugs so we calculate the slug form the i18n'd term name.
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
<h2><?php _e('Batch Seed Exchange', 'wp-seedbank');?></h2>
<p><?php _e('This page allows you to upload a comma-separated values (CSV) file that will be translated to seed exchange requests or offers.', 'wp-seedbank');?> <?php _e('The CSV file should have the structure like', 'wp-seedbank');?> <a href="#wp-seedbank-batch-exchange-example"><?php _e('the example shown in the table below', 'wp-seedbank');?></a>.</p>
<form id="<?php print esc_attr($this->post_type)?>-batch-exchange-form" name="<?php print esc_attr($this->post_type);?>_batch_exchange" action="<?php print esc_url($_SERVER['PHP_SELF'] . '?post_type=' . $this->post_type . '&amp;page=' . $this->post_type . '_batch_exchange');?>" method="post" enctype="multipart/form-data">
    <?php wp_nonce_field($this->post_type . '-batch-exchange', 'batch-exchange');?>
    <input type="hidden" name="<?php print esc_attr($this->post_type);?>-batch-exchange-step" value="1" />
    <p>
        <?php _e('My batch exchange file is located on', 'wp-seedbank');?>
        <select id="seedbank-batch-exchange-data-source">
            <option value="another website"><?php _e('another website', 'wp-seedbank');?></option>
            <option value="my computer"><?php _e('my computer', 'wp-seedbank');?></option>
        </select>.
        <?php // TODO: Figure out how to i18n this madlibs style thing. ?>
        It
        <select name="<?php print esc_attr($this->post_type);?>-batch-exchange-strip-headers">
                <option value="1">has</option>
                <option value="0">does not have</option>
        </select> column labels (a header row).
    </p>
    <fieldset id="seedbank-batch-exchange-web-fetch"><legend><?php _e('Web fetch options', 'wp-seedbank');?></legend>
        <p><label><?php _e('The address of the file containing my seed exchange data is', 'wp-seedbank');?> <input name="<?php print esc_attr($this->post_type);?>-batch-exchange-file-url" value="" placeholder="<?php esc_attr_e('http://mysite.com/file.csv', 'wp-seedbank');?>" />.</label></p>
    </fieldset>
    <fieldset id="seedbank-batch-exchange-file-upload"><legend><?php _e('File upload options', 'wp-seedbank');?></legend>
        <p><label><?php _e('The file on my computer containing my seed exchange data is', 'wp-seedbank');?> <input type="file" name="<?php print esc_attr($this->post_type);?>-batch-exchange-file-data" value="" />.</label></p>
    </fieldset>
    <p><label><input type="checkbox" name="<?php print esc_attr($this->post_type);?>-batch-exchange-post_status" value="draft" /> <?php _e('Let me review each seed exchange before publishing.', 'wp-seedbank');?></label></p>
    <p><input type="submit" name="<?php print esc_attr($this->post_type);?>-batch-exchange-submit" value="<?php esc_attr_e('Make Seed Exchanges', 'wp-seedbank');?>" /></p>
</form>
<table summary="<?php esc_attr_e('Example of batch seed exchange data.', 'wp-seedbank');?>" id="wp-seedbank-batch-exchange-example">
    <thead>
        <tr>
            <th><?php _e('Title', 'wp-seedbank');?></th>
            <th><?php _e('Type', 'wp-seedbank');?></th>
            <th><?php _e('Quantity', 'wp-seedbank');?></th>
            <th><?php _e('Common Name', 'wp-seedbank');?></th>
            <th><?php _e('Unit label', 'wp-seedbank');?></th>
            <th><?php _e('Seed expiration date', 'wp-seedbank');?></th>
            <th><?php _e('Exchange expiration date', 'wp-seedbank');?></th>
            <th><?php _e('Notes', 'wp-seedbank');?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?php _e('Looking to swap peppers for carrots', 'wp-seedbank');?></td>
            <td><?php _e('Swap', 'wp-seedbank');?></td>
            <td><?php _e('5', 'wp-seedbank');?></td>
            <td><?php _e('Pepper', 'wp-seedbank');?></td>
            <td><?php _e('seeds', 'wp-seedbank');?></td>
            <td><?php _e('2016-05-01', 'wp-seedbank');?></td>
            <td><?php _e('2014-05-01', 'wp-seedbank');?></td>
            <td><?php _e('Ideally, I would like to receive carrot seeds in exchange. Thanks!', 'wp-seedbank');?></td>
        </tr>
        <tr>
            <td><?php _e('For sale: tomato seed packets, negotiable price', 'wp-seedbank');?></td>
            <td><?php _e('Sell', 'wp-seedbank');?></td>
            <td><?php _e('100', 'wp-seedbank');?></td>
            <td><?php _e('Tomato', 'wp-seedbank');?></td>
            <td><?php _e('seed packets', 'wp-seedbank');?></td>
            <td><?php _e('2017-01-01', 'wp-seedbank');?></td>
            <td><?php _e('2015-06-01', 'wp-seedbank');?></td>
            <td><?php _e('Price is negotiable. Reply here or by phone at (555) 555-5555 if interested.', 'wp-seedbank');?></td>
        </tr>
        <tr>
            <td colspan="8">&hellip;</td>
        </tr>
        <tr>
            <td><?php _e('These are the best bean seeds!', 'wp-seedbank');?></td>
            <td><?php _e('Swap', 'wp-seedbank');?></td>
            <td><?php _e('20', 'wp-seedbank');?></td>
            <td><?php _e('Bean', 'wp-seedbank');?></td>
            <td><?php _e('packets', 'wp-seedbank');?></td>
            <td><?php _e('2015-03-30', 'wp-seedbank');?></td>
            <td><?php _e('2014-05-01', 'wp-seedbank');?></td>
            <td><?php _e('These beans are kidney beans. They are delicious and nutritious, but taste nothing like chicken.', 'wp-seedbank');?></td>
        </tr>
    <tbody>
</table>
<?php
    }

    // TODO: i18n this.
    public function processBatchExchangeForm ($fields) {
        $error_msgs = array(
            'bad_nonce' => sprintf(
                __('Your batch exchange request has expired or is invalid. Please %s start again %s.', 'wp-seedbank'),
                '<a href="' . admin_url('edit.php?post_type=' . $this->post_type . '&page=' . $this->post_type . '_batch_exchange') . '">',
                '</a>'
            ),
            'no_source' => sprintf(
                __('Please let us know where to find your data. You will need to %s start again %s.', 'wp-seedbank'),
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
                update_post_meta($p, $this->post_type . '_seed_expiry_date', gmdate('U', strtotime($seed_expiry_date)));
                update_post_meta($p, $this->post_type . '_exchange_expiry_date', gmdate('U', strtotime($exchange_expiry_date)));
            }
        }

        // Display success message.
        $n = count($new_post_ids);
        if ($n) { ?>
            <p><?php print sprintf(esc_html__('Successfully imported %d new Seed Exchange Posts.', 'wp-seedbank'), $n);?></p>
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
