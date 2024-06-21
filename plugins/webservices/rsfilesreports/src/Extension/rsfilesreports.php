<?php
namespace TCM\Plugin\Webservices\RSFilesReports;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Router\Route;

\defined('_JEXEC') or die;

class PlgWebservicesRSFilesReports extends CMSPlugin
{
	protected $autoloadLanguage = true;

	public function onBeforeApiRoute(&$router): void
	{
		$defaults    = ['public' => true,'component' => 'com_rsfilesreports'];
		$routes = [
			new Route(['GET'],'v1/rsfilesreports/downloaded', 'downloaded.getDocuments',[], $defaults),
			new Route(['POST'],'v1/rsfilesreports/downloaded', 'downloaded.saveDownloaded',[], $defaults),
			new Route(['GET'],'v1/rsfilesreports/viewed', 'viewed.getDocuments',[], $defaults),
			new Route(['POST'],'v1/rsfilesreports/viewed', 'viewed.saveViewed',[], $defaults),
			new Route(['GET'],'v1/rsfilesreports/categories', 'categories.getRsCategory',[], $defaults),
			new Route(['GET'],'v1/rsfilesreports/country/from/ip/:ip', 'country.getCountryFromIp',[], $defaults),
		];

		$router->addRoutes($routes);
	}
}
