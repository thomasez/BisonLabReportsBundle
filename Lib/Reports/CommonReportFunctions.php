<?php

namespace BisonLab\ReportsBundle\Lib\Reports;

class CommonReportFunctions
{

    private $router;
    private $manager;

    public function getManager()
    {
        if (!$this->manager) {
            $this->manager = $this->container->get('doctrine')->getManager();
        }
        return $this->manager;
    } 

    public function getRouter()
    {
        if (!$this->router) {
            $this->router = $this->container->get('router');
        }
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
