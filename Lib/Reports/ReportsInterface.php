<?php

namespace BisonLab\ReportsBundle\Lib\Reports;

interface ReportsInterface
{
    public function getDescription(): string;

    public function getRequiredOptions(): array;

    public function allowRunReport(): bool;

    public function runFixedReport($config = null);
}
