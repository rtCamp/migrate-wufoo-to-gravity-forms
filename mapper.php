<?php
/*
  Plugin Name: Wufoo to Gravity Importer
  Plugin URI: http://rtcamp.com/
  Description: Wuffo Form to Garvity Forms migrator!
  Version: 0.1
  Author: rtCamp
  Author URI: http://rtcamp.com
 */

/*
 * Parse CSV library
 */
require_once('lib/parsecsv.lib.php');
require_once('lib/simplexlsx.php');
require_once('lib/excel_reader2.php');
require_once('lib/WufooApiWrapper.php');
/*
 * Admin pages
 */
add_action('admin_menu', 'map_admin_pages', 11);

function map_admin_pages() {
    $hook = add_submenu_page('gf_edit_forms', 'Raw to GForms Mapper', 'Mapper', 'administrator', 'mapper', 'map_admin_page');
    add_action('admin_print_scripts-' . $hook, 'map_assets_enqueue');
    $wuf_hook = add_submenu_page('gf_edit_forms', 'Wufoo Entries', 'Wufoo', 'administrator', 'mapper_wufoo', 'map_wufoo_admin_page');
    add_action('admin_print_scripts-' . $wuf_hook, 'map_assets_enqueue');
}

function map_admin_page() {
    ?>
    <div class="wrap">
        <?php
        $forms = map_get_forms();
        if (isset($forms) && !empty($forms)) {
            $form_select = '<select name="map_forms_list" id="map_forms_list">';
            $form_select .= '<option value="">Please select a form</option>';
            foreach ($forms as $id => $form) {
                $form_select .= '<option value="' . $id . '">' . $form . '</option>';
            }
        } else {
            $form_select = '<strong>Please create some forms!</strong>';
        }
        ?>
        <h2>Mapper Settings Page</h2>
        <form action="" method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="map_upload">Upload a data file:</label></th>
                    <td>
                        <input type="file" name="map_upload" id="map_upload" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="map_forms_list">Select a Form:</label></th>
                    <td>
                        <?php echo $form_select; ?>
                    </td>
                </tr>
                <tr>
                    <td><input type="submit" name="map_submit" value="Upload" class="button"/></td>
                </tr>
            </table>
        </form>

        <?php
        /*
         * File Handler Code
         */
        if (isset($_POST['map_submit']) && $_POST['map_submit'] != '') {
            $form_id = isset($_POST['map_forms_list']) && $_POST['map_forms_list'] != '' ? $_POST['map_forms_list'] : '';
            $form_data = RGFormsModel::get_form_meta($form_id);
            if (isset($_FILES['map_upload']) && $_FILES['map_upload']['error'] == 0) {
                if ($_FILES['map_upload']['type'] != 'text/csv') {
                    echo "<div class='error'>Please upload a CSV file only!</div>";
                }

                //Upload the file to 'Uploads' folder
                $file = $_FILES['map_upload'];
                $upload = wp_handle_upload($file, array('test_form' => false));
//                    if($_FILES['map_upload']['type'] == 'text/csv'){
//                        //Parse CSV
//                        $csv = new parseCSV();
//                        $csv->auto( $upload['file'] );
//                    } else if($_FILES['map_upload']['type'] == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
//                        $xlsx = new SimpleXLSX( $upload['file'] );
//                        echo '<pre>';
//                        print_r($xlsx);
//                        echo '</pre>';
//                        exit;
//                    } else if($_FILES['map_upload']['type'] == 'application/vnd.ms-excel') {
//                        $xls = new Spreadsheet_Excel_Reader( $_FILES['map_upload']['tmp_name'], false );
//                        $rows = $xls->rowcount($sheet_index=0);
//                        $cols = $xls->colcount($sheet_index=0);
//                        $data = array();
//
//                        for($i = 0; $i <= $rows; $i++) {
//                            for($j = 0; $j <= $cols; $j++) {
//                                $data[$i] = array( $xls->val($i, $j));
//                            }
//                        }
//                        echo '<pre>';
//                        print_r($rows);
//                        print_r($cols);
//                        echo '</pre>';
//                        exit;
//                    } else {
//
//                    }
//                    exit;
                //Parse CSV
                $csv = new parseCSV();
                $csv->auto($upload['file']);
                ?>
                <div id="map_message" class="updated map_message">
                    File uploaded: <strong><?php echo $_FILES['map_upload']['name']; ?></strong>
                    Total Rows: <strong><?php echo count($csv->data); ?></strong>
                </div>
                <input type="hidden" name="map_row_count" id="map_row_count" value="<?php echo count($csv->data); ?>" />
                <input type="hidden" name="map_filename" id="map_filename" value="<?php echo $upload['file']; ?>" />
                <input type="hidden" name="map_form_id" id="map_form_id" value="<?php echo $form_id; ?>" />

                <form method="post" action="" id="map_mapping_form" name="map_mapping_form">
                    <table class="wp-list-table widefat fixed" id="map_mapping_table">
                        <tr>
                            <th scope="row">Column Name</th>
                            <th scope="row">Field Name</th>
                        </tr>
                        <?php
                        foreach ($form_data['fields'] as &$field) {
                            if ($field['type'] != 'section' && $field['type'] != 'html') {
                                ?>
                                <tr>
                                    <td><?php echo ucfirst($field['label']); ?></td>
                                    <td>
                                        <?php
                                        $form_fields = '<select name="field-' . $field['id'] . '" class="map_form_fields">';
                                        $form_fields .= '<option value="">Choose a field or Skip it</option>';
                                        foreach ($csv->titles as $value) {
                                            $form_fields .= '<option value="' . $value . '">' . ucfirst($value) . '</option>';
                                        }
                                        $form_fields .= '<option value="other">Other Field</option>';
                                        $form_fields .= '</select>';
                                        echo $form_fields;
                                        ?>
                                    </td>
                                </tr>
                            <?php }
                        }
                        ?>
                        <?php
                        $form_extra_fields = '<option value="">Choose a field</option>';
                        foreach ($csv->titles as $value) {
                            $form_extra_fields .= '<option value="' . $value . '">' . ucfirst($value) . '</option>';
                        }
                        $form_extra_fields .= '<option value="other">Other Field</option>';

                        $form_IP_select = '<select name="map_IP" class="map_form_fields">' . $form_extra_fields . '</select>';
                        $form_date_select = '<select name="map_date" class="map_form_fields">' . $form_extra_fields . '</select>';
                        $form_http_select = '<select name="map_http" class="map_form_fields">' . $form_extra_fields . '</select>';
                        ?>
                        <tr>
                            <td>Client IP</td><td><?php echo $form_IP_select; ?></td>
                        </tr>
                        <tr>
                            <td>Date
                                <div id="map_date_format_selector"style="padding: 0pt 10px; display: inline;">
                                    <input type="radio" value="mdy" name="map_date_format" id="map_date_format_mdy" checked/><label for="map_date_format_mdy">MM-DD-YYYY</label>
                                    <input type="radio" value="dmy" name="map_date_format" id="map_date_format_dmy"/><label for="map_date_format_dmy">DD-MM-YYYY</label>
                                </div>
                            </td>
                            <td><?php echo $form_date_select; ?></td>
                        </tr>
                        <tr>
                            <td>HTTP User Agent</td><td><?php echo $form_http_select; ?></td>
                        </tr>
                    </table>
                    <input type="submit" name="map_mapping_import" id="map_mapping_import" value="Import" class="button"/>
                    <span class="map_loading"></span>
                    <div class="clear"></div>
                    <table id="map_mapping_progress" class="widefat">

                    </table>
                </form>
        <?php
        }
    }
    ?>
    </div>
    <?php
}

function map_wufoo_admin_page() {
    $wuf_sub = get_site_option('wufootogravity_subdomain');
    $wuf_key = get_site_option('wufootogravity_apikey');
    ?>
    <div class="wrap">
        <h2>Import entries to Gravity Forms</h2>

        <!--Tabs-->
        <!--        <ul class="subsubsub">
                    <li><a class="current" href="#">Import from Wufoo</a> | </li>
                    <li><a class="" href="#">Import from CSV</a></li>
                </ul>-->
        <!--Tabs end-->

        <br style="clear:both; " />
        <br />
        <p class="textleft">Enter your Wufoo details below and select the forms for mapping the entries</p>

        <div class="hr-divider"></div>
        <form action="" method="post" id="map_wuf_credentials">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="map_wuf_sub">Enter the Wufoo subdomain:</label></th>
                    <td>
                        <input type="text" name="map_wuf_sub" id="map_wuf_sub" value="<?php echo isset($_POST['map_wuf_sub']) && $_POST['map_wuf_sub'] != '' ? $_POST['map_wuf_sub'] : ''; ?>"/>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="map_wuf_key">Enter the Wufoo API key:</label></th>
                    <td>
                        <input type="text" name="map_wuf_key" id="map_wuf_key" value="<?php echo isset($_POST['map_wuf_key']) && $_POST['map_wuf_key'] != '' ? $_POST['map_wuf_key'] : ''; ?>"/>
                    </td>
                </tr>
                <tr>
                    <td><input type="submit" name="map_wuf_submit" value="Get Forms" class="button"/></td>
                </tr>
            </table>
        </form>

        <?php
        //Wufoo handler code
        if (isset($_POST['map_wuf_submit'])) {
            if ((!isset($_POST['map_wuf_sub']) || $_POST['map_wuf_sub'] == '') && (!isset($_POST['map_wuf_key']) || $_POST['map_wuf_key'] == '')) {
                echo '<div class="error">Please enter the correct Wufoo subdomain/API key</div>';
                return;
            }
            $wuf_sub = $_POST['map_wuf_sub'];
            $wuf_api_key = $_POST['map_wuf_key'];
            $wuf = new WufooApiWrapper($wuf_api_key, $wuf_sub);
            try {
                $wuf_forms = $wuf->getForms();
            } catch (Exception $rt_importer_e) {
                rt_map_err_handling($rt_importer_e);
            }
            if (!isset($wuf_forms) || empty($wuf_forms)) {
                echo '<div class="error">Please <a href="https://' . $wuf_sub . '.wufoo.com/build/">create</a> some forms in <a href="https://' . $wuf_sub . '.wufoo.com/build/">Wufoo!</a></div>';
                return;
            }

            $form_select = '<select name="map_wuf_forms_list" id="map_wuf_forms_list">';
            $form_select .= '<option value="">Choose a form</option>';
            foreach ($wuf_forms as $hash => $wuf_form) {
                $form_select .= '<option value="' . $hash . '">' . $wuf_form->Name . '</option>';
            }
            $form_select .= '</select>';
            ?>
            <h3>Select a Wufoo Form</h3>
            <form method="post" id="map_wuf_forms_list_form">
                <table class="form-table" id="map_wuf_forms_list_table">
                    <tr>
                        <th scope="row"><label for="map_wuf_forms_list">Select a Form:</label></th>
                        <td>
        <?php echo $form_select; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="submit" name="map_wuf_import_comments" id="map_wuf_import_comments" value="Next: Import Comments" class="button"/>
                            <input type="button" name="map_wuf_skip_comments" id="map_wuf_skip_comments" value="Or: Skip Importing" class="button"/>
                            <span class="map_loading"></span>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="map_wuf_sub" value="<?php echo $wuf_sub; ?>" />
                <input type="hidden" name="map_wuf_key" value="<?php echo $wuf_api_key; ?>" />
            </form>
            <input type="hidden" id="map_wuf_comment_count" name="map_wuf_comment_count" value="" />
    <?php } ?>
        <table id="map_mapping_progress_bar" class="widefat">
            <tr>
                <td>
                    <div id="progressbar">
                        <div></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td id="map_progress_msgs">

                </td>
            </tr>
        </table>
    </div>
<?php
}

/*
 * Enqueue assets
 */

function map_assets_enqueue() {
    wp_enqueue_script('mapper-script', plugins_url('/js/mapper.js', __FILE__), array('jquery'));
    wp_enqueue_style('mapper-style', plugins_url('/css/mapper.css', __FILE__));
}

/*
 * Get all active forms
 */

function map_get_forms() {
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

/*
 * Ajax for rows import
 */

add_action('wp_ajax_map_import', 'map_import_callback');
add_action('wp_ajax_map_import_no_priv', 'map_import_callback');

function map_import_callback() {
    if (isset($_POST['action']) && $_POST['action'] == 'map_import') {
        global $current_user;
        global $wpdb;
        $prefix = $wpdb->prefix;

        //Create GForm entry
        $f = new RGFormsModel();
        $c = new GFCommon();

        $data = $_POST['map_data'];

        $form_id = $_POST['map_form_id'];
        $row_index = $_POST['map_row_index'];

        //Parse the CSV again
        $csv = new parseCSV();
        $csv->auto($_POST['map_filename']);

        //Get the form data
        $form_meta = $form_data = RGFormsModel::get_form_meta($form_id);
        foreach ($data as $single) {
            if ($single['name'] == 'map_IP' || $single['name'] == 'map_date' || $single['name'] == 'map_http' || $single['name'] == 'map_date_format') {
                $val = $single['value'];
                if (isset($val) && $val != '') {
                    if ($single['name'] == 'map_IP') {
                        if (array_key_exists($val, $csv->data[$row_index])) {
                            $ip = $csv->data[$row_index][$val];
                        } else {
                            $ip = $val;
                        }
                    } else if ($single['name'] == 'map_date') {
                        if (array_key_exists($val, $csv->data[$row_index])) {
                            $date_created = strtotime($csv->data[$row_index][$val]);
                        } else {
                            $date_created = strtotime($val);
                        }
                    } else if ($single['name'] == 'map_http') {
                        if (array_key_exists($val, $csv->data[$row_index])) {
                            $user_agent = $csv->data[$row_index][$val];
                        } else {
                            $user_agent = $val;
                        }
                    } else if ($single['name'] == 'map_date_format') {
                        $date_format = $single['value'];
                    }
                } else {
                    $ip = $f->get_ip();
                    $user_agent = strlen($_SERVER["HTTP_USER_AGENT"]) > 250 ? substr($_SERVER["HTTP_USER_AGENT"], 0, 250) : $_SERVER["HTTP_USER_AGENT"];
                    //$date_created = date("Y-m-d H:i:s");
                    $date_created = 'utc_timestamp()';
                }
            } else {
                $field_id = explode('-', $single['name']);
                $field = RGFormsModel::get_field($form_meta, $field_id[1]);
                $val = $single['value'];
                if (isset($val) && $val != '') {
                    if ($field['type'] == 'fileupload') {
                        if (array_key_exists($val, $csv->data[$row_index])) {
                            if (isset($csv->data[$row_index][$val]) && $csv->data[$row_index][$val] != '') {
                                $image_url = explode('(', $csv->data[$row_index][$val]);
                                $image_url = explode(')', $image_url[1]);
                                echo $image_url[0];
                                $image_data = wp_remote_get($image_url[0]);
                                if (!is_wp_error($image_data) && $image_data['body'] != '') {
                                    $upload = wp_upload_bits(basename($csv->data[$row_index][$val]), 0, $image_data['body']);
                                    $op[$field_id[1]] = $upload['url'];
                                } else {
                                    $op[$field_id[1]] = 'Image not found';
                                }
                            } else {
                                $op[$field_id[1]] = 'No image set';
                            }
                        } else {
                            $image_data = wp_remote_get($val);
                            if (!is_wp_error($image_data) && $image_data['body'] != '') {
                                $upload = wp_upload_bits(basename($val), 0, $image_data['body']);
                                $op[$field_id[1]] = $upload['url'];
                            } else {
                                $op[$field_id[1]] = 'Image not found';
                            }
                        }
                    } else {
                        if (array_key_exists($val, $csv->data[$row_index])) {
                            if (isset($csv->data[$row_index][$single['value']]) && $csv->data[$row_index][$single['value']] != '')
                                $op[$field_id[1]] = $csv->data[$row_index][$single['value']];
                            else
                                $op[$field_id[1]] = '';
                        } else {
                            $op[$field_id[1]] = $val;
                        }
                    }
                } else {
                    $op[$field_id[1]] = '';
                }
            }
        }

        $user_id = $current_user && $current_user->ID ? $current_user->ID : 'NULL';
        $lead_table = $f->get_lead_table_name();
        //$user_agent = strlen($_SERVER["HTTP_USER_AGENT"]) > 250 ? substr($_SERVER["HTTP_USER_AGENT"], 0, 250) : $_SERVER["HTTP_USER_AGENT"];
        $currency = $c->get_currency();
        //$ip = $f->get_ip();
        $page = $f->get_current_page_url();

        //Format date according to Datetime format
        if ($date_format == 'mdy') {
            $date_created = date('Y-m-d H:i:s', $date_created);
        } else if ($date_format == 'dmy') {
            $timezone = "Asia/Calcutta";
            date_default_timezone_set($timezone);
            $date_created = date('Y-m-d H:i:s', $date_created);
        }

        $wpdb->query($wpdb->prepare("INSERT INTO $lead_table(form_id, ip, source_url, date_created, user_agent, currency, created_by) VALUES(%d, %s, %s, %s, %s, %s, {$user_id})", $form_id, $ip, $page, $date_created, $user_agent, $currency));
        $lead_id = $wpdb->insert_id;
        if (!$lead_id) {
            echo 0;
        } else {
            foreach ($op as $inputid => $value) {
                $done = $wpdb->insert($prefix . 'rg_lead_detail', array('lead_id' => $lead_id, 'form_id' => $form_id, 'field_number' => $inputid, 'value' => $value), array('%d', '%d', '%d', '%s'));
            }
            echo $row_index;
        }
    }
    die();
}

/*
 * Callback for Wufoo Form Select and comments data from Wufoo
 */
add_action('wp_ajax_map_wuf_form_select', 'map_wuf_form_select_callback');

function map_wuf_form_select_callback() {
    if (isset($_POST['action']) && $_POST['action'] == 'map_wuf_form_select') {
        $data = $_POST['map_wuf_form_data'];
        foreach ($data as $single) {
            $values[$single['name']] = $single['value'];
        }

        $wuf = new WufooApiWrapper($values['map_wuf_key'], $values['map_wuf_sub']);
        $wuf_form = $values['map_wuf_forms_list'];
        $total_comment_cont_obj = $wuf->rtgetCommentCount($wuf_form);
        echo $total_comment_cont_obj->Count;
        die();
    }
}

add_action('wp_ajax_map_wuf_form_comment_import', 'map_wuf_form_comment_import_callback');

function map_wuf_form_comment_import_callback() {
    $data = $_POST['map_wuf_form_data'];
    foreach ($data as $single) {
        $values[$single['name']] = $single['value'];
    }
    $wuf_form = $values['map_wuf_forms_list'];
    $pageno = $_POST['map_wuf_request_index'];

    $wuf = new WufooApiWrapper($values['map_wuf_key'], $values['map_wuf_sub']);

    $wuf_tst_form_comments = $wuf->rtgetComments($wuf_form, null, 25, $pageno * 25);

    $wuf_frm_comments = $wuf_tst_form_comments->Comments;
    comments_db_install();
    foreach ($wuf_frm_comments as $wuf_frm_comment) {
        global $wpdb;
        $table_name = $wpdb->prefix . "_wu_2_gravity_comments_tmp";

        $comment_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE commentid='{$wuf_frm_comments->CommentId}' AND entryid='{$wuf_frm_comment->EntryId}';"));

        if (!$comment_exists) {
            $rows_affected = $wpdb->insert(
                    $table_name, array(
                'datecreated' => $wuf_frm_comment->DateCreated,
                'commentid' => $wuf_frm_comment->CommentId,
                'entryid' => $wuf_frm_comment->EntryId,
                'text' => $wuf_frm_comment->Text,
                'commentedby' => $wuf_frm_comment->CommentedBy,
                'form' => $wuf_form
                    )
            );
        }
    }
    echo count($wuf_frm_comments) + ($pageno * 25);
    die();
}

function comments_db_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . "_wu_2_gravity_comments_tmp";

    $sql = "CREATE TABLE $table_name (
        commentid mediumint(9) NOT NULL,
        datecreated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        entryid tinytext NOT NULL,
        text text NOT NULL,
        commentedby tinytext DEFAULT '' NOT NULL,
        form tinytext DEFAULT '' NOT NULL,
        UNIQUE KEY commentid (commentid)
        );";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_action('wp_ajax_map_wuf_map_commentators', 'map_wuf_map_commentators_callback');

function map_wuf_map_commentators_callback() {
    global $blog_id;
    $grav_users = get_users(array(
        'blog_id' => $blog_id));

    $wuf = new WufooApiWrapper($_POST['map_wuf_key'], $_POST['map_wuf_sub']);
    $wuf_form = $_POST['map_wuf_form'];
    $wuf_commentators = array();
    global $wpdb;
    $table_name = $wpdb->prefix . "_wu_2_gravity_comments_tmp";
    $wuf_commentators = $wpdb->get_results(
            "
            SELECT DISTINCT commentedby
            FROM {$table_name}
            WHERE form= '{$wuf_form}'
            "
    );
    $wuf_entry_count = $wuf->getEntryCount($wuf_form);
    $wuf_form_fields = $wuf->getFields($wuf_form);

    $wuf_page_size = 25;
    $wuf_times = ceil(floatval($wuf_entry_count) / 25);
    $wuf_form_entries = array();
    for ($i = 0; $i < $wuf_times; $i++) {
        $wuf_form_entries = array_merge($wuf_form_entries, $wuf->getEntries($wuf_form, 'forms', 'pageStart=' . ($i * $wuf_page_size) . '&pageSize=' . $wuf_page_size));
    }
    $gforms = map_get_forms();
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

/*
 * Callback for mapping getting the GForm fields and proceed to map them with Wufoo form fields
 */
add_action('wp_ajax_map_wuf_form_fields', 'map_wuf_form_fields_callback');

function map_wuf_form_fields_callback() {
    if (isset($_POST['action']) && $_POST['action'] == 'map_wuf_form_fields') {
        $data = $_POST['map_wuf_form_data'];
        foreach ($data as $single) {
            $values[$single['name']] = $single['value'];
        }

        $wuf = new WufooApiWrapper($_POST['map_wuf_key'], $_POST['map_wuf_sub']);
        $wuf_form = $values['map_wuf_form_hash'];
        $gform = $values['map_wuf_gforms_list'];
        echo $wuf_form;
        echo $gform;
        //Retain only the author map list
        unset($values['map_wuf_form_hash']);
        unset($values['map_wuf_gforms_list']);
        unset($values['map_wuf_entry_count']);

        $gform_data = RGFormsModel::get_form_meta($gform);
        $fields = $wuf->getFields($wuf_form);
        //$entries = $wuf->getEntries($wuf_form, 'forms', 'pageStart=0&pageSize=20');

        $return = '<h3>Map Fields:</h3>';
        $return .='<form action="" method="post" id="map_wuf_field_mapping_form">';
        $return .= '<table class="wp-list-table widefat fixed" id="map_wuf_field_mapping_table">';
        $return .= '<tr><th>Gravity Form Field</th><th>Wufoo Form Field</th></tr>';
        foreach ($gform_data['fields'] as &$gfield) {
            if ($gfield['type'] != 'section' && $gfield['type'] != 'html') {
                $return .= '<tr>';
                $return .= '<td>' . ucfirst($gfield['label']) . '</td>';
                $return .= '<td>';
                $return .= '<select name="field-' . $gfield['id'] . '" class="map_wuf_gform_fields">';
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
        $return .= '<input type="submit" name="map_wuf_field_mapping_submit" id="map_wuf_field_mapping_submit" value="Map fields" class="button" /><span class="map_loading"></span>';
        $return .= '<div class="clear"></div>';
        $return .= '</form>';
        echo $return;
        die();
    }
}

/*
 * Callback for mapping the fields
 */
add_action('wp_ajax_map_wuf_form_field_mapping', 'map_wuf_form_field_mapping_callback');

function map_wuf_form_field_mapping_callback() {
    if (isset($_POST['action']) && $_POST['action'] == 'map_wuf_form_field_mapping') {
        //The actual mapping: Gfield ID = Wufoo field ID
        $data = $_POST['map_wuf_form_data'];
        foreach ($data as $single) {
            $values[$single['name']] = $single['value'];
        }
        //Other required details
        $gform = $_POST['map_gform'];   //GForm ID
        $wuf_form = $_POST['map_wuf_form_hash']; //Wufoo form hash
        $wuf_key = $_POST['map_wuf_key']; //Wufoo API key
        $wuf_sub = $_POST['map_wuf_sub']; //Wufoo Subdomain
        $wuf_user_mapping = $_POST['map_wuf_user_mapping']; //Wufoo comment to WP user id map
        $wuf_entry_index = $_POST['map_wuf_entry_index']; //Wufoo entry chunk index

        $wuf_fields = maybe_unserialize(get_option($wuf_form . '_fields')); //Wufoo fields saved in options with form hash
        $wuf_entries = maybe_unserialize(get_option($wuf_form . '_entries')); //Wufoo entries saved in options with form hash
        $wuf_entries_chunked = array_chunk($wuf_entries, 25); //Wufoo entries chunked in batches of n (25 here, change it above too)
        //Create GForm entry
        $f = new RGFormsModel();
        $c = new GFCommon();
        $gform_meta = RGFormsModel::get_form_meta($gform);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $chunk_count = count($wuf_entries_chunked[$wuf_entry_index]);
        foreach ($wuf_entries_chunked[$wuf_entry_index] as $index => $wuf_entry) {
            //Get Wufoo comments for a particular entry

            $wuf_comments = map_get_comments_by_entry($wuf_form, $wuf_entry->EntryId);
            foreach ($values as $key => $val) {
                $field = explode('-', $key);
                $field = $field[1];
                if (isset($val) && $val != '') {
                    $field_meta = RGFormsModel::get_field($gform_meta, $field);
                    if ($field_meta['type'] == 'fileupload') {
                        //If field exists in the entries, use it else use the value directly
                        if (property_exists($wuf_entry, $val)) {
                            $image_url = $wuf_entry->$val;
                            if (isset($image_url) && $image_url != '') {
                                $image_url = explode('(', $image_url);
                                $image_url = explode(')', $image_url[1]);
                                $image_data = wp_remote_get($image_url[0]);
                                if (!is_wp_error($image_data) && $image_data['body'] != '') {
                                    $upload = wp_upload_bits(basename($image_url[0]), 0, $image_data['body']);
                                    $op[$field] = $upload['url'];
                                } else {
                                    $op[$field] = 'Image not found';
                                }
                            } else {
                                $op[$field] = 'No image set';
                            }
                        } else {
                            $image_url = $val;
                            if (isset($image_url) && $image_url != '') {
                                $image_data = wp_remote_get($image_url[0]);
                                $upload = wp_upload_bits(basename($image_url[0]), 0, $image_data['body']);
                                $op[$field] = $upload['url'];
                            } else {
                                $op[$field] = 'No image set';
                            }
                        }
                    } else {
                        if (property_exists($wuf_entry, $val)) {
                            if (property_exists($wuf_fields->Fields[$val], 'SubFields') && is_array($wuf_fields->Fields[$val]->SubFields)) {
                                if (is_array($field_meta['inputs'])) {
                                    $wuf_field_keys = array_keys($wuf_fields->Fields[$val]->SubFields);
                                    if (count($wuf_field_keys) == count($field_meta['inputs'])) {
                                        foreach ($wuf_field_keys as $i => $wuf_field_key) {
                                            $op[strval($field_meta['inputs'][$i]['id'])] = $wuf_entry->$wuf_field_key;
                                        }
                                    } else if (count($wuf_field_keys) > count($field_meta['inputs'])) {
                                        $wuf_last = array_pop($wuf_field_keys);
                                        $gfield_last = array_pop($field_meta['inputs']);
                                        $op[strval($gfield_last['id'])] = $wuf_entry->$wuf_last;
                                        $wuf_value = '';
                                        foreach ($wuf_fields->Fields[$val]->SubFields as $key => $value) {
                                            $wuf_value .= $wuf_entry->$key;
                                            $wuf_value .= ' ';
                                        }
                                        $op[$field_meta['inputs']][0] = $wuf_value;
                                    }
                                } else {
                                    $wuf_value = '';
                                    foreach ($wuf_fields->Fields[$val]->SubFields as $key => $value) {
                                        $wuf_value .= $wuf_entry->$key;
                                        $wuf_value .= ' ';
                                    }
                                    $op[$field] = $wuf_value;
                                }
                            } else {
                                $op[$field] = $wuf_entry->$val;
                            }
                        } else {
                            $op[$field] = $val;
                        }
                    }
                } else {
                    $op[$field] = '';
                }
            }

            //Set user for the entry
            if (isset($wuf_user_mapping) && !empty($wuf_user_mapping)) {
                if (array_key_exists($wuf_entry->CreatedBy, $wuf_user_mapping)) {
                    $user = $wuf_user_mapping[$wuf_entry->CreatedBy];
                    $user = get_user_by('id', $user);
                    if (!is_wp_error($user)) {
                        $user_id = $user;
                    } else {
                        $user_id = 1;
                    }
                } else {
                    $user_id = 1;
                }
            }

            //Set the default params for a lead entry
            $date = isset($wuf_entry->DateCreated) && $wuf_entry->DateCreated != '' ? $wuf_entry->DateCreated : date('Y-m-d H:i:s');
            $user_id = $current_user && $current_user->ID ? $current_user->ID : 'NULL';
            $lead_table = $f->get_lead_table_name();
            $user_agent = strlen($_SERVER["HTTP_USER_AGENT"]) > 250 ? substr($_SERVER["HTTP_USER_AGENT"], 0, 250) : $_SERVER["HTTP_USER_AGENT"];
            $currency = $c->get_currency();
            $ip = $f->get_ip();
            $page = $f->get_current_page_url();

            //Insert a new entry/lead
            $wpdb->query($wpdb->prepare("INSERT INTO $lead_table(form_id, ip, source_url, date_created, user_agent, currency, created_by) VALUES(%d, %s, %s, %s, %s, %s, {$user_id})", $gform, $ip, $page, $date, $user_agent, $currency));
            $lead_id = $wpdb->insert_id;
            if ($lead_id) {
                foreach ($op as $inputid => $value) {
                    $wpdb->insert($prefix . 'rg_lead_detail', array('lead_id' => $lead_id, 'form_id' => $gform, 'field_number' => $inputid, 'value' => $value), array('%d', '%d', '%f', '%s'));
                    $lead_detail_id = $wpdb->insert_id;
                    //If the value is more than 200 chars, insert it into lead detail long table
                    if (strlen($value) > 200) {
                        map_insert_lead_detail_long($lead_detail_id, $value);
                    }
                }

                //Insert comments as notes for the corresponding user map
                if (isset($wuf_comments) && !empty($wuf_comments)) {
                    foreach ($wuf_comments as $wuf_comment) {
                        if (array_key_exists($wuf_comment->commentedby, $wuf_user_mapping)) {
                            $table_name = $f->get_lead_notes_table_name();
                            $user = get_user_by('id', $wuf_user_mapping[$wuf_comment->commentedby]);
                            $sql = $wpdb->prepare("INSERT INTO $table_name(lead_id, user_id, user_name, value, date_created) values(%d, %d, %s, %s, %s)", $lead_id, $wuf_user_mapping[$wuf_comment->commentedby], $user->display_name, $wuf_comment->text, $wuf_comment->datecreated);
                            $wpdb->query($sql);
                        }
                    }
                }
            }
        } //Chunk loop end

        echo $wuf_entry_index * 25 + $chunk_count;
    }
    die();
}

/*
 * Get Wufoo comments for a particular entry
 */

function map_get_comments_by_entry($formhash, $entry_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . "_wu_2_gravity_comments_tmp";
    $sql = "SELECT * FROM {$table_name} WHERE entryid='{$entry_id}' AND form='{$formhash}'";
    $comments = $wpdb->get_results($sql);
    return $comments;
}

/*
 * Insert GField long detail in the long detail table
 */

function map_insert_lead_detail_long($lead_detail_id, $value) {
    global $wpdb;
    $f = new RGFormsModel();
    $lead_detail_long = $f->get_lead_details_long_table_name();
    $query = $wpdb->prepare("INSERT INTO $lead_detail_long(lead_detail_id, value) VALUES(%d, %s)", $lead_detail_id, $value);
    $wpdb->query($query);
}

function rt_map_err_handling($rt_importer_e) {
    echo '<pre>';
    print_r($rt_importer_e);
    echo '</pre>';
}

/*
 * Create DB tables for a Wufoo form
 */

function map_form_fields_db($hash, $columns) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    $table = $prefix . '_' . $hash . '_comments';

    //Need to check the $columns array before forming this query!
    $columns = implode(" LONGTEXT,\n", $columns);
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        " . $columns . "
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

