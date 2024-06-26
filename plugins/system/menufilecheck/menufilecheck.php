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
                        // Check if an entry already exists in the rsfiles_menuhits table
                        $hitsQuery = $this->db->getQuery(true)
                            ->select($this->db->quoteName('id'))
                            ->select($this->db->quoteName('hits'))
                            ->from($this->db->quoteName('#__rsfiles_menuhits'))
                            ->where($this->db->quoteName('menu_id') . ' = ' . (int)$Itemid)
                            ->where($this->db->quoteName('file_path') . ' = ' . $this->db->quote($result));
                        
                        $this->db->setQuery($hitsQuery);
                        $hitsResult = $this->db->loadObject();

                        if ($hitsResult) {
                            // Update the hit counter in the rsfiles_menuhits table
                            $hitsResult->hits++;
                            $query = $this->db->getQuery(true)
                                ->update($this->db->quoteName('#__rsfiles_menuhits'))
                                ->set($this->db->quoteName('hits') . ' = ' . (int)$hitsResult->hits)
                                ->where($this->db->quoteName('id') . ' = ' . (int)$hitsResult->id);
                            $this->db->setQuery($query);
                            $this->db->execute();
                        } else {
                            // Add a new row in the rsfiles_menuhits table
                            $query = $this->db->getQuery(true)
                                ->insert($this->db->quoteName('#__rsfiles_menuhits'))
                                ->columns(array($this->db->quoteName('menu_id'), $this->db->quoteName('menu_title'), $this->db->quoteName('file_path'), $this->db->quoteName('hits')))
                                ->values((int)$Itemid . ', ' . $this->db->quote($menuTitle) . ', ' . $this->db->quote($result) . ', 1');
                            $this->db->setQuery($query);
                            $this->db->execute();
                        }
                    }
                }
            }
        }

        return true;
    }
}
