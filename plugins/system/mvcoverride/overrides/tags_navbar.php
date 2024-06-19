<?php
/**
* @package RSFiles!
* @copyright (C) 2010-2014 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/
defined( '_JEXEC' ) or die( 'Restricted access' ); ?>

<ul class="<?php echo RSFilesAdapterGrid::styles(array('unstyled', 'inline')); ?>">
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_HOME'); ?>" href="<?php echo JRoute::_('/index.php'); ?>">
			<span class="fa fa-home"></span>
		</a>
	</li>
</ul>

<div class="alert alert-success alert-dismissible" id="rsf_alert" style="display:none;">
	<button type="button" class="close btn-close" onclick="document.getElementById('rsf_alert').style.display = 'none';"><?php echo rsfilesHelper::isJ4() ? '' : '&times;'; ?></button>
	<span id="rsf_message"></span>
</div>