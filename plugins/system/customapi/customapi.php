<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Response\JsonResponse;

class PlgSystemCustomapi extends CMSPlugin
{
    public function onAfterRoute(): void
    {
        try
        {
            $app = Factory::getApplication();

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
        try {
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
        try {
            $db = Factory::getDbo();
            $input = Factory::getApplication()->input;
            $startDate = $db->escape($input->getString('startDate', ''));
            $endDate = $db->escape($input->getString('endDate', ''));

            if (empty($startDate)) {
                $startDate = '1970-01-01';
            }
            if (empty($endDate)) {
                $endDate = date('Y-m-d');
            }

            $subQueryHits = $db->getQuery(true)
                ->select('v.file_id, COUNT(v.file_id) AS totalHits')
                ->from($db->quoteName('lanl4_lanl_rsfiles_viewed', 'v'))
                ->where('v.date_viewed BETWEEN ' . $db->quote($startDate) . ' AND ' . $db->quote($endDate))
                ->group('v.file_id');

            $subQueryDownloads = $db->getQuery(true)
                ->select('d.file_id, COUNT(d.file_id) AS totalDownloads')
                ->from($db->quoteName('lanl4_lanl_rsfiles_downloaded', 'd'))
                ->where('d.date_downloaded BETWEEN ' . $db->quote($startDate) . ' AND ' . $db->quote($endDate))
                ->group('d.file_id');

            $query = $db->getQuery(true)
                ->select('f.IdFile, f.FileName, f.FilePath, f.DateAdded, 
                          COALESCE(h.totalHits, 0) AS totalHits, 
                          COALESCE(dl.totalDownloads, 0) AS totalDownloads')
                ->from($db->quoteName('lanl4_rsfiles_files', 'f'))
                ->leftJoin('(' . $subQueryHits . ') AS h ON h.file_id = f.IdFile')
                ->leftJoin('(' . $subQueryDownloads . ') AS dl ON dl.file_id = f.IdFile');

            $sortBy = $db->escape($input->getString('sortBy', ''));
            if ($sortBy == 'mostViewed') {
                $query->where('COALESCE(h.totalHits, 0) >= 1');
                $query->order('totalHits DESC');
            } elseif ($sortBy == 'mostDownloaded') {
                $query->where('COALESCE(dl.totalDownloads, 0) >= 1');
                $query->order('totalDownloads DESC');
            }

            $db->setQuery($query);
            $files = $db->loadObjectList();

            foreach ($files as $file) {
                $filePath = $file->FilePath;
                $lastSlashPos = strrpos($filePath, '/');
                
                if ($lastSlashPos !== false) {
                    $fileName = substr($filePath, $lastSlashPos + 1);
                    $categoryPath = substr($filePath, 0, $lastSlashPos);
                    $lastDotPos = strrpos($fileName, '.');
                    
                    if ($lastDotPos === false) {
                        $file->FileName = '';
                        $file->Category = $fileName;
                    } else {
                        $file->FileName = $fileName;
                        $secondLastSlashPos = strrpos($categoryPath, '/');
                        if ($secondLastSlashPos !== false) {
                            $file->Category = substr($categoryPath, $secondLastSlashPos + 1);
                        } else {
                            $file->Category = $categoryPath;
                        }
                    }
                } else {
                    $file->FileName = '';
                    $file->Category = '';
                }
            }

            echo new JsonResponse($files);
        } catch (Exception $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }
    }

    protected function getRsCategory(): void
    {
        try {
            $db = Factory::getDbo();
            $input = Factory::getApplication()->input;
            $startDate = $db->escape($input->getString('startDate', '1970-01-01'));
            $endDate = $db->escape($input->getString('endDate', date('Y-m-d') . ' 23:59:59'));
            $sortBy = $db->escape($input->getString('sortBy', ''));

            $subQueryHits = $db->getQuery(true)
                ->select('v.file_id, COUNT(v.file_id) AS totalHits')
                ->from($db->quoteName('lanl4_lanl_rsfiles_viewed', 'v'))
                ->where('v.date_viewed BETWEEN ' . $db->quote($startDate) . ' AND ' . $db->quote($endDate))
                ->group('v.file_id');

            $subQueryDownloads = $db->getQuery(true)
                ->select('d.file_id, COUNT(d.file_id) AS totalDownloads')
                ->from($db->quoteName('lanl4_lanl_rsfiles_downloaded', 'd'))
                ->where('d.date_downloaded BETWEEN ' . $db->quote($startDate) . ' AND ' . $db->quote($endDate))
                ->group('d.file_id');

            $query = $db->getQuery(true)
                ->select('f.FilePath, 
                          COALESCE(SUM(h.totalHits), 0) AS totalHits, 
                          COALESCE(SUM(dl.totalDownloads), 0) AS totalDownloads')
                ->from($db->quoteName('lanl4_rsfiles_files', 'f'))
                ->leftJoin('(' . $subQueryHits . ') AS h ON h.file_id = f.IdFile')
                ->leftJoin('(' . $subQueryDownloads . ') AS dl ON dl.file_id = f.IdFile')
                ->group('f.FilePath');

            if ($sortBy == 'mostViewed') {
                $query->having('totalHits >= 1');
                $query->order('totalHits DESC');
            } elseif ($sortBy == 'mostDownloaded') {
                $query->having('totalDownloads >= 1');
                $query->order('totalDownloads DESC');
            }

            $db->setQuery($query);
            $files = $db->loadAssocList();

            $categoryMap = [];

            foreach ($files as $file) {
                $filePath = $file['FilePath'];
                $lastSlashPos = strrpos($filePath, '/');

                if ($lastSlashPos !== false) {
                    $fileName = substr($filePath, $lastSlashPos + 1);
                    $categoryPath = substr($filePath, 0, $lastSlashPos);
                    $lastDotPos = strrpos($fileName, '.');

                    if ($lastDotPos === false) {
                        $categoryName = $fileName;
                    } else {
                        $secondLastSlashPos = strrpos($categoryPath, '/');
                        if ($secondLastSlashPos !== false) {
                            $categoryName = substr($categoryPath, $secondLastSlashPos + 1);
                        } else {
                            $categoryName = $categoryPath;
                        }
                    }
                    
                    if (empty($categoryName)) {
                        continue;
                    }

                    if (isset($categoryMap[$categoryName])) {
                        $categoryMap[$categoryName]['totalHits'] += (int)$file['totalHits'];
                        $categoryMap[$categoryName]['totalDownloads'] += (int)$file['totalDownloads'];
                    } else {
                        $categoryMap[$categoryName] = [
                            'Category' => $categoryName,
                            'totalHits' => (int)$file['totalHits'],
                            'totalDownloads' => (int)$file['totalDownloads']
                        ];
                    }
                }
            }

            $aggregatedCategories = array_values($categoryMap);

            echo new JsonResponse($aggregatedCategories);
        } catch (Exception $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }
    }

    protected function saveViewed(): void
    {
        try {
            $input = Factory::getApplication()->input;
            $fileId = $input->json->getInt('file_id');
            $viewerIpAddress = $input->server->get('REMOTE_ADDR');
            $viewerCountry = $this->getCountryFromIp($viewerIpAddress);

            if (!$viewerCountry) {
                $viewerCountry = 'Unknown';
            }

            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('lanl4_lanl_rsfiles_viewed'))
                ->columns(array($db->quoteName('file_id'), $db->quoteName('viewer_ip_address'), $db->quoteName('viewer_country'), $db->quoteName('date_viewed')))
                ->values(implode(',', array($db->quote($fileId), $db->quote($viewerIpAddress), $db->quote($viewerCountry), 'NOW()')));
            
            $db->setQuery($query);
            $db->execute();

            echo new JsonResponse(array('success' => true));
        } catch (Exception $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }
    }

    protected function saveDownloaded(): void
    {
        try {
            $input = Factory::getApplication()->input;
            $fileId = $input->json->getInt('file_id');
            $downloaderIpAddress = $input->server->get('REMOTE_ADDR');
            $downloaderCountry = $this->getCountryFromIp($downloaderIpAddress);

            if (!$downloaderCountry) {
                $downloaderCountry = 'Unknown';
            }

            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('lanl4_lanl_rsfiles_downloaded'))
                ->columns(array($db->quoteName('file_id'), $db->quoteName('downloader_ip_address'), $db->quoteName('downloader_country'), $db->quoteName('date_downloaded')))
                ->values(implode(',', array($db->quote($fileId), $db->quote($downloaderIpAddress), $db->quote($downloaderCountry), 'NOW()')));
            
            $db->setQuery($query);
            $db->execute();

            echo new JsonResponse(array('success' => true));
        } catch (Exception $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }
    }

    protected function getCountryFromIp($ipAddress): string
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true);

            $query->select('country_code')
                  ->from($db->quoteName('ip_to_country'))
                  ->where($db->quote($ipAddress) . ' BETWEEN ip_start AND ip_end');

            $db->setQuery($query);
            return $db->loadResult();
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
}
?>

