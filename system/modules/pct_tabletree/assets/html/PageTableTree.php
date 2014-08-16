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
 * Namespace
 */
namespace PCT\Widgets\TableTree;

/**
 * Initialize the system
 */
define('TL_MODE', 'BE');
require_once str_replace(substr($_SERVER['SCRIPT_FILENAME'], strpos($_SERVER['SCRIPT_FILENAME'],'system/modules')),'',$_SERVER['SCRIPT_FILENAME']).'system/initialize.php';

/**
 * Class file
 * PageTableTree
 */
class PageTableTree extends \Backend
{
	/**
	 * Current Ajax object
	 * @var object
	 */
	protected $objAjax;

	/**
	 * Initialize the controller
	 *
	 * 1. Import the user
	 * 2. Call the parent constructor
	 * 3. Authenticate the user
	 * 4. Load the language files
	 * DO NOT CHANGE THIS ORDER!
	 */
	public function __construct()
	{
		$this->import('BackendUser', 'User');
		parent::__construct();

		$this->User->authenticate();
		\System::loadLanguageFile('default');
	}


	/**
	 * Run the controller and parse the template
	 */
	public function run()
	{
		$this->Template = new \BackendTemplate('be_pct_tabletree');
		$this->Template->main = '';

		// Ajax request
		if ($_POST && \Environment::get('isAjaxRequest'))
		{
			$this->objAjax = new \Ajax(\Input::post('action'));
			$this->objAjax->executePreActions();
		}

		$strTable = \Input::get('table');
		$strField = \Input::get('field');
		$strSource = \Input::get('source');
		$strValueField = \Input::get('valueField') ? \Input::get('valueField') : 'title';
		
		// Define the current ID
		define('CURRENT_ID', (\Input::get('table') ? \Session::getInstance()->get('CURRENT_ID') : \Input::get('id')));
		
		$this->loadDataContainer($strTable);
		$strDriver = 'DC_' . $GLOBALS['TL_DCA'][$strTable]['config']['dataContainer'];
		$objDca = new $strDriver($strTable);
		
		$objDca->valueField = $strValueField;
		$objDca->keyField = $strKeyField;
		
		// AJAX request
		if ($_POST && \Environment::get('isAjaxRequest'))
		{
		   $this->objAjax->executePostActions($objDca);
		}
		
		\Session::getInstance()->set('pctTableTreeRef', \Environment::get('request'));

		// Prepare the widget
		$objTableTree = new \PCT\Widgets\TableTree(array(
			'strId'    => $strField,
			'strTable' => $strTable,
			'tabletree'=> array
			(
				'source'		=> $strSource,
				'valueField'	=> $strValueField,
				'keyField'		=> \Input::get('keyField') ? \Input::get('keyField') : '',
			),
			'strField' => $strField,
			'strName'  => $strField,
			'varValue' => array_filter(explode(',', \Input::get('value')))
		), $objDca);
		
		$this->Template->main = $objTableTree->generate();
		$this->Template->theme = \Backend::getTheme();
		$this->Template->base = \Environment::get('base');
		$this->Template->language = $GLOBALS['TL_LANGUAGE'];
		$this->Template->title = specialchars($GLOBALS['TL_LANG']['MSC']['pct_tableTreeTitle']);
		$this->Template->charset = $GLOBALS['TL_CONFIG']['characterSet'];
		$this->Template->addSearch = true;
		$this->Template->search = $GLOBALS['TL_LANG']['MSC']['search'];
		$this->Template->action = ampersand(\Environment::get('request'));
		#$this->Template->manager = $GLOBALS['TL_LANG']['MSC']['pct_tableTreeManager'];
		#$this->Template->managerHref = 'contao/main.php?do=pct_customelements_tags&amp;popup=1';
		$this->Template->breadcrumb = $GLOBALS['TL_DCA'][$strSource]['list']['sorting']['breadcrumb'];

		$this->Template->value = $this->Session->get('pct_tabletree_selector_search');
		
		$GLOBALS['TL_CONFIG']['debugMode'] = false;
		
		$this->Template->output();
	}
}


/**
 * Instantiate the controller
 */
$objPageTableTree = new \PCT\Widgets\TableTree\PageTableTree();
$objPageTableTree->run();
