<?php

/**
 * @file plugins/importexport/rsciexport/classes/form/rsciexportSettingsForm.php
 * @class RSCISettingsForm
 * @ingroup plugins_importexport_rsciexport
 *
 * @brief Form for journal managers to setup RSCI plugin
 */

 namespace APP\plugins\importexport\rsciexport\classes\form;

 use PKP\form\Form;
 use PKP\form\validation\FormValidatorPost;
 use PKP\form\validation\FormValidatorCSRF;


class RSCIexportSettingsForm extends Form {

    //
    // Private properties
    //
    /** @var integer */
    var $_contextId;

    /**
     * Get the context ID.
     * @return integer
     */
    function _getContextId() {
        return $this->_contextId;
    }

    /** @var CrossRefExportPlugin */
    var $_plugin;

    /**
     * Get the plugin.
     * @return CrossRefExportPlugin
     */
    function _getPlugin() {
        return $this->_plugin;
    }


    //
    // Constructor
    //
    /**
     * Constructor
     * @param $plugin RSCIexportPlugin
     * @param $contextId integer
     */
    function __construct($plugin, $contextId) {
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        // Add form validation checks.
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }


    //
    // Implement template methods from Form
    //
    /**
     * @copydoc Form::initData()
     */
    function initData() {
        $contextId = $this->_getContextId();
        $plugin = $this->_getPlugin();
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $this->setData($fieldName, $plugin->getSetting($contextId, $fieldName));
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    function readInputData() {
        $this->readUserVars(array_keys($this->getFormFields()));
    }

    /**
     * Execute the form.
     */
    function execute(...$functionArgs) {
        $plugin = $this->_getPlugin();
        $contextId = $this->_getContextId();
        foreach($this->getFormFields() as $fieldName => $fieldType) {
            $plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }
        parent::execute(...$functionArgs);
    }


    //
    // Public helper methods
    //
    /**
     * Get form fields
     * @return array (field name => field type)
     */
    function getFormFields() {
        return array(
            'exportSections' => 'bool',
            'exportArtTypeFromSectionAbbrev' => 'bool',
            'journalRSCITitleId' => 'int',
            'docStartKey' => 'string',
            'docEndKey' => 'string',
            'langCitation' => 'bool',
            'namesAsIs' => 'bool',
        );
    }
}


