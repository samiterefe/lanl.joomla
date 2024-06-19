<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');

foreach ($this->form->getFieldset('general') as $field)
{
	$extension = strtolower(rsfilesHelper::getExt($this->item->FilePath));

	$extra = '';
	if ($field->fieldname == 'FilePath')
	{
		$class = $this->item->type == 'local' ? 'far fa-hdd' : 'fas fa-external-link-alt';
		$extra = ' <span class="rs_extra"><i class="hasTooltip ' . $class . '" title="' . JText::_(strtoupper('COM_RSFILES_FILE_TYPE_' . $this->item->type)) . '"></i></span>';
	}

	if ($this->type != 'external' && $field->fieldname == 'DownloadName')
	{
		continue;
	}

	if ($this->briefcase)
	{
		if ($this->type == 'folder')
		{
			if (in_array($field->fieldname, array('publish_down', 'FileStatistics', 'FileVersion', 'IdLicense', 'DownloadMethod', 'DownloadLimit', 'tags')))
				continue;
		}
		else
		{
			if ($field->fieldname == 'publish_down' || $field->fieldname == 'FileStatistics' || $field->fieldname == 'DownloadMethod' || $field->fieldname == 'tags')
				continue;
		}
	}
	else
	{
		// Dont show specific fields if the path is a folder
		if ($this->type == 'folder' && in_array($field->fieldname, array('FileStatistics', 'FileVersion', 'IdLicense', 'DownloadMethod', 'DownloadLimit', 'show_preview', 'tags')))
			continue;

		if ($this->type == 'folder' && $field->fieldname == 'publish_down')
			continue;
	}

	if ($field->fieldname == 'FileStatus')

	{
		$FileStatus = $field->value;
		echo '<div id="filestatusid" class="control-group" style="display: flex">';
	}
	$input = $extra ? RSFilesAdapterGrid::inputGroup($field->input, null, $extra) : $field->input;

	if ($field->fieldname == 'FileRelatedToStatus')
	{
		if ($field->value !== '' && in_array($FileStatus, array('0', '1', '2', '3', '5'), true))
		{
			//load javascript to display field
			if ($field->value !== 'Related File')
			{
				$js = '
                 jQuery(document).ready(function(){
                 jQuery("#filestatusid .input-prepend").fadeIn("fast", function(){});
                 jQuery("#jform_FileRelatedToStatus_name").attr("disabled", false);
                 jQuery("#jform_FileRelatedToStatus_name").attr("readonly", true);
            });
            ';
			}
			else
			{
				$js = '
			     jQuery(document).ready(function(){
                 jQuery("#filestatusid .input-prepend").fadeIn("fast", function(){});
                 jQuery("#jform_FileRelatedToStatus_name").attr("disabled", true);
                 jQuery("#jform_FileRelatedToStatus_name").attr("readonly", false);
            });
			   ';
			}
			JFactory::getDocument()->addScriptDeclaration($js);
		}
			echo '<div id="FileRelatedToStatus_div"   style="width:30%; margin-left:5px;">';
		echo $input;
		echo '</div>';
		//echo RSFilesAdapterGrid::renderField($field->label, $input, false, JText::_($field->description));
	}
    elseif ($field->fieldname == 'DateRelatedToStatus')
	{
		if ($field->value == '11/11/1111')
		{
			$field->value = '';
		}
		{
			if ($FileStatus === '' )
			{
				//load javascript to display field
				$js = '
				jQuery(document).ready(function(){
					  jQuery("#FileRelatedToStatus_div").fadeOut("fast");
					 jQuery("#filestatusid .field-calendar").fadeOut();
					
				});
				 
            ';
				JFactory::getDocument()->addScriptDeclaration($js);
			}else if ($FileStatus == '1'  && $field->value !== '' )
			{
				//load javascript to display field
				$js = '
            jQuery(document).ready(function(){
				  jQuery("#FileRelatedToStatus_div").fadeIn("fast");
                 jQuery("#filestatusid .field-calendar").fadeIn("fast", function(){
                 jQuery("#jform_DateRelatedToStatus").attr("readonly", "readonly");});
            });
            ';
				JFactory::getDocument()->addScriptDeclaration($js);
			}else if ($FileStatus == '4'     )
			{
				//load javascript to display field
				$js = '
            jQuery(document).ready(function(){
				    jQuery("#FileRelatedToStatus_div").fadeOut("fast");
                 jQuery("#filestatusid .field-calendar").fadeIn("fast", function(){
                 jQuery("#jform_DateRelatedToStatus").attr("readonly", "readonly");});
            });
            ';
				JFactory::getDocument()->addScriptDeclaration($js);
			}
            elseif ($FileStatus == '1' && $field->value == '')
			{
				$js = '
            jQuery(document).ready(function(){
				  jQuery("#FileRelatedToStatus_div").fadeIn("fast");
                 jQuery("#jform_DateRelatedToStatus").attr("disabled", true);
                 jQuery("#jform_DateRelatedToStatus").attr("value", "");
                 jQuery("#filestatusid .field-calendar").fadeIn("fast", function(){});
            });
            ';
				JFactory::getDocument()->addScriptDeclaration($js);
			}
			else
			{
				$js = '
            jQuery(document).ready(function(){
				 jQuery("#FileRelatedToStatus_div").fadeIn("fast");
                 jQuery("#jform_DateRelatedToStatus").attr("disabled", true);
                 jQuery("#jform_DateRelatedToStatus").attr("value", "");
            });
            ';
				JFactory::getDocument()->addScriptDeclaration($js);
			}
		}
		echo $input;
		echo '</div>';
	}
	else
	{
		echo RSFilesAdapterGrid::renderField($field->label, $input, false, JText::_($field->description));
	}

	if ($field->fieldname == 'FilePath' && $this->type != 'folder')
	{
		$icon = 'fa fa-file';
		$ext  = $this->item->icon ? $this->item->icon : $extension;

		if (in_array($ext, rsfilesHelper::fileExtensions()))
		{
			$icon = 'flaticon-' . $ext . '-file';
		}

		echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_ICON'), '<button type="button" onclick="jQuery(\'#rsfIcon\').modal(\'show\');" class="btn btn-success" id="file_icon"><i id="rsfiles-icon" class="' . $icon . '"></i></button>');
	}

	if ($field->fieldname == 'DownloadLimit')
	{
		if ($this->type == 'file' && !$this->briefcase)
		{
			$allowed = array('mp3', 'ogg', 'mp4', 'mov', 'webm');

			if (in_array($extension, $allowed))
			{
				if (empty($this->item->poster) || !file_exists(JPATH_SITE . '/components/com_rsfiles/images/poster/' . $this->item->poster))
				{
					echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_POSTER'), '<input type="file" id="poster" class="form-control" name="poster" size="50" />', false, JText::_('COM_RSFILES_FILE_POSTER_DESC'));
				}
				else
				{
					$poster = JHTML::_('image', JURI::root() . 'components/com_rsfiles/images/poster/' . $this->item->poster . '?sid=' . rand(), '', array('width' => 200, 'class' => 'rsf_thumb', 'style' => 'vertical-align: middle'));
					$poster .= ' <a href="' . JRoute::_('index.php?option=com_rsfiles&task=file.deleteposter&id=' . $this->item->IdFile) . '">';
					$poster .= '<i class="fa fa-trash"></i>';
					$poster .= '</a>';

					echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_POSTER'), $poster, false, JText::_('COM_RSFILES_FILE_POSTER_DESC'));
				}
			}
		}

		if (empty($this->item->FileThumb) || !file_exists(JPATH_SITE . '/components/com_rsfiles/images/thumbs/files/' . $this->item->FileThumb))
		{
			echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_THUMB'), '<input type="file" id="thumb" class="form-control" name="thumb" size="50" />', false, JText::_('COM_RSFILES_FILE_THUMB_DESC'));
		}
		else
		{
			$thumb = JHTML::_('image', JURI::root() . 'components/com_rsfiles/images/thumbs/files/' . $this->item->FileThumb . '?sid=' . rand(), '', array('class' => 'rsf_thumb', 'style' => 'vertical-align: middle'));
			$thumb .= ' <a href="' . JRoute::_('index.php?option=com_rsfiles&task=file.deletethumb&id=' . $this->item->IdFile) . '">';
			$thumb .= '<i class="fa fa-trash"></i>';
			$thumb .= '</a>';

			echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_THUMB'), $thumb, false, JText::_('COM_RSFILES_FILE_THUMB_DESC'));
		}

		if ($this->type != 'folder')
		{
			if (empty($this->item->preview) || !file_exists(JPATH_SITE . '/components/com_rsfiles/images/preview/' . $this->item->preview))
			{
				$preview = '<input type="file" id="preview" name="preview" class="form-control" size="50" /> <br>';
				$preview .= RSFilesAdapterGrid::inputGroup('<input type="text" value="200" class="input-mini form-control" size="5" name="resize_width" />', '<span class="input-group-text"><label class="checkbox inline" for="resize"><input type="checkbox" id="resize" name="resize" value="1" /> ' . JText::_('COM_RSFILES_FILE_PREVIEW_RESIZE') . '</label></span>', 'px');

				echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_PREVIEW'), $preview, false, JText::sprintf('COM_RSFILES_FILE_PREVIEW_DESC', rsfilesHelper::previewExtensions(true)));
			}
			else
			{
				$properties = rsfilesHelper::previewProperties($this->item->IdFile);
				$preview    = '<a href="javascript:void(0);" onclick="rsfiles_show_preview(\'' . JRoute::_('index.php?option=com_rsfiles&task=preview&tmpl=component&id=' . $this->item->IdFile, false) . '\');">' . JText::_('COM_RSFILES_FILE_PREVIEW') . '</a>';
				$preview    .= ' / <a href="' . JRoute::_('index.php?option=com_rsfiles&task=file.deletepreview&id=' . $this->item->IdFile) . '">' . JText::_('COM_RSFILES_DELETE') . '</a>';

				echo RSFilesAdapterGrid::renderField(JText::_('COM_RSFILES_FILE_PREVIEW'), $preview, false, JText::sprintf('COM_RSFILES_FILE_PREVIEW_DESC', rsfilesHelper::previewExtensions(true)));
			}
		}
	}
}

echo JHtml::_('bootstrap.renderModal', 'rsfPreviewModal', array('title' => JText::_('COM_RSFILES_FILE_PREVIEW'), 'bodyHeight' => 70));
if ($this->type != 'folder')
{
	echo JHtml::_('bootstrap.renderModal', 'rsfIcon', array('title' => JText::_('COM_RSFILES_SELECT_FILE_ICON'), 'bodyHeight' => 70, 'modalWidth' => 40), $this->loadTemplate('icon'));
}
?>

<script type="text/javascript">
    function rsfiles_show_preview(url) {
        jQuery('#rsfPreviewModal .modal-body img').remove();
        jQuery('#rsfPreviewModal .modal-body').prepend('<img class="rsfiles-image-modal" src="' + url + '" />');
        jQuery('#rsfPreviewModal').modal('show');
    }
</script>