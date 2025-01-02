<?php

namespace BisonLab\ReportsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use BisonLab\ReportsBundle\Service\Reports;

/**
 * User controller.
 */
#[Route(path: '/{access}/bisonlab_reports', defaults: ['access' => 'web'], requirements: ['access' => 'web|rest|ajax'])]
class ReportsController extends AbstractController
{
    private $reports;
    private $translator;

    public function __construct(Reports $reports, TranslatorInterface $translator)
    { 
        $this->reports = $reports;
        $this->translator = $translator;
    }

    /**
     * Lists all available reports
     */
    #[Route(path: '/', name: 'reports')]
    public function indexAction($access)
    {
        $reports = $this->reports;

        $report_form_builder = $this->createReportFormBuilder($reports->getReports());
        $reports->addCriteriasToForm($report_form_builder);
        // Gotta add criterias filtering.
        $criterias_config = [];
        foreach ($reports->getReports() as $name => $report) {
            // No criterias to add, do not bother.
            if (method_exists($report, 'getCriterias')) {
                foreach ($report->getCriterias() as $c) {
                    // I am sure there is a shorthand for this.
                    if (!isset($criterias_config[$c]))
                        $criterias_config[$c] = [];
                    $criterias_config[$c][] = $name;
                }
            }
        }

        $reports->addOutputChoicesToForm($report_form_builder);

        return $this->render('@BisonLabReports/Reports/index.html.twig',
            array(
            'criterias_config' => $criterias_config,
            'report_form' => $report_form_builder->getForm()->createView(),
        ));
    }

    /**
     * Run report
     */
    #[Route(path: '/run', name: 'reports_run_fixed', methods: ['POST'])]
    public function runFixedAction(Request $request, $access)
    {
        $config = $request->request->all('form');
        $report_result = $this->reports->runFixedReport($config);

        // All is just done and finished.
        if (true === $report_result) {
            return new Response('', 200);
        }

        // We do presume the object returned is a response object.
        if ('object' === gettype($report_result) && $report_result instanceof Response ) {
            return $report_result;
        }

        $data = array();
        $header = array('Nothing found');
        if (!empty($report_result) && isset($report_result['data'])) {
            $data = $report_result['data'];
            // We got da web.
            if (!isset($report_result['header']) || !$header = $report_result['header']) {
                $header = array_keys($report_result['data'][0]);
            }
        }

        if (isset($config['store_server'])) {
            $this->addFlash('info', "Report stored.");
            return $this->redirect($this->generateUrl('reports'));
        }

        return $this->render('@BisonLabReports/Reports/run.html.twig',
            array(
                'header' => $header,
                'report' => $config,
                'data'   => $data
            )
        );
    }

    /**
     * Not in use, old relic and seems to be a good plan somehow.
     */
    #[Route(path: '/run_compiled', name: 'reports_run_compiled', methods: ['POST'])]
    public function runCompiledAction(Request $request, $access)
    {
        $config = $request->request->all('form');

        $report_result = $this->reports->runCompiledReport($config);
        if (!isset($data['header']) || !$header = $data['header']) {
            $header = array_keys($report_result['data'][0]);
        }
        return $this->render('@BisonLabReports/Reports/run.html.twig',
            $report_result);
    }

    private function createReportFormBuilder($reports)
    {
        $choices = array();
        $required = array();
        foreach ($reports as $r => $c) {
            $choices[$c->getDescription()] = $r;
        }

        $report_form_builder = $this->createFormBuilder()
            ->add('report', ChoiceType::class, array('choices' => $choices))
            ->add('filename', TextType::class, array('required' => false))
            ->add('delimiter', ChoiceType::class,
                array('label' => 'Field delimiter', 'choices' => array(';' => ';', ',' => ',', 'TAB' => 'TAB')))
            ->add('store_server', CheckboxType::class,
                array('required' => false, 'label' => $this->translator->trans('Store the file on the server')));
            ;
        return $report_form_builder;
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', HiddenType::class)
            ->getForm()
        ;
    }
}
