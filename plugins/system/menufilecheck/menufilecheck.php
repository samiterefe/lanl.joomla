<?php
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Application\SiteApplication;

class PlgSystemMenuFileCheck extends CMSPlugin
{
    protected $db;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->db = Factory::getDbo();
    }

    public function onAfterRoute(): bool
    {
        $app = Factory::getApplication();

        // Check that we are on the frontend of the site
        if ($app->isClient('site'))
        {
            // Get the current url query parameters
            Uri::current(); // It's very strange, but without this line at least Joomla 3 fails to fulfill the task
            $url = Uri::getInstance();
            $router = SiteApplication::getRouter(); // get router
            $urlQueryParams = $router->parse($url); // Get the real Joomla query as an array - parse current Joomla link

            // Check if the url has an Itemid so we know it could be a menu link
            if (isset($urlQueryParams['Itemid']))
            {
                // Extract the Itemid value for later use
                $Itemid = $urlQueryParams['Itemid'];

                // Unset Itemid because link url returned from JMenu doesn't include it and we need to compare it.
                unset($urlQueryParams['Itemid']);

                // Use the Itemid to get the menu item
                $menuItem = Factory::getApplication()->getMenu()->getItem((int) $Itemid);

                // Check if the menu item exists
                if ($menuItem !== false && isset($menuItem->title))
                {
                    $menuTitle = $menuItem->title;

                    // Query the rsfiles_files table to get the file paths
                    $query = $this->db->getQuery(true)
                        ->select($this->db->quoteName('filepath'))
                        ->from($this->db->quoteName('#__rsfiles_files'));

                    $this->db->setQuery($query);    
                    $files = $this->db->loadAssocList();

                    // Extract categories and aggregate data
                    $processedCategories = [];

                    foreach ($files as $file)
                    {
                        $filePath     = $file['filepath'];
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

                            // Normalize category name and menu title for comparison
                            $normalizedCategoryName = strtolower(trim($categoryName));
                            $normalizedMenuTitle = strtolower(trim($menuTitle));

                            // Check if the normalized category name matches the normalized menu title
                            if ($normalizedCategoryName === $normalizedMenuTitle)
                            {
                                // Check if this category has already been processed
                                if (!in_array($normalizedCategoryName, $processedCategories))
                                {
                                    // Get downloader IP address and country
                                    $downloaderIpAddress = $app->input->server->get('REMOTE_ADDR');
                                    $downloaderCountry = $this->getCountryFromIp($downloaderIpAddress);

                                    // Insert a new row in the rsfiles_menuhits table
                                    $query = $this->db->getQuery(true)
                                        ->insert($this->db->quoteName('#__lanl_rsfiles_menuhits'))
                                        ->columns(array($this->db->quoteName('menu_id'), $this->db->quoteName('menu_title'), $this->db->quoteName('file_path'), $this->db->quoteName('country'), $this->db->quoteName('viewer_ip_address')))
                                        ->values((int)$Itemid . ', ' . $this->db->quote($menuTitle) . ', ' . $this->db->quote($categoryName) . ', ' . $this->db->quote($downloaderCountry) . ', ' . $this->db->quote($downloaderIpAddress));
                                    $this->db->setQuery($query);
                                    $this->db->execute();

                                    // Mark this category as processed
                                    $processedCategories[] = $normalizedCategoryName;
                                }
                            }
                        }
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
            $query = $this->db->getQuery(true);
            $query
                ->select($this->db->quoteName('country_code'))
                ->from($this->db->quoteName('#__rsfilesreports_ip_to_country'))
                ->where($this->db->quote($ipAddress) . ' BETWEEN ip_start AND ip_end');

            $this->db->setQuery($query);

            return $this->db->loadResult();
        }
        catch (\Exception $e)
        {
            // Log the error and return null
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return null;
        }
    }
}
