<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\LongEssayTask\Corrector;

use ILIAS\Plugin\LongEssayTask\BaseGUI;
use ILIAS\Plugin\LongEssayTask\LongEssayTaskDI;
use ILIAS\UI\Factory;
use \ilUtil;

/**
 *Start page for correctors
 *
 * @package ILIAS\Plugin\LongEssayTask\Writer
 * @ilCtrl_isCalledBy ILIAS\Plugin\LongEssayTask\Corrector\CorrectorStartGUI: ilObjLongEssayTaskGUI
 */
class CorrectorStartGUI extends BaseGUI
{
    /**
     * Execute a command
     * This should be overridden in the child classes
     * note: permissions are already checked in the object gui
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd('showStartPage');
        switch ($cmd)
        {
            case 'showStartPage':
                $this->$cmd();
                break;

            default:
                $this->tpl->setContent('unknown command: ' . $cmd);
        }
    }


    /**
     * Show the items
     */
    protected function showStartPage()
    {
//        $this->toolbar->setFormAction($this->ctrl->getFormAction($this));
//        $button = \ilLinkButton::getInstance();
//        $button->setUrl('./Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayTask/lib/corrector/index.html');
//        $button->setCaption('Korrektur starten', false);
//        $button->setPrimary(true);
//        $button->setTarget('_blank');   // as long as the corrector has no return address
//        $this->toolbar->addButtonInstance($button);


        $actions = array(
            "Alle" => "all",
            "Offen" => "",
            "Vorläufig" => "",
            "Korrigiert" => "",
            "Große Abweichung" => "",
        );

//        $aria_label = "change_the_currently_displayed_mode";
//        $view_control = $this->uiFactory->viewControl()->mode($actions, $aria_label)->withActive("Alle");
//
//        $result = $this->uiFactory->item()->group("", [
//            $this->uiFactory->item()->standard("Korrekturstatus")
//                ->withDescription("")
//                ->withProperties(array(
//                    "Bewertete Abgaben:" => "1",
//                    "Offene Abgaben:" => "1",
//                    "Durchschnittsnote:" => "10"))
//        ]);



        $item1 = $this->uiFactory->item()->standard($this->uiFactory->link()->standard("Abgabe 1 (anonymisiert)",'./Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayTask/lib/corrector/index.html'))
            ->withLeadIcon($this->uiFactory->symbol()->icon()->standard('adve', 'user', 'medium'))
            ->withProperties(array(
                "Abgabe-Status:" => "abgegeben",
                "Korrektur-Status:" => "vorläufig",
                "Punkte:" => 10,
                "Notenstufe:" => "bestanden",
                "Zweitkorrektor:" =>  "Volker Reuschenback (volker.reuschenbach)"
            ))
            ->withActions(
                $this->uiFactory->dropdown()->standard([
                    $this->uiFactory->button()->shy('Korrektur bearbeiten', '#'),
                    $this->uiFactory->button()->shy('Korrektur finalisieren', '#')
                ]));

        $item2 = $this->uiFactory->item()->standard($this->uiFactory->link()->standard("Abgabe 2 (anonymisiert)", ''))
            ->withLeadIcon($this->uiFactory->symbol()->icon()->standard('adve', 'editor', 'medium'))
            ->withProperties(array(
                "Abgabe-Status:" => "abgegeben",
                "Korrektur-Status:" => "offen",
                "Erstkorrektor:" => "Matthias Munkel (matthias.kunkel)"
            ))
            ->withActions(
                $this->uiFactory->dropdown()->standard([
                    $this->uiFactory->button()->shy('Korrektur bearbeiten', '#'),
                ]));

        $essays = $this->uiFactory->item()->group("Zugeteilte Abgaben", array(
            $item1,
            $item2
        ));

        $this->tpl->setContent(

//            $this->renderer->render($result) . '<br>'.
//            $this->renderer->render($view_control) . '<br><br>' .
            $this->renderer->render($essays)

        );

     }


    /**
     * Start the Writer Web app
     */
    protected function startCorrector()
    {
        $di = LongEssayTaskDI::getInstance();

        // ensure that an essay record exists
        $essay = $di->getEssayRepo()->getEssayByWriterIdAndTaskId((string) $this->dic->user()->getId(), (string) $this->object->getId());
        if (!isset($essay)) {
            $essay = new Essay();
            $essay->setWriterId((string) $this->dic->user()->getId());
            $essay->setTaskId((string) $this->object->getId());
            $essay->setUuid($essay->generateUUID4());
            $essay->setRawTextHash('');
            $di->getEssayRepo()->createEssay($essay);
        }

        $context = new WriterContext();
        $context->init((string) $this->dic->user()->getId(), (string) $this->object->getRefId());
        $service = new Service($context);
        $service->openFrontend();
    }

}