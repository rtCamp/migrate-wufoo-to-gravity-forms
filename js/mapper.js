/*  UTILITY FUNCTIONS   */

/**
 * Blinks the supplied element once.
 * Used for error message blinking
 * 
 * @param {object} el JQuery element
 * @returns {undefined} nothing
 */
function rtWufooBlink(el){
    jQuery(el).fadeOut('slow', function(){
        jQuery(this).fadeIn('slow');
    });
}

/**
 * Creates the alert from given text
 * 
 * @param {string} $err The error message string
 * @returns {undefined} Northing
 */
function rtWufooAlert($err){
    $container = jQuery('#rt_wufoo_error');
    $close = '&nbsp;[<a href="#" class="rt_wufoo_err_close">close</a>]';
    $msgcontainer =  jQuery('<div id="rt_wufoo_error_msg"></div>');
    $msgcontainer.html($err);
    $msgcontainer.append($close);
    $container.append($msgcontainer);
    rtWufooBlink($msgcontainer);
}

/**
 * Creates the spinner for ajax requests between steps
 * 
 * @param {string} formID The id of the form, the loader is for
 * @returns {undefined} nothing
 */
function rtWufooLoader(formID){
    jQuery('#'+formID)
            .closest('.rt_wufoo_stepbox')
            .after('<div class="rt_wufoo_loader"></div>');
    
}

/**
 * Scrolls to the given element. Useful for moving between steps
 * 
 * @param {object} el the jQuery element to scroll to 
 * @returns {undefined}
 */

function rtWufooScroll(el){
    jQuery(window).scrollTop(el.position().top);
}

/*  AJAX PIPES          */

/**
 * 
 * @param {type} data
 * @returns {unresolved}
 */
function fireEntryRequest(data) {
   
    var total = jQuery('#rt_wufoo_entry_total').val();
    var data = jQuery('#rt_wufoo_entries_import').serializeArray();
    data.push({ name: 'action', value : 'rt_wufoo_import_entries' });
    
    return jQuery.post(ajaxurl, data, function(response) {
        if (response != 0) {
            var progw = Math.ceil((parseInt(response) / parseInt(total)) * 100);
            jQuery('#rtprogressbar>div').css('width', progw + '%');
            jQuery('#rt_wufoo_entry_index').val(response);
            jQuery('#rt_wufoo_imported_entries').html(response);
        } else {
            rtWufooAlert('Row ' + response + ' failed');
            jQuery('.rt_wufoo_loader').remove();
        }
        if (response == total) {
            jQuery('#rt-wufoo-step-import').after('<div id="rt_wufoo_completed" class="rt_wufoo_steps"><div class="rt_wufoo_stepbox"><h3>Done!</h3></div></div>');
            rtWufooScroll(jQuery('#rt_wufoo_completed'));
        }

    });
}
/**
 * Pipe function for comment import
 * 
 * @param {type} data The form data for processing
 * @returns {unresolved} the promise for the next iteration in the pipe
 */
function fireCommentRequest() {

    var total = jQuery('#rt_wufoo_comment_count').val();
    var data = jQuery('#rt_wufoo_comment_import').serializeArray();
    data.push({ name: 'action', value : 'rt_wufoo_comment_import' });
    
    return jQuery.post(ajaxurl, data, function(response) {
        if (response != 0) {
            var progw = Math.ceil((parseInt(response) / parseInt(total)) * 100);
            jQuery('#rtprogressbar>div').css('width', progw + '%');
            jQuery('#rt_wufoo_comment_index').val(response);
            jQuery('#rt_wufoo_imported_comments').html(response);
        } else {
            rtWufooAlert('Row ' + response + ' failed');
            jQuery('.rt_wufoo_loader').remove();
        }
        if (response == total) {
            rtWufooLoader(jQuery('#rtprogressbar>div').closest('form').attr('id'));
            jQuery.get(ajaxurl,{action: 'rt_wufoo_comment_next', form: rt_wufoo_obj.form}).done(function(hresponse){
                jQuery('tr#rt_wufoo_comment_next').replaceWith(hresponse);
                    jQuery('.rt_wufoo_form').val(rt_wufoo_obj.form);
                    jQuery('.rt_wufoo_loader').remove();
            });

        }

    });
}

/*  AJAX MANIPULATORS   */

function rtWufooImportAjax($type){
    total = jQuery('#rt_wufoo_'+$type+'_total').val();
    done = jQuery('#rt_wufoo_'+$type+'_index').val();
    remaining = total - done;
    var countreq = Math.ceil((remaining / rt_wufoo_obj.count));
    var newstartingpoint = jQuery.Deferred();
    newstartingpoint.resolve();
    for (var i = 0; i < parseInt(countreq); i++) {
        newstartingpoint = newstartingpoint.pipe(function() {
            if($type=='entry'){
                return fireEntryRequest();
            }else if($type=='comment'){
                return fireCommentRequest();
            }
        });
    }
    return true;
}
/**
 * Called before the default ajax request is fired. Useful for overriding the default request
 * Useful for import progress trigerring instead of loading the next step
 * 
 * @param {string} form the form this request is for
 * @param {object} data the form input array
 * @returns {Boolean} whether the default request should be prevented
 */
function rtWufooPreAjax(form,data){
    switch(form){
        case 'rt_wufoo_entries_import':
            return rtWufooImportAjax('entry');
            break;
        case 'rt_wufoo_comment_import':
            
            return rtWufooImportAjax('comment');
            break;
        default:
            return false;
    }
    
}

/**
 * 
 * @param {type} form
 * @param {type} data
 * @returns {rtWufooDone}
 */
function rtWufooDone(form, data){
   if(form=='rt_wufoo_api_form'){
            
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
   }else{
       jQuery('#'+form).closest('.rt_wufoo_steps').next().html(data);
   }
    
}

/**
 * 
 * @param {type} form
 * @param {type} data
 * @returns {undefined}
 */
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


jQuery(document).ready(function() {
    
    
    //Any form that is submitted
    jQuery('#rt_wufoo_wizard').on('submit','form',function(e){
        
        //assign id attribute to var
        formID = jQuery(this).attr('id');
        
        //check if this is our form
        if(formID.indexOf('rt_wufoo_')==0){
            
            
            //only if it is our form, prevent submit
            e.preventDefault();
            
            
            //get the form data
            var formData = jQuery(this).serializeArray();
            
            
            //add the necessary action to the data array/object for wp_ajax
            formData.push({ name: 'action', value : formID });
            
            
            // here's the chance for the script to decide if it wants to interfere and take over
            rt_wufoo_interference = rtWufooPreAjax(formID, formData);
            
            // if there's no interference, continue
            if(!rt_wufoo_interference){
                
                // add the loader
                rtWufooLoader(formID);
            
            //post the form
                jQuery.post( ajaxurl,formData)
                        .done(function( data ){
                            rtWufooDone(formID,data);
                            rtWufooScroll(jQuery('#'+formID).closest('.rt_wufoo_steps').next());
                            jQuery('.rt_wufoo_loader').remove();
                        })
                        .fail(function(){
                            rtWufooFail(formID);
                            jQuery('.rt_wufoo_loader').remove();
                        });
            }
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
});