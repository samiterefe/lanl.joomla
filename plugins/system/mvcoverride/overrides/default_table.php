<?php
/**
* @package RSFiles!
* @copyright (C) 2010-2014 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/
defined( '_JEXEC' ) or die( 'Restricted access' ); ?>

<table class="rsf_files table table-striped table-sort table-ar">
	<thead>
		<tr>
			<th width="30%" align="left">
			<?php echo JHtml::_('grid.sort', 'COM_RSFILES_FILE_NAME', 'name', $this->listDirn, $this->listOrder); ?>
			</th>
			 <th width="30%" style="text-align: center;"><?php echo JText::_('Expiry/Renewals'); ?></th>
			 <th width="20%" style="text-align: center;"><?php echo JText::_('Download/Details/Preview'); ?></th>
			<?php if ($this->config->list_show_date) { ?>
			<th width="20%" style="text-align: center;"><?php echo JHtml::_('grid.sort', 'COM_RSFILES_FILE_DATE', 'date', $this->listDirn, $this->listOrder); ?></th><?php } ?>
			
		</tr>
	</thead>
	<tbody>
		<?php if (!empty($this->items)) { ?>
		<?php foreach ($this->items as $i => $item) { ?>
		<?php $fullpath		= $this->dld_fld.$this->ds.urldecode($item->fullpath) ; ?>
		<?php $path			= str_replace($this->config->download_folder.$this->ds, '', $fullpath);  ?>
		<?php $canDownload 	= rsfilesHelper::permissions('CanDownload',$path); ?>
		<?php $canDelete   	= rsfilesHelper::permissions('CanDelete',$path) || (rsfilesHelper::briefcase('deleteown') && $item->IdUser == $this->user->get('id')); ?>
		<?php if (!empty($item->DownloadLimit) && $item->Downloads >= $item->DownloadLimit) $canDownload = false; ?>

		<tr class="row<?php echo $i % 2; ?>">
			<td class="rsfiles-download-info">
				<?php $thumbnail = rsfilesHelper::thumbnail($item); ?>
				<?php if ($item->type != 'folder') { ?>
					<?php $download = rsfilesHelper::downloadlink($item,$item->fullpath); ?>
					<?php if ($canDownload && $this->config->direct_download) { ?>
					<?php if ($download->ismodal) { ?>
					<a class="rsfiles-file <?php echo $thumbnail->class; ?>" href="javascript:void(0)" onclick="rsfiles_show_modal('<?php echo $download->dlink; ?>', '<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>', 600)" title="<?php echo $thumbnail->image; ?>">
					<?php } else { ?>
					<a class="rsfiles-file rsfiles-download-link <?php echo $thumbnail->class; ?>" href="<?php echo $download->dlink; ?>" title="<?php echo $thumbnail->image; ?>">
					<?php } ?>
					<?php } else { ?>
					<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=download&path='.rsfilesHelper::encode($item->fullpath).$this->itemid); ?>" class="rsfiles-file <?php echo $thumbnail->class; ?>" title="<?php echo $thumbnail->image; ?>">
					<?php } ?>
						<i class="rsfiles-file-icon <?php echo $item->icon; ?>"></i> <?php echo (!empty($item->filename) ? $item->filename : $item->name); ?>
					</a>
					
					<br />
					
					<?php if ($item->isnew) { ?>
					<span class="badge badge-info bg-info"><?php echo JText::_('COM_RSFILES_NEW'); ?></span>
					<?php } ?>
					
					<?php if ($item->popular) { ?>
						<span class="badge badge-success bg-success"><?php echo JText::_('COM_RSFILES_POPULAR'); ?></span>
					<?php } ?>
					
					<?php if ($this->config->list_show_version && !empty($item->fileversion)) { ?><span class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_FILE_VERSION'); ?>"><i class="fa fa-code-branch"></i> <?php echo $item->fileversion; ?></span><?php } ?>
					<?php if ($this->config->list_show_license && !empty($item->filelicense)) { ?><span class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip badge-license" title="<?php echo JText::_('COM_RSFILES_FILE_LICENSE'); ?>"><i class="fa fa-flag"></i> <?php echo $item->filelicense; ?></span><?php } ?>
					<?php if ($this->config->list_show_size && !empty($item->size)) { ?><span class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_FILE_SIZE'); ?>"><i class="fa fa-file"></i> <?php echo $item->size; ?></span><?php } ?>
					<?php if ($this->config->list_show_downloads && !empty($item->downloads)) { ?><span class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip" title="<?php echo JText::_('COM_RSFILES_FILE_DOWNLOADS'); ?>"><i class="fa fa-download"></i> <?php echo $item->downloads; ?></span><?php } ?>
					
				<?php } else { ?>
					<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&folder='.rsfilesHelper::encode($item->fullpath).$this->itemid); ?>" class="<?php echo $thumbnail->class; ?>" title="<?php echo $thumbnail->image; ?>">
						<i class="rsfiles-file-icon fa fa-folder"></i> <?php echo (!empty($item->filename) ? $item->filename : $item->name); ?>
					</a>
				<?php } ?>
			</td>
			<td style="text-align: center;">
						<?php
						if ($item->FileStatus === 'Date Only')
						{
							echo $item->DateRelatedToStatus;
						}
						else
						{
							echo $item->FileStatus;
						}
						$FileRelatedName = end(explode('/', $item->FileRelatedToStatus)); ?><br>
                        <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&relatedfile=1&layout=download&path=' . rsfilesHelper::encode($item->FileRelatedToStatus) . '&Itemid=' . $this->itemid) ?>"
                           class="rsfiles-file"
                           title="<?php echo $FileRelatedName; ?>"
                           style="margin-bottom: 0;"><?php echo $FileRelatedName; ?></a>
						<?php if ($item->FileStatus == 'Renewed' && ($item->DateRelatedToStatus != '' && $item->DateRelatedToStatus != '11/1111' && $item->DateRelatedToStatus != '11/-0001')) echo '<br/>' . $item->DateRelatedToStatus ?>
                    </td>
			<td style="text-align: center;">
				<?php if ($item->type != 'folder') { ?>
				<?php if ($canDownload && $this->config->direct_download) { ?>
				<?php if ($download->ismodal) { ?>
				<a class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>" href="javascript:void(0)" onclick="rsfiles_show_modal('<?php echo $download->dlink; ?>', '<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>', 600);">
				<?php } else { ?>
				<a class="hasTooltip rsfiles-download-link" title="<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>" href="<?php echo $download->dlink; ?>">
				<?php } ?>
				<?php } else { ?>
				<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=download&path='.rsfilesHelper::encode($item->fullpath).$this->itemid); ?>" class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>">
				<?php } ?>
					<i class="fa fa-download fa-fw"></i>
				</a>
				
				<?php if ($this->config->show_details) { ?>
				<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=details&path='.rsfilesHelper::encode($item->fullpath).$this->itemid); ?>" class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_DETAILS'); ?>">
					<i class="fa fa-list fa-fw"></i>
				</a>
				<?php } ?>
				
				<?php if ($canDownload) { ?>
					<?php $properties	= rsfilesHelper::previewProperties($item->id, $item->fullpath); ?>
					<?php $extension	= $properties['extension']; ?>
					<?php $size			= $properties['size']; ?>
					
					<?php if (in_array($extension, rsfilesHelper::previewExtensions()) && $item->show_preview) { ?>
					<a href="javascript:void(0)" onclick="rsfiles_show_modal('<?php echo rsfilesHelper::previewURL($extension, JRoute::_('index.php?option=com_rsfiles&layout=preview&tmpl=component&path='.rsfilesHelper::encode($item->fullpath).$this->itemid)); ?>', '<?php echo JText::_('COM_RSFILES_PREVIEW'); ?>', <?php echo $size['height']; ?>, '<?php echo $properties['handler']; ?>');" class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_PREVIEW'); ?>">
						<i class="fa fa-search fa-fw"></i>
					</a>
					<?php } ?>
				<?php } ?>
				
				<?php if ($canDownload && $this->config->show_bookmark && !$item->FileType) { ?>
				<a href="javascript:void(0);" class="hasTooltip" title="<?php echo rsfilesHelper::isBookmarked($item->fullpath) ? JText::_('COM_RSFILES_NAVBAR_FILE_IS_BOOKMARKED') : JText::_('COM_RSFILES_NAVBAR_BOOKMARK_FILE'); ?>" onclick="rsf_bookmark('<?php echo JURI::root(); ?>','<?php echo $this->escape(addslashes(urldecode($item->fullpath))); ?>','<?php echo $this->briefcase ? 1 : 0; ?>','<?php echo $this->app->input->getInt('Itemid',0); ?>', this)">
					<i class="<?php echo rsfilesHelper::isBookmarked($item->fullpath) ? 'fa' : 'far'; ?> fa-bookmark fa-fw"></i>
				</a>
				<?php } ?>
				
				<?php if ($canDelete) { ?>
				<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&task=rsfiles.delete&path='.rsfilesHelper::encode($item->fullpath).$this->itemid); ?>" class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_DELETE'); ?>" onclick="if (!confirm('<?php echo JText::_('COM_RSFILES_DELETE_FILE_MESSAGE',true); ?>')) return false;">
					<i class="fa fa-trash fa-fw"></i>
				</a>
				<?php } ?>
				
				<?php } else if ($item->type == 'folder') { ?>
				
					<?php if ($canDelete) { ?>
					<a href="<?php echo JRoute::_('index.php?option=com_rsfiles&task=rsfiles.delete&folder='.rsfilesHelper::encode($item->fullpath).$this->itemid); ?>" class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_NAVBAR_DELETE'); ?>" onclick="if (!confirm('<?php echo JText::_('COM_RSFILES_DELETE_MESSAGE',true); ?>')) return false;">
						<i class="fa fa-trash fa-fw"></i>
					</a>
					<?php } ?>
				
				<?php } ?>
			</td>
			<?php if ($this->config->list_show_date) { ?><td style="text-align: center;"><?php if ($item->type != 'folder') echo $item->dateadded; ?></td><?php } ?>
		</tr>
		<?php } ?>
		<?php } else { ?>
		<tr>
			<td colspan="4"><?php echo JText::_('COM_RSFILES_NO_FILES'); ?></td>
		</tr>
		<?php } ?>
	</tbody>
</table>