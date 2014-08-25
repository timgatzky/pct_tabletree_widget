<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2014, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_tabletree_widget
 * @link		http://contao.org
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace PCT\Widgets;


/**
 * Class file
 * TableTree
 * Render the TableTree list view
 */
class TableTree extends \Widget
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
	 * Load the database object
	 * @param array
	 */
	public function __construct($arrAttributes=null)
	{
		$this->import('Database');
		parent::__construct($arrAttributes);
		
		if($arrAttributes['fieldType'] == 'checkbox' || $arrAttributes['multiple'] == true)
		{
			$this->blnIsMultiple = true;
		}
		
		// load js
		$GLOBALS['TL_JAVASCRIPT'][] = PCT_TABLETREE_PATH.'/assets/js/tabletree.js';
		
		$this->strSource = $arrAttributes['tabletree']['source'];
		$this->strValueField = strlen($arrAttributes['tabletree']['valueField']) > 0 ? $arrAttributes['tabletree']['valueField'] : 'title';
		$this->strKeyField = strlen($arrAttributes['tabletree']['keyField']) > 0 ? $arrAttributes['tabletree']['keyField'] : 'id';
		$this->strOrderField = strlen($arrAttributes['tabletree']['orderField']) > 0 ? $arrAttributes['tabletree']['orderField'] : 'sorting';
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
		if(!$this->strSource)
		{
			return '';
		}
		
		$objSession = \Session::getInstance();
		$objDatabase = \Database::getInstance();
		$strKeyField = $this->strKeyField;
		$strValueField = $this->strValueField;

		$this->import('BackendUser', 'User');
		$this->loadDataContainer($this->strSource);

		// Store the keyword
		if (\Input::post('FORM_SUBMIT') == 'item_selector')
		{
			$objSession->set('pct_tabletree_selector_search', \Input::post('keyword'));
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

			$objRoot = $this->Database->prepare("SELECT id,".$strValueField.$strKeyField != 'id' ? ",".$strKeyField : ""." FROM ".$this->strSource." WHERE CAST(title AS CHAR) REGEXP ?")
									  ->execute($for);

			if ($objRoot->numRows > 0)
			{
				// Respect existing limitations
				if (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes']))
				{
					$arrRoot = array();

					while ($objRoot->next())
					{
						// Predefined node set (see #3563)
						if (count(array_intersect($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes'], $this->Database->getParentRecords($objRoot->id, $this->strSource))) > 0)
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
						// Show only mounted pages to regular users
						## tl_page only
						if (count(array_intersect($this->User->pagemounts, $this->Database->getParentRecords($objRoot->id, $this->strSource))) > 0)
						{
							$arrRoot[] = $objRoot->id;
						}
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
			if ($strNode > 0 && is_array($GLOBALS['TL_DCA'][$this->strSource]['fields'][$this->strField]['rootNodes']))
			{
				if (!in_array($strNode, $objDatabase->getChildRecords($GLOBALS['TL_DCA'][$this->strSource]['fields'][$this->strField]['rootNodes'], 'tl_page')))
				{
					$this->Session->remove('tabletree_node');
				}
			}

			// Add the breadcrumb menu
			if (\Input::get('do') != 'page')
			{
				#\Backend::addBreadcrumb();
			}

			// Root nodes (breadcrumb menu)
			if (!empty($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['root']))
			{
			   $nodes = $this->eliminateNestedPages($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['root'], $this->strSource);
			   foreach ($nodes as $node)
			   {
			   		$tree .= $this->renderTree($node, -20);
			   }
			}
			
			// Predefined node set (see #3563)
			elseif (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes']))
			{
			   $nodes = $this->eliminateNestedPages($GLOBALS['TL_DCA'][$this->strSource]['fields'][$this->strField]['rootNodes'], $this->strSource);
			   foreach ($nodes as $node)
			   {
			   		$tree .= $this->renderTree($node, -20);
			   }
			}

			// Show all pages to admins
			elseif ($this->User->isAdmin)
			{
				// check if table contains a pid field
				$hasPid = false;
				if($objDatabase->fieldExists('pid',$this->strSource))
				{
					$hasPid = true;
				}
				$objRows = $objDatabase->prepare("SELECT id FROM ".$this->strSource.($hasPid == true ? " WHERE pid=? " : "").($this->strOrderField ? " ORDER BY ".$this->strOrderField : "") )->execute(0);
				while ($objRows->next())
				{
					$tree .= $this->renderTree($objRows->id, -20);
				}
			}
			// Show only mounted records to regular users
			else
			{
				$nodes = $this->eliminateNestedPages($this->User->pagemounts, $this->strSource);
				
				foreach ($nodes as $node)
				{
					$tree .= $this->renderTree($node, -20);
				}
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

		// Return the tree
		return '<ul class="tl_listing tree_view picker_selector'.(($this->strClass != '') ? ' ' . $this->strClass : '').'" id="'.$this->strId.'">
    <li class="tl_folder_top"><div class="tl_left">'.\Image::getHtml($GLOBALS['TL_DCA'][$this->strSource]['list']['sorting']['icon'] ?: 'pagemounts.gif').' '.($GLOBALS['TL_CONFIG']['websiteTitle'] ?: 'Contao Open Source CMS').'</div> <div class="tl_right">&nbsp;</div><div style="clear:both"></div></li><li class="parent" id="'.$this->strId.'_parent"><ul>'.$tree.$strReset.'
  </ul></li></ul>';
	}


	/**
	 * Generate a particular subpart of the page tree and return it as HTML string
	 * @param integer
	 * @param string
	 * @param integer
	 * @return string
	 */
	public function generateAjax($id, $strField, $strValueField, $level)
	{
		if(!\Environment::get('isAjaxRequest'))
		{
			return '';
		}
		
		$this->strId = $id;
		$this->strField = $strField;
		$this->strValueField = $strValueField;
		
		$objDatabase = \Database::getInstance();
		$this->loadDataContainer($this->strSource);
		$this->loadDataContainer($this->strTable);
		
		// check if regular dca field exists and if value field in source table exists
		if(!$objDatabase->fieldExists($this->strField, $this->strTable) && !$objDatabase->fieldExists($this->strValueField, $this->strSource))
		{
		   return;
		}
		$objField = $objDatabase->prepare("SELECT ".$this->strValueField." FROM ".$this->strSource." WHERE id=?")->limit(1)->execute($this->strId);
		if($objField->numRows > 0)
		{
		    $this->varValue = deserialize($objField->{$this->strValueField});
		}

		$this->getNodes();
		
		// Load the requested nodes
		$tree = '';
		$level = $level * 30;
		$objRows = \Database::getInstance()->prepare("SELECT id FROM ".$this->strSource." WHERE pid=? ".($this->strOrderField ? "ORDER BY ".$this->strOrderField : ""))->execute($id);

		while ($objRows->next())
		{
			$tree .= $this->renderTree($objRows->id,$level);
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
		$objSession = \Session::getInstance();
		$objDatabase = \Database::getInstance();
		$session = $objSession->getData();
		
		$flag = substr($this->strField, 0, 2);
		$node = 'tree_' . $this->strSource . '_' . $this->strField;
		$xtnode = 'tree_' . $this->strSource . '_' . $this->strName;
		$nestedModes = array(5);
		
		$strKeyField = $this->strKeyField;
		$strValueField = $this->strValueField;
		$strOrderField = $this->strOrderField;

		// Get the session data and toggle the nodes
		if (\Input::get($flag.'tg'))
		{
			$session[$node][\Input::get($flag.'tg')] = (isset($session[$node][\Input::get($flag.'tg')]) && $session[$node][\Input::get($flag.'tg')] == 1) ? 0 : 1;
			$objSession->setData($session);
			$this->redirect(preg_replace('/(&(amp;)?|\?)'.$flag.'tg=[^& ]*/i', '', \Environment::get('request')));
		}

		$objRow = $objDatabase->prepare("SELECT * FROM ".$this->strSource." WHERE id=?")->limit(1)->execute($id);

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
			$objChilds = $objDatabase->prepare("SELECT id FROM ".$this->strSource." WHERE pid=? ".($this->strOrderField ? " ORDER BY ".$this->strOrderField : ""))
									   ->execute($id);
			if ($objChilds->numRows > 0)
			{
				$childs = $objChilds->fetchEach('id');
			}
		}

		$return .= "\n    " . '<li class="tl_file" onmouseover="Theme.hoverDiv(this, 1)" onmouseout="Theme.hoverDiv(this, 0)" onclick="Theme.toggleSelect(this)"><div class="tl_left" style="padding-left:'.($intMargin + $intSpacing).'px">';

		$folderAttribute = 'style="margin-left:20px"';
		$session[$node][$id] = is_numeric($session[$node][$id]) ? $session[$node][$id] : 0;
		$level = ($intMargin / $intSpacing + 1);
		$blnIsOpen = ($session[$node][$id] == 1 || in_array($id, $this->arrNodes));

		if (!empty($childs))
		{
			$folderAttribute = '';
			$img = $blnIsOpen ? 'folMinus.gif' : 'folPlus.gif';
			$alt = $blnIsOpen ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode'];
			$return .= '<a href="'.$this->addToUrl($flag.'tg='.$id).'" title="'.specialchars($alt).'" onclick="return AjaxRequest.toggleTabletree(this,\''.$xtnode.'_'.$id.'\',\''.$this->strField.'\',\''.$this->strName.'\',\''.$this->strSource.'\',\''.$this->strValueField.'\',\''.$this->strKeyField.'\','.$level.')">'.\Image::getHtml($img, '', 'style="margin-right:2px"').'</a>';
		}

		// Set the protection status
		$objRow->protected = ($objRow->protected || $protectedRow);

		// Add the current row
		if (count($childs) > 0)
		{
			$return .= '<a href="' . $this->addToUrl('node='.$objRow->id) . '" title="'.specialchars($objRow->$strValueField . ' (' . $objRow->$strKeyField . $GLOBALS['TL_CONFIG']['urlSuffix'] . ')').'">'.$objRow->$strValueField.'</a></div> <div class="tl_right">';
		}
		else
		{
			$return .= $objRow->$strValueField.'</div> <div class="tl_right">';
			#\FB::log($objRow->$strValueField);
		}

		// set fieldtype to checkbox if field is multiple
		if($this->blnIsMultiple)
		{
			$GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType'] = 'checkbox';
		}
		
		// Add checkbox or radio button
		switch ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType'])
		{
			case 'checkbox':
				$return .= '<input type="checkbox" name="'.$this->strName.'[]" id="'.$this->strName.'_'.$id.'" class="tl_tree_checkbox" value="'.specialchars($id).'" onfocus="Backend.getScrollOffset()"'.static::optionChecked($id, $this->varValue).'>';
				break;

			default:
			case 'radio':
				$return .= '<input type="radio" name="'.$this->strName.'" id="'.$this->strName.'_'.$id.'" class="tl_tree_radio" value="'.specialchars($id).'" onfocus="Backend.getScrollOffset()"'.static::optionChecked($id, $this->varValue).'>';
				break;
		}

		$return .= '</div><div style="clear:both"></div></li>';

		// Begin a new submenu
		if (count($childs) > 0 && ($blnIsOpen || $this->Session->get('pct_tabletree_selector_search') != ''))
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

		foreach ($this->varValue as $id)
		{
			$arrPids = \Database::getInstance()->getParentRecords($id, $this->strSource);
			array_shift($arrPids); // the first element is the ID of the page itself
			$this->arrNodes = array_merge($this->arrNodes, $arrPids);
		}
	}
}