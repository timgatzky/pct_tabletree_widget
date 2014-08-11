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
	 * Source table
	 * @var string
	 */
	protected $strSource = '';


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
		
		if($arrAttributes['fieldType'] == 'checkbox' || $arrAttributes['multiple'] == true)
		{
			$this->blnIsMultiple = true;
		}
		
		$this->strSource = 'tl_pct_customelement_tags';
	}


	/**
	 * Return an array if the "multiple" attribute is set
	 * @param mixed
	 * @return mixed
	 */
	protected function validator($varInput)
	{
	   if ($varInput == '')
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
		$blnHasOrder = ($this->strOrderField != '' && is_array($this->{$this->strOrderField}));

		if (!empty($this->varValue)) // Can be an array
		{
			if(!is_array($this->varValue))
			{
				$this->varValue = array($this->varValue);
			}
			
			$objRows = \Database::getInstance()->execute("SELECT * FROM ".$this->strSource." WHERE id IN(".implode(',',$this->varValue).")");
			
			if ($objRows !== null)
			{
				while ($objRows->next())
				{
					$arrSet[] = $objRows->id;
					$arrValues[$objRows->id] = $objRows->title . ' (' . $objRows->id . ')';
				}
			}

			// Apply a custom sort order
			#if ($blnHasOrder)
			#{
			#	$arrNew = array();
			#
			#	foreach ($this->{$this->strOrderField} as $i)
			#	{
			#		if (isset($arrValues[$i]))
			#		{
			#			$arrNew[$i] = $arrValues[$i];
			#			unset($arrValues[$i]);
			#		}
			#	}
			#
			#	if (!empty($arrValues))
			#	{
			#		foreach ($arrValues as $k=>$v)
			#		{
			#			$arrNew[$k] = $v;
			#		}
			#	}
			#
			#	$arrValues = $arrNew;
			#	unset($arrNew);
			#}
		}

		$return = '<input type="hidden" name="'.$this->strName.'" id="ctrl_'.$this->strId.'" value="'.implode(',', $arrSet).'">' . ($blnHasOrder ? '
  <input type="hidden" name="'.$this->strOrderName.'" id="ctrl_'.$this->strOrderId.'" value="'.$this->{$this->strOrderField}.'">' : '') . '
  <div class="selector_container">' . (($blnHasOrder && count($arrValues) > 1) ? '
    <p class="sort_hint">' . $GLOBALS['TL_LANG']['MSC']['dragItemsHint'] . '</p>' : '') . '
    <ul id="sort_'.$this->strId.'" class="'.($blnHasOrder ? 'sortable' : '').'">';

		foreach ($arrValues as $k=>$v)
		{
			$return .= '<li data-id="'.$k.'">'.$v.'</li>';
		}
		
		$return .= '</ul>
    <p><a href="'.PCT_CUSTOMELEMENTS_TAGS_PATH.'/PCT/Widgets/TableTree/assets/html/PageTableTree.php?do='.\Input::get('do').'&amp;table='.$this->strTable.'&amp;field='.$this->strField.'&amp;source='.$this->strSource.'&amp;act=show&amp;id='.$this->activeRecord->id.'&amp;value='.implode(',', $arrSet).'&amp;rt='.REQUEST_TOKEN.'" class="tl_submit" onclick="Backend.getScrollOffset();Backend.openModalTabletreeSelector({\'width\':765,\'title\':\''.specialchars($GLOBALS['TL_LANG']['MSC']['pct_tablepicker']).'\',\'url\':this.href,\'id\':\''.$this->strId.'\',\'source\':\''.$this->strSource.'\'});return false">'.$GLOBALS['TL_LANG']['MSC']['changeSelection'].'</a></p>' . ($blnHasOrder ? '
    <script>Backend.makeMultiSrcSortable("sort_'.$this->strId.'", "ctrl_'.$this->strOrderId.'")</script>' : '') . '
  </div>';

		if (!\Environment::get('isAjaxRequest'))
		{
			$return = '<div>' . $return . '</div>';
		}

		return $return;
	}
}
