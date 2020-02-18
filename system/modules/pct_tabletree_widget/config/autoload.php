<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2014, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_tabletree
 * @link		http://contao.org
 * @license     LGPL
 */

$path = 'system/modules/pct_tabletree_widget';

/**
 * Register the namespaces
 */
\Contao\ClassLoader::addNamespaces(array
(
	'PCT',
));


/**
 * Register the classes
 */
\Contao\ClassLoader::addClasses(array
(
	'PCT\Widgets\TableTree'										=> $path.'/PCT/Widgets/TableTree/TableTree.php',	
	'PCT\Widgets\WidgetTableTree'								=> $path.'/PCT/Widgets/TableTree/WidgetTableTree.php',	
	'PCT\Widgets\TableTree\TableTreeHelper'						=> $path.'/PCT/Widgets/TableTree/TableTreeHelper.php',
	'Contao\BackendPctTableTree'								=> $path.'/Contao/BackendPctTableTree.php',
));


/**
 * Register the templates
 */
\Contao\TemplateLoader::addFiles(array
(
	// widgets
	'be_pct_tabletree'     => 	$path.'/templates',
));
