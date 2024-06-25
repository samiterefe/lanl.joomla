<?php 
namespace TCM\Component\RSFilesReports\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;

\defined('_JEXEC') or die;

class MenuController extends ApiController
{
    protected $contentType = 'menu';

    protected $default_view = 'menu';

    public function __construct()
    {
        parent::__construct();
    }

    public function getMenu(): void
    {
        try
        {
            $input = $this->app->input;
            $startDate = $input->get('startDate', '1970-01-01', 'string');
            $endDate = $input->get('endDate', date('Y-m-d') . ' 23:59:59', 'string');
            $sortBy = $input->get('sortBy', 'menu_id', 'string'); // Default sort


            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            // Query to count the total views for each menu_id
            $query
                ->select($db->quoteName(['menu_id', 'menu_title', 'file_path', 'country']))
                ->select('COUNT(' . $db->quoteName('id') . ') AS total_views')
                ->from($db->quoteName('#__lanl_rsfiles_menuhits'))
                ->where($db->quoteName('date_viewed') . ' BETWEEN ' . $db->quote($startDate) . ' AND ' . $db->quote($endDate))
                ->group($db->quoteName('menu_id'))
                ->order($db->quoteName($sortBy)); // Use the sortBy parameter


            // Adding sorting if required
            if ($sortBy === 'mostViewed') {
                $query->order('total_views DESC');
            }

            $db->setQuery($query);
            $menuViews = $db->loadObjectList();

            echo new JsonResponse($menuViews);
            $this->app->close();
        }
        catch (\Exception $e)
        {
            echo new JsonResponse(null, $e->getMessage(), true);
            $this->app->close();
        }
    }
}
