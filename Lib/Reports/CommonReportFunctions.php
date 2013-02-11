<?php

namespace RedpillLinpro\SimpleReportsBundle\Lib\Reports;

class CommonReportFunctions
{

    public function getEntityManager()
    {
        return $this->container->get('doctrine')->getEntityManager();
    } 

}

