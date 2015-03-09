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


/**
 * Class file
 * WidgetTableTree
 * Render the TableTree widget with its button
 */
class WidgetTableTree extends \Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

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
	 * Sortable flag
	 * @var boolean
	 */
	protected $blnIsSortable = false;
	
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
		
		// load js
		$GLOBALS['TL_JAVASCRIPT'][] = PCT_TABLETREE_PATH.'/assets/js/tabletree.js';
		
		if($arrAttributes['fieldType'] == 'checkbox' || $arrAttributes['multiple'] == true || $arrAttributes['eval']['fieldType'] == 'checkbox' || $arrAttributes['eval']['multiple'] == true)
		{
			$this->blnIsMultiple = true;
		}
		
		// get field defintion from datacontainer since contao does not pass custom evalulation arrays to widgets
		if(!is_array($arrAttributes['tabletree']))
		{
			$this->loadDataContainer($this->strTable);
			$arrAttributes = array_merge($arrAttributes, $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]);
		}
		
		$this->strSource = $arrAttributes['tabletree']['source'];
		$this->strValueField = strlen($arrAttributes['tabletree']['valueField']) > 0 ? $arrAttributes['tabletree']['valueField'] : 'id';
		$this->strKeyField = strlen($arrAttributes['tabletree']['keyField']) > 0 ? $arrAttributes['tabletree']['keyField'] : 'id';
		$this->strOrderField = $arrAttributes['tabletree']['orderField'];
		
		// flag as sortable
		if($arrAttributes['sortable'] || $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['orderField'])
		{
			$this->blnIsSortable = true;
		}
			
		// store root nodes in session
		if(isset($arrAttributes['tabletree']['roots']))
		{
			if(is_array($arrAttributes['tabletree']['roots']))
			{
				$roots = $arrAttributes['tabletree']['roots'];
			}
			else
			{
				$roots = explode(',', $arrAttributes['tabletree']['roots']);
			}
		}
		\Session::getInstance()->set('pct_tabletree_roots',array($this->name => $roots));
	}


	/**
	 * Return an array if the "multiple" attribute is set
	 * @param mixed
	 * @return mixed
	 */
	protected function validator($varInput)
	{
		if (empty($varInput))
		{
			if ($this->mandatory)
			{
				$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
			}
			return '';
		}
		elseif (strpos($varInput, ',') === false)
		{
			return $this->blnIsMultiple ? array(intval($varInput)) : intval($varInput);
		}
		else
		{
			$arrValue = array_map('intval', array_filter(explode(',', $varInput)));
			return $this->blnIsMultiple ? $arrValue : $arrValue[0];
		}
	}


	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		$arrSet = array();
		$arrValues = array();
		$strKeyField = $this->strKeyField;
		$strValueField = $this->strValueField;
		if (!empty($this->varValue)) // Can be an array
		{
			if(!is_array($this->varValue))
			{
				$this->varValue = array($this->varValue);
			}
			
			$objRows = \Database::getInstance()->execute("SELECT * FROM ".$this->strSource." WHERE id IN(".implode(',',$this->varValue).")");
			
			if ($objRows->numRows > 0)
			{
				while ($objRows->next())
				{
					$arrSet[] = $objRows->id;
					$arrValues[$objRows->id] = $objRows->$strValueField . ' (' . $objRows->id . ')';
				}
			}
			
			// Custom order
			if($this->blnIsSortable)
			{	
				// Apply a custom sort by real dca order field like orderSRC
				if($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['orderField'])
				{
					$strOrderField = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['orderField'];
					$arrNew = array();
					foreach ($this->$strOrderField as $i)
					{
						if (isset($arrValues[$i]))
						{
							$arrNew[$i] = $arrValues[$i];
							unset($arrValues[$i]);
						}
					}
	
					if (!empty($arrValues))
					{
						foreach ($arrValues as $k=>$v)
						{
							$arrNew[$k] = $v;
						}
					}
	
					$arrValues = $arrNew;
					unset($arrNew);
				}
				else
				{
					// Apply a custom sort order by stored or submitted value order
					$tmp = array();
					foreach($this->varValue as $i => $id)
					{
						$tmp[$id] = $arrValues[$id];
					}
					$arrValues = $tmp;
					unset($tmp);
				}
			}
		}

		$return = '<input type="hidden" name="'.$this->strName.'" id="ctrl_'.$this->strId.'" value="'.implode(',', $arrSet).'">' . ($this->blnIsSortable ? '
  <input type="hidden" name="'.$this->strOrderName.'" id="ctrl_'.$this->strOrderId.'" value="'.$this->{$this->strOrderField}.'">' : '') . '
  <div class="selector_container">' . (($this->blnIsSortable && count($arrValues) > 1) ? '
    <p class="sort_hint">' . $GLOBALS['TL_LANG']['MSC']['dragItemsHint'] . '</p>' : '') . '
    <ul id="sort_'.$this->strId.'" class="'.($this->blnIsSortable ? 'sortable' : '').'">';

		foreach ($arrValues as $k=>$v)
		{
			$return .= '<li data-id="'.$k.'">'.$v.'</li>';
		}
		
		$return .= '</ul>
    <p><a href="'.PCT_TABLETREE_PATH.'/assets/html/PageTableTree.php?do='.\Input::get('do').'&amp;table='.$this->strTable.'&amp;field='.$this->strField.'&amp;source='.$this->strSource.'&amp;valueField='.$this->strValueField.'&amp;keyField='.$this->strKeyField.'&amp;orderField='.$this->strOrderField.'&amp;act=show&amp;id='.$this->activeRecord->id.'&amp;value='.implode(',', $arrSet).'&amp;rt='.REQUEST_TOKEN.'" class="tl_submit" onclick="Backend.getScrollOffset();Backend.openModalTabletreeSelector({\'width\':765,\'title\':\''.specialchars($GLOBALS['TL_LANG']['MSC']['pct_tablepicker']).'\',\'url\':this.href,\'id\':\''.$this->strId.'\',\'source\':\''.$this->strSource.'\',\'valueField\':\''.$this->strValueField.'\',\'keyField\':\''.$this->strKeyField.'\'});return false">'.$GLOBALS['TL_LANG']['MSC']['changeSelection'].'</a></p>' . 
    ($blnHasOrder ? '<script>Backend.makeMultiSrcSortable("sort_'.$this->strId.'", "ctrl_'.$this->strOrderId.'")</script>' : '') . '
  
  </div>';

		if (!\Environment::get('isAjaxRequest'))
		{
			$return = '<div>' . $return . '</div>';
		}
		
		return $return;
	}
}
