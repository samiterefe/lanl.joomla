<?php
/**
 * @package     RSfilesReports.Administrator
 * @subpackage  com_rsfilesreports
 *
 * @copyright   Copyright (C) 2024 BK Design Solutions
 * @license     Private
 */
//namespace TCM\Component\RsfilesReports\Administrator;

defined('_JEXEC') or die;
//use Joomla Document
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$document = Factory::getApplication()->getDocument();
$document->setTitle('Rsfiles Reports');
$document->addScript(Uri::base() . 'components/com_rsfilesreports/assets/script.js',['version' => 'auto'],['type' => 'module', 'crossorigin' => true]);
$document->addScript('https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js',['version' => 'auto'],['type' => 'module', 'crossorigin' => true]);
$document->addStyleSheet('https://unpkg.com/primeicons@7.0.0/primeicons.css', ['crossorigin' => true]);
$document->addStyleSheet(Uri::base() . 'components/com_rsfilesreports/assets/style.css', ['crossorigin' => true]);
$controller = JControllerLegacy::getInstance('RsfilesReports');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
