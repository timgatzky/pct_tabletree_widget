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

// path relative from composer directory
$path = \Contao\System::getContainer()->getParameter('kernel.project_dir').'/vendor/composer/../../system/modules/pct_tabletree_widget';

/**
 * Register the classes
 */
$classMap = array
(
	'PCT\Widgets\TableTree'										=> $path.'/PCT/Widgets/TableTree/TableTree.php',	
	'PCT\Widgets\WidgetTableTree'								=> $path.'/PCT/Widgets/TableTree/WidgetTableTree.php',	
	'PCT\Widgets\TableTree\TableTreeHelper'						=> $path.'/PCT/Widgets/TableTree/TableTreeHelper.php',
	'PCT\Widgets\TableTree\ContaoCallbacks'						=> $path.'/PCT/Widgets/TableTree/ContaoCallbacks.php',
	'PCT\Widgets\TableTree\Backend\PageTableTree'				=> $path.'/PCT/Widgets/TableTree/Backend/PageTableTree.php',
#	'Contao\BackendPctTableTree'								=> $path.'/Contao/BackendPctTableTree.php',
);

$loader = new \Composer\Autoload\ClassLoader();
$loader->addClassMap($classMap);
$loader->register();


/**
 * Register the templates
 */
\Contao\TemplateLoader::addFiles(array
(
	// widgets
	'be_pct_tabletree'     => 	'system/modules/pct_tabletree_widget/templates',
));
