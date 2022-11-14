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
        if (!is_array($values)) {
            $values = array('id' => $values);
        }

        $route = $this->router->generate($path, $values);
        $url = '<A HREF="' . $route . '">' . array_values($values)[0] . '</a>';
    
        return $url;
    }
}
