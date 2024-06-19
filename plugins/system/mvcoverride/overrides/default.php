<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Layout\LayoutHelper;
?>

<?php
if ($this->params->get('show_page_heading') != 0)
{ ?>
    <div class="page-header">
        <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    </div>
<?php } ?>

<div class="rsfiles-layout">
	<?php
	if (JFactory::getApplication()->scope !== 'mod_rsfiles_newest' && JFactory::getApplication()->scope !== 'mod_rsfiles_list_tags')
	{
		echo $this->loadTemplate('navbar');
	}else { ?>
        <div class="alert alert-success alert-dismissible" id="rsf_alert" style="display:none;">
            <button type="button" class="close btn-close" onclick="document.getElementById('rsf_alert').style.display = 'none';"><?php echo rsfilesHelper::isJ4() ? '' : '&times;'; ?></button>
            <span id="rsf_message"></span>
        </div>
    <?php }
    ?>

	<?php
	if (JFactory::getApplication()->scope !== 'mod_rsfiles_newest' && JFactory::getApplication()->scope !== 'mod_rsfiles_list_tags')
	{
		if (($this->config->show_pagination_position == 0 || $this->config->show_pagination_position == 2) && $this->pagination->pagesTotal > 1)
		{ ?>
            <div class="com-rsfiles-files_navigation w-100 <?php echo !rsfilesHelper::isJ4() ? 'pagination' : ''; ?>">
                <p class="com-rsfiles-files_counter <?php echo RSFilesAdapterGrid::styles(array('counter', 'pull-right')); ?>">
					<?php echo $this->pagination->getPagesCounter(); ?>
                </p>
                <div class="com-rsfiles-files_pagination">
					<?php echo $this->pagination->getPagesLinks(); ?>
                </div>
            </div>
		<?php }
		?>

		<?php if ($this->config->show_folder_desc == 1 && !empty($this->fdescription)) { ?>
        <div class="<?php echo RSFilesAdapterGrid::card(); ?> mb-3">
            <div class="card-body">
				<?php echo $this->fdescription; ?>
            </div>
        </div>
	<?php }
	} ?>

    <form action="<?php echo htmlentities(JURI::getInstance(), ENT_COMPAT, 'UTF-8'); ?>" method="post"
          id="adminForm" name="adminForm">
		<?php if (JFactory::getApplication()->scope == 'mod_rsfiles_newest')
		{
			echo '<script src="' . JUri::root() . 'modules/mod_rsfiles_newest/js/table-sort.js">' . '</script>';
		}
		else if (JFactory::getApplication()->scope == 'mod_rsfiles_list_tags')
		{
			echo '<script src="' . JUri::root() . 'modules/mod_rsfiles_list_tags/js/table-sort.js">' . '</script>';
		}
		?>
        <table class="rsf_files table table-striped table-sort table-ar">
            <thead>
            <tr>
                <th width="30%">
					<?php
					if (JFactory::getApplication()->scope !== 'mod_rsfiles_newest' && JFactory::getApplication()->scope !== 'mod_rsfiles_list_tags')
					{
						echo JHtml::_('grid.sort', 'COM_RSFILES_FILE_NAME', 'name', $this->listDirn, $this->listOrder);
					}
					else
					{
						echo JText::_('Name');
					} ?>
                </th>

                <th width="30%" style="text-align: center;"><?php echo JText::_('Expiry/Renewals'); ?></th>
				 <th width="20%" style="text-align: center;"><?php echo JText::_('Download/Details/Preview'); ?></th>

				<?php if ($this->config->list_show_date) { ?>
                    <th width="20%" style="text-align: center;"><?php
					if (JFactory::getApplication()->scope !== 'mod_rsfiles_newest' && JFactory::getApplication()->scope !== 'mod_rsfiles_list_tags')
					{
						echo JHtml::_('grid.sort', 'COM_RSFILES_FILE_DATE', 'date', $this->listDirn, $this->listOrder);
					}
					else
					{
						echo JText::_('Date added');
					} ?></th><?php } ?>


            </tr>
            </thead>
            <tbody>
			<?php

			/*			// remove any folders when using mod_rsfiles_compoent
						if (JFactory::getApplication()->scope == 'mod_rsfiles_list_tags')
						{
							function folder($item)
							{
								return $item->type !== 'folder';
							}

							$filter      = array_filter($this->items, "folder");
							$this->items = $filter;
						}*/
			if (!empty($this->items))
			{ ?>
				<?php foreach ($this->items as $i => $item) { ?>
        <?php
        $tags = null;
		    $alltags = $this->getModel()->getFileTags(rsfilesHelper::getTags($item->id));
		    $tags = $this->getModel()->FilterFileTags($alltags);
        ?>
				<?php $fullpath = $this->dld_fld . $this->ds . urldecode($item->fullpath); ?>
				<?php $path = str_replace($this->config->download_folder . $this->ds, '', $fullpath); ?>
				<?php $canDownload = rsfilesHelper::permissions('CanDownload', $path); ?>
				<?php $canDelete = rsfilesHelper::permissions('CanDelete', $path) || (rsfilesHelper::briefcase('deleteown') && $item->IdUser == $this->user->get('id')); ?>
				<?php if (!empty($item->DownloadLimit) && $item->Downloads >= $item->DownloadLimit) $canDownload = false; ?>

                <tr class="row<?php echo $i % 2; ?>">
                    <td class="rsfiles-download-info">
						<?php $thumbnail = rsfilesHelper::thumbnail($item); ?>
						<?php if ($item->type != 'folder') { ?>
						<?php $download = rsfilesHelper::downloadlink($item, $item->fullpath); ?>
						<?php if ($canDownload && $this->config->direct_download) { ?>
						<?php if ($download->ismodal) { ?>
                        <a class="rsfiles-file <?php echo $thumbnail->class; ?>" href="javascript:void(0)"
                           onclick="rsfiles_show_modal('<?php echo $download->dlink; ?>', '<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>', 600)"
                           title="<?php echo $thumbnail->image; ?>">
							<?php } else { ?>
                            <a class="rsfiles-file <?php echo $thumbnail->class; ?>"
                               href="<?php echo $download->dlink; ?>" title="<?php echo $thumbnail->image; ?>">
								<?php } ?>
								<?php } else { ?>
                                <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>"
                                   class="rsfiles-file <?php echo $thumbnail->class; ?>"
                                   title="<?php echo $thumbnail->image; ?>">
									<?php } ?>
                                    <i class="rsfiles-file-icon <?php echo $item->icon; ?>"></i> <?php echo(!empty($item->filename) ? $item->filename : $item->name); ?>
                                </a>

                                <br />

								<?php if ($item->isnew) { ?>
                                    <span class="badge badge-info bg-info"><?php echo JText::_('COM_RSFILES_NEW'); ?></span>
								<?php } ?>

                <?php if(count($tags) > 0): ?>
                  <?php foreach($tags as $tag): ?>
                      <span class="badge bg-secondary"><?php echo $this->getModel()->getFileTagAlias($tag); ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>

								<?php if ($item->popular) { ?>
                                    <span class="badge badge-success bg-success"><?php echo JText::_('COM_RSFILES_POPULAR'); ?></span>
								<?php } ?>

								<?php if ($this->config->list_show_version && !empty($item->fileversion)) { ?>
                                <span
                                        class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip"
                                        title="<?php echo JText::_('COM_RSFILES_FILE_VERSION'); ?>"><i
                                            class="fa fa-code-branch"></i> <?php echo $item->fileversion; ?>
                                    </span><?php } ?>
								<?php if ($this->config->list_show_license && !empty($item->filelicense)) { ?>
                                <span
                                        class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip badge-license"
                                        title="<?php echo JText::_('COM_RSFILES_FILE_LICENSE'); ?>"><i
                                            class="fa fa-flag"></i> <?php echo $item->filelicense; ?>
                                    </span><?php } ?>
								<?php if ($this->config->list_show_size && !empty($item->size)) { ?><span
                                    class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip"
                                    title="<?php echo JText::_('COM_RSFILES_FILE_SIZE'); ?>"><i
                                            class="fa fa-file"></i> <?php echo $item->size; ?></span><?php } ?>
								<?php if ($this->config->list_show_downloads && !empty($item->downloads)) { ?>
                                <span
                                        class="<?php echo RSFilesAdapterGrid::styles(array('badge')); ?> hasTooltip"
                                        title="<?php echo JText::_('COM_RSFILES_FILE_DOWNLOADS'); ?>"><i
                                            class="fa fa-download"></i> <?php echo $item->downloads; ?>
                                    </span><?php } ?>

								<?php } else { ?>
                                    <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&folder=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>"
                                       class="<?php echo $thumbnail->class; ?>"
                                       title="<?php echo $thumbnail->image; ?>">
                                        <i class="rsfiles-file-icon fa fa-folder"></i> <?php echo(!empty($item->filename) ? $item->filename : $item->name); ?>
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
						<?php
						if ($item->type != 'folder')
						{ ?>
						<?php if ($canDownload && $this->config->direct_download) { ?>
						<?php if ($download->ismodal) { ?>
                        <a class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>"
                           href="javascript:void(0)"
                           onclick="rsfiles_show_modal('<?php echo $download->dlink; ?>', '<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>', 600);">
							<?php } else { ?>
                            <a class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>"
                               href="<?php echo $download->dlink; ?>">
								<?php } ?>
								<?php } else { ?>
                                <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>"
                                   class="hasTooltip" title="<?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>">
									<?php } ?>
                                    <i class="fa fa-download fa-fw"></i>
                                </a>

								<?php if ($this->config->show_details) { ?>
                                    <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=details&path=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>"
                                       class="hasTooltip"
                                       title="<?php echo JText::_('COM_RSFILES_DETAILS'); ?>">
                                        <i class="fa fa-list fa-fw"></i>
                                    </a>
								<?php } ?>

								<?php if ($canDownload) { ?>
									<?php $properties = rsfilesHelper::previewProperties($item->id, $item->fullpath); ?>
									<?php $extension = $properties['extension']; ?>
									<?php $size = $properties['size']; ?>

									<?php if (in_array($extension, rsfilesHelper::previewExtensions()) && $item->show_preview) { ?>
                                        <a href="javascript:void(0)"
                                           onclick="rsfiles_show_modal('<?php echo JRoute::_('index.php?option=com_rsfiles&layout=preview&tmpl=component&path=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>', '<?php echo JText::_('COM_RSFILES_PREVIEW'); ?>', <?php echo $size['height']; ?>, '<?php echo $properties['handler']; ?>');"
                                           class="hasTooltip"
                                           title="<?php echo JText::_('COM_RSFILES_PREVIEW'); ?>">
                                            <i class="fa fa-search fa-fw"></i>
                                        </a>
									<?php } ?>
								<?php } ?>

								<?php if ($canDownload && $this->config->show_bookmark && !$item->FileType) { ?>
                                    <a href="javascript:void(0);" class="hasTooltip"
                                       title="<?php echo rsfilesHelper::isBookmarked($item->fullpath) ? JText::_('COM_RSFILES_NAVBAR_FILE_IS_BOOKMARKED') : JText::_('COM_RSFILES_NAVBAR_BOOKMARK_FILE'); ?>"
                                       onclick="rsf_bookmark('<?php echo JURI::root(); ?>','<?php echo $this->escape(addslashes(urldecode($item->fullpath))); ?>','<?php echo $this->briefcase ? 1 : 0; ?>','<?php echo $this->app->input->getInt('Itemid', 0); ?>', this)">
                                        <i class="<?php echo rsfilesHelper::isBookmarked($item->fullpath) ? 'fa' : 'far'; ?> fa-bookmark fa-fw"></i>
                                    </a>
								<?php } ?>

								<?php if ($canDelete) { ?>
                                    <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&task=rsfiles.delete&path=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>"
                                       class="hasTooltip"
                                       title="<?php echo JText::_('COM_RSFILES_NAVBAR_DELETE'); ?>"
                                       onclick="if (!confirm('<?php echo JText::_('COM_RSFILES_DELETE_FILE_MESSAGE', true); ?>')) return false;">
                                        <i class="fa fa-trash fa-fw"></i>
                                    </a>
								<?php } ?>

								<?php } else if ($item->type == 'folder') { ?>

									<?php if ($canDelete) { ?>
                                        <a href="<?php echo JRoute::_('index.php?option=com_rsfiles&task=rsfiles.delete&folder=' . rsfilesHelper::encode($item->fullpath) . $this->itemid); ?>"
                                           class="hasTooltip"
                                           title="<?php echo JText::_('COM_RSFILES_NAVBAR_DELETE'); ?>"
                                           onclick="if (!confirm('<?php echo JText::_('COM_RSFILES_DELETE_MESSAGE', true); ?>')) return false;">
                                            <i class="fa fa-trash fa-fw"></i>
                                        </a>
									<?php } ?>

								<?php } ?>
                    </td>
					<?php if ($this->config->list_show_date) { ?>
                        <td style="text-align: center;"><?php if ($item->type != 'folder') echo $item->dateadded; ?></td><?php } ?>
                </tr>
			<?php } ?>
			<?php } else { ?>
                <tr>
                    <td colspan="4"><?php echo JText::_('COM_RSFILES_NO_FILES'); ?></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>

        <input type="hidden" name="task" value="" />
        <input type="hidden" name="filter_order" value="<?php echo $this->escape($this->listOrder); ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->escape($this->listDirn); ?>" />
    </form>

	<?php
	if (JFactory::getApplication()->scope !== 'mod_rsfiles_newest' && JFactory::getApplication()->scope !== 'mod_rsfiles_list_tags')
	{
		if (($this->config->show_pagination_position == 0 || $this->config->show_pagination_position == 2) && $this->pagination->pagesTotal > 1)
		{ ?>
            <div class="com-rsfiles-files_navigation w-100 <?php echo !rsfilesHelper::isJ4() ? 'pagination' : ''; ?>">
                <p class="com-rsfiles-files_counter <?php echo RSFilesAdapterGrid::styles(array('counter', 'pull-right')); ?>">
					<?php echo $this->pagination->getPagesCounter(); ?>
                </p>
                <div class="com-rsfiles-files_pagination">
					<?php echo $this->pagination->getPagesLinks(); ?>
                </div>
            </div>
		<?php }
	} ?>
</div>

<?php if ($this->config->modal == 1) echo JHtml::_('bootstrap.renderModal', 'rsfRsfilesModal', array('title' => '', 'bodyHeight' => 70)); ?>
