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

class CreateSchema
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
                new InputArgument('entities_file', InputArgument::REQUIRED, 'The entities file list (php array)'),
                new InputOption('drop', null, InputOption::PARAMETER_NONE, 'Drop the schema if exists'),
                new InputOption('sql', null, InputOption::PARAMETER_NONE, 'Output generated SQL')
            ))
            ->setName('create-schema')
            ->setDescription('Create a database schema from doctrine entities list')
            ->setHelp(<<<EOF
The <info>create-schema</info> command create a databse schema for a given doctrine entities list (in a php array file):

  <info>./cli.php create-schema entities_list.php --drop --sql</info>

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
		$output->writeln("Create Schema");
		$output->writeln("-------------");
		
		// get entities list argument
    	$entitiesListFile = $input->getArgument('entities_file');
		// clean it
		$entitiesListFile = realpath(trim($entitiesListFile));
        $output->writeln("From entities list file:\t$entitiesListFile");
    	// must be valid
    	if(empty($entitiesListFile) || !file_exists($entitiesListFile)) {
			throw new \InvalidArgumentException("Invalid argument 'entities_file' ($entitiesListFile)");
		}
		
		// entities list from argument
		$entitiesList = include $entitiesListFile;
		
		// print entities list
		$output->writeln("Entities included:\n\t" . implode("\n\t", $entitiesList) . "\n");
		
		// get entity manager
		$em = $this->serviceContainer->get('entity_manager');
		
    	// get connection
		$conn = $em->getConnection();

        // get schema tool
		$tool = new SchemaTool($em);
		
		// get classes
		$classes = array();
		foreach($entitiesList as $entityClass) {
			if(class_exists($entityClass)) {
				$classes[] = $em->getClassMetadata($entityClass);
			}
			else {
				$output->writeln("<error>Class '$entityClass' doesn't exist</error>");
			}
		}
		
		// drop schema
		if($input->getOption('drop')) {
			$output->writeln("Droping schema ...");
			$statements = $tool->getDropSchemaSql($classes);
			foreach($statements as $sql) {
				if($input->getOption('sql')) {
					$output->writeln("   $sql");
				}
				$conn->executeQuery($sql);
	        }
			$output->writeln("<info>Schema droped successfully.</info>\n");
		}
		
		// create the schema
		$output->writeln("Creating schema ...");
		$statements = $tool->getCreateSchemaSql($classes);
    	foreach($statements as $sql) {
			if($input->getOption('sql')) {
				$output->writeln("   $sql");
			}
			$conn->executeQuery($sql);
	    }
		$output->writeln("<info>Schema created successfully.</info>");
		
		$output->writeln('');
    }
}