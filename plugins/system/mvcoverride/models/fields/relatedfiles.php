<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */

defined('JPATH_PLATFORM') or die;

class JFormFieldRelatedFiles extends JFormField
{
	public $type = 'RelateFiles';

	protected function getInput()
	{
		$html   = array();
		$script = array();

		if (!class_exists('RSFilesAdapterGrid'))
		{
			require_once JPATH_SITE . '/components/com_rsfiles/helpers/adapter/adapter.php';
		}

		// Build the script.
		$js = <<<EOD
function jSelectFolder(path) {
    jQuery("#jform_FileRelatedToStatus_id").val(path);
    jQuery("#jform_FileRelatedToStatus_name").val(path);
    jQuery('#rsfFoldersModal').modal('hide');
}

function jDeselectFolder() {
    jQuery("#jform_FileRelatedToStatus_id").val("");
    const FileRelatedToStatus = jQuery("#jform_FileRelatedToStatus_name");
    FileRelatedToStatus.val("Related File");
    FileRelatedToStatus.removeAttr("readonly");
    FileRelatedToStatus.attr("disabled", true);
}

function rs_placeholder(what,other) {

    const FileRelatedToStatus = jQuery("#jform_FileRelatedToStatus_name");
    FileRelatedToStatus.removeAttr("disabled");
    FileRelatedToStatus.attr("readonly", true);
    FileRelatedToStatus.val(what || other);
    try {
        window.jQuery(".modal").modal("hide");
    } catch (err) {
    }
    try {
        window.jQuery(".modal").modal("hide");
    } catch (err) {
    }
    return false;
}

jQuery(document).ready(function () {

	Joomla.calendarDateSet = function()
	{
        //If date is set and file status is "Renewed" then enable the field
        let DateRelatedToStatus = jQuery('#jform_DateRelatedToStatus').attr('data-alt-value');
        let FileStatus = jQuery('#jform_FileStatus').val();
        if(DateRelatedToStatus !== '11/11/1111' && FileStatus == '1'){
            jQuery('#jform_DateRelatedToStatus').attr('disabled', false);
        }
	};

	jQuery('input[data-alt-value]').on("change", function(event, params){
		if((jQuery('#jform_DateRelatedToStatus').val() == '' && jQuery('#jform_FileStatus').val() == '1')){
			jQuery('#jform_DateRelatedToStatus').attr('disabled', true);
		}
		
	});
	
	jQuery('#filestatusid .js-btn.btn.btn-clear').click(function(){
		alert('clear');
	});
	
	//things to do as the file status drop down is selected/modified
    jQuery("#jform_FileStatus").on("change", function (event, params) {
    
        const FileStatusOptions = [0, 2, 3, 5];
        jQuery("#jform_FileRelatedToStatus_name").attr("value","Related File");
		 jQuery("#jform_DateRelatedToStatus").val('');
        //What to do if we have selected an option that requires a related file
		if ( isNaN(parseInt(event.target.value))) { 
			  jQuery("#FileRelatedToStatus_div").hide();
			 // jQuery('#rsfFoldersModal').fadeOut('fast');
		}else if(parseInt(event.target.value)==1) { // renewed
			  jQuery("#FileRelatedToStatus_div").fadeIn("fast");
			  jQuery("#filestatusid .field-calendar").fadeIn("fast");
		}else if(parseInt(event.target.value)==4) { // date only
			  
			  jQuery("#filestatusid .field-calendar").fadeIn("fast");
			  jQuery("#FileRelatedToStatus_div").hide();
		} else {
			jQuery("#FileRelatedToStatus_div").fadeIn("fast");
		}
        if (FileStatusOptions.includes(parseInt(event.target.value))) {
        
            //Hide the calendar picker and show the related file picker
            jQuery("#filestatusid .field-calendar").fadeOut("fast", function () {
                jQuery("#filestatusid .input-prepend").fadeIn("fast");
            });
            
            //Delete the calendar picker value
            jQuery('#jform_DateRelatedToStatus').attr('value','');
            
            //Disable the calendar picker so its value doesn't get submitted
            jQuery('#jform_DateRelatedToStatus').attr('disabled',true);
        } else if (parseInt(event.target.value) == 4) {
            
            //Hide the related file picker and show the calendar picker 
            jQuery("#filestatusid .input-prepend").fadeOut("fast", function () {
                jQuery("#jform_FileRelatedToStatus_name").attr("value","Related File");
                jQuery("#jform_FileRelatedToStatus_name").removeAttr("readonly");
                jQuery("#jform_FileRelatedToStatus_name").attr("disabled", true);
                jQuery("#jform_DateRelatedToStatus").attr("disabled", false);
                jQuery("#jform_DateRelatedToStatus").val('');
                jQuery("#jform_DateRelatedToStatus").attr("readonly","true");
                jQuery("#filestatusid .field-calendar").fadeIn("fast");
            });
            jQuery("#jform_FileRelatedToStatus_name").removeAttr("readonly");
            jQuery("#jform_FileRelatedToStatus_name").attr("disabled", true);
        } else if(parseInt(event.target.value) == 1){
                
                //Show the related file picker and show the calendar picker
                jQuery("#jform_DateRelatedToStatus").val('');
                jQuery("#filestatusid .input-prepend").fadeIn("fast", function () {
                jQuery("#jform_FileRelatedToStatus_name").removeAttr("readonly");
                jQuery("#jform_FileRelatedToStatus_name").attr("disabled", true);
                jQuery("#jform_DateRelatedToStatus").attr("disabled", true);
                jQuery("#filestatusid .field-calendar").fadeIn("fast");
                
            });
            jQuery("#jform_FileRelatedToStatus_name").removeAttr("readonly");
            jQuery("#jform_FileRelatedToStatus_name").attr("disabled", true);
        } else {
            jQuery("#filestatusid .input-prepend").fadeOut("fast", function () {
                jQuery("#jform_FileRelatedToStatus_name").attr("value","Related File");
            });
            jQuery("#jform_FileRelatedToStatus_name").removeAttr("readonly");
            jQuery("#jform_FileRelatedToStatus_name").attr("disabled", true);
            jQuery("#filestatusid .field-calendar").fadeOut("fast");
            jQuery("#jform_DateRelatedToStatus").attr("disabled", true);
        }
    })
    //Form Validation
    document.formvalidator.setHandler("relatedfile", function (value) {
        const fileStatus = jQuery("#jform_FileStatus").val();
        const fileRelatedToStatus = jQuery("#jform_FileRelatedToStatus_name").val();
        const dateRelatedToStatus = jQuery("#jform_DateRelatedToStatus").val();
        jQuery("#jform_DateRelatedToStatus").blur(()=>{alert('blur')});
        //If a status that requires a related file doesn't have one then alert error
        if ((fileStatus == '0' || fileStatus == '1' || fileStatus == '2' || fileStatus == '5') && (fileRelatedToStatus === "Related File")) {
            alert("Please select a Related File");
            return false;
        } else if (fileStatus == '4' && dateRelatedToStatus == '') {
            alert("Please select a Date");
            return false;
        } else {
            return true
        }
    });
});
EOD;


		$css = '
		#filestatusid .input-prepend {
            margin-left : 10px;
            display: none;
		}
		
		#filestatusid .field-calendar {
			margin-left: 10px;
			display: none;
		}
		';

		// Add styles
		JFactory::getDocument()->addStyleDeclaration($css);

		// Add the script to the document head.
		JFactory::getDocument()->addScriptDeclaration($js);

		$title = $this->value;

		if (empty($title))
		{
			$title = JText::_('Related File');
		}
		$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

		$html[] = RSFilesAdapterGrid::inputGroup('<input type="text" class="input-large form-control" id="' . $this->id . '_name" name="' . $this->name . '" value="' . $this->value . '" disabled="disabled" />', null, '<a class="btn btn-primary" title="' . JText::_('COM_RSFILES_CHANGE_DOWNLOAD_ROOT') . '"  href="javascript:void(0)" onclick="jQuery(\'#rsfFoldersModal\').modal(\'show\');"><i class="icon-file"></i> ' . JText::_('JSELECT') . '</a> <a class="btn btn-danger" title="' . JText::_('COM_RSFILES_CLEAR') . '"  href="javascript:void(0)" onclick="jDeselectFolder();"><i class="icon-remove"></i></a>');

		$class = '';
		if ($this->required)
		{
			$class = ' class="required modal-value"';
		}

		//$html[] = '<input type="hidden" id="' . $this->id . '_id"' . $class . ' name="' . $this->name . '" value="' . $this->value . '" />';

		$html[] = JHtml::_('bootstrap.renderModal', 'rsfFoldersModal', array('title' => JText::_('COM_RSFILES_CONF_SET_DOWNLOAD_FOLDER'), 'url' => JRoute::_('index.php?option=com_rsfiles&view=files&layout=modal&from=editor&tmpl=component&' . JSession::getFormToken() . '=1', false), 'height' => 800, 'bodyHeight' => 70));

		return implode("\n", $html);
	}
}
