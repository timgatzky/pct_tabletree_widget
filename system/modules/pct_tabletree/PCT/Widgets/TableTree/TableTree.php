<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2014, Premium Contao Webworks, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_customelements
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
	 * Source table
	 * @var string
	 */
	protected $strSource = '';

	
	/**
	 * Array tag nodes
	 * @param array
	 */
	protected $arrNodes = array();


	/**
	 * Load the database object
	 * @param array
	 */
	public function __construct($arrAttributes=null)
	{
		$this->import('Database');
		parent::__construct($arrAttributes);
		
		// load js
		$GLOBALS['TL_JAVASCRIPT'][] = PCT_CUSTOMELEMENTS_TAGS_PATH.'/PCT/Widgets/TableTree/assets/js/tabletree.js';
		
		$this->strSource = $arrAttributes['strSource'];
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
		
		$this->import('BackendUser', 'User');

		// Store the keyword
		if (\Input::post('FORM_SUBMIT') == 'item_selector')
		{
			$this->Session->set('pct_tabletree_selector_search', \Input::post('keyword'));
			$this->reload();
		}

		$tree = '';
		$this->getPathNodes();
		$for = $this->Session->get('pct_tabletree_selector_search');
		$arrIds = array();

		// Search for a specific value
		if ($for != '')
		{
			// The keyword must not start with a wildcard (see #4910)
			if (strncmp($for, '*', 1) === 0)
			{
				$for = substr($for, 1);
			}

### title field
			$objRoot = $this->Database->prepare("SELECT id FROM ".$this->strSource." WHERE CAST(title AS CHAR) REGEXP ?")
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
			$strNode = $this->Session->get('pct_tabletree_picker');
			
			// Unset the node if it is not within the predefined node set (see #5899)
			if ($strNode > 0 && is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes']))
			{
				if (!in_array($strNode, $this->Database->getChildRecords($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes'], 'tl_page')))
				{
					$this->Session->remove('pct_tabletree_picker');
				}
			}

			// Add the breadcrumb menu
			if (\Input::get('do') != 'page')
			{
				\Backend::addBreadcrumb();
			}

			// Root nodes (breadcrumb menu)
			if (!empty($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['root']))
			{
				$nodes = $this->eliminateNestedPages($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['root']);

				foreach ($nodes as $node)
				{
					$tree .= $this->renderTree($node, -20);
				}
			}

			// Predefined node set (see #3563)
			elseif (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes']))
			{
			   $nodes = $this->eliminateNestedPages($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['rootNodes']);
			
			   foreach ($nodes as $node)
			   {
			   	$tree .= $this->renderPagetree($node, -20);
			   }
			}

			// Show all pages to admins
			elseif ($this->User->isAdmin)
			{
				$objPage = $this->Database->prepare("SELECT id FROM ".$this->strSource." WHERE pid=? ORDER BY sorting")->execute(0);
				while ($objPage->next())
				{
					$tree .= $this->renderTree($objPage->id, -20);
				}
			}

			// Show only mounted pages to regular users
			else
			{
				$nodes = $this->eliminateNestedPages($this->User->pagemounts);

				foreach ($nodes as $node)
				{
					$tree .= $this->renderTree($node, -20);
				}
			}
		}
		
		// Select all checkboxes
		if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['fieldType'] == 'checkbox')
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
    <li class="tl_folder_top"><div class="tl_left">'.\Image::getHtml($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['icon'] ?: 'pagemounts.gif').' '.($GLOBALS['TL_CONFIG']['websiteTitle'] ?: 'Contao Open Source CMS').'</div> <div class="tl_right">&nbsp;</div><div style="clear:both"></div></li><li class="parent" id="'.$this->strId.'_parent"><ul>'.$tree.$strReset.'
  </ul></li></ul>';
	}


	/**
	 * Generate a particular subpart of the page tree and return it as HTML string
	 * @param integer
	 * @param string
	 * @param integer
	 * @return string
	 */
	public function generateAjax($id, $strField, $level)
	{
		if (!\Environment::get('isAjaxRequest'))
		{
			return '';
		}

		$this->strField = $strField;
		#$this->loadDataContainer($this->strTable);
		#if (!$this->Database->fieldExists($this->strField, $this->strSource))
		#{
		#	return;
		#}
	#	$objField = $this->Database->prepare("SELECT " . $this->strField . " FROM " . $this->strSource . " WHERE id=?")
	#									   ->limit(1)
	#									   ->execute($this->strId);
	#
	#	if ($objField->numRows)
	#	{
	#		$this->varValue = deserialize($objField->{$this->strField});
	#	}

		#$this->getPathNodes();
		// Load the requested nodes
		$tree = '';
		$level = $level * 30;
		$objPage = \Database::getInstance()->prepare("SELECT id FROM ".$this->strSource." WHERE pid=? ORDER BY sorting")
								  ->execute($id);

		while ($objPage->next())
		{
			$tree .= $this->renderTree($objPage->id, $level);
		}
		
		return $tree;
	}

	
	
	/**
	 * Recursively render the table tree
	 * @param int
	 * @param integer
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function renderTree($id, $intMargin, $protectedPage=false, $blnNoRecursion=false)
	{
		static $session;
		$session = $this->Session->getData();

		$flag = substr($this->strField, 0, 2);
		$node = 'tree_' . $this->strSource . '_' . $this->strField;
		$xtnode = 'tree_' . $this->strSource . '_' . $this->strName;

		// Get the session data and toggle the nodes
		if (\Input::get($flag.'tg'))
		{
			$session[$node][\Input::get($flag.'tg')] = (isset($session[$node][\Input::get($flag.'tg')]) && $session[$node][\Input::get($flag.'tg')] == 1) ? 0 : 1;
			$this->Session->setData($session);
			$this->redirect(preg_replace('/(&(amp;)?|\?)'.$flag.'tg=[^& ]*/i', '', \Environment::get('request')));
		}

		$objPage = $this->Database->prepare("SELECT * FROM ".$this->strSource." WHERE id=?")
								  ->limit(1)
								  ->execute($id);

		// Return if there is no result
		if ($objPage->numRows < 1)
		{
			return '';
		}

		$return = '';
		$intSpacing = 20;
		$childs = array();

		// Check whether there are child records
		if (!$blnNoRecursion)
		{
			$objNodes = $this->Database->prepare("SELECT id FROM ".$this->strSource." WHERE pid=? ORDER BY sorting")
									   ->execute($id);

			if ($objNodes->numRows)
			{
				$childs = $objNodes->fetchEach('id');
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
			$return .= '<a href="'.$this->addToUrl($flag.'tg='.$id).'" title="'.specialchars($alt).'" onclick="return AjaxRequest.toggleTableTree(this,\''.$xtnode.'_'.$id.'\',\''.$this->strField.'\',\''.$this->strName.'\',\''.$this->strSource.'\','.$level.')">'.\Image::getHtml($img, '', 'style="margin-right:2px"').'</a>';
		}

		// Set the protection status
		$objPage->protected = ($objPage->protected || $protectedPage);

		// Add the current page
		if (!empty($childs))
		{
			$return .= \Image::getHtml($this->getPageStatusIcon($objPage), '', $folderAttribute).' <a href="' . $this->addToUrl('node='.$objPage->id) . '" title="'.specialchars($objPage->title . ' (' . $objPage->alias . $GLOBALS['TL_CONFIG']['urlSuffix'] . ')').'">'.(($objPage->type == 'root') ? '<strong>' : '').$objPage->title.(($objPage->type == 'root') ? '</strong>' : '').'</a></div> <div class="tl_right">';
		}
		else
		{
			$return .= \Image::getHtml($this->getPageStatusIcon($objPage), '', $folderAttribute).' '.(($objPage->type == 'root') ? '<strong>' : '').$objPage->title.(($objPage->type == 'root') ? '</strong>' : '').'</div> <div class="tl_right">';
		}

		// set fieldtype to checkbox if field is multiple
		if($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple'])
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
		if (!empty($childs) && ($blnIsOpen || $this->Session->get('pct_tabletree_selector_search') != ''))
		{
			$return .= '<li class="parent" id="'.$node.'_'.$id.'"><ul class="level_'.$level.'">';

			for ($k=0, $c=count($childs); $k<$c; $k++)
			{
				$return .= $this->renderTree($childs[$k], ($intMargin + $intSpacing), $objPage->protected);
			}

			$return .= '</ul></li>';
		}

		return $return;
	}


	/**
	 * Get the IDs of all parent pages of the selected pages, so they are expanded automatically
	 * @return array
	 */
	protected function getPathNodes()
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
