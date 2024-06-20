<?php

namespace TCM\View\Viewed;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

class JsonapiView extends BaseApiView
{
	protected $fieldsToRenderItem = [
		'id',
		'catid',
		'title',
		'alias',
		'url',
		'xreference',
		'tags',
	];

	protected $fieldsToRenderList = [
		'id',
		'title',
		'alias',
	];
}
