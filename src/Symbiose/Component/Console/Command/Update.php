<?php

namespace Symbiose\Component\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
	Symfony\Component\Console\Input\InputOption,
	Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface,
	Symfony\Component\Console\Output\Output,
	Symfony\Component\Console\Command\Command,
	Doctrine\ORM\Tools\SchemaTool
;

class Update
	extends Command
{
	protected $serviceContainer;

	public function __construct($serviceContainer)
	{
		$this->serviceContainer = $serviceContainer;
		if(empty($this->serviceContainer)) {
			throw new \InvalidArgumentException("you must provide a service container as unique argument (none given)");
		}
		parent::__construct();
	}
	
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('update_script', InputArgument::REQUIRED, 'A file that contains php commands to update entities')
            ))
            ->setName('update')
            ->setDescription('Update doctrine entities')
            ->setHelp(<<<EOF
The <info>update</info> command update doctrine entities:

  <info>./cli.php update "update_script.php"</info>

The php update script can :
	- display message as following : \$output->writeln("message") 
	- get the service container as following : \$this->serviceContainer

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
		$output->writeln("Update");
		$output->writeln("-----------");
		
		// get update script file argument
    	$scriptFileArg = $input->getArgument('update_script');
		// must exists
    	if(!file_exists($scriptFileArg)) {
			throw new \InvalidArgumentException("Invalid argument 'update_script' (file '$scriptFileArg' doesn't exist)");
		}
		// must be readable
    	if(!is_readable($scriptFileArg)) {
			throw new \InvalidArgumentException("Invalid argument 'update_script' (file '$scriptFileArg' is not readable)");
		}
		
		// include the file
		$output->writeln("Running update script '$scriptFileArg' ...");
		$output->writeln('');
		
		include($scriptFileArg);
		
		$output->writeln('');
		$output->writeln("Success");
		$output->writeln('');
    }
}