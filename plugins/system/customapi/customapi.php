<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Response\JsonResponse;

/** @noinspection PhpUnused */
class PlgSystemCustomapi extends CMSPlugin
{
	public function onAfterRoute(): void
	{
		try
		{
			$app = Factory::getApplication();

			// Check if it's the frontend and a custom API request
			if ($app->isClient('site') && $app->input->get('customapi', false))
			{
				$this->addCorsHeaders();
				$this->handleApiRequest();
				$app->close();
			}
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
		}
	}

	protected function addCorsHeaders(): void
	{
		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
	}

	protected function handleApiRequest(): void
	{
		try{
			$input = Factory::getApplication()->input;
			$task  = $input->getCmd('task');

			switch ($task)
			{
				case 'getDocuments':
					$this->getDocuments();
					break;
				case 'getRsCategory':
					$this->getRsCategory();
					break;
				case 'saveViewed':
					$this->saveViewed();
					break;
				case 'saveDownloaded':
					$this->saveDownloaded();
					break;
				default:
					echo new JsonResponse(null, 'Invalid task', true);
					break;
			}
		} catch (Exception $e) {
			echo new JsonResponse(null, $e->getMessage(), true);
		}
	}

	protected function getDocuments(): void
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

			// Subquery for total hits with date range
			$subQueryHits = $db->getQuery(true);
			$subQueryHits
				->select($db->qn(array('file_id', 'COUNT(file_id) AS totalHits')))
				->from($db->qn('#__lanl_rsfiles_viewed'))
				->where($db->qn('date_viewed') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->q('file_id'));

			// Subquery for total downloads with date range
			$subQueryDownloads = $db->getQuery(true);
			$subQueryDownloads
				->select($db->qn(array('file_id', 'COUNT(file_id) AS totalDownloads')))
				->from($db->qn('#__lanl_rsfiles_downloaded'))
				->where($db->qn('date_downloaded') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->q('file_id'));

			// Main query to fetch files data
			$query = $db->getQuery(true)
				->select('f.IdFile, f.FileName, f.FilePath, f.DateAdded, 
                      COALESCE(h.totalHits, 0) AS totalHits, 
                      COALESCE(dl.totalDownloads, 0) AS totalDownloads')
				->from($db->qn('lanl4_rsfiles_files', 'f'))
				->leftJoin('(' . $subQueryHits . ') AS h ON h.file_id = f.IdFile')
				->leftJoin('(' . $subQueryDownloads . ') AS dl ON dl.file_id = f.IdFile');

			// Apply sorting
			$sortBy = $db->escape($input->getString('sortBy', ''));
			if ($sortBy == 'mostViewed')
			{
				$query->order($db->qn('totalHits') . ' DESC');
			}
			elseif ($sortBy == 'mostDownloaded')
			{
				$query->order($db->qn('totalDownloads') . ' DESC');
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
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
		}
	}

	protected function getRsCategory(): void
	{
		try
		{
			$db        = Factory::getContainer()->get('DatabaseDriver');
			$input     = Factory::getApplication()->input;
			$startDate = $db->escape($input->getString('startDate', '1970-01-01'));
			$endDate   = $db->escape($input->getString('endDate', date('Y-m-d') . ' 23:59:59'));
			$sortBy    = $db->escape($input->getString('sortBy', ''));

			// Subquery for total hits with date range
			$subQueryHits = $db->getQuery(true)
				->select($db->qn(array('file_id, COUNT(file_id) AS totalHits')))
				->from($db->qn('#__lanl_rsfiles_viewed'))
				->where('date_viewed BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->q('file_id'));

			// Subquery for total downloads with date range
			$subQueryDownloads = $db->getQuery(true)
				->select('file_id, COUNT(file_id) AS totalDownloads')
				->from($db->qn('#__lanl_rsfiles_downloaded'))
				->where($db->qn('date_downloaded') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group('file_id');

			// Main query to fetch files data
			$query = $db->getQuery(true)
				->select('f.FilePath, 
                      COALESCE(SUM(h.totalHits), 0) AS totalHits, 
                      COALESCE(SUM(dl.totalDownloads), 0) AS totalDownloads')
				->from($db->qn('#__rsfiles_files', 'f'))
				->leftJoin('(' . $subQueryHits . ') AS h ON h.file_id = f.IdFile')
				->leftJoin('(' . $subQueryDownloads . ') AS dl ON dl.file_id = f.IdFile')
				->group('f.FilePath');

			// Apply sorting
			if ($sortBy == 'mostViewed')
			{
				$query->order('totalHits DESC');
			}
			elseif ($sortBy == 'mostDownloaded')
			{
				$query->order('totalDownloads DESC');
			}

			$db->setQuery($query);
			$files = $db->loadAssocList();

			// Aggregate categories
			$categoryMap = [];

			foreach ($files as $file)
			{
				$filePath     = $file['FilePath'];
				$lastSlashPos = strrpos($filePath, '/');

				if ($lastSlashPos !== false)
				{
					$fileName     = substr($filePath, $lastSlashPos + 1);
					$categoryPath = substr($filePath, 0, $lastSlashPos);
					$lastDotPos   = strrpos($fileName, '.');

					// Check if the fileName contains a dot and thus a file extension
					if ($lastDotPos === false)
					{
						$categoryName = $fileName;  // Set Category to the name after the last "/" when no extension
					}
					else
					{
						// Extract the Category
						$secondLastSlashPos = strrpos($categoryPath, '/');
						if ($secondLastSlashPos !== false)
						{
							$categoryName = substr($categoryPath, $secondLastSlashPos + 1);
						}
						else
						{
							$categoryName = $categoryPath;
						}
					}

					// Skip if category name is empty
					if (empty($categoryName))
					{
						continue;
					}

					// Aggregate totals
					if (isset($categoryMap[$categoryName]))
					{
						$categoryMap[$categoryName]['totalHits']      += (int) $file['totalHits'];
						$categoryMap[$categoryName]['totalDownloads'] += (int) $file['totalDownloads'];
					}
					else
					{
						$categoryMap[$categoryName] = [
							'Category'       => $categoryName,
							'totalHits'      => (int) $file['totalHits'],
							'totalDownloads' => (int) $file['totalDownloads']
						];
					}
				}
			}

			// Convert map to list
			$aggregatedCategories = array_values($categoryMap);

			echo new JsonResponse($aggregatedCategories);
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
		}
	}

	protected function saveViewed(): void
	{
		try
		{
			$input           = Factory::getApplication()->input;
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
			$db->execute();

			echo new JsonResponse(array('success' => true));
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
		}
	}

	protected function saveDownloaded(): void
	{
		try
		{
			$input               = Factory::getApplication()->input;
			$fileId              = $input->getInt('file_id');
			$downloaderIpAddress = $input->server->get('REMOTE_ADDR');
			$downloaderCountry   = $this->getCountryFromIp($downloaderIpAddress);

			if (!$downloaderCountry)
			{
				$downloaderCountry = 'Unknown';
			}

			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->insert($db->qn('#__lanl_rsfiles_downloaded'))
				->columns(array($db->qn('file_id'), $db->qn('downloader_ip_address'), $db->qn('downloader_country'), $db->qn('date_downloaded')))
				->values(implode(',', array($db->q($fileId), $db->q($downloaderIpAddress), $db->q($downloaderCountry), 'NOW()')));

			$db->setQuery($query);
			$db->execute();

			echo new JsonResponse(array('success' => true));
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
		}
	}

	protected function getCountryFromIp($ipAddress): string
	{
		try
		{
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);

			$query->select('country_code')
				->from($db->qn('ip_to_country'))
				->where($db->q($ipAddress) . ' BETWEEN ip_start AND ip_end');

			$db->setQuery($query);

			return $db->loadResult();
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
			return 'Unknown';
		}
	}
}
