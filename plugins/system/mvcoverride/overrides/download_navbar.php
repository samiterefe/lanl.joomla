<?php
/**
* @package RSFiles!
* @copyright (C) 2010-2014 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/
defined( '_JEXEC' ) or die( 'Restricted access' ); ?>

<ul class="<?php echo RSFilesAdapterGrid::styles(array('unstyled', 'inline')); ?>">
	
	<?php if (!$this->briefcase) { ?>
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_HOME'); ?>" href="<?php echo JRoute::_('/index.php'); ?>">
			<span class="fa fa-home"></span>
		</a>
	</li>
	<?php } ?>

	<?php if ($this->candownload && $this->config->show_bookmark && !$this->file->FileType) { ?>
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo rsfilesHelper::isBookmarked($this->path) ? JText::_('COM_RSFILES_NAVBAR_FILE_IS_BOOKMARKED') : JText::_('COM_RSFILES_NAVBAR_BOOKMARK_FILE'); ?>" href="javascript:void(0);" onclick="rsf_bookmark('<?php echo JURI::root(); ?>','<?php echo $this->escape(addslashes($this->path)); ?>','<?php echo $this->briefcase ? 1 : 0; ?>','<?php echo $this->app->input->getInt('Itemid',0); ?>', this)">
			<i class="<?php echo rsfilesHelper::isBookmarked($this->path) ? 'fa' : 'far'; ?> fa-bookmark fa-fw"></i>
		</a>
	</li>
	<?php } ?>

</ul>	

<div class="alert alert-success alert-dismissible" id="rsf_alert" style="display:none;">
	<button type="button" class="close btn-close" onclick="document.getElementById('rsf_alert').style.display = 'none';"><?php echo rsfilesHelper::isJ4() ? '' : '&times;'; ?></button>
	<span id="rsf_message"></span>
</div>