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
			$input             = $this->app->input;
			$viewer_ip_address = $input->server->get('REMOTE_ADDR');
			$viewer_country    = $this->getCountryFromIp($viewer_ip_address);
			(int) $fileId = $input->json->get('file_id');
			//@todo use form token for more security

			if (!is_numeric($fileId) || $fileId < 1)
			{
				throw new Exception('File ID is missing');
			}

			if (!$viewer_country)
			{
				$viewer_country = 'Unknown';
			}

			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->insert($db->qn('#__lanl_rsfiles_viewed'))
				->columns(array($db->qn('file_id'), $db->qn('viewer_ip_address'), $db->qn('viewer_country'), $db->qn('date_viewed')))
				->values(implode(',', array($db->q($fileId), $db->q($viewer_ip_address), $db->q($viewer_country), 'NOW()')));

			$db->setQuery($query);
			if (!$db->execute())
			{
				throw new Exception($db->getErrorMsg());
			}

			echo new JsonResponse();
			$this->app->close();
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
