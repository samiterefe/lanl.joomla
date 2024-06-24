<?php

namespace TCM\Plugin\WebServices\RSFilesReports\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Router\Route;

defined('_JEXEC') or die;

class RSFilesReports extends CMSPlugin
{
	protected $autoloadLanguage = true;

	/**
	 * Registers com_rsfilesreports's API's routes in the application
	 *
	 * @param   ApiRouter  &$router  The API Routing object
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function onBeforeApiRoute(&$router): void
	{
		$defaults = ['public' => true, 'component' => 'com_rsfilesreports'];
		$routes   = [
			new Route(['GET'], 'v1/rsfilesreports/get/documents/info', 'downloaded.getDocumentsInfo', [], $defaults),
			new Route(['POST'], 'v1/rsfilesreports/save/document/downloaded', 'downloaded.saveDownloaded', [], $defaults),
			new Route(['POST'], 'v1/rsfilesreports/save/document/viewed', 'viewed.saveViewed', [], $defaults),
			new Route(['GET'], 'v1/rsfilesreports/get/categories/info', 'categories.getCategoriesInfo', [], $defaults),
		];

		$router->addRoutes($routes);
	}
}
