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
namespace PCT\Widgets\TableTree\Backend;

use Contao\BackendUser;
use Contao\System;
use Contao\Backend;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Contao\Config;
use Contao\Database;
use Contao\StringUtil;
use Contao\BackendTemplate;

/**
 * Class file
 * PageTableTree
 */
class PageTableTree extends Backend
{
	/**
	 * Current Ajax object
	 * @var object
	 */
	protected $objAjax;
	
	protected $Template = null;

	public function __construct()
	{
		parent::__construct();
		$this->import(BackendUser::class, 'User');	
		System::loadLanguageFile('default');
	}


	/**
	 * Run the controller and parse the template
	 */
	public function run()
	{
		$objDatabase = Database::getInstance();
		$objSession = System::getContainer()->get('request_stack')->getSession();
		
		$this->Template = new BackendTemplate('be_pct_tabletree');
		$this->Template->main = '';

		// Ajax request
		if ($_POST && Environment::get('isAjaxRequest'))
		{
			$this->objAjax = new \Contao\Ajax(\Contao\Input::post('action'));
			$this->objAjax->executePreActions();
		}

		$strTable = Input::get('table');
		$strField = Input::get('field');
		$strName = Input::get('name');
		$strSource = Input::get('source');
		$strValueField = Input::get('valueField') ?: 'id';
		$strKeyField = Input::get('keyField') ?: 'id';
		$strOrderField = Input::get('orderField') ?: 'id';
		$strConditionsField = Input::get('conditionsField') ?: '';
		$strRootsField = Input::get('rootsField');
		$strTranslationField = Input::get('translationField');
		
		// Define the current ID
		#define('CURRENT_ID', (\Contao\Input::get('table') ? $objSession->get('CURRENT_ID') : \Contao\Input::get('id')));
		
		$this->loadDataContainer($strSource);
		
		$strDriver = DataContainer::getDriverForTable($strSource);
		$objDC = new $strDriver($strSource);
		$objDC->valueField = $strValueField;
		$objDC->keyField = $strKeyField;
		$objDC->orderField = $strOrderField;
		$objDC->translationField = $strTranslationField;
		$objDC->rootsField = $strRootsField;
		$objDC->conditionsField = $strConditionsField;
		$objDC->conditions = \Contao\Input::get( $strConditionsField );
		$objDC->field = $strField;
		$objDC->table = $strTable;
		$objDC->source = $strSource;
		
		// AJAX request
		if ($_POST && Environment::get('isAjaxRequest') && $objDC !== null)
		{
		   $this->objAjax->executePostActions($objDC);
		}
		
		$objSession->set('pctTableTreeRef', Environment::get('request'));
		
		if( !isset($GLOBALS['TL_DCA'][$strSource]) || !is_array($GLOBALS['TL_DCA'][$strSource]))
		{
			$this->loadDataContainer($strSource);
		}
		
		
		// Build the attributes based on the "eval" array
		$arrAttribs = $GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['eval'] ?? array();
		$arrAttribs['id'] = $objDC->field;
		$arrAttribs['name'] = $objDC->field;
		$arrAttribs['value'] = array_filter(explode(',', Input::get('value')));
		$arrAttribs['strTable'] = $objDC->table;
		$arrAttribs['strField'] = $strName;
		$arrAttribs['activeRecord'] = $objDC->activeRecord;
		$arrAttribs['tabletree']['source'] = $strSource;
		$arrAttribs['tabletree']['valueField'] = $strValueField;
		$arrAttribs['tabletree']['keyField'] = $strKeyField;
		$arrAttribs['tabletree']['orderField'] = $strOrderField;
		$arrAttribs['tabletree']['rootsField'] = $strRootsField;
		$arrAttribs['tabletree']['translationField'] = $strTranslationField;
		$arrAttribs['tabletree']['conditionsField'] = $strConditionsField;
		
		// get root nodes from session
		$roots = $objSession->get('pct_tabletree_roots');
		if( isset($roots[$strField]) && is_array($roots[$strField]))
		{
			$arrAttribs['tabletree']['roots'] = array_filter($roots[$strField]);
		}


		// get the conditions from the session
		$conditions = $objSession->get('pct_tabletree_conditions');
		if( isset($conditions[$strField]) && !empty($conditions[$strField]) )
		{
			$arrAttribs['tabletree']['conditions'] = $conditions[$strField];
		}
		
		$objWidget = new \PCT\Widgets\TableTree($arrAttribs,$objDC);
		$this->Template->main = $objWidget->generate();
		$this->Template->theme = Backend::getTheme();
		$this->Template->base = Environment::get('base');
		$this->Template->language = $GLOBALS['TL_LANGUAGE'];
		$this->Template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pct_tableTreeTitle']);
		$this->Template->charset = Config::get('characterSet');
		$this->Template->addSearch = true;
		$this->Template->search = $GLOBALS['TL_LANG']['MSC']['search'];
		$this->Template->action = StringUtil::ampersand( Environment::get('request') );
		#$this->Template->manager = $GLOBALS['TL_LANG']['MSC']['pct_tableTreeManager'];
		#$this->Template->managerHref = 'contao/main.php?do=pct_customelements_tags&amp;popup=1';
		$this->Template->breadcrumb = $GLOBALS['TL_DCA'][$strSource]['list']['sorting']['breadcrumb'] ?? '';
		$this->Template->request_token = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
		$this->Template->value = $objSession->get('pct_tabletree_selector_search');
		$this->Template->isPopup = true;
		
		// add customs panels
		$arrPanels = array();
		if (isset($GLOBALS['PCT_TABLETREE_HOOKS']['getCustomPanel']) && !empty($GLOBALS['PCT_TABLETREE_HOOKS']['getCustomPanel']))
		{
			foreach($GLOBALS['PCT_TABLETREE_HOOKS']['getCustomPanel'] as $callback)
			{
				$arrPanels[] = System::importStatic($callback[0])->{$callback[1]}($objDC,$this);
			}
		}
		
		if(count($arrPanels) > 0)
		{
			$this->Template->panels = $arrPanels;
		}
		
		return $this->Template->parse();
	}
}
