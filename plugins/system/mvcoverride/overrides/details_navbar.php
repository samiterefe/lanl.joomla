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
	
	<?php if ($this->user->get('id') > 0 && $this->config->enable_briefcase && !empty($this->config->briefcase_folder) && (rsfilesHelper::briefcase('CanDownloadBriefcase') || rsfilesHelper::briefcase('CanUploadBriefcase') || rsfilesHelper::briefcase('CanDeleteBriefcase') || rsfilesHelper::briefcase('CanMaintainBriefcase'))) { ?>
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_BRIEFCASE'); ?>" href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=briefcase'.$this->itemid); ?>">
			<span class="fa fa-briefcase"></span>
		</a>
	</li>
	<?php } ?>
	
	<?php if ($this->config->show_search && !$this->briefcase && !$this->tagMenu) { ?>
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_SEARCH'); ?>" href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=search'.$this->itemid); ?>">
			<span class="fa fa-search"></span>
		</a>
	</li>
	<?php } ?>
	
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_DOWNLOAD'); ?>" href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=download'.($this->briefcase ? '&from=briefcase' : '').rsfilesHelper::getPath(true).($this->hash ? '&hash='.$this->hash : '').$this->itemid); ?>">
			<span class="fa fa-download"></span>
		</a>
	</li>
	
	<?php if ($this->candelete) { ?>
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_DELETE'); ?>" href="<?php echo JRoute::_('index.php?option=com_rsfiles&task=rsfiles.delete'.($this->briefcase ? '&from=briefcase' : '').rsfilesHelper::getPath(true).$this->itemid); ?>" onclick="if (!confirm('<?php echo JText::_('COM_RSFILES_DELETE_FILE_MESSAGE',true); ?>')) return false;">
			<span class="fa fa-trash"></span>
		</a>
	</li>
	<?php } ?>
	
	<?php if ($this->canedit) { ?>
	<li class="list-inline-item">
		<a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_EDIT'); ?>" href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=edit'.($this->briefcase ? '&from=briefcase' : '').rsfilesHelper::getPath(true).'&return='.base64_encode(JURI::getInstance()).$this->itemid); ?>">
			<span class="fa fa-edit"></span>
		</a>
	</li>
	<?php } ?>

    <?php //if ($this->candownload && $this->config->show_bookmark && !$this->file->FileType) { ?>
      <li class="list-inline-item">
        <a class="<?php echo RSFilesAdapterGrid::styles(array('btn')); ?> hasTooltip" title="<?php echo rsfilesHelper::isBookmarked($this->path) ? JText::_('COM_RSFILES_NAVBAR_FILE_IS_BOOKMARKED') : JText::_('COM_RSFILES_NAVBAR_BOOKMARK_FILE'); ?>" href="javascript:void(0);" onclick="rsf_bookmark('<?php echo JURI::root(); ?>','<?php echo $this->escape(addslashes($this->path)); ?>','<?php echo $this->briefcase ? 1 : 0; ?>','<?php echo $this->app->input->getInt('Itemid',0); ?>', this)">
          <i class="<?php echo rsfilesHelper::isBookmarked($this->path) ? 'fa' : 'far'; ?> fa-bookmark fa-fw"></i>
        </a>
      </li>
    <?php //} ?>

</ul>