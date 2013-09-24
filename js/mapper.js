/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

function fireFieldRequest(data) {
    var obj = jQuery('#map_wuf_field_mapping_form');
    var total = jQuery('#map_wuf_entry_total').val();
    return jQuery.post(ajaxurl, data, function(response) {
        if (response != 0) {
            var progw = Math.ceil((parseInt(response) / parseInt(total)) * 100);
            obj.find('.map_loading').hide();
            jQuery('#map_progress_msgs').html('<div class="map_mapping_success"> Processed ' + response + ' of ' + total + '.</div>');
            jQuery('#progressbar>div').css('width', progw + '%');
        } else {
            jQuery('#map_progress_msgs').html('<div class="map_mapping_failure">Row ' + response + ' failed.</div>');
        }

    });
}

function fireCommentRequest(data) {

    var total = jQuery('.rt_wufoo_comment_count').val();
    return jQuery.post(ajaxurl, data, function(response) {
        if (response != 0) {
            var progw = Math.ceil((parseInt(response) / parseInt(total)) * 100);
            jQuery('#rtprogressbar>div').css('width', progw + '%');
            jQuery('.rt_wufoo_comment_index').val(response);
        } else {
            rt_wufoo_alert('Row ' + response + ' failed');
        }
        if (response == total) {
            jQuery.get(ajaxurl,{action: 'rt_wufoo_comment_next', form: rt_wufoo_obj.form},function(hresponse){
                jQuery('tr#rt_wufoo_comment_next').replaceWith(hresponse);
                    jQuery('.rt_wufoo_form').val(rt_wufoo_obj.form);
                    jQuery('.rt_wufoo_loader').remove();
            });

        }

    });
}
function post_comment_import() {
}

function rtWufooDone(form, data){
    switch(form){
        
        case 'rt_wufoo_api_form':
            
            data = jQuery.parseJSON(data);
            rt_wufoo_obj.subdomain = data.subdomain;
            rt_wufoo_obj.api_key = data.api_key;
            formdata= {
                action: 'rt_wufoo_form_select_ui'
            }
            jQuery.get(ajaxurl,formdata).done(
                    function(newdata){
                        jQuery('#rt-wufoo-step-form').html(newdata);
                    }
                );
            break;
            
        case 'rt_wufoo_comment_count_ajax':
            jQuery('.rt_wufoo_comment_count').val(data);
            var countreq = Math.ceil((data / 25));
            var form_data = jQuery('#'+form).serializeArray();
            chain_data = {};
            for (var i = 0; i < parseInt(countreq); i++) {
                
                var act_data = form_data;
                act_data.push({
                    name:'action',
                    value: 'rt_wufoo_comment_import',
                });
                chain_data[i] = act_data;
            }

            var newstartingpoint = jQuery.Deferred();
            newstartingpoint.resolve();
            jQuery.each(chain_data, function(i, v) {
                newstartingpoint = newstartingpoint.pipe(function() {
                    return fireCommentRequest(v);
                });
            });
            break;
            
        case 'rt_wufoo_map_users':
            
            jQuery('#rt-wufoo-step-users').html(data);
            break;
        case 'rt_wufoo_form_fields_map':
            jQuery('#rt-wufoo-step-fields').html(data);
            break;
        case 'rt_wufoo_field_mapping_form':
             jQuery('#rt-wufoo-step-import').html(data);
             break;
        default:
            break;
        
    }
    
}

function rtWufooFail(form, data){
    switch(form){
        case 'rt_wufoo_api_form':
            rt_wufoo_alert('Please check the API settings');
            break;
        case 'rt_wufoo_comment_import':
            break;
        case 'rt_wufoo_map_users':
            break;
        default: 
    }
    
}


function rt_wufoo_alert($err){
    console.log($err);
}

function rt_wufoo_loader(formID){
    jQuery('#'+formID).closest('.rt_wufoo_stepbox').after('<div class="rt_wufoo_loader"></div>');
    
}
jQuery(document).ready(function() {
    
    
    //Any form that is submitted
    jQuery('#rt_wufoo_wizard').on('submit','form',function(e){
        
        //assign id attribute to var
        formID = jQuery(this).attr('id');
        
        //check if this is our form
        if(formID.indexOf('rt_wufoo_') == 0){
            
            //only if it is our form, prevent submit
            e.preventDefault();
            
            //get the form data
            var formData = jQuery(this).serializeArray();
            
            
            //add the necessary action to the data array/object for wp_ajax
            formData.push({ name: 'action', value : formID });
            
            // add the loader
            rt_wufoo_loader(formID);
            
            //post the form
            jQuery.post( ajaxurl,formData)
                    .done(function( data ){
                        rtWufooDone(formID,data);
                        jQuery('.rt_wufoo_loader').remove();
                    })
                    .fail(function(){
                        rtWufooFail(formID);
                        jQuery('.rt_wufoo_loader').remove();
                    });
        }
        
    });
    
    
    
    jQuery('#rt_wufoo_wizard').on('change', '#rt_wufoo_form_selector',function(){
        $this = jQuery(this);
        rt_wufoo_obj.form = jQuery(this).val();
        if(rt_wufoo_obj.form!=''){
            
            formdata = {
                action: 'rt_wufoo_comment_count',
                form: rt_wufoo_obj.form
            };
            jQuery(this).after('<div class="rt_wufoo_loader"></div>');
            jQuery.get(ajaxurl,formdata)
            .done(function(data){
                rt_wufoo_obj.comment_count = data;
                progressdata = {
                    action:'rt_wufoo_comment_next',
                    form: rt_wufoo_obj.form
                };
                
                jQuery.get(ajaxurl,progressdata)
                        .done(function(newdata){
                            jQuery('tr#rt_wufoo_comment_next').remove();
                    $this.closest('tr').after(newdata);
                    jQuery('.rt_wufoo_form').val(rt_wufoo_obj.form);
                    jQuery('.rt_wufoo_loader').remove();
                });
            });
        }
        
    });
    
    jQuery('#map_mapping_form').submit(function() {
        jQuery(this).find('.map_loading').show();
        var data = jQuery(this).serializeArray();
        var count = jQuery('#map_row_count').val();
        for (var i = 0; i < count; i++) {
            var ajaxdata = {
                action: 'map_import',
                map_data: data,
                map_filename: jQuery('#map_filename').val(),
                map_form_id: jQuery('#map_form_id').val(),
                map_row_index: i
            }
            jQuery.post(ajaxurl, ajaxdata, function(response) {
                if (response != 0) {
                    jQuery('#map_mapping_progress').append('<tr><td><div class="map_mapping_success">Row ' + response + ' inserted.</td></tr>');
                } else {
                    jQuery('#map_mapping_progress').append('<tr><td><div class="map_mapping_failure">Row ' + response + ' failed.</td></tr>');
                }
            });
        }
        return false;
    });

    jQuery('.map_form_fields').change(function() {
        if (jQuery(this).val() == 'other') {
            jQuery(this).parent().append('<input type="text" name="' + jQuery(this).attr('name') + '"/>');
            jQuery(this).removeAttr('name')
        } else {
            var attr = jQuery(this).parent().children('input').attr('name');
            jQuery(this).attr('name', attr);
            jQuery(this).parent().children('input').remove();
        }
    });

    jQuery('.map_wuf_gform_fields').live('change', function() {
        if (jQuery(this).val() == 'other') {
            jQuery(this).parent().append('<input type="text" name="' + jQuery(this).attr('name') + '"/>');
            jQuery(this).removeAttr('name')
        } else {
            var attr = jQuery(this).parent().children('input').attr('name');
            jQuery(this).attr('name', attr);
            jQuery(this).parent().children('input').remove();
        }
    });

//Disable same option from multiple selects
//    jQuery('.map_form_fields').change(function(){
//        var val = jQuery(this).val();
//        jQuery('.map_form_fields').not(jQuery(this)).each(function(){
//            jQuery(this).find('option[value="'+val+'"]').attr('disabled', 'disabled');
//        });
//    });
    jQuery('#map_wuf_skip_comments').click(function() {
        var obj = jQuery('#map_wuf_forms_list_form');
        jQuery('#map_mapping_progress_bar').hide();
        obj.find('.map_loading').show();
        var commentatordata = {
            action: 'map_wuf_map_commentators',
            map_wuf_key: jQuery('#map_wuf_key').val(),
            map_wuf_sub: jQuery('#map_wuf_sub').val(),
            map_wuf_form: jQuery('#map_wuf_forms_list').val()
        }
        jQuery.post(ajaxurl, commentatordata, function(response) {
            obj.find('.map_loading').hide();
            obj.after(response);
        });

    });
    jQuery('#map_wuf_forms_list_form').submit(function() {
        var obj = jQuery(this);
        obj.find('#map_wuf_forms_list_error').remove();
        if (obj.find('#map_wuf_forms_list').val() == '') {
            jQuery('#map_wuf_forms_list_table').prepend('<tr id="map_wuf_forms_list_error"><td colspan="2"><div class="error">Please select a Wufoo Form!</div></td></tr>');
        } else {
            obj.find('.map_loading').show();
            var form_data = jQuery(this).serializeArray();
            var ajaxdata = {
                action: 'map_wuf_form_select',
                map_wuf_form_data: form_data
            };
            jQuery.post(ajaxurl, ajaxdata, function(response) {
                obj.find('.map_loading').hide();
                jQuery('input#map_wuf_comment_count').val(response);
                commentcountstr = response + ' comments found';
                obj.after(commentcountstr);
                var countreq = Math.ceil((response / 25));
                var data = {};
                for (var i = 0; i < parseInt(countreq); i++) {
                    var ajaxdata = {
                        action: 'map_wuf_form_comment_import',
                        map_wuf_form_data: form_data,
                        map_wuf_request_index: i
                    };
                    data[i] = ajaxdata;
                }

                var newstartingpoint = jQuery.Deferred();
                newstartingpoint.resolve();
                jQuery('#map_mapping_progress_bar').show();
                jQuery.each(data, function(i, v) {
                    jQuery('#map_progress_msgs').html('<div class="map_mapping_process">Process started. Please wait.</div>');
                    newstartingpoint = newstartingpoint.pipe(function() {
                        return fireCommentRequest(v);
                    });
                });

            });
        }
        return false;
    });

    jQuery('#map_wuf_comment_mapping_form').live('submit', function() {
        var obj = jQuery(this);
        obj.find('#map_wuf_forms_list_error').remove();
        if (obj.find('#map_wuf_gforms_list').val() == '') {
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
            jQuery.post(ajaxurl, ajaxdata, function(response) {
                obj.find('.map_loading').hide();
                obj.after(response);
            });
        }
        return false;
    });

    jQuery('#map_wuf_field_mapping_form').live('submit', function() {
        var obj = jQuery(this);
        //obj.find('.error').remove();
        var flag = 0;
//        jQuery('.map_wuf_gform_fields').each(function(){
//            if(jQuery(this).val() == ''){
//                flag = 1;
//            }
//        });

        if (flag == 1) {
            obj.prepend('<div class="error">Please match non-blank fields!</div>');
        } else {
            obj.find('.map_loading').show();
            var form_data = obj.serializeArray();
            var user_map = {};
            jQuery('#map_wuf_comment_mapping_table select').each(function() {
                user_map[jQuery(this).attr('name')] = jQuery(this).val();
            });

            var current = 0;
            var count = jQuery('#map_wuf_entry_count').val();
            var data = {};
            for (var i = 0; i < parseInt(count); i++) {
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
                data[i] = ajaxdata;
            }

            var startingpoint = jQuery.Deferred();
            startingpoint.resolve();
            jQuery('#map_mapping_progress_bar').show();
            jQuery.each(data, function(i, v) {
                jQuery('#map_progress_msgs').html('<div class="map_mapping_process">Process started. Please wait.</div>');
                startingpoint = startingpoint.pipe(function() {
                    return fireFieldRequest(v);
                });
            });


        }
        return false;
    });
});