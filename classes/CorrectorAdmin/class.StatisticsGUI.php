<?php

namespace ILIAS\Plugin\LongEssayAssessment\CorrectorAdmin;

use ILIAS\Plugin\LongEssayAssessment\BaseGUI;
use ILIAS\Plugin\LongEssayAssessment\Data\Corrector\CorrectorRepository;
use ILIAS\Plugin\LongEssayAssessment\Data\Essay\EssayRepository;
use ILIAS\Plugin\LongEssayAssessment\Data\Object\ObjectRepository;
use ILIAS\Plugin\LongEssayAssessment\CorrectorAdmin\CorrectorAdminService;
use ILIAS\UI\Component\Table\PresentationRow;
use ILIAS\Plugin\LongEssayAssessment\Data\Corrector\Corrector;
use ILIAS\Data\UUID\Factory as UUID;
use ilObjUser;
use ILIAS\UI\Component\Table\Presentation;

abstract class StatisticsGUI extends BaseGUI
{
    protected \ilAccessHandler $access;
    protected \ilUIService $ui_service;
    protected EssayRepository $essay_repo;
    protected ObjectRepository $object_repo;
    protected CorrectorAdminService $service;
    protected array $grade_level = [];
    protected array $summaries = [];
    protected array $essays = [];
    protected array $usernames = [];
    protected array $objects = [];

    public function __construct(\ilObjLongEssayAssessmentGUI $objectGUI)
    {
        parent::__construct($objectGUI);
        $this->service = $this->localDI->getCorrectorAdminService($this->object->getId());
        $this->ui_service = $this->dic->uiService();
        $this->essay_repo = $this->localDI->getEssayRepo();
        $this->object_repo = $this->localDI->getObjectRepo();
        $this->access = $this->dic->access();
    }

    protected function buildCSV(array $records, bool $has_obj_id = true) : string
    {
        $grade_levels = array_unique(array_merge(...array_map(fn (array $x) => array_keys($x['grade_statistics']), $records)));

        $csv = new \ilCSVWriter();
        $csv->setDoUTF8Decoding(true);
        $csv->setSeparator(';');
        $csv->setDelimiter('"');
        $csv->addColumn($this->lng->txt('login'));
        $csv->addColumn($this->lng->txt('firstname'));
        $csv->addColumn($this->lng->txt('lastname'));
        $csv->addColumn($this->lng->txt('matriculation'));
        if($has_obj_id) {
            $csv->addColumn($this->lng->txt('object'));
        }
        $csv->addColumn($this->plugin->txt('count'));
        $csv->addColumn($this->plugin->txt('finalized'));
        $csv->addColumn($this->plugin->txt('essay_not_attended'));
        $csv->addColumn($this->plugin->txt('essay_passed'));
        $csv->addColumn($this->plugin->txt('essay_not_passed'));
        $csv->addColumn($this->plugin->txt('essay_not_passed_quota'));
        $csv->addColumn($this->plugin->txt('essay_average_points'));

        foreach($grade_levels as $value) {
            $csv->addColumn($value);
        }

        foreach($records as $record) {
            if(!isset($record["usr_id"])) {
                continue;
            }
            $csv->addRow();
            $user = new ilObjUser($record["usr_id"]);
            $statistic = $record["statistic"];

            $csv->addColumn($user->getLogin());
            $csv->addColumn($user->getFirstname());
            $csv->addColumn($user->getLastname());
            $csv->addColumn($user->getMatriculation());
            if($has_obj_id) {
                $csv->addColumn($this->objects[$record["obj_id"]]['title']);
            }
            $csv->addColumn((string)$statistic[CorrectorAdminService::STATISTIC_COUNT]);
            $csv->addColumn((string)$statistic[CorrectorAdminService::STATISTIC_FINAL]);
            $csv->addColumn((string)($statistic[CorrectorAdminService::STATISTIC_NOT_ATTENDED] ?? 0));
            $csv->addColumn((string)$statistic[CorrectorAdminService::STATISTIC_PASSED]);
            $csv->addColumn((string)$statistic[CorrectorAdminService::STATISTIC_NOT_PASSED]);
            $csv->addColumn($statistic[CorrectorAdminService::STATISTIC_NOT_PASSED_QUOTA] !== null ? sprintf('%.2f', $statistic[CorrectorAdminService::STATISTIC_NOT_PASSED_QUOTA]) : "");
            $csv->addColumn($statistic[CorrectorAdminService::STATISTIC_AVERAGE] !== null ? sprintf('%.2f', $statistic[CorrectorAdminService::STATISTIC_AVERAGE]) : "");
            foreach($grade_levels as $value) {
                $csv->addColumn((string)($record['grade_statistics'][$value] ?? 0));

            }
        }
        $storage = $this->dic->filesystem()->temp();
        $basedir = ILIAS_DATA_DIR . '/' . CLIENT_ID . '/temp';
        $file = 'xlas/'. (new UUID)->uuid4AsString() . '.csv';
        $storage->write($file, $csv->getCSVString());

        return $basedir . '/' . $file;
    }

    protected function buildPresentationTable() : Presentation
    {
        return $this->uiFactory->table()->presentation(
            $this->plugin->txt('statistic'), //title
            [],
            function (PresentationRow $row, $record, $ui_factory, $environment) { //mapping-closure
                if(count($record) == 1) {
                    return [$this->uiFactory->divider()->horizontal()->withLabel("<h4>" . $record["title"] . "</h4>")];
                }

                $statistic = $record["statistic"];
                $properties = [];
                $fproperties = [];
                $pseudonym = [];
                $properties[$record['count']] = (string)$statistic[CorrectorAdminService::STATISTIC_COUNT];
                $properties[$record['final']] = (string)$statistic[CorrectorAdminService::STATISTIC_FINAL];
                if($statistic[CorrectorAdminService::STATISTIC_NOT_ATTENDED] !== null) {
                    $properties[$this->plugin->txt('essay_not_attended')] = (string)$statistic[CorrectorAdminService::STATISTIC_NOT_ATTENDED];
                }
                $properties[$this->plugin->txt('essay_passed')] = (string)$statistic[CorrectorAdminService::STATISTIC_PASSED];
                $properties[$this->plugin->txt('essay_not_passed')] = (string)$statistic[CorrectorAdminService::STATISTIC_NOT_PASSED];

                if($statistic[CorrectorAdminService::STATISTIC_NOT_PASSED_QUOTA] !== null) {
                    $properties[$this->plugin->txt('essay_not_passed_quota')] = sprintf('%.2f', $statistic[CorrectorAdminService::STATISTIC_NOT_PASSED_QUOTA]);
                }

                if($statistic[CorrectorAdminService::STATISTIC_AVERAGE] !== null) {
                    $properties[$this->plugin->txt('essay_average_points')] = sprintf('%.2f', $statistic[CorrectorAdminService::STATISTIC_AVERAGE]);
                }

                foreach($record['grade_statistics'] as $key => $value) {
                    $fproperties[$key . " "/*Hack to ensure a string*/] = (string)$value;
                }

                if(isset($record["pseudonym"])) {
                    $pseudonym = [$this->plugin->txt("pseudonym") => implode(", ", $record["pseudonym"])];
                }

                return $row
                    ->withHeadline($record['title'])
                    ->withImportantFields($properties)
                    ->withContent($ui_factory->listing()->descriptive(array_merge($pseudonym, $properties)))
                    ->withFurtherFieldsHeadline($this->plugin->txt('grade_distribution'))
                    ->withFurtherFields($fproperties);
            }
        );
    }

    protected function loadObjectsInContext() : void
    {
        $objects = $this->object_services->iliasContext()->getAllEssaysInThisContext();
        $this->objects = array_filter($objects, fn ($object) => ($this->access->checkAccess("maintain_correctors", '', $object["ref_id"])));
    }

    protected function loadDataForObject($obj_id) : void
    {
        $this->summaries[$obj_id] = $this->essay_repo->getCorrectorSummariesByTaskId($obj_id);
        $this->grade_level[$obj_id] = $this->object_repo->getGradeLevelsByObjectId($obj_id);
        $this->essays[$obj_id] = $this->essay_repo->getEssaysByTaskId($obj_id);
    }

    protected function getGradeStatistic(array $statistic, int $obj_id) : array
    {
        $grade_statistics = [];
        foreach($this->grade_level[$obj_id] as $level) {
            $grade_statistics[$level->getGrade()] = $statistic[CorrectorAdminService::STATISTIC_COUNT_BY_LEVEL][$level->getId()] ?? 0;
        }
        return $grade_statistics;
    }

    protected function getGradeStatisticOverAll(array $statistic) : array
    {
        $grade_statistic = [];
        foreach(array_merge(...$this->grade_level) as $level) {
            if(isset($grade_statistic[$level->getGrade()])) {
                $grade_statistic[$level->getGrade()] += $statistic[CorrectorAdminService::STATISTIC_COUNT_BY_LEVEL][$level->getId()] ?? 0;
            } else {
                $grade_statistic[$level->getGrade()] = $statistic[CorrectorAdminService::STATISTIC_COUNT_BY_LEVEL][$level->getId()] ?? 0;
            }

        }
        return $grade_statistic;
    }
}