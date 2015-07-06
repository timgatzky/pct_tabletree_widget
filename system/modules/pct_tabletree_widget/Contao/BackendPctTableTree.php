<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2015, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_tabltree
 * @link		http://contao.org
 */

/**
 * Namespace
 */
namespace Contao;

/**
 * Class file
 * BackendPctTableTree
 */
class BackendPctTableTree extends Backend
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
		$objDatabase = \Database::getInstance();
		
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
		$strValueField = \Input::get('valueField') ?: 'id';
		$strKeyField = \Input::get('keyField') ?: 'id';
		$strOrderField = \Input::get('orderField') ?: 'id';
		if($objDatabase->fieldExists('sorting',$strTable))
		{
			$strOrderField = 'sorting';
		}
		$strRootsField = \Input::get('rootsField') ?: 'rootNodes';
		$strTranslationField = \Input::get('translationField');
		
		// Define the current ID
		define('CURRENT_ID', (\Input::get('table') ? \Session::getInstance()->get('CURRENT_ID') : \Input::get('id')));
		
		$this->loadDataContainer($strSource);
		$this->loadDataContainer($strTable);
		
		$strDriver = 'DC_' . ($GLOBALS['TL_DCA'][$strSource]['config']['dataContainer'] ? $GLOBALS['TL_DCA'][$strSource]['config']['dataContainer'] : 'Table');
		$objDC = new $strDriver($strSource);
		$objDC->valueField = $strValueField;
		$objDC->keyField = $strKeyField;
		$objDC->orderField = $strOrderField;
		$objDC->translationField = $strTranslationField;
		$objDC->field = $strField;
		$objDC->table = $strTable;
		
		// AJAX request
		if ($_POST && \Environment::get('isAjaxRequest'))
		{
		   $this->objAjax->executePostActions($objDC);
		}
		
		\Session::getInstance()->set('pctTableTreeRef', \Environment::get('request'));
		
		// Build the attributes based on the "eval" array
		$arrAttribs = $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval'];
		$arrAttribs['id'] = $objDC->field;
		$arrAttribs['name'] = $objDC->field;
		$arrAttribs['value'] = array_filter(explode(',', \Input::get('value')));
		$arrAttribs['strTable'] = $objDC->table;
		$arrAttribs['strField'] = $strField;
		$arrAttribs['activeRecord'] = $objDC->activeRecord;
		$arrAttribs['tabletree']['source'] = $strSource;
		$arrAttribs['tabletree']['valueField'] = $strValueField;
		$arrAttribs['tabletree']['keyField'] = $strKeyField;
		$arrAttribs['tabletree']['orderField'] = $strOrderField;
		$arrAttribs['tabletree']['rootsField'] = $strRootsField;
		$arrAttribs['tabletree']['translationField'] = $strTranslationField;
		
		// get root nodes from session
		$roots = \Session::getInstance()->get('pct_tabletree_roots');
		$arrAttribs['tabletree']['roots'] = $roots[$strField];
		$objWidget = new \PCT\Widgets\TableTree($arrAttribs,$objDC);
		
		$this->Template->main = $objWidget->generate();
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