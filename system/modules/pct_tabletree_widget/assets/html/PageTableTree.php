<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2014, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_tabltree
 * @link		http://contao.org
 */

/**
 * Initialize the system
 */
define('TL_MODE', 'BE');

// Apache server
if(strlen(strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'apache')) > 0)
{
	$path_to_initialize = str_replace(substr($_SERVER['SCRIPT_FILENAME'], strpos($_SERVER['SCRIPT_FILENAME'],'system/modules')),'',$_SERVER['SCRIPT_FILENAME']).'system/initialize.php';
}
// IIS or anything else
else
{
	$path_to_initialize = str_replace('modules\pct_tabletree_widget\assets\html\PageTableTree.php', 'initialize.php', $_SERVER['SCRIPT_FILENAME']);	
}

if(!file_exists($path_to_initialize))
{
	throw new \Exception('Contaos initialize.php not found in: '.$path_to_initialize);
}

require_once $path_to_initialize;

/**
 * Instantiate the controller
 */
$objPageTableTree = new BackendPctTableTree;
$objPageTableTree->run();
