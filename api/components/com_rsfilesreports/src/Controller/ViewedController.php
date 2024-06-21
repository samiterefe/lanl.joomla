<?php

namespace TCM\Component\RSFilesReports\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;

\defined('_JEXEC') or die;

class ViewedController extends ApiController
{
	protected $contentType = 'viewed';

	protected $default_view = 'viewed';

	public function saveViewed(): void
	{
		try
		{
			$input           = $this->app->input;
			$fileId          = $input->getInt('file_id');
			$viewerIpAddress = $input->server->get('REMOTE_ADDR');
			$viewerCountry   = $this->getCountryFromIp($viewerIpAddress);

			if (!$viewerCountry)
			{
				$viewerCountry = 'Unknown';
			}

			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->insert($db->qn('#__lanl_rsfiles_viewed'))
				->columns(array($db->qn('file_id'), $db->qn('viewer_ip_address'), $db->qn('viewer_country'), $db->qn('date_viewed')))
				->values(implode(',', array($db->q($fileId), $db->q($viewerIpAddress), $db->q($viewerCountry), 'NOW()')));

			$db->setQuery($query);
			$result = $db->execute();

			echo new JsonResponse(array('success' => true));
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
			$this->app->close();
		}
	}

	public function getCountryFromIp($ipAddress): ?string
	{
		try
		{
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);
			$query
				->select($db->qn('country_code'))
				->from($db->qn('#__rsfilesreports_ip_to_country'))
				->where($db->q($ipAddress) . ' BETWEEN ip_start AND ip_end');

			$db->setQuery($query);

			return $db->loadResult();
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
			$this->app->close();
			return null;
		}
	}
}
