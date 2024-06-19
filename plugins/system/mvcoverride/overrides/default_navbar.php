<?php
/**
* @package RSFiles!
* @copyright (C) 2010-2014 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/
defined( '_JEXEC' ) or die( 'Restricted access' );
$currentFolder = $this->current == $this->config->download_folder ? 'root_rs_files' : $this->currentRel; 
$canCreate = rsfilesHelper::permissions('CanCreate',$currentFolder);
$canUpload = rsfilesHelper::permissions('CanUpload',$currentFolder);
$canDelete = rsfilesHelper::permissions('CanDelete',$currentFolder); ?>

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

<?php if ($this->config->file_path == 1) { ?>
	<ul class="breadcrumb">
		<?php if (empty($this->navigation)) { ?>
		<li class="active"><?php echo JText::_('COM_RSFILES_HOME'); ?></li>
		<?php } else { ?>
		<li>
			<a href="<?php echo JRoute::_('index.php?option=com_rsfiles'.$this->itemid); ?>"><?php echo JText::_('COM_RSFILES_HOME'); ?></a>
		</li>
		<?php end($this->navigation); ?>
		<?php $last_item_key = key($this->navigation); ?>
		<?php foreach ($this->navigation as $key => $element) { ?>
		<?php if ($key != $last_item_key) { ?>
		<li>
			<span class="divider">/</span>
			<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&folder='.rsfilesHelper::encode($element->fullpath).$this->itemid); ?>"><?php echo $element->name; ?></a>
		</li>
		<?php } else { ?>
		<li class="active">
			<span class="divider">/</span>
			<?php echo $element->name; ?>
		</li>
		<?php } ?>
		<?php } ?>
		<?php } ?>
	</ul>
<?php } ?>