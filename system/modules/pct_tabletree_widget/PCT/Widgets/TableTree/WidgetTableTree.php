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
		$objSession = \Session::getInstance();
		
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
		$this->strRootField = strlen($arrAttributes['tabletree']['rootsField']) > 0 ? $arrAttributes['tabletree']['rootsField'] : 'rootNodes';
		
		if(strlen($arrAttributes['tabletree']['translationField']) > 0)
		{
			$this->strTranslationField = $arrAttributes['tabletree']['translationField'];
		}
		
		// flag as sortable
		if($arrAttributes['sortable'] || $arrAttributes['eval']['isSortable'])
		{
			$this->blnIsSortable = true;
			$this->strOrderName = 'orderSRC_'.$this->strName;
			$this->strOrderId = 'orderSRC_'.$this->strName;
			$this->strOrderField = 'orderSRC_'.$this->strName;
		}
		
		// store root nodes in session
		$roots = array();
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
 		$arrSession = $objSession->get('pct_tabletree_roots');
		$arrSession[$this->name] = $roots;
		$objSession->set('pct_tabletree_roots',$arrSession);
	}


	/**
	 * Return an array if the "multiple" attribute is set
	 * @param mixed
	 * @return mixed
	 */
	protected function validator($varInput)
	{
		// Store the order value
		if ($this->blnIsSortable)
		{
			$arrNew = \Input::post($this->strOrderName);

			// Only proceed if the value has changed
			if ($arrNew !== deserialize($this->activeRecord->{$this->strOrderName}))
			{
				\Database::getInstance()->prepare("UPDATE ".$this->strTable." %s WHERE id=?")->set( array('tstamp'=>time(),$this->strOrderName=>$arrNew) )->execute($this->activeRecord->id);
				$this->objDca->createNewVersion = true; // see #6285
			}
		}
		
		if (empty($varInput))
		{
			if ($this->mandatory)
			{
				$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
			}
			return '';
		}
		else
		{
			if($this->blnIsMultiple)
			{
				return explode(',', $varInput);
			}
			
			
			return $varInput;
		}
	}


	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		$arrRawValues = array();
		$arrValues = array();
		$strKeyField = $this->strKeyField;
		$strValueField = $this->strValueField;
		$strTanslationField = $this->strTranslationField;
		
		if(!empty($this->varValue)) // Can be an array
		{
			if(!is_array($this->varValue))
			{
				$this->varValue = array($this->varValue);
			}
			
			$objRows = \Database::getInstance()->execute("SELECT * FROM ".$this->strSource." WHERE ".\Database::getInstance()->findInSet($strKeyField,$this->varValue));
			
			if ($objRows->numRows > 0)
			{
				while ($objRows->next())
				{
					$arrRawValues[] = $objRows->{$strKeyField};
					
					// translate
					$strLabel = $objRows->$strValueField;
					if(strlen($objRows->{$strTanslationField}) > 0)
					{
						$arrTranslations = deserialize($objRows->{$strTanslationField});
						$lang = \Input::get('language') ?: \Input::get('lang') ?: $GLOBALS['TL_LANGUAGE'];
						$strLabel = $arrTranslations[$lang]['label'] ?: $strLabel;
					}
							
					$arrValues[$objRows->{$strKeyField}] = $strLabel . ' (' . $objRows->{$strKeyField} . ')';
				}
			}
			
			// Custom order
			if($this->blnIsSortable)
			{	
				// Apply a custom sort by real dca order field like orderSRC
				$strOrderField = 'orderSRC_'.$this->strName;
					
				if(strlen($strOrderField) > 0)
				{
					$arrNew = array();
					$varValues = deserialize($this->activeRecord->{$strOrderField});
					if(!is_array($varValues))
					{
						$varValues = explode(',', $varValues);
					}
					foreach ($varValues as $i)
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
					$arrSet = $tmp;
					unset($tmp);
				}
			}
		}
		
		$intId = $this->activeRecord->id ?: \Input::get('id');
	
		$return = '<input type="hidden" name="'.$this->strName.'" id="ctrl_'.$this->strId.'" value="'.implode(',', $arrRawValues).'">' . ($this->blnIsSortable ? '
  <input type="hidden" name="'.$this->strOrderName.'" id="ctrl_'.$this->strOrderId.'" value="'.$this->{$this->strOrderField}.'">' : '') . '
  <div class="selector_container">' . (($this->blnIsSortable && count($arrValues) > 1) ? '
    <p class="sort_hint">' . $GLOBALS['TL_LANG']['MSC']['dragItemsHint'] . '</p>' : '') . '
    <ul id="sort_'.$this->strId.'" class="'.($this->blnIsSortable ? 'sortable' : '').'">';

		foreach ($arrValues as $k=>$v)
		{
			$return .= '<li data-id="'.$k.'">'.$v.'</li>';
		}
		
		$return .= '</ul>
    <p><a href="'.PCT_TABLETREE_PATH.'/assets/html/PageTableTree.php?do='.\Input::get('do').'&amp;table='.$this->strTable.'&amp;field='.$this->strField.'&amp;source='.$this->strSource.'&amp;valueField='.$this->strValueField.'&amp;keyField='.$this->strKeyField.'&amp;orderField='.$this->strOrderField.'&amp;rootsField='.$this->strRootField.'&amp;translationField='.$this->strTranslationField.'&amp;act=show&amp;id='.$intId.'&amp;value='.implode(',', $arrRawValues).'&amp;rt='.REQUEST_TOKEN.'" class="tl_submit" onclick="Backend.getScrollOffset();Backend.openModalTabletreeSelector({\'width\':765,\'title\':\''.specialchars($GLOBALS['TL_LANG']['MSC']['pct_tablepicker']).'\',\'url\':this.href,\'id\':\''.$this->strId.'\',\'source\':\''.$this->strSource.'\',\'valueField\':\''.$this->strValueField.'\',\'keyField\':\''.$this->strKeyField.'\',\'translationField\':\''.$this->strTranslationField.'\'});return false">'.$GLOBALS['TL_LANG']['MSC']['changeSelection'].'</a></p>' . 
    ($this->blnIsSortable ? '<script>Backend.makeMultiSrcSortable("sort_'.$this->strId.'", "ctrl_'.$this->strOrderId.'")</script>' : '') . '
  
  </div>';

		if (!\Environment::get('isAjaxRequest'))
		{
			$return = '<div>' . $return . '</div>';
		}
		
		return $return;
	}
}
