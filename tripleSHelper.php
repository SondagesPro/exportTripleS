<?php
/**
 * exportTripleS Plugin for LimeSurvey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @license GPL v3
 * @version 0.9
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */
 class tripleSHelper 
{
    /* The plugin settings ?*/
    public $pluginSettings;
    public $iSurveyId = 0;
    public $sLanguageCode;

    private $ident=1;

    /*return functionName to call for fieldtype : syntax and data */
    public $aTypeFunction=array(
        'id'=>'getSrid',
        'startdate'=>'getDateTime',
        'submitdate'=>'getDateTime',
        'datestamp'=>'getDateTime',
        'lastpage'=>'getLastPage',
        'startlanguage'=>'getLanguage',
        'url'=>'getString',
        'X'=>'getNone', //'boilerplate'
        '5'=>'getList5', //'choice-5-pt-radio'
        'D'=>'getDateTime', //'date';        //  DATE
        'Z'=>'getListAnswers', //'list-radio-flexible'; //  LIST Flexible radio-button
        'L'=>'getListAnswers', //'list-radio';      //  LIST radio-button
        'W'=>'getListAnswers', //'list-dropdown-flexible'; //   LIST drop-down (flexible label)
        '!'=>'getListAnswers', //'list-dropdown';   //  List - dropdown
        'O'=>'getListAnswers', //'list-with-comment';   //  LIST radio-button + textarea
        'R'=>'getListAnswers', //'ranking';     //  RANKING STYLE
        'M'=>'getMultiple', //'multiple-opt';    //  Multiple choice checkbox
        'I'=>'getLanguage', //'language';        //  Language Question
        'P'=>'getMultiple', //'multiple-opt-comments'; //    Multiple choice with comments checkbox + text
        'Q'=>'getString', //'multiple-short-txt';  //  TEXT
        'K'=>'getDecimal', //'numeric-multi';   //  MULTIPLE NUMERICAL QUESTION
        'N'=>'getDecimal', //'numeric';     //  NUMERICAL QUESTION TYPE
        'S'=>'getString', //'text-short';      //  SHORT FREE TEXT
        'T'=>'getString', //'text-long';       //  LONG FREE TEXT
        'U'=>'getString', //'text-huge';       //  HUGE FREE TEXT
        'Y'=>'getListYN', //'yes-no';      //  YES/NO radio-buttons
        'G'=>'getListGender', //'gender';      //  GENDER drop-down list
        'A'=>'getList5', //'array-5-pt';      //  ARRAY (5 POINT CHOICE) radio-buttons
        'B'=>'getList10', //'array-10-pt';     //  ARRAY (10 POINT CHOICE) radio-buttons
        'C'=>'getListYUN', //'array-yes-uncertain-no'; //   ARRAY (YES/UNCERTAIN/NO) radio-buttons
        'E'=>'getListISD', //'array-increase-same-decrease'; // ARRAY (Increase/Same/Decrease) radio-buttons
        'F'=>'getListAnswers', //'array-flexible-row';  //  ARRAY (Flexible) - Row Format
        'H'=>'getListAnswers', //'array-flexible-column'; //    ARRAY (Flexible) - Column Format
        ':'=>'getArrayNumbers', //'array-multi-flexi';   //  ARRAY (Multi Flexi) 1 to 10
        ";"=>'getString', //'array-multi-flexi-text';
        "1"=>'getListAnswers', //'array-flexible-duel-scale'; //    Array dual scale
        "*"=>'getString', //'equation';    // Equation
    );


    function __construct($settings)
    {
        foreach($settings as $name => $value)
            $this->pluginSettings[$name]=$value;
    }
    public function getColumnInfo()
    {
        return $this->aColumnInfo;
    }
    public function createTripleSFieldmap($oSurvey, $sLanguage, $oOptions)
    {
        $aExistingKey=array();
        $aTripleSField=array();
        $aFieldmap['questions'] = array_intersect_key($oSurvey->fieldMap, array_flip($oOptions->selectedColumns));
        foreach($aFieldmap['questions'] as $aField)
        {
            if($aTripleSarray=$this->getTripleSarray($oSurvey,$aField))
                $aTripleSField[$aField['fieldname']]=$aTripleSarray;
        }
        $aFieldmap['tokenFields'] = array_intersect_key($oSurvey->tokenFields, array_flip($oOptions->selectedColumns));
#        foreach($aFieldmap['tokenFields'] as $aField)
#        {
#            $sName='token_';
#            $aTripleSField[$sName]=array(
#                'type'=>'t_'.,
#                'qid'=>$aField['qid'],
#                'text'=>$aField['question'],
#            );
#        }
        return $aTripleSField;
    }
    
    /*
     * Return the unique name for the triple-S format
     * 
     * @param object $oSurvey the survey
     * @param array $aField the field in LimeSurvey format
     * 
     * @return string final name
     */
    private function getName($oSurvey,$aField)
    {
        static $aExistingKey=array();
        $sName=viewHelper::getFieldCode($oSurvey->fieldMap[$aField['fieldname']],array('LEMcompat'=>true));
        if (function_exists('iconv'))
        {
            $sName=iconv('UTF-8', 'ASCII//TRANSLIT', $sName);
        }
        $sName=preg_replace('/[^_a-zA-Z0-9]/','', $sName);
        
        if(!ctype_alpha($sName[0])){
            $sName="Q.".$sName;
        }

        if(isset($aExistingKey[$sName]))
        {
            $aExistingKey[$sName]++;
            $sName="{$sName}.{$aExistingKey[$sName]}";
        }
        else
        {
            $aExistingKey[$sName]=0;
        }
        return $sName;
    }
    private function getTripleSarray($oSurvey,$aField)
    {
        $sName=$this->getName($oSurvey,$aField);
        $aDefaultTripleSArray=array(
            '@attributes' => array(
                'ident'=>0,// Set but not up : just for the position
            ),
            'name' => $sName,
            'label' => viewHelper::getFieldText($oSurvey->fieldMap[$aField['fieldname']],array('flat'=>true)),
        );
        if(intval($this->pluginSettings['debugMode'])>=2)
        {
            $aDefaultTripleSArray['lscolumn']=$aField['fieldname'];
            $aDefaultTripleSArray['lstype']=$aField['type'];
        }
        if(array_key_exists($aField['type'],$this->aTypeFunction))
        {
            $function = $this->aTypeFunction[$aField['type']]."Syntax";
            $aTripleSdefinitions=$this->$function($aField,$sName);
            if(isset($aTripleSdefinitions['datasize']))
                $aTripleSdefinitions=array($aTripleSdefinitions);

        }
        elseif(intval($this->pluginSettings['debugMode']))
        {
            $aTripleSdefinitions=$this->todoSyntax($aField,$sName);
            if(isset($aTripleSdefinitions['datasize']))
                $aTripleSdefinitions=array($aTripleSdefinitions);
        }
        if(!empty($aTripleSdefinitions))
        {
            foreach($aTripleSdefinitions as $aTripleSdefinition)
            {
                $iSize=$aTripleSdefinition['datasize'];
                unset($aTripleSdefinition['datasize']);
                $aBase=array(
                    '@attributes' => array(
                        'ident'=>$this->ident++,
                    ),
                    'position'=>array(
                        '@attributes'=>array(
                            'start'=>++$this->position,
                            'finish'=>$this->position+=($iSize-1),
                        ),
                    ),
                );
                $aTriplesArrays[]=array_replace_recursive($aDefaultTripleSArray,$aBase,$aTripleSdefinition);
            }
            return $aTriplesArrays;
        }
        //~ if(!empty($aTripleSArrays))
        //~ {
            //~ $aTriplesFinalArrays=array();
            //~ foreach($aTripleSArrays as $aTriplesArray)
            //~ {
                //~ $aTriplesArrays[]=array_replace_recursive($aDefaultTripleSArray,$aTriplesArray);
            //~ }
            //~ return $aTriplesArrays;
        //~ }
        elseif($this->pluginSettings['debugMode']>1)
        {
            $aDebugInfo=array(
                'field'=>$aField
            );
            $aField['type']=isset($aField['type']) ? $aField['type'] : "Unknow";
            $aDebugArray= array(
                    '@attributes' => array(
                        'ident'=>$this->ident++,
                        'type'=>'character',
                    ),
                    'name' => "Debug.{$aField['type']}.".$this->getName($oSurvey,$aField),
                    'position'=>array(
                        '@attributes'=>array(
                            'start'=>++$this->position,
                            'finish'=>$this->position+=1,
                        ),
                    ),
                    'size'=>1,
            );
            return array(array_replace_recursive($aDefaultTripleSArray,$aDebugArray));

            //return array(array_replace_recursive($aDefaultTripleSArray,$aDebugInfo)); // Debugging purpose
        }
    }

    public function todoSyntax($aField)
    {
        if($this->pluginSettings['debugMode'])
        {
            return array(
                'datasize'=>1,
                '@attributes'=>array(
                    'type'=>'character',
                ),
                'name' => "Todo.{$aField['type']}.{$aField['fieldname']}",
            );
        }
    }

    /* getNone : don't export */
    public function getNoneSyntax($aField)
    {

    }

    /* getSrid : the response id */
    public function getSridSyntax($aField)
    {
        return array(
            'datasize'=>10,
            '@attributes'=>array(
                'type'=>'quantity',
                'use'=>'serial',
            ),
            'values'=>array(
                'range'=>array(
                    '@attributes'=>array(
                        'from'=>'0000000001',
                        'to'=>'2147483648',
                    ),
                )
            ),
        );
    }

    /* getMultiple : boolean value except for comment and other */
    public function getMultipleSyntax($aField)
    {
        if(mb_substr($aField['fieldname'], -5, 5) == 'other')
            return $this->getStringSyntax(array_merge($aField,array('type'=>'other')));
        if(mb_substr($aField['fieldname'], -7, 7) == 'comment')
            return $this->getStringSyntax(array_merge($aField,array('type'=>'comment')));
        return array(
            'datasize'=>1,
            '@attributes'=>array(
                'type'=>'logical',
            ),
        );
    }
    /* getRadio5 : 5 point radiog : integer 1 to 5 */
    public function getList5Syntax($aField)
    {
        $aValue=array();
        for ($iCount = 1; $iCount <= 5; $iCount++) {
            $aValue=array(
                '@value'=>$iCount,
                '@attributes'=>array(
                    'code'=>$iCount,
                ),
            );
        }
        return $this->getList($aValue,1);
    }

    /* getRadio5 : 10 point radio : integer 1 to 5 */
    public function getList10Syntax($aField)
    {
        $aValue=array();
        for ($iCount = 1; $iCount <= 10; $iCount++) {
            $aValue=array(
                '@value'=>$iCount,
                '@attributes'=>array(
                    'code'=>$iCount,
                ),
            );
        }
        return $this->getList($aValue,2);
    }
    /* getListYUN : 10 point radio : integer 1 to 5 */
    public function getListYUNSyntax($aField)
    {
        $aValue=array(
            array(
                '@value'=>$this->translate("Yes"),
                '@attributes'=>array(
                    'code'=>'Y',
                ),
            ),
            array(
                '@value'=>$this->translate("No"),
                '@attributes'=>array(
                    'code'=>'N',
                ),
            ),
            array(
                '@value'=>$this->translate("Uncertain"),
                '@attributes'=>array(
                    'code'=>'U',
                ),
            ),
        );
        return $this->getList($aValue,1);

    }
    /* getListISD : 10 point radio : integer 1 to 5 */
    public function getListISDSyntax($aField)
    {
        $aValue=array(
            array(
                '@value'=>$this->translate("Increase"),
                '@attributes'=>array(
                    'code'=>'I',
                ),
            ),
            array(
                '@value'=>$this->translate("Decrease"),
                '@attributes'=>array(
                    'code'=>'D',
                ),
            ),
            array(
                '@value'=>$this->translate("Same"),
                '@attributes'=>array(
                    'code'=>'S',
                ),
            ),
        );
        return $this->getList($aValue,1);

    }
    /* getListYN : Y / N */
    public function getListYNSyntax($aField)
    {
        $aValue=array(
            array(
                '@value'=>$this->translate("Yes"),
                '@attributes'=>array(
                    'code'=>'Y',
                ),
            ),
            array(
                '@value'=>$this->translate("No"),
                '@attributes'=>array(
                    'code'=>'N',
                ),
            ),
        );
        return $this->getList($aValue,1);
    }

    /* getListGender */
    public function getListGenderSyntax($aField)
    {
        $aValue=array(
            array(
                '@value'=>$this->translate("Male"),
                '@attributes'=>array(
                    'code'=>'M',
                ),
            ),
            array(
                '@value'=>$this->translate("Female"),
                '@attributes'=>array(
                    'code'=>'F',
                ),
            ),
        );

        return $this->getList($aValue,1);

    }

    /* getListAnswers : The code OR a string for comment or other */
    public function getListAnswersSyntax($aField)
    {

        if(mb_substr($aField['fieldname'], -7, 7) == 'comment')
            return $this->getStringSyntax(array_merge($aField,array('type'=>'comment')));
        if(mb_substr($aField['fieldname'], -5, 5) == 'other')
            return $this->getStringSyntax(array_merge($aField,array('type'=>'other')));
        /* Real list */
        /* Construct array value */
        if($aField['type']=="1")
            $scale=$aField['scale_id'];
        else
            $scale=0;

        $aoAnswers = Answer::model()->findAll('qid=:qid AND language=:language and scale_id=:scale', array(':qid'=>$aField['qid'],':language'=>$this->sLanguageCode,':scale'=>$scale));

        foreach($aoAnswers as $oAnswer)
        {
            $aValue[]=array(
                '@attributes'=>array(
                    'code'=>$oAnswer->code,
                ),
                '@value'=>self::filterTextForXML($oAnswer->answer),
            );
        }
        $oQuestion=Question::model()->find('qid=:qid AND language=:language', array(':qid'=>$aField['qid'],':language'=>$this->sLanguageCode));
        if($oQuestion->other=='Y')
        {
            $oQuestionOtherText=QuestionAttribute::model()->find('qid=:qid AND language=:language AND attribute =:attribute', array(':qid'=>$aField['qid'],':language'=>$this->sLanguageCode,':attribute'=>'other_replace_text'));
            if($oQuestionOtherText)
                $sOtherText=$oQuestionOtherText->value;
            else
                $sOtherText=$this->translate("Other");
            $aValue[]=array(
                '@attributes'=>array(
                    'code'=>"-oth-", // Validate if OK
                ),
                '@value'=>self::filterTextForXML($sOtherText),
            );
        }
        return $this->getList($aValue,5);
    }

    public function getList($aValues,$iSize=5)
    {
        if($this->pluginSettings['listChoiceNoANswer']!='' && $iSize>=strlen($this->pluginSettings['listChoiceNoANswer']))
        {
            $aValues[]=array(
                        '@value'=>$this->translate("No answer"),
                        '@attributes'=>array(
                            'code'=>substr($this->pluginSettings['listChoiceNoANswer'],0,$iSize),
                        ),
                    );
        }
        return array(
            'datasize'=>$iSize,
            '@attributes'=>array(
                'type'=>'single',
                'format'=>'literal',
            ),
            'values'=>array("value"=>$aValues),
        );
    }
    /* getDateTime : Date + time  in YmdHi format */
    public function getDateTimeSyntax($aField,$sName)
    {
        return array(
            array(
                'datasize'=>8,
                '@attributes'=>array(
                    'type'=>'date',
                ),
                'name' => $sName."_date",
            ),
            array(
                'datasize'=>4,
                '@attributes'=>array(
                    'type'=>'time',
                ),
                'name' => $sName."_time",
            ),
        );
    }

    /* getLastPage : integer max 999 */
    public function getLastPageSyntax($aField)
    {
        return array(
            'datasize'=>3,
            '@attributes'=>array(
                'type'=>'quantity',
            ),
            'values'=>array(
                'range'=>array(
                    '@attributes'=>array(
                        'from'=>'001',
                        'to'=>'999',
                    ),
                )
            ),
        );
    }

    /* getLanguage : the language code, can be more than 2 , then max to 20 */
    public function getLanguageSyntax($aField)
    {
        return array(
            'datasize'=>20,
            '@attributes'=>array(
                'type'=>'character',
            ),
            'size'=>20,
        );
    }

    /* getArrayNumbers : 3 solution : float/fxed list / boolean */
    public function getArrayNumbersSyntax($aField)
    {
        $aAttributes=QuestionAttribute::model()->getQuestionAttributes($aField['qid']);
        if($aAttributes['multiflexible_checkbox'])
        {
            return array(
                'datasize'=>1,
                '@attributes'=>array(
                    'type'=>'logical',
                ),
            );
        }
        if($aAttributes['input_boxes'])
        {
            return $this->getDecimalSyntax($aField);
        }
        $nMinValue=(is_numeric($aAttributes['multiflexible_min'])) ? $aAttributes['multiflexible_min'] : "1" ;
        $nStep=($aAttributes['multiflexible_step']) ? $aAttributes['multiflexible_step'] : "1" ;
        $nMaxValue=strval(is_numeric($aAttributes['multiflexible_max']) ? $aAttributes['multiflexible_max'] : $nMinValue+10);
        $iDecimals=max(strrpos($nMinValue,"."),strrpos($nStep,"."),strrpos($nMaxValue,"."));
        if($iDecimals)
        {
            $aMinValue=explode(".",$nMinValue);
            $decMinValue=isset($aMinValue[1]) ? $aMinValue[1] : "";
            $nMinValue=$aMinValue[0].".".str_pad ($decMinValue,$iDecimals,"0");
            $aMaxValue=explode(".",$nMaxValue);
            $decMaxValue=isset($aMaxValue[1]) ? $aMaxValue[1] : "";
            $nMaxValue=$aMaxValue[0].".".str_pad ($decMaxValue,$iDecimals,"0");
        }
        return array(
            'datasize'=>max(strlen($nMinValue),strlen($nMaxValue)),
            '@attributes'=>array(
                'type'=>'quantity',
            ),
            'values'=>array(
                'range'=>array(
                    '@attributes'=>array(
                        'from'=>$nMinValue,
                        'to'=>$nMaxValue,
                    ),
                )
            ),
        );
    }

    /* getDecimal : number : Format is decimal(30.10) except with max/min and integer + minus*/
    public function getDecimalSyntax($aField)
    {
        $aNumberInfo=$this->decimalInfo($aField);
        $aFormat=array();
        $aValMin=explode(".",$aNumberInfo['min']);
        $intValMin=$aValMin[0];
        $decValMin=isset($aValMin[1]) ? $aValMin[1] : "";

        $aValMax=explode(".",$aNumberInfo['max']);
        $intValMax=$aValMax[0];
        $decValMax=isset($aValMax[1]) ? $aValMax[1] : "";

        $aFormat['min']=$intValMin;
        $aFormat['max']=$intValMax;

        if($iDecimals=intval($aNumberInfo['decimals']))
        {
            $aFormat['min'].=".".str_pad (substr($decValMin,0,$iDecimals),$iDecimals,"0");
            $aFormat['max'].=".".str_pad (substr($decValMax,0,$iDecimals),$iDecimals,"0");
        }
        $sSize=max(strlen($aFormat['min']),strlen($aFormat['max']));

        return array(
            'datasize'=>$sSize,
            '@attributes'=>array(
                'type'=>'quantity',
            ),
            'values'=>array(
                'range'=>array(
                    '@attributes'=>array(
                        'from'=>$aFormat['min'],
                        'to'=>$aFormat['max'],
                    ),
                )
            ),
        );
    }

    /* getString : a free string : size is set by question type and real length */
    public function getStringSyntax($aField)
    {
        switch ($aField['type'])
        {
            case 'other':
                $dataSize=$this->stringSize($aField['fieldname'],$aField['type']);
                break;
            case 'comment':
                $dataSize=$this->stringSize($aField['fieldname'],$aField['type']);
                break;
            default:
                $aAttributes=QuestionAttribute::model()->getQuestionAttributes($aField['qid']);
                if(isset($aAttributes['maximum_chars']) && ctype_digit($aAttributes['maximum_chars']))
                    $dataSize=$aAttributes['maximum_chars'];
                else
                    $dataSize=$this->stringSize($aField['fieldname'],$aField['type']);
                break;
        }
        return array(
            'datasize'=>$dataSize,
            '@attributes'=>array(
                'type'=>'character',
            ),
            'size'=>$dataSize,
        );
    }

    /* Find the string size according to type and real DB size */
    public function stringSize($sColumn,$sType)
    {
        /* type to type */
        $aTypes=array(
            'S'=>'short',
            'L'=>'long',
            'U'=>'huge',
            'Q'=>'multiple',
            ';'=>'array',
            'comment'=>'comment',
            'other'=>'other',
            '*'=>'equation',
        );
        $sType=isset($aTypes[$sType]) ? $aTypes[$sType] : $sType ;
        $minSize=isset($this->pluginSettings['stringMin_'.$sType]) ? $this->pluginSettings['stringMin_'.$sType] : $this->pluginSettings['stringMin'];
        
        // TODO : fix LENGTH for msSQL ?
        $lengthReal = Yii::app()->db->createCommand()
        ->select('LENGTH('.Yii::app()->db->quoteColumnName($sColumn).')')
        ->from("{{survey_".$this->iSurveyId."}}")
        ->order('LENGTH('.Yii::app()->db->quoteColumnName($sColumn).')  DESC')
        ->limit(1)
        ->queryScalar();
        
        return max((int)$minSize,(int)$lengthReal);
    }

    /* Find the numeric format according to attribute */
     public function decimalInfo($aField)
    {
        $aInfo=array(
            "decimals"=>10,
            "min"=>"-".str_repeat ("9",30).".".str_repeat ("9",10),
            "max"=>str_repeat ("9",30).".".str_repeat ("9",10),
        );
        $aByAttributes=array();

        $aAttributes=QuestionAttribute::model()->getQuestionAttributes($aField['qid']);
        if(isset($aAttributes['slider_layout']) && $aAttributes['slider_layout'])
        {
            $aInfo['min']=is_numeric($aAttributes['slider_min']) ? $aAttributes['slider_min'] : 0;
            $aInfo['max']=is_numeric($aAttributes['slider_max']) ? $aAttributes['slider_max'] : 100;
            $nSliderStep=is_numeric($aAttributes['slider_accuracy']) ? $aAttributes['slider_accuracy'] : 100;
            $aInfo['decimals']=strpos($nSliderStep,".") ? strlen(substr($nSliderStep, strrpos($nSliderStep, ".") + 1) ) : 0;
        }
        else
        {
            if(isset($aAttributes['num_value_int_only']) && $aAttributes['num_value_int_only'])
                $aInfo['decimals']=0;
            if($aField['type']==":")// Array number
            {
                if(isset($aAttributes['multiflexible_min']) && is_numeric($aAttributes['multiflexible_min']))
                    $aInfo['min']=$aAttributes['multiflexible_min'];
                if(isset($aAttributes['multiflexible_max']) && is_numeric($aAttributes['multiflexible_max']))
                    $aInfo['max']=$aAttributes['multiflexible_max'];
            }
            else
            {
                if(isset($aAttributes['min_num_value_n']) && is_numeric($aAttributes['min_num_value_n']))
                    $aInfo['min']=$aAttributes['min_num_value_n'];
                if(isset($aAttributes['max_num_value_n']) && is_numeric($aAttributes['max_num_value_n']))
                    $aInfo['max']=$aAttributes['max_num_value_n'];
            }
            if($aInfo['decimals'] && (strpos($aInfo['min'],".") || strpos($aInfo['max'],".")) ) // Allow to set decimal via 1.00 in min (or max)
            {
                $iDecimalMin=strpos($aInfo['min'],".") ? strlen(substr($aInfo['min'], strrpos($aInfo['min'], ".") + 1) ) : 10;
                $iDecimalMax=strpos($aInfo['max'],".") ? strlen(substr($aInfo['max'], strrpos($aInfo['max'], ".") + 1) ) : 10;
                $aInfo['decimals']=min($iDecimalMin,$iDecimalMax);
            }
        }
        return $aInfo;
    }

    public function translate($key)
    {
        return gT($key,'html',$this->sLanguageCode);
    }

    /*
     * Filter string : no tag and no line feed
     * 
     * @param string $string to filter
     * @return string filtered string
     */
    public static function filterTextForXML($string)
    {
        if (version_compare(substr(PCRE_VERSION,0,strpos(PCRE_VERSION,' ')),'7.0')>-1)
           return preg_replace(array('~\R~u'),array(' '), strip_tags(trim($string)));
        return preg_replace("/[\n\r]/"," ",strip_tags(trim($string)));    }

}
