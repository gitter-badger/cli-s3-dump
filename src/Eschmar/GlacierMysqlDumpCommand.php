<?php

namespace Eschmar;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Ifsnop\Mysqldump as IMysqldump;

/**
 * Command for dumping a mysql database and writing it to Amazon Glacier.
 *
 * @author Marcel Eschmann
 **/
class GlacierMysqlDumpCommand extends Command
{
    /**
     * Default timestamp format for filenames.
     *
     * @var string
     **/
    protected $timestamp_format = 'Y-m-d_H-i-s';

    /**
     * Default dump target location.
     *
     * @var string
     **/
    protected $dump_dir = 'dumps/';

    /**
     * Configuration read from config.yml.
     *
     * @var array
     **/
    protected $config;

    /**
     * Command input configuration.
     *
     * @return void
     * @author Marcel Eschmann
     **/
    protected function configure()
    {
        $this
            ->setName('dump')
            ->setDescription('Dumps a MySQL database and writes it to Amazon Glacier')
            ->addArgument('location', InputArgument::OPTIONAL, 'Write dumps to this directory (with trailing slash).')
            ->addOption(
               'aws-glacier',
               null,
               InputOption::VALUE_NONE,
               'If set, the dump will be uploaded to Amazon Glacier'
            )
        ;
    }

    /**
     * Command execution.
     *
     * @return void
     * @author Marcel Eschmann
     **/
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("");
        $start = time();

        if (!file_exists('config.yml')) {
            $output->writeln(" \033[1;31m[ERROR]: No config.yml file found.");
            return;
        }

        try {
            $yaml = new Parser();
            $this->config = $yaml->parse(file_get_contents('config.yml'));
        } catch (\Exception $e) {
            $output->writeln(" \033[1;31m[ERROR]: Not able to parse config.yml. Please check for syntax errors.");
            return;
        }

        // check database configuration
        if (!isset($this->config['database']) || !$this->checkForKeys($this->config['database'], array('user', 'password', 'name'))) {
            $output->writeln(" \033[1;31m[ERROR]: Invalid database configuration. Check config.yml.");
            return;
        }

        // check amazon glacier configuration
        $glacier = $input->getOption('aws-glacier');
        if ($glacier && (!isset($this->config['aws']['glacier']) || !$this->checkForKeys($this->config['aws']['glacier'], array('key', 'secret', 'region')))) {
            $output->writeln(" \033[1;31m[ERROR]: Invalid amazon glacier configuration. Check config.yml.");
            return;
        }

        if ($input->getArgument('location')) {
            $this->dump_dir = $input->getArgument('location');
        }

        // check if target directory exists
        if (!is_dir($this->dump_dir)) {
            mkdir($this->dump_dir, 0777, true);
        }

        // generate filename
        $now = new \DateTime();
        $filename = $this->config['database']['name'] . '_' . $now->format($this->timestamp_format) . '.sql';

        // execute mysql dump
        try {
            $dump = new IMysqldump\Mysqldump($this->config['database']['name'], $this->config['database']['user'], $this->config['database']['password'], 'localhost', 'mysql', array(
                'compress' => 'Gzip'
            ));

            $dump->start($this->dump_dir . $filename);
        } catch (\Exception $e) {
            $output->writeln(" \033[1;31m[ERROR]: " . $e->getMessage());
        }

        // write dump to amazon glacier if chosen
        if ($glacier) {
            $output->writeln(" \033[1;32m[TODO]: AWS Glacier implementation.");
            $output->writeln(" \033[36mSkiping Glacier...");
        }
 
        $end = time();
        $elapsed = $end-$start;
        $output->writeln("\033[0;0m ...generated \033[36m{$this->dump_dir}$filename\033[0;0m in \033[36m$elapsed\033[0;0m seconds.");
    }

    /**
     * Batch check for array keys.
     *
     * @return boolean
     * @author Marcel Eschmann
     **/
    protected function checkForKeys($haystack, $needles)
    {
        foreach ($needles as $needle) {
            if (!array_key_exists($needle, $haystack)) {
                return false;
            }
        }

        return true;
    }

} // END class GlacierMysqlDumpCommand extends Command