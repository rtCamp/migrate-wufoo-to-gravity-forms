<?php

/**
 * Description of rtWufooToGravity
 *
 * @author sourabh
 */
class rtCSV {

    function __construct() {
        $this->active = RGForms::get("active") == "" ? null : RGForms::get("active");
        $this->gravity_forms = RGFormsModel::get_forms($active, "title");
    }

    function menu() {
        $hook = add_submenu_page(
                'gf_edit_forms', 'Raw to GForms Mapper', 'Mapper', 'administrator', 'mapper', array($this, 'map_admin_page')
        );
        add_action('admin_print_scripts-' . $hook, 'map_assets_enqueue');
    }

    function map_admin_page() {
        echo '<div class="wrap">'
        . '<h2>Mapper Settings Page</h2>';

        $this->source_ui();

        if (isset($_POST['map_submit']) && $_POST['map_submit'] != '') {
            $this->csv_process();
        }

        echo '</div>';
    }

    function source_ui() {
        ?>
        <form action="" method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="map_upload">Upload a CSV:</label></th>
                    <td>
                        <input type="file" name="map_upload" id="map_upload" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        OR
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="map_forms_list">Select a Gravity Form:</label></th>
                    <td>
        <?php echo $this->gforms_select_ui(); ?>
                    </td>
                </tr>
                <tr>
                    <td><input type="submit" name="map_submit" value="Upload" class="button"/></td>
                </tr>
            </table>
        </form>
        <?php
    }

    function csv_process() {

        $form_id = isset($_POST['map_forms_list']) && $_POST['map_forms_list'] != '' ? $_POST['map_forms_list'] : '';

        if (isset($_FILES['map_upload']) && $_FILES['map_upload']['error'] == 0) {
            if ($_FILES['map_upload']['type'] != 'text/csv') {
                echo "<div class='error'>Please upload a CSV file only!</div>";
            }

            //Upload the file to 'Uploads' folder
            $file = $_FILES['map_upload'];
            $upload = wp_handle_upload($file, array('test_form' => false));
            //Parse CSV
            $this->csv = new parseCSV();
            $this->csv->auto($upload['file']);
            ?>
            <div id="map_message" class="updated map_message">
                File uploaded: <strong><?php echo $_FILES['map_upload']['name']; ?></strong>
                Total Rows: <strong><?php echo count($csv->data); ?></strong>
            </div>
            <input type="hidden" name="map_row_count" id="map_row_count" value="<?php echo count($this->csv->data); ?>" />
            <input type="hidden" name="map_filename" id="map_filename" value="<?php echo $upload['file']; ?>" />
            <input type="hidden" name="map_form_id" id="map_form_id" value="<?php echo $form_id; ?>" />


            <?php
            $this->map_form($form_id);
        }
    }

    function map_form($form_id) {
        ?>
        <form method = "post" action = "" id = "map_mapping_form" name = "map_mapping_form">
            <table class = "wp-list-table widefat fixed" id = "map_mapping_table">
                <tr>
                    <th scope = "row">Column Name</th>
                    <th scope = "row">Field Name</th>
                </tr>

                <?php $this->map_ui($form_id)
                ?>
            </table>
            <input type="submit" name="map_mapping_import" id="map_mapping_import" value="Import" class="button"/>
            <?php
            echo $this->progress_ui();
            ?>
        </form>
        <?
    }

    function map_ui($form_id) {
        $this->fields_ui($form_id);
        $form_extra_fields = $this->extra_fields();
        $this->meta_fields($form_extra_fields);
    }

    function fields_ui($form_id) {
        $form_data = RGFormsModel::get_form_meta($form_id);
        foreach ($form_data['fields'] as &$field) {
            if ($field['type'] != 'section' && $field['type'] != 'html') {
                ?>
                <tr>
                    <td><?php echo ucfirst($field['label']); ?></td>
                    <td>
                        <?php
                        $fields = '<select name="field-' . $field['id'] . '" class="map_form_fields">';
                        $fields .= '<option value="">Choose a field or Skip it</option>';
                        foreach ($this->csv->titles as $value) {
                            $fields .= '<option value="' . $value . '">' . ucfirst($value) . '</option>';
                        }
                        $fields .= '<option value="other">Other Field</option>';
                        $fields .= '</select>';
                        echo $fields;
                        ?>
                    </td>
                </tr>
                <?php
            }
        }
    }

    function extra_fields() {
        $extra_fields = '<option value="">Choose a field</option>';
        foreach ($$this->csv->titles as $value) {
            $extra_fields .= '<option value="' . $value . '">' . ucfirst($value) . '</option>';
        }
        $extra_fields .= '<option value="other">Other Field</option>';
        return $extra_fields;
    }

    function progress_ui() {
        return '<span class="map_loading"></span>'
                . '<div class="clear"></div>'
                . '<table id="map_mapping_progress" class="widefat">'
                . '</table>';
    }

    function meta_fields($form_extra_fields) {
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
        <?php
    }

    /**
     * Provides a reformatted array of active Gravity Forms in the system
     *
     * @return array
     */
    function gforms() {

        $formatted_form_array = array();

        if (!empty($this->gravity_forms) && is_array($this->gravity_forms)) {
            foreach ($this->gravity_forms as $form) {
                $formatted_form_array[$form->id] = $form->title;
            }
        }

        return $formatted_form_array;
    }

    function gforms_select_ui() {

        $forms = $this->gforms();
        $html = '';
        if (!$forms) {
            $html .= '<strong>No forms were found. Please create some.</strong>';
        } else {
            $html .= '<select name="map_forms_list" id="map_forms_list">';
            $html .= '<option value="">Please select a form</option>';
            foreach ($forms as $id => $form) {
                $html .= '<option value="' . $id . '">' . $form . '</option>';
            }
        }

        return $html;
    }

    function map_wufoo_admin_page() {
        ?>
        <div class="wrap">
            <h2>Import entries to Gravity Forms</h2>
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

}
?>
