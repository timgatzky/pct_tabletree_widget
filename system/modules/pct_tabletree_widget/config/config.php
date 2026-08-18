<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2013, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_tabletree_widget
 * @link		http://contao.org
 */

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\System;

if( version_compare(ContaoCoreBundle::getVersion(),'5.0','>=') && version_compare(ContaoCoreBundle::getVersion(),'5.8','<=') )
{
	$extensionDir = '/system/modules/pct_tabletree_widget';
	$rootDir = System::getContainer()->getParameter('kernel.project_dir');
	include( $rootDir.$extensionDir.'/contao/config/config.php' );

	// Create symlinks for the contao 5 folder structure
	$folders = scandir($rootDir.$extensionDir.'/contao/');
	foreach($folders as $folder)
	{
		$from = $rootDir.$extensionDir.'/contao/'.$folder;
		$to = $rootDir.$extensionDir.'/'.$folder;
			
		if( !in_array($folder, array('.','..','config','templates')) )
		{
			$from = $rootDir.$extensionDir.'/contao/'.$folder;
			$to = $rootDir.$extensionDir.'/'.$folder;
			
			if ( !is_link($to) )
			{
				symlink($from, $to);
			}
		}
		else
		{
			// remove symlink
			if ( is_link($to) )
			{
				unlink($to);
			}
		}
	}

	/**
	 * Register the templates
	 */
	\Contao\TemplateLoader::addFiles(array
	(
		'be_pct_tabletree' 					=> 'system/modules/pct_tabletree_widget/contao/templates',
	));
}