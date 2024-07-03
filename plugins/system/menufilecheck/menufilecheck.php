<?php
// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Application\SiteApplication;

Table::addIncludePath(__DIR__ . '/tables');

class PlgSystemMenuFileCheck extends CMSPlugin
{
    protected $db;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->db = Factory::getDbo();
    }

    public function onAfterRoute(): bool {
        $app = Factory::getApplication();

        // Check that we are on the frontend of the site
        if ($app->isClient('site'))
        {
            // Get the current url query parameters
            Uri::current(); // It's very strange, but without this line at least Joomla 3 fails to fulfill the task
            $url = Uri::getInstance();
            $router = SiteApplication::getRouter(); // get router
            $urlQueryParams = $router->parse($url); // Get the real joomla query as an array - parse current joomla link

            // Check if the url has an Itemid so we know it could be a menu link
            if (isset($urlQueryParams['Itemid']))
            {
                // extract the Itemid value for later use
                $Itemid = $urlQueryParams['Itemid'];

                // Unset Itemid because link url returned from JMenu doesn't include it and we need to compare it.
                unset($urlQueryParams['Itemid']);

                // Use the Itemid to get the menu item
                $menuItem = Factory::getApplication()->getMenu()->getItem((int) $Itemid);

                // Check if the menu item exists
                if ($menuItem !== false && isset($menuItem->title))
                {
                    $menuTitle = $menuItem->title;

                    // Query the rsfiles_files table to check if any file paths match the menu title
                    $query = $this->db->getQuery(true)
                        ->select($this->db->quoteName('filepath'))
                        ->from($this->db->quoteName('#__rsfiles_files'))
                        ->where('SUBSTRING_INDEX(' . $this->db->quoteName('filepath') . ', "/", 1) = ' . $this->db->quote($menuTitle));

                    $this->db->setQuery($query);
                    $result = $this->db->loadResult();

                    if ($result) {
                        // Get downloader IP address and country
                        $downloader_ip_address = $app->input->server->get('REMOTE_ADDR');
                        $downloader_country = $this->getCountryFromIp($downloader_ip_address);

                        // Always insert a new row in the rsfiles_menuhits table for every click
                        $query = $this->db->getQuery(true)
                            ->insert($this->db->quoteName('#__lanl_rsfiles_menuhits'))
                            ->columns(array($this->db->quoteName('menu_id'), $this->db->quoteName('menu_title'), $this->db->quoteName('file_path'), $this->db->quoteName('country')))
                            ->values((int)$Itemid . ', ' . $this->db->quote($menuTitle) . ', ' . $this->db->quote($result) . ', ' . $this->db->quote($downloader_country));
                        $this->db->setQuery($query);
                        $this->db->execute();
                    }
                }
            }
        }

        return true;
    }

    public function getCountryFromIp($ipAddress): ?string
    {
        try
        {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);
            $query
                ->select($db->quoteName('country_code'))
                ->from($db->quoteName('#__rsfilesreports_ip_to_country'))
                ->where($db->quote($ipAddress) . ' BETWEEN ip_start AND ip_end');

            $db->setQuery($query);

            return $db->loadResult();
        }
        catch (\Exception $e)
        {
            echo new JsonResponse(null, $e->getMessage(), true);
            Factory::getApplication()->close();

            return null;
        }
    }
}
