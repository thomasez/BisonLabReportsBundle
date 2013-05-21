<?php

namespace RedpillLinpro\SimpleReportsBundle\Lib\Reports;

class CommonReportFunctions
{

    public function getManager()
    {
        return $this->container->get('doctrine')->getManager();
    } 

}

