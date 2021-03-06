<?php

/**
 * Main plugin class, does WordPress side adjustments for Wufoo API
 *
 * @author sourabh
 */
class rtWufoo {

    /**
     *
     * @var string The Wufoo subdomain
     */
    var $subdomain = '';

    /**
     *
     * @var string The Wufoo API key
     */
    var $api_key = '';

    /**
     *
     * @var object Instance of WufooAPIWrapper
     */
    var $wufoo = null;

    /**
     *
     * @var object Instance of rtProgress for rendering the progress bar UI
     */
    var $progress = null;

    /**
     * Hooks into admin_menu action to initialise the UI
     */
    function __construct() {
        add_action('admin_menu', array($this, 'admin'), 11);

        add_action(
                'wp_ajax_rt_wufoo_api_form', array($this, 'process_api_form')
        );
        add_action(
                'wp_ajax_rt_wufoo_form_select_ui', array($this, 'ajax_form_select_ui')
        );
        add_action(
                'wp_ajax_rt_wufoo_comment_count_ajax', array($this, 'comment_count_ajax')
        );
        add_action(
                'wp_ajax_rt_wufoo_comment_import', array($this, 'comment_import')
        );

        add_action(
                'wp_ajax_rt_wufoo_comment_next', array($this, 'comment_next')
        );
        add_action(
                'wp_ajax_rt_wufoo_map_users', array($this, 'map_users')
        );
        add_action(
                'wp_ajax_rt_wufoo_form_fields_map', array($this, 'form_fields_ui')
        );
        add_action(
                'wp_ajax_rt_wufoo_field_mapping_form', array($this, 'entries_import_ui')
        );
        add_action(
                'wp_ajax_rt_wufoo_import_entries', array($this, 'entries_import')
        );
    }

    /**
     * Adds the submenu to Gravity Forms menu on the dashboard
     * Enqueues the necessary js and css
     */
    function admin() {
        $hook = add_submenu_page(
                'gf_edit_forms', 'Wufoo to Gravity', 'Wufoo Importer', 'administrator', 'mapper_wufoo', array($this, 'ui')
        );
        add_action('admin_print_scripts-' . $hook, array($this, 'enqueue'));
    }

    /**
     *
     */
    function comment_count_ajax() {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'rt_wufoo_comment_count_ajax') {
            echo $this->comment_count();
        }
        die();
    }

    /**
     *
     * @return type
     */
    function comment_count() {
        $wuf_form = $_REQUEST['form'];
        if (empty($wuf_form))
            echo '-1';

        $this->init();

        try {
            $total_comment_cont_obj = $this->wufoo->getCommentCount($wuf_form);
        } catch (Exception $rt_importer_e) {
            $this->error($rt_importer_e);
        }


        return $total_comment_cont_obj->Count;
    }

    /**
     *
     * @global type $wpdb
     */
    function comments_db_install() {
        global $wpdb;

        $table_name = $wpdb->prefix . "_rt_w2g_comments";

        $sql = "CREATE TABLE $table_name (
        commentid mediumint(9) NOT NULL,
        datecreated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        entryid tinytext,
        text text,
        commentedby tinytext,
        form tinytext,
        UNIQUE KEY commentid (commentid)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     *
     * @global type $wpdb
     * @return type
     */
    function imported_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . "_rt_w2g_comments";
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name};");
        return $count;
    }

    /**
     *
     * @global type $wpdb
     */
    function comment_import() {
        $form = $_POST['form'];
        $page_start = $_POST['comment_index'];
        $page_size = RT_WUFOO_IMPORT_PAGE_SIZE;
        $this->init();
        $this->comments_db_install();

        try {
            $comments = $this->wufoo->getPagedComments($form, $page_size, $page_start);
        } catch (Exception $rt_importer_e) {
            $this->error($rt_importer_e);
        }

        foreach ($comments as $comment) {
            global $wpdb;
            $table_name = $wpdb->prefix . "_rt_w2g_comments";

            $comment_exists = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE commentid='{$comment->CommentId}' AND entryid='{$comment->EntryId}';");
            if (!$comment_exists) {
                $rows_affected = $wpdb->insert(
                        $table_name, array(
                    'datecreated' => $comment->DateCreated,
                    'commentid' => $comment->CommentId,
                    'entryid' => $comment->EntryId,
                    'text' => $comment->Text,
                    'commentedby' => $comment->CommentedBy,
                    'form' => $form
                        )
                );
            }
        }
        echo count($comments) + ($page_start);
        die();
    }

    /**
     *
     */
    function ui() {
        ?>
        <div class="wrap">
            <h2>Wufoo to Gravity Forms Importer</h2>
            <p class="textleft">Use the wizard below to import your Wufoo form submissions to a Gravity Form</p>

            <div class="hr-divider"></div>
            <?php
            $this->init();


            echo '<div id="rt_wufoo_wizard" >';
            echo '<div id="rt_wufoo_error"></div>';

            echo '<div class="rt_wufoo_steps" id="rt-wufoo-step-api">';
            $this->api_form_ui();
            echo '</div>';

            echo '<div class="rt_wufoo_steps" id="rt-wufoo-step-form">';
            $this->form_select_ui();
            echo '</div>';

            echo '<div class="rt_wufoo_steps" id="rt-wufoo-step-users">';
            echo '</div>';

            echo '<div class="rt_wufoo_steps" id="rt-wufoo-step-fields">';
            echo '</div>';

            echo '<div class="rt_wufoo_steps" id="rt-wufoo-step-import">';
            echo '</div>';

            echo '</div>';
            ?>
        </div>
        <?php
    }

    /**
     * Saves Wufoo API credentials in the options table
     *
     * @param string $subdomain Wufoo subdomain
     * @param string $api_key Wufoo API key
     */
    function save_options($subdomain, $api_key) {
        update_site_option('rt_wufoo_gravity_subdomain', $subdomain);
        update_site_option('rt_wufoo_gravity_api_key', $api_key);
    }

    /**
     * Get the Wufoo API credentials and populate the appropriate properties
     */
    function set_options() {
        $this->subdomain = get_site_option('rt_wufoo_gravity_subdomain');
        $this->api_key = get_site_option('rt_wufoo_gravity_api_key');
    }

    /**
     * Process the API credentials submitted via the form
     */
    function process_api_form() {
        if ($_POST['action'] != 'rt_wufoo_api_form')
            echo '0';

        if (!empty($_POST['map_wuf_sub']) &&
                !empty($_POST['map_wuf_key'])) {
            $this->save_options(
                    $_POST['map_wuf_sub'], $_POST['map_wuf_key']
            );
            echo json_encode(array(
                'subdomain' => $_POST['map_wuf_sub'],
                'api_key' => $_POST['map_wuf_key']
            ));
        }

        die();
    }

    /**
     *
     * @return type
     */
    function init() {
        if (!$this->subdomain || !$this->api_key) {
            $this->set_options();
        }
        if (!isset($this->wufoo) || !is_object($this->wufoo)) {
            $this->setup_forms();
        }
    }

    /**
     *
     * @return type
     */
    function setup_forms() {
        if (!$this->subdomain || !$this->api_key)
            return;
        try {
            $this->wufoo = new rtWufooAPI($this->api_key, $this->subdomain);
        } catch (Exception $rt_importer_e) {
            $this->error($rt_importer_e);
        }
    }

    /**
     *
     */
    function api_form_ui() {
        ?>
        <div class="rt_wufoo_stepbox">
            <h3>Setup API information</h3>
            <form action="" method="post" id="rt_wufoo_api_form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="map_wuf_sub">
                                Wufoo subdomain
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   name="map_wuf_sub"
                                   id="map_wuf_sub"
                                   value="<?php echo $this->subdomain; ?>"
                                   />.wufoo.com
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="map_wuf_key">
                                Wufoo API key
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   name="map_wuf_key"
                                   id="map_wuf_key"
                                   value="<?php echo $this->api_key; ?>"
                                   />
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="submit"
                                   id="map_wuf_submit"
                                   name="map_wuf_submit"
                                   value="Next: Get Wufoo Forms"
                                   class="button"
                                   />
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }

    /**
     *
     * @return type
     */
    function input_form_selector() {
        $form_select = '<select name="rt_wufoo_form_selector" id="rt_wufoo_form_selector">'
                . '<option value="">&hellip;</option>';
        $this->init();
        try {
            $wforms = $this->wufoo->getForms();
        } catch (Exception $rt_importer_e) {
            $this->error($rt_importer_e);
        }
        foreach ($wforms as $hash => $wuf_form) {
            $form_select .= '<option value="' . $hash . '">' . $wuf_form->Name . '</option>';
        }
        $form_select .= '</select>';

        return $form_select;
    }

    /**
     *
     */
    function ajax_form_select_ui() {
        $this->form_select_ui();
        die();
    }

    /**
     *
     */
    function form_select_ui() {
        $this->init();
        if (!empty($this->subdomain) && !empty($this->api_key)) {
            ?>
            <div class="rt_wufoo_stepbox">
                <h3>Select Wufoo Form for Import</h3>
                <table class="form-table" id="map_wuf_forms_list_table">
                    <tr>
                        <th scope="row"><label for="map_wuf_forms_list">Select a Form:</label></th>
                        <td>
                            <?php echo $this->input_form_selector(); ?>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }
    }

    /**
     *
     */
    function comment_next() {
        ?>

        <tr id="rt_wufoo_comment_next">
            <td colspan="2">
                <?php
                $remaining = $this->comment_count() - $this->imported_count();
                if ($remaining > 0) {
                    $this->comment_progress_ui();
                    ?>
                    <form method="post" id="rt_wufoo_comment_import">
                        <input type="hidden" name="comment_index" id="rt_wufoo_comment_index" value="<?php echo $this->imported_count(); ?>" />
                        <input type="hidden" name="comment_count" id="rt_wufoo_comment_total" value="<?php echo $this->comment_count(); ?>" />
                        <input type="hidden" name="form" class="rt_wufoo_form" value="" />
                        <input type="submit" class="rt_wufoo_comment_btn button" name="rt_wufoo_import_comments" id="rt_wufoo_import_comments" value="Import Comments" />
                    </form>
                    <?php
                } else {
                    ?>
                    <form method="post" id="rt_wufoo_map_users">
                        <input type="hidden" name="comment_index" class="rt_wufoo_comment_index" value="<?php echo $this->imported_count(); ?>" />
                        <input type="hidden" name="comment_count" class="rt_wufoo_comment_count" value="<?php echo $this->comment_count(); ?>" />
                        <input type="hidden" name="form" class="rt_wufoo_form" value="" />
                        <input type="submit" class="rt_wufoo_comment_btn button" name="rt_wufoo_skip_comments" id="rt_wufoo_skip_comments" value="Next: Select Gravity Form" />
                    </form>
                    <?php
                }
                ?>
            </td>
        </tr>
        <?php
    }

    /**
     *
     */
    function comment_progress_ui() {
        $progress = (int) $this->imported_count() / (int) $this->comment_count() * 100;
        $instance = array(
            'name' => 'comment-import',
            'progress' => $progress
        );
        echo '<span id="rt_wufoo_imported_comments" class="rt_wufoo_completed">' . $this->imported_count() . '</span>';
        echo '<span class="rt_wufoo_progress_count_sep">/</span>';
        echo '<span id="rt_wufoo_total_comments" class="rt_wufoo_total">' . $this->comment_count() . '</span>';
        echo ' comments';
        echo $this->progress_ui($instance);
    }

    /**
     *
     * @global type $blog_id
     * @global type $wpdb
     */
    function map_users() {
        echo '<div class="rt_wufoo_stepbox">';
        echo '<form action="" method="post" id="rt_wufoo_form_fields_map">';
        echo $this->import_to();

        global $blog_id;
        if ($this->comment_count() > 0) {
            $grav_users = get_users(
                    array('blog_id' => $blog_id)
            );
            $wuf_form = $_POST['form'];
            $wuf_commentators = array();
            global $wpdb;
            $table_name = $wpdb->prefix . "_rt_w2g_comments";
            $wuf_commentators = $wpdb->get_results(
                    "
            SELECT DISTINCT commentedby
            FROM {$table_name}
            WHERE form= '{$wuf_form}'
            "
            );

//Return the markup
            foreach ($grav_users as $user) {
                $user_options .= '<option value="' . $user->ID . '">' . $user->user_login . '</option>';
            }
        }



        if (isset($wuf_commentators) && !empty($wuf_commentators)) {
            $return .= '<h4>Sync users for notes</h4>';
            $return .= '<table class="form-table" id="map_wuf_comment_mapping_table">';
            foreach ($wuf_commentators as $wuf_c_i => $wuf_commentator) {
                $return .= '<tr>';
                $return .= '<th scope="row">' . $wuf_commentator->commentedby . '</th>';
                $return .= '<td><select name="users[' . $wuf_commentator->commentedby . ']">' . $user_options . '</select></td>';
                $return .= '</tr>';
            }
            $return .= '<tr><td colspan="2"><input type="submit" name="map_wuf_get_fields" id="map_wuf_get_fields" value="Next: Sync Form fields" class="button"/></td></tr>';
            $return .= '</table>';
        } else {
            $return .= '<input type="submit" name="map_wuf_get_fields" id="map_wuf_get_fields" value="Next: Sync Form fields" class="button"/>';
        }
        $return .= '</form>';

        echo $return;
        echo '</div>';

        die();
    }

    /**
     *
     * @return type
     */
    function import_to() {
        $wuf_form = $_POST['form'];
        $this->set_options();
        $wufoo = new rtWufooAPI($this->api_key, $this->subdomain);
        $wuf_form_fields = $wufoo->getFields($wuf_form);

        $gforms = $this->get_gravity_forms();
        if (isset($gforms) && !empty($gforms)) {
            $form_select = '<select name="gform" id="map_wuf_gforms_list">';
            $form_select .= '<option value="">&hellip;</option>';
            foreach ($gforms as $id => $form) {
                $form_select .= '<option value="' . $id . '">' . $form . '</option>';
            }
        } else {
            $form_select = '<strong>Please create some forms!</strong>';
        }

//Use this to get the entries and fields
        update_option('rt_wufoo_' . $wuf_form . '_fields', maybe_serialize($wuf_form_fields));


        $return = '<h3>Select a Gravity Form:</h3>';
        $return .= '<table class="form-table" id="map_wuf_gforms_list_table">';
        $return .= '<tr><th scope="row">Select a Gravity Form</th><td>' . $form_select . '</td></tr>';
        $return .= '</table>';
        $return .= '<input type="hidden" name="form" value="' . $wuf_form . '" id="map_wuf_form_hash"/>';

        return $return;
    }

    /**
     *
     */
    function form_fields_ui() {

        $this->init();

        $wform = $_REQUEST['form'];
        $gform = $_REQUEST['gform'];
        $wusers = $_REQUEST['users'];

        $entry_count = $_REQUEST['entry_count'];

        update_site_option('rt_wufoo_' . $wform . '_user_map', maybe_serialize($wusers));



        $gform_data = RGFormsModel::get_form_meta($gform);
        $fields = maybe_unserialize(get_site_option('rt_wufoo_' . $wform . '_fields'));

        $return = '<h3>Sync Form fields:</h3>';
        $return .='<form action="" method="post" id="rt_wufoo_field_mapping_form">';
        $return .= '<table class="wp-list-table widefat fixed" id="map_wuf_field_mapping_table">';
        $return .= '<tr><th>Gravity Form Field</th><th>Wufoo Form Field</th></tr>';
        foreach ($gform_data['fields'] as &$gfield) {
            if ($gfield['type'] != 'section' && $gfield['type'] != 'html') {
                $return .= '<tr>';
                $return .= '<td>' . ucfirst($gfield['label']) . '</td>';
                $return .= '<td>';
                $return .= '<select name="gform_fields[' . $gfield['id'] . ']" class="map_wuf_gform_fields">';
                $return .= '<option value="">Choose a field or Skip it</option>';
                foreach ($fields->Fields as $field => $field_data) {
                    if (strpos($field, 'Field') !== FALSE || strpos($field, 'Entry') !== FALSE) {
                        $return .= '<option value="' . $field . '">' . $field_data->Title . '</option>';
                    }
                }
                $return .= '<option value="other">Other Field</option>';
                $return .= '</select>';
                $return .= '</td>';
                $return .= '</tr>';
            }
        }
        $return .= '</table>';
        $return .= '<input type="hidden" name="form" value="' . $wform . '" id="map_wuf_form_hash"/>';
        $return .= '<input type="hidden" name="gform" id="map_wuf_gform" value="' . $gform . '"/>';
        $return .= '<input type="submit" name="map_wuf_field_mapping_submit" id="map_wuf_field_mapping_submit" value="Next: Save & Import Form Data" class="button" />';
        $return .= '</form>';
        echo '<div class="rt_wufoo_stepbox">' . $return . '</div>';
        die();
    }

    /**
     *
     */
    function entries_import_ui() {

        $return = '<div class="rt_wufoo_stepbox">';
        $return .= '<h3>Import form data</h3>';

        $wform = $_REQUEST['form'];
        $gform = $_REQUEST['gform'];
        $field_map = $_REQUEST['gform_fields'];
        update_site_option('rt_wufoo_' . $wform . '_field_map', maybe_serialize($field_map));

        $this->init();
        try {
            $entry_count = $this->wufoo->getEntryCount($wform);
        } catch (Exception $rt_importer_e) {
            $this->error($rt_importer_e);
        }


        $saved_entry_index = $this->imported_entries();
        $entry_index = $saved_entry_index ? $saved_entry_index : 0;
        $progress = (int) $entry_index / (int) $entry_count * 100;

        $instance = array(
            'name' => 'entry-import',
            'progress' => $progress
        );
        $return .= '<span id="rt_wufoo_imported_entries" class="rt_wufoo_completed">' . $entry_index . '</span>';
        $return .= '<span class="rt_wufoo_progress_count_sep">/</span>';
        $return .= '<span id="rt_wufoo_total_entries" class="rt_wufoo_total">' . $entry_count . '</span>';
        $return .= ' entries';
        $return .= $this->progress_ui($instance);
        $return .= '<form id="rt_wufoo_entries_import">';
        $return .= '<input type="hidden" name="form" value="' . $wform . '" id="map_wuf_form_hash"/>';
        $return .= '<input type="hidden" name="gform" id="map_wuf_gform" value="' . $gform . '"/>';
        $return .= '<input type="hidden" name="entry_index" id="rt_wufoo_entry_index" value="' . $entry_index . '"/>';
        $return .= '<input type="hidden" name="entry_count" id="rt_wufoo_entry_total" value="' . $entry_count . '"/>';
        if ($progress != 100) {
            $return .= '<input type="submit" name="rt_wufoo_start_import" id="rt_wufoo_start_import" value="Start Import" class="button" />';
        }

        $return .= '</form>';
        $return .= '</div>';
        echo $return;
        die();
    }

    /**
     *
     * @global type $wpdb
     */
    function entries_import() {
        $wform = $_REQUEST['form'];
        $gform = $_REQUEST['gform'];
        $entry_index = $_REQUEST['entry_index'];

        $this->init();


        $field_map = maybe_unserialize(get_site_option('rt_wufoo_' . $wform . '_field_map'));

        $f = new RGFormsModel();
        $c = new GFCommon();
        $gform_meta = RGFormsModel::get_form_meta($gform);

        try {
            $entries = $this->wufoo->getEntries($wform, 'forms', 'pageStart=' . $entry_index . '&pageSize=' . RT_WUFOO_IMPORT_PAGE_SIZE);
        } catch (Exception $rt_importer_e) {
            $this->error($rt_importer_e);
        }


        $this->op = array();

        foreach ($entries as $index => $entry) {
            $lead_exists_id = $this->is_imported($entry->EntryId);
            print_r($lead_exists_id);
            if (!$lead_exists_id) {

                foreach ($field_map as $g_id => $w_id) {
                    if (isset($w_id) && $w_id != '') {
                        $this->op[$g_id] = '';
                        $field_meta = RGFormsModel::get_field($gform_meta, $g_id);
                        if ($field_meta['type'] == 'fileupload') {
                            $this->op[$g_id] = $this->import_file_upload($entry, $w_id);
                        } else {
                            $this->import_regular_field($wform, $entry, $g_id, $w_id, $field_meta);
                        }
                    }
                }

                $currency = $c->get_currency();
                $ip = $f->get_ip();
                $page = $f->get_current_page_url();
                $lead_table = $f->get_lead_table_name();


                $lead_id = $this->insert_lead($entry, $lead_table, $ip, $currency, $page, $wform, $gform);
            }

            if ($lead_id) {
                foreach ($this->op as $inputid => $value) {
                    $this->insert_fields($lead_id, $gform, $inputid, $value);
                }

//Insert comments as notes for the corresponding user map
                $comments = $this->get_comments_by_entry($wform, $entry->EntryId);
                if (isset($comments) && !empty($comments)) {
                    foreach ($comments as $comment) {
                        $this->move_comments_for_entry($comment, $f->get_lead_notes_table_name(), $lead_id, $wform);
                    }
                }
            } else {
                $lead_id = $lead_exists_id;
            }

            gform_update_meta($lead_id, 'rt_wufoo_entry_id', $entry->EntryId);
        }
        //update_site_option('rt_wufoo_' . $wform . '_entry_complete_count','0');
        echo count($entries) + $entry_index;
        die();
    }

    /**
     *
     * @param type $entry
     * @param type $w_id
     * @return type
     */
    function import_file_upload($entry, $w_id) {
        $value = 'No image set';

        if (property_exists($entry, $w_id)) {
            $image_url = $entry->$w_id;
            if (isset($image_url) && $image_url != '') {
                $value = $this->import_set_image($image_url);
            }
        } else {
            $image_url = $w_id;
            if (isset($image_url) && $image_url != '') {
                $value = $this->upload_image($image_url);
            }
        }

        return $value;
    }

    /**
     *
     * @param type $image_url
     * @return type
     */
    function import_set_image($image_url) {
        $image_url = explode('(', $image_url);
        $image_url = explode(')', $image_url[1]);
        return $this->upload_image($image_url);
    }

    /**
     *
     * @param type $image_url
     * @return type
     */
    function upload_image($image_url) {
        $image_data = wp_remote_get($image_url[0]);
        $value = 'Image not found';
        if (!is_wp_error($image_data) && $image_data['body'] != '') {
            $upload = wp_upload_bits(basename($image_url[0]), 0, $image_data['body']);
            $value = $upload['url'];
        }
        return $value;
    }

    /**
     *
     * @param type $entry
     * @param type $w_id
     * @param type $field_meta
     * @return type
     */
    function import_regular_field($wform, $entry, $g_id, $w_id, $field_meta) {
        if (property_exists($entry, $w_id)) {

            $fields = maybe_unserialize(get_site_option('rt_wufoo_' . $wform . '_fields'));
            $this->op[$g_id] = $w_id;

            if (property_exists($fields->Fields[$w_id], 'SubFields') && is_array($fields->Fields[$w_id]->SubFields)) {
                if (is_array($field_meta['inputs'])) {
                    $field_keys = array_keys($fields->Fields[$w_id]->SubFields);
                    $this->import_multipart_field($field_keys, $w_id, $field_meta, $entry);
                } else {
                    $wuf_value = '';
                    foreach ($fields->Fields[$w_id]->SubFields as $key => $value) {
                        $wuf_value .= $entry->$key;
                        $wuf_value .= ' ';
                    }
                    $this->op[$g_id] = $wuf_value;
                }
            } else {
                if (is_array($field_meta['inputs'])) {
                    $field_keys = explode(' ', $entry->$w_id);
                    $this->import_single_to_multipart_field($field_keys, $w_id, $field_meta, $entry);
                } else {
                    $this->op[$g_id] = $entry->$w_id;
                }
            }
        }
    }

    function import_single_to_multipart_field($field_keys, $w_id, $field_meta, $entry) {
        $diff = count($field_meta['inputs']) - count($field_keys);

        if ($diff > 0) {
            for ($i = 0; $i < $diff; $i++) {
                $field_keys[] = ' ';
            }
        }

        foreach ($field_keys as $i => $field_key) {
            $this->op[strval($field_meta['inputs'][$i]['id'])] = $field_key;
        }
    }

    /**
     *
     * @param type $field_keys
     * @param type $w_id
     * @return type
     */
    function import_multipart_field($field_keys, $w_id, $field_meta, $entry) {

        if (count($field_keys) == count($field_meta['inputs'])) {
            foreach ($field_keys as $i => $field_key) {
                $this->op[strval($field_meta['inputs'][$i]['id'])] = $entry->$field_key;
            }
        } else if (count($field_keys) > count($field_meta['inputs'])) {
            $wuf_last = array_pop($field_keys);
            $gfield_last = array_pop($field_meta['inputs']);
            $this->op[strval($gfield_last['id'])] = $entry->$wuf_last;
            $wuf_value = '';
            foreach ($fields->Fields[$w_id]->SubFields as $key => $value) {
                $wuf_value .= $entry->$key;
                $wuf_value .= ' ';
            }
            $this->op[$field_meta['inputs']][0] = $wuf_value;
        }
    }

    /**
     *
     * @param type $wufoo_user
     * @return type
     */
    function get_user_for_entry($wufoo_user, $wform) {
        $user_id = 1;
        $user_map = maybe_unserialize(get_site_option('rt_wufoo_' . $wform . '_user_map'));
        if (isset($user_map) && !empty($user_map)) {
            if (array_key_exists($wufoo_user, $user_map)) {
                $user = $user_map[$wufoo_user];
                $user = get_user_by('id', $user);
                if (!is_wp_error($user)) {
                    $user_id = $user;
                } else {
                    $user_id = 1;
                }
            }
        }
        return $user_id;
    }

    /**
     *
     * @param type $entry
     * @param type $lead_table
     * @param type $ip
     * @param type $currency
     * @param type $page
     * @return type
     */
    function insert_lead($entry, $lead_table, $ip, $currency, $page, $wform, $gform) {
        global $wpdb;
        $user_id = $this->get_user_for_entry($entry->CreatedBy, $wform);

        $date = isset($entry->DateCreated) && $entry->DateCreated != '' ? $entry->DateCreated : date('Y-m-d H:i:s');
        $current_user_id = get_current_user_id();
        $user_id = $current_user_id ? $current_user_id : 'NULL';

        $user_agent = strlen($_SERVER["HTTP_USER_AGENT"]) > 250 ? substr($_SERVER["HTTP_USER_AGENT"], 0, 250) : $_SERVER["HTTP_USER_AGENT"];

        $wpdb->query($wpdb->prepare("INSERT INTO $lead_table(form_id, ip, source_url, date_created, user_agent, currency, created_by) VALUES(%d, %s, %s, %s, %s, %s, {$user_id})", $gform, $ip, $page, $date, $user_agent, $currency));

        return $wpdb->insert_id;
    }

    /**
     *
     * @param type $lead_id
     * @param type $gform
     * @param type $inputid
     * @param type $value
     */
    function insert_fields($lead_id, $gform, $inputid, $value) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->insert($prefix . 'rg_lead_detail', array('lead_id' => $lead_id, 'form_id' => $gform, 'field_number' => $inputid, 'value' => $value), array('%d', '%d', '%f', '%s'));
        $lead_detail_id = $wpdb->insert_id;
        if (strlen($value) > 200) {
            $this->insert_lead_detail_long($lead_detail_id, $value);
        }
    }

    /**
     *
     * @global type $wpdb
     * @param type $lead_detail_id
     * @param type $value
     */
    function insert_lead_detail_long($lead_detail_id, $value) {
        global $wpdb;
        $f = new RGFormsModel();
        $lead_detail_long = $f->get_lead_details_long_table_name();
        $query = $wpdb->prepare("INSERT INTO $lead_detail_long(lead_detail_id, value) VALUES(%d, %s)", $lead_detail_id, $value);
        $wpdb->query($query);
    }

    function imported_entries() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $leads = $prefix . "rg_lead";
        $meta = $prefix . "rg_lead_meta";
        $count = $wpdb->get_var("SELECT COUNT(l.id) FROM $leads l
LEFT JOIN $meta m ON l.id = m.lead_id WHERE meta_key='rt_wufoo_entry_id'");
        return $count;
    }

    function is_imported($entry_id) {
        $is_it = false;
        global $wpdb;
        $prefix = $wpdb->prefix;
        $meta = $prefix . "rg_lead_meta";
        $query = $wpdb->get_results("SELECT * FROM {$meta} WHERE meta_key='rt_wufoo_entry_id' AND meta_value='{$entry_id}'");
        if (is_array($query) && count($query) > 0) {
            $is_it = $query[0]->lead_id;
        }
        return $is_it;
    }

    /**
     *
     * @param type $comment
     * @param type $table_name
     * @param type $lead_id
     */
    function move_comments_for_entry($comment, $table_name, $lead_id, $wform) {
        global $wpdb;
        $user_map = maybe_unserialize(get_site_option('rt_wufoo_' . $wform . '_user_map'));
        if (array_key_exists($comment->commentedby, $user_map)) {
            $user = get_user_by('id', $user_map[$comment->commentedby]);
            $sql = $wpdb->prepare(
                    "INSERT INTO $table_name(lead_id, user_id, user_name, value, date_created) values(%d, %d, %s, %s, %s)", $lead_id, $user_map[$comment->commentedby], $user->display_name, $comment->text, $comment->datecreated
            );
            $wpdb->query($sql);
        }
    }

    /**
     *
     * @global type $wpdb
     * @param type $form
     * @param type $entry_id
     * @return type
     */
    function get_comments_by_entry($form, $entry_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "_rt_w2g_comments";
        $sql = "SELECT * FROM {$table_name} WHERE entryid='{$entry_id}' AND form='{$form}'";
        $comments = $wpdb->get_results($sql);
        return $comments;
    }

    /**
     * Function to enqueue the necessary js and css
     */
    function enqueue() {
        wp_enqueue_script('rt-wufoo-script', plugins_url('/js/mapper.js', __FILE__), array('jquery'));
        wp_enqueue_style('rt-wufoo-style', plugins_url('/css/mapper.css', __FILE__));
        $this->set_options();
        $obj_array = array(
            'subdomain' => $this->subdomain,
            'api_key' => $this->api_key,
            'form' => '',
            'count' => RT_WUFOO_IMPORT_PAGE_SIZE
        );
        wp_localize_script('rt-wufoo-script', 'rt_wufoo_obj', $obj_array);
    }

    /**
     *
     * @param array $instance An array with the name to be used for id and the progress percentage
     * @return string The progress ui html
     */
    function progress_ui($instance = array()) {
        $progress = new rtProgress();
        $instance = wp_parse_args($instance, array('name' => 'general', 'progress' => 0));
        $ui = '<div class="rt-wufoo-progress" id="' . $instance['name'] . '">'
                . $progress->progress_ui($instance['progress'], false)
                . '</div>';
        return $ui;
    }

    /**
     * Error handler
     *
     * @param type $err
     */
    function error($err) {
        print_r($err->getMessage());
    }

    /**
     *
     * @return boolean
     */
    function get_gravity_forms() {
        $active = RGForms::get("active") == "" ? null : RGForms::get("active");
        $forms = RGFormsModel::get_forms($active, "title");

        if (isset($forms) && !empty($forms)) {
            foreach ($forms as $form) {
                $return[$form->id] = $form->title;
            }
            return $return;
        } else
            return false;
    }

}