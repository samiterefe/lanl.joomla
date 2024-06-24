<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die('Restricted access');
$this->download->dlink = $this->download->dlink . '&relatedfile=' . JFactory::getApplication()->input->get('relatedfile');
if (!empty($this->file->DownloadLimit) && $this->file->Downloads >= $this->file->DownloadLimit) $this->candownload = false; ?>

<div class="page-header">
    <h1><?php echo $this->file->fname; ?></h1>
</div>

<div class="rsfiles-layout">
	<?php echo $this->loadTemplate('navbar'); ?>

    <div class="<?php echo RSFilesAdapterGrid::card(); ?> mb-3">
        <div class="card-body">
			<?php if ($this->candownload) { ?>
				<?php $properties = rsfilesHelper::previewProperties($this->file->IdFile, $this->file->fullpath); ?>
				<?php $extension = $properties['extension']; ?>
				<?php $size = $properties['size']; ?>
                <p><?php echo JText::_('View or Download directly from our server'); ?>:</p>
                <p>
					<?php if (in_array($extension, rsfilesHelper::previewExtensions()) && $this->file->show_preview) { ?>
                        <a href="javascript:void(0)"
                           onclick="saveViewedAndShowModal('<?php echo JRoute::_('index.php?option=com_rsfiles&layout=preview&relatedfile=' . JFactory::getApplication()->input->get('relatedfile', 0) . '&tmpl=component&path=' . rsfilesHelper::encode($this->file->fullpath) . $this->itemid); ?>', '<?php echo JText::_('COM_RSFILES_PREVIEW'); ?>', <?php echo $size['height']; ?>, '<?php echo $properties['handler']; ?>', <?php echo $this->file->IdFile; ?>);"
                           class="btn btn-primary btn-large"
                           title="<?php echo JText::_('COM_RSFILES_PREVIEW'); ?>"><i
                                    class="fa fa-search fa-fw"></i><?php echo JText::_('View'); ?></a>
					<?php } ?>
                    <a class="btn btn-primary btn-large"
                       href="<?php echo $this->download->dlink; ?>"
                       onclick="saveDownloaded(<?php echo $this->file->IdFile; ?>)">
                        <i class="fa fa-download"></i> <?php echo JText::_('COM_RSFILES_DOWNLOAD'); ?>
                    </a>
                </p>

				<?php if (!empty($this->mirrors)) { ?>
                    <p><?php echo JText::_('COM_RSFILES_DOWNLOAD_FROM_MIRRORS'); ?> :</p>
					<?php foreach ($this->mirrors as $mirror) { ?>
                        <a class="btn btn-info"
                           target="_blank"
                           href="<?php echo $mirror->MirrorURL; ?>"><?php echo $mirror->MirrorName; ?></a>
					<?php } ?>
				<?php } ?>
			<?php } else { ?>
                <i class="fa fa-exclamation-triangle"></i> <?php echo JText::_('COM_RSFILES_DOWNLOAD_PERMISSION_ERROR'); ?>
			<?php } ?>
        </div>
    </div>

	<?php if (!empty($this->screenshots)) { ?>
		<?php foreach ($this->screenshots as $screenshot) { ?>
            <ul class="thumbnails <?php echo RSFilesAdapterGrid::row(); ?> <?php echo RSFilesAdapterGrid::styles(array('unstyled', 'center')); ?>">
				<?php foreach ($screenshot as $path) { ?>
                    <li class="<?php echo RSFilesAdapterGrid::column(3); ?>">
                        <a href="javascript:void(0)"
                           onclick="rsfiles_show_modal('<?php echo JURI::root(); ?>components/com_rsfiles/images/screenshots/<?php echo $path; ?>', '&nbsp;', 800);"
                           class="thumbnail">
                            <img src="<?php echo JURI::root(); ?>components/com_rsfiles/images/screenshots/<?php echo $path; ?>"
                                 alt=""
                                 style="width:160px; height:120px;"/>
                        </a>
                    </li>
				<?php } ?>
            </ul>
		<?php } ?>
	<?php } ?>

    <div class="clearfix"></div>

	<?php if (rsfilesHelper::gallery())
	{
		$gallery = RSMediaGalleryIntegration::getInstance();
		if (!empty($this->file->ScreenshotsTags))
		{
			echo $gallery->display($this->file->ScreenshotsTags, array('thumb_width' => '160', 'thumb_height' => '120'), 'default');
		}
	} ?>

	<?php if ($this->config->show_file_size || $this->config->show_date_added || $this->config->show_date_updated || $this->config->show_license || $this->config->show_file_version || $this->config->show_number_of_downloads) { ?>
        <h3 class="page-header mt-3"><?php echo JText::_('COM_RSFILES_DETAILS'); ?></h3>
        <div class="<?php echo RSFilesAdapterGrid::styles(array('muted')); ?>">
            <div class="<?php echo RSFilesAdapterGrid::styles(array('pull-left')); ?>">
				<?php if ($this->config->show_file_size && !empty($this->file->filesize)) { ?>
                    <i class="fa fa-file fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_SIZE'); ?>: <?php echo $this->file->filesize; ?>
                    <br/>
				<?php } ?>

				<?php if ($this->config->show_date_added && !empty($this->file->dateadded)) { ?>
                    <i class="fa fa-calendar-alt fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_DATE_ADDED'); ?>: <?php echo $this->file->dateadded; ?>
                    <br/>
				<?php } ?>

				<?php if ($this->config->show_date_updated && !empty($this->file->lastmodified)) { ?>
                    <i class="fa fa-calendar-alt fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_LAST_MODIFIED'); ?>: <?php echo $this->file->lastmodified; ?>
                    <br/>
				<?php } ?>
            </div>

            <div class="<?php echo RSFilesAdapterGrid::styles(array('pull-right')); ?>">
				<?php if ($this->config->show_license && !empty($this->file->filelicense)) { ?>
                    <i class="fa fa-flag fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_LICENSE'); ?>: <a
                            href="javascript:void(0)"
                            onclick="rsfiles_show_modal('<?php echo JRoute::_($this->file->filelicense, false); ?>', '<?php echo $this->file->LicenseName; ?>', 600)"><?php echo $this->file->LicenseName; ?></a>
                    <br/>
				<?php } ?>

				<?php if ($this->config->show_file_version && !empty($this->file->fileversion)) { ?>
                    <i class="fa fa-code-branch fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_VERSION'); ?>: <?php echo $this->file->fileversion; ?>
                    <br/>
				<?php } ?>

				<?php if ($this->config->show_hits && !empty($this->file->hits) && !$this->briefcase) { ?>
                    <i class="fa fa-eye fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_HITS'); ?>: <?php echo $this->file->hits; ?>
                    <br/>
				<?php } ?>

				<?php if ($this->config->show_number_of_downloads && !empty($this->file->downloads) && !$this->briefcase) { ?>
                    <i class="fa fa-download fa-fw"></i> <?php echo JText::_('COM_RSFILES_FILE_DOWNLOADS'); ?>: <?php echo $this->file->downloads; ?>
                    <br/>
				<?php } ?>
            </div>
        </div>
        <div class="clearfix"></div>
	<?php } ?>

	<?php if ($this->config->show_descriptions && !empty($this->file->FileDescription)) { ?>
        <h3 class="page-header mt-3"><?php echo JText::_('COM_RSFILES_DESCRIPTION'); ?></h3>
		<?php echo $this->file->FileDescription; ?>
	<?php } ?>
</div>

<?php if ($this->config->modal == 1) echo JHtml::_('bootstrap.renderModal', 'rsfRsfilesModal', array('title' => '', 'bodyHeight' => 70)); ?>
<script>
    async function saveViewedAndShowModal(url, title, height, handler, fileId) {
        await fetch(document.location.origin + "/api/index.php/v1/rsfilesreports/save/document/viewed", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(({
                file_id: fileId
            }))
        }).then(res => {
            if (res.ok) {
                rsfiles_show_modal(url, title, height, handler);
            } else throw new Error('Error saving that file was viewed');
        }).catch(err => {
                console.log(err.message);
            }
        );
    }

    async function saveDownloaded(fileId) {
        await fetch(document.location.origin + "/api/index.php/v1/rsfilesreports/save/document/downloaded", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(({
                file_id: fileId
            }))
        }).then(res => {
            if (!res.ok) {
                throw new Error('Error saving that file was downloaded');
            }
        }).catch(err => {
                console.log(err.message);
            }
        );
    }

    jQuery(document).ready(function () {
        jQuery('.fa-bookmark').click(function () {
            setTimeout(function () {
                window.parent.location.reload();
            }, 2000)
        });
    });
</script>
