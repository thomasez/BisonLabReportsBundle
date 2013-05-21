<?php

namespace RedpillLinpro\SimpleReportsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generic but not that generic report command. Yet.
 *
 * PHP Version 5
 *
 * @author    Thomas Lundquist <thomasez@redpill-linpro.com>
 * @copyright 2010, 2011, 2012 Repill-Linpro
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 */

class RedpillLinproSimpleReportsCommand extends ContainerAwareCommand
{

    private $verbose = true;

    protected function configure()
    {

        $this->setDefinition(array(
                new InputOption('report', '', InputOption::VALUE_REQUIRED, 'The report you want'),
                new InputOption('list', null, InputOption::VALUE_NONE, 'A list of available reports'),
                new InputOption('filename', '', InputOption::VALUE_REQUIRED, 'The file to write to. Default is a generated name.'),
                new InputOption('delimiter', '', InputOption::VALUE_REQUIRED, 'Field delimiter, defaults to semicolon'),
                ))
                ->setDescription('Reports')
                ->setHelp(<<<EOT
This command is the CLI for the report generator.
EOT
            );

        $this->setName('rplp:report');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->verbose    = $input->getOption('verbose') ? true : false;
        $this->list       = $input->getOption('list') ? true : false;
        $this->filename   = $input->getOption('filename');
        $this->delimiter  = $input->getOption('delimiter') ? $input->getOption('delimiter') : ';';
        $this->report = $input->getOption('report') ? $input->getOption('report') : '';

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf('Debug mode is <comment>%s</comment>.', $input->getOption('no-debug') ? 'off' : 'on'));
        $output->writeln('');

        $reports = $this->getContainer()->get('simple_reports');

        if ($this->list)
        {
            foreach($reports->getReports() as $name => $config) {
                $output->writeln($name . "\t\t" . $config['description']);
            }
            exit;
        }

        if (!$this->filename)
        {
           $output->writeln("I do need a filename");
           exit;
        }
    
        // Ok, prepare the config:
        $config = array(
            'report' => $this->report,
            'filename' => $this->filename,
            'delimiter' => $this->delimiter,
            'output_method' => 'csv',
        );

        $reports->runFixedReport($config);

      //  $this->entityManager = $this->getContainer()->get('doctrine')->getManager();
     //   $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

    }

  /**
   * @see sfTask
   */
/*
  protected function execute($arguments = array(), $options = array())
  {
    $this->databaseManager = new sfDatabaseManager($this->configuration);
    $this->em = $this->getManager();

    $report_config = sfYaml::load(sfConfig::get("sf_root_dir") . "/config/reports.yml");
    $report_output_config = sfYaml::load(sfConfig::get("sf_root_dir") . "/config/report_output.yml");

    $picker_config  = array();
    $picker_config += $report_config['all'];
    $picker_config += $report_config[$options['report']];

    $outputter_config  = array();
    // $outputter_config += $report_output_config['all'];
    $outputter_config += $report_output_config[$picker_config['output']];

    $picker    = new ReportPickFunctions(   $this->em, $picker_config, $outputter_config );
    $outputter = new ReportOutputFunctions( $this->em, $picker_config, $outputter_config );

    $pick_options = array();
    if (isset($options['external-id'])) {
      $pick_options['external_id'] = $options['external-id'];

    }
    $picked_objects = $picker->pickObjects($pick_options);

    // No need to care then:
    if (!count($picked_objects)) { echo "Nothing found"; return; }

    $output_options = array();

    $outputter->start();
    $outputter->outputObjects($picked_objects, $output_options);

    $outputter->finish();
    echo "Done, filename is: " .  $outputter->getFileName() . "\n";

  }
*/

}

