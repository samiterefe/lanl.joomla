<?php

namespace TCM\Component\RSFilesReports\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;

\defined('_JEXEC') or die;

class DownloadedController extends ApiController
{
	protected $contentType = 'downloaded';

	protected $default_view = 'downloaded';

	function __construct()
	{
		parent::__construct();
	}

	public function getDocuments(): void
	{
		try
		{
			$db = Factory::getContainer()->get('DatabaseDriver');

			$input     = Factory::getApplication()->input;
			$startDate = $db->escape($input->getString('startDate', ''));
			$endDate   = $db->escape($input->getString('endDate', ''));

			// Set default dates if not provided
			if (empty($startDate))
			{
				$startDate = '1970-01-01';
			}
			if (empty($endDate))
			{
				$endDate = date('Y-m-d');
			}

			// Subquery for total views with date range
			$subQueryViews = $db->getQuery(true);
			$subQueryViews
				->select($db->qn('file_id'))
				->select('COUNT(file_id) as total_views')
				->from($db->qn('#__lanl_rsfiles_viewed'))
				->where($db->qn('date_viewed') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->q('file_id'));

			// Subquery for total downloads with date range
			$subQueryDownloads = $db->getQuery(true);
			$subQueryDownloads
				->select($db->qn('file_id'))
				->select('COUNT(file_id) as total_downloads')
				->from($db->qn('#__lanl_rsfiles_downloaded'))
				->where($db->qn('date_downloaded') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->q('file_id'));

			// Main query to fetch files data
			$query = $db->getQuery(true);
			$query
				->select($db->qn(['f.IdFile', 'f.FileName', 'f.FilePath', 'f.DateAdded']))
				->select('COALESCE(h.total_views, 0) as total_views')
				->select('COALESCE(dl.total_downloads, 0) as total_downloads')
				->from($db->qn('#__rsfiles_files', 'f'))
				->leftJoin('(' . $subQueryViews . ') AS h ON h.file_id = f.IdFile')
				->leftJoin('(' . $subQueryDownloads . ') AS dl ON dl.file_id = f.IdFile');

			// Apply sorting
			$sortBy = $db->escape($input->getString('sortBy', ''));
			if ($sortBy == 'mostViewed')
			{
				$query->order($db->qn('total_views') . ' DESC');
			}
			elseif ($sortBy == 'mostDownloaded')
			{
				$query->order($db->qn('total_downloads') . ' DESC');
			}

			$db->setQuery($query);
			$files = $db->loadObjectList();

			// Process the results to adjust FileName and Category
			foreach ($files as $file)
			{
				$filePath     = $file->FilePath;
				$lastSlashPos = strrpos($filePath, '/');

				if ($lastSlashPos !== false)
				{
					$fileName     = substr($filePath, $lastSlashPos + 1);
					$categoryPath = substr($filePath, 0, $lastSlashPos);
					$lastDotPos   = strrpos($fileName, '.');

					// Check if the fileName contains a dot and thus a file extension
					if ($lastDotPos === false)
					{
						$file->FileName = '';
						$file->Category = $fileName;  // Set Category to the name after the last "/" when no extension
					}
					else
					{
						$file->FileName = $fileName;

						// Extract the Category
						$secondLastSlashPos = strrpos($categoryPath, '/');
						if ($secondLastSlashPos !== false)
						{
							$file->Category = substr($categoryPath, $secondLastSlashPos + 1);
						}
						else
						{
							$file->Category = $categoryPath;
						}
					}
				}
				else
				{
					$file->FileName = '';
					$file->Category = '';
				}
			}

			echo new JsonResponse($files);
			$this->app->close();
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
			$this->app->close();
		}
	}

	public function saveDownloaded(): void
	{
		try
		{
			$input               = $this->app->input;
			$fileId              = $input->getInt('file_id');
			$downloaderIpAddress = $input->server->get('REMOTE_ADDR');
			$downloaderCountry   = $this->getCountryFromIp($downloaderIpAddress);

			if (!$downloaderCountry)
			{
				$downloaderCountry = 'Unknown';
			}

			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->insert($db->quoteName('#__lanl_rsfiles_downloaded'))
				->columns(array($db->quoteName('file_id'), $db->quoteName('downloader_ip_address'), $db->quoteName('downloader_country'), $db->quoteName('date_downloaded')))
				->values(implode(',', array($db->quote($fileId), $db->quote($downloaderIpAddress), $db->quote($downloaderCountry), 'NOW()')));

			$db->setQuery($query);
			$db->execute();

			echo new JsonResponse(array('success' => true));
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
