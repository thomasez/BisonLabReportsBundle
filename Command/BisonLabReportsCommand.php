<?php

namespace BisonLab\ReportsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generic but not that generic report command. Yet.
 *
 * @author    Thomas Lundquist <thomasez@bisonlab.no>
 * @copyright 2010, 2011, 2012 Repill-Linpro
 * @copyright 2015, 2016, 2017, 2018, 2019 BisonLab AS
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 */

class BisonLabReportsCommand extends ContainerAwareCommand
{
    private $verbose = true;

    protected function configure()
    {
        $this->setDefinition(array(
                new InputOption('report', '', InputOption::VALUE_REQUIRED, 'The report you want'),
                new InputOption('list', null, InputOption::VALUE_NONE, 'A list of available reports'),
                new InputOption('filename', '', InputOption::VALUE_REQUIRED, 'The file to write to. Default is a generated name.'),
                new InputOption('delimiter', '', InputOption::VALUE_REQUIRED, 'Field delimiter, defaults to semicolon'),
                new InputOption('output-method', '', InputOption::VALUE_REQUIRED, 'File format of report, default csv.'),
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
        $this->report     = $input->getOption('report') ? $input->getOption('report') : '';
        $this->output_method = $input->getOption('output_method') ? $input->getOption('output_method') : 'csv';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf('Debug mode is <comment>%s</comment>.', $input->getOption('no-debug') ? 'off' : 'on'));
        $output->writeln('');

        $reports = $this->getContainer()->get('bisonlab_reports');

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
            'output_method' => $this->output_method,
            'store_server' => true
        );

        $reports->runFixedReport($config);
    }
}

