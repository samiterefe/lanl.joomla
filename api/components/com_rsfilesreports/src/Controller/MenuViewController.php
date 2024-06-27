<?php
namespace TCM\Component\RSFilesReports\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;

\defined('_JEXEC') or die;

class MenuViewController extends ApiController
{
    protected $contentType = 'menuView';

    protected $default_view = 'menuView';

    public function __construct()
    {
        parent::__construct();
    }

    public function getMenuViews(): void
    {
        try
        {
            echo "Debug: Entering getMenuViews\n";
            
            $input = $this->app->input;
            $startDate = $input->get('startDate', '1970-01-01', 'string');
            $endDate = $input->get('endDate', date('Y-m-d') . ' 23:59:59', 'string');
            $sortBy = $input->get('sortBy', '', 'string');
    
            echo "Debug: Start Date: $startDate, End Date: $endDate, Sort By: $sortBy\n";
    
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
    
            $query
                ->select($db->quoteName(['menu_id', 'menu_title', 'country', 'hits']))
                ->from($db->quoteName('lanl4_rsfiles_menuhits'))
                ->where($db->quoteName('created_at') . ' BETWEEN ' . $db->quote($startDate) . ' AND ' . $db->quote($endDate));
    
            if ($sortBy === 'mostViewed') {
                $query->order($db->quoteName('hits') . ' DESC');
            }
    
            $db->setQuery($query);
            $menuViews = $db->loadObjectList();
    
            echo "Debug: Query executed, result count: " . count($menuViews) . "\n";
    
            echo new JsonResponse($menuViews);
            $this->app->close();
        }
        catch (\Exception $e)
        {
            echo "Debug: Exception caught: " . $e->getMessage() . "\n";
            echo new JsonResponse(null, $e->getMessage(), true);
            $this->app->close();
        }
    }
}
