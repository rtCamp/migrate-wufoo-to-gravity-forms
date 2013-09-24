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
                'wp_ajax_rt_wufoo_comment_count', array($this, 'comment_count_ajax')
        );
        add_action(
                'wp_ajax_rt_wufoo_comment_import', array($this, 'comment_import')
        );

        add_action(
                'wp_ajax_rt_wufoo_comment_progress_ui', array($this, 'comment_progress_ui')
        );
        add_action(
                'wp_ajax_rt_wufoo_map_users', array($this, 'map_users')
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

    function comment_count_ajax() {
        if (isset($_GET['action']) && $_GET['action'] == 'rt_wufoo_comment_count') {
        echo $this->comment_count();
        die();
        }
    }
    
    function comment_count(){
        $wuf_form = $_GET['form'];
        if (empty($wuf_form))
            echo '-1';

        $this->set_options();
        $wufoo = new rtWufooAPI($this->api_key, $this->subdomain);


        $total_comment_cont_obj = $wufoo->getCommentCount($wuf_form);

        return $total_comment_cont_obj->Count;
    }

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

    function imported_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . "_rt_w2g_comments";
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name};");
        return $count;
    }

    function comment_import() {
        $form = $_POST['rt_wufoo_form'];
        $pageno = $_POST['rt_wufoo_comment_index'];

        $this->set_options();
        $wufoo = new rtWufooAPI($this->api_key, $this->subdomain);

        $comments = $wufoo->getPagedComments($form, 25, $pageno * 25, null);
        $this->comments_db_install();
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
        echo count($comments) + ($pageno * 25);
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
        if (empty($this->subdomain) && empty($this->api_key)) {
            $this->step = 'get_api';
        } else {
            $this->step = 'get_forms';
        }

        echo '<div id = "rt_wufoo_wizard" >';

        echo '<div class="steps" id="rt-wufoo-step-api">';
        $this->api_form_ui();
        echo '</div>';

        echo '<div class="steps" id="rt-wufoo-step-form">';
        $this->form_select_ui();
        echo '</div>';

        echo '<div class="steps" id="rt-wufoo-step-users">';
        //$this->form_select_ui();
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
            $this->progress = new rtProgress();
            $this->set_options();
            if (!$this->subdomain || !$this->api_key)
                return;
            $this->wufoo = new rtWufooAPI($this->api_key, $this->subdomain);
            try {
                $this->wforms = $this->wufoo->getForms();
            } catch (Exception $rt_importer_e) {
                $this->error($rt_importer_e);
            }

            if (!isset($this->wforms) || empty($this->wforms)) {
                echo '<div class="error">'
                . 'Please <a href="https://' . $this->subdomain . '.wufoo.com/build/">'
                . 'create some forms on Wufoo!'
                . '</a>'
                . '</div>';
                return;
            }
        }

        /**
         *
         */
        function api_form_ui() {
            ?>
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
                               />
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
                               value="Get Forms"
                               class="button"
                               />
                    </td>
                </tr>
            </table>
        </form>
        <?php
    }

    /**
     *
     * @return type
     */
    function input_form_selector() {
        $form_select = '<select name="rt_wufoo_form_selector" id="rt_wufoo_form_selector">'
                . '<option value="">Choose a form</option>';
        foreach ($this->wforms as $hash => $wuf_form) {
            $form_select .= '<option value="' . $hash . '">' . $wuf_form->Name . '</option>';
        }
        $form_select .= '</select>';

        return $form_select;
    }

    /**
     *
     */
    function form_select_ui() {
        ?>
        <h3>Map a Wufoo Form</h3>
        <table class="form-table" id="map_wuf_forms_list_table">
            <tr>
                <th scope="row"><label for="map_wuf_forms_list">Select a Form:</label></th>
                <td>
        <?php echo $this->input_form_selector(); ?>
                </td>
            </tr>
            <tr>
                <td>
                    <form method="post" id="rt_wufoo_comment_import">
                        <input type="hidden" name="rt_wufoo_comment_index" class="rt_wufoo_comment_index" value="0" />
                        <input type="hidden" name="rt_wufoo_form" class="rt_wufoo_form" value="" />
                        <input disabled="disabled" type="submit" class="rt_wufoo_comment_btn button" name="rt_wufoo_import_comments" id="rt_wufoo_import_comments" value="Next: Import Comments" />
                    </form>
                    <form method="post" id="rt_wufoo_map_users">
                        <input type="hidden" name="rt_wufoo_comment_index" class="rt_wufoo_comment_index" value="0" />
                        <input type="hidden" name="rt_wufoo_form" class="rt_wufoo_form" value="" />
                        <input disabled="disabled" type="submit" class="rt_wufoo_comment_btn button" name="rt_wufoo_skip_comments" id="rt_wufoo_skip_comments" value="Or: Skip Importing" />
                    </form>
                </td>
            </tr>
        </table>
        <?php
    }

    function comment_progress_ui() {
        $progress = (int) $this->imported_count() / (int) $this->comment_count() * 100;
        $instance = array(
            'name' => 'comment-import',
            'progress' => $progress
        );
        echo '<span id="rt_wufoo_imported_comments" class="rt_wufoo_completed">'.$this->imported_count().'</span>';
        echo '<span class="rt_wufoo_progress_count_sep">/</span>';
        echo '<span id="rt_wufoo_total_comments" class="rt_wufoo_total">'.$this->comment_count().'</span>';
        echo ' comments';
        echo $this->progress_ui($instance);
        die();
    }

    function map_users() {
        global $blog_id;
        $grav_users = get_users(
                array('blog_id' => $blog_id)
        );
        $wuf_form = $_POST['rt_wufoo_form'];
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
            $this->set_options();
            $wufoo = new rtWufooAPI($this->api_key,$this->subdomain);
        $wuf_entry_count = $wufoo->getEntryCount($wuf_form);
        $wuf_form_fields = $wufoo->getFields($wuf_form);

        $wuf_page_size = 25;
        $wuf_times = ceil(floatval($wuf_entry_count) / 25);
        $wuf_form_entries = array();
        for ($i = 0; $i < $wuf_times; $i++) {
            $wuf_form_entries = array_merge(
                    $wuf_form_entries, $wufoo->getEntries($wuf_form, 'forms', 'pageStart=' . ($i * $wuf_page_size) . '&pageSize=' . $wuf_page_size));
        }
        $gforms = $this->get_gravity_forms();
        if (isset($gforms) && !empty($gforms)) {
            $form_select = '<select name="map_wuf_gforms_list" id="map_wuf_gforms_list">';
            $form_select .= '<option value="">Please select a form</option>';
            foreach ($gforms as $id => $form) {
                $form_select .= '<option value="' . $id . '">' . $form . '</option>';
            }
        } else {
            $form_select = '<strong>Please create some forms!</strong>';
        }

        //Use this to get the entries and fields
        update_option($wuf_form . '_entries', maybe_serialize($wuf_form_entries));
        update_option($wuf_form . '_fields', maybe_serialize($wuf_form_fields));

        //Return the markup
        foreach ($grav_users as $user) {
            $user_options .= '<option value="' . $user->ID . '">' . $user->user_login . '</option>';
        }
        $return = '<form action="" method="post" id="map_wuf_comment_mapping_form">';
        if (isset($wuf_commentators) && !empty($wuf_commentators)) {
            $return .= '<h3>Map comments and notes:</h3>';
            $return .= '<table class="form-table" id="map_wuf_comment_mapping_table">';
            foreach ($wuf_commentators as $wuf_c_i => $wuf_commentator) {
                $return .= '<tr>';
                $return .= '<th scope="row">' . $wuf_commentator->commentedby . '</th>';
                $return .= '<td><select name="' . $wuf_commentator->commentedby . '">' . $user_options . '</select></td>';
                $return .= '</tr>';
            }
            $return .= '</table>';
        }

        $return .= '<h3>Select a Gravity Form:</h3>';
        $return .= '<table class="form-table" id="map_wuf_gforms_list_table">';
        $return .= '<tr><th scope="row">Select a Gravity Form</th><td>' . $form_select . '</td></tr>';
        $return .= '<tr><td><input type="submit" name="map_wuf_get_fields" id="map_wuf_get_fields" value="Get Fields" class="button"/>
                <span class="map_loading"></span></td></tr>';
        $return .= '</table>';
        $return .= '<input type="hidden" name="map_wuf_form_hash" value="' . $wuf_form . '" id="map_wuf_form_hash"/>';
        $return .= '<input type="hidden" name="map_wuf_entry_count" id="map_wuf_entry_count" value="' . $wuf_times . '"/>';
        $return .= '<input type="hidden" name="map_wuf_entry_total" id="map_wuf_entry_total" value="' . count($wuf_form_entries) . '"/>';
        $return .= '</form>';

        echo $return;
        die();
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
            'comment_count' => 0,
            'comment_index' => 0
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
        echo '<pre>';
        print_r($err);
        echo '</pre>';
    }

    function get_gravity_forms() {
        $active = RGForms::get("active") == "" ? null : RGForms::get("active");
        $forms = RGFormsModel::get_forms($active, "title");

        if (isset($forms) && !empty($forms)) {
            foreach ($forms as $form) {
                $return[$form->id] = $form->title;
            }
            return $return;
        }
        else
            return false;
    }

}
?>
