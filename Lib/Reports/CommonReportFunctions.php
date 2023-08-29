<?php

namespace BisonLab\ReportsBundle\Lib\Reports;

trait CommonReportFunctions
{
    public function getRequiredOptions(): array
    {
        return [''];
    }

    public function allowRunReport(): bool
    {
        return true;
    }

    public function createUrlFor($path, $values)
    {
        if (!isset($this->router))
            throw new \Exception("The report does not autwiree a router to create URLs with.");

        if (!is_array($values)) {
            $values = array('id' => $values);
        }

        $route = $this->router->generate($path, $values);
        $url = '<A HREF="' . $route . '">' . array_values($values)[0] . '</a>';
    
        return $url;
    }
}
