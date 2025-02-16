<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\LongEssayAssessment\Writer;

use Edutiek\LongEssayAssessmentService\Writer\Service;
use ILIAS\Plugin\LongEssayAssessment\BaseGUI;
use ILIAS\Plugin\LongEssayAssessment\Data\Task\Resource;
use ILIAS\Plugin\LongEssayAssessment\LongEssayAssessmentDI;
use ILIAS\Plugin\LongEssayAssessment\Task\ResourceAdmin;
use \ilUtil;
use ILIAS\Plugin\LongEssayAssessment\Data\Task\TaskRepository;
use ILIAS\Plugin\LongEssayAssessment\Data\Task\TaskSettings;
use ILIAS\Plugin\LongEssayAssessment\CorrectorAdmin\CorrectorAdminService;

/**
 * Start page for writers
 *
 * @package ILIAS\Plugin\LongEssayAssessment\Writer
 * @ilCtrl_isCalledBy ILIAS\Plugin\LongEssayAssessment\Writer\WriterStartGUI: ilObjLongEssayAssessmentGUI
 */
class WriterStartGUI extends BaseGUI
{

    protected TaskRepository $task_repo;
    protected TaskSettings $task;
    protected CorrectorAdminService $corrector_admin_service;

    public function __construct(\ilObjLongEssayAssessmentGUI $objectGUI)
    {
        parent::__construct($objectGUI);

        $this->task_repo = $this->localDI->getTaskRepo();
        $this->task = $this->task_repo->getTaskSettingsById($this->object->getId());
        $this->corrector_admin_service = $this->localDI->getCorrectorAdminService($this->object->getId());
    }

    /**
     * Execute a command
     * This should be overridden in the child classes
     * note: permissions are already checked in the object gui
     */
    public function executeCommand()
    {

        $cmd = $this->ctrl->getCmd('showStartPage');
        switch ($cmd) {
            case 'showStartPage':
            case 'startWriter':
            case 'startWritingReview':
            case 'downloadWriterPdf':
            case 'downloadCorrectedPdf':
            case 'downloadCorrectionReportsPdf':
            case 'downloadResourceFile':
            case 'viewDescription':
            case 'viewClosingMessage':
            case 'viewInstructions':
            case 'downloadInstructions':
            case 'viewSolution':
            case 'downloadSolution':
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
        $contents = [];
        $modals = [];

        $essay = $this->data->getOwnEssay(); // max be null

        $writing_start = $this->task->getWritingStart();
        $writing_end = null;
        if (!empty($this->task->getWritingEnd())) {
            $writing_end = $this->data->unixTimeToDb(
                $this->data->dbTimeToUnix($this->task->getWritingEnd()) + $this->data->getOwnTimeExtensionSeconds()
            );
        }
        $is_written = isset($essay) && !empty($essay->getWritingAuthorized());
        $is_before_writing = isset($writing_start) && time() < $this->data->dbTimeToUnix($writing_start);
        $is_after_writing = $is_written || (isset($writing_end)  && time() > $this->data->dbTimeToUnix($writing_end));

        // Screen Message

        if (isset($essay)) {
            if (!empty($essay->getWritingExcluded())) {
                $this->tpl->setOnScreenMessage("info", $this->plugin->txt('message_writing_excluded'));

            } elseif ($this->object->canWrite()) {
                if (isset($this->params['returned'])) {
                    $this->tpl->setOnScreenMessage("info", $this->plugin->txt('message_writing_returned_interrupted'));
                }
            } elseif (empty($essay->getWritingAuthorized())) {
                if ($this->object->canReviewWrittenEssay()) {
                    $this->tpl->setOnScreenMessage("failure", $this->plugin->txt('message_writing_to_authorize'));
                } else {
                    $this->tpl->setOnScreenMessage("failure", $this->plugin->txt('message_writing_not_authorized'));
                }

            } elseif (!empty($essay->getWritingAuthorized())) {
                if (isset($this->params['returned'])) {
                    if(!empty($this->task->getClosingMessage())) {
                        $message = $this->displayText($this->task->getClosingMessage());
                    } else {
                        $message = $this->plugin->txt('message_writing_authorized');
                    }
                    if (!empty($this->task->getReviewStart()) || !empty($this->task->getReviewEnd())) {
                        $message .= sprintf(
                            '<p>'. $this->plugin->txt('message_review_period') . '</p>',
                            $this->data->formatPeriod($this->task->getReviewStart(), $this->task->getReviewEnd())
                        );
                    }
                    $back_url = \ilLink::_getLink($this->dic->repositoryTree()->getParentId($this->object->getRefId()));
                    $back_text = $this->plugin->txt('message_writing_authorized_link');
                    $message .= '<p><a href="'.$back_url.'">'.$back_text.'</a></p>';

                    $this->tpl->setOnScreenMessage("success", $message);

                } else {
                    $this->tpl->setOnScreenMessage("info", $this->plugin->txt('message_writing_authorized'));
                }
            }
        }

        // Toolbar

        if ($this->object->canWrite()) {
            $button = \ilLinkButton::getInstance();
            $button->setUrl($this->ctrl->getLinkTarget($this, 'startWriter'));
            $button->setCaption($this->plugin->txt(empty($essay) ? 'start_writing' : 'continue_writing'), false);
            $button->setPrimary(true);
            $this->toolbar->addButtonInstance($button);

        } elseif ($this->object->canReviewWrittenEssay() && isset($essay) && empty($essay->getWritingAuthorized())) {
            $button = \ilLinkButton::getInstance();
            $button->setUrl($this->ctrl->getLinkTarget($this, 'startWritingReview'));
            $button->setCaption($this->plugin->txt('review_writing'), false);
            $this->toolbar->addButtonInstance($button);
        }

        // Instructions

        $inst_parts = [];
        $properties = [];

        if (!empty($this->task->getDescription()) && !$is_after_writing) {
            $inst_parts[] = $this->uiFactory->legacy($this->displayText($this->task->getDescription()));
        }

        if ($is_before_writing) {
            $properties[$this->plugin->txt('writing_period')] = $this->uiFactory->button()->shy(
                $this->data->formatPeriod($this->task->getWritingStart(), $writing_end)
                . ' ' . $this->plugin->txt('refresh_page'),
                $this->ctrl->getLinkTarget($this)
            );
        }
        elseif (!$is_written) {
            $properties[$this->plugin->txt('writing_period')] = $this->data->formatPeriod($this->task->getWritingStart(), $writing_end);
        }
        if (isset($essay) && $essay->getLocation() !== null) {
            $properties[$this->plugin->txt("location")] = ($location = $this->task_repo->getLocationById($essay->getLocation())) !== null ? $location->getTitle() : " - ";
        }

        if (!empty($properties)) {
            $inst_parts = empty($inst_parts) ? [] : array_merge($inst_parts, [$this->uiFactory->divider()->horizontal()]);
            $inst_parts[] = $this->uiFactory->listing()->descriptive($properties);
        }

        if ($is_after_writing) {
            $divider = $this->uiFactory->divider()->vertical();

            if (!empty($this->task->getDescription())) {
                $inst_parts[] = $this->uiFactory->button()->shy(
                    $this->plugin->txt('task_description'),
                    $this->ctrl->getLinkTarget($this, 'viewDescription')
                );
            }
            if (!empty($this->task->getClosingMessage()) && $is_written) {
                $inst_parts = empty($inst_parts) ? [] : array_merge($inst_parts, [$divider]);
                $inst_parts[] = $this->uiFactory->button()->shy(
                    $this->plugin->txt('closing_message'),
                    $this->ctrl->getLinkTarget($this, 'viewClosingMessage')
                );
            }
            if (!empty($this->task->getInstructions())) {
                $inst_parts = empty($inst_parts) ? [] : array_merge($inst_parts, [$divider]);
                $inst_parts[] = $this->uiFactory->button()->shy(
                    $this->plugin->txt('view_instructions'),
                    $this->ctrl->getLinkTarget($this, 'viewInstructions')
                );
            }
            if (!empty($this->task_repo->getInstructionResource($this->object->getId()))) {
                $inst_parts = empty($inst_parts) ? [] : array_merge($inst_parts, [$divider]);
                $inst_parts[] = $this->uiFactory->button()->shy(
                    $this->plugin->txt('download_instructions'),
                    $this->ctrl->getLinkTarget($this, 'downloadInstructions')
                );
            }
        }
        elseif (!$is_before_writing) {
            if (!empty($this->task->getInstructions())) {
                $inst_parts[] = $this->uiFactory->button()->standard(
                    $this->plugin->txt('view_instructions'),
                    $this->ctrl->getLinkTarget($this, 'viewInstructions')
                );
            }
            if (!empty($this->task_repo->getInstructionResource($this->object->getId()))) {
                $inst_parts[] = $this->uiFactory->item()->standard(
                    $this->uiFactory->link()->standard(
                        $this->plugin->txt('download_instructions'),
                        $this->ctrl->getLinkTarget($this, "downloadInstructions")
                    )
                )->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'));
            }
        }

        $contents[] = $this->uiFactory->panel()->standard($this->plugin->txt('task_instructions'), $inst_parts);

        // Resources
        $writing_resources = [];
        $solution_resources = [];

        $repo = LongEssayAssessmentDI::getInstance()->getTaskRepo();
        $resources = $repo->getResourceByTaskId($this->object->getId(), [Resource::RESOURCE_TYPE_URL, Resource::RESOURCE_TYPE_FILE]);

        /** @var Resource $resource */
        foreach ($resources as $resource) {
            $item = null;
            if ($this->data->isResourceAvailable($resource, $this->task)) {

                if ($resource->getType() == Resource::RESOURCE_TYPE_FILE && $resource->getFileId() !== null) {
                    $resource_file = $this->dic->resourceStorage()->manage()->find($resource->getFileId());
                    if ($resource_file !== null) {
                        $revision = $this->dic->resourceStorage()->manage()->getCurrentRevision($resource_file);
                        $this->ctrl->setParameter($this, "resource_id", $resource->getId());

                        $item = $this->uiFactory->item()->standard(
                            $this->uiFactory->link()->standard(
                                $resource->getTitle(),
                                $this->ctrl->getLinkTarget($this, "downloadResourceFile")
                            )
                        )   ->withDescription((string) $resource->getDescription())
                            ->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'))
                            ->withProperties(
                                array(
                                    $this->lng->txt("filename") => $revision->getInformation()->getTitle(),
                                    $this->plugin->txt("resource_availability") => $this->plugin->txt('resource_availability_' . $resource->getAvailability())
                                )
                            );
                    }
                } else {
                    $item = $this->uiFactory->item()->standard($this->uiFactory->link()->standard($resource->getTitle(), $resource->getUrl()))
                                            ->withDescription((string) $resource->getDescription())
                                            ->withLeadIcon($this->uiFactory->symbol()->icon()->standard('webr', $this->lng->txt("link"), 'medium'))
                                            ->withProperties(array(
                                                $this->plugin->txt("website") => $resource->getUrl(),
                                                $this->plugin->txt("resource_availability") => $this->plugin->txt('resource_availability_' . $resource->getAvailability())));
                }

                if ($item !== null) {
                    if ($resource->getAvailability() == Resource::RESOURCE_AVAILABILITY_AFTER) {
                        $solution_resources[] = $item;
                    } else {
                        $writing_resources[] = $item;
                    }
                }

            }
        }
        if (!empty($writing_resources)) {
            if ($is_after_writing) {
                $popover = $this->uiFactory->popover()->listing($writing_resources)->withTitle($this->plugin->txt("tab_resources"));
                $contents[] = $popover;
                $button = $this->uiFactory->button()->shy($this->plugin->txt('show_resources'),'#')
                                  ->withOnClick($popover->getShowSignal());
                $contents[] = $this->uiFactory->panel()->standard($this->plugin->txt("tab_resources"), $button);
            }
            else {
                $contents[] = $this->uiFactory->panel()->standard($this->plugin->txt("tab_resources"), $writing_resources);

            }
        }

        // Result
        $result_items = [];
        $properties = [];

        if ($this->object->canViewResult()) {
            $result_items[] = $this->uiFactory->legacy($this->data->formatFinalResult($essay));
            $result_items[] = $this->uiFactory->divider()->horizontal();
        }
        else {
            $properties[$this->plugin->txt('label_available')] = $this->data->formatResultAvailability($this->task);
        }

        if(!empty($this->task->getReviewStart()) || !empty($this->task->getReviewEnd())) {
            $properties[$this->plugin->txt('review_period')] =
                $this->data->formatPeriod($this->task->getReviewStart(), $this->task->getReviewEnd());
        }
        $result_items[] = $this->uiFactory->listing()->descriptive($properties);

        if (isset($essay)) {
            if ($this->object->canReviewWrittenEssay() && !empty($essay->getWritingAuthorized())) {
                $result_items[] = $this->uiFactory->item()->standard(
                    $this->uiFactory->link()->standard(
                        $this->plugin->txt('download_written_submission'),
                        $this->ctrl->getLinkTarget($this, "downloadWriterPdf")
                    )
                )->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'));
            }
            if ($this->object->canReviewCorrectedEssay()) {
                $result_items[] = $this->uiFactory->item()->standard(
                    $this->uiFactory->link()->standard(
                        $this->plugin->txt('download_corrected_submission'),
                        $this->ctrl->getLinkTarget($this, "downloadCorrectedPdf")
                    )
                )->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'));
            }
        }
        if ($this->object->canDownloadCorrectionReports() && $this->corrector_admin_service->hasCorrectionReports()) {
            $result_items[] = $this->uiFactory->item()->standard(
                $this->uiFactory->link()->standard(
                    $this->plugin->txt('download_correction_reports'),
                    $this->ctrl->getLinkTarget($this, "downloadCorrectionReportsPdf")
                )
            )->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'));
        }

        $contents[] = $this->uiFactory->panel()->standard($this->plugin->txt('result'), $result_items);

        // Solution
        $solution_items = [];
        if ($this->object->canViewSolution()) {
            if (!empty($this->task->getSolution())) {
                $solution_items[] = $this->uiFactory->button()->standard(
                    $this->plugin->txt('view_solution'),
                    $this->ctrl->getLinkTarget($this, 'viewSolution')
                );
            }
            if (!empty($this->localDI->getTaskRepo()->getSolutionResource($this->object->getId()))) {
                $solution_items[] = $this->uiFactory->item()->standard(
                    $this->uiFactory->link()->standard(
                        $this->plugin->txt('download_solution'),
                        $this->ctrl->getLinkTarget($this, "downloadSolution")
                    )
                )->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'));
            }
            $solution_items = array_merge($solution_items, $solution_resources);

            if (!empty($solution_items)) {
                $contents[] = $this->uiFactory->panel()->standard($this->plugin->txt('task_solution'), $solution_items);
            }
        }

        // Output to the page
        $this->tpl->setContent($this->renderer->render($contents));
    }


    /**
     * Start the Writer Web app
     */
    protected function startWriter()
    {
        if ($this->object->canWrite()) {
            $context = new WriterContext();
            $context->init((string) $this->dic->user()->getId(), (string) $this->object->getRefId());
            $service = new Service($context);
            $service->openFrontend();
        } else {
            $this->raisePermissionError();
        }
    }

    /**
     * Start the Writer Web app for review
     */
    protected function startWritingReview()
    {
        if ($this->object->canReviewWrittenEssay()) {
            $context = new WriterContext();
            $context->init((string) $this->dic->user()->getId(), (string) $this->object->getRefId());
            $service = new Service($context);
            $service->openFrontend();
        } else {
            $this->raisePermissionError();
        }
    }


    /**
     * Download a generated pdf from the processed written text
     */
    protected function downloadWriterPdf()
    {
        if ($this->object->canViewWriterScreen()) {
            $service = $this->localDI->getWriterAdminService($this->object->getId());
            $repoWriter = $this->localDI->getWriterRepo()->getWriterByUserIdAndTaskId($this->dic->user()->getId(), $this->object->getId());

            $filename = 'task' . $this->object->getId() . '_writer' . $repoWriter->getId(). '-writing.pdf';
            ilUtil::deliverData($service->getWritingAsPdf($this->object, $repoWriter), $filename, 'application/pdf');

            //$this->common_services->fileHelper()->deliverData($service->getWritingAsPdf($this->object, $repoWriter), $filename, 'application/pdf');
        } else {
            $this->raisePermissionError();
        }
    }

    /**
     * Download a generated pdf from the processed written text
     */
    protected function downloadCorrectedPdf()
    {
        if ($this->object->canReviewCorrectedEssay()) {
            $service = $this->localDI->getCorrectorAdminService($this->object->getId());
            $repoWriter = $this->localDI->getWriterRepo()->getWriterByUserIdAndTaskId($this->dic->user()->getId(), $this->object->getId());

            $filename = 'task' . $this->object->getId() . '_writer' . $repoWriter->getId(). '-correction.pdf';
            $this->common_services->fileHelper()->deliverData($service->getCorrectionAsPdf($this->object, $repoWriter, null, false, true), $filename, 'application/pdf');
        } else {
            $this->raisePermissionError();
        }
    }

    /**
     * Download a generated pdf from the processed written text
     */
    protected function downloadCorrectionReportsPdf()
    {
        if ($this->object->canDownloadCorrectionReports()) {
            $service = $this->localDI->getCorrectorAdminService($this->object->getId());

            $filename = 'task' . $this->object->getId() . '-reports.pdf';
            $this->common_services->fileHelper()->deliverData(
                $service->getCorrectionReportsAsPdf($this->object), $filename, 'application/pdf');
        } else {
            $this->raisePermissionError();
        }
    }

    /**
     * @return ?int
     */
    protected function getResourceId(): ?int
    {
        if (isset($_GET["resource_id"]) && is_numeric($_GET["resource_id"])) {
            return (int) $_GET["resource_id"];
        }
        return null;
    }


    protected function downloadResourceFile()
    {
        $identifier = "";
        if (($resource_id = $this->getResourceId()) !== null) {
            $resource_admin = new ResourceAdmin($this->object->getId());
            $resource = $resource_admin->getResource($resource_id);

            if ($resource->getTaskId() != $this->object->getId()) {
                $this->raisePermissionError();
            }
            if (!$this->data->isResourceAvailable($resource, $this->task)) {
                $this->raisePermissionError();
            }

            if ($resource->getType() == Resource::RESOURCE_TYPE_FILE && is_string($resource->getFileId())) {
                $this->common_services->fileHelper()->deliverResource($resource->getFileId(), 'attachment');
            }
        }
    }

    protected function viewDescription()
    {
        $this->toolbar->addComponent($this->uiFactory->button()->standard(
            $this->lng->txt('back'),
            $this->ctrl->getLinkTarget($this, 'showStartPage')
        ));

        $content = $this->uiFactory->panel()->standard(
            $this->plugin->txt('task_description'),
            $this->uiFactory->legacy($this->displayText($this->task->getDescription()))
        );
        $this->tpl->setContent($this->renderer->render($content));
    }

    protected function viewClosingMessage()
    {
        $this->toolbar->addComponent($this->uiFactory->button()->standard(
            $this->lng->txt('back'),
            $this->ctrl->getLinkTarget($this, 'showStartPage')
        ));

        $content = $this->uiFactory->panel()->standard(
            $this->plugin->txt('closing_message'),
            $this->uiFactory->legacy($this->displayText($this->task->getClosingMessage()))
        );
        $this->tpl->setContent($this->renderer->render($content));
    }

    protected function viewInstructions()
    {
        $this->toolbar->addComponent($this->uiFactory->button()->standard(
            $this->lng->txt('back'),
            $this->ctrl->getLinkTarget($this, 'showStartPage')
        ));

        $content = [];
        if ($this->data->isInRange(time(), $this->data->dbTimeToUnix($this->task->getWritingStart()), null)) {
            $content[] = $this->uiFactory->panel()->standard(
                $this->plugin->txt('task_instructions'),
                $this->uiFactory->legacy($this->displayText($this->task->getInstructions()))
            );
        }

        $this->tpl->setContent($this->renderer->render($content));
    }

    protected function downloadInstructions()
    {
        if ($this->data->isInRange(time(), $this->data->dbTimeToUnix($this->task->getWritingStart()), null)) {
            if (!empty($resource = $this->task_repo->getInstructionResource($this->object->getId()))) {
                $this->common_services->fileHelper()->deliverResource($resource->getFileId(), 'attachment');
            }
        }
    }
    
    protected function viewSolution()
    {
        $this->toolbar->addComponent($this->uiFactory->button()->standard(
            $this->lng->txt('back'),
            $this->ctrl->getLinkTarget($this, 'showStartPage')
        ));

        $content = [];
        if ($this->object->canViewSolution()) {
            $content[] = $this->uiFactory->panel()->standard(
                $this->plugin->txt('task_solution'),
                $this->uiFactory->legacy($this->displayText($this->task->getSolution()))
            );
        }

        $this->tpl->setContent($this->renderer->render($content));
    }


    protected function downloadSolution()
    {
        if ($this->object->canViewSolution()) {
            if (!empty($resource = $this->task_repo->getSolutionResource($this->object->getId()))) {
                $this->common_services->fileHelper()->deliverResource($resource->getFileId(), 'attachment');
            }
        }
    }
}
