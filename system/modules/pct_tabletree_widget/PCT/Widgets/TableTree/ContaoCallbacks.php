<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2023, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_tabletree
 * @link		http://contao.org
 * @license     LGPL
 */

/**
 * Namespace
 */
namespace PCT\Widgets\TableTree;

use Contao\Input;

/**
 * Class file
 * ContaoCallbacks
 */
class ContaoCallbacks extends \Contao\Backend
{
	protected $strAjaxKey = '';
	protected $strAjaxId = 0;
	protected $strAjaxName = '';


	/**
	 *  Add information to the backend main template
	 * @param object
	 * called from parseTemplate Hook
	 */
	public function parseTemplateCallback($objTemplate)
	{
		if($objTemplate->getName() == 'be_main' && (Input::get('key') == 'tabletree' || Input::get('do') == 'tabletree') )
		{
			$objTemplate->attributes .= ' data-page="tabletree"';
		}
	}
	
	
	/**
	 * Backend ajax requests
	 * @param string
	 */
	public function postActions($strAction, $objDC)
	{
		switch($strAction)
		{
			// Load nodes of the table tree
			case 'toggleTabletree':
			case 'loadTabletree':
				$arrData['strTable'] = $objDC->table;
				$arrData['strField'] = $objDC->field;
				$arrData['id'] = $this->strAjaxName ?: $objDC->id;
				$arrData['name'] = $objDC->field;
				$arrData['field'] = $objDC->field;
				$arrData['tabletree']['source'] = \Contao\Input::get('source');
				$arrData['tabletree']['valueField'] = \Contao\Input::get('valueField');
				$arrData['tabletree']['keyField'] = \Contao\Input::get('keyField');
				$arrData['tabletree']['orderField'] = \Contao\Input::get('orderField');
				$arrData['tabletree']['translationField'] = \Contao\Input::get('translationField');
				$arrData['tabletree']['rootsField'] = \Contao\Input::get('rootsField');
				$arrData['tabletree']['roots'] = \Contao\Input::get('roots');
				#$arrData['tabletree']['conditions'] = \Contao\Input::get('conditions');
				
				$objWidget = new \PCT\Widgets\TableTree($arrData, $objDC);
				echo $objWidget->generateAjax($this->strAjaxId, $objDC->field, $arrData['tabletree']['valueField'], $arrData['tabletree']['keyField'], intval(\Contao\Input::post('level')));
				exit;
				break;
			case 'reloadTabletree':
				$intId = \Contao\Input::post('id');
				$intDcaId = $objDC->id = \Contao\Input::get('id');
				$strField = $objDC->field = \Contao\Input::post('name');
				$strSource = $objDC->source = \Contao\Input::post('source');
				$strValueField = $objDC->valueField = \Contao\Input::post('valueField');
				$strKeyField = $objDC->keyField = \Contao\Input::post('keyField');
				$strOrderField = $objDC->orderField = \Contao\Input::post('orderField');
				$strTranslationField = $objDC->translationField = \Contao\Input::post('translationField');
				$strRootsField = $objDC->rootsField = \Contao\Input::post('rootsField');
				$strRoots = $objDC->roots = \Contao\Input::post('roots');
				#$strConditionsField = $objDC->conditionsField = \Contao\Input::post('conditionsField');
				#$strConditions = $objDC->conditions = \Contao\Input::post('conditions');
				
				$objDatabase = \Contao\Database::getInstance();
				
				// Handle the keys in "edit multiple" mode
				if (\Contao\Input::get('act') == 'editAll')
				{
					$intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
					$strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
				}
				
				$this->loadDataContainer($objDC->table);
				
				// The field does not exist
				if (!isset($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]))
				{
					\Contao\System::getContainer()->get('monolog.logger.contao.error')->info('Field "' . $strField . '" does not exist in DCA "' . $objDC->table . '"');
					header('HTTP/1.1 400 Bad Request');
					die('Bad Request');
				}

				$objRow = null;
				$varValue = null;
				$multiple = false;
			
				if( (isset($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval']['fieldType']) && $GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval']['fieldType'] == 'checkbox') || (isset($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval']['multiple']) && $GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval']['multiple']) )
				{
					$multiple = true;
				}
	
				// Load the current active record
				if($intDcaId > 0 && $objDatabase->tableExists($objDC->table))
				{
					$objActiveRecord = $objDatabase->prepare("SELECT * FROM " . $objDC->table . " WHERE id=?")->execute($intDcaId);
					if($objActiveRecord->numRows <  1)
					{
						\Contao\System::getContainer()->get('monolog.logger.contao.error')->info('A record with the ID "' . $intId . '" does not exist in table "' . $strSource . '"');
						header('HTTP/1.1 400 Bad Request');
						die('Bad Request');
					}
					$objDC->activeRecord = $objActiveRecord;
				}
				
				// Set the new value
				$varValue = \Contao\StringUtil::trimsplit('\t',\Contao\Input::post('value',true));
				
				if(!is_array($varValue))
				{
					$varValue = explode(',', $varValue);
				}
				
				// Call the load_callback
				if ( isset($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['load_callback']) && is_array($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['load_callback']))
				{
					foreach ($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['load_callback'] as $callback)
					{
						if (is_array($callback))
						{
							$varValue = \Contao\System::importStatic($callback[0])->{$callback[1]}($varValue, $objDC);
						}
						elseif (is_callable($callback))
						{
							$varValue = $callback($varValue, $objDC);
						}
					}
				}

				// Build the attributes based on the "eval" array
				$arrAttribs = $GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval'];

				$arrAttribs['id'] = $objDC->field;
				$arrAttribs['name'] = $objDC->field;
				$arrAttribs['value'] = $varValue;
				$arrAttribs['strTable'] = $objDC->table;
				$arrAttribs['strField'] = $strField;
				$arrAttribs['activeRecord'] = $objDC->activeRecord;
				$arrAttribs['tabletree']['source'] = $strSource;
				$arrAttribs['tabletree']['valueField'] = $strValueField;
				$arrAttribs['tabletree']['keyField'] = $strKeyField;
				$arrAttribs['tabletree']['orderField'] = $strOrderField;
				$arrAttribs['tabletree']['translationField'] = $strTranslationField;
				$arrAttribs['tabletree']['rootsField'] = $strRootsField;
				$arrAttribs['tabletree']['roots'] = $strRoots;
				#$arrAttribs['tabletree']['conditionsField'] = $strConditionsField;
				#$arrAttribs['tabletree']['conditions'] = $strConditions;
				
				$objWidget = new $GLOBALS['BE_FFL']['pct_tabletree']($arrAttribs);
				echo $objWidget->generate();
				exit;
				break;
		}
	}
	
	
	/**
	 * Ajax requests
	 * @param string
	 */
	public function preActions($strAction)
	{
		switch($strAction)
		{
			case 'toggleTabletree':
			case 'loadTabletree':
				$objSession = \Contao\System::getContainer()->get('request_stack')->getSession();
				$this->strAjaxId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', \Contao\Input::post('id'));
				$this->strAjaxKey = str_replace('_' . $this->strAjaxId, '', \Contao\Input::post('id'));
				
				if (\Contao\Input::get('act') == 'editAll')
				{
					$this->strAjaxKey = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $this->strAjaxKey);
					$this->strAjaxName = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', \Contao\Input::post('name'));
				}
				
				$nodes = $objSession->get($this->strAjaxKey);
				$nodes[$this->strAjaxId] = intval(\Contao\Input::post('state'));
				$objSession->set($this->strAjaxKey, $nodes);
				break;
		}
	}
}