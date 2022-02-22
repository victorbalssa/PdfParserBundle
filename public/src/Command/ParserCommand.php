<?php

namespace App\Command;

use App\Processor\SgProProcessor;
use App\Util\PdfParser;
use App\Util\ParseHelper;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\Yaml\Dumper;

/**
 * Class ParserCommand.
 */
class ParserCommand extends ContainerAwareCommand
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('pdf-parser:parse')
            ->setDescription('Parse document of many types.')
            ->addArgument('processor', InputArgument::OPTIONAL, 'The id of the processor')
            ->addArgument('file_path', InputArgument::OPTIONAL, 'The absolute path to the PDF file to parse.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (console, json, yml)', 'console');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $container = $this->getContainer();
        $logger = new Logger();
        $pdfParser = new PdfParser($logger);
        $pdfDirectoryPath = "/var/www/html/data/";

        // Select processor
        $this->selectProcessor($input, $pdfParser);

        // Select file
        //$filePath = $this->selectFile($input, $pdfDirectoryPath);

        $pdfNameArray = [];


        $files = glob($pdfDirectoryPath.'*.pdf');
        foreach ($files as $file) {
            $file = pathinfo($file);
            $pdfNameArray[] = $file['filename'];
        }

        foreach ($pdfNameArray as $pdfName) {


            try {
                    // Parse
                    $rows = $pdfParser->parse($pdfDirectoryPath.$pdfName.'.pdf');
                    //var_dump($rows);
                    $data = ParseHelper::inlineDates($rows->toArray());

                    print_r("count: ".count($data) . "\n");

                    // Write output
                    $this->writeOutput('csv', $data, $pdfName);
            } catch (Exception $e) {
                print_r($e->getCode() . "\n");
                print_r($e->getMessage(). "\n");
                print_r($e->getLine(). "\n");
                print_r($e->getFile(). "\n");
                print_r($e->getTraceAsString(). "\n");
                print_r($pdfName);

            }
        }


        return;
    }

    /**
     * @param                 $format
     * @param                 $data
     */
    protected function writeOutput($format, $data, $pdfName)
    {
        switch ($format) {
            case 'json':
                $outputData = json_encode($data);
                $this->output->write($outputData);
                break;
            case 'csv':

                // CSV file name => geeks.csv
                $csv = '/var/www/html/data/export_'. $pdfName.'.csv';

// File pointer in writable mode
                $file_pointer = fopen($csv, 'w');

// Traverse through the associative
// array using for each loop
                foreach($data as $line){

                    // Write the data to the CSV file
                    fputcsv($file_pointer, $line);
                }

// Close the file pointer.
                fclose($file_pointer);
                break;
            case 'yml':
                $dumper = new Dumper();
                $outputData = $dumper->dump($data, 1);
                $this->output->write($outputData);
                break;
            case 'console':
                if (count($data)) {
                    $table = new Table($this->output);
                    $table
                        ->setHeaders([array_keys($data[0])])
                        ->setRows($data);
                    $table->render();
                }
                break;
        }
    }

    /**
     * @param InputInterface $input
     * @param                $pdfDirectoryPath
     *
     * @return mixed
     */
    protected function selectFile(InputInterface $input, $pdfDirectoryPath)
    {
        $filePath = $input->getArgument('file_path');
        if (!$filePath) {
            $helper = $this->getHelper('question');
            $finder = new Finder();
            $finder->files()->in($pdfDirectoryPath);
            $files = [];
            foreach ($finder as $key => $file) {
                /* @var SplFileInfo $file */
                $files[] = $file->getRealPath();
            }

            $question = new ChoiceQuestion('Which file? Enter the key.', $files);
            $filePath = $helper->ask($input, $this->output, $question);

            return $filePath;
        }

        return $filePath;
    }

    /**
     * @param InputInterface $input
     * @param                $pdfParser
     */
    protected function selectProcessor(InputInterface $input, PdfParser $pdfParser)
    {
/*        $processors = $pdfParser->getAvailableProcessors();
        $processorId = $input->getArgument('processor');
        if (!$processorId) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('Which processor to use?', $processors);
            $processorId = $helper->ask($input, $this->output, $question);
        }*/
        $processor = new SgProProcessor();
        $pdfParser->setProcessor($processor);
    }
}
