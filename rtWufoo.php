<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of rtWufoo
 *
 * @author sourabh
 */
class rtWufoo {

    var $subdomain = '';
    var $api_key = '';
    var $wufoo = null;

    function __construct() {
        add_action('admin_menu', array($this, 'admin'), 11);
    }

    function admin() {
        $hook = add_submenu_page(
                'gf_edit_forms', 'Wufoo to Gravity', 'Wufoo Importer', 'administrator', 'mapper_wufoo', array($this, 'ui')
        );
        add_action('admin_print_scripts-' . $hook, 'map_assets_enqueue');
    }

    function ui() {
        ?>
        <div class="wrap">
            <h2>Import entries to Gravity Forms</h2>
            <p class="textleft">Enter your Wufoo details below and select the forms for mapping the entries</p>

            <div class="hr-divider"></div>
            <?php
            $this->init();
            if (empty($this->subdomain) && empty($this->api_key)) {
                $this->step = 'get_api';
            } else {
                $this->step = 'get_forms';
            }
            $this->api_form();
            $this->form_select_ui();
            ?>
        </div>
        <?php
    }

    function save_options($subdomain, $api_key) {
        update_site_option('rt_wufoo_gravity_subdomain', $subdomain);
        update_site_option('rt_wufoo_gravity_api_key', $api_key);
    }

    function set_options() {
        $this->subdomain = get_site_option('rt_wufoo_gravity_subdomain');
        $this->api_key = get_site_option('rt_wufoo_gravity_api_key');
    }

    function process_api_form() {
        if (isset($_POST['map_wuf_submit'])) {
            if (!empty($_POST['map_wuf_sub']) &&
                    !empty($_POST['map_wuf_key'])) {
                $this->save_options(
                        $_POST['map_wuf_sub'], $_POST['map_wuf_key']
                );
            }
        }
    }

    function init() {
        $this->set_options();
        $this->wufoo = new WufooApiWrapper($this->subdomain, $this->api_key);
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

    function api_form() {
        ?>
        <form action="" method="post" id="map_wuf_credentials">
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

    function input_form_selector() {
        $form_select = '<select name="map_wuf_forms_list" id="map_wuf_forms_list">'
                . '<option value="">Choose a form</option>';
        foreach ($this->wforms as $hash => $wuf_form) {
            $form_select .= '<option value="' . $hash . '">' . $wuf_form->Name . '</option>';
        }
        $form_select .= '</select>';

        return $form_select;
    }

    function form_select_ui() {
        ?>
        <h3>Select a Wufoo Form</h3>
        <form method="post" id="map_wuf_forms_list_form">
            <table class="form-table" id="map_wuf_forms_list_table">
                <tr>
                    <th scope="row"><label for="map_wuf_forms_list">Select a Form:</label></th>
                    <td>
                        <?php echo $this->input_form_selector(); ?>
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
            <input type="hidden" name="map_wuf_sub" value="<?php echo $this->subdomain; ?>" />
            <input type="hidden" name="map_wuf_key" value="<?php echo $this->api_key; ?>" />
        </form>
        <?php
    }

    function enqueue() {
        wp_enqueue_script('mapper-script', plugins_url('/js/mapper.js', __FILE__), array('jquery'));
        wp_enqueue_style('mapper-style', plugins_url('/css/mapper.css', __FILE__));
    }

    function error($err) {
        echo '<pre>';
        print_r($err);
        echo '</pre>';
    }

}
?>
