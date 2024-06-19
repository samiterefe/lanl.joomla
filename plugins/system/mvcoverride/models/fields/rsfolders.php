<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */

defined('JPATH_PLATFORM') or die;

class JFormFieldRSFolders extends JFormField
{
	public $type = 'RSFolders';

	protected function getInput()
	{
		$html   = array();
		$script = array();

		if (!class_exists('RSFilesAdapterGrid'))
		{
			require_once JPATH_SITE . '/components/com_rsfiles/helpers/adapter/adapter.php';
		}

		JHtml::_('jquery.framework', true);

		JFactory::getLanguage()->load('com_rsfiles');

		// Build the script.
		$script[] = '	function jSelectFolder(path) {';
		$script[] = '		jQuery("#' . $this->id . '_id").val(path);';
		$script[] = '		jQuery("#' . $this->id . '_name").val(path);';
		$script[] = '		jQuery(\'#rsfFoldersModal\').modal(\'hide\');';
		$script[] = '	}';

		$script[] = '	function jDeselectFolder() {';
		$script[] = '		jQuery("#' . $this->id . '_id").val("");';
		$script[] = '		jQuery("#' . $this->id . '_name").val("' . JText::_('Related Files', true) . '");';
		$script[] = '	}';

		// Add the script to the document head.
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		$title = $this->value;

		if (empty($title))
		{
			$title = JText::_('Related Files');
		}
		$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

		$html[] = RSFilesAdapterGrid::inputGroup('<input type="text" class="input-large form-control" id="' . $this->id . '_name" value="' . $title . '" disabled="disabled" />', null, '<a class="btn btn-primary" title="' . JText::_('COM_RSFILES_CHANGE_DOWNLOAD_ROOT') . '"  href="javascript:void(0)" onclick="jQuery(\'#rsfFoldersModal\').modal(\'show\');"><i class="icon-file"></i> ' . JText::_('JSELECT') . '</a> <a class="btn btn-danger" title="' . JText::_('COM_RSFILES_CLEAR') . '"  href="javascript:void(0)" onclick="jDeselectFolder();"><i class="icon-remove"></i></a>');

		$class = '';
		if ($this->required)
		{
			$class = ' class="required modal-value"';
		}

		$html[] = '<input type="hidden" id="' . $this->id . '_id"' . $class . ' name="' . $this->name . '" value="' . $this->value . '" />';

		$html[] = JHtml::_('bootstrap.renderModal', 'rsfFoldersModal', array('title' => JText::_('COM_RSFILES_CONF_SET_DOWNLOAD_FOLDER'), 'url' => JRoute::_('index.php?option=com_rsfiles&view=files&layout=modal&tmpl=component&' . JSession::getFormToken() . '=1', false), 'height' => 800, 'bodyHeight' => 70));

		return implode("\n", $html);
	}
}
