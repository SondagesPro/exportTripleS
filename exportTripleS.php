<?php
/**
 * exportTripleS Plugin for LimeSurvey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2015 Denis Chenu <http://sondages.pro>
 * @license GPL v3
 * @version 2.0
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
class exportTripleS extends PluginBase {
    protected $storage = 'DbStorage';
    static protected $name = 'Export Triple S 2.0';
    static protected $description = 'Export result to Triple-S XML Version 2.0 and 1.2, with fixed column for data.';
    
    private $demo=false;
    protected $settings = array(

        'XMLversion'=>array(
            'type'=>'select',
            'label'=>"sss XML version",
            'options'=>array(
                '2'=>'XML version 2',
                '1.2'=>'XML version 1.2',
            ),
            'default'=>'2',
            'help'=>'Data exported is always exported in dat format',
        ),

        'listDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl><dt>For list of choice</dt><dd>This settings is using for single choice question and Array of single choice question type.</dd></dl></div>",
        ),
        'listChoiceNoANswer'=>array(
            'type'=>'string',
            'label'=>'Replace no answer by code',
            'default'=>"",
            'htmlOtions'=>array(
              'maxlength'=>5,
            ),
            'help'=>'Set a specific string for `No answer code` (different from not see because hidden by releance or don’t get to this step).',
        ),
        'listChoiceReplace'=>array(
            'type'=>'select',
            'label'=>'Use numeric code for list of choice',
            'options'=>array(
                'code'=>'Use real code',
                'order'=>'Order of the answers (Default for XML 1.2)',
                'replace'=>'Removing non numeric character from code',
            ),
            'default'=>"code",
            'help' => 'Only XML2 accept alphanumeric character, XML1.2 can use the order of the answer or removing all non numeric character',
        ),
        'listChoiceOther'=>array(
            'type'=>'int',
            'label'=>'With Removing non numeric character: Replace other by ',
            'default'=>"99999",
            
        ),
        'listChoiceLabel'=>array(
            'type'=>'select',
            'label'=>'Add the real code before the label',
            'options'=>array(
                'yes'=>'Yes',
                'no'=>'No',
            ),
            'default'=>"no",
            'help' => 'If you use order or replace for code, it can be interesting to have the real code somewhere',
        ),

        'multipleDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl><dt>For multiple choice</dt><dd>You can export other on mutiple in 2 column : one logical and one text.</dd></dl></div>",
        ),
        'multipleOtherExport'=>array(
            'type'=>'select',
            'label'=>"Export other in multiple ",
            'options'=>array(
                '2col'=>'2 column : 1 logical + 1 caracter',
                '1col'=>'Only caracter column',
            ),
            'default'=>'2col',
        ),

        'stringDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl><dt>For text value</dt><dd>Value set the minimum,</dd><dd> final size are the miniumum betwwen this size and the real data in the database.</dd></dl></div>",
        ),
        'stringAnsi'=>array(
            'type'=>'select',
            'label'=>"Export user text in ANSI (else UTF-8)",
            'help'=>"Some tools don't import correctly UTF-8 fixed width, use ANSI remove all accent from the data.",
            'options'=>array(
                'utf8'=>'Export in utf-8',
                'ansi'=>'Force ANSI',
            ),
            'default'=>'utf8',
        ),
        'stringMin'=>array(
            'type'=>'int',
            'label'=>"Minimal size for text by default",
            'default'=>255,
        ),
        'stringMin_short'=>array(
            'type'=>'int',
            'label'=>"Minimal size for Short free text question type",
            'default'=>40,
        ),
        'stringMin_long'=>array(
            'type'=>'int',
            'label'=>"Minimal size for Long free text question type",
            'default'=>255,
        ),
        'stringMin_huge'=>array(
            'type'=>'int',
            'label'=>"Minimal size for Huge free text question type",
            'default'=>255,
        ),
        'stringMin_multiple'=>array(
            'type'=>'int',
            'label'=>"Minimal size for Multiple short text question type",
            'default'=>255,
        ),
        'stringMin_array'=>array(
            'type'=>'int',
            'label'=>"Minimal size for Array (Texts) question type",
            'default'=>40,
        ),
        'stringMin_equation'=>array(
            'type'=>'int',
            'label'=>"Minimal size for Equation question type",
            'default'=>40,
        ),
        'stringMin_other'=>array(
            'type'=>'int',
            'label'=>"Minimal size for answers Other type",
            'default'=>40,
        ),
        'stringMin_comment'=>array(
            'type'=>'int',
            'label'=>"Minimal size for answers Comment type",
            'default'=>40,
        ),
        'stringMin_url'=>array(
            'type'=>'int',
            'label'=>"Minimal size for referrer URL",
            'default'=>40,
        ),

        'numericDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl> <dt>For numeric value</dt><dd>You can set minimum and maximum to fix the minimum and maximum value.</dd><dd>To fix the number of decimal places, use the dot inside the minimum. For exemple, to always set an integer : use '0.', even is the value is not an integer. .Attention : 0.0 at minimum and 10.99 at maximum give only 1 decimal, only the minimal settings is controlled.</dd><dt>Attention:</dt><dd>Numerci value in the DataBase of LimeSurvey have 30 digits and 10 decimals.</dd></dl></div>",
        ),
        'numericIntLength'=>array(
            'type'=>'int',
            'label'=>'Number of digits for numeric value.',
            'min'=>1,
            'max'=>30,
            'default'=>30,
        ),
        'numericDecLength'=>array(
            'type'=>'int',
            'label'=>'Number of decimals (by default) for numeric value.',
            'min'=>1,
            'max'=>10,
            'default'=>10,
        ),

        'datetimeDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl> <dt>For Date/time value</dt><dd>Default is to export a date column and a time column with TripleS XML2. You can choose character for XML2 or number. WIth XML 1.2 : only character and number can be used.</dd></dl></div>",
        ),
        'datetimeExport'=>array(
            'type'=>'select',
            'label'=>"Export DATETIME en ",
            'options'=>array(
                'date'=>'date + time',
                'character'=>'character in 1 column (default for XML 1.2)',
                'number'=>'number in 1 column (14 number))',
            ),
            'default'=>'date',
        ),

        
        'debugDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><strong>Information de debug</strong><dl> <dt>Basique</dt><dd>Ajoute dans l'export sss une colonne pour tous les types de question. Permet la compatibilité du fichier.</dd><dt>Avancé</dt><dd>Visualisation du fichier et pas export, casse le fichier triple-s (ajoute trop d’information)</dd><dt>Complete</dt><dd>Export les données visuellement sous forme de tableau.</dd></dt></dl></div>",
        ),
        'debugMode'=>array(
            'type'=>'select',
            'label'=>"Debugging",
            'options'=>array(
                '0'=>'None',
                '1'=>'Basic',
                '3'=>'Advanced',
                '7'=>'Complete',
            ),
            'default'=>0
        ),
    );

    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
        if((Yii::app()->getConfig("buildnumber") && intval(Yii::app()->getConfig("buildnumber"))<140703))
        {
            unset($this->settings['listDocumentation']);
            unset($this->settings['stringDocumentation']);
            unset($this->settings['numericDocumentation']);

            unset($this->settings['debugDocumentation']);
        }
        if($this->demo)
        {
          unset($this->settings['debugDocumentation']);
          unset($this->settings['debugMode']);
        }
        $this->subscribe('listExportPlugins');
        $this->subscribe('listExportOptions');
        $this->subscribe('newExport');
        if($this->demo)
          $this->subscribe('beforeDeactivate');
    }

    public function beforeDeactivate()
    {
        $this->getEvent()->set('success', false);

        // Optionally set a custom error message.
        $this->getEvent()->set('message', gT('This plugin can not be disabled in this website.'));
    }

    public function listExportOptions()
    {
        $event = $this->getEvent();
        $type = $event->get('type');

        switch ($type) {
            case 'triples-syntax':
                $event->set('label', gT("Triple-S XML Syntax"));
                if($this->demo)
                  $event->set('default', true);
                break;
            case 'triples-data':
            default:
                $event->set('label', gT("Triple-S CSV Data"));
                break;
        }
    }

    /**
    * Registers this export type
    */
    public function listExportPlugins()
    {
        $event = $this->getEvent();
        $exports = $event->get('exportplugins');
        
        $exports['triples-syntax'] = get_class();
        $exports['triples-data'] = get_class();
        $event->set('exportplugins', $exports);
    }
    public function newExport()
    {
        $event = $this->getEvent();
        $type = $event->get('type');

        $pluginSettings=array();
        foreach ($this->settings as $name => $value)
        {
            $default=isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL;
            $value = $this->get($name,null,null,$default);
            $pluginSettings[$name]=$value;
        }
        if($this->demo)
          $pluginSettings['debugMode']=0;
        // Fix datetimeExport : can't fix in public get ?
        if($pluginSettings['XMLversion']<2)
        {
            if(!in_array($pluginSettings['datetimeExport'],array('character','number')))
                $pluginSettings['datetimeExport']='character';
            if(!in_array($pluginSettings['listChoiceReplace'],array('order','replace')))
                $pluginSettings['listChoiceReplace']='order';
        }
        switch ($type) {
            case 'triples-syntax':
                $writer = new exportTripleSSyntaxWriter($pluginSettings);
                break;
            case 'triples-data':
            default:
                $writer = new exportTripleSDataWriter($pluginSettings);
                break;
        }

        $event->set('writer', $writer);
    }

    public function saveSettings($settings)
    {
        foreach ($settings as $setting=>$aSetting)
        {
            if($this->settings[$setting]['type']=='int')
            {
                $settings[$setting]=(int)$settings[$setting];
                if($settings[$setting]<0 && isset($this->settings[$setting]['default']))
                    $settings[$setting]=$this->settings[$setting]['default'];
            }
        }
        parent::saveSettings($settings);
    }
}
