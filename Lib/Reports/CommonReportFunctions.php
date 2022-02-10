<?php

namespace BisonLab\ReportsBundle\Lib\Reports;

class CommonReportFunctions
{
    public function getManager()
    {
        return $this->entityManager;
    } 

    public function getRouter()
    {
        return $this->router;
    }

    public function createUrlFor($path, $values)
    {
        if (!is_array($values)) {
            $values = array('id' => $values);
        }

        $router = $this->getRouter();
        $route = $router->generate($path, $values);
        $url = '<A HREF="' . $route . '">' . array_values($values)[0] . '</a>';
    
        return $url;
    }
}
