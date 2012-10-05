/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
jQuery(document).ready(function(){
    jQuery('#map_mapping_form').submit(function(){
        jQuery(this).find('.map_loading').show();
        var data = jQuery(this).serializeArray();
        var count = jQuery('#map_row_count').val();
        for(var i = 0; i < count; i++ ){
            var ajaxdata = {
                action: 'map_import',
                map_data: data,
                map_filename: jQuery('#map_filename').val(),
                map_form_id: jQuery('#map_form_id').val(),
                map_row_index: i
            }
            jQuery.post(ajaxurl, ajaxdata, function(response){
                if(response != 0){
                    jQuery('#map_mapping_progress').append('<tr><td><div class="map_mapping_success">Row '+response+' inserted.</td></tr>');
                } else {
                    jQuery('#map_mapping_progress').append('<tr><td><div class="map_mapping_failure">Row '+response+' failed.</td></tr>');
                }
            });
        }
        return false;
    });

//    jQuery(".map_loading").ajaxStart(function(){
//        jQuery(this).show();
//    });
    //Input field for other option
    jQuery('.map_form_fields').change(function(){
        if(jQuery(this).val() == 'other'){
            jQuery(this).parent().append('<input type="text" name="'+jQuery(this).attr('name')+'"/>');
            jQuery(this).removeAttr('name')
        } else {
            var attr = jQuery(this).parent().children('input').attr('name');
            jQuery(this).attr('name', attr);
            jQuery(this).parent().children('input').remove();
        }
    });
    
    jQuery('.map_wuf_gform_fields').live('change', function(){
        if(jQuery(this).val() == 'other') {
            jQuery(this).parent().append('<input type="text" name="'+jQuery(this).attr('name')+'"/>');
            jQuery(this).removeAttr('name')
        } else {
            var attr = jQuery(this).parent().children('input').attr('name');
            jQuery(this).attr('name', attr);
            jQuery(this).parent().children('input').remove();
        }
    });
    
    //Disable same option
//    jQuery('.map_form_fields').change(function(){
//        var val = jQuery(this).val();
//        jQuery('.map_form_fields').not(jQuery(this)).each(function(){
//            jQuery(this).find('option[value="'+val+'"]').attr('disabled', 'disabled');
//        });
//    });

    jQuery('#map_wuf_forms_list_form').submit(function(){
        var obj = jQuery(this);
        obj.find('#map_wuf_forms_list_error').remove();
        if(obj.find('#map_wuf_forms_list').val() == ''){
            jQuery('#map_wuf_forms_list_table').prepend('<tr id="map_wuf_forms_list_error"><td colspan="2"><div class="error">Please select a Wufoo Form!</div></td></tr>');
        } else {
            obj.find('.map_loading').show();
            var form_data = jQuery(this).serializeArray();
            var ajaxdata = {
                action: 'map_wuf_form_select',
                map_wuf_form_data: form_data
            };
            jQuery.post(ajaxurl, ajaxdata, function(response){
                obj.find('.map_loading').hide();
                obj.after(response);
            });
        }
        return false;
    });
    
    jQuery('#map_wuf_comment_mapping_form').live('submit', function(){
        var obj = jQuery(this);
        obj.find('#map_wuf_forms_list_error').remove();
        if(obj.find('#map_wuf_gforms_list').val() == ''){
            jQuery('#map_wuf_gforms_list_table').prepend('<tr id="map_wuf_forms_list_error"><td colspan="2"><div class="error">Please select a Gravity Form!</div></td></tr>');
        } else {
            obj.find('.map_loading').show();
            var form_data = jQuery(this).serializeArray();
            var ajaxdata = {
                action: 'map_wuf_form_fields',
                map_wuf_form_data: form_data,
                map_wuf_key: jQuery('#map_wuf_key').val(),
                map_wuf_sub: jQuery('#map_wuf_sub').val()
            };
            jQuery.post(ajaxurl, ajaxdata, function(response){
                obj.find('.map_loading').hide();
                obj.after(response);
            });
        }
        return false;
    });
    
    jQuery('#map_wuf_field_mapping_form').live('submit', function(){
        var obj = jQuery(this);
        //obj.find('.error').remove();
        var flag = 0;
//        jQuery('.map_wuf_gform_fields').each(function(){
//            if(jQuery(this).val() == ''){
//                flag = 1;
//            }
//        });
        
        if(flag == 1){
            obj.prepend('<div class="error">Please match non-blank fields!</div>');
        } else {
            obj.find('.map_loading').show();
            var form_data = obj.serializeArray();
            var user_map = {};
            jQuery('#map_wuf_comment_mapping_table select').each(function(){
                user_map[jQuery(this).attr('name')] = jQuery(this).val();
            });
            
            var current = 0;
            var count = jQuery('#map_wuf_entry_count').val();
            for(var i = 0; i < parseInt(count); i++){
                var ajaxdata = {
                    action: 'map_wuf_form_field_mapping',
                    map_gform: jQuery('#map_wuf_gforms_list').val(),
                    map_wuf_form_hash: jQuery('#map_wuf_form_hash').val(),
                    map_wuf_key: jQuery('#map_wuf_key').val(),
                    map_wuf_sub: jQuery('#map_wuf_sub').val(),
                    map_wuf_form_data: form_data,
                    map_wuf_user_mapping: user_map,
                    map_wuf_entry_index: i
                };
                
//                For Asynchronous requests, not working
//                jQuery.ajax({
//                    action: 'map_wuf_form_field_mapping',
//                    type: "POST",
//                    url: ajaxurl,
//                    data: ajaxdata,
//                    async: false
//                }).done(function(response){
//                    current = current+1;
//                    if(response != 0){
//                        obj.find('.map_loading').hide();
//                        jQuery('#map_mapping_progress').html('<tr><td><div class="map_mapping_success">Row '+response+' inserted. '+current+'/'+count+'</td></tr>');
//                    } else {
//                        jQuery('#map_mapping_progress').html('<tr><td><div class="map_mapping_failure">Row '+response+' failed. '+current+'/'+count+'</td></tr>');
//                    }
//                });
                    

                jQuery.ajaxSetup({type: 'POST', async: false});
                jQuery.post(ajaxurl, ajaxdata, function(response){
                    current = current + 1;
                    if(response != 0){
                        obj.find('.map_loading').hide();
                        jQuery('#map_mapping_progress').html('<tr><td><div class="map_mapping_success">Row '+response+' inserted. '+current+'/'+count+'</td></tr>');
                    } else {
                        jQuery('#map_mapping_progress').html('<tr><td><div class="map_mapping_failure">Row '+response+' failed. '+current+'/'+count+'</td></tr>');
                    }
                });
            }
        }
        return false;
    });
});