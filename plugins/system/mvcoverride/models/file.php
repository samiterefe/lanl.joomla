<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');

class rsfilesModelFile extends JModelAdmin
{
	protected $text_prefix = 'COM_RSFILES';

	/**
	 * Returns a Table object, always creating it.
	 *
	 * @param type    The table type to instantiate
	 * @param string    A prefix for the table class name. Optional.
	 * @param array    Configuration array for model. Optional.
	 *
	 * @return    JTable    A database object
	 */

	public function getTable($type = 'File', $prefix = 'rsfilesTable', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get a single record.
	 *
	 * @param integer    The id of the primary key.
	 *
	 * @return    mixed    Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{

			if ($item->publish_down == JFactory::getDbo()->getNullDate())
			{
				$item->publish_down = '';
			}

			if (!empty($item->ScreenshotsTags))
				$item->ScreenshotsTags = explode(',', $item->ScreenshotsTags);

			if (!empty($item->CanCreate))
				$item->CanCreate = explode(',', $item->CanCreate);

			if (!empty($item->CanUpload))
				$item->CanUpload = explode(',', $item->CanUpload);

			if (!empty($item->CanDelete))
				$item->CanDelete = explode(',', $item->CanDelete);

			if (!empty($item->CanEdit))
				$item->CanEdit = explode(',', $item->CanEdit);

			if (!empty($item->CanDownload))
				$item->CanDownload = explode(',', $item->CanDownload);

			if (!empty($item->CanView))
				$item->CanView = explode(',', $item->CanView);

			if (!empty($item->IdFile))
			{
				if ($item->FileType)
				{
					$item->type = 'remote';
				}
				else
				{
					$item->type = 'local';
				}
			}
			else $item->type = 'remote';

			if ($item->DownloadLimit == 0)
				$item->DownloadLimit = '';

			if (empty($item->IdFile))
			{
				$item->FileParent = urldecode(JFactory::getApplication()->input->getString('parent', ''));
			}

			if (empty($item->icon))
			{
				$ext = strtolower(rsfilesHelper::getExt($item->FilePath));

				if (in_array($ext, rsfilesHelper::fileExtensions()))
				{
					$item->icon = $ext;
				}
			}

			$item->itemType = rsfilesHelper::getType($item->IdFile);
			$item->tags     = rsfilesHelper::getTags($item->IdFile);

			// Get File Status Data
			$db    = $this->getDbo();
			$query = $db->getQuery(true);
			$query
				->select('*')
				->from($db->qn('#__rsfiles_file_status'))
				->where($db->qn('FileId') . ' = ' . $db->q($item->IdFile));
			$db->setQuery($query);
			$FileStatusResult = $db->loadObject();

			// Add in File Status Data
			if ($FileStatusResult)
			{
				$item->FileRelatedToStatus = $FileStatusResult->FileRelatedToStatus;
				$item->FileStatus          = $FileStatusResult->FileStatus;
				if ($item->FileStatus == '1' && $FileStatusResult->DateRelatedToStatus == '1111-11-11 00:00:00')
				{
					$item->DateRelatedToStatus = '';
				}
				else
				{
					$item->DateRelatedToStatus = $FileStatusResult->DateRelatedToStatus;
				}
			}

			// Add in extra info.
			if ($item->IdFile)
			{
				$ExtraInfo = self::getExtraInfo($item->IdFile);

				// add extra info
				$item->FileDisplayAsNew = ($ExtraInfo) ? $ExtraInfo->FileDisplayAsNew : "1";
			}
		}

		return $item;
	}

	private function getExtraInfo($IdFile)
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query
			->select('*')
			->from($db->qn('#__rsfiles_extra_info'))
			->where($db->qn('IdFile') . ' = ' . $IdFile);
		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Method to get the record form.
	 *
	 * @param array   $data     Data for the form.
	 * @param boolean $loadData True if the form is to load its own data (default case), false if not.
	 *
	 * @return    mixed    A JForm object on success, false on failure
	 * @since    1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{

		JPluginHelper::importPlugin('editors-xtd', 'rsfiles');
		$plugin = JPluginHelper::getPlugin('editors-xtd', 'rsfiles');


		// Read our custom form into a variable so it overrides forms in path
		$xml = file_get_contents(JPATH_ROOT . '/plugins/system/mvcoverride/models/forms/file.xml');

		// Get the form.
		$form = $this->loadForm('com_rsfiles.file', $xml, array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form))
			return false;

		$type = rsfilesHelper::getType($form->getValue('IdFile', null, 0));
		if ($type == 'external')
		{
			$form->setFieldAttribute('FilePath', 'required', 'true');
			$form->setFieldAttribute('FilePath', 'readonly', 'false');
			$form->setValue('FileType', null, 1);
		}

		if (rsfilesHelper::isJ4())
		{
			$form->setFieldAttribute('CanView', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('CanDownload', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('CanEdit', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('CanCreate', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('CanUpload', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('CanDelete', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('tags', 'layout', 'joomla.form.field.list-fancy-select');
			$form->setFieldAttribute('ScreenshotsTags', 'layout', 'joomla.form.field.list-fancy-select');
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return    mixed    The data for the form.
	 * @since    1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_rsfiles.edit.file.data', array());

		if (empty($data))
			$data = $this->getItem();

		return $data;
	}

	/**
	 * Method to get Tabs
	 *
	 * @return    mixed    The Joomla! Tabs.
	 * @since    1.6
	 */
	public function getTabs()
	{
		$tabs = new RSFilesAdapterTabs('file');

		return $tabs;
	}

	/**
	 * Method to get the available layouts.
	 *
	 * @return    mixed    The available layouts.
	 * @since    1.6
	 */
	public function getLayouts()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$id    = JFactory::getApplication()->input->getInt('IdFile', 0);
		$root  = rsfilesHelper::getConfig(rsfilesHelper::getRoot() . '_folder');
		$ds    = rsfilesHelper::ds();

		$query->clear()
			->select($db->qn('FilePath'))->select($db->qn('DownloadMethod'))
			->from($db->qn('#__rsfiles_files'))
			->where($db->qn('IdFile') . ' = ' . $id);
		$db->setQuery($query);
		$file = $db->loadObject();

		if (rsfilesHelper::getRoot() == 'briefcase')
		{
			if (!empty($file->FilePath) && is_dir($root . $ds . $file->FilePath))
			{
				$fields = array('general');
			}
			else
			{
				$fields = array('general', 'metadata', 'mirrors', 'screenshots');
			}
		}
		else
		{
			if (!empty($file->FilePath) && is_dir($root . $ds . $file->FilePath))
			{
				$fields = array('general', 'permissions');
			}
			else
			{
				$fields = array('general', 'permissions', 'metadata', 'mirrors', 'screenshots');

				if (!empty($file->DownloadMethod))
				{
					$fields[] = 'emails';
				}
			}
		}

		return $fields;
	}

	/**
	 * Method to get file mirrors.
	 *
	 * @return    mixed    The available mirrors.
	 * @since    1.6
	 */
	public function getMirrors()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$id    = JFactory::getApplication()->input->getInt('IdFile', 0);

		$query->clear()
			->select('*')
			->from($db->qn('#__rsfiles_mirrors'))
			->where($db->qn('IdFile') . ' = ' . $id);
		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 * Method to save file mirrors.
	 */
	public function mirror()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$input = JFactory::getApplication()->input;
		$id    = $input->getInt('id', 0);
		$name  = $input->getString('name', '');
		$url   = $input->getString('url', '');
		$type  = $input->get('type', '');
		$data  = new stdClass();


		if ($type == 'update')
		{
			$query->clear()
				->update($db->qn('#__rsfiles_mirrors'))
				->set($db->qn('MirrorName') . ' = ' . $db->q($name))
				->set($db->qn('MirrorURL') . ' = ' . $db->q($url))
				->where($db->qn('IdMirror') . ' = ' . $id);

			$db->setQuery($query);
			$db->execute();

			return $id;
		}
		else
		{
			$query->clear()
				->insert($db->qn('#__rsfiles_mirrors'))
				->set($db->qn('MirrorName') . ' = ' . $db->q($name))
				->set($db->qn('MirrorURL') . ' = ' . $db->q($url))
				->set($db->qn('IdFile') . ' = ' . $id);

			$db->setQuery($query);
			$db->execute();

			return $db->insertid();
		}
	}

	/**
	 * Method to delete file mirrors.
	 */
	public function deletemirror($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->clear()
			->delete()
			->from($db->qn('#__rsfiles_mirrors'))
			->where($db->qn('IdMirror') . ' = ' . $id);
		$db->setQuery($query);

		return $db->execute();
	}

	/**
	 * Method to get a file mirror.
	 */
	public function getmirror($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->clear()
			->select('*')
			->from($db->qn('#__rsfiles_mirrors'))
			->where($db->qn('IdMirror') . ' = ' . $id);
		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Method to get screenshots.
	 */
	public function getScreenshots()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$id    = JFactory::getApplication()->input->getInt('IdFile', 0);

		$query->clear()
			->select('*')
			->from($db->qn('#__rsfiles_screenshots'))
			->where($db->qn('IdFile') . ' = ' . $id);
		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 *    Method to delete a file screenshot
	 */
	public function deletescreenshot($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->clear()
			->select($db->qn('Path'))
			->from($db->qn('#__rsfiles_screenshots'))
			->where($db->qn('IdScreenshot') . ' = ' . $id);
		$db->setQuery($query);
		if ($path = $db->loadResult())
		{
			if (JFile::exists(JPATH_SITE . '/components/com_rsfiles/images/screenshots/' . $path))
			{
				if (JFile::delete(JPATH_SITE . '/components/com_rsfiles/images/screenshots/' . $path))
				{
					$query->clear()->delete()->from($db->qn('#__rsfiles_screenshots'))->where($db->qn('IdScreenshot') . ' = ' . $id);
					$db->setQuery($query);
					$db->execute();

					return true;
				}
			}
		}

		return false;
	}

	/**
	 *    Method to delete the file poster
	 */
	public function deleteposter($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->clear()
			->select($db->qn('poster'))
			->from($db->qn('#__rsfiles_files'))
			->where($db->qn('IdFile') . ' = ' . (int) $id);

		$db->setQuery($query);
		$poster = $db->loadResult();

		if (!empty($poster))
		{
			if (JFile::exists(JPATH_SITE . '/components/com_rsfiles/images/poster/' . $poster))
			{
				if (JFile::delete(JPATH_SITE . '/components/com_rsfiles/images/poster/' . $poster))
				{
					$query->clear()
						->update($db->qn('#__rsfiles_files'))
						->set($db->qn('poster') . ' = ' . $db->q(''))
						->where($db->qn('IdFile') . ' = ' . (int) $id);
					$db->setQuery($query);
					$db->execute();

					return true;
				}
			}
		}

		$this->setError(JText::_('COM_RSFILES_POSTER_DELETE_ERROR'));

		return false;
	}

	/**
	 *    Method to delete the file thumb
	 */
	public function deletethumb($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->clear()
			->select($db->qn('FileThumb'))
			->from($db->qn('#__rsfiles_files'))
			->where($db->qn('IdFile') . ' = ' . (int) $id);

		$db->setQuery($query);
		$thumb = $db->loadResult();

		if (!empty($thumb))
		{
			if (JFile::exists(JPATH_SITE . '/components/com_rsfiles/images/thumbs/files/' . $thumb))
			{
				if (JFile::delete(JPATH_SITE . '/components/com_rsfiles/images/thumbs/files/' . $thumb))
				{
					$query->clear()
						->update($db->qn('#__rsfiles_files'))
						->set($db->qn('FileThumb') . ' = ' . $db->q(''))
						->where($db->qn('IdFile') . ' = ' . (int) $id);
					$db->setQuery($query);
					$db->execute();

					return true;
				}
			}
		}

		$this->setError(JText::_('COM_RSFILES_THUMB_DELETE_ERROR'));

		return false;
	}

	/**
	 *    Method to delete the file preview
	 */
	public function deletepreview($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->clear()
			->select($db->qn('preview'))
			->from($db->qn('#__rsfiles_files'))
			->where($db->qn('IdFile') . ' = ' . (int) $id);

		$db->setQuery($query);
		$preview = $db->loadResult();

		if (!empty($preview))
		{
			if (JFile::exists(JPATH_SITE . '/components/com_rsfiles/images/preview/' . $preview))
			{
				if (JFile::delete(JPATH_SITE . '/components/com_rsfiles/images/preview/' . $preview))
				{
					$query->clear()
						->update($db->qn('#__rsfiles_files'))
						->set($db->qn('preview') . ' = ' . $db->q(''))
						->where($db->qn('IdFile') . ' = ' . (int) $id);
					$db->setQuery($query);
					$db->execute();

					return true;
				}
			}
		}

		$this->setError(JText::_('COM_RSFILES_PREVIEW_DELETE_ERROR'));

		return false;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param array $data The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	public function save($data)
	{
		// Add path for FileStatus table:
		JTable::addIncludePath(JPATH_ROOT . '/plugins/system/mvcoverride/tables');

		// Initialise variables;
		$FileStatusTable = $this->getTable('FileStatus');
		$ExtraInfoTable  = $this->getTable('ExtraInfo');
		$table           = $this->getTable();
		$pk              = (!empty($data['IdFile'])) ? $data['IdFile'] : (int) $this->getState($this->getName() . '.id');
		$data['FileId']  = $pk;
		$isNew           = true;

		// Load the row if saving an existing tag.
		if ($pk > 0)
		{
			$table->load($pk);
			// Check if there's a File Status Entry, get the pk and use it for the rest of the code
			$db    = $table->getDbo();
			$query = $db->getQuery(true);
			$query
				->select($db->qn('id'))
				->from($db->qn('#__rsfiles_file_status'))
				->where($db->qn('FileId') . ' = ' . $db->q($pk));
			$db->setQuery($query);
			$FileStatusPrimaryKey = $db->loadResult();

			if ($data['FileStatus'] === '')
			{
				// We're deleting existing File Status information from the database
				$deleteFileStatus = true;
			}
			elseif ($data['FileStatus'] !== '' && $FileStatusPrimaryKey)
			{
				// We're updating an existing File Status that's in the database

				// Check if Were saving a date or a related file
				if (($data['DateRelatedToStatus'] != '1111-11-11 00:00:00') && ($data['DateRelatedToStatus'] != '') && $data['FileStatus'] !== '1')
				{
					$data['FileRelatedToStatus'] = '';
				}
				elseif ($data['FileRelatedToStatus'] && ($data['FileStatus'] != '1' || ($data['FileStatus'] == '1') && is_null($data['DateRelatedToStatus'])))
				{
					$data['DateRelatedToStatus'] = '1111-11-11 00:00:00';
				}
				elseif ($data['FileStatus'] === "3" && !$data['FileRelatedToStatus'])
				{
					// We're removing a previously saved file from the Catch-All option
					$data['FileRelatedToStatus'] = '';
					$data['DateRelatedToStatus'] = '1111-11-11 00:00:00';
				}
				elseif ($data['FileStatus'] === "1" && $data['FileRelatedToStatus'] && ($data['DateRelatedToStatus'] != '1111-11-11 00:00:00') && ($data['DateRelatedToStatus'] != ''))
				{
					//were saving a file and a date do nothing
				}
				$FileStatusTable->load($FileStatusPrimaryKey);
			}
			$isNew = false;
		}

		// Bind the data.
		if (!$table->bind($data))
		{
			$this->setError($table->getError());

			return false;
		}

		if (!$deleteFileStatus)
		{
			if (!$FileStatusTable->bind($data))
			{
				$this->setError($FileStatusTable->getError());

				return false;
			}
		}

		// Check the data.
		if (!$table->check())
		{
			$this->setError($table->getError());

			return false;
		}


		// Store the data.
		if (!$table->store())
		{
			$this->setError($table->getError());

			return false;
		}

		JPluginHelper::importPlugin('finder');
		JFactory::getApplication()->triggerEvent('onFinderAfterSave', array('com_rsfiles.file', $table, $isNew));

		if (!$deleteFileStatus)
		{
			if (!$FileStatusTable->store())
			{
				$this->setError($FileStatusTable->getError());

				return false;
			}
		}
		else
		{
			if ($FileStatusPrimaryKey) // last minute check to see if we need to delete a row in the file status table or not
			{
				if (!$FileStatusTable->delete($FileStatusPrimaryKey))
				{
					$this->setError($FileStatusTable->getError());

					return false;
				}
			}
		}

		//load & bind existing data row already exist in table
		$ExtraInfoTable->load(array('IdFile' => $data['FileId']));

		//bind new data
		if (!$ExtraInfoTable->bind($data))
		{
			$this->setError($ExtraInfoTable->getError());

			return false;
		}

		if (!$ExtraInfoTable->store())
		{
			$this->setError($ExtraInfoTable->getError());

			return false;
		}
		rsfilesHelper::upload($table->IdFile);
		rsfilesHelper::tags($table->IdFile);

		$this->setState($this->getName() . '.id', $table->IdFile);

		return true;
	}

	public function getDownloads()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$id    = JFactory::getApplication()->input->getInt('IdFile', 0);

		$query->clear()
			->select('*')
			->from($db->qn('#__rsfiles_email_downloads'))
			->where($db->qn('IdFile') . ' = ' . $id)
			->order($db->qn('date') . ' DESC');
		$db->setQuery($query);

		return $db->loadObjectList();
	}

	public function deletedownloads($pks)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$pks = array_map('intval', $pks);

		$query->delete($db->qn('#__rsfiles_email_downloads'))
			->where($db->qn('id') . ' IN (' . implode(',', $pks) . ')');
		$db->setQuery($query);
		$db->execute();
	}

	public function export($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('*')
			->from($db->qn('#__rsfiles_email_downloads'))
			->where($db->qn('IdFile') . ' = ' . $db->q($id));
		$db->setQuery($query);
		if ($downloads = $db->loadObjectList())
		{
			ob_end_clean();

			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=export.csv");
			header("Pragma: no-cache");
			header("Expires: 0");

			foreach ($downloads as $download)
			{
				echo '"' . $download->name . '","' . $download->email . '","' . $download->date . '","' . $download->downloaded . '"' . "\n";
			}

			JFactory::getApplication()->close();
		}

		return false;
	}

	public function renameFile($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$app   = JFactory::getApplication();
		$input = $app->input;
		$root  = rsfilesHelper::getConfig(rsfilesHelper::getRoot() . '_folder');
		$ds    = rsfilesHelper::ds();
		$path  = $input->getString('path');
		$new   = $input->getString('new_name');

		$fullpath    = $root . $ds . $path;
		$filename    = basename($fullpath);
		$newfullpath = substr_replace($fullpath, $new, -strlen($filename));

		if (file_exists($newfullpath))
		{
			$app->enqueueMessage(JText::_('COM_RSFILES_FILE_RENAME_EXISTS'), 'error');

			return false;
		}

		if (!rename($fullpath, $newfullpath))
		{
			$app->enqueueMessage(JText::_('COM_RSFILES_FILE_RENAME_ERROR'), 'error');

			return false;
		}

		$newpath = str_replace($root . $ds, '', $newfullpath);

		$query->clear()
			->update($db->qn('#__rsfiles_files'))
			->set($db->qn('FilePath') . ' = ' . $db->q($newpath))
			->where($db->qn('IdFile') . ' = ' . $db->q($id));
		$db->setQuery($query);
		$db->execute();

		return true;
	}
}
