<?php

namespace TCM\Component\RSFilesReports\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;

defined('_JEXEC') or die;

class CategoriesController extends ApiController
{
	protected $contentType = 'categories';
	protected $default_view = 'categories';

	public function getCategoriesInfo(): void
	{
		try
		{
			$db        = Factory::getContainer()->get('DatabaseDriver');
			$input     = $this->app->input;
			$startDate = $db->escape($input->getString('startDate', '1970-01-01'));
			$endDate   = $db->escape($input->getString('endDate', date('Y-m-d') . ' 23:59:59'));
			$sortBy    = $db->escape($input->getString('sortBy', 'mostViewed')); // Default to 'mostViewed'

			// Subquery for total views with date range
			$subQueryViews = $db->getQuery(true);
			$subQueryViews
				->select($db->qn('file_id'))
				->select('COUNT(file_id) as total_views')
				->from($db->qn('#__lanl_rsfiles_viewed'))
				->where($db->qn('date_viewed') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->qn('file_id'));

			// Subquery for total downloads with date range
			$subQueryDownloads = $db->getQuery(true);
			$subQueryDownloads
				->select($db->qn('file_id'))
				->select('COUNT(file_id) as total_downloads')
				->from($db->qn('#__lanl_rsfiles_downloaded'))
				->where($db->qn('date_downloaded') . ' BETWEEN ' . $db->q($startDate) . ' AND ' . $db->q($endDate))
				->group($db->qn('file_id'));

			// Main query to fetch files data
			$query = $db->getQuery(true)
				->select('f.FilePath')
				->select('COALESCE(SUM(h.total_views), 0) AS total_views')
				->select('COALESCE(SUM(dl.total_downloads), 0) AS total_downloads')
				->from($db->qn('#__rsfiles_files', 'f'))
				->leftJoin('(' . $subQueryViews . ') AS h ON h.file_id = f.IdFile')
				->leftJoin('(' . $subQueryDownloads . ') AS dl ON dl.file_id = f.IdFile')
				->group('f.FilePath');

			// Apply sorting and filtering
			if ($sortBy == 'mostViewed')
			{
				$query->having('total_views > 0');
				$query->order('total_views DESC');
			}
			elseif ($sortBy == 'mostDownloaded')
			{
				$query->having('total_downloads > 0');
				$query->order('total_downloads DESC');
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
						$categoryMap[$categoryName]['total_views']     += (int) $file['total_views'];
						$categoryMap[$categoryName]['total_downloads'] += (int) $file['total_downloads'];
					}
					else
					{
						$categoryMap[$categoryName] = [
							'Category'        => $categoryName,
							'total_views'     => (int) $file['total_views'],
							'total_downloads' => (int) $file['total_downloads']
						];
					}
				}
			}

			// Convert map to list
			$aggregatedCategories = array_values($categoryMap);

			echo new JsonResponse($aggregatedCategories);
			$this->app->close();
		}
		catch (Exception $e)
		{
			echo new JsonResponse(null, $e->getMessage(), true);
			$this->app->close();
		}
	}
}
