<?php
//namespace TCM\Component\RsfilesReports;
class RSfilesReportsViewCategories extends JViewLegacy
{
	public function display($tpl = null)
	{
		JToolBarHelper::title(JText::_('Categories Report'), 'categories');
		echo '<div id="app"></div>';
	}
}
