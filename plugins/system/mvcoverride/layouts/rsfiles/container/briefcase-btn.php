<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');
?>

<ul class="<?php echo RSFilesAdapterGrid::styles(array('unstyled', 'inline')); ?>">
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_BRIEFCASE'); ?>" href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=bookmarks'.$this->itemid); ?>">
			<span class="fa fa-briefcase"></span>
		</a>
	</li>
</ul>
