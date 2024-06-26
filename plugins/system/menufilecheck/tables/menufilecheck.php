<?php
defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

class MenuViewsTableMenuViews extends Table
{
    public function __construct(&$db)
    {
        parent::__construct('#__rsfiles_menuhits', 'id', $db);
    }
}
