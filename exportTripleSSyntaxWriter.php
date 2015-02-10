<?php
/**
 * exportTripleSSyntaxWriter part of exportTripleS Plugin for LimeSurvey
 * Writer for the plugin
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
Yii::import('application.helpers.admin.export.*');
class exportTripleSSyntaxWriter extends Writer {
    const APPNAME = 'exportTripleS';
    const VERSION = '1.0';
    private $output;
    private $separator;
    private $hasOutputHeader;

    public $pluginSettings = array();

    private $position = 0;
    private $ident = 1;

    protected $customFieldmap = array();
    protected $iSurveyId;
    protected $sSurveyTitle;

    protected $sLanguageCode;


    function __construct($settings)
    {
        $this->output = '';
        $this->separator = ',';
        $this->hasOutputHeader = false;
        $basedir=dirname(__FILE__); // this will give you the / directory
        Yii::setPathOfAlias('exportTripleS', $basedir);
        foreach($settings as $name => $value)
            $this->pluginSettings[$name]=$value;

        if (function_exists('iconv'))
        {
            @setlocale(LC_ALL, 'en_US.UTF8');
        }
    }

    public function init(\SurveyObj $oSurvey, $sLanguageCode, \FormattingOptions $oOptions) {
        parent::init($oSurvey, $sLanguageCode, $oOptions);
        $this->iSurveyId=$oSurvey->id;
        $this->sLanguageCode=$sLanguageCode;
        $this->sSurveyTitle=self::filterText($oSurvey->info['surveyls_title']);

        $now=date("Ymd-His");
        $oOptions->headingFormat = "full";      // force to use own code
        $oOptions->answerFormat = "short";      // force to use own code
        Yii::import('exportTripleS.tripleSHelper');
        $tripleSfunction= new tripleSHelper($this->pluginSettings);
        $tripleSfunction->iSurveyId=$this->iSurveyId;
        $tripleSfunction->sLanguageCode=$this->sLanguageCode;
        $this->customFieldmap = $tripleSfunction->createTripleSFieldmap($oSurvey, $sLanguageCode, $oOptions);
        if ($oOptions->output == 'display')
        {
            if(!$this->pluginSettings['debugMode'])
                header("Content-Disposition: attachment; filename=survey_{$oSurvey->id}_{$now}_triples.sss");
            header("Content-type: text/xml; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
            $this->handle = fopen('php://output', 'w');
        }
        elseif ($oOptions->output == 'file')
        {
            $this->handle = fopen($this->filename, 'w');
        }


    }


    protected function out($content)
    {
        fwrite($this->handle, $content . "\n");
    }

    protected function outputRecord($headers, $values, FormattingOptions $oOptions)
    {

    }

    public function close()
    {
        $sss=array(
            '@attributes' => array(
                'version' => '2.0',
                'languages'=>$this->languageCode,
                'modes'=>'interview',
            ),
            'date'=>date("Y-m-d"),
            'time'=>date("H:i:s"),
            'origin'=>'LimeSurvey '.App()->getConfig('versionnumber')." - build:".App()->getConfig('buildnumber')." - ".self::APPNAME." ".self::VERSION,
            //'user'=>'', // Get owner name ?
            'survey'=>array(
                'name'=>'sid'.$this->iSurveyId,
                'title'=>$this->sSurveyTitle,
                'record'=>array(
                    '@attributes' => array(
                        'ident' => 'D',
                        'format'=>'fixed',
                        'skip'=>"0",
                    ),
                    'variable'=>array(
                        // Fill by $this->customFieldmap
                    ),
                )
            ),
        );
         foreach ($this->customFieldmap as $key => $aTripleSarray) {
                $sss['survey']['record']['variable']=array_merge($sss['survey']['record']['variable'],$aTripleSarray);
         }

        Yii::import('exportTripleS.third_party.Array2XML');
        $xml = Array2XML::createXML('sss', $sss);
        $this->out($xml->saveXML());
        fclose($this->handle);
    }


    private static function filterText($string)
    {
        if (version_compare(substr(PCRE_VERSION,0,strpos(PCRE_VERSION,' ')),'7.0')>-1)
           return preg_replace(array('~\R~u'),array(' '), strip_tags(trim($string)));
        return preg_replace("/[\n\r]/"," ",strip_tags(trim($string)));
    }

}
