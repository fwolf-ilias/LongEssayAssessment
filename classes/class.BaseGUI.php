<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\LongEssayAssessment;

use ILIAS\DI\Container;
use ILIAS\Plugin\LongEssayAssessment\Data\DataService;
use ILIAS\Plugin\LongEssayAssessment\Data\Task\EditorSettings;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\Plugin\LongEssayAssessment\ServiceLayer\ObjectServices;
use ILIAS\Plugin\LongEssayAssessment\ServiceLayer\CommonServices;
use ilLink;
use ilMailFormCall;

/**
 * Base class for GUI classes (except the plugin guis required by ILIAS)
 * @author Fred Neumann <fred.neumann@ilias.de>
 */
abstract class BaseGUI
{
    /** @var Container */
    protected $dic;

    /** @var \ilCtrl */
    protected $ctrl;

    /** @var  \ilTabsGUI */
    protected $tabs;

    /** @var \ilGlobalTemplateInterface */
    protected $tpl;

    /** @var \ilLanguage */
    protected $lng;

    /** @var \ilToolbarGUI */
    protected $toolbar;

    /** @var \ilObjLongEssayAssessmentGUI */
    protected $objectGUI;

    /** @var  \ilObjLongEssayAssessment */
    protected $object;

    /** @var  \ilLongEssayAssessmentPlugin */
    protected $plugin;

    /** @var Factory  */
    protected $uiFactory;

    /** @var Renderer  */
    protected $renderer;

    /** @var RequestInterface|ServerRequestInterface  */
    protected $request;

    /** @var \ILIAS\Refinery\Factory  */
    protected $refinery;

    /** @var LongEssayAssessmentDI */
    protected $localDI;

    /** @var DataService */
    protected $data;

    /** @var array query params */
    protected $params;

    /** @var string[] placeholder => html */
    protected array $placeholders = [];

    protected \ILIAS\ResourceStorage\Services $storage;

    protected CommonServices $common_services;
    protected ObjectServices $object_services;

    /**
     * Constructor
     * @param \ilObjLongEssayAssessmentGUI  $objectGUI
     */
    public function __construct(\ilObjLongEssayAssessmentGUI $objectGUI)
    {
        global $DIC;

        // ILIAS dependencies
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();
        $this->tabs = $this->dic->tabs();
        $this->toolbar = $this->dic->toolbar();
        $this->lng = $this->dic->language();
        $this->tpl = $this->dic->ui()->mainTemplate();
        $this->uiFactory = $this->dic->ui()->factory();
        $this->renderer = $this->dic->ui()->renderer();
        $this->request = $this->dic->http()->request();
        $this->refinery = $this->dic->refinery();

        // Plugin dependencies
        $this->objectGUI = $objectGUI;
        $this->object = $this->objectGUI->getObject();
        $this->plugin = \ilLongEssayAssessmentPlugin::getInstance();
        $this->localDI = LongEssayAssessmentDI::getInstance();
        $this->data = $this->localDI->getDataService($this->object->getId());
        $this->params = $this->request->getQueryParams();
        $this->storage = $DIC->resourceStorage();
        $this->common_services = $this->localDI->services()->common();
        $this->object_services = $this->localDI->services()->object($this->object->getRefId());
    }

    /**
     * Execute a command
     * This should be overridden in the child classes
     * note: permissions are already checked in the object gui
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd('xxx');
        switch ($cmd) {
            case 'yyy':
            case 'zzz':
                $this->$cmd();
                break;

            default:
                // show unknown command
                $this->tpl->setContent('unknown command: ' . $cmd);
        }
    }

    /**
     * Raise a permission error
     * This may be needed if wrong ids for editing records are given
     */
    public function raisePermissionError()
    {
        $this->tpl->setOnScreenMessage("failure", $this->lng->txt("permission_denied"), true);
        $this->ctrl->clearParameters($this->objectGUI);
        $this->ctrl->redirect($this->objectGUI);
    }

    /**
     * Display an HTML text in readable width
     */
    public function displayText(?string $html) : string
    {
        return '<div style="max-width: 60em;">' . $html . '</div>';
    }

    /**
     * Display an essay content
     */
    public function displayContent(?string $html) : string
    {
        if (!empty($settings = $this->localDI->getTaskRepo()->getEditorSettingsById($this->object->getId()))) {
            switch ($settings->getHeadlineScheme()) {
                case EditorSettings::HEADLINE_SCHEME_SINGLE:
                    $headline_class = "headlines-single";
                    break;
                case EditorSettings::HEADLINE_SCHEME_THREE:
                    $headline_class = "headlines-three";
                    break;
                case EditorSettings::HEADLINE_SCHEME_EDUTIEK:
                    $headline_class = "headlines-edutiek";
                    break;
                case EditorSettings::HEADLINE_SCHEME_NUMERIC:
                    $headline_class = "headlines-numeric";
                    break;
            }
        }
        return '<div class="long-essay-content '. $headline_class.' ">' . $html . '</div>';
    }

    /**
     * Add the css for displaying essay content
     */
    public function addContentCss() : void
    {
        $this->tpl->addCss($this->plugin->getDirectory() .'/templates/css/content.css');

        if (!empty($settings = $this->localDI->getTaskRepo()->getEditorSettingsById($this->object->getId()))) {
            switch ($settings->getHeadlineScheme()) {
                case EditorSettings::HEADLINE_SCHEME_SINGLE:
                    $this->tpl->addCss($this->plugin->getDirectory() .'/templates/css/headlines-single.css');
                    break;
                case EditorSettings::HEADLINE_SCHEME_THREE:
                    $this->tpl->addCss($this->plugin->getDirectory() .'/templates/css/headlines-three.css');
                    break;
                case EditorSettings::HEADLINE_SCHEME_EDUTIEK:
                    $this->tpl->addCss($this->plugin->getDirectory() .'/templates/css/headlines-edutiek.css');
                    break;
                case EditorSettings::HEADLINE_SCHEME_NUMERIC:
                    $this->tpl->addCss($this->plugin->getDirectory() .'/templates/css/headlines-numeric.css');
                    break;
            }
        }
    }

    /**
     * Add HTML code for later replacement and get a placeholder value
     * This is a workaround to use to html in UI elements where it would be quoted by the rendering
     */
    protected function createPlaceholder(string $html) : string
    {
        $key = '[HTML]' . md5($html) . '[/HTML]';
        $this->placeholders[$key] = $html;
        return $key;
    }

    /**
     * Replace the placeholders with their remembered html content
     * This is a workaround to use to html in UI elements where it would be quoted by the rendering
     */
    protected function fillPlaceholders(string $content) : string
    {
        foreach ($this->placeholders as $key => $value) {
            $content = str_replace($key , $value, $content);
        }
        return $content;
    }

    /**
     * Open the mail form for sending a mail to accounts
     * @see Services/Mail/README.md
     */
    protected function openMailForm(array $logins, $current_command)
    {
        $sig = chr(13) . chr(10) . chr(13) . chr(10);
        $sig .= $this->plugin->txt('link_to_object');
        $sig .= chr(13) . chr(10);
        $sig .= ilLink::_getStaticLink((int) ($get['ref_id'] ?? ''));
        $sig = rawurlencode(base64_encode($sig));

        $get = $this->request->getQueryParams();
        $this->ctrl->redirectToUrl(
            ilMailFormCall::getRedirectTarget(
                $this, $current_command, ['ref_id' => $get['ref_id'] ?? ''],
                [
                    'type' => 'new', // Could also be 'reply' with an additional 'mail_id' paremter provided here
                    'rcp_to' => implode(', ', $logins),
                    ilMailFormCall::SIGNATURE_KEY => $sig
                ],
            )
        );
    }

}
