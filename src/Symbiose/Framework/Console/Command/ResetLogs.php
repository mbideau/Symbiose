<?php

namespace Symbiose\Framework\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
	Symfony\Component\Console\Input\InputOption,
	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface,
	Symfony\Component\Console\Output\Output,
	Symfony\Component\Console\Command\Command,
	RuntimeException as Exception
;

class ResetLogs
	extends Command
{
	protected $serviceContainer;
	protected $domainDataPath;
	
	public function __construct($domainDataPath)
	{
		$this->domainDataPath = $domainDataPath;
		parent::__construct();
	}
	
	
	/**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('reset-logs')
            ->setDescription('Reset every application logs')
            ->setHelp(<<<EOF
The <info>reset-logs</info> command reset every application logs

  <info>./cli.php reset-logs</info>

EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // content
		$output->writeln("Reset logs");
		$output->writeln("----------");

		// if the domain module data path is not defined
		$logDir = $this->domainDataPath . '/logs';
		
		if(!is_dir($logDir)) {
			throw new Exception("The domain data dir doesn't exist ($logDir)");
		}
		
		$files = array();
		
		// get log files in the domain module data dir
    	foreach(new \DirectoryIterator($logDir) as $fileInfo) {
			if(!$fileInfo->isDot() || strpos($fileInfo->getFilename(), '.') !== 0) {
				if(substr($fileInfo->getFilename(), -4, 4) == '.log') {
					$files[] = $fileInfo->getRealPath();
				}
			}
		}
		
		// log files
		if(!empty($files)) {
			$output->writeln("Log files exist (" . count($files) . ")");
			
			// reset log files
			foreach($files as $file) {
				if(!is_writable($file)) {
					throw new Exception("The log file '$file' is not writable");
				}
				try {
					if(@file_put_contents($file, '') === false) {
						throw new Exception("Failed to reset the log file '$file'");
					}
				}
				catch(\Exception $e) {
					throw new Exception("Failed to reset the log file '$file'.\nMessage: " . $e->getMessage());
				}
			}
			
			// success
        	$output->writeln("<info>Successfully reseted " . count($files) . " log files.</info>");
		}
		// no log file
		else {
			$output->writeln("No log file exist");
		}
		
		// end output
		$output->writeln('');
    }
}