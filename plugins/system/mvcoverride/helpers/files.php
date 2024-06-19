<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */

defined('_JEXEC') or die('Restricted access');

if (file_exists(JPATH_SITE . '/components/com_rsfiles/helpers/rsfiles.php'))
{
	require_once JPATH_SITE . '/components/com_rsfiles/helpers/rsfiles.php';
	require_once JPATH_SITE . '/components/com_rsfiles/helpers/file.php';
}

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.path');

class RSFilesFiles
{
	/*
	*	The main folder
	*/
	public $folder = null;

	/*
	*	The location type (backend/frontend)
	*/
	public $location = null;

	/*
	*	The RSFiles! configuration object
	*/
	private $config = null;

	/*
	*	The database object
	*/
	private $_db = null;

	/*
	*	The Itemid
	*/
	protected $itemid = null;

	/*
	*	The page params
	*/
	protected $params = null;

	/*
	*	Directory separator
	*/
	protected $ds;

	/*
	*	Do we use this in the plugin
	*/
	protected $plugin = 0;

	/*
	*	Do we use this in the module
	*/
	protected $module = 0;

	/*
	*	The order
	*/
	protected $order = null;

	/*
	*	The order direction
	*/
	protected $orderdir = null;

	/*
	*	Filter for files
	*/
	protected $filter = '.';

	/*
	*	Enable search
	*/
	protected $search = true;


	/*
	*	Main constructor
	*/
	public function __construct($folder, $location = 'admin', $itemid = '', $plugin = 0, $order = null, $orderdir = null)
	{
		$app  = JFactory::getApplication();
		$lang = JFactory::getLanguage();

		// Is this from the plugin?
		$this->plugin = $plugin;

		// Load language files
		if ($app->isClient('administrator'))
			$lang->load('com_rsfiles', JPATH_ADMINISTRATOR);
		else
			$lang->load('com_rsfiles', JPATH_SITE);

		// Set the database object
		$this->_db = JFactory::getDBO();

		// Set the current root folder
		$this->folder = $folder;

		// Set the location
		$this->location = $location;

		// Set the configuration object
		$this->config = $this->getConfig();

		// Set the Itemid
		$this->itemid = $itemid;

		// Set the params
		$this->params = $this->getParams();

		// Set directory separator
		$this->ds = rsfilesHelper::ds();

		// Set the default ordering
		$this->order = $order;

		// Set the default ordering direction
		$this->orderdir = $orderdir;
	}

	/**
	 *    Modifies a property of the object, creating it if it does not already exist.
	 */
	public function set($name, $value)
	{
		$this->{$name} = $value;
	}

	/*
	*	Get the available files from the main folder
	*/
	public function getFiles($options = null)
	{
		$query  = $this->_db->getQuery(true);
		$input  = JFactory::getApplication()->input;
		$layout = $input->getCmd('layout', '');
		$search = mb_strtolower($input->getString('filter_search', ''));
		$search = $this->search ? $search : false;
		$from   = $input->get('from', '');
		$return = array();
		$files  = array();

		JModelLegacy::addIncludePath(JPATH_SITE . '/components/com_rsfiles/models', 'rsfilesModel');
		$model = JModelLegacy::getInstance('Rsfiles', 'rsfilesModel');

		// Get files for backend view
		if ($this->location == 'admin')
		{
			// Set the current download folder
			$root      = rsfilesHelper::getRoot();
			$briefcase = $root == 'briefcase';
			$dld_fld   = $this->config->{$root . '_folder'} . $this->ds;

			// Search trough files
			if (!empty($search))
			{
				if ($files_ftp = JFolder::files($this->config->{$root . '_folder'}, $this->filter, true, true, array('.htaccess')))
				{
					foreach ($files_ftp as $file)
					{
						if (mb_strpos(mb_strtolower(rsfilesHelper::getName($file)), $search) !== false)
						{
							if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
								$file = str_replace('/', "\\", $file);
							$files[] = $file;
						}
					}
				}

				$query->clear()
					->select($this->_db->qn('FilePath'))
					->from($this->_db->qn('#__rsfiles_files'))
					->where($this->_db->qn('briefcase') . ' = ' . $this->_db->q((int) $briefcase))
					->where('( LOWER(' . $this->_db->qn('FileName') . ') LIKE ' . $this->_db->q('%' . $search . '%') . ' OR LOWER(' . $this->_db->qn('FileDescription') . ') LIKE ' . $this->_db->q('%' . $search . '%') . ')');

				$this->_db->setQuery($query);
				if ($search_files = $this->_db->loadColumn())
				{
					foreach ($search_files as $search_file)
					{
						if (is_file($dld_fld . $search_file))
						{
							$files[] = $dld_fld . $search_file;
						}
					}
				}

				$files = array_unique($files);
			}
			else
			{
				// Get files from this folder
				$files = JFolder::files($this->folder, $this->filter, false, true, array('.htaccess'));
			}
		}
		elseif ($this->location == 'tags')
		{
			$files = $this->folder;
		}
		else
		{
			// Set the current download folder
			$session = JFactory::getSession();
			if ($from == 'briefcase' || $layout == 'briefcase')
			{
				$dld_fld = $this->config->briefcase_folder . $this->ds;

				if (!rsfilesHelper::briefcase('CanMaintainBriefcase'))
				{
					$dld_fld .= JFactory::getUser()->get('id') . $this->ds;
				}

				if ($this->module)
				{
					$dld_fld = $this->config->download_folder . $this->ds;
				}
			}
			else
			{
				if (!is_null($options))
				{
					$dld_fld = $options['dld_fld'] . $this->ds;
				}
				else
				{
					if ($this->plugin || $this->module)
					{
						$dld_fld = realpath($this->config->download_folder) . $this->ds;
					}
					else
					{
						$dld_fld = $session->get('rsfilesdownloadfolder') . $this->ds;
					}
				}
			}

			// Get files
			if (!empty($search))
			{
				$files = JFolder::files($this->folder, $this->filter, true, true, array('.htaccess'));
			}
			else
			{
				if (!is_null($options))
				{
					$files = array($options['file']);
				}
				else
				{
					if (JFactory::getApplication()->scope == 'mod_rsfiles_newest')
					{
						$files = self::getNewest();
					}
					else
					{
						$files = JFolder::files($this->folder, $this->filter, false, true, array('.htaccess'));
					}
				}
			}

			$start = $this->module ? '' : $this->params->get('start');
			$end   = $this->module ? '' : $this->params->get('end');
		}

		$opt = array('itemid' => $this->itemid);
		if (!is_null($options))
		{
			$opt['dld_fld'] = $options['dld_fld'] . $this->ds;
		}

		if ($this->plugin || $this->module)
		{
			$opt['dld_fld'] = $dld_fld;
		}

		// Parse files
		if (!empty($files))
		{
			foreach ($files as $file)
			{
				if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
				{
					$file = str_replace('/', "\\", $file);
				}

				$instance = RSFiles::getInstance($file, $opt);
				$element  = $instance->info;

				if ($this->location == 'site' || $this->location == 'tags')
				{
					// No extension found
					if ($element->extension == '') continue;

					// The file is unpublished
					if (!$element->published) continue;

					// The file has availability overdue
					if (!empty($element->publish_down) && $element->publish_down != $this->_db->getNullDate())
					{
						$publish_down = JFactory::getDate($element->publish_down)->toUnix();
						if (JFactory::getDate()->toUnix() > $publish_down) continue;
					}

					if ($layout != 'briefcase')
					{
						$file = str_replace($this->config->download_folder . $this->ds, '', $file);
						if (!rsfilesHelper::permissions('CanView', $file))
						{
							continue;
						}
					}

					if (!empty($start))
					{
						if (JFactory::getDate($element->time)->toUnix() < JFactory::getDate($start)->toUnix())
							continue;
					}

					if (!empty($end))
					{
						if (JFactory::getDate($element->time)->toUnix() > JFactory::getDate($end)->toUnix())
							continue;
					}

					if ($layout == 'search')
					{
						if (!empty($search))
						{
							// custom search tags
							$tags = null;
							$tags = $model->getFileTags(rsfilesHelper::getTags($element->id));
							if( (count($tags) > 0) && (in_array($search,$tags)) ){
								$return[] = $element;
								continue;
							}else{
								foreach($tags as $tag){
									if( strpos(mb_strtolower($tag),$search) !== FALSE ){
										$return[] = $element;
										continue;
									}
								}
							}

							if (mb_strpos(mb_strtolower($element->name), $search) === false && mb_strpos(mb_strtolower($element->filename), $search) === false && mb_strpos(mb_strtolower($element->filedescription), $search) === false) continue;
						}
					}
				}

				// Get File Status Information and add it:
				$db    = $this->_db;
				$query = $db->getQuery(true);
				$query
					->select('*')
					->from($db->qn('#__rsfiles_file_status'))
					->where($db->qn('FileId') . ' = ' . $db->q($element->id));
				$db->setQuery($query);
				$FileStatusResult = $db->loadObject();
				if ($FileStatusResult)
				{
					$element->FileRelatedToStatus = $FileStatusResult->FileRelatedToStatus;
                    if($FileStatusResult->DateRelatedToStatus === '0000-00-00 00:00:00')
                        $element->DateRelatedToStatus = '';
                    else
                        $element->DateRelatedToStatus = date("m/Y", strtotime($FileStatusResult->DateRelatedToStatus));
					switch ($FileStatusResult->FileStatus)
					{
						case 0:
							$element->FileStatus = 'Expired';
							break;
						case 1:
							$element->FileStatus = 'Renewed';
							break;
						case 2:
							$element->FileStatus = 'Revoked';
							break;
						case 3:
							$element->FileStatus = 'Catch - All';
							break;
						case 4:
							$element->FileStatus = 'Date Only';
							break;
						case 5:
							$element->FileStatus = 'Removed';
							break;

					}
				}
				else
				{
					$element->FileRelatedToStatus = null;
					$element->FileStatus          = null;
					$element->DateRelatedToStatus = '1111-11-11 00:00:00';
				}

				// Get File Extra Information and add it:
				$db    = $this->_db;
				$query = $db->getQuery(true);
				$query
					->select('*')
					->from($db->qn('#__rsfiles_extra_info'))
					->where($db->qn('IdFile') . ' = ' . $db->q($element->id));
				$db->setQuery($query);
				$element->FileDisplayAsNew = ($ExtraInfo = $db->loadObject()) ? $ExtraInfo->FileDisplayAsNew : '1';




				$return[] = $element;
			}
		}

		// Sort files
		//check if mod_rsfiles_newest is being loaded
		if (JFactory::getApplication()->getUserState('mod_rsfiles_newest'))
		{
			$order = 'date';
		}
		else
		{
			$order = !is_null($this->order) ? $this->order : $this->params->get('order', 'name');
		}
		$order        = !is_null($this->order) ? $this->order : $this->params->get('order', 'name');
		$direction    = !is_null($this->orderdir) ? strtoupper($this->orderdir) : strtoupper($this->params->get('order_way', 'desc'));
		$theorder     = $layout == 'search' ? $input->getString('rsfl_ordering', 'name') : $order;
		$thedirection = $layout == 'search' ? $input->getString('rsfl_ordering_direction', 'ASC') : $direction;
		$thedirection = strtoupper($thedirection);

		switch ($theorder)
		{
			default:
			case 'name':
				$return = rsfilesHelper::sort_array_name($return, $thedirection);
				break;

			case 'date':
				if ($thedirection == 'ASC')
					usort($return, array('rsfilesHelper', 'sort_time_asc'));
				if ($thedirection == 'DESC')
					usort($return, array('rsfilesHelper', 'sort_time_desc'));
				break;

			case 'hits':
				if ($thedirection == 'ASC')
					usort($return, array('rsfilesHelper', 'sort_hits_asc'));
				if ($thedirection == 'DESC')
					usort($return, array('rsfilesHelper', 'sort_hits_desc'));
				break;
		}

		return $return;
	}

	/*
	*	Get the available folders from the main folder
	*/
	public function getFolders()
	{
		$query   = $this->_db->getQuery(true);
		$input   = JFactory::getApplication()->input;
		$layout  = $input->getCmd('layout', '');
		$search  = mb_strtolower($input->getString('filter_search', ''));
		$search  = $this->search ? $search : false;
		$from    = $input->get('from', '');
		$return  = array();
		$folders = array();

		if ($this->location == 'admin')
		{
			$root      = rsfilesHelper::getRoot();
			$briefcase = $root == 'briefcase';
			$dld_fld   = $this->config->{$root . '_folder'} . $this->ds;

			if (!empty($search))
			{
				if ($folders_ftp = JFolder::folders($this->config->{$root . '_folder'}, '.', true, true, array('.htaccess')))
				{
					foreach ($folders_ftp as $folder)
					{
						if (mb_strpos(mb_strtolower(rsfilesHelper::getName($folder)), $search) !== false)
						{
							if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
							{
								$folder = str_replace('/', "\\", $folder);
							}
							$folders[] = $folder;
						}
					}
				}

				$query->clear()
					->select($this->_db->qn('FilePath'))
					->from($this->_db->qn('#__rsfiles_files'))
					->where($this->_db->qn('briefcase') . ' = ' . $this->_db->q((int) $briefcase))
					->where('(LOWER(' . $this->_db->qn('FileName') . ') LIKE ' . $this->_db->q('%' . $search . '%') . ' OR LOWER(' . $this->_db->qn('FileDescription') . ') LIKE ' . $this->_db->q('%' . $search . '%') . ')');

				$this->_db->setQuery($query);
				if ($search_folders = $this->_db->loadColumn())
				{
					foreach ($search_folders as $search_folder)
					{
						if (is_dir($dld_fld . $search_folder))
						{
							$folders[] = $dld_fld . $search_folder;
						}
					}
				}

				$folders = array_unique($folders);
			}
			else
			{
				$folders = JFolder::folders($this->folder, '.', false, true, array('.htaccess'));
			}
		}
		else
		{
			$session = JFactory::getSession();
			if ($from == 'briefcase' || $layout == 'briefcase')
			{
				$dld_fld = $this->config->briefcase_folder . $this->ds;

				if (!rsfilesHelper::briefcase('CanMaintainBriefcase'))
				{
					$dld_fld .= JFactory::getUser()->get('id') . $this->ds;
				}

				if ($this->module)
				{
					$dld_fld = $this->config->download_folder . $this->ds;
				}
			}
			else
			{
				if ($this->plugin || $this->module)
				{
					$dld_fld = realpath($this->config->download_folder) . $this->ds;
				}
				else
				{
					$dld_fld = $session->get('rsfilesdownloadfolder') . $this->ds;
				}
			}

			if (!empty($search))
			{
				$folders = JFolder::folders($this->folder, '.', true, true, array('.htaccess'));
			}
			else
			{
				$folders = JFolder::folders($this->folder, '.', false, true, array('.htaccess'));
			}

			$start = $this->module ? '' : $this->params->get('start');
			$end   = $this->module ? '' : $this->params->get('end');
		}

		$opt = array('itemid' => $this->itemid);
		if ($this->plugin || $this->module)
		{
			$opt['dld_fld'] = $dld_fld;
		}

		if (!empty($folders))
		{
			foreach ($folders as $folder)
			{
				if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
				{
					$folder = str_replace('/', "\\", $folder);
				}

				$instance = RSFiles::getInstance($folder, $opt);
				$element  = $instance->info;

				if ($this->location == 'site')
				{
					if (!$element->published) continue;

					if ($layout != 'briefcase')
					{
						$folder = str_replace($this->config->download_folder . $this->ds, '', $folder);
						if (!rsfilesHelper::permissions('CanView', $folder))
						{
							continue;
						}
					}

					if (!empty($start))
					{
						if (JFactory::getDate($element->time)->toUnix() < JFactory::getDate($start)->toUnix())
							continue;
					}

					if (!empty($end))
					{
						if (JFactory::getDate($element->time)->toUnix() > JFactory::getDate($end)->toUnix())
							continue;
					}

					if ($layout == 'search')
					{
						if (!empty($search))
						{
							if (mb_strpos(mb_strtolower($element->name), $search) === false && mb_strpos(mb_strtolower($element->filename), $search) === false && mb_strpos(mb_strtolower($element->filedescription), $search) === false) continue;
						}
					}
				}

				$return[] = $element;
			}
		}

		$order        = !is_null($this->order) ? $this->order : $this->params->get('order', 'name');
		$direction    = !is_null($this->orderdir) ? $this->orderdir : strtoupper($this->params->get('order_way', 'desc'));
		$theorder     = $layout == 'search' ? $input->getString('rsfl_ordering', 'name') : $order;
		$thedirection = $layout == 'search' ? $input->getString('rsfl_ordering_direction', 'ASC') : $direction;
		$thedirection = strtoupper($thedirection);

		switch ($theorder)
		{
			default:
			case 'name':
				$return = rsfilesHelper::sort_array_name($return, $thedirection);
				break;

			case 'date':
				if ($thedirection == 'ASC')
					usort($return, array('rsfilesHelper', 'sort_time_asc'));
				else
					usort($return, array('rsfilesHelper', 'sort_time_desc'));
				break;

			case 'hits':
				if ($thedirection == 'ASC')
					usort($return, array('rsfilesHelper', 'sort_hits_asc'));
				else
					usort($return, array('rsfilesHelper', 'sort_hits_desc'));
				break;
		}

		return $return;
	}

	/*
	*	Get the external files
	*/
	public function getExternal($options = null)
	{
		$query     = $this->_db->getQuery(true);
		$input     = JFactory::getApplication()->input;
		$layout    = $input->getCmd('layout', '');
		$search    = mb_strtolower($input->getString('filter_search', ''));
		$search    = $this->search ? $search : false;
		$isPopular = (int) $this->config->popular;
		$isNew     = (int) $this->config->new;
		$days      = $isNew - 2 * $isNew;
		$return    = array();
		$root      = rsfilesHelper::root(true);

		if ($this->location == 'site' && $layout == 'search' || $this->location == 'tags')
		{
			$query->clear()
				->select('DISTINCT f.*')
				->from($this->_db->qn('#__rsfiles_files', 'f'))
				->where($this->_db->qn('f.FileType') . ' = 1');

			if ($this->location == 'tags')
			{
				$tags = isset($options['tags']) ? $options['tags'] : $this->params->get('tags');

				if ($tags)
				{
					$tags = array_map('intval', $tags);

					$query->join('LEFT', $this->_db->qn('#__rsfiles_tag_relation', 'tr') . ' ON ' . $this->_db->qn('f.IdFile') . ' = ' . $this->_db->qn('tr.file'));
					$query->join('LEFT', $this->_db->qn('#__rsfiles_tags', 't') . ' ON ' . $this->_db->qn('t.id') . ' = ' . $this->_db->qn('tr.tag'));
					$query->where($this->_db->qn('t.published') . ' = 1');
					$query->where($this->_db->qn('f.briefcase') . ' = 0');
					$query->where($this->_db->qn('tr.tag') . ' IN (' . implode(',', $tags) . ')');
				}
			}
		}
		else
		{
			if (!is_null($options))
			{
				$query->clear()
					->select('DISTINCT f.*')
					->from($this->_db->qn('#__rsfiles_files', 'f'))
					->where($this->_db->qn('f.IdFile') . ' = ' . (int) $options['file']);
			}
			else
			{
				if ($this->folder == $this->config->download_folder)
				{
					$folder = 'root';
				}
				else
				{
					$folder = str_replace($this->config->download_folder, '', $this->folder);
					$folder = trim($folder, $this->ds);
				}

				if (!empty($search))
				{
					$query->clear()
						->select('DISTINCT f.*')
						->from($this->_db->qn('#__rsfiles_files', 'f'))
						->where($this->_db->qn('f.FileType') . ' = 1');
				}
				else
				{
					$query->clear()
						->select('DISTINCT f.*')
						->from($this->_db->qn('#__rsfiles_files', 'f'))
						->where($this->_db->qn('f.FileType') . ' = 1')
						->where($this->_db->qn('f.FileParent') . ' = ' . $this->_db->q($folder));
				}
			}
		}

		$this->_db->setQuery($query);
		$externals = $this->_db->loadObjectList();

		if (!empty($externals))
		{
			if ($this->location == 'site')
			{
				$start = $this->module ? '' : $this->params->get('start');
				$end   = $this->module ? '' : $this->params->get('end');
			}

			foreach ($externals as $external)
			{
				if ($this->location == 'admin')
				{
					if (!empty($search))
					{
						if (mb_strpos(mb_strtolower(rsfilesHelper::getName($external->FileName)), $search) === false && mb_strpos(mb_strtolower(rsfilesHelper::getName($external->FilePath)), $search) === false)
							continue;
					}
				}

				$instance = RSFiles::getInstance($external->IdFile, array('itemid' => $this->itemid));
				$element  = $instance->info;

				if ($this->location == 'site')
				{
					if (!$element->published) continue;

					if (!empty($external->publish_down) && $external->publish_down != $this->_db->getNullDate())
					{
						$publish_down = JFactory::getDate($external->publish_down)->toUnix();
						if (JFactory::getDate()->toUnix() > $publish_down) continue;
					}

					if (!rsfilesHelper::permissions('CanView', $external->IdFile)) continue;

					if (!empty($start))
					{
						if (JFactory::getDate($element->time)->toUnix() < JFactory::getDate($start)->toUnix())
							continue;
					}

					if (!empty($end))
					{
						if (JFactory::getDate($element->time)->toUnix() > JFactory::getDate($end)->toUnix())
							continue;
					}

					if ($layout == 'search')
					{
						if (!empty($search))
						{
							if (mb_strpos(mb_strtolower($element->name), $search) === false && mb_strpos(mb_strtolower($element->filename), $search) === false && mb_strpos(mb_strtolower($element->filedescription), $search) === false) continue;
						}
					}
				}

				$return[] = $element;
			}

			$order        = !is_null($this->order) ? $this->order : $this->params->get('order', 'name');
			$direction    = !is_null($this->orderdir) ? $this->orderdir : strtoupper($this->params->get('order_way', 'desc'));
			$theorder     = $layout == 'search' ? $input->getString('rsfl_ordering', 'name') : $order;
			$thedirection = $layout == 'search' ? $input->getString('rsfl_ordering_direction', 'ASC') : $direction;
			$thedirection = strtoupper($thedirection);

			switch ($theorder)
			{
				default:
				case 'name':
					$return = rsfilesHelper::sort_array_name($return, $thedirection);
					break;

				case 'date':
					if ($thedirection == 'ASC')
						usort($return, array('rsfilesHelper', 'sort_time_asc'));
					if ($thedirection == 'DESC')
						usort($return, array('rsfilesHelper', 'sort_time_desc'));
					break;

				case 'hits':
					if ($thedirection == 'ASC')
						usort($return, array('rsfilesHelper', 'sort_hits_asc'));
					if ($thedirection == 'DESC')
						usort($return, array('rsfilesHelper', 'sort_hits_desc'));
					break;
			}
		}

		return $return;
	}

	/*
	*	Get the navigation
	*/
	public static function getPathNavigation($path)
	{
		$navigation = '';
		$config     = self::getConfig();
		$ds         = rsfilesHelper::ds();
		$layout     = JFactory::getApplication()->input->get('layout', '');
		$modal      = $layout == 'modal' ? '&layout=modal&tmpl=component' : '';
		$from       = JFactory::getApplication()->input->get('from', '') == 'editor' ? '&from=editor' : '';
		$editor     = JFactory::getApplication()->input->getString('editor', '') ? '&editor=' . JFactory::getApplication()->input->getString('editor', '') : '';

		if (!empty($config->briefcase_folder) && $config->briefcase_folder == substr($path, 0, strlen($config->briefcase_folder)))
		{
			$root       = 'briefcase_folder';
			$navigation = '<a href="' . JRoute::_('index.php?option=com_rsfiles&view=files' . $from . $editor . $modal) . '">' . str_replace($ds, ' ' . $ds . ' ', $config->briefcase_folder) . '</a> ' . $ds;
			$path       = str_replace($config->briefcase_folder, '', $path);
		}
		else
		{
			$root       = 'download_folder';
			$navigation = '<a href="' . JRoute::_('index.php?option=com_rsfiles&view=files' . $from . $editor . $modal) . '">' . str_replace($ds, ' ' . $ds . ' ', $config->download_folder) . '</a> ' . $ds;
			$path       = str_replace($config->download_folder, '', $path);
		}

		if (substr($path, 0, 1) == $ds)
		{
			$path = ltrim($path, $ds);
		}

		if (!empty($path))
		{
			$folders     = explode($ds, $path);
			$total       = count($folders);
			$folder_path = '';

			$i = 0;
			foreach ($folders as $folder)
			{
				if ($i != 0 && $i != $total)
					$folder_path .= $ds;

				$folder_path .= $folder;

				$navigation .= ' <a href="' . JRoute::_('index.php?option=com_rsfiles&view=files' . $from . $editor . $modal . '&folder=' . urlencode($folder_path)) . '">' . $folder . '</a> ' . $ds;
				$i++;
			}
		}

		return $navigation;
	}

	/*
	*	Set files filter
	*/
	public function setFilter($filter = '.')
	{
		$this->filter = $filter;
	}

	/*
	*	Get the configuration
	*/
	protected static function getConfig()
	{
		return rsfilesHelper::getConfig();
	}

	/*
	*	Get the page params
	*/
	protected function getParams()
	{
		$params = new JRegistry;

		if ($this->location != 'admin')
		{
			if ($this->plugin)
			{
				jimport('joomla.plugin.helper');
				if ($plugin = JPluginHelper::getPlugin('system', 'rsfiles'))
				{
					$params->loadString($plugin->params);
				}
			}
			else
			{
				$app    = JFactory::getApplication('site');
				$params = $app->getParams();
			}
		}

		return $params;
	}

	public static function getNewest(): array
	{
		require_once JPATH_SITE . '/components/com_rsfiles/helpers/rsfiles.php';

		$db      = JFactory::getDbo();
		$query   = $db->getQuery(true);
		$dld_fld = rsfilesHelper::getConfig('download_folder');
		$limit   = JFactory::getApplication()->getUserState('number_of_results', 5);
		$now     = JFactory::getDate()->toSql();
		$return  = array();

		$query->clear()
			->select($db->qn('FilePath'))
			->from($db->qn('#__rsfiles_files'))
			->where($db->qn('published') . ' = ' . $db->q(1))
			->where($db->qn('FileType') . ' = ' . $db->q(0))
			->where($db->qn('briefcase') . ' = ' . $db->q(0))
			->where('(' . $db->qn('publish_down') . ' = ' . $db->q($db->getNullDate()) . ' OR ' . $db->qn('publish_down') . ' > ' . $db->q($now) . ')')
			->order($db->qn('DateAdded') . ' DESC');
		$query->setLimit($limit);
		$db->setQuery($query);
		if ($files = $db->loadColumn())
		{
			foreach ($files as $file)
			{
				$return[] = $dld_fld . rsfilesHelper::ds() . $file;
			}
		}

		return $return;
	}
}
