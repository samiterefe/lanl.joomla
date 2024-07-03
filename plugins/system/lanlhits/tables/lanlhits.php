<?php

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

class LanlHitsTableLanlHits extends Table
{
    public function __construct(&$db)
    {
        parent::__construct('#__lanlhits_menuviews', 'Itemid', $db);
        $this->_autoincrement = false;
    }

    public function hit($pk = null)
    {
        // If no primary key is provided, use the current key
        $pk = $pk ?: $this->Itemid;
        
        if ($pk)
        {
            // Load the record with the provided primary key
            if ($this->load($pk))
            {
                // Increment the hits counter
                $this->hits++;
                
                // Store the updated record
                return $this->store();
            }
        }

        // Return false if the record could not be loaded or primary key is not valid
        return false;
    }
}
