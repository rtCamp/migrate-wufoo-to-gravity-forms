<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of rtWufooToGravity
 *
 * @author sourabh
 */
class rtWufooToGravity {

    //put your code here

    function menu() {
        $hook = add_submenu_page('gf_edit_forms', 'Raw to GForms Mapper', 'Mapper', 'administrator', 'mapper', 'map_admin_page');
        add_action('admin_print_scripts-' . $hook, 'map_assets_enqueue');
        $wuf_hook = add_submenu_page('gf_edit_forms', 'Wufoo Entries', 'Wufoo', 'administrator', 'mapper_wufoo', 'map_wufoo_admin_page');
        add_action('admin_print_scripts-' . $wuf_hook, 'map_assets_enqueue');
    }

}

?>
