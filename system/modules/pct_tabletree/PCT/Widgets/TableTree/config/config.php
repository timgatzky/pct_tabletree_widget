<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2014, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_customelements
 * @subpackage	pct_customelements_plugin_tags
 * @widget		TableTree
 * @link		http://contao.org
 */

/**
 * Constants
 */
define(PCT_WIDGETS_TABLETREE_PATH, 'system/modules/pct_customelements_plugin_tags');
define(PCT_WIDGETS_TABLETREE_VERSION, '1.0.0');

/**
 * Back end form fields
 */
$GLOBALS['BE_FFL']['pct_TableTree'] = 'PCT\Widgets\WidgetTableTree';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions'][] 	= array('PCT\Widgets\TableTree\TableTreeHelper','postActions');
$GLOBALS['TL_HOOKS']['executePreActions'][] 	= array('PCT\Widgets\TableTree\TableTreeHelper','preActions');