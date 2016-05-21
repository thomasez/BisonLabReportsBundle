<?php

namespace BisonLab\ReportsBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * User controller.
 *
 * @Route("/{access}/reports", defaults={"access" = "web"}, requirements={"web|rest"})
 */
class ReportsController extends Controller
{
    /**
     * Lists all User entities.
     * @Route("/", name="reports");
     * @Template()
     */
    public function indexAction($access)
    {
        $reports = $this->get('bisonlab_reports');

        // $reports = $reports->getReports();
        $picker_form_builder = $this->createPickerFormBuilder($reports->getPickers());
        // $picker_form_builder = $reports->addCriteriasToForm($picker_form_builder);
        $reports->addCriteriasToForm($picker_form_builder);
        $reports->addOutputChoicesToForm($picker_form_builder);

        $report_form_builder = $this->createReportFormBuilder($reports->getReports());
        $reports->addCriteriasToForm($report_form_builder);
        $reports->addOutputChoicesToForm($report_form_builder);

        return array(
            'picker_form' => $picker_form_builder->getForm()->createView(),
            'report_form' => $report_form_builder->getForm()->createView(),
        );
    }

    /**
     * Lists all User entities.
     * @Route("/run", name="reports_run_fixed");
     * @Method("POST")
     * @Template()
     */
    public function runFixedAction(Request $request, $access)
    {

        $reports = $this->get('bisonlab_reports');
        $config = $request->request->get('form');
        $report_result = $reports->runFixedReport($config);

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

        return $this->render('BisonLabReportsBundle:Reports:run.html.twig',
            array(
                'header' => $header,
                'report' => $config,
                'data'   => $data
            )
        );

    }

    /**
     * Lists all User entities.
     * @Route("/run", name="reports_run_compiled");
     * @Method("POST")
     * @Template("BisonLabReportsBundle:Reports:run.html.twig")
     */
    public function runCompiledAction(Request $request, $access)
    {
        $reports = $this->get('bisonlab_reports');
        $config = $request->request->get('form');

        $report_result = $reports->runCompiledReport($config);
        if (!isset($data['header']) || !$header = $data['header']) {
            $header = array_keys($report_result['data'][0]);
        }

    }

    private function createPickerFormBuilder($pickers)
    {
        $choices = array();
        $required = array();
        foreach ($pickers as $p => $c) {
            $choices[$p] = $c['description'];
        }

        $picker_form_builder = $this->createFormBuilder()
            ->add('pickers', ChoiceType::class, array(
                'choices' => $choices,
                'choices_as_values' => true)
                );

        return $picker_form_builder;
    }

    private function createReportFormBuilder($reports)
    {
        $choices = array();
        $required = array();
        foreach ($reports as $r => $c) {
            $choices[$r] = $c['description'];
        }

        $report_form_builder = $this->createFormBuilder()
            ->add('report', ChoiceType::class, array(
                'choices' => $choices,
                'choices_as_values' => true)
                );

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
