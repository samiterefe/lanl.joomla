<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.path');

use Joomla\Archive\Archive;

class rsfilesModelRsfiles extends JModelLegacy
{
	protected $_total = null;
	protected $_totalTags = null;
	protected $_pagination = null;
	protected $_folder = null;
	protected $input = null;
	protected $ds = null;

	// Main controller
	public function __construct()
	{
		parent::__construct();

		$this->ds    = rsfilesHelper::ds();
		$this->input = JFactory::getApplication()->input;

		// Set the root
		$this->setRoot();

		// Set the folder
		$this->setFolder();

		// Set statistics
		rsfilesHelper::statistics($this->absoluteFolder, $this->relativeFolder);

		// Set limit and limitstart
		$limit      = rsfilesHelper::getConfig('nr_per_page');
		$limitstart = $this->input->getInt('limitstart', 0);

		// In case limit has been changed, adjust it
		$limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

		$this->setState('com_rsfiles.' . $this->input->get('layout') . '.limit', $limit);
		$this->setState('com_rsfiles.' . $this->input->get('layout') . '.limitstart', $limitstart);
	}

	// Get the folders / files list
	public function getItems()
	{
		$config = rsfilesHelper::getConfig();

		if ($config->use_cache)
		{
			$id      = md5($this->absoluteFolder . $this->getOrder() . $this->getOrderDir());
			$default = rsfilesHelper::isJ4() ? JPATH_CACHE : JPATH_SITE . '/cache';
			$options = array('storage' => 'file', 'defaultgroup' => 'com_rsfiles_data', 'cachebase' => realpath(JFactory::getConfig()->get('cache_path', $default)), 'caching' => true);
			$cache   = JCache::getInstance('output', $options);

			if ($cache->contains($id))
			{
				$data = $cache->get($id);
			}
			else
			{
				$data = $this->getData($this->absoluteFolder, $this->getOrder(), $this->getOrderDir());
				$cache->store($data, $id);
			}
		}
		else
		{
			$data = $this->getData($this->absoluteFolder, $this->getOrder(), $this->getOrderDir());
		}

		$this->_total = count($data);

		// If we are not in a module then adjust the size using the components setting
		if (JFactory::$application->scope !== 'mod_rsfiles_newest' && JFactory::$application->scope !== 'mod_rsfiles_list_tags')
		{
			// Adjust the size of the list
			if ($this->input->get('format', '') != 'feed')
			{
				$data = array_slice($data, $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limitstart'), $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limit'));
			}
		}

		return $data;
	}

	public static function getData($absoluteFolder, $order, $direction)
	{
		require_once(JPATH_ROOT . '/plugins/system/mvcoverride/helpers/files.php');

		$class    = new RSFilesFiles($absoluteFolder, 'site', rsfilesHelper::getItemid(), 0, $order, $direction);
		$files    = $class->getFiles();
		$folders  = $class->getFolders();
		$external = $class->getExternal();

		// If we are in the module filter the results to only those that are new
		if (JFactory::$application->scope == 'mod_rsfiles_newest')
		{

			// get only the files that are new
			$files = array_filter($files, function ($var) {
				return $var->isnew;
			});

			//remove any that files that have been set not to display in newest module
			$files = array_filter($files, function ($var) {
				return $var->FileDisplayAsNew;
			});

			// don't display any folders
			$folders = [];
		}

		return array_merge($folders, $files, $external);
	}

	public function getTaggedItems()
	{
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$params = rsfilesHelper::getParams();
		if (JFactory::getApplication()->scope == 'mod_rsfiles_list_tags')
		{
			$tags = JFactory::getApplication()->getUserState('mod_rsfiles_list_tags.params')->get('tags');
			$params->set('tags', $tags);
		}
		$tags  = $params->get('tags');
		$tag   = $this->getTag();
		$array = array();

		if (!empty($tags))
		{
			$tags    = array_map('intval', $tags);
			$dld_fld = realpath(rsfilesHelper::getConfig('download_folder')) . $this->ds;

			if ($params->get('filter', 0) && $tag)
			{
				if (in_array($tag, $tags))
				{
					$tags = array($tag);
				}
			}

			$query->select('DISTINCT ' . $db->qn('f.FilePath'))
				->from($db->qn('#__rsfiles_files', 'f'))
				->join('LEFT', $db->qn('#__rsfiles_tag_relation', 'tr') . ' ON ' . $db->qn('f.IdFile') . ' = ' . $db->qn('tr.file'))
				->join('LEFT', $db->qn('#__rsfiles_tags', 't') . ' ON ' . $db->qn('t.id') . ' = ' . $db->qn('tr.tag'))
				->where($db->qn('t.published') . ' = 1')
				->where($db->qn('f.briefcase') . ' = 0')
				->where($db->qn('f.FileType') . ' = 0')
				->where($db->qn('tr.tag') . ' IN (' . implode(',', $tags) . ')');
			$db->setQuery($query);
			if ($files = $db->loadColumn())
			{
				foreach ($files as $file)
				{
					if (is_file($dld_fld . $file))
					{
						$array[] = $dld_fld . $file;
					}
				}
			}

			require_once JPATH_SITE . '/plugins/system/mvcoverride/helpers/files.php';

			$class    = new RSFilesFiles($array, 'tags', rsfilesHelper::getItemid(), 0, $params->get('order', 'name'), $params->get('order_way', 'ASC'));
			$files    = $class->getFiles();
			$external = $class->getExternal();

			$data             = array_merge($files, $external);
			$this->_totalTags = count($data);

			return array_slice($data, $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limitstart'), $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limit'));
		}

		return array();
	}

	// Get search results
	public function getResults()
	{
		$search = trim($this->input->getString('filter_search', ''));
		$type   = rsfilesHelper::getConfig('search_type');

		if (empty($search))
		{
			return;
		}

		if ($type)
		{
			$db              = JFactory::getDbo();
			$query           = $db->getQuery(true);
			$text            = $db->q('%' . $db->escape($search, true) . '%', false);
			$download_folder = rsfilesHelper::isBriefcase() ? rsfilesHelper::getConfig('briefcase_folder') : rsfilesHelper::getConfig('download_folder');
			$ds              = rsfilesHelper::ds();
			$files           = array();
			$theorder        = $this->input->getString('rsfl_ordering', 'name');
			$thedirection    = $this->input->getString('rsfl_ordering_direction', 'ASC');
			$thedirection    = strtoupper($thedirection);
			$from            = rsfilesHelper::isBriefcase() ? 1 : 0;

			$query->clear()
				->select($db->qn('IdFile'))->select($db->qn('FilePath'))
				->select($db->qn('FileName'))->select($db->qn('FileDescription'))
				->select($db->qn('FileType'))->select($db->qn('DateAdded'))
				->select($db->qn('published'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('briefcase') . ' = ' . $db->q($from))
				->where('(' . $db->qn('FileName') . ' LIKE ' . $text . ' OR ' . $db->qn('FileDescription') . ' LIKE ' . $text . ' OR ' . $db->qn('FilePath') . ' LIKE ' . $text . ')');

			if ($this->absoluteRoot != $download_folder)
			{
				$folder = str_replace($download_folder . $ds, '', $this->absoluteRoot);
				$query->where($db->qn('FilePath') . ' LIKE ' . $db->q($folder . $ds . '\%'));
			}

			$db->setQuery($query);
			if ($all_files = $db->loadObjectList())
			{

				require_once JPATH_SITE . '/components/com_rsfiles/helpers/file.php';

				foreach ($all_files as $file)
				{
					if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
						$file->FilePath = str_replace('/', "\\", $file->FilePath);

					$extension = explode($ds, $file->FilePath);
					$extension = end($extension);

					if (JFile::stripExt($extension) == '')
						continue;

					$fullpath = $download_folder . $ds . $file->FilePath;

					// Check for publishing permission
					if ($file->FileType)
					{
						$published = $file->published;
					}
					else
					{
						$published = $file->published;
						$parts     = explode($ds, $file->FilePath);

						if (!empty($parts))
						{
							foreach ($parts as $i => $part)
							{
								$query->clear()->select($db->qn('published'))->from($db->qn('#__rsfiles_files'))->where($db->qn('FilePath') . ' = ' . $db->q(implode($ds, $parts)));
								$db->setQuery($query);
								if ($db->loadResult() === 0) $published = 0;
								array_pop($parts);
							}
						}
					}

					if (!$published)
					{
						continue;
					}

					if (!rsfilesHelper::permissions('CanView', $file->FilePath))
					{
						continue;
					}

					$instance = RSFiles::getInstance($fullpath);
					$element  = $instance->info;

					if (!empty($search))
					{
						$search = strtolower($search);
						$skip   = false;

						if (strpos(strtolower($element->name), $search) === false && strpos(strtolower($element->description), $search) === false)
							$skip = true;

						if ($skip)
							continue;
					}

					$files[] = $element;
				}
			}

			if ($files)
			{
				switch ($theorder)
				{
					default:
					case 'name':
						$files = rsfilesHelper::sort_array_name($files, $thedirection);
						break;

					case 'date':
						if ($thedirection == 'ASC')
							usort($files, array('rsfilesHelper', 'sort_time_asc'));
						if ($thedirection == 'DESC')
							usort($files, array('rsfilesHelper', 'sort_time_desc'));
						break;

					case 'hits':
						if ($thedirection == 'ASC')
							usort($files, array('rsfilesHelper', 'sort_hits_asc'));
						if ($thedirection == 'DESC')
							usort($files, array('rsfilesHelper', 'sort_hits_desc'));
						break;
				}
			}

			return $files;

		}
		else
		{
			$itemid = rsfilesHelper::getItemid();

			require_once JPATH_SITE . '/plugins/system/mvcoverride/helpers/files.php';

			$theclass = new RSFilesFiles($this->absoluteRoot, 'site', $itemid);
			$files    = $theclass->getFiles();
			$folders  = $theclass->getFolders();
			$external = rsfilesHelper::isBriefcase() ? array() : $theclass->getExternal();

			return array_merge($folders, $files, $external);
		}
	}

	// Get total number of files
	public function getTotal()
	{
		return $this->input->get('layout') == 'tags' ? $this->_totalTags : $this->_total;
	}

	// Get the pagination
	public function getPagination()
	{
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination($this->getTotal(), $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limitstart'), $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limit'));
		}

		return $this->_pagination;
	}

	public function getOrder()
	{
		if (JFactory::getApplication()->scope == 'mod_rsfiles_list_tags')
		{
			$params = JFactory::getApplication()->getUserState('mod_rsfiles_list_tags.params');
		}
		else
		{
			$params = rsfilesHelper::getParams();
		}

		$briefcase = rsfilesHelper::isBriefcase() ? '.briefcase' : '';
		
		$order = 'name';
		
		if(is_object($params)){
			
			$order = $params->get('order', 'name');
		}
		return JFactory::getApplication()->getUserStateFromRequest('com_rsfiles' . $briefcase . '.filter_order', 'filter_order', $order);
	}

	public function getOrderDir()
	{
		if (JFactory::getApplication()->scope == 'mod_rsfiles_list_tags')
		{
			$params = JFactory::getApplication()->getUserState('mod_rsfiles_list_tags.params');
		}
		else
		{
			$params = rsfilesHelper::getParams();
		}
		$direction = strtoupper($params->get('order_way', 'desc'));
		$briefcase = rsfilesHelper::isBriefcase() ? '.briefcase' : '';

		return strtoupper(JFactory::getApplication()->getUserStateFromRequest('com_rsfiles' . $briefcase . '.filter_order_Dir', 'filter_order_Dir', $direction));
	}

	// Get the current absolute folder path
	public function getCurrent()
	{
		return $this->absoluteFolder;
	}

	// Get the current relative folder path
	public function getCurrentRelative()
	{
		return $this->relativeFolder;
	}

	// Method to get the edit form info.
	public function getForm()
	{
		jimport('joomla.form.form');

		JForm::addFormPath(JPATH_ADMINISTRATOR . '/components/com_rsfiles/models/forms');
		JForm::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_rsfiles/models/fields');

		// Get a new instance of the edit form
		$form = JForm::getInstance('com_rsfiles.file', 'file', array('control' => 'jform'));

		// Get data
		$data = $this->getFile();

		// Bind data
		$form->bind($data);

		if (isset($data->FileType))
		{
			$form->setFieldAttribute('FilePath', 'required', 'true');
			$form->setFieldAttribute('FilePath', 'readonly', 'false');
			$form->setFieldAttribute('FilePath', 'class', 'span12');
			$form->setFieldAttribute('DownloadName', 'class', 'span12');
		}

		$form->setFieldAttribute('FileName', 'class', 'span12');
		$form->setFieldAttribute('FileVersion', 'class', 'span12');
		$form->setFieldAttribute('IdLicense', 'class', 'span12');
		$form->setFieldAttribute('DownloadMethod', 'class', 'span12');
		$form->setFieldAttribute('DateAdded', 'class', 'span12');
		$form->setFieldAttribute('publish_down', 'class', 'span12');

		if (empty($data->publish_down) || $data->publish_down == JFactory::getDbo()->getNullDate())
			$form->setValue('publish_down', null, '');

		return $form;
	}

	// Save the file
	public function save($data)
	{
		// Initialise variables;
		$config = rsfilesHelper::getConfig();
		$table  = JTable::getInstance('File', 'rsfilesTable');
		$user   = JFactory::getUser();
		$pk     = (!empty($data['IdFile'])) ? $data['IdFile'] : (int) $this->getState($this->getName() . '.id');
		$isNew  = true;

		// Load the row if saving an existing tag.
		if ($pk > 0)
		{
			$table->load($pk);
			$isNew = false;
		}

		if ($config->consent && !$this->input->getInt('consent'))
		{
			$this->setError(JText::_('COM_RSFILES_CONSENT_ERROR'));

			return false;
		}

		// Bind the data.
		if (!$table->bind($data))
		{
			$this->setError($table->getError());

			return false;
		}

		// Check the data.
		if (!$table->check())
		{
			$this->setError($table->getError());

			return false;
		}

		if (rsfilesHelper::isBriefcase())
		{
			$canedit = rsfilesHelper::briefcase('CanUploadBriefcase') || rsfilesHelper::briefcase('CanMaintainBriefcase') ? 1 : 0;
		}
		else
		{
			$canedit = rsfilesHelper::permissions('CanEdit', $table->FilePath) || (rsfilesHelper::briefcase('editown') && $table->IdUser == $user->get('id'));
		}

		if (!$canedit)
		{
			$this->setError(JText::_('COM_RSFILES_CANNOT_SAVE'));

			return false;
		}

		// Store the data.
		if (!$table->store())
		{
			$this->setError($table->getError());

			return false;
		}

		if ($table->FileType)
		{
			$fullpath = $this->absoluteRoot . $this->ds . $table->FileParent;
		}
		else
		{
			$fullpath = $this->absoluteRoot . $this->ds . $table->FilePath;
		}

		$parts = explode($this->ds, $fullpath);
		array_pop($parts);
		$fullpath = implode($this->ds, $parts);
		rsfilesHelper::clearCache($fullpath);

		rsfilesHelper::upload($table->IdFile);
		rsfilesHelper::tags($table->IdFile);

		$this->setState($this->getName() . '.id', $table->IdFile);

		return true;
	}

	// Get file details
	public function getFile()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$now       = JFactory::getDate()->toSql();
		$path      = rsfilesHelper::getPath();
		$config    = rsfilesHelper::getConfig();
		$briefcase = rsfilesHelper::isBriefcase();
		$fullpath  = (JFactory::getApplication()->input->get('relatedfile')) ? $config->download_folder . $this->ds . $path : $this->absoluteRoot . $this->ds . $path;

		$query->select($db->qn('f') . '.*')->select($db->qn('l.LicenseName'))
			->select($db->qn('u.username'))->select($db->qn('u.name'))
			->from($db->qn('#__rsfiles_files', 'f'))
			->join('LEFT', $db->qn('#__rsfiles_licenses', 'l') . ' ON ' . $db->qn('f.IdLicense') . ' = ' . $db->qn('l.IdLicense'))
			->join('LEFT', $db->qn('#__users', 'u') . ' ON ' . $db->qn('f.IdUser') . ' = ' . $db->qn('u.id'));

		if (rsfilesHelper::external($path))
		{
			$query->where($db->qn('f.IdFile') . ' = ' . (int) $path);
		}
		else
		{
			$filePath = $briefcase ? str_replace($config->briefcase_folder, '', $fullpath) : str_replace($config->download_folder, '', $fullpath);
			$filePath = trim($filePath, $this->ds);
			$query->where($db->qn('f.FilePath') . ' = ' . $db->q($filePath));
		}

		$db->setQuery($query);
		$file = $db->loadObject();

		if (empty($file) && is_file($fullpath))
		{
			JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_rsfiles/tables');

			$file                 = JTable::getInstance('File', 'rsfilesTable');
			$file->FilePath       = $path;
			$file->FileSize       = rsfilesHelper::filesize($fullpath);
			$file->FileStatistics = 0;
			$file->DownloadMethod = 0;
			$file->DateAdded      = filemtime($fullpath);
			$file->hash           = md5_file($fullpath);
			$file->CanDownload    = 0;
			$file->CanView        = 0;
			$file->published      = 1;
			$file->FileType       = 0;
			$file->hits           = 0;
			$file->show_preview   = 1;
		}

		if (empty($file))
		{
			return false;
		}

		if (empty($file->DateAdded) || $file->DateAdded == $db->getNullDate())
		{
			$dateadded = $file->FileType ? '' : rsfilesHelper::showDate(filemtime($fullpath));
		}
		else
		{
			$dateadded = rsfilesHelper::showDate($file->DateAdded);
		}

		if (empty($file->ModifiedDate) || $file->ModifiedDate == $db->getNullDate())
		{
			$lastmodified = $file->FileType ? '' : rsfilesHelper::showDate(filemtime($fullpath));
		}
		else
		{
			$lastmodified = rsfilesHelper::showDate($file->ModifiedDate);
		}

		$extension = $file->FileType ? rsfilesHelper::getExt($file->FilePath) : rsfilesHelper::getExt($fullpath);
		$mimetype  = rsfilesHelper::mimetype(strtolower($extension));
		$object    = new stdClass();

		$object->fname           = !empty($file->FileName) ? $file->FileName : rsfilesHelper::getName((rsfilesHelper::external($path) ? $file->FilePath : $fullpath));
		$object->filedescription = !empty($file->FileDescription) ? $file->FileDescription : JText::_('COM_RSFILES_NO_DESCRIPTION');
		$object->filelicense     = !empty($file->IdLicense) ? JRoute::_('index.php?option=com_rsfiles&layout=license&tmpl=component&id=' . rsfilesHelper::sef($file->IdLicense, $file->LicenseName) . rsfilesHelper::getItemid()) : '';
		$object->filename        = $file->FileType ? rsfilesHelper::getName($file->FilePath) : rsfilesHelper::getName($fullpath);
		$object->fileversion     = $file->FileVersion;
		$object->filesize        = !empty($file->FileSize) ? $file->FileSize : ($file->FileType ? '-' : rsfilesHelper::formatBytes(rsfilesHelper::filesize($fullpath)));
		$object->filetype        = $extension ? $extension . ($mimetype ? ' (' . JText::_('COM_RSFILES_MIMETYPE') . ' ' . rsfilesHelper::mimetype(strtolower($extension)) . ')' : '') : '';
		$object->owner           = empty($file->IdUser) ? JText::_('COM_RSFILES_GUEST') : $file->name;
		$object->dateadded       = $dateadded;
		$object->hits            = (int) $file->hits;
		$object->downloads       = rsfilesHelper::downloads($file->FilePath);
		$object->lastmodified    = $lastmodified;
		$object->checksum        = empty($file->hash) ? JText::_('COM_RSFILES_NO_CHECKSUM') : $file->hash;
		$object->thumb           = empty($file->FileThumb) ? '' : JURI::root() . 'components/com_rsfiles/images/thumbs/files/' . $file->FileThumb;
		$object->fullpath        = $file->FileType ? $file->FilePath : $fullpath;
		$object->tags            = rsfilesHelper::getTags($file->IdFile);

		return (object) array_merge((array) $file, (array) $object);
	}

	// Create a new folder
	public function create()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$uid       = JFactory::getUser()->get('id');
		$session   = JFactory::getSession();
		$jform     = $this->input->get('jform', array(), 'array');
		$config    = rsfilesHelper::getConfig();
		$briefcase = rsfilesHelper::isBriefcase();
		$folder    = rsfilesHelper::makeSafe($jform['folder']);
		$parent    = $jform['parent'];
		$fullpath  = $briefcase ? rsfilesHelper::getBriefcase($parent) : $session->get('rsfilesdownloadfolder') . $this->ds . $parent;

		if ($briefcase && !rsfilesHelper::briefcase('CanMaintainBriefcase'))
		{
			$parent = empty($parent) ? $uid : $uid . $this->ds . $parent;
		}

		// Check to see if the user has permission to create a new folder
		if ($briefcase)
		{
			if (!rsfilesHelper::briefcase('CanUploadBriefcase') && !rsfilesHelper::briefcase('CanMaintainBriefcase'))
			{
				$this->setError(JText::_('COM_RSFILES_ERROR_1'));

				return false;
			}
		}
		else
		{
			$checkpath = str_replace($config->download_folder, '', $fullpath);
			$checkpath = trim($checkpath, $this->ds);
			$checkpath = empty($checkpath) ? 'root_rs_files' : $checkpath;

			if (!rsfilesHelper::permissions('CanCreate', $checkpath))
			{
				$this->setError(JText::_('COM_RSFILES_ERROR_1'));

				return false;
			}
		}

		if (JFolder::exists($fullpath . $this->ds . $folder))
		{
			$this->setError(JText::_('COM_RSFILES_FOLDER_ALREADY_EXISTS', true));

			return false;
		}

		if (strlen($folder) > 1)
		{
			if (JFolder::create($fullpath . $this->ds . $folder))
			{
				rsfilesHelper::clearCache($fullpath);
				$filePath = $briefcase ? str_replace($config->briefcase_folder, '', $fullpath) : str_replace($config->download_folder, '', $fullpath);
				$filePath = trim($filePath, $this->ds);
				$filePath = empty($filePath) ? $folder : $filePath . $this->ds . $folder;

				$query->clear()
					->insert($db->qn('#__rsfiles_files'))
					->set($db->qn('FilePath') . ' = ' . $db->q($filePath))
					->set($db->qn('DateAdded') . ' = ' . $db->q(JFactory::getDate()->toSql()))
					->set($db->qn('FileDescription') . ' = ' . $db->q(''))
					->set($db->qn('metadescription') . ' = ' . $db->q(''))
					->set($db->qn('metakeywords') . ' = ' . $db->q(''))
					->set($db->qn('FileParent') . ' = ' . $db->q(''))
					->set($db->qn('DownloadMethod') . ' = 0')
					->set($db->qn('briefcase') . ' = ' . (int) $briefcase)
					->set($db->qn('published') . ' = 1');

				if ($uid && !$briefcase)
				{
					$parts = explode($this->ds, $filePath);
					array_pop($parts);
					if (!empty($parts))
					{
						$parts = implode($this->ds, $parts);

						$thequery = $db->getQuery(true)
							->select($db->qn('CanCreate'))->select($db->qn('CanUpload'))
							->select($db->qn('CanDelete'))->select($db->qn('CanView'))
							->select($db->qn('CanEdit'))->select($db->qn('CanDownload'))
							->from($db->qn('#__rsfiles_files'))
							->where($db->qn('FilePath') . ' = ' . $db->q($parts));

						$db->setQuery($thequery);
						if ($permissions = $db->loadObject())
						{
							$query->set($db->qn('CanCreate') . ' = ' . $db->q($permissions->CanCreate));
							$query->set($db->qn('CanUpload') . ' = ' . $db->q($permissions->CanUpload));
							$query->set($db->qn('CanDelete') . ' = ' . $db->q($permissions->CanDelete));
							$query->set($db->qn('CanView') . ' = ' . $db->q($permissions->CanView));
							$query->set($db->qn('CanDownload') . ' = ' . $db->q($permissions->CanDownload));
							$query->set($db->qn('CanEdit') . ' = ' . $db->q($permissions->CanEdit));
						}
					}
					else
					{
						$fullpath = trim($fullpath, $this->ds);
						if ($fullpath == $config->download_folder)
						{
							$query->set($db->qn('CanCreate') . ' = ' . $db->q($config->download_cancreate));
							$query->set($db->qn('CanUpload') . ' = ' . $db->q($config->download_canupload));
						}
					}
				}

				$db->setQuery($query);
				$db->execute();

				return true;
			}
			else
			{
				$this->setError(JText::_('COM_RSFILES_NEW_FOLDER_ERROR', true));

				return false;
			}
		}
		else
		{
			$this->setError(JText::_('COM_RSFILES_NEW_FOLDER_LENGTH_ERROR', true));

			return false;
		}
	}

	public function checkupload()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$config    = rsfilesHelper::getConfig();
		$user      = JFactory::getUser();
		$briefcase = rsfilesHelper::isBriefcase();
		$folder    = rsfilesHelper::getFolder();
		$overwrite = $this->input->getInt('overwrite', 0);
		$app       = JFactory::getApplication();
		$moderate  = rsfilesHelper::briefcase('moderate');
		$iOS       = rsfilesHelper::isiOS();
		$file      = $this->input->getString('file');
		$size      = $this->input->getInt('size');

		// Check to see if upload is enabled
		if ($config->enable_upload == 0)
		{
			$this->setError(JText::_('COM_RSFILES_UPLOAD_DENIED'));

			return false;
		}

		// Do we have a valid file
		if (empty($file))
		{
			$this->setError(JText::_('COM_RSFILES_NO_UPLOAD_REQUESTED'));

			return false;
		}

		// On some browsers for iOS when selecting MOV videos the size is 0
		if ($iOS && $size == 0 && strtolower(rsfilesHelper::getExt($file)) == 'mov')
		{
			$this->setError(JText::_('COM_RSFILES_IOS_RESTRICTION'));

			return false;
		}

		if ($briefcase)
		{
			$root        = $config->briefcase_folder;
			$maintenance = rsfilesHelper::briefcase('CanMaintainBriefcase');
			$path        = $folder;
			$path        = !empty($path) ? $path : ($maintenance ? $user->get('id') : '');
			$fullpath    = $maintenance ? $root . $this->ds . $path : $root . $this->ds . $user->get('id') . $this->ds . $path;
			$size_limit  = (rsfilesHelper::getMaxFileSize() * 1048576);

			if (!rsfilesHelper::briefcase('CanMaintainBriefcase'))
			{
				$path = empty($path) ? $user->get('id') : $user->get('id') . $this->ds . $path;
			}

			if (!empty($folder))
			{
				if ($maintenance)
				{
					$parts          = explode($this->ds, $folder);
					$user_folder    = $parts[0];
					$briefcase_root = $root . $this->ds . $user_folder;
				}
				else
				{
					$briefcase_root = $root . $this->ds . $user->get('id');
				}
			}
			else
			{
				$briefcase_root = $maintenance ? $root : $root . $this->ds . $user->get('id');
			}

			$currentQuota        = $this->input->getFloat('quota');
			$maxQuota            = rsfilesHelper::getMaxFilesSize() * 1048576;
			$no_of_max_files     = rsfilesHelper::getMaxFilesNo();
			$current_no_of_files = $this->input->getInt('number');
		}
		else
		{
			$root       = JFactory::getSession()->get('rsfilesdownloadfolder');
			$path       = $folder;
			$path       = empty($path) ? '' : $this->ds . $path;
			$fullpath   = $root . $path;
			$size_limit = ($config->max_upl_size * 1024);

			if ($userSizeLimit = rsfilesHelper::getGroupOption('MaxUploadFileSize'))
			{
				$size_limit = $userSizeLimit * 1048576;
			}

			$current_no_of_files = 1;
			$no_of_max_files     = 2;

			if ($userMaxUploads = rsfilesHelper::getGroupOption('MaxUploadFiles'))
			{
				$no_of_max_files     = $userMaxUploads;
				$current_no_of_files = $this->input->getInt('number');
			}

			if ($userMaxQuota = rsfilesHelper::getGroupOption('MaxUploadFilesSize'))
			{
				$currentQuota = $this->input->getFloat('quota');
				$maxQuota     = $userMaxQuota * 1048576;
			}
		}

		// Set the full path
		$thefile = rsfilesHelper::makeSafe(rsfilesHelper::getName($file));
		if ($iOS)
		{
			$filenoextension = JFile::stripExt($thefile);
			if (in_array($filenoextension, array('image', 'capturedvideo')))
			{
				$thefile = JFactory::getDate()->format('U') . '_' . $thefile;
			}
		}

		$thepath       = urldecode($fullpath . $this->ds . $thefile);
		$performInsert = file_exists($thepath);

		$canUpload = true;
		if ($overwrite)
		{
			$canUpload = true;
		}
		else
		{
			if (file_exists($thepath))
			{
				$canUpload = false;
			}
			else
			{
				$canUpload = true;
			}
		}

		// The file already exists on the server and the overwrite option is disabled
		if (!$canUpload)
		{
			$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_4', $file));

			return false;
		}

		if ($briefcase)
		{
			// Check to see if user has permission to upload
			if (!rsfilesHelper::briefcase('CanUploadBriefcase') && !rsfilesHelper::briefcase('CanMaintainBriefcase'))
			{
				$this->setError(JText::_('COM_RSFILES_UPLOAD_DENIED'));

				return false;
			}

			// Check to see if the user has reached his maximum files
			if ($current_no_of_files >= $no_of_max_files)
			{
				$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_3', $file));

				return false;
			}

			// Check not to exceed the maximum upload quota
			if ($currentQuota + $size >= $maxQuota)
			{
				$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_5', $file));

				return false;
			}

			$config->briefcase_allowed_files = strtolower($config->briefcase_allowed_files);
			$config->briefcase_allowed_files = str_replace("\r", '', $config->briefcase_allowed_files);
			$allowed_extensions              = explode("\n", $config->briefcase_allowed_files);

		}
		else
		{
			$checkForMaxFiles = false;
			if ($overwrite && !$performInsert)
			{
				$checkForMaxFiles = true;
			}
			else if (!$overwrite)
			{
				$checkForMaxFiles = true;
			}

			// Check to see if the user has reached his maximum files
			if ($current_no_of_files >= $no_of_max_files && $checkForMaxFiles)
			{
				$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_3', $file));

				return false;
			}

			// Check not to exceed the maximum upload quota
			if (rsfilesHelper::getGroupOption('MaxUploadFilesSize') && $currentQuota + $size >= $maxQuota)
			{
				$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_7', $file));

				return false;
			}

			// Check to see if the user has permission to upload
			$checkpath = str_replace($config->download_folder, '', $fullpath);
			$checkpath = trim($checkpath, $this->ds);
			$checkpath = empty($checkpath) ? 'root_rs_files' : $checkpath;
			if (!rsfilesHelper::permissions('CanUpload', $checkpath))
			{
				$this->setError(JText::_('COM_RSFILES_UPLOAD_DENIED'));

				return false;
			}

			$config->allowed_files = strtolower($config->allowed_files);
			$config->allowed_files = str_replace("\r", '', $config->allowed_files);
			$allowed_extensions    = explode("\n", $config->allowed_files);

			if ($userExtensions = rsfilesHelper::getGroupOption('MaxUploadFileExtensions'))
			{
				$allowed_extensions = $userExtensions;
			}
		}

		// Check for file owner, if the file exists and someone tries to overwrite it
		if (!$briefcase && $overwrite && $performInsert)
		{
			$filePath = str_replace($config->download_folder, '', $thepath);
			$filePath = trim($filePath, $this->ds);

			$query->clear()
				->select($db->qn('IdUser'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('FilePath') . ' = ' . $db->q($filePath));
			$db->setQuery($query);
			$owner = (int) $db->loadResult();

			if ($owner != $user->get('id'))
			{
				$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_6', $file));

				return false;
			}
		}

		// Check for allowed file extension
		if (!in_array(strtolower(rsfilesHelper::getExt($thefile)), $allowed_extensions))
		{
			$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_1', $file));

			return false;
		}

		// Check for allowed file size
		if ($size > $size_limit)
		{
			$this->setError(JText::sprintf('COM_RSFILES_UPLOAD_ERROR_2', $file));

			return false;
		}

		$this->setState('perform.insert', $performInsert);
		$this->setState('file.name', $thefile);

		return true;
	}

	// Upload files using the form method
	public function upload()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$config    = rsfilesHelper::getConfig();
		$user      = JFactory::getUser();
		$briefcase = rsfilesHelper::isBriefcase();
		$folder    = rsfilesHelper::getFolder();
		$moderate  = rsfilesHelper::briefcase('moderate');
		$iOS       = rsfilesHelper::isiOS();
		$filename  = $this->input->getString('filename');
		$exists    = $this->input->getInt('exists');

		if ($briefcase)
		{
			$root        = $config->briefcase_folder;
			$maintenance = rsfilesHelper::briefcase('CanMaintainBriefcase');
			$path        = $folder;
			$path        = !empty($path) ? $path : ($maintenance ? $user->get('id') : '');
			$fullpath    = $maintenance ? $root . $this->ds . $path : $root . $this->ds . $user->get('id') . $this->ds . $path;

			if (!rsfilesHelper::briefcase('CanMaintainBriefcase'))
			{
				$path = empty($path) ? $user->get('id') : $user->get('id') . $this->ds . $path;
			}

			if (!empty($folder))
			{
				if ($maintenance)
				{
					$parts          = explode($this->ds, $folder);
					$user_folder    = $parts[0];
					$briefcase_root = $root . $this->ds . $user_folder;
				}
				else
				{
					$briefcase_root = $root . $this->ds . $user->get('id');
				}
			}
			else
			{
				$briefcase_root = $maintenance ? $root : $root . $this->ds . $user->get('id');
			}

		}
		else
		{
			$root     = JFactory::getSession()->get('rsfilesdownloadfolder');
			$path     = $folder;
			$path     = empty($path) ? '' : $this->ds . $path;
			$fullpath = $root . $path;
		}

		$cleanpath = ltrim($path, $this->ds);

		// Upload file
		$thefile = $filename;

		require_once JPATH_SITE . '/components/com_rsfiles/helpers/upload.php';

		$options  = array('upload_dir' => $fullpath . $this->ds, 'param_name' => 'file', 'filename' => $thefile);
		$upload   = new UploadHandler($options);
		$response = $upload->response;
		$finished = isset($response['file'][0]->insert) ? true : false;
		$error    = isset($response['file'][0]->error) ? $response['file'][0]->error : false;

		if (!empty($error))
		{
			$this->setError($error);

			return false;
		}

		if ($finished)
		{
			$filePath = $briefcase ? str_replace($config->briefcase_folder, '', $fullpath) : str_replace($config->download_folder, '', $fullpath);
			$filePath = trim($filePath, $this->ds);
			$filePath = empty($filePath) ? $thefile : $filePath . $this->ds . $thefile;

			$query->clear()
				->select($db->qn('IdFile'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('FilePath') . ' = ' . $db->q($filePath));
			$db->setQuery($query);
			$fileID = (int) $db->loadResult();

			if (!$fileID)
			{
				$query->clear()
					->insert($db->qn('#__rsfiles_files'))
					->set($db->qn('FilePath') . ' = ' . $db->q($filePath))
					->set($db->qn('DateAdded') . ' = ' . $db->q(JFactory::getDate()->toSql()))
					->set($db->qn('FileDescription') . ' = ' . $db->q(''))
					->set($db->qn('metadescription') . ' = ' . $db->q(''))
					->set($db->qn('metakeywords') . ' = ' . $db->q(''))
					->set($db->qn('FileParent') . ' = ' . $db->q(''))
					->set($db->qn('IdUser') . ' = ' . (int) $user->get('id'))
					->set($db->qn('DownloadMethod') . ' = 0')
					->set($db->qn('show_preview') . ' = 1')
					->set($db->qn('briefcase') . ' = ' . $db->q((int) $briefcase))
					->set($db->qn('hash') . ' = ' . $db->q(md5_file($fullpath . $this->ds . $thefile)));

				if ($briefcase)
				{
					$query->set($db->qn('published') . ' = 1');
				}
				else
				{
					if ($moderate)
					{
						$query->set($db->qn('published') . ' = 0');
					}
					else
					{
						$query->set($db->qn('published') . ' = 1');
					}
				}

				if (!$briefcase)
				{
					$parts = explode($this->ds, $filePath);
					array_pop($parts);
					if (!empty($parts))
					{
						$parts = implode($this->ds, $parts);

						$thequery = $db->getQuery(true)
							->select($db->qn('CanDownload'))->select($db->qn('CanView'))
							->select($db->qn('CanEdit'))->select($db->qn('CanDelete'))
							->from($db->qn('#__rsfiles_files'))
							->where($db->qn('FilePath') . ' = ' . $db->q($parts));

						$db->setQuery($thequery);
						$permissions = $db->loadObject();

						if (isset($permissions))
						{
							$query->set($db->qn('CanDownload') . ' = ' . $db->q($permissions->CanDownload));
							$query->set($db->qn('CanView') . ' = ' . $db->q($permissions->CanView));
							$query->set($db->qn('CanEdit') . ' = ' . $db->q($permissions->CanEdit));
							$query->set($db->qn('CanDelete') . ' = ' . $db->q($permissions->CanDelete));
						}
					}
				}

				$db->setQuery($query);
				$db->execute();
				$fileID = $db->insertid();
			}
			else
			{
				if (!$briefcase)
				{
					$query->clear()->update($db->qn('#__rsfiles_files'));

					if ($moderate)
					{
						$query->set($db->qn('published') . ' = 0');
					}
					else
					{
						$query->set($db->qn('published') . ' = 1');
					}

					$query->where($db->qn('IdFile') . ' = ' . $db->q($fileID));
					$db->setQuery($query);
					$db->execute();
				}
			}

			if (!$briefcase)
			{
				if ($moderate)
				{
					// Send moderation email
					if ($moderation_email = rsfilesHelper::getMessage('moderate'))
					{
						if (!empty($moderation_email->to))
						{
							$cc  = !empty($config->email_cc) ? $config->email_cc : null;
							$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

							$subject = $moderation_email->subject;
							$body    = $moderation_email->message;

							if ($emails = explode(',', $moderation_email->to))
							{
								foreach ($emails as $email)
								{
									$email = trim($email);

									if (empty($email))
									{
										continue;
									}

									$hash        = md5($email . $fileID);
									$filedldpath = $cleanpath ? $cleanpath . $this->ds . $thefile : $thefile;
									$fileurl     = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . rsfilesHelper::encode($filedldpath) . '&hash=' . $hash, false);
									$approveurl  = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&task=approve&hash=' . $hash, false);

									$bad  = array('{file}', '{approve}');
									$good = array($fileurl, $approveurl);
									$body = str_replace($bad, $good, $body);

									rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $moderation_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
								}
							}
						}
					}
				}
			}

			// Send emails
			$url    = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download' . ($briefcase ? '&from=briefcase' : '') . '&path=' . rsfilesHelper::encode($cleanpath . $this->ds . $thefile));
			$anchor = '<a href="' . $url . '">' . $url . '</a>';

			if (!$moderate && !$briefcase)
			{
				// Send upload email
				if ($upload_email = rsfilesHelper::getMessage('upload'))
				{
					if ($upload_email->enable && !empty($upload_email->to))
					{

						$cc  = !empty($config->email_cc) ? $config->email_cc : null;
						$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

						$subject = $upload_email->subject;
						$body    = $upload_email->message;

						$bad  = array('{name}', '{username}', '{files}', '{file}');
						$good = array($user->get('name'), $user->get('username'), $anchor, $anchor);
						$body = str_replace($bad, $good, $body);

						if ($emails = explode(',', $upload_email->to))
						{
							foreach ($emails as $email)
							{
								$email = trim($email);

								if (empty($email))
								{
									continue;
								}

								rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $upload_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
							}
						}
					}
				}
			}

			// Send briefcase notification to the owner of the briefcase
			if ($briefcase && $maintenance)
			{
				$userID        = (int) str_replace($config->briefcase_folder . $this->ds, '', $briefcase_root);
				$currentUserID = (int) $user->get('id');

				if ($userID != $currentUserID)
				{
					if ($briefcaseupload_email = rsfilesHelper::getMessage('briefcaseupload'))
					{
						if ($briefcaseupload_email->enable)
						{

							$owner = JFactory::getUser($userID);
							$to    = $owner->get('email');
							$cc    = !empty($config->email_cc) ? $config->email_cc : null;
							$bcc   = !empty($config->email_bcc) ? $config->email_bcc : null;

							$subject = $briefcaseupload_email->subject;
							$body    = $briefcaseupload_email->message;

							$filePath        = $cleanpath . $this->ds . $thefile;
							$userMaintenance = rsfilesHelper::briefcase('CanMaintainBriefcase', $userID);

							// If the user does not have the maintenance option, then remove the user folder
							if (!$userMaintenance)
							{
								$parts = explode($this->ds, $filePath);
								if ($parts[0] == $userID)
								{
									unset($parts[0]);
									$filePath = implode($this->ds, $parts);
								}
							}

							$url    = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download' . ($briefcase ? '&from=briefcase' : '') . '&path=' . rsfilesHelper::encode($filePath));
							$anchor = '<a href="' . $url . '">' . $url . '</a>';

							$bad  = array('{name}', '{uploader}', '{files}', '{file}');
							$good = array($owner->get('name'), $user->get('name'), $anchor, $anchor);
							$body = str_replace($bad, $good, $body);

							rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $to, $subject, $body, $briefcaseupload_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
						}
					}
				}
			}

			// Send admin briefcase email
			if ($briefcase)
			{
				if ($briefcaseuploadadmin_email = rsfilesHelper::getMessage('briefcaseuploadadmin'))
				{
					if ($briefcaseuploadadmin_email->enable && !empty($briefcaseuploadadmin_email->to))
					{
						$userID = (int) str_replace($config->briefcase_folder . $this->ds, '', $briefcase_root);

						$query->clear()
							->select($db->qn('FileName'))
							->from($db->qn('#__rsfiles_files'))
							->where($db->qn('FilePath') . ' = ' . $db->q($userID));
						$db->setQuery($query);
						$bfname = $db->loadResult();
						$bfname = $bfname ? $bfname : JFactory::getUser($userID)->get('name');

						$cc  = !empty($config->email_cc) ? $config->email_cc : null;
						$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

						$subject = $briefcaseuploadadmin_email->subject;
						$body    = $briefcaseuploadadmin_email->message;

						$url    = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download&from=briefcase&path=' . rsfilesHelper::encode($cleanpath . $this->ds . $thefile));
						$anchor = '<a href="' . $url . '">' . $url . '</a>';

						$bad  = array('{briefcase}', '{uploader}', '{files}', '{file}');
						$good = array($bfname, $user->get('name'), $anchor, $anchor);
						$body = str_replace($bad, $good, $body);

						if ($emails = explode(',', $briefcaseuploadadmin_email->to))
						{
							foreach ($emails as $email)
							{
								$email = trim($email);

								if (empty($email))
								{
									continue;
								}

								rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $briefcaseuploadadmin_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
							}
						}
					}
				}
			}

			if ($moderate)
			{
				$this->setState('success.message', JText::sprintf('COM_RSFILES_IMAGE_SUCCESSFULLY_UPLOADED_MODERATION', $thefile));
			}
			else
			{
				rsfilesHelper::clearCache($fullpath);
				$this->setState('success.message', JText::sprintf('COM_RSFILES_IMAGE_SUCCESSFULLY_UPLOADED', $thefile));
			}
		}

		return true;
	}

	// Cancel uploads
	public function cancelupload()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$config    = rsfilesHelper::getConfig();
		$user      = JFactory::getUser();
		$briefcase = rsfilesHelper::isBriefcase();
		$folder    = rsfilesHelper::getFolder();
		$file      = $this->input->getString('file');
		$file      = rsfilesHelper::makeSafe(rsfilesHelper::getName($file));

		if ($briefcase)
		{
			// Check to see if user has permission to upload
			if (!rsfilesHelper::briefcase('CanUploadBriefcase') && !rsfilesHelper::briefcase('CanMaintainBriefcase'))
			{
				return false;
			}

			$root        = $config->briefcase_folder;
			$maintenance = rsfilesHelper::briefcase('CanMaintainBriefcase');
			$path        = $folder;
			$path        = !empty($path) ? $path : ($maintenance ? $user->get('id') : '');
			$fullpath    = $maintenance ? $root . $this->ds . $path : $root . $this->ds . $user->get('id') . $this->ds . $path;
		}
		else
		{
			// Check to see if the user has permission to upload
			$root      = JFactory::getSession()->get('rsfilesdownloadfolder');
			$path      = $folder;
			$path      = empty($path) ? '' : $this->ds . $path;
			$fullpath  = $root . $path;
			$checkpath = str_replace($config->download_folder, '', $fullpath);
			$checkpath = trim($checkpath, $this->ds);
			$checkpath = empty($checkpath) ? 'root_rs_files' : $checkpath;

			if (!rsfilesHelper::permissions('CanUpload', $checkpath))
			{
				return false;
			}
		}

		if (file_exists($fullpath . $this->ds . $file))
		{
			jimport('joomla.filesystem.file');
			JFile::delete($fullpath . $this->ds . $file);

			$query->clear()->delete($db->qn('#__rsfiles_files'));

			if ($briefcase)
			{
				$filepath = str_replace($config->briefcase_folder . $this->ds, '', $fullpath . $this->ds . $file);
				$query->where($db->qn('briefcase') . ' = 1');
			}
			else
			{
				$filepath = str_replace($config->download_folder . $this->ds, '', $fullpath . $this->ds . $file);
				$query->where($db->qn('briefcase') . ' = 0');
			}

			$query->where($db->qn('FilePath') . ' = ' . $db->q($filepath));
			$db->setQuery($query);
			$db->execute();
		}
		else
		{
			return false;
		}

		return true;
	}

	// Upload external files
	public function uploadexternal()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$app       = JFactory::getApplication();
		$user      = JFactory::getUser();
		$config    = rsfilesHelper::getConfig();
		$folder    = rsfilesHelper::getFolder();
		$moderate  = rsfilesHelper::briefcase('moderate');
		$filepaths = array();
		$uploads   = 0;
		$externals = $this->input->get('external', array(), 'array');

		// Insert external files
		if (!empty($externals))
		{
			$root       = JFactory::getSession()->get('rsfilesdownloadfolder');
			$path       = $folder;
			$path       = empty($path) ? '' : $this->ds . $path;
			$fullpath   = $root . $path;
			$fileParent = str_replace($config->download_folder, '', $fullpath);
			$fileParent = trim($fileParent, $this->ds);
			$fileParent = empty($fileParent) ? 'root' : $fileParent;

			$thequery = $db->getQuery(true)
				->select($db->qn('CanDownload'))->select($db->qn('CanView'))
				->select($db->qn('CanEdit'))->select($db->qn('CanDelete'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('FilePath') . ' = ' . $db->q($fileParent));

			$db->setQuery($thequery);
			$permissions = $db->loadObject();

			foreach ($externals as $external)
			{
				if (empty($external))
				{
					continue;
				}

				$code = rsfilesHelper::externalStatus($external);

				if (!in_array(substr($code, 0, 1), array(2, 3)))
				{
					continue;
				}

				$query->clear()
					->select($db->qn('IdFile'))
					->from($db->qn('#__rsfiles_files'))
					->where($db->qn('FilePath') . ' = ' . $db->q($external))
					->where($db->qn('FileType') . ' = 1')
					->where($db->qn('FileParent') . ' = ' . $db->q($fileParent));
				$db->setQuery($query);
				if ((int) $db->loadResult())
				{
					continue;
				}

				$query->clear()
					->insert($db->qn('#__rsfiles_files'))
					->set($db->qn('FilePath') . ' = ' . $db->q($external))
					->set($db->qn('DownloadName') . ' = ' . $db->q(rsfilesHelper::getExternalName($external)))
					->set($db->qn('FileSize') . ' = ' . $db->q(rsfilesHelper::externalHeaders($external, 'Content-Length')))
					->set($db->qn('DateAdded') . ' = ' . $db->q(JFactory::getDate()->toSql()))
					->set($db->qn('FileDescription') . ' = ' . $db->q(''))
					->set($db->qn('metadescription') . ' = ' . $db->q(''))
					->set($db->qn('metakeywords') . ' = ' . $db->q(''))
					->set($db->qn('IdUser') . ' = ' . (int) $user->get('id'))
					->set($db->qn('FileType') . ' = 1')
					->set($db->qn('DownloadMethod') . ' = 0')
					->set($db->qn('FileParent') . ' = ' . $db->q($fileParent));

				if ($moderate)
				{
					$query->set($db->qn('published') . ' = 0');
				}
				else
				{
					$query->set($db->qn('published') . ' = 1');
				}

				if (isset($permissions))
				{
					$query->set($db->qn('CanDownload') . ' = ' . $db->q($permissions->CanDownload));
					$query->set($db->qn('CanView') . ' = ' . $db->q($permissions->CanView));
					$query->set($db->qn('CanEdit') . ' = ' . $db->q($permissions->CanEdit));
					$query->set($db->qn('CanDelete') . ' = ' . $db->q($permissions->CanDelete));
				}

				$db->setQuery($query);
				$db->execute();
				$externalID = $db->insertid();

				if ($moderate)
				{
					if ($moderation_email = rsfilesHelper::getMessage('moderate'))
					{
						if (!empty($moderation_email->to))
						{
							$cc  = !empty($config->email_cc) ? $config->email_cc : null;
							$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

							$subject = $moderation_email->subject;
							$body    = $moderation_email->message;

							if ($emails = explode(',', $moderation_email->to))
							{
								foreach ($emails as $email)
								{
									$email = trim($email);

									if (empty($email))
									{
										continue;
									}

									$hash       = md5($email . $externalID);
									$fileurl    = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . $externalID . '&hash=' . $hash, false);
									$approveurl = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&task=approve&hash=' . $hash, false);

									$bad  = array('{file}', '{approve}');
									$good = array($fileurl, $approveurl);
									$body = str_replace($bad, $good, $body);

									rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $moderation_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
								}
							}
						}
					}
				}

				$url         = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . $externalID, false);
				$filepaths[] = '<a href="' . $url . '">' . $url . '</a>';
				$uploads++;
			}

			if (!$moderate && !empty($uploads))
			{
				// Send upload email
				if ($upload_email = rsfilesHelper::getMessage('upload'))
				{
					if ($upload_email->enable && !empty($upload_email->to))
					{

						$cc  = !empty($config->email_cc) ? $config->email_cc : null;
						$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

						$subject   = $upload_email->subject;
						$body      = $upload_email->message;
						$filepaths = implode('<br />', $filepaths);

						$bad  = array('{name}', '{username}', '{files}');
						$good = array($user->get('name'), $user->get('username'), $filepaths);
						$body = str_replace($bad, $good, $body);

						if ($emails = explode(',', $upload_email->to))
						{
							foreach ($emails as $email)
							{
								$email = trim($email);

								if (empty($email))
								{
									continue;
								}

								rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $upload_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
							}
						}
					}
				}
			}

			if ($moderate)
			{
				$this->setState('success.message', JText::plural('COM_RSFILES_UPLOAD_SUCCESS_WITH_MODERATION', $uploads));
			}
			else
			{
				if (empty($uploads))
				{
					$this->setError(JText::_('COM_RSFILES_NO_VALID_EXTERNAL_FILES'));

					return false;
				}
				else
				{
					$this->setState('success.message', JText::plural('COM_RSFILES_UPLOAD_SUCCESS', $uploads));
					rsfilesHelper::clearCache($fullpath);
				}
			}

			return true;
		}
		else
		{
			$this->setError(JText::_('COM_RSFILES_NO_EXTERNAL_FILES'));

			return false;
		}
	}

	// Delete folder / files
	public function delete()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$config    = rsfilesHelper::getConfig();
		$user      = JFactory::getUser();
		$briefcase = rsfilesHelper::isBriefcase();
		$path      = rsfilesHelper::getPath();
		$folder    = rsfilesHelper::getFolder();
		$root      = $briefcase ? $config->briefcase_folder : JFactory::getSession()->get('rsfilesdownloadfolder');
		$fullpath  = '';
		$external  = rsfilesHelper::external($path);
		$source    = '';
		$paths     = array();

		if (!empty($folder))
		{
			if ($briefcase)
			{
				if (rsfilesHelper::briefcase('CanMaintainBriefcase'))
				{
					$fullpath = $root . $this->ds . $folder;
				}
				else
				{
					$fullpath = $root . $this->ds . $user->get('id') . $this->ds . $folder;
				}
			}
			else
			{
				$fullpath = $root . $this->ds . $folder;
			}
		}
		else if (!empty($path))
		{
			if ($external)
			{
				$fullpath = (int) $path;
			}
			else
			{
				if ($briefcase)
				{
					if (rsfilesHelper::briefcase('CanMaintainBriefcase'))
					{
						$fullpath = $root . $this->ds . $path;
					}
					else
					{
						$fullpath = $root . $this->ds . $user->get('id') . $this->ds . $path;
					}
				}
				else
				{
					$fullpath = $root . $this->ds . $path;
				}
			}
		}

		// Get the redirect to path
		if (is_dir($fullpath) && !$external)
		{
			$return = explode($this->ds, $folder);
			array_pop($return);
			$return = implode($this->ds, $return);
			$this->setState('return.path', $return);
		}

		if (is_file($fullpath) && !$external)
		{
			$return = explode($this->ds, $path);
			array_pop($return);
			$return = implode($this->ds, $return);
			$this->setState('return.path', $return);
		}

		if ($external)
		{
			$query->clear()->select($db->qn('FileParent'))->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . (int) $fullpath);
			$db->setQuery($query);
			$thepath = $db->loadResult();

			$return = $thepath == $root ? '' : str_replace($root . $this->ds, '', $thepath);
			$this->setState('return.path', $return);
		}

		if (empty($fullpath))
		{
			$this->setError(JText::_('COM_RSFILES_CANNOT_DELETE'));

			return false;
		}

		if (!$external)
		{
			// Check if folder is outside of the root
			if (strpos(realpath($fullpath), realpath($root)) !== 0)
			{
				$this->setError(JText::_('COM_RSFILES_DELETE_OUTSIDE_ROOT_FOLDER'));

				return false;
			}

			$parts = explode($this->ds, $fullpath);
			$ext   = end($parts);
			if (JFile::stripExt($ext) == '')
			{
				$this->setError(JText_('COM_RSFILES_CANNOT_DELETE'));

				return false;
			}

			// Check for permissions
			if ($briefcase)
			{
				if (!rsfilesHelper::briefcase('CanMaintainBriefcase') && !rsfilesHelper::briefcase('CanDeleteBriefcase'))
				{
					$this->setError(JText::_('COM_RSFILES_CANNOT_DELETE'));

					return false;
				}
			}
			else
			{
				$checkpath = str_replace($config->download_folder, '', $fullpath);
				$checkpath = trim($checkpath, $this->ds);
				$checkpath = empty($checkpath) ? 'root_rs_files' : $checkpath;

				$query->clear()->select($db->qn('IdUser'))->from($db->qn('#__rsfiles_files'))->where($db->qn('FilePath') . ' = ' . $db->q($checkpath));
				$db->setQuery($query);
				$iduser = $db->loadResult();

				if (!rsfilesHelper::permissions('CanDelete', $checkpath) && !(rsfilesHelper::briefcase('deleteown') && $iduser == $user->get('id')))
				{
					$this->setError(JText::_('COM_RSFILES_CANNOT_DELETE'));

					return false;
				}
			}
		}

		$parts = explode($this->ds, $fullpath);
		array_pop($parts);
		$paths[] = implode($this->ds, $parts);

		// Are we trying to delete a folder ?
		if (is_dir($fullpath) && !$external)
		{
			// Do not delete the root folder
			if ($fullpath == $root . $this->ds || $fullpath == $root)
			{
				$this->setError(JText::_('COM_RSFILES_DELETE_ROOT_FOLDER'));

				return false;
			}

			$subfolders = JFolder::folders($fullpath, '.', true, true);
			$subfiles   = JFolder::files($fullpath, '.', true, true);
			$elements   = array_merge(array($fullpath), $subfolders, $subfiles);

			if ($subfolders)
			{
				foreach ($subfolders as $subfolder)
				{
					$paths[] = realpath($subfolder);
				}
			}

			if (!empty($elements))
			{
				foreach ($elements as $element)
				{
					$element = realpath($element);
					$element = $briefcase ? str_replace($config->briefcase_folder, '', $element) : str_replace($config->download_folder, '', $element);
					$element = trim($element, $this->ds);

					$query->clear()
						->select($db->qn('IdFile'))
						->from($db->qn('#__rsfiles_files'))
						->where($db->qn('FilePath') . ' = ' . $db->q($element));
					$db->setQuery($query);
					if ($id = (int) $db->loadResult())
					{
						rsfilesHelper::remove($id);

						$query->clear()->delete()->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . $db->q($id));
						$db->setQuery($query);
						$db->execute();
					}
				}
			}

			JFolder::delete($fullpath);
			$this->setState('return.message', JText::_('COM_RSFILES_FOLDER_REMOVED'));
		}

		// Are we trying to delete a single file ?
		if (is_file($fullpath) && !$external)
		{
			$thepath = $briefcase ? str_replace($config->briefcase_folder, '', $fullpath) : str_replace($config->download_folder, '', $fullpath);
			$thepath = trim($thepath, $this->ds);

			$query->clear()
				->select($db->qn('IdFile'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('FilePath') . ' = ' . $db->q($thepath));
			$db->setQuery($query);
			if ($id = (int) $db->loadResult())
			{
				rsfilesHelper::remove($id);

				$query->clear()->delete()->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . $db->q($id));
				$db->setQuery($query);
				$db->execute();
			}

			JFile::delete($fullpath);
			$this->setState('return.message', JText::_('COM_RSFILES_FILE_REMOVED'));
		}

		// Are we trying to remove an external file ?
		if ($external)
		{
			$theid = (int) $fullpath;
			rsfilesHelper::remove($theid);

			$query->clear()->delete()->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . $theid);
			$db->setQuery($query);
			$db->execute();
			$this->setState('return.message', JText::_('COM_RSFILES_FILE_REMOVED'));
		}

		if ($paths)
		{
			foreach ($paths as $path)
			{
				rsfilesHelper::clearCache($path);
			}
		}

		return true;
	}

	// Download file
	public function download()
	{
		$db         = JFactory::getDbo();
		$query      = $db->getQuery(true);
		$app        = JFactory::getApplication();
		$user       = JFactory::getUser();
		$config     = rsfilesHelper::getConfig();
		$itemid     = rsfilesHelper::getItemid();
		$fromemail  = $this->input->getString('email');
		$briefcase  = rsfilesHelper::isBriefcase();
		$task       = $this->input->get('task');
		$session    = JFactory::getSession();
		$dld_fld    = $session->get('rsfilesdownloadfolder');
		$brf_fld    = $session->get('rsfilesbriefcasefolder');
		$file       = rsfilesHelper::getPath();
		$file       = $fromemail ? urldecode(base64_decode($file)) : $file;
		$ip         = rsfilesHelper::getIP(true);
		$isExternal = rsfilesHelper::external($file);
		$hash       = $app->input->getString('hash', '');

		if ($briefcase)
		{
			$fullpath = $this->absoluteFolder . $this->ds . $file;
		}
		elseif (JFactory::getApplication()->input->get('relatedfile'))
		{
			$dld_fld  = $config->download_folder;
			$fullpath = $config->download_folder . $this->ds . $file;
		}
		else
		{
			$fullpath = $isExternal ? ((int) $file) : $this->absoluteFolder . $this->ds . $file;
		}

		$published = rsfilesHelper::published($fullpath);

		if (!$briefcase)
		{
			if ($hash)
			{
				if (!$published)
				{
					if (rsfilesHelper::checkHash())
					{
						$published = true;
					}
				}
			}
		}

		if ($briefcase)
		{
			if (strpos(realpath($fullpath), realpath($brf_fld)) !== 0)
				rsfilesHelper::errors(JText::_('COM_RSFILES_OUTSIDE_OF_ROOT'), JRoute::_('index.php?option=com_rsfiles', false));

			$candownload = rsfilesHelper::briefcase('CanDownloadBriefcase') || rsfilesHelper::briefcase('CanMaintainBriefcase') ? 1 : 0;

			if (!$candownload)
			{
				$app->enqueueMessage(JText::_('COM_RSFILES_CANNOT_DOWNLOAD'));
				$app->redirect(JRoute::_('index.php?option=com_rsfiles&layout=download&from=briefcase&path=' . $file, false));
			}
		}
		else
		{
			//if the users get out of the root
			if (empty($isExternal) && strpos(realpath($fullpath), realpath($dld_fld)) !== 0)
				rsfilesHelper::errors(JText::_('COM_RSFILES_OUTSIDE_OF_ROOT'), JRoute::_('index.php?option=com_rsfiles', false));

			$thepath = str_replace($config->download_folder, '', $fullpath);
			$thepath = trim($thepath, $this->ds);

			//check first if the user can download the file
			if (!rsfilesHelper::permissions('CanDownload', $thepath))
			{
				$app->enqueueMessage(JText::_('COM_RSFILES_CANNOT_DOWNLOAD'));
				$app->redirect(JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . $file, false));
			}
		}

		// Check if it the file is published
		if (!$published && !is_null($published))
		{
			rsfilesHelper::errors(JText::_('COM_RSFILES_CANNOT_DOWNLOAD'), JRoute::_('index.php?option=com_rsfiles', false));
		}

		$parts = explode($this->ds, $fullpath);
		$ext   = end($parts);

		if (empty($isExternal) && JFile::stripExt($ext) == '')
		{
			rsfilesHelper::errors(JText::_('COM_RSFILES_CANNOT_DOWNLOAD'), JRoute::_('index.php?option=com_rsfiles', false));
		}

		if (is_file($fullpath) || $isExternal)
		{
			$thepath = str_replace(($briefcase ? $config->briefcase_folder : $config->download_folder), '', $fullpath);
			$thepath = trim($thepath, $this->ds);

			// Check to see if the file can be downloaded.
			$query->clear()->select($db->qn('DownloadLimit'))->select($db->qn('FilePath'))->select($db->qn('DownloadName'))->select($db->qn('Downloads'))->from($db->qn('#__rsfiles_files'));

			if ($isExternal)
				$query->where($db->qn('IdFile') . ' = ' . (int) $file);
			else
				$query->where($db->qn('FilePath') . ' = ' . $db->q($thepath));

			$db->setQuery($query);
			$info = $db->loadObject();

			if (!empty($info->DownloadLimit) && $info->Downloads >= $info->DownloadLimit)
				rsfilesHelper::errors(JText::_('COM_RSFILES_DOWNLOADS_LIMIT_REACHED'), JRoute::_('index.php?option=com_rsfiles', false));


			// Download Method is Email download
			if ($fromemail && !$briefcase)
			{
				if ($this->verifyEmailHash())
				{
					$this->updateEmailHashDownload();
				}
				else
				{
					$app->enqueueMessage(JText::_('COM_RSFILES_INVALID_HASH'));
					$app->redirect(JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . rsfilesHelper::encode($file) . $itemid, false));
				}
			}

			$relative = str_replace($config->download_folder . $this->ds, '', $fullpath);
			rsfilesHelper::statistics($fullpath, $relative, $isExternal ? (int) $file : false);

			rsfilesHelper::hits($fullpath, $relative);

			if (headers_sent($fname, $line))
			{
				throw new Exception(JText::sprintf('COM_RSFILES_HEADERS_SENT', $fname, $line));
			}

			@ob_end_clean();
			@set_time_limit(0);
			$filename = $isExternal ? (!empty($info->DownloadName) ? $info->DownloadName : rsfilesHelper::getName(parse_url($info->FilePath, PHP_URL_PATH))) : basename($fullpath);
			header("Cache-Control: public, must-revalidate");
			header('Cache-Control: pre-check=0, post-check=0, max-age=0');
			header("Pragma: no-cache");
			header("Expires: 0");
			header("Content-Description: File Transfer");
			header("Content-Type: application/octet-stream");

			if (!$isExternal)
			{
				$filesize = rsfilesHelper::filesize($fullpath);
				header("Content-Length: " . (string) $filesize);
			}

			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header("Content-Transfer-Encoding: binary\n");
			rsfilesHelper::readfile_chunked($isExternal ? $info->FilePath : $fullpath);

			if (!empty($info->DownloadLimit))
			{
			$query->clear()->update($db->qn('#__rsfiles_files'))->set($db->qn('Downloads') . ' = ' . $db->qn('Downloads') . ' + 1');

			if ($isExternal)
				$query->where($db->qn('IdFile') . ' = ' . (int) $file);
			else
				$query->where($db->qn('FilePath') . ' = ' . $db->q($thepath));

			$db->setQuery($query);
			$db->execute();
			}

			// Send email to admin
			if ($admin_email = rsfilesHelper::getMessage('admin'))
			{
				if ($admin_email->enable && !empty($admin_email->to))
				{

					$cc  = !empty($config->email_cc) ? $config->email_cc : null;
					$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

					$subject = $admin_email->subject;
					$body    = $admin_email->message;
					$email   = $this->getEmailFromHash();
					$name    = $this->getNameFromHash();

					$username = $user->get('username');
					$username = empty($username) ? JText::_('COM_RSFILES_GUEST') : $username;
					$filepath = $isExternal ? $info->FilePath : $fullpath;
					$bad      = array('{filename}', '{filepath}', '{ip}', '{username}', '{email}', '{name}');
					$good     = array($filename, $filepath, $ip, $username, $email, $name);
					$body     = str_replace($bad, $good, $body);

					if ($emails = explode(',', $admin_email->to))
					{
						foreach ($emails as $email)
						{
							$email = trim($email);

							if (empty($email))
							{
								continue;
							}

							rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $admin_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
						}
					}
				}
			}
			exit();
		}
	}

	// Email download
	public function emaildownload()
	{
		$db            = JFactory::getDbo();
		$query         = $db->getQuery(true);
		$config        = rsfilesHelper::getConfig();
		$path          = rsfilesHelper::getPath();
		$session       = JFactory::getSession();
		$briefcase     = rsfilesHelper::isBriefcase();
		$dfolder       = $briefcase ? $session->get('rsfilesbriefcasefolder') : $session->get('rsfilesdownloadfolder');
		$fullpath      = $dfolder . $this->ds . $path;
		$itemid        = rsfilesHelper::getItemid();
		$to            = $this->input->getString('email', '');
		$toname        = $this->input->getString('name', '');
		$user          = JFactory::getUser();
		$captcha_valid = true;
		$root          = $briefcase ? $config->briefcase_folder : $config->download_folder;
		$thepath       = str_replace($root . $this->ds, '', $fullpath);
		$mod_hash      = $this->input->getString('hash', '');

		// Validate captcha
		if ($config->captcha_enabled)
		{
			$captcha_valid = $this->validate();
		}

		if (!$captcha_valid)
		{
			$session->set('rsfiles_email', $to);
			$session->set('rsfiles_name', $toname);
			$this->setError(JText::_('COM_RSFILES_INVALID_CAPTCHA'));

			return false;
		}

		// Check and validate the email address
		if (empty($to) || !JMailHelper::isEmailAddress($to))
		{
			$this->setError(JText::_('COM_RSFILES_INVALID_EMAIL_ADDRESS'));
			$session->set('rsfiles_email', $to);
			$session->set('rsfiles_name', $toname);

			return false;
		}

		$query->clear()->select($db->qn('IdLicense'))->from($db->qn('#__rsfiles_files'))->where($db->qn('FilePath') . ' = ' . $db->q($thepath));
		$db->setQuery($query);
		if ($license = (int) $db->loadResult())
		{
			$agreement = $this->input->getInt('agreement', 0);
			if (!$agreement)
			{
				$session->set('rsfiles_email', $to);
				$session->set('rsfiles_name', $toname);
				$this->setError(JText::_('COM_RSFILES_CHECK_AGREEMENT'));

				return false;
			}
		}

		if ($config->consent && !$this->input->getInt('consent'))
		{
			$session->set('rsfiles_email', $to);
			$session->set('rsfiles_name', $toname);
			$this->setError(JText::_('COM_RSFILES_CONSENT_ERROR'));

			return false;
		}

		// Check if its published or not
		$published = rsfilesHelper::external($path) ? rsfilesHelper::published($path) : rsfilesHelper::published($fullpath);

		if (!$briefcase)
		{
			if ($mod_hash)
			{
				if (!$published)
				{
					if (rsfilesHelper::checkHash())
					{
						$published = true;
					}
				}
			}
		}

		if (!$published)
		{
			$this->setError(JText::_('COM_RSFILES_FILE_UNPUBLISHED'));

			return false;
		}

		// RSMail! integration
		if ($config->rsmail_integration && !empty($config->rsmail_list_id) && rsfilesHelper::isRsmail())
		{
			require_once JPATH_SITE . '/components/com_rsmail/helpers/actions.php';

			// Get a new instance of the RSMail! helper
			$rsmail = new rsmHelper();

			// Get the state
			$state = $rsmail->getState();

			$vars = array($config->rsmail_field_name => $toname);

			// Prepare list
			$list = $rsmail->setList($config->rsmail_list_id, $vars);

			// Subscribe user
			$idsubscriber = $rsmail->subscribe($to, $list, $state, null, true);

			if ($idsubscriber)
			{
				// The user must confirm his subscription
				if (!$state)
				{
					$hash = md5($config->rsmail_list_id . $idsubscriber . $to);
					$rsmail->confirmation($config->rsmail_list_id, $to, $hash);
				}

				//send notifications
				$rsmail->notifications($config->rsmail_list_id, $to, $vars);
			}
		}

		// Send the download email
		if ($download_email = rsfilesHelper::getMessage('download'))
		{

			$session->clear('rsfiles_email');
			$session->clear('rsfiles_name');

			$ip    = rsfilesHelper::getIP(true);
			$hash  = md5(uniqid($ip));
			$dpath = base64_encode($path);
			$url   = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&task=rsfiles.download' . ($briefcase ? '&from=briefcase' : '') . '&path=' . $dpath . '&email=1&emailhash=' . $hash . ($mod_hash ? '&hash=' . $mod_hash : '') . $itemid);

			if (rsfilesHelper::external($path))
			{
				$query->clear()->select($db->qn('FilePath'))->select($db->qn('FileName'))->select($db->qn('DownloadName'))->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . (int) $path);
				$db->setQuery($query);
				$extenalFile = $db->loadObject();
				$name        = !empty($extenalFile->FileName) ? $extenalFile->FileName : (!empty($extenalFile->DownloadName) ? $extenalFile->DownloadName : rsfilesHelper::getName($extenalFile->FilePath));

				$url    = '<a target="_blank" href="' . $url . '">' . $name . '</a>';
				$IdFile = (int) $path;
			}
			else
			{
				$query->clear()->select($db->qn('IdFile'))->from($db->qn('#__rsfiles_files'))->where($db->qn('FilePath') . ' = ' . $db->q($path));
				$db->setQuery($query);
				$IdFile = (int) $db->loadResult();

				$url = '<a target="_blank" href="' . $url . '">' . rsfilesHelper::getName($path) . '</a>';
			}

			$query->clear()
				->insert($db->qn('#__rsfiles_email_downloads'))
				->set($db->qn('hash') . ' = ' . $db->q($hash))
				->set($db->qn('date') . ' = ' . $db->q(JFactory::getDate()->toSql()))
				->set($db->qn('email') . ' = ' . $db->q($to))
				->set($db->qn('name') . ' = ' . $db->q($toname))
				->set($db->qn('IdFile') . ' = ' . $db->q($IdFile));

			$db->setQuery($query);
			$db->execute();

			$nr_days = (int) $config->remove_days;

			if ($nr_days && $config->enable_remove_days)
			{
				$query->clear()
					->delete()->from($db->qn('#__rsfiles_email_downloads'))
					->where($db->qn('date') . ' < DATE_SUB(' . $db->q(JFactory::getDate()->toSql()) . ',INTERVAL ' . $nr_days . ' DAY)');

				$db->setQuery($query);
				$db->execute();
			}

			$cc  = !empty($config->email_cc) ? $config->email_cc : null;
			$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

			$subject = $download_email->subject;
			$body    = $download_email->message;

			$bad  = array('{email}', '{downloadurl}', '{name}');
			$good = array($to, $url, $toname);
			$body = str_replace($bad, $good, $body);

			rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $to, $subject, $body, $download_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);

			return true;
		}
		else
		{
			$session->set('rsfiles_email', $to);
			$session->set('rsfiles_name', $toname);

			return false;
		}
	}

	// Create a new user briefcase folder
	public function newbriefcase()
	{
		$db       = JFactory::getDbo();
		$query    = $db->getQuery(true);
		$id       = $this->input->getInt('id');
		$config   = rsfilesHelper::getConfig();
		$root     = rsfilesHelper::getConfig('briefcase_folder');
		$maintain = rsfilesHelper::briefcase('CanMaintainBriefcase');

		// Can create new user briefcase
		if (!$maintain)
		{
			$this->setError(JText::_('COM_RSFILES_CANNOT_CREATE_BRIEFCASE_USERS'));

			return false;
		}

		// Do we have an empty id
		if (empty($id))
		{
			$this->setError(JText::_('COM_RSFILES_INVALID_ID'));

			return false;
		}

		// Do we have a valid user
		$query->select('COUNT(' . $db->qn('id') . ')')
			->from($db->qn('#__users'))
			->where($db->qn('id') . ' = ' . $id);
		$db->setQuery($query);
		if (!$db->loadResult())
		{
			$this->setError(JText::_('COM_RSFILES_INVALID_USER'));

			return false;
		}

		// Check if already exists
		if (JFolder::exists($root . $this->ds . $id))
		{
			$this->setError(JText::_('COM_RSFILES_BRIEFCASE_ALREADY_EXISTS'));

			return false;
		}

		if (JFolder::create($root . $this->ds . $id))
		{
			rsfilesHelper::clearCache($root);

			$user = JFactory::getUser($id);
			$name = $config->briefcase_name ? $user->get('name') : $user->get('username');
			$query->clear()
				->insert($db->qn('#__rsfiles_files'))
				->set($db->qn('FileName') . ' = ' . $db->q(JText::sprintf('COM_RSFILES_BRIEFCASE_FOLDER_LABEL', $name)))
				->set($db->qn('FilePath') . ' = ' . $db->q($id))
				->set($db->qn('DateAdded') . ' = ' . $db->q(JFactory::getDate()->toSql()))
				->set($db->qn('FileDescription') . ' = ' . $db->q(''))
				->set($db->qn('metadescription') . ' = ' . $db->q(''))
				->set($db->qn('metakeywords') . ' = ' . $db->q(''))
				->set($db->qn('FileParent') . ' = ' . $db->q(''))
				->set($db->qn('DownloadMethod') . ' = 0')
				->set($db->qn('briefcase') . ' = 1')
				->set($db->qn('published') . ' = 1');

			$db->setQuery($query);
			$db->execute();

			return true;
		}
		else
		{
			$this->setError(JText::_('COM_RSFILES_CANNOT_CREATE_BRIEFCASE'));

			return false;
		}
	}

	// Get briefcase files
	public function getBriefcaseFiles()
	{
		$db               = JFactory::getDbo();
		$query            = $db->getQuery(true);
		$user             = JFactory::getUser();
		$itemid           = rsfilesHelper::getItemid();
		$config           = rsfilesHelper::getConfig();
		$briefcase_folder = rsfilesHelper::getBriefcase();
		$name             = $config->briefcase_name ? $user->get('name') : $user->get('username');

		// Keep guests out of the briefcase folder
		if ($user->get('id') == 0)
		{
			rsfilesHelper::errors(JText::_('COM_RSFILES_BRIEFCASE_ERROR_1'), 'index.php');

			return false;
		}

		$canupload   = rsfilesHelper::briefcase('CanUploadBriefcase');
		$canmaintain = rsfilesHelper::briefcase('CanMaintainBriefcase');
		$candelete   = rsfilesHelper::briefcase('CanDeleteBriefcase');
		$candownload = rsfilesHelper::briefcase('CanDownloadBriefcase');

		if ($canmaintain)
		{
			$current = $this->getCurrent();
			if (strpos(realpath($current), realpath($briefcase_folder)) !== 0)
			{
				rsfilesHelper::errors(JText::_('COM_RSFILES_OUTSIDE_OF_BRIEFCASE'), 'index.php');

				return false;
			}
		}
		else
		{
			// Check if the user has any permission
			if (!$candownload && !$canupload && !$candelete)
			{
				rsfilesHelper::errors(JText::_('COM_RSFILES_BRIEFCASE_ERROR_2'), 'index.php');

				return false;
			}

			//check to see if the user folder exists
			$user_folder = $config->briefcase_folder . $this->ds . $user->get('id');

			if (!JFolder::exists($user_folder))
			{
				if (JFolder::create($user_folder))
				{
					$query->clear()
						->insert($db->qn('#__rsfiles_files'))
						->set($db->qn('FileName') . ' = ' . $db->q(JText::sprintf('COM_RSFILES_BRIEFCASE_FOLDER_LABEL', $name)))
						->set($db->qn('FilePath') . ' = ' . $db->q($user->get('id')))
						->set($db->qn('DateAdded') . ' = ' . $db->q(JFactory::getDate()->toSql()))
						->set($db->qn('FileDescription') . ' = ' . $db->q(''))
						->set($db->qn('metadescription') . ' = ' . $db->q(''))
						->set($db->qn('metakeywords') . ' = ' . $db->q(''))
						->set($db->qn('FileParent') . ' = ' . $db->q(''))
						->set($db->qn('DownloadMethod') . ' = 0')
						->set($db->qn('briefcase') . ' = 1')
						->set($db->qn('published') . ' = 1');

					$db->setQuery($query);
					$db->execute();
				}
				else
				{
					return false;
				}
			}

			$folder = rsfilesHelper::getFolder();
			if (!empty($folder))
			{
				$briefcase_folder = $user_folder . $this->ds . $folder;
			}
			else
			{
				$briefcase_folder = $user_folder;
			}

			if (strpos($briefcase_folder, realpath($config->briefcase_folder)) !== 0)
			{
				return false;
			}
		}

		if (!empty($briefcase_folder) && JFolder::exists($briefcase_folder))
		{
			require_once JPATH_SITE . '/components/com_rsfiles/helpers/files.php';

			$theclass = new RSFilesFiles($briefcase_folder, 'site', $itemid, 0, $this->getOrder(), $this->getOrderDir());
			$files    = $theclass->getFiles();
			$folders  = $theclass->getFolders();

			$data         = array_merge($folders, $files);
			$this->_total = count($data);

			// Pagination
			$data = array_slice($data, $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limitstart'), $this->getState('com_rsfiles.' . $this->input->get('layout') . '.limit'));

			return $data;
		}

		return false;
	}

	// Get bookmarked files
	public function getBookmarks()
	{
		$db          = JFactory::getDbo();
		$query       = $db->getQuery(true);
		$session     = JFactory::getSession();
		$uid         = JFactory::getUser()->get('id');
		$config      = rsfilesHelper::getConfig();
		$default     = array('download_folder' => array(), 'briefcase_folder' => array());
		$data        = $session->get('rsfl_bookmarks', $default);
		$dfolder     = $session->get('rsfilesdownloadfolder');
		$bfolder     = $config->briefcase_folder;
		$candownload = rsfilesHelper::briefcase('CanDownloadBriefcase') || rsfilesHelper::briefcase('CanMaintainBriefcase') ? 1 : 0;
		$files       = array();

		// Get user saved bookmarked files
		$userBookmarks = rsfilesHelper::getUserBookmarks();

		$download_folder  = array_unique(array_merge($data['download_folder'], $userBookmarks['download_folder']));
		$briefcase_folder = array_unique(array_merge($data['briefcase_folder'], $userBookmarks['briefcase_folder']));

		if (empty($download_folder) && empty($briefcase_folder))
		{
			return $files;
		}

		// Process download folder bookmarks
		if ($download_folder)
		{
			foreach ($download_folder as $file)
			{
				// If we have a folder configured in the menu item, check to show only items from that folder and sub-folders
				if (strpos($file, $dfolder) === false)
				{
					continue;
				}

				$file = str_replace($config->download_folder . $this->ds, '', $file);

				// Check for file permission
				if (!rsfilesHelper::permissions('CanView', urldecode($file)) || !rsfilesHelper::permissions('CanDownload', urldecode($file)))
				{
					continue;
				}

				$query->clear()
					->select($db->qn('FileName'))->select($db->qn('published'))
					->select($db->qn('DownloadLimit'))->select($db->qn('Downloads'))
					->from($db->qn('#__rsfiles_files'))
					->where($db->qn('briefcase') . ' = 0')
					->where($db->qn('FilePath') . ' = ' . $db->q($file));

				$db->setQuery($query);
				$info = $db->loadObject();

				// Check for download limit
				if (!empty($info->DownloadLimit) && $info->Downloads >= $info->DownloadLimit)
				{
					continue;
				}

				// Check for file status
				if (!$info->published)
				{
					continue;
				}

				$class       = new stdClass();
				$class->name = !empty($info->FileName) ? $info->FileName : rsfilesHelper::getName($file);
				$class->path = $file;
				$class->root = 'download_folder';

				$files[] = $class;
			}
		}

		// Process briefcase folder bookmarks
		if ($briefcase_folder && $candownload)
		{
			foreach ($briefcase_folder as $file)
			{
				if (strpos($file, $bfolder) === false)
				{
					continue;
				}

				$file = str_replace($bfolder . $this->ds, '', $file);

				$query->clear()
					->select($db->qn('FileName'))->select($db->qn('published'))
					->select($db->qn('DownloadLimit'))->select($db->qn('Downloads'))
					->from($db->qn('#__rsfiles_files'))
					->where($db->qn('briefcase') . ' = 1')
					->where($db->qn('FilePath') . ' = ' . $db->q($file));

				$db->setQuery($query);
				$info = $db->loadObject();

				// Check for download limit
				if (!empty($info->DownloadLimit) && $info->Downloads >= $info->DownloadLimit)
				{
					continue;
				}

				// Check for file status
				if (!$info->published)
				{
					continue;
				}

				$class       = new stdClass();
				$class->name = !empty($info->FileName) ? $info->FileName : rsfilesHelper::getName($file);
				$class->path = $file;
				$class->root = 'briefcase_folder';

				$files[] = $class;
			}
		}

		return $files;
	}

	// Bookmark a file
	public function bookmark()
	{
		$db        = JFactory::getDbo();
		$session   = JFactory::getSession();
		$config    = rsfilesHelper::getConfig();
		$briefcase = rsfilesHelper::isBriefcase();
		$path      = rsfilesHelper::getPath();
		$fullpath  = realpath($this->absoluteRoot . $this->ds . $path);
		$exists    = false;
		$response  = new stdClass();

		$response->status = false;

		if (!$config->show_bookmark)
		{
			$response->message = JText::_('COM_RSFILES_BOOKMARK_DISABLE', true);

			return $response;
		}

		if (rsfilesHelper::external($path))
		{
			$response->message = JText::_('COM_RSFILES_FILE_BOOKMARKED_ERROR', true);

			return $response;
		}

		if (!file_exists($fullpath))
		{
			$response->message = JText::_('COM_RSFILES_NO_SUCH_FILE', true);

			return $response;
		}

		if (strpos($fullpath, realpath($this->absoluteRoot)) !== 0)
		{
			$response->message = JText::_('COM_RSFILES_FILE_UNREACHABLE', true);

			return $response;
		}

		// Get bookmarks
		$bookmarks = rsfilesHelper::getBookmarks();

		if (empty($bookmarks))
		{
			$bookmarks['briefcase_folder'] = array();
			$bookmarks['download_folder']  = array();
		}

		if ($briefcase)
		{
			if (!in_array($fullpath, $bookmarks['briefcase_folder']))
			{
				$bookmarks['briefcase_folder'][] = $fullpath;
				rsfilesHelper::addUserBookmark($fullpath);
			}
			else
			{
				$key = array_search($fullpath, $bookmarks['briefcase_folder']);

				if ($key !== false)
				{
					unset($bookmarks['briefcase_folder'][$key]);
				}

				$exists = true;
			}
		}
		else
		{
			if (!in_array($fullpath, $bookmarks['download_folder']))
			{
				$bookmarks['download_folder'][] = $fullpath;
				rsfilesHelper::addUserBookmark($fullpath);
			}
			else
			{
				$key = array_search($fullpath, $bookmarks['download_folder']);

				if ($key !== false)
				{
					unset($bookmarks['download_folder'][$key]);
				}

				$exists = true;
			}
		}

		$session->set('rsfl_bookmarks', $bookmarks);

		if ($exists)
		{
			rsfilesHelper::removeUserBookmark($fullpath);

			$response->status  = true;
			$response->removed = true;
			$response->title   = JText::_('COM_RSFILES_NAVBAR_BOOKMARK_FILE', true);
			$response->message = JText::_('COM_RSFILES_FILE_BOOKMARK_REMOVED', true);
		}
		else
		{
			$response->status  = true;
			$response->title   = JText::_('COM_RSFILES_NAVBAR_FILE_IS_BOOKMARKED', true);
			$response->message = JText::_('COM_RSFILES_FILE_BOOKMARKED', true);
		}

		return $response;
	}

	// Remove bookmark
	public function removebookmark()
	{
		$path = rsfilesHelper::getPath();

		rsfilesHelper::deleteBookmark($path);

		return true;
	}

	public function deletebookmarks($pks)
	{
		if ($pks)
		{
			foreach ($pks as $pk)
			{
				rsfilesHelper::deleteBookmark($pk);
			}
		}

		return true;
	}

	// Download bookmarks
	public function downloadbookmarks()
	{
		jimport('joomla.filesystem.archive');
		jimport('joomla.filesystem.file');

		$db       = JFactory::getDbo();
		$query    = $db->getQuery(true);
		$config   = rsfilesHelper::getConfig();
		$jarchive = new Archive;
		$adapter  = $jarchive->getAdapter('zip');
		$cids     = $this->input->get('cid', array(), 'array');
		$session  = JFactory::getSession();
		$tmp      = JFactory::getConfig()->get('tmp_path');
		$uid      = JFactory::getUser()->get('id');
		$files    = array();

		if (!$config->show_bookmark || empty($cids))
		{
			return false;
		}

		foreach ($cids as $cid)
		{
			$cid = urldecode($cid);

			if (file_exists($session->get('rsfilesdownloadfolder') . $this->ds . $cid))
			{
				$file = $session->get('rsfilesdownloadfolder') . $this->ds . $cid;

				if (strpos(realpath($file), realpath($session->get('rsfilesdownloadfolder'))) !== 0)
				{
					continue;
				}

				$path = str_replace($config->download_folder . $this->ds, '', $file);

				$query->clear()
					->select($db->qn('published'))->select($db->qn('DownloadLimit'))->select($db->qn('Downloads'))
					->from($db->qn('#__rsfiles_files'))
					->where($db->qn('briefcase') . ' = 0')
					->where($db->qn('FilePath') . ' = ' . $db->q($path));

				$db->setQuery($query);
				$info = $db->loadObject();

				// Check for download limit
				if (!empty($info->DownloadLimit) && $info->Downloads >= $info->DownloadLimit)
				{
					continue;
				}

				// Check for file status
				if (!$info->published)
				{
					continue;
				}

				if (!empty($info->DownloadLimit))
				{
				$query->clear()
					->update($db->qn('#__rsfiles_files'))
					->set($db->qn('Downloads') . ' = ' . $db->qn('Downloads') . ' + 1')
					->where($db->qn('FilePath') . ' = ' . $db->q($path));
				$db->setQuery($query);
				$db->execute();
			}
			}
			else
			{
				if (rsfilesHelper::briefcase('CanMaintainBriefcase'))
				{
					$root = $config->briefcase_folder;
					$file = $config->briefcase_folder . $this->ds . $cid;
				}
				else
				{
					$root = $config->briefcase_folder . $this->ds . $uid;
					$file = $config->briefcase_folder . $this->ds . $uid . $this->ds . $cid;
				}

				if (strpos(realpath($file), realpath($root)) !== 0)
				{
					continue;
				}

				$path = str_replace($config->briefcase_folder . $this->ds, '', $file);

				$query->clear()
					->select($db->qn('published'))->select($db->qn('DownloadLimit'))->select($db->qn('Downloads'))
					->from($db->qn('#__rsfiles_files'))
					->where($db->qn('briefcase') . ' = 1')
					->where($db->qn('FilePath') . ' = ' . $db->q($path));

				$db->setQuery($query);
				$info = $db->loadObject();

				// Check for download limit
				if (!empty($info->DownloadLimit) && $info->Downloads >= $info->DownloadLimit)
				{
					continue;
				}

				// Check for file status
				if (!$info->published)
				{
					continue;
				}

				if (!empty($info->DownloadLimit))
				{
				$query->clear()
					->update($db->qn('#__rsfiles_files'))
					->set($db->qn('Downloads') . ' = ' . $db->qn('Downloads') . ' + 1')
					->where($db->qn('FilePath') . ' = ' . $db->q($path));
				$db->setQuery($query);
				$db->execute();
			}
			}

			$data    = file_get_contents($file);
			$files[] = array('name' => rsfilesHelper::getName($file), 'data' => $data);
		}

		if (!empty($files))
		{
			$filename = 'download_package_' . time() . '.zip';
			$zip      = $adapter->create($tmp . $this->ds . $filename, $files);

			if ($zip)
			{
				@ob_end_clean();
				$filename = basename($filename);
				header("Cache-Control: public, must-revalidate");
				header('Cache-Control: pre-check=0, post-check=0, max-age=0');
				header("Pragma: no-cache");
				header("Expires: 0");
				header("Content-Description: File Transfer");
				header("Expires: Sat, 01 Jan 2000 01:00:00 GMT");
				header("Content-Type: application/octet-stream");
				header("Content-Length: " . (string) filesize($tmp . $this->ds . $filename));
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header("Content-Transfer-Encoding: binary\n");
				rsfilesHelper::readfile_chunked($tmp . $this->ds . $filename);
				exit();
			}
		}
	}

	// Get folder description
	public function getFolderDescription()
	{
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$config = rsfilesHelper::getConfig();

		if (!$config->show_folder_desc)
		{
			return false;
		}

		if ($this->absoluteFolder == $config->download_folder)
		{
			return $config->download_description;
		}

		if (is_dir($this->absoluteFolder))
		{
			if (rsfilesHelper::isBriefcase())
			{
				if (!rsfilesHelper::briefcase('CanMaintainBriefcase'))
				{
					$path = str_replace($config->briefcase_folder . $this->ds, '', $this->absoluteFolder);
				}
				else
				{
					$path = $this->relativeFolder;
				}
			}
			else
			{
				$path = $this->relativeFolder;
			}

			$query->clear()
				->select($db->qn('FileDescription'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('FilePath') . ' = ' . $db->q($path));

			$db->setQuery($query);

			return $db->loadResult();
		}

		return false;
	}

	// Get the preview
	public function getPreview()
	{
		$db        = JFactory::getDbo();
		$query     = $db->getQuery(true);
		$path      = rsfilesHelper::getPath();
		$droot     = rsfilesHelper::getConfig('download_folder');
		$broot     = rsfilesHelper::getConfig('briefcase_folder');
		$briefcase = rsfilesHelper::isBriefcase();

		if (rsfilesHelper::external($path))
		{
			$id = (int) $path;
		}
		else
		{
			$fullpath = $this->absoluteRoot . $this->ds . $path;

			if ($briefcase)
			{
				$path = str_replace($broot . $this->ds, '', $fullpath);
			}
			else
			{
				$path = str_replace($droot . $this->ds, '', $fullpath);
			}

			$query->clear()
				->select($db->qn('IdFile'))
				->from($db->qn('#__rsfiles_files'))
				->where($db->qn('FilePath') . ' = ' . $db->q($path));
			$db->setQuery($query);
			$id = (int) $db->loadResult();
		}

		rsfilesHelper::preview($id);
	}

	// Report file
	public function report()
	{
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$user   = JFactory::getUser();
		$config = rsfilesHelper::getConfig();
		$jform  = $this->input->get('jform', array(), 'array');
		$ip     = rsfilesHelper::getIp(true);
		$path   = urldecode($jform['path']);
		$report = $jform['report'];

		if (!$config->show_report)
		{
			return false;
		}

		if (rsfilesHelper::external($path))
		{
			$idfile = (int) $path;
		}
		else
		{
			$fullpath = $this->absoluteRoot . $this->ds . $path;

			$query->select($db->qn('IdFile'))->from($db->qn('#__rsfiles_files'))->where($db->qn('FilePath') . ' = ' . $db->q($path));
			$db->setQuery($query);
			if (!$idfile = $db->loadResult())
			{
				$query->clear()
					->insert($db->qn('#__rsfiles_files'))
					->set($db->qn('FilePath') . ' = ' . $db->q($path))
					->set($db->qn('briefcase') . ' = ' . $db->q(0))
					->set($db->qn('FileDescription') . ' = ' . $db->q(''))
					->set($db->qn('metadescription') . ' = ' . $db->q(''))
					->set($db->qn('metakeywords') . ' = ' . $db->q(''))
					->set($db->qn('FileParent') . ' = ' . $db->q(''))
					->set($db->qn('DateAdded') . ' = ' . $db->q(JFactory::getDate()->toSql()));

				if (is_file($fullpath))
				{
					$query->set($db->qn('hash') . ' = ' . $db->q(md5_file($fullpath)));
				}

				$db->setQuery($query);
				$db->execute();
				$idfile = $db->insertid();
			}
		}

		if ($idfile)
		{
			$query->clear()
				->insert($db->qn('#__rsfiles_reports'))
				->set($db->qn('IdFile') . ' = ' . (int) $idfile)
				->set($db->qn('ReportMessage') . ' = ' . $db->q($report))
				->set($db->qn('uid') . ' = ' . $db->q($user->get('id')))
				->set($db->qn('ip') . ' = ' . $db->q($ip))
				->set($db->qn('date') . ' = ' . $db->q(JFactory::getDate()->toSql()));

			$db->setQuery($query);
			$db->execute();

			if ($report_email = rsfilesHelper::getMessage('report'))
			{
				if ($report_email->enable && !empty($report_email->to))
				{

					$cc  = !empty($config->email_cc) ? $config->email_cc : null;
					$bcc = !empty($config->email_bcc) ? $config->email_bcc : null;

					$subject = $report_email->subject;
					$body    = $report_email->message;

					$url  = rsfilesHelper::getBase() . JRoute::_('index.php?option=com_rsfiles&layout=download&path=' . rsfilesHelper::encode($path));
					$file = '<a href="' . $url . '">' . $url . '</a>';
					$bad  = array('{username}', '{ip}', '{report}', '{filename}');
					$good = array($user->get('username'), $ip, $report, $file);
					$body = str_replace($bad, $good, $body);

					if ($emails = explode(',', $report_email->to))
					{
						foreach ($emails as $email)
						{
							$email = trim($email);

							if (empty($email))
							{
								continue;
							}

							rsfilesHelper::sendMail($config->email_from, $config->email_from_name, $email, $subject, $body, $report_email->mode, $cc, $bcc, null, $config->email_reply, $config->email_reply_name);
						}
					}
				}
			}

			return true;
		}

		return false;
	}

	// Load license details
	public function getLicense()
	{
		$db    = JFactory::getDbo();
		$id    = $this->input->getInt('id', 0);
		$query = $db->getQuery(true)->select('*')->from($db->qn('#__rsfiles_licenses'))->where($db->qn('IdLicense') . ' = ' . $id);

		$db->setQuery($query);

		return $db->loadObject();
	}

	// Delete file thumb
	public function deletethumb($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)->select($db->qn('FileThumb'))->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . (int) $id);

		$db->setQuery($query);
		$thumb = $db->loadResult();

		if (!empty($thumb))
		{
			if (JFile::exists(JPATH_SITE . '/components/com_rsfiles/images/thumbs/files/' . $thumb))
			{
				if (JFile::delete(JPATH_SITE . '/components/com_rsfiles/images/thumbs/files/' . $thumb))
				{
					$query = $db->getQuery(true)->update($db->qn('#__rsfiles_files'))->set($db->qn('FileThumb') . ' = ' . $db->q(''))->where($db->qn('IdFile') . ' = ' . (int) $id);
					$db->setQuery($query);
					$db->execute();

					return true;
				}
			}
		}

		$this->setError(JText::_('COM_RSFILES_THUMB_DELETE_ERROR'));

		return false;
	}

	// Delete file preview
	public function deletepreview($id)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)->select($db->qn('preview'))->from($db->qn('#__rsfiles_files'))->where($db->qn('IdFile') . ' = ' . (int) $id);

		$db->setQuery($query);
		$preview = $db->loadResult();

		if (!empty($preview))
		{
			if (JFile::exists(JPATH_SITE . '/components/com_rsfiles/images/preview/' . $preview))
			{
				if (JFile::delete(JPATH_SITE . '/components/com_rsfiles/images/preview/' . $preview))
				{
					$query = $db->getQuery(true)->update($db->qn('#__rsfiles_files'))->set($db->qn('preview') . ' = ' . $db->q(''))->where($db->qn('IdFile') . ' = ' . (int) $id);
					$db->setQuery($query);
					$db->execute();

					return true;
				}
			}
		}

		$this->setError(JText::_('COM_RSFILES_PREVIEW_DELETE_ERROR'));

		return false;
	}

	// Get email from hash
	public function getEmailFromHash()
	{
		$db    = JFactory::getDbo();
		$hash  = $this->input->getCmd('emailhash');
		$query = $db->getQuery(true)->select($db->qn('email'))->from($db->qn('#__rsfiles_email_downloads'))->where($db->qn('hash') . ' = ' . $db->q($hash));

		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}

	// Get name from hash
	public function getNameFromHash()
	{
		$db    = JFactory::getDbo();
		$hash  = $this->input->getCmd('emailhash');
		$query = $db->getQuery(true)->select($db->qn('name'))->from($db->qn('#__rsfiles_email_downloads'))->where($db->qn('hash') . ' = ' . $db->q($hash));

		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}

	// Check hash
	public function verifyEmailHash()
	{
		$db    = JFactory::getDbo();
		$hash  = $this->input->getCmd('emailhash');
		$query = $db->getQuery(true)->select($db->qn('id'))->from($db->qn('#__rsfiles_email_downloads'))->where($db->qn('hash') . ' = ' . $db->q($hash));

		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}

	// File from hash was downloaded
	public function updateEmailHashDownload()
	{
		$db    = JFactory::getDbo();
		$hash  = $this->input->getCmd('emailhash');
		$query = $db->getQuery(true);

		$query->update($db->qn('#__rsfiles_email_downloads'))
			->set($db->qn('downloaded') . ' = ' . $db->q(JFactory::getDate()->toSql()))
			->where($db->qn('hash') . ' = ' . $db->q($hash));

		$db->setQuery($query);
		$db->execute();
	}

	// Validate captcha
	public function validate()
	{
		$config   = rsfilesHelper::getConfig();
		$response = true;

		if ($config->captcha_enabled == 1)
		{
			$string   = $this->input->getString('captcha', '');
			$captcha  = new JSecurImage();
			$response = $captcha->check($string);
		}
		else if ($config->captcha_enabled == 3)
		{
			try
			{
				$response  = $this->input->get('g-recaptcha-response', '', 'raw');
				$ip        = $_SERVER['REMOTE_ADDR'];
				$secretKey = $config->recaptcha_new_secret_key;

				jimport('joomla.http.factory');
				$http = JHttpFactory::getHttp();
				if ($request = $http->get('https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) . '&response=' . urlencode($response) . '&remoteip=' . urlencode($ip)))
				{
					$json = json_decode($request->body);
				}
			}
			catch (Exception $e)
			{
				$response = false;
			}

			if (!$json->success)
			{
				$response = false;
			}
		}

		return $response;
	}

	// Get State options
	public function getStates()
	{
		return array(JHtml::_('select.option', 0, JText::_('JUNPUBLISHED')), JHtml::_('select.option', 1, JText::_('JPUBLISHED')));
	}

	// Get Yes/No options
	public function getYesNo()
	{
		return array(JHtml::_('select.option', 0, JText::_('JNO')), JHtml::_('select.option', 1, JText::_('JYES')));
	}

	// Get return page
	public function getReturnPage()
	{
		return $this->input->get('return', null, 'base64');
	}

	// Set the root folder
	protected function setRoot()
	{
		$session   = JFactory::getSession();
		$config    = rsfilesHelper::getConfig();
		$uid       = JFactory::getUser()->get('id');
		$params    = rsfilesHelper::getParams();
		$briefcase = $this->input->get('layout') == 'briefcase' || $this->input->get('from') == 'briefcase';
		$d_root    = realpath($config->download_folder);
		$b_root    = realpath($config->briefcase_folder);

		$this->d_root = $d_root;
		$this->b_root = $b_root;

		// Set Root path
		if ($briefcase)
		{
			if (!rsfilesHelper::briefcase('CanMaintainBriefcase'))
			{
				$b_root .= $this->ds . $uid;
			}

			$aRoot = realpath($b_root);
			$aRoot = rtrim($aRoot, $this->ds);
			$rRoot = str_replace($b_root, '', $aRoot);
			$rRoot = ltrim($rRoot, $this->ds);

			$session->set('rsfilesbriefcasefolder', $aRoot);
			$session->set('rsf_absolute_root', $aRoot);
			$session->set('rsf_relative_root', $rRoot);
		}
		else
		{
			// If we are viewing a related file then we need to determine the path and not use the default method
			if (!JFactory::getApplication()->input->get('relatedfile', 0))
			{
				// check if we need to override any params because were in the module
				if (JFactory::getApplication()->scope == 'mod_rsfiles_list_tags' && is_object(JFactory::getApplication()->getUserState('mod_rsfiles_list_tags.params')))
				{
					$folder = JFactory::getApplication()->getUserState('mod_rsfiles_list_tags.params')->get('folder');
				}
				else
				{
					$folder = $params->get('folder', '');
				}
			}

			if (!empty($folder) && is_dir($d_root . $this->ds . $folder))
			{
				$aRoot = realpath($d_root . $this->ds . $folder);
			}
			else
			{
				$aRoot = $d_root;
			}

			$aRoot = rtrim($aRoot, $this->ds);
			$rRoot = str_replace($d_root, '', $aRoot);
			$rRoot = ltrim($rRoot, $this->ds);

			$session->set('rsfilesdownloadfolder', $aRoot);
			$session->set('rsf_absolute_root', $aRoot);
			$session->set('rsf_relative_root', $rRoot);
		}

		$this->absoluteRoot = $aRoot;
		$this->relativeRoot = $rRoot;
	}

	// Set the current folder
	protected function setFolder()
	{
		$session = JFactory::getSession();
		$folder  = rsfilesHelper::getFolder();

		if (!empty($folder) && is_dir(realpath($this->absoluteRoot . $this->ds . $folder)))
		{
			$this->absoluteFolder = realpath($this->absoluteRoot . $this->ds . $folder);
			$this->relativeFolder = str_replace((rsfilesHelper::isBriefcase() ? $this->b_root : $this->d_root), '', $this->absoluteFolder);
			$this->relativeFolder = ltrim($this->relativeFolder, $this->ds);
		}
		else
		{
			$this->absoluteFolder = $this->absoluteRoot;
			$this->relativeFolder = $this->relativeRoot;
		}

		$session->set('rsf_absolute_folder', $this->absoluteFolder);
		$session->set('rsf_relative_folder', $this->relativeFolder);
	}

	public function getTags()
	{
		$params = rsfilesHelper::getParams();

		if ($tags = $params->get('tags'))
		{
			$db    = JFactory::getDbo();
			$query = $db->getQuery(true);
			$tags  = array_map('intval', $tags);

			$query->select($db->qn('id'))->select($db->qn('title'))
				->from($db->qn('#__rsfiles_tags'))
				->where($db->qn('id') . ' IN (' . implode(',', $tags) . ')');
			$db->setQuery($query);
			$items = $db->loadObjectList();

			if (count($items) >= 2)
			{
				return $items;
			}
		}

		return array();
	}

	public function getTag()
	{
		$params = rsfilesHelper::getParams();

		return $params->get('filter', 0) ? JFactory::getApplication()->getUserStateFromRequest('com_rsfiles.tag', 'filter_tag', 0, 'int') : 0;
	}

	public function getFileTags($tags) {
		if(count($tags)>0){
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$tags	= array_map('intval',$tags);

			$query->select($db->qn('id'))->select($db->qn('title'))
				->from($db->qn('#__rsfiles_tags'))
				->where($db->qn('id').' IN ('.implode(',',$tags).')');
			$db->setQuery($query);
			$items = $db->loadObjectList();
			$array_tags = array();
			if (count($items) >0) {
				foreach($items as $item){
					$array_tags[] = $item->title;
				}
				return $array_tags;
			}
		}

		return array();
	}

	public function FilterFileTags($alltags){
		$visibleTags = array(
			"Implementation Meeting",
			"Enforcement Exchange",
			"Information Exchange",
			"Plenary",
			"New and Evolving Technologies Technical Experts’ Meeting",
		);
		$tags = array();
		foreach($alltags as $tag){
			if(in_array($tag,$visibleTags)){
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	public function getFileTagAlias($key)
	{
		$tagAlias = array(
			"Implementation Meeting" => "IM",
			"Enforcement Exchange" => "EE",
			"Information Exchange" => "IE",
			"New and Evolving Technologies Technical Experts’ Meeting" => "NETTEM",
		);

		$tag = ($tagAlias[$key]) ? $tagAlias[$key] : $key;

		return $tag;
	}
}
