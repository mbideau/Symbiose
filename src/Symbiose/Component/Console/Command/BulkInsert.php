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

class BulkInsert
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
                new InputArgument('entities_file', InputArgument::REQUIRED, 'A file that contains doctrine entities declaration')
            ))
            ->setName('bulk-insert')
            ->setDescription('Insert doctrine entities into database')
            ->setHelp(<<<EOF
The <info>bulk-insert</info> command insert doctrine entities into database:

  <info>./cli.php bulk-insert "insert-entities.php"</info>

The php file that contains entities must have the following format (will be included):

  <?php
  \$entities = array(
    new CmsUser(array('name' => 'Michael')),
    new CmsUser(array('name' => 'John')),
    new CmsUser(array('name' => 'Ryan')),
  );

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
		$output->writeln("Bulk insert");
		$output->writeln("-----------");
		
		// get entities file argument
    	$entitiesFileArg = $input->getArgument('entities_file');
		// must exists
    	if(!file_exists($entitiesFileArg)) {
			throw new \InvalidArgumentException("Invalid argument 'entities_file' (file '$entitiesFileArg' doesn't exist)");
		}
		// must be readable
    	if(!is_readable($entitiesFileArg)) {
			throw new \InvalidArgumentException("Invalid argument 'entities_file' (file '$entitiesFileArg' is not readable)");
		}
		
		// include the file
		$output->writeln("Including entities file '$entitiesFileArg'");
		include($entitiesFileArg);
		$output->writeln("File '$entitiesFileArg' successfully included");
		
		// the var entities must be defined
		if(!isset($entities)) {
			throw new \InvalidArgumentException("You must define a variable that is an array (of entities) named 'entities' in '$entitiesFileArg'");
		}
		
		// entities must be an array
		if(!is_array($entities)) {
			throw new \InvalidArgumentException("The variable named 'entities' in '$entitiesFileArg' must be an array (of entities)");
		}
		
		// entities must not be empty
		if(empty($entities)) {
			throw new \InvalidArgumentException("The array named 'entities' in '$entitiesFileArg' must not be empty");
		}
		
		// entities must contains entities
		foreach($entities as $k => $e) {
			if(!is_object($e) || strpos(get_class($e), 'Entities\\') !== 0) {
				throw new \InvalidArgumentException("The array named 'entities' in '$entitiesFileArg' must contains entities (failed key: $k)");
			}
		}
		
		$output->writeln("Entities are present (" . count($entities) . ")");
		
		// get entity manager
		$em = $this->serviceContainer->get('entity_manager');
		
		$output->writeln("Inserting entities ...");
		
		// suspend auto-commit
		$em->getConnection()->beginTransaction();
		try {
		    // persist each entity
			foreach($entities as $entity) {
				$em->persist($entity);
		    }
		    // really save them here 
	    	$em->flush();
	    	$em->getConnection()->commit();
		}
		catch(\Exception $e) {
		    // cancel the whole job in case of an error
			$em->getConnection()->rollback();
		    $em->close();
		    // throw the error
		    throw $e;
		}
		
		// success
        $output->writeln("<info>Successfully inserted " . count($entities) . " entities.</info>");
		
		// output the result
		$output->writeln('');
    }
}