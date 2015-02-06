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
class exportTripleS extends PluginBase {
    protected $storage = 'DbStorage';
    static protected $name = 'Export Triple S';
    static protected $description = 'Export result to Triple-S XML Version 2.0, with fixed column for data.';
    
    protected $settings = array(

        'listDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl><dt>For list of choice</dt><dd>You can set a specific string for No answer code (différent from not seeing).</dd><dd>If length of this string is up at default question type length (or is empty) : No answer don't have specific code.</dd></dl></div>",
        ),
        'listChoiceNoANswer'=>array(
            'type'=>'string',
            'label'=>'Remplacer les sans réponses par le code',
            'default'=>"",
        ),

        'stringDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl><dt>Pour les valeurs textes</dt><dd>Les valeurs indiquées donnent la taille minium,</dd><dd> la taille finale sera le minimum entre celle ci et la taille réelle en base de données.</dd></dl></div>",
        ),
        'stringMin'=>array(
            'type'=>'int',
            'label'=>"Taille minimum des exports de type texte par défaut",
            'default'=>255,
        ),
        'stringMin_short'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des questions de type texte court",
            'default'=>40,
        ),
        'stringMin_long'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des questions de type texte long",
            'default'=>255,
        ),
        'stringMin_huge'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des questions de type texte très long",
            'default'=>255,
        ),
        'stringMin_multiple'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des questions de type multiple texte",
            'default'=>255,
        ),
        'stringMin_array'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des questions de type tableau de  texte",
            'default'=>40,
        ),
        'stringMin_equation'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des réponses de type équation",
            'default'=>40,
        ),
        'stringMin_other'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des réponses de type autres",
            'default'=>40,
        ),
        'stringMin_comment'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export des réponses de type commentaires",
            'default'=>40,
        ),
        'stringMin_url'=>array(
            'type'=>'int',
            'label'=>"Taille minimum de l’export de l'url référente",
            'default'=>40,
        ),
        'numericDocumentation'=>array(
            'type'=>'info',
            'content'=>"<div class='alert alert-info'><dl> <dt>Pour les valeurs numériques</dt><dd>Vous pouvez utiliser les valeurs minimum et valeurs maximum pour fixer les valeurs minimums et maximum.</dd><dd> Pour fixer le nombre de chiffres après la virgule, utiliser le . (point). Par exemple '0.' en minimum donneras un entier.Attention : 0.0 en minimum et 10.99 en max donneras 1 décimal au maximum</dd></div>",
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
        $this->subscribe('listExportPlugins');
        $this->subscribe('listExportOptions');
        $this->subscribe('newExport');
    }

    public function listExportOptions()
    {
        $event = $this->getEvent();
        $type = $event->get('type');

        switch ($type) {
            case 'triples-syntax':
                $event->set('label', gT("Triple-S XML Syntax"));
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
