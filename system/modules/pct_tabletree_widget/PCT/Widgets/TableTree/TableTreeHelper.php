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
 * Class file
 * TableTreeHelper
 */
class TableTreeHelper extends \Backend
{
	/**
	 * Backend ajax requests
	 * @param string
	 */
	public function postActions($strAction, \DataContainer $objDC)
	{
		switch($strAction)
		{
			// Load nodes of the table tree
			case 'toggleTabletree':
			case 'loadTabletree':
				$arrData['strTable'] = $objDC->table;
				$arrData['strField'] = $objDC->field;
				$arrData['id'] = $this->strAjaxName ?: $objDC->id;
				$arrData['name'] = \Input::post('name');
				$arrData['tabletree']['source'] = \Input::post('source');
				$arrData['tabletree']['valueField'] = \Input::post('valueField');
				$arrData['tabletree']['keyField'] = \Input::post('keyField');
				$arrData['tabletree']['orderField'] = \Input::post('orderField');
				$objWidget = new \PCT\Widgets\TableTree($arrData, $objDC);
				echo $objWidget->generateAjax($this->strAjaxId, \Input::post('field'), \Input::post('valueField'), intval(\Input::post('level')));
				exit;
				break;
			case 'reloadTabletree':
				$intId = \Input::post('id');
				$intDcaId = $objDC->id = \Input::get('id');
				$strField = $objDC->field = \Input::post('name');
				$strSource = $objDC->source = \Input::post('source');
				$strValueField = $objDC->valueField = \Input::post('valueField');
				$strKeyField = $objDC->keyField = \Input::post('keyField');
				$strOrderField = $objDC->orderField = \Input::post('orderField');
				$objDatabase = \Database::getInstance();
				
				// Handle the keys in "edit multiple" mode
				if (\Input::get('act') == 'editAll')
				{
					$intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
					$strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
				}

				// The field does not exist
				if (!isset($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]))
				{
					$this->log('Field "' . $strField . '" does not exist in DCA "' . $objDC->table . '"', __METHOD__, TL_ERROR);
					header('HTTP/1.1 400 Bad Request');
					die('Bad Request');
				}

				$objRow = null;
				$varValue = null;
				$multiple = false;
			
				if($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval']['fieldType'] == 'checkbox' || $GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['eval']['multiple'])
				{
					$multiple = true;
				}

				// Load the value
				if ($intId > 0 && $objDatabase->tableExists($strSource))
				{
					$objRow = $objDatabase->prepare("SELECT * FROM " . $strSource . " WHERE id=?")->execute($intId);
					
					// The record does not exist
					if ($objRow->numRows < 1)
					{
						$this->log('A record with the ID "' . $intId . '" does not exist in table "' . $strSource . '"', __METHOD__, TL_ERROR);
						header('HTTP/1.1 400 Bad Request');
						die('Bad Request');
					}

					$varValue = $objRow->$strValueField;
				}
				
				// Load the current active record
				if($intDcaId > 0 && $objDatabase->tableExists($objDC->table))
				{
					$objActiveRecord = $objDatabase->prepare("SELECT * FROM " . $objDC->table . " WHERE id=?")->execute($intDcaId);
					$objDC->activeRecord = $objActiveRecord;
				}
				
				// Call the load_callback
				if (is_array($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['load_callback']))
				{
					foreach ($GLOBALS['TL_DCA'][$objDC->table]['fields'][$strField]['load_callback'] as $callback)
					{
						if (is_array($callback))
						{
							$this->import($callback[0]);
							$varValue = $this->$callback[0]->$callback[1]($varValue, $objDC);
						}
						elseif (is_callable($callback))
						{
							$varValue = $callback($varValue, $objDC);
						}
					}
				}

				// Set the new value
				$varValue = trimsplit('\t',\Input::post('value',true));
				
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
				$objSession = \Session::getInstance();
				$this->strAjaxId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', \Input::post('id'));
				$this->strAjaxKey = str_replace('_' . $this->strAjaxId, '', \Input::post('id'));
				
				if (\Input::get('act') == 'editAll')
				{
					$this->strAjaxKey = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $this->strAjaxKey);
					$this->strAjaxName = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', \Input::post('name'));
				}

				$nodes = $objSession->get($this->strAjaxKey);
				$nodes[$this->strAjaxId] = intval(\Input::post('state'));
				$objSession->set($this->strAjaxKey, $nodes);
				break;
		}
	}
}