<?php
//namespace TCM\Component\RsfilesReports;
class RSfilesReportsViewDocuments extends JViewLegacy
{
	public function display($tpl = null)
	{
		JToolBarHelper::title(JText::_('Documents Report'), 'documents');
		echo '<div id="app"></div>';
	}
}
