<?php

namespace AppBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportCommand extends ContainerAwareCommand

{

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setName('import:database')
            ->setDescription('Import entities from CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $serviceImport = $this->getContainer()->get('AppBundle\Service\ImportEntities');
        $pathSoft = "app/Resources/datas/import-softwares.csv";
        $pathTags = "app/Resources/datas/import-tags.csv";
        $pathVersus = "app/Resources/datas/import-versus.csv";

        $connection = $this->em->getConnection();
        $dbName = $this->getContainer()->getParameter("database_name");

        if (file_exists($pathTags)) {
            $type = "import-tags";
            $serviceImport->verifCsv($pathTags, $type);
        } else {
            $output->writeln("Fichier import-tags.csv manquant");

        }
      
        if (file_exists($pathSoft)) {
            $type = "import-softwares";
            $serviceImport->verifCsv($pathSoft, $type);
        } else {
            $output->writeln("Fichier import-softwares.csv manquant");
        }


        if (file_exists($pathVersus)) {
            $type = "import-versus";
            $serviceImport->verifCsv($pathVersus, $type);
        } else {
            $output->writeln("Fichier import-versus.csv manquant");
        }

        $errors = $serviceImport->getErrors();
        if (is_array($errors) && count($errors) > 0) {
            foreach ($errors as $error) {
                $output->writeln($error);
            }
        } else {
            $connection->beginTransaction();
            $output->writeln(PHP_EOL ."La BDD est en train d'être importée. Cela peut durer quelques minutes." . PHP_EOL . '');
            $progress = new ProgressBar($output, 60);
            $progress->start();
            try {
                $progress->advance(5);
                $serviceImport->deleteAllContent($connection, $dbName);
                $progress->advance(5);
                $serviceImport->import($pathTags, "import-tags");
                $progress->advance(20);
                $serviceImport->import($pathSoft, "import-softwares");
                $progress->advance(10);
                $serviceImport->import($pathVersus, "import-versus");
                $progress->advance(10);
                $serviceImport->addSeeAlsoBySoftwares();
                $progress->advance(10);
                $progress->finish();
                $output->writeln(PHP_EOL ."La BDD a bien été importée." . PHP_EOL . '
____    __    ____  _______  __       __          _______   ______   .__   __.  _______     __  
\   \  /  \  /   / |   ____||  |     |  |        |       \ /  __  \  |  \ |  | |   ____|   |  | 
 \   \/    \/   /  |  |__   |  |     |  |        |  .--.  |  |  |  | |   \|  | |  |__      |  | 
  \            /   |   __|  |  |     |  |        |  |  |  |  |  |  | |  . `  | |   __|     |  | 
   \    /\    /    |  |____ |  `----.|  `----.   |  \'--\'  |  `--\'  | |  |\   | |  |____    |__| 
    \__/  \__/     |_______||_______||_______|   |_______/ \______/  |__| \__| |_______|   (__) 
                                                                                                
            
            ');
                $connection->commit();



            } catch (\Exception $e) {
              
                $connection->rollBack();

                $output->writeln('Exception reçue : ' . $e->getMessage() . PHP_EOL);
            }


        }
    }
}
