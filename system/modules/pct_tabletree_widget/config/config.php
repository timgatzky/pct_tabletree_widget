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

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\System;

/**
 * Constants
 */
define('PCT_TABLETREE_PATH', 'system/modules/pct_tabletree_widget');
define('PCT_TABLETREE_VERSION', '1.8.0');

if( version_compare(ContaoCoreBundle::getVersion(),'5.0','>=') )
{
	$rootDir = System::getContainer()->getParameter('kernel.project_dir');
	include( $rootDir.'/system/modules/pct_tabletree_widget/config/autoload.php' );
}

/**
 * Back end form fields
 */
$GLOBALS['BE_FFL']['pct_tabletree'] = 'PCT\Widgets\WidgetTableTree';
// Backend Controller
$GLOBALS['BE_MOD']['content']['pct_customelements_tags']['tabletree'] = array('PCT\Widgets\TableTree\Backend\PageTableTree','run'); 

#$GLOBALS['BE_MOD']['tabletree']['tabletree'] = array('PCT\Widgets\TableTree\Backend\PageTableTree','run'); 

#http://dev50.tim-gatzky.de/contao?do=page&id=2&key=iconpicker&table=tl_page&field=fontIcon&value=
/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['executePostActions'][] = array('PCT\Widgets\TableTree\ContaoCallbacks','postActions');
$GLOBALS['TL_HOOKS']['executePreActions'][] = array('PCT\Widgets\TableTree\ContaoCallbacks','preActions');
$GLOBALS['TL_HOOKS']['parseTemplate'][]  = array('PCT\Widgets\TableTree\ContaoCallbacks', 'parseTemplateCallback');