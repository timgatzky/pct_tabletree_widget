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


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace PCT\Widgets;

use Contao\BackendUser;
use Contao\System;
use Contao\Config;

/**
 * Class file
 * TableTree
 * Render the TableTree list view
 */
class TableTree extends \Contao\Widget
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';

	/**
	 * Multiple flag
	 * @var boolean
	 */
	protected $blnIsMultiple = false;
	
	/**
	 * Array nodes
	 * @param array
	 */
	protected $arrNodes = array();
	
	/**
	 * Root nodes array
	 * @var array
	 */
	protected $arrRootNodes = array();

	/**
	 * Source table
	 * @var string
	 */
	protected $strSource = '';
	
	/**
	 * Field name of the value column
	 * @var string
	 */
	protected $strValueField = '';

	/**
	 * Field name of the key column
	 * @var string
	 */
	protected $strKeyField = '';
	
	/**
	 * Custom conditions
	 * @var string
	 */
	protected $strConditions = '';
	
	/**
	 * Field name of the conditions field
	 * @var string
	 */
	protected $strConditionField = '';
	

	/**
	 * Load the database object
	 * @param array
	 */
	public function __construct($arrAttributes=null)
	{
		parent::__construct($arrAttributes);
		
		if( !isset($this->Database) || $this->Database === null )
		{
			$this->Database = \Contao\Database::getInstance();
		}

		// load the datacontainer of the origin table
		$this->loadDataContainer($this->strTable);
			
		// load js
		$GLOBALS['TL_JAVASCRIPT'][] = PCT_TABLETREE_PATH.'/assets/js/tabletree.js';
		
		// get field defintion from datacontainer since contao does not pass custom evalulation arrays to widgets
		if(!is_array($arrAttributes['tabletree']))
		{
			$arrAttributes = array_merge($arrAttributes, $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]);
		}
		
		if( (isset($arrAttributes['fieldType']) && $arrAttributes['fieldType'] == 'checkbox') || (isset($arrAttributes['multiple']) && $arrAttributes['multiple'] == true) || (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple']) && (boolean)$GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple']) )
		{
			$this->blnIsMultiple = true;
		}
		
		$this->strSource = $arrAttributes['tabletree']['source'];
		$this->strValueField = strlen($arrAttributes['tabletree']['valueField']) > 0 ? $arrAttributes['tabletree']['valueField'] : 'id';
		$this->strKeyField = strlen($arrAttributes['tabletree']['keyField']) > 0 ? $arrAttributes['tabletree']['keyField'] : 'id';
		$this->strOrderField = $arrAttributes['tabletree']['orderField'];
		$this->strRootField = $arrAttributes['tabletree']['rootsField'];
		$this->strConditionsField = $arrAttributes['tabletree']['conditionsField'] ?? '';
		$this->strConditions = System::getContainer()->get('contao.insert_tag.parser')->replace($arrAttributes['tabletree']['conditions'] ?? ''); 
		// load the data container of the source table e.g. for permission checks
		$this->loadDataContainer($this->strSource);
		
		if( isset($arrAttributes['tabletree']['translationField']) && strlen($arrAttributes['tabletree']['translationField']) > 0)
		{
			$this->strTranslationField = $arrAttributes['tabletree']['translationField'];
		}
		
		// set roots
		if(isset($arrAttributes['tabletree']['roots']))
		{
			if(!is_array($arrAttributes['tabletree']['roots']))
			{
				$this->arrRootNodes = explode(',', $arrAttributes['tabletree']['roots']);
			}
			else
			{
				$this->arrRootNodes = $arrAttributes['tabletree']['roots'];
			}
		}
	}
	
	
	/**
	 * Setter
	 * @param string
	 * @param mixed
	 */
	public function set($strKey, $varValue)
	{
		$this->$strKey = $varValue;
	}
	
	
	/**
	 * Getter
	 * @param string
	 * @return mixed
	 */
	public function get($strKey)
	{
		return $this->$strKey;
	}


	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		if( empty($this->strSource) )
		{
			return '';
		}

		$objSession = System::getContainer()->get('request_stack')->getSession();
		$objDatabase = \Contao\Database::getInstance();
		$strKeyField = $this->strKeyField;
		$strValueField = $this->strValueField;
		$strTranslationField = $this->strTranslationField;
		
		System::import(BackendUser::class, 'User');	
		$this->loadDataContainer($this->strSource);

		// Store the keyword
		if (\Contao\Input::post('FORM_SUBMIT') == 'pct_tableTreeWidget' && \Contao\Input::post('keyword') != $objSession->get('pct_tabletree_selector_search'))
		{
			$objSession->set('pct_tabletree_selector_search', \Contao\Input::post('keyword'));
			$this->reload();
		}

		$tree = '';
		$this->getNodes();
		$for = $objSession->get('pct_tabletree_selector_search');
		$arrIds = array();
		
		// Search for a specific value
		if ($for != '')
		{
			// The keyword must not start with a wildcard (see #4910)
			if (strncmp($for, '*', 1) === 0)
			{
				$for = substr($for, 1);
			}

			$objRoot = $objDatabase->prepare("SELECT id,".$strValueField." FROM ".$this->strSource." WHERE CAST(".$strValueField." AS CHAR) REGEXP ? ".($this->strConditions ? " AND ".$this->strConditions:""))->execute($for);
			if ($objRoot->numRows > 0)
			{
				// Respect existing limitations
				if ( isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField][$this->strRootField]) && is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField][$this->strRootField]))
				{
					$arrRoot = array();

					while ($objRoot->next())
					{
						// Predefined node set (see #3563)
						if (count(array_intersect($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField][$this->strRootField], $objDatabase->getParentRecords($objRoot->id, $this->strSource))) > 0)
						{
							$arrRoot[] = $objRoot->id;
						}
					}

					$arrIds = $arrRoot;
				}
				elseif ($this->User->isAdmin)
				{
					// Show all pages to admins
					$arrIds = $objRoot->fetchEach('id');
				}
				else
				{
					$arrRoot = array();

					while ($objRoot->next())
					{
						$arrRoot[] = $objRoot->{$strKeyField};
					}

					$arrIds = $arrRoot;
				}
			}

			// Build the tree
			foreach ($arrIds as $id)
			{
				$tree .= $this->renderTree($id, -20, false, true);
			}
		}
		else
		{
			$strNode = $objSession->get('tabletree_node');
			
			// Unset the node if it is not within the predefined node set (see #5899)
			if ($strNode > 0 && is_array($GLOBALS['TL_DCA'][$this->strSource]['fields'][$this->strField][$this->strRootField]))
			{
				if (!in_array($strNode, $objDatabase->getChildRecords($GLOBALS['TL_DCA'][$this->strSource]['fields'][$this->strField][$this->strRootField], $this->strSource)))
				{
					$objSession->remove('tabletree_node');
				}
			}
			
			// Root nodes (breadcrumb menu)
			if ( isset($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['root']) && !empty($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['root']))
			{
			   $nodes = $this->eliminateNestedPages($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['root'], $this->strSource);
			   foreach ($nodes as $node)
			   {
			   		$tree .= $this->renderTree($node, -20);
			   }
			}
			
			// Predefined node set (see #3563)
			elseif ( isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField][$this->strRootField]) && is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField][$this->strRootField]))
			{
			   $nodes = $this->eliminateNestedPages($GLOBALS['TL_DCA'][$this->strSource]['fields'][$this->strField][$this->strRootField], $this->strSource);
			   foreach ($nodes as $node)
			   {
			   		$tree .= $this->renderTree($node, -20);
			   }
			}
			// custom root nodes
			elseif( isset($this->arrRootNodes) && count($this->arrRootNodes) > 0)
			{
				$nodes = $this->eliminateNestedPages($this->arrRootNodes, $this->strSource);
				foreach ($nodes as $node)
				{
					$tree .= $this->renderTree($node, -20);
				}
			}
			// Show all to admins
			elseif ($this->User->isAdmin || empty($this->arrRootNodes))
			{
				// check if table contains a pid field
				$hasPid = (boolean)$objDatabase->fieldExists('pid',$this->strSource);
				$pid = '';
				$mode = (int)$GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['mode'];
				if($hasPid && in_array($mode, array(4)) )
				{
					$pid = 'pid>=0';
				}
				else if( $hasPid && in_array($mode, array(5)) )
				{
					$pid = 'pid=0';
				}
				
				$objRows = $objDatabase->prepare("SELECT ".$strKeyField." FROM ".$this->strSource." WHERE ".(strlen($pid) > 0 ? $pid : $strKeyField."!=''").($this->strConditions ? " AND ".$this->strConditions:"") . ($this->strOrderField ? " ORDER BY ".$this->strOrderField : "") )->execute();
				
				while ($objRows->next())
				{
					$tree .= $this->renderTree($objRows->{$strKeyField}, -20);
				}
			}
			else
			{
				// do nothing
			}
		}
		
		// Select all checkboxes
		if ($this->blnIsMultiple)
		{
			$strReset = "\n" . '    <li class="tl_folder"><div class="tl_left">&nbsp;</div> <div class="tl_right"><label for="check_all_' . $this->strId . '" class="tl_change_selected">' . $GLOBALS['TL_LANG']['MSC']['selectAll'] . '</label> <input type="checkbox" id="check_all_' . $this->strId . '" class="tl_tree_checkbox" value="" onclick="Backend.toggleCheckboxGroup(this,\'' . $this->strName . '\')"></div><div style="clear:both"></div></li>';
		}
		// Reset radio button selection
		else
		{
			$strReset = "\n" . '    <li class="tl_folder"><div class="tl_left">&nbsp;</div> <div class="tl_right"><label for="reset_' . $this->strId . '" class="tl_change_selected">' . $GLOBALS['TL_LANG']['MSC']['resetSelected'] . '</label> <input type="radio" name="' . $this->strName . '" id="reset_' . $this->strName . '" class="tl_tree_radio" value="" onfocus="Backend.getScrollOffset()"></div><div style="clear:both"></div></li>';
		}
		
		$pickerValue = array_map('strval',$this->varValue);
		$pickerValue = json_encode($pickerValue);
		$pickerValue = htmlspecialchars($pickerValue);
		
		// Return the tree
		return '<ul data-picker-value="'.$pickerValue.'" class="tl_listing tree_view picker_selector'.(($this->strClass != '') ? ' ' . $this->strClass : '').'" id="'.$this->strId.'">
    <li class="tl_folder_top"><div class="tl_left">'.\Contao\Image::getHtml($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['icon'] ?? 'pagemounts.gif').' '.($GLOBALS['TL_CONFIG']['websiteTitle'] ?? 'Contao Open Source CMS').'</div> <div class="tl_right">&nbsp;</div><div style="clear:both"></div></li><li class="parent" id="'.$this->strId.'_parent"><ul>'.$tree.$strReset.'
  </ul></li></ul>';
	}


	/**
	 * Generate a particular subpart of the page tree and return it as HTML string
	 * @param integer
	 * @param string
	 * @param string
	 * @param integer
	 * @return string
	 */
	public function generateAjax($id, $strField, $strValueField, $strKeyField, $level)
	{
		if(!\Contao\Environment::get('isAjaxRequest'))
		{
			return '';
		}
		
		$this->strId = $id ?: 0;
		$this->strField = $strField;
		$this->strValueField = $strValueField ?: 'id';
		$this->strKeyField = $strKeyField ?: 'id';
		
		$objDatabase = \Contao\Database::getInstance();
		$this->loadDataContainer($this->strSource);
		$this->loadDataContainer($this->strTable);
		
		// check if field is multiple
		if($GLOBALS['TL_DCA'][$this->strTable]['fields'][$strField]['eval']['fieldType'] == 'checkbox' || $GLOBALS['TL_DCA'][$this->strTable]['fields'][$strField]['eval']['multiple'])
		{
			$this->blnIsMultiple = true;
		}
		
		// check if regular dca field exists and if value field in source table exists
		if(!$objDatabase->fieldExists($this->strField, $this->strTable) && !$objDatabase->fieldExists($this->strValueField, $this->strSource))
		{
		   return;
		}
		
		$objField = $objDatabase->prepare("SELECT ".$this->strValueField." FROM ".$this->strSource." WHERE id=?")->limit(1)->execute($this->strId);
		if($objField->numRows > 0)
		{
		    $this->varValue = \Contao\StringUtil::deserialize($objField->{$this->strValueField});
		}

		$this->getNodes();
		
		// check if the order field exists
		if($this->strOrderField && !$objDatabase->fieldExists($this->strOrderField,$this->strSource))
		{
			$this->strOrderField = '';
		}
		
		// Load the requested nodes
		$tree = '';
		$level = $level * 30;
		$objRows = \Contao\Database::getInstance()->prepare("SELECT id,".$this->strKeyField." FROM ".$this->strSource." WHERE pid=? ".($this->strOrderField ? " ORDER BY ".$this->strOrderField : ""))->execute($id);

		while ($objRows->next())
		{
			$tree .= $this->renderTree($objRows->{$this->strKeyField},$level);
		}
		
		return $tree;
	}

	
	
	/**
	 * Recursively render the table tree
	 * @param integer
	 * @param integer
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function renderTree($id, $intMargin, $protectedRow=false, $blnNoRecursion=false)
	{
		static $session;
		$objSession = System::getContainer()->get('request_stack')->getSession();
		$objDatabase = \Contao\Database::getInstance();
		$session = $objSession->all();
		
		$flag = substr($this->strField, 0, 2);
		$node = 'tree_' . $this->strSource . '_' . $this->strField;
		$xtnode = 'tree_' . $this->strSource . '_' . $this->strName;
		$nestedModes = array(5);
	
		$strKeyField = $this->strKeyField ?: 'id';
		$strValueField = $this->strValueField ?: 'id';
		$strOrderField = $this->strOrderField;
		$strTanslationField = $this->strTranslationField;
			
		// Get the session data and toggle the nodes
		if (\Contao\Input::get($flag.'tg'))
		{
			$session[$node][\Contao\Input::get($flag.'tg')] = (isset($session[$node][\Contao\Input::get($flag.'tg')]) && $session[$node][\Contao\Input::get($flag.'tg')] == 1) ? 0 : 1;
			$objSession->replace($session);
			$this->redirect(preg_replace('/(&(amp;)?|\?)'.$flag.'tg=[^& ]*/i', '', \Contao\Environment::get('request')));
		}

		$objRow = $objDatabase->prepare("SELECT * FROM ".$this->strSource." WHERE ".$strKeyField."=?".($this->strConditions ? " AND ".$this->strConditions:""))->limit(1)->execute($id);
		
		// Return if there is no result
		if ($objRow->numRows < 1)
		{
			return '';
		}
		
		$return = '';
		$intSpacing = 20;
		$childs = array();

		// Check whether there are child records
		if (!$blnNoRecursion && in_array($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['mode'], $nestedModes) )
		{
			$objChilds = $objDatabase->prepare("SELECT id,".$strKeyField." FROM ".$this->strSource." WHERE pid=? ".($this->strOrderField ? " ORDER BY ".$this->strOrderField : ""))
									   ->execute($id);
			if ($objChilds->numRows > 0)
			{
				$childs = $objChilds->fetchEach($strKeyField);
			}
		}
		
		$return .= "\n    " . '<li class="tl_file toggle_select"><div class="tl_left" style="padding-left:'.($intMargin + $intSpacing).'px">';
		
		$folderAttribute = 'style="margin-left:20px"';
		$session[$node][$id] = $session[$node][$id] ?? 0;
		$level = ($intMargin / $intSpacing + 1);
		$blnIsOpen = ($session[$node][$id] == 1 || in_array($id, $this->arrNodes));

		if (!empty($childs))
		{
			$folderAttribute = '';
			$img = $blnIsOpen ? 'folMinus.gif' : 'folPlus.gif';
			$alt = $blnIsOpen ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode'];
			$return .= '<a href="'.$this->addToUrl($flag.'tg='.$id).'" title="'.\Contao\StringUtil::specialchars($alt).'" onclick="return AjaxRequest.toggleTabletree(this,\''.$xtnode.'_'.$id.'\',\''.$this->strField.'\',\''.$this->strName.'\',\''.$this->strSource.'\',\''.$this->strValueField.'\',\''.$this->strKeyField.'\',\''.$this->strConditions.'\','.$level.')">'.\Contao\Image::getHtml($img, '', 'style="margin-right:2px"').'</a>';
		}

		// Set the protection status
		$objRow->protected = ($objRow->protected || $protectedRow);

		// label callback
		$label_callback = $GLOBALS['PCT_TABLETREE_WIDGET'][$this->strSource]['label_callback'] ?? array();	
		
		// Add the current row
		if (count($childs) > 0)
		{
			$strLabel = $objRow->$strValueField;
			if(strlen($objRow->{$strTanslationField}) > 0)
			{
				$arrTranslations = \Contao\StringUtil::deserialize($objRow->{$strTanslationField});
				$lang = \Contao\Input::get('language') ?: \Contao\Input::get('lang') ?: $GLOBALS['TL_LANGUAGE'];
				if( isset( $arrTranslations[$lang]['label'] ) && !empty( $arrTranslations[$lang]['label'] ) )
				{
					$strLabel = $arrTranslations[$lang]['label'];
				}
			}
			
			// list_label_callback
			if(is_array($label_callback) && !empty($label_callback))
			{
				$strLabel = \Contao\Controller::importStatic($label_callback[0])->{$label_callback[1]}($objRow->row(),$strLabel);
			}
			
			$return .= '<a href="' . $this->addToUrl('node='.$objRow->{$strKeyField}) . '" title="'.\Contao\StringUtil::specialchars($objRow->$strValueField . ' (' . $objRow->$strKeyField . Config::get('urlSuffix') . ')').'">'.$strLabel.'</a></div> <div class="tl_right">';
		}
		else
		{
			$strLabel = $objRow->$strValueField;
			if(strlen($objRow->{$strTanslationField}) > 0)
			{
				$arrTranslations = \Contao\StringUtil::deserialize($objRow->{$strTanslationField});
				$lang = \Contao\Input::get('language') ?: \Contao\Input::get('lang') ?: $GLOBALS['TL_LANGUAGE'];
				if( isset( $arrTranslations[$lang]['label'] ) && !empty( $arrTranslations[$lang]['label'] ) )
				{
					$strLabel = $arrTranslations[$lang]['label'];
				}
			}
			
			// list_label_callback
			if(is_array($label_callback) && !empty($label_callback))
			{
				$strLabel = \Contao\Controller::importStatic($label_callback[0])->{$label_callback[1]}($objRow->row(),$strLabel);
			}
			
			$return .= $strLabel.'</div> <div class="tl_right">';
		}

		// set fieldtype to checkbox if field is multiple
		if($this->blnIsMultiple)
		{
			$GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType'] = 'checkbox';
		}
		
		if( !isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType']) )
		{
			$GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType'] = 'radio';
		}
		// Add checkbox or radio button
		switch ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType'])
		{
			case 'checkbox':
				$return .= '<input type="checkbox" name="'.$this->strName.'[]" id="'.$this->strName.'_'.$id.'" class="tl_tree_checkbox" value="'.\Contao\StringUtil::specialchars($id).'" onfocus="Backend.getScrollOffset()"'.static::optionChecked($id, $this->varValue).'>';
				break;

			default:
			case 'radio':
				$return .= '<input type="radio" name="'.$this->strName.'" id="'.$this->strName.'_'.$id.'" class="tl_tree_radio" value="'.\Contao\StringUtil::specialchars($id).'" onfocus="Backend.getScrollOffset()"'.static::optionChecked($id, $this->varValue).'>';
				break;
		}

		$return .= '</div><div style="clear:both"></div></li>';

		// Begin a new submenu
		if (!empty($childs) && is_array($childs) && ($blnIsOpen || $objSession->get('pct_tabletree_selector_search') != ''))
		{
			$return .= '<li class="parent" id="'.$node.'_'.$id.'"><ul class="level_'.$level.'">';

			for ($k=0; $k<count($childs); $k++)
			{
				$return .= $this->renderTree($childs[$k], ($intMargin + $intSpacing), $objRow->protected);
			}

			$return .= '</ul></li>';
		}

		return $return;
	}


	/**
	 * Get the IDs of all parent record ids
	 * @return array
	 */
	protected function getNodes()
	{
		if (!$this->varValue)
		{
			return array();
		}

		if (!is_array($this->varValue))
		{
			$this->varValue = array($this->varValue);
		}

		$objDatabase = \Contao\Database::getInstance();
		$hasPid = (boolean)$objDatabase->fieldExists('pid',$this->strSource);

		foreach ($this->varValue as $id)
		{
			$arrPids = array(0);
			if($hasPid)
			{
				$arrPids = $objDatabase->getParentRecords($id, $this->strSource);
				array_shift($arrPids); // the first element is the ID of the page itself
			}
			$this->arrNodes = array_merge($this->arrNodes, $arrPids);
		}
	}
}
