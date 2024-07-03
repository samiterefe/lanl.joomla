<?php
// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Application\SiteApplication;

Table::addIncludePath(__DIR__ . '/tables');

class PlgSystemLanlHits extends CMSPlugin
{
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

                // Use the Itemid to get the menu query parameters.
                $menuItem = Factory::getApplication()->getMenu()->getItem((int) $Itemid);

                // Check if the menu item exists
                if ($menuItem !== false && isset($menuItem->query))
                {
                    $menuQueryParams = $menuItem->query;

                    // check if we have a menu link by verifying that the query parameters are the same
                    sort($urlQueryParams);
                    sort($menuQueryParams);

                    if ($urlQueryParams == $menuQueryParams)
                    {
                        // check if a row tracking hits already exist in the database
                        $table = Table::getInstance('LanlHits', 'LanlHitsTable');
                        if ($table->load($Itemid))
                        {
                            // Update the hit counter in the database
                            return $table->hit($Itemid);
                        }
                        else
                        {
                            // add a new row in the table to start tracking this menu item's hits
                            return $table->save(array('Itemid' => $Itemid, 'hits' => 1));
                        }
                    }
                }
            }
        }

        return true;
    }
}
