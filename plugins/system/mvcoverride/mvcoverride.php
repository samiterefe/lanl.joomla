<?php
//@todo need to get the files from the new version of RSFiles
// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;


/**
 * PlgSystemMVCOverride class.
 *
 * @extends CMSPlugin
 */
class PlgSystemMVCOverride extends CMSPlugin
{
	//use the onAfterInitialise trigger because it's the first Joomla trigger and we need to load our override as soon as possible before the one we are trying to override is loaded
	public function onAfterRoute()
	{
		$app = Factory::getApplication();
		$sef = Factory::getConfig()->get('sef');


		$option = $app->input->get('option');
		$view   = $app->input->get('view', 'rsfiles');
		$layout = $app->input->get('layout');


		if ($app->isClient('administrator') and $option == 'com_rsfiles' and $view == 'file')
		{
			require_once(JPATH_ROOT . '/plugins/system/mvcoverride/models/file.php');
			require_once(JPATH_ROOT . '/plugins/system/mvcoverride/views/file/view.html.php');
		}
		if ($app->isClient('administrator') and $option == 'com_rsfiles' and $view == 'files' and ($layout == 'edit' || $layout == 'modal'))
		{
			require_once(JPATH_ROOT . '/plugins/system/mvcoverride/views/files/view.html.php');
		}
		if ($app->isClient('site') and $option == 'com_rsfiles' and ($view == 'rsfiles' or $layout == 'download'))
        {
            require_once(JPATH_ROOT . '/plugins/system/mvcoverride/models/rsfiles.php');
            require_once(JPATH_ROOT . '/plugins/system/mvcoverride/views/rsfiles/view.html.php');
        }
	}

	public function &onUserBeforeDataValidation($form, &$data)
	{
		if ($data['DateRelatedToStatus'])
		{
			$data['DateRelatedToStatus'] = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $data['DateRelatedToStatus'])));
		}
	}
}
