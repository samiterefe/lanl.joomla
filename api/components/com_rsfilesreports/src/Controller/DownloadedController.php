<?php


namespace TCM\Component\Rsfilesreports\Administrator\Api\Controller;

use Joomla\CMS\MVC\Controller\ApiController;

\defined('_JEXEC') or die;

class DownloadedController extends ApiController
{
	protected $contentType = 'downloaded';

	protected $default_view = 'downloaded';

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
}
