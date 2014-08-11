<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2014, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_customelements
 * @subpackage	AttributeTags
 * @link		http://contao.org
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'PCT\Widgets',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'PCT\Widgets\TableTree'										=> 'system/modules/pct_customelements_plugin_tags/PCT/Widgets/TableTree/TableTree.php',	
	'PCT\Widgets\WidgetTableTree'								=> 'system/modules/pct_customelements_plugin_tags/PCT/Widgets/TableTree/WidgetTableTree.php',	
	'PCT\Widgets\TableTree\PageTableTree'						=> 'system/modules/pct_customelements_plugin_tags/PCT/Widgets/TableTree/assets/html/PageTableTree.php',	
	'PCT\Widgets\TableTree\TableTreeHelper'						=> 'system/modules/pct_customelements_plugin_tags/PCT/Widgets/TableTree/TableTreeHelper.php',	
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	// widgets
	'be_pct_tabletree'     => 'system/modules/pct_customelements_plugin_tags/PCT/Widgets/TableTree/templates',
));
