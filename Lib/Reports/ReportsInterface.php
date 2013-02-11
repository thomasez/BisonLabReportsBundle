<?php

namespace RedpillLinpro\SimpleReportsBundle\Lib\Reports;

interface ReportsInterface
{

    public function getPickerFunctions();

    public function addCriteriasToForm(&$form);

}
