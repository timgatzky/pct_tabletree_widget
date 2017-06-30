<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2013 Leo Feyer
 *
 * @package Core
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Initialize the system
 */
define('TL_MODE', 'BE');
define('TL_SCRIPT', 'PCT_TABLETREE_WIDGET');

// contao 3 structure
if( file_exists( realpath($_SERVER['DOCUMENT_ROOT']) . '/system/initialize.php') )
{
	require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/system/initialize.php';
}
// contao 4 structure runs in a relative subfolder
else if( file_exists( realpath($_SERVER['DOCUMENT_ROOT'].'/../') . '/system/initialize.php') )
{
	require_once realpath($_SERVER['DOCUMENT_ROOT'].'/../') . '/system/initialize.php';
}
else
{
	throw new \Exception('Contaos initialize.php not found in: '.$path_to_initialize);
}

/**
 * Instantiate the controller
 */
$objPageTableTree = new BackendPctTableTree;
$objPageTableTree->run();
