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

use Contao\StringUtil;
use Contao\Controller;
use Contao\Input;
use Contao\System;

/**
 * Class file
 * WidgetTableTree
 * Render the TableTree widget with its button
 */
class WidgetTableTree extends \Contao\Widget
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
		
		$objSession = \Contao\System::getContainer()->get('request_stack')->getSession();
		
		// load js
		$GLOBALS['TL_JAVASCRIPT'][] = PCT_TABLETREE_PATH.'/assets/js/tabletree.js';
		
		if( (isset($arrAttributes['fieldType']) && $arrAttributes['fieldType'] == 'checkbox') || (isset($arrAttributes['multiple']) && $arrAttributes['multiple'] == true) || (isset($arrAttributes['eval']['fieldType']) && $arrAttributes['eval']['fieldType'] == 'checkbox') || (isset($arrAttributes['eval']['multiple']) && $arrAttributes['eval']['multiple'] == true))
		{
			$this->blnIsMultiple = true;
		}
		
		// get field defintion from datacontainer since contao does not pass custom evalulation arrays to widgets
		if( !isset($arrAttributes['tabletree']) || !is_array($arrAttributes['tabletree']))
		{
			$this->loadDataContainer($this->strTable);
			$arrAttributes = array_merge($arrAttributes, $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]);
		}
		
		$this->strSource = $arrAttributes['tabletree']['source'];
		$this->strValueField = $arrAttributes['tabletree']['valueField'] ?? 'id';
		$this->strKeyField = $arrAttributes['tabletree']['keyField'] ?? 'id';
		$this->strOrderField = $arrAttributes['tabletree']['orderField'] ?? '';
		$this->strRootField = $arrAttributes['tabletree']['rootsField'] ?? '';
		$this->strConditionsField = $arrAttributes['tabletree']['conditionsField'] ?? '';
		$this->strConditions = $arrAttributes['tabletree']['conditions'] ?? '';
		
		if( isset($arrAttributes['tabletree']['translationField']) && strlen($arrAttributes['tabletree']['translationField']) > 0)
		{
			$this->strTranslationField = $arrAttributes['tabletree']['translationField'];
		}
		
		// flag as sortable
		if( (isset($arrAttributes['sortable']) && $arrAttributes['sortable']) || isset($arrAttributes['eval']['isSortable']) || (isset($arrAttributes['eval']['sortable']) && $arrAttributes['eval']['sortable']) )
		{
			$this->blnIsSortable = true;
			$this->strOrderSRC = strlen($arrAttributes['eval']['orderField']) > 0 ? $arrAttributes['eval']['orderField']: 'orderSRC_'.$this->strName;
			$this->strOrderSRCId = $this->strOrderSRC;
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
		
		// store the conditions in the session
		$arrSession = $objSession->get('pct_tabletree_conditions');
		$arrSession[$this->name] = $this->strConditions;
		$objSession->set('pct_tabletree_conditions',$arrSession);
		
		$this->strRoots = implode(',',$roots);
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
			$arrNew = \Contao\Input::post($this->strOrderSRC);
			if( isset($this->activeRecord->{$this->strOrderSRC}) && \Contao\Database::getInstance()->fieldExists($this->strOrderSRC,$this->strTable) )
			{
				// Only proceed if the value has changed
				if ($arrNew !== \Contao\StringUtil::deserialize($this->activeRecord->{$this->strOrderSRC}) )
				{
					if($this->blnIsMultiple)
					{
						$arrNew =  explode(',', $arrNew);
					}
					
					\Contao\Database::getInstance()->prepare("UPDATE ".$this->strTable." %s WHERE id=?")->set( array('tstamp'=>time(),$this->strOrderSRC=>$arrNew) )->execute($this->activeRecord->id);
					$this->objDca->createNewVersion = true; // see #6285
				}
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
		$strOrderField = $this->strOrderField;
		$strOrderSRC = $this->strOrderSRC;
		
		if(!empty($this->varValue)) // Can be an array
		{
			if(!is_array($this->varValue))
			{
				$this->varValue = array($this->varValue);
			}
			
			$objRows = \Contao\Database::getInstance()->execute("SELECT * FROM ".$this->strSource." WHERE ".($this->strConditions ? $this->strConditions ." AND " : " ")." ".\Contao\Database::getInstance()->findInSet($strKeyField,$this->varValue));
			
			if ($objRows->numRows > 0)
			{
				while ($objRows->next())
				{
					$arrRawValues[] = $objRows->{$strKeyField};
					
					// translate
					$strLabel = $objRows->$strValueField;
					if(strlen($objRows->{$strTanslationField}) > 0)
					{
						$arrTranslations = \Contao\StringUtil::deserialize($objRows->{$strTanslationField});
						$lang = \Contao\Input::get('language') ?: \Contao\Input::get('lang') ?: $GLOBALS['TL_LANGUAGE'];
						if( isset($arrTranslations[$lang]['label']) && !empty($arrTranslations[$lang]['label']) )
						{
							$strLabel = $arrTranslations[$lang]['label'];
						}

					}
							
					$arrValues[$objRows->{$strKeyField}] = $strLabel . ' (' . $objRows->{$strKeyField} . ')';
				}
			}
			
			// Custom order
			if($this->blnIsSortable)
			{	
				// Apply a custom sort by real dca order field like orderSRC
					
				if(strlen($strOrderSRC) > 0)
				{
					$arrNew = array();
					$varValues = \Contao\StringUtil::deserialize($this->activeRecord->{$strOrderSRC});
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
					$arrValues = $tmp;
					unset($tmp);
				}
			}
		}
		
		$strToken = \Contao\System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();

		$intId = \Contao\Input::get('id');
		if( isset($this->activeRecord->id) )
		{
			$this->activeRecord->id = 0;
		}
		
		$return = '<input type="hidden" name="'.$this->strName.'" id="ctrl_'.$this->strId.'" value="'.implode(',', $arrRawValues).'">' . ($this->blnIsSortable ? '
  <input type="hidden" name="'.$this->strOrderSRC.'" id="ctrl_'.$this->strOrderSRCId.'" value="'.$this->{$this->strOrderSRC}.'">' : '') . '
  <div class="selector_container">' . (($this->blnIsSortable && count($arrValues) > 1) ? '
    <p class="sort_hint">' . $GLOBALS['TL_LANG']['MSC']['dragItemsHint'] . '</p>' : '') . '
    <ul id="sort_'.$this->strId.'" class="'.($this->blnIsSortable ? 'sortable' : '').'">';

		foreach ($arrValues as $k=>$v)
		{
			$return .= '<li data-id="'.$k.'">'.$v.'</li>';
		}
		#Backend.openModalTabletreeSelector({\'width\':765,\'title\':\''.\Contao\StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pct_tablepicker']).'\',\'url\':this.href,\'id\':\''.$this->strId.'\',\'source\':\''.$this->strSource.'\',\'table\':\''.$this->strTable.'\',\'valueField\':\''.$this->strValueField.'\',\'keyField\':\''.$this->strKeyField.'\'
		#,\'translationField\':\''.$this->strTranslationField.'\',\'rootsField\':\''.$this->strRootField.'\',\'roots\':\''.$this->strRoots.'\'
		#,\'conditions\':\''.$this->strConditions.'\',\'conditionsField\':\''.$this->strConditionsField.'\'})
		$inputName = $this->strField;
		$params = array
		(
			'key' => 'tabletree',
			'do' => 'pct_customelements_tags',
			'table' => $this->strTable,
			'field' => $this->strId,
			'name' => $this->strField,
			'source' => $this->strSource,
			'valueField' => $this->strValueField,
			'keyField' => $this->strKeyField,
			'orderField' => $this->strOrderField,
			'rootsField'=> $this->strRootField,
			'roots' => $this->strRoots,
			'translationField' => $this->strTranslationField,
			'conditionsField' => $this->strConditionsField
		);
		$href = Controller::addToUrl( \http_build_query($params),true,array('act') );
		$link = $GLOBALS['TL_LANG']['MSC']['changeSelection'];
		
		$return .= '</ul>';
		$return .= '<p><a class="tl_submit" href="' . StringUtil::ampersand($href) . '" title="' . StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pct_tableTreeTitle']) . '" id="pp_' . $inputName . '">' . $link . '</a><p>
		<script>
			$("pp_' . $inputName . '").addEvent("click", function(e) {
			e.preventDefault();
			Backend.openModalTabletreeSelector({
				"id": "'.$this->strField.'",
				"title": ' .json_encode($GLOBALS['TL_LANG']['MSC']['pct_tableTreeTitle']) . ',
				"url": this.href + "&value=" + document.getElementById("ctrl_' . $inputName . '").value,
				"source":"'.$this->strSource.'",
				"table":"'.$this->strTable.'",
				"valueField":"'.$this->strValueField.'",
				"keyField":"'.$this->strKeyField.'",
				"translationField":"'.$this->strTranslationField.'",
				"rootsField":"'.$this->strRootField.'",
				"roots":"'.$this->strRoots.'",
				"conditions":"'.$this->strConditions.'",
				"conditionsField":"'.$this->strConditionsField.'",
				"callback": function(picker, value) 
				{
					$("ctrl_' . $inputName . '").value = value.join(",");
					$("ctrl_' . $inputName . '").fireEvent("change");
				}.bind(this)
			});
			});
		</script>';
		$return .= '</div>';

		if (!\Contao\Environment::get('isAjaxRequest'))
		{
			$return = '<div>' . $return . '</div>';
		}
		
		return $return;
	}
}
