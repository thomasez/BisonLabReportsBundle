<?php

namespace BisonLab\ReportsBundle\Service;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\DependencyInjection\ServiceLocator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;

class Reports 
{
    private $picker_list = array();
    private $report_services = array();
    private $forms_services = array();
    private $default_filestore = null;

    public function __construct(
        private ServiceLocator $locator,
        private ParameterBagInterface $pbag,
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authChecker,
        private SerializerInterface $serializer,
        private RouterInterface $router
    ) {
        $config = $pbag->get('bisonlab_reports');
        $this->default_filestore = $config['default_filestore'] ?? null;

        foreach ($this->locator->getProvidedServices() as $rclass) {
            $rep_obj = $this->locator->get($rclass);

            if (method_exists($rep_obj, 'addCriteriasToForm')) {
                $this->forms_services[] = $rep_obj;
            }

            if (method_exists($rep_obj, 'getPickerFunctions')) {
                foreach ($rep_obj->getPickerFunctions() as $p => $config) {
                    // Somehow I have to add the service handling this?
                    $this->picker_list[$p] = $config;
                }
            }

            if (method_exists($rep_obj, 'getDescription')) {
                $this->report_services[$rclass] = $rep_obj;
            }
        }
    }

    /*
     * Cheating? maybe. But gotta security check.
     */
    public function getReports($all = false)
    {
        if ($all)
            return $this->report_services;
        $reports = array();
        foreach ($this->report_services as $n => $r) {
            if ($r->allowRunReport())
                $reports[$n] = $r;
        }
        return $reports;
    }

    /*
     * This has been an idea and not more than work in progress for years.
     */
    public function getPickers()
    {
        return $this->picker_list;
    }

    public function runFixedReport($config)
    {
        // First, pick the objects.
        $report = $config['report'];
        if (!isset($this->report_services[$report])) {
            throw new \InvalidArgumentException('There are no such report');
        }

        $report_service = $this->report_services[$report];
        if (!$report_service->allowRunReport())
            throw new \Exception("No will do");

        // Run the report:
        $report_result = $report_service->runFixedReport($config);

        // Run the filter: (Coming later)
        if (isset($config['store_server'])) {
            // No filename, nothing to do?
            if (!isset($config['filename']))
                $config['filename'] = "report_from_web";
        }

        // Remove extensions. Will be re-added later.
        if (empty($config['filename'] ?? null))
            $config['filename'] = "generated_report";
        $config['filename'] = preg_replace('/\.\w\w\w$/', '', $config['filename']);

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
                    $this->printToCsvFile($config, $report_result)
                    : $this->sendAsXCsv($config, $report_result);
                break;
            case 'xls2007':
                return isset($config['store_server']) ? 
                    $this->printToXls2007File($config, $report_result)
                    : $this->sendAsXls2007($config, $report_result);
                break;
            case 'xls5':
                return isset($config['store_server']) ? 
                    $this->printToXls5File($config, $report_result)
                    : $this->sendAsXls5($config, $report_result);
                break;
            case 'ods':
                return isset($config['store_server']) ? 
                    $this->printToXls5File($config, $report_result)
                    : $this->sendAsOds($config, $report_result);
                break;
            case 'pdf':
                return isset($config['store_server']) ? 
                    $this->printToPdf($config, $report_result)
                    : $this->sendAsPdf($config, $report_result);
                break;
        }
    }

    /*
     * Not in use, seems like a good idea, but not finished.
     */
    public function runCompiledReport($config)
    {
        // First, pick the objects.
        $picker = $config['pickers'];
        $picker_servcice = $this->picker_list[$picker];

        // Run the picker:
        $data = $picker_servcice->$picker($config);

        if ($config['output_method'] == "web") {
            return $this->serializer->serialize($data, 'json');
        }
    }

    /* 
     * This can just as well be hard coded. This is common for all bundles
     * and report types anyway.
     */
    public function addOutputChoicesToForm(&$form)
    {
        $choices = [
                'Web'                   => 'web',
                'CSV'                   => 'csv',
                'OpenOffice Calc'       => 'ods',
                'XLS 2007'              => 'xls2007',
                'XLS 5'                 => 'xls5',
                'PDF'                   => 'pdf',
                'CSV to server storage' => 'store_csv',
        ];
        $form->add('output_method', ChoiceType::class, array(
            'choices' => $choices,
            ));
    }

    public function addCriteriasToForm(&$form)
    {
        foreach ($this->forms_services as $service) {
            $service->addCriteriasToForm($form);
        }
    }

    public function sendAsCsv($config, $report_result)
    {
        $filename = $config['filename'] . ".csv" ?: "report.csv";
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

        if (strlen(dirname($config['filename']) < 2))
            $config['filename'] = $this->default_filestore
                . "/" . $config['filename'];

        if (!$output_file = fopen($config['filename'] . ".csv", 'w')) {
          throw new \RuntimeException("Could not open file " 
                . $config['filename'] . " for writing");
        }
        $this->createCsv($output_file, $config, $report_result);
        fclose($output_file);
        return true;
    }

    public function createCsv(&$output_file, $config, $report_result)
    {
        $delimiter = $config['delimiter'] ?? ",";

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
        $filename = $config['filename'] . ".xlsx" ?: "report.xlsx";
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        $response = $this->_createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function printToXls2007File($config, $report_result)
    {
        $filename = $config['filename'] . ".xlsx" ?: "report.xlsx";
        if (strlen(dirname($filename) < 2))
            $filename = $this->default_filestore
                . "/" . $filename;

        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return true;;
    }

    public function sendAsXls5($config, $report_result)
    {
        $filename = $config['filename'] . ".xls" ?: "report.xls";
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Xls');

        $response = $this->_createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function printToXls5File($config, $report_result)
    {
        $filename = $config['filename'] . ".xls" ?: "report.xls";
        if (strlen(dirname($filename) < 2))
            $filename = $this->default_filestore
                . "/" . $filename;
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save($filename);

        return true;;
    }

    public function sendAsOds($config, $report_result)
    {
        $filename = $config['filename'] . ".ods" ?: "report.ods";
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Ods');

        $response = $this->_createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'application/vnd.oasis.opendocument.spreadsheet');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function printToOdsFile($config, $report_result)
    {
        $filename = $config['filename'] . ".ods" ?: "report.ods";
        if (strlen(dirname($filename) < 2))
            $filename = $this->default_filestore
                . "/" . $filename;
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Ods');
        $writer->save($filename);

        return true;;
    }

    public function sendAsXCsv($config, $report_result)
    {
        $filename = $config['filename'] . ".csv" ?: "report.csv";
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Csv');

        $response = $this->_createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function sendAsPdf($config, $report_result)
    {
        $filename = $config['filename'] . ".pdf" ?: "report.pdf";
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);
        $writer = IOFactory::createWriter($spreadsheet, 'Mpdf');

        $response = $this->_createStreamedResponse($writer);
        $response->headers->set('Content-Type', 'application/pdf; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment;filename=' . $filename);

        return $response;
    }

    public function printToPdfFile($config, $report_result)
    {
        $filename = $config['filename'] . ".pdf" ?: "report.pdf";
        if (strlen(dirname($filename) < 2))
            $filename = $this->default_filestore
                . "/" . $filename;
        $spreadsheet = $this->compilePhpSpreadsheet($config, $report_result);

        $writer = IOFactory::createWriter($spreadsheet, 'Mpdf');
        $writer->save($filename);

        return true;;
    }

    public function compilePhpSpreadsheet($config, $report_result)
    {
        /*
         * Prepare and handle config options.
         */
        if (!isset($report_result['header'])
                || !$header = $report_result['header']) {
            $header = array_keys($report_result['data'][0]);
        }
        $title = $config['title'] ?? 'Report';
        $category = $config['category'] ?? 'Report';
        $subject = $config['subject'] ?? 'Report';
        $creator = $config['creator'] ?? 'BisonLab Reports Bundle';
        $last_mod = $config['last_mod'] ?? 'BisonLab Reports Bundle';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                ->setCreator($creator)
                ->setLastModifiedBy($last_mod)
                ->setTitle($title)
                ->setCategory($category)
                ->setSubject($subject);

        // Set active sheet index to the first sheet, so Excel opens this as
        // the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $col = 1;
        $row = 1;
        foreach ($header as $head) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($col, $row, $head);
            $col++;
        }
        $row++;

        foreach ($report_result['data'] as $line) 
        {
            $col = 1;
            foreach ($line as $coldata) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($col, $row, $coldata);
                $col++;
            }
            $row++;
        }

        $spreadsheet->getActiveSheet()->setTitle($title);

        return $spreadsheet;
    }

    private function _createStreamedResponse($writer, $headers = array())
    {
        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            }
        );
    }
}
