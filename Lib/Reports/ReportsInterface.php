<?php

namespace BisonLab\ReportsBundle\Lib\Reports;

interface ReportsInterface
{
    public function getReports();

    public function getPickerFunctions();

    public function addCriteriasToForm(&$form);
}
