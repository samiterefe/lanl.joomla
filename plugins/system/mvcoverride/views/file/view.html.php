<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');

class rsfilesViewFile extends JViewLegacy
{
	public function display($tpl = null)
	{
		$plugin               = JPluginHelper::getPlugin('system', 'mvcoverride');
		$params               = new JRegistry($plugin->params);
		$useTemplateOverrides = $params->get('useTemplateOverrides', 0);
		if (!$useTemplateOverrides)
		{
			JViewLegacy::addTemplatePath(JPATH_ROOT . '/plugins/system/mvcoverride/overrides');
		}
		$this->form      = $this->get('Form');
		$this->item      = $this->get('Item');
		$this->tabs      = $this->get('Tabs');
		$this->layouts   = $this->get('Layouts');
		$this->downloads = $this->get('Downloads');
		$this->fieldsets = $this->form->getFieldsets();
		$this->type      = rsfilesHelper::getType($this->item->IdFile);
		$this->briefcase = rsfilesHelper::getRoot() == 'briefcase';

		if ($this->type != 'folder')
		{
			$this->mirrors     = $this->get('Mirrors');
			$this->screenshots = $this->get('Screenshots');
		}

		$this->addToolBar();
		parent::display($tpl);
	}

	protected function addToolBar()
	{
		if (!rsfilesHelper::isJ4())
		{
			JHtml::_('formbehavior.chosen', 'select');
		}

		if ($this->type == 'folder')
		{
			JToolBarHelper::title(JText::_('COM_RSFILES_ADD_EDIT_FOLDER', 'rsfiles'));
		}
		else
		{
			JToolBarHelper::title(JText::_('COM_RSFILES_ADD_EDIT_FILE', 'rsfiles'));
		}

		JToolBarHelper::apply('file.apply');
		JToolBarHelper::save('file.save');
		JToolBarHelper::cancel('file.cancel');

		if ($this->item->itemType == 'file')
		{
			$layout = new JLayoutFile('joomla.toolbar.standard');
			$dhtml  = $layout->render(array('text' => JText::_('COM_RSFILES_RENAME'), 'btnClass' => 'btn', 'id' => '', 'htmlAttributes' => '', 'onclick' => 'jQuery(\'#rsfRename\').modal(\'show\');', 'class' => 'icon-refresh', 'doTask' => 'jQuery(\'#rsfRename\').modal(\'show\');', 'listCheck' => false));
			JToolbar::getInstance('toolbar')->appendButton('Custom', $dhtml, 'refresh');
		}
	}
}
