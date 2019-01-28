<?php

namespace BisonLab\ReportsBundle\Service;

use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class Reports 
{
    private $report_classes = array();
    private $picker_list = array();
    private $report_list = array();
    private $default_filestore = null;
    private $container = null;

    public function __construct($container, $report_classes = array(), $default_filestore = null)
    {
        $this->container         = $container;
        $this->default_filestore = $default_filestore;

        foreach ($report_classes as $class) {
            $rep_obj = new $class($container, array());

            $this->report_classes[] = $rep_obj;
            $pickers = $rep_obj->getPickerFunctions();
            foreach ($pickers as $p => $config) {
                if (!isset($config['class']))  $config['class'] = $class;
                $this->picker_list[$p] = $config;
            }

            $reports = $rep_obj->getReports();
            foreach ($reports as $r => $config) {
                if (!isset($config['class']))  $config['class'] = $class;
                $this->report_list[$r] = $config;
            }
        }
    }

    /*
     * Cheating? maybe. But gotta security check.
     */
    public function getReports($all = false)
    {
        if ($all)
            return $this->report_list;
        $reports = array();
        foreach ($this->report_list as $n => $r) {
            if (isset($r['role'])) {
                if ($this->container->get('security.authorization_checker')
                        ->isGranted($r['role'])) {
                    $reports[$n] = $r;
                }
            } else {
                $reports[$n] = $r;
            }
        }
        return $reports;
    }

    public function getPickers()
    {
        return $this->picker_list;
    }

    public function runFixedReport($config)
    {
        // First, pick the objects.
        $report = $config['report'];
        if (!isset($this->report_list[$report])) {
            throw new InvalidArgumentException('There are no such report');
        }
        $report_config = $this->report_list[$report];
        if (isset($report_config['role']) &&
            !$this->container->get('security.authorization_checker')
                    ->isGranted($report_config['role'])) {
            throw new \Exception("No will do");
        }

        $report_class = new $report_config['class']($this->container);
        $config = array_merge($report_config, $config);

        // Run the report:
        $report_result = $report_class->runFixedReport($config);

        // Run the filter: (Coming later)
        if (isset($config['store_server'])) {
            // No filename, nothing to do?
            if (!isset($config['filename']))
                $config['filename'] = "report_from_web.csv";
            if (strlen(dirname($config['filename']) < 2))
                $config['filename'] = $this->default_filestore
                    . "/" . $config['filename'];
        }

        switch ($config['output_method']) {
            case 'web':
                return $report_result;
                break;
            case 'csv':
                return isset($config['store_server']) ? 
                    $this->printToCsvFile($config, $report_result)
                    : $this->sendAsCsv($config, $report_result);
                break;
            case 'xcsv':
                return isset($config['store_server']) ? 
                    // This actually does not exist..
                    $this->printToCsvFile($config, $report_result)
                    : $this->sendAsXCsv($config, $report_result);
                break;
            case 'xls2007':
                return isset($config['store_server']) ? 
                    // TODO: Create this one
                    $this->printToXls2007File($config, $report_result)
                    : $this->sendAsXls2007($config, $report_result);
                break;
            case 'xls5':
                return isset($config['store_server']) ? 
                    // TODO: Create this one
                    $this->printToXls5File($config, $report_result)
                    : $this->sendAsXls5($config, $report_result);
                break;
            case 'ods':
                return isset($config['store_server']) ? 
                    // TODO: Create this one
                    $this->printToXls5File($config, $report_result)
                    : $this->sendAsOds($config, $report_result);
                break;
            case 'pdf':
                return isset($config['store_server']) ? 
                    // TODO: Create this one
                    $this->printToPdf($config, $report_result)
                    : $this->sendAsPdf($config, $report_result);
                break;
        }
    }

    public function runCompiledReport($config)
    {
        // First, pick the objects.
        $picker = $config['pickers'];
        $picker_config = $this->picker_list[$picker];
        $report_class = new $picker_config['class']($this->container);

        // Run the picker:
        $data = $report_class->$picker($config);

        // Run the filter: (Coming later)

        // Serialize everything;
        $serializer = $this->container->get('jms_serializer');
        // $encoders = array();
        // $normalizers = array(new GetSetMethodNormalizer());
        // $serializer = new Serializer($normalizers, $encoders);
        // $serializer->normalize($data);

        // Output it all? CSV to file or just return the stuff to 
        // the controller if web.

        if ($config['output_method'] == "web") {
            return $serializer->serialize($data, 'json');
            return $data;
        }
    }

    /* 
     * This can just as well be hard coded. This is common for all bundles
     * and report types anyway.
     */
    public function addOutputChoicesToForm(&$form)
    {
        $form->add('output_method', ChoiceType::class, array(
            'choices_as_values' => true,
            'choices' => array(
                'Web'                   => 'web',
                'CSV'                   => 'csv',
                'OpenOffice Calc'       => 'ods',
                'XLS 2007'              => 'xls2007',
                'XLS 5'                 => 'xls5',
                'CSV to server storage' => 'store_csv',
                // Not yet, have to decide on a renderer and make it available
                // somehow.
                // https://github.com/PHPOffice/PHPExcel/blob/develop/Examples/01simple-download-pdf.php
                // 'pdf' => 'PDF', 
                // Not in Luiggios Bundle 'xcsv' => 'xCSV', 
            )));
    }

    public function addCriteriasToForm(&$form)
    {
        foreach ($this->report_classes as $class) {
            $class->addCriteriasToForm($form);
        }
    }

    public function sendAsCsv($config, $report_result)
    {
        if (isset($config['filename'])) 
            $filename = $config['filename'];
        else
            $filename = "report.csv";
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment;filename='.$filename);

        $output_file = fopen('php://output', 'w');

        $this->createCsv($output_file, $config, $report_result);

        fclose($output_file);
        return true;
    }

    public function printToCsvFile($config, $report_result)
    {
        if (!isset($config['filename'])) 
          throw new \RuntimeException("Can not write to a file with no name.");

        if (!$output_file = fopen($config['filename'], 'w')) {
          throw new \RuntimeException("Could not open file " 
                . $config['filename'] . " for writing");
        }
        $this->createCsv($output_file, $config, $report_result);
        fclose($output_file);
    }

    public function createCsv(&$output_file, $config, $report_result)
    {
        $delimiter = isset($config['delimiter']) ? $config['delimiter'] : ",";

        if (!isset($report_result['header']) || !$header = $report_result['header']) {
            $header = array_keys($report_result['data'][0]);
        }

        fputcsv($output_file, $header, $delimiter);

        foreach ($report_result['data'] as $line) {
            fputcsv($output_file, $line, $delimiter);
        }
    }

    public function sendAsXls2007($config, $report_result)
    {
        if (isset($config['filename'])) 
            $filename = $config['filename'];
        else
            $filename = "report.xls";
        $eobject = $this->compilePhpExelObject($config, $report_result);
        $writer = $this->container->get('phpexcel')->createWriter($eobject, 'Excel2007');

        $response = $this->container->get('phpexcel')->createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function sendAsXls5($config, $report_result)
    {
        if (isset($config['filename'])) 
            $filename = $config['filename'];
        else
            $filename = "report.xls";
        $eobject = $this->compilePhpExelObject($config, $report_result);
        $writer = $this->container->get('phpexcel')->createWriter($eobject, 'Excel5');

        $response = $this->container->get('phpexcel')->createStreamedResponse($writer);
        
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function sendAsOds($config, $report_result)
    {
        if (isset($config['filename'])) 
            $filename = $config['filename'];
        else
            $filename = "report.ods";
        $eobject = $this->compilePhpExelObject($config, $report_result);
        // Or is it the same as PHPOffice - OpenDocument
        $writer = $this->container->get('phpexcel')->createWriter($eobject, 'OpenDocument');

        $response = $this->container->get('phpexcel')->createStreamedResponse($writer);
        
        $response->headers->set('Content-Type', 'application/vnd.oasis.opendocument.spreadsheet');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function sendAsXCsv($config, $report_result)
    {
        if (isset($config['filename'])) 
            $filename = $config['filename'];
        else
            $filename = "report.csv";
        $eobject = $this->compilePhpExelObject($config, $report_result);
        $writer = $this->container->get('phpexcel')->createWriter($eobject, 'CSV');

        $response = $this->container->get('phpexcel')->createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function sendAsPdf($config, $report_result)
    {
        if (isset($config['filename'])) 
            $filename = $config['filename'];
        else
            $filename = "report.pdf";
        $eobject = $this->compilePhpExelObject($config, $report_result);
        $writer = $this->container->get('phpexcel')->createWriter($eobject, 'PDF');

        $response = $this->container->get('phpexcel')->createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'application/pdf; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function compilePhpExelObject($config, $report_result)
    {
        // TODO: Make this configureable. Either through the config argument or
        // parameters.yml/config.yml
        $eobject = $this->container->get('phpexcel')->createPHPExcelObject();

        $eobject->getProperties()->setCreator("BisonLab Reports Bundle")
                ->setLastModifiedBy("BisonLab Reports Bundle")
                ->setTitle("Report")
                ->setCategory("Report")
                ->setSubject("Report");

        if (!isset($report_result['header']) || !$header = $report_result['header']) {
            $header = array_keys($report_result['data'][0]);
        }

        $col = 0;
        $row = 1;
        foreach ($header as $head) {
            $eobject->setActiveSheetIndex(0)
                ->setCellValueExplicitByColumnAndRow($col, $row, $head);
            $col++;
        }
        $row++;

        foreach ($report_result['data'] as $line) 
        {
            $col = 0;
            foreach ($line as $coldata) {
                $eobject->setActiveSheetIndex(0)
                    ->setCellValueExplicitByColumnAndRow($col, $row, $coldata);
                $col++;
            }
            $row++;
        }

        $eobject->getActiveSheet()->setTitle('Inventory Report');
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $eobject->setActiveSheetIndex(0);

        return $eobject;
    }
}
