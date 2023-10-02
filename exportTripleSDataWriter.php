<?php

/**
 * exportTripleSDataWriter part of exportTripleS Plugin for LimeSurvey
 * Writer for the plugin
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2023 Denis Chenu <http://sondages.pro>
 * @license AGPL v3
 * @version 3.1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

Yii::import('application.helpers.admin.export.*');
class exportTripleSDataWriter extends Writer
{
    private $output;
    private $hasOutputHeader;

    private $aColunInfo = array();
    public $pluginSettings = array();

    private $position = 0;
    private $ident = 1;

    protected $customFieldmap = array();
    //protected $oSurvey;
    protected $iSurveyId;
    protected $sLanguageCode;


    function __construct($settings)
    {
        mb_internal_encoding('utf-8'); // @important
        $this->output = '';
        $this->hasOutputHeader = false;
        $basedir = dirname(__FILE__); // this will give you the / directory
        if (intval(App()->getConfig('versionnumber')) < 4) {
            Yii::setPathOfAlias('exportTripleS', $basedir . DIRECTORY_SEPARATOR . 'legacy');
        } else {
            Yii::setPathOfAlias('exportTripleS', $basedir);
        }
        foreach ($settings as $name => $value) {
            $this->pluginSettings[$name] = $value;
        }
    }

    public function init(\SurveyObj $oSurvey, $sLanguageCode, \FormattingOptions $oOptions)
    {
        parent::init($oSurvey, $sLanguageCode, $oOptions);
        //$this->oSurvey=$oSurvey;
        $this->iSurveyId = $oSurvey->id;
        $this->sLanguageCode = $sLanguageCode;

        $now = date("Ymd-His");
        $oOptions->headingFormat = "full";      // force to use own code
        $oOptions->answerFormat = "short";      // force to use own code
        Yii::import('exportTripleS.tripleSHelper');
        $tripleSfunction = new tripleSHelper($this->pluginSettings);
        $tripleSfunction->iSurveyId = $this->iSurveyId;
        $tripleSfunction->sLanguageCode = $this->sLanguageCode;

        $this->customFieldmap = $tripleSfunction->createTripleSFieldmap($oSurvey, $sLanguageCode, $oOptions);

        if ($this->pluginSettings['stringAnsi'] == "ansi") {
            setlocale(LC_ALL, $this->getLocaleLanguage($this->sLanguageCode));
        }

        if ($oOptions->output == 'display') {
            header('Content-Encoding: UTF-8');
            if (!$this->pluginSettings['debugMode']) {
                header("Content-Disposition: attachment; filename=survey_{$oSurvey->id}_{$now}_triples.dat");
            }
            //if(intval($this->pluginSettings['debugMode'])<2)
                header("Content-type: text/plain; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
            $this->handle = fopen('php://output', 'w');
        } elseif ($oOptions->output == 'file') {
            $this->handle = fopen($this->filename, 'w');
        }
    }

    protected function out($content)
    {
        fwrite($this->handle, $content . "\n");
    }

    protected function outputRecord($headers, $values, FormattingOptions $oOptions)
    {
        if (!$this->hasOutputHeader) {
            $return = "\xEF\xBB\xBF"; // UTF-8 BOM
            $this->hasOutputHeader = true;
        }
            $aValues = array();
            $aColumns = $oOptions->selectedColumns;
        foreach ($values as $key => $value) {
            $sColumn = $aColumns[$key];
            if ($this->pluginSettings['debugMode'] > 2) {
                    $aValues[] = $sColumn . "|";
            }
            $aValues[] = $this->tripleSgetValue($value, $sColumn);
            if ($this->pluginSettings['debugMode'] > 2) {
                    $aValues[] = "-";
            }
        }

            echo implode($aValues) . "\n";
    }
    private function tripleSgetValue($sValue, $sColumn)
    {
        if (isset($this->customFieldmap[$sColumn])) {
            if ($this->pluginSettings['debugMode'] >= 4) {
                return array('column' => $sColumn,'value' => $sValue,'triples' => $this->customFieldmap[$sColumn]);
            }
            $return = "";// Some column need 2 function

            foreach ($this->customFieldmap[$sColumn] as $aTripleS) {
                if (isset($aTripleS['@attributes']['type'])) {
                    $function = "getValue" . $aTripleS['@attributes']['type'];
                    $return .= $this->$function($sValue, $aTripleS);
                }
            }
            return $return;
        } else {
            return "";
        }
    }

    public function close()
    {
        fclose($this->handle);
    }

    private function getValueCharacter($sValue, $aTriplesField)
    {
        if ($aTriplesField['info']['type'] == "D") {
            $numCar = strlen("YYYY-MM-DD HH:ii:ss");
            if (is_null($sValue)) {
                return str_repeat(" ", $numCar);
            }
            if (strlen($sValue) < $numCar) {
                return str_repeat(" ", $numCar); /* invalid date */
            }
            $oDate = new DateTime($sValue);
            if ($oDate->format("Y-m-d H:i:s") == substr($sValue, 0, $numCar)) {
                return $oDate->format("Y-m-d H:i:s");
            }
            return str_repeat(" ", $numCar); /* invalid date */
        }
        $iSize = $aTriplesField['size'];
        if (is_null($sValue)) {
            return str_repeat(" ", $iSize);
        }

        $sValue = self::filterStringForTripleS($sValue, $this->pluginSettings['stringAnsi'] == "ansi");
        if ($this->pluginSettings['stringAnsi'] == "ansi") {
            return str_pad(substr($sValue, 0, $iSize), $iSize, " ", STR_PAD_RIGHT);
        }
        return self::mb_str_pad(mb_substr($sValue, 0, $iSize), $iSize, " ", STR_PAD_RIGHT, 'UTF8');
    }
    private function getValueQuantity($sValue, $aTriplesField)
    {
        if ($aTriplesField['info']['type'] == "D") {
            if (is_null($sValue)) {
                return str_repeat(" ", 8 + 6);
            }
            $oDate = new DateTime($sValue);
            return $oDate->format("YmdHis");
        }
        $sMin = $aTriplesField['values']['range']['@attributes']['from'];
        $sMax = $aTriplesField['values']['range']['@attributes']['to'];
        $iSize = max(strlen($sMin), strlen($sMax));
        if (is_null($sValue)) {
            return str_repeat(" ", $iSize);
        }
        if ($sValue == "" || $sValue == " ") {
            return str_repeat(" ", $iSize);
        }
        $aSize = explode(".", $sMax);
        if (isset($aSize[1])) {
            $iDecimalLength = strlen($aSize[1]);
        } else {
            $iDecimalLength = 0;
        }
        $aValue = explode(".", $sValue);
        $aMax = explode(".", $sMax);
        $aMin = explode(".", $sMin);
        if ($aValue[0] < $aMin[0]) {
            $sNewValue = $aMin[0];
        } elseif ($aValue[0] > $aMax[0]) {
            $sNewValue = $aMax[0];
        } else {
            $sNewValue = $aValue[0];
        }
        if ($iDecimalLength) {
            $sNewValue .= ".";
            if (isset($aValue[1])) {
                $sNewValue .= str_pad(substr($aValue[1], 0, $iDecimalLength), $iDecimalLength, "0");
            } else {
                $sNewValue .= str_repeat("0", $iDecimalLength);
            }
        }
        // Fix min Max ?

        return str_pad($sNewValue, $iSize, " ", STR_PAD_LEFT);
    }
    private function getValueSingle($sValue, $aTriplesField)
    {
        $iStart = intval($aTriplesField['position']['@attributes']['start']);
        $iFinish = intval($aTriplesField['position']['@attributes']['finish']);
        $iSize = $iFinish - $iStart + 1;
        if (is_null($sValue)) {
            return str_repeat(" ", $iSize);
        }
        if (isset($aTriplesField['info']['replace'])) {
            $sValue = isset($aTriplesField['info']['replace'][$sValue]) ? $aTriplesField['info']['replace'][$sValue] : "";
        }
        // else test if code exist
        if ($sValue == "" && $iSize >= strlen($this->pluginSettings['listChoiceNoANswer'])) {
            $sValue = $this->pluginSettings['listChoiceNoANswer'];
        }
        return str_pad($sValue, $iSize, " ");
    }
    /**
     * return date part of SQL date-time
     * @param $sValue
     * @param $aTriplesField
     * @retrun string
     */
    private function getValueDate($sValue, $aTriplesField)
    {
        if (is_null($sValue) || strlen($sValue) < 10) {
            return str_repeat(" ", 8); /* Not sure for this one : non set for missing datetime in TripleS book */
        }
        if (strlen($sValue) < 10) {
            return str_repeat(" ", 8); /* invalid date */
        }
        $oDate = new DateTime($sValue);
        if ($oDate->format("Y-m-d") == substr($sValue, 0, 10)) {
            return $oDate->format("Ymd");
        }
        return str_repeat(" ", 8); /* invalid date */
    }
    private function getValueTime($sValue, $aTriplesField)
    {
        if (is_null($sValue)) {
            return str_repeat(" ", 4); // Not sure for this one : non set for missing datetime in TripleS book
        }
        if (strlen($sValue) < 19) {
            return str_repeat(" ", 4); /* invalid time */
        }
        $oDate = new DateTime($sValue);
        if ($oDate->format("H:i:s") == substr($sValue, 11, 19)) {
            return $oDate->format("Hi");
        }
        return str_repeat(" ", 4); /* invalid time */
    }
    private function getValueLogical($sValue, $aTriplesField)
    {
        if (is_null($sValue)) {
            return " ";
        }
        return (int)(bool)$sValue;
    }

    /*
     * Filter string : no line feed
     *
     * @param string $string to filter
     * @return string filtered string
     */
    public static function filterStringForTripleS($string, $bAnsi = false)
    {
        if ($bAnsi) {
            $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        }
        if (version_compare(substr(PCRE_VERSION, 0, strpos(PCRE_VERSION, ' ')), '7.0') > -1) {
            return preg_replace(array('~\R~u'), array(' '), $string);
        }
        return preg_replace("/[\n\r]/", " ", $string);
    }

    private static function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT, $encoding = null)
    {
        $encoding = $encoding === null ? mb_internal_encoding() : $encoding;
        $padBefore = $dir === STR_PAD_BOTH || $dir === STR_PAD_LEFT;
        $padAfter = $dir === STR_PAD_BOTH || $dir === STR_PAD_RIGHT;
        $pad_len -= mb_strlen($str, $encoding);
        $targetLen = $padBefore && $padAfter ? $pad_len / 2 : $pad_len;
        $strToRepeatLen = mb_strlen($pad_str, $encoding);
        $repeatTimes = ceil($targetLen / $strToRepeatLen);
        $repeatedString = str_repeat($pad_str, max(0, $repeatTimes)); // safe if used with valid unicode sequences (any charset)
        $before = $padBefore ? mb_substr($repeatedString, 0, floor($targetLen), $encoding) : '';
        $after = $padAfter ? mb_substr($repeatedString, 0, ceil($targetLen), $encoding) : '';
        return $before . $str . $after;
    }
    private function getLocaleLanguage($sLanguageCode)
    {
        $aLanguageLocale = array(
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'de-informal' => 'de_DE',
        );
        if (isset($aLanguageLocale[$sLanguageCode])) {
            return $aLanguageLocale[$sLanguageCode];
        }
        return 'en_US';
    }
}
