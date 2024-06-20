<?php

namespace TCM\View\Downloaded;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

\defined('_JEXEC') or die;

class JsonapiView extends BaseApiView
{
	protected $fieldsToRenderItem = [
		'id',
		'file_id',
		'downloader_ip_address',
		'downloader_country',
		'date_downloaded'
	];

	protected $fieldsToRenderList = [
		'id',
		'file_id',
		'downloader_ip_address',
		'downloader_country',
		'date_downloaded'
	];
}
