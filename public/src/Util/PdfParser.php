<?php

namespace App\Util;

use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use App\Processor\ProcessorInterface;
use App\Util\ParseHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PdfParser
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var ProcessorInterface */
    private $processor;

    /** @var array */
    private $processorConfiguration;

    /** @var string */
    private $temporaryDirectoryPath;

    /** @var ProcessorInterface[] */
    private $availableProcessors = [];

    /**
     * PdfParser constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->temporaryDirectoryPath = sys_get_temp_dir();
    }

    /**
     * @param ProcessorInterface $processor
     */
    public function addAvailableProcessor(ProcessorInterface $processor)
    {
        $this->availableProcessors[$processor->getConfiguration()['id']] = $processor;
    }

    /**
     * @return ProcessorInterface[]
     */
    public function getAvailableProcessors()
    {
        return $this->availableProcessors;
    }

    /**
     * @return ProcessorInterface
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * @param ProcessorInterface $processor
     */
    public function setProcessor(ProcessorInterface $processor)
    {
        $this->processor = $processor;
    }

    /**
     * @param $filePath
     *
     * @return ArrayCollection
     *
     * @throws Exception
     */
    public function parse($filePath)
    {
        $this->processorConfiguration = $this->processor->getConfiguration();

        $rawData = $this->getTextVersion($filePath);

        $rows = $this->doParse($rawData);
        $rows = new ArrayCollection($rows);

        $formattedRows = $this->processor->format($rows);

        return $formattedRows;
    }

    /**
     * @param $data
     *
     * @return array|string
     *
     * @throws Exception
     */
    private function doParse($data)
    {
        $blocks = [];
        //var_dump($data);
        while ($startPos = ParseHelper::findPosition($data, $this->processorConfiguration['startConditions'])) {
            // Find start
            if (is_null($startPos) && !count($blocks)) {
                throw new Exception('Start condition never reached.');
            }
            $data = substr($data, $startPos);
            $data = substr($data, strpos($data, "\n"));

            // Find end

            $endPos = ParseHelper::findPosition($data, $this->processorConfiguration['endConditions']);
            if (is_null($endPos)) {
                throw new Exception('End condition not reached at the ' . (count($blocks) + 1) . ' nth loop of block.');
            } else {
                $blockData = substr($data, 0, $endPos);
                $data = substr($data, $endPos);
            }
            $blockData = rtrim($blockData);

            $block = $this->parseBlock(
                $blockData,
                $this->processorConfiguration['rowsToSkip'],
                $this->processorConfiguration['rowMergeColumnTokens'],
                $this->processorConfiguration['rowSkipConditions']
            );

            $blocks[] = $block;
        }

        // Merge block.
        $data = [];
        foreach ($blocks as $block) {
            $data = array_merge($data, $block);
        }

        return $data;
    }



    /**
     * @param $blockData
     * @param $skipKeys
     * @param $rowMergeColumnTokens
     * @param $rowSkipConditions
     *
     * @return array
     */
    private function parseBlock($blockData, $skipKeys, $rowMergeColumnTokens, $rowSkipConditions)
    {
        $rows = [];
        $rawRows = explode("\n", $blockData);
        $rawRows = ParseHelper::prepareRows($rawRows, $skipKeys, $rowSkipConditions);
        $this->logger->debug(implode("\n", $rawRows));
        $previousIndex = 0;
        $colWidths = $this->guessWidth($rawRows);
        //var_dump($colWidths);
        foreach ($rawRows as $key => $rawRow) {
            $row = ParseHelper::parseRow($colWidths, $rawRow);
            $toMergeWithPrevious = false;
            if ($key > 0) {
                foreach ($rowMergeColumnTokens as $rowMergeColumnToken) {
                    if (!strlen($row[$rowMergeColumnToken])) {
                        $toMergeWithPrevious = true;
                    }
                }
            }

            //var_dump($row);

            if ($toMergeWithPrevious) {
                $rows[$previousIndex] = ParseHelper::mergeRows($rows[$previousIndex], $row);
            } else {
                $rows[] = $row;
                $previousIndex = count($rows) - 1;
            }
        }

        return $rows;
    }

    /**
     * @param $rawRows
     *
     * @return array
     */
    private function findSpaceGroups($rawRows)
    {
        $globalSpacePositions = [];
        foreach ($rawRows as $rawRow) {
            $spacePositions = ParseHelper::getSpacePositions($rawRow);

            if (count($globalSpacePositions)) {
                $globalSpacePositions = array_intersect($globalSpacePositions, $spacePositions);
            } else {
                $globalSpacePositions = $spacePositions;
            }
        }
        $globalSpacePositions = array_values($globalSpacePositions);

        $spaceGroups = [];
        $spaceGroupIndex = 0;
        foreach ($globalSpacePositions as $key => $spacePosition) {
            if ($key == 0) {
                $spaceGroups[$spaceGroupIndex] = ['start' => $spacePosition, 'end' => $spacePosition + 1];
            } else {
                $previousPos = $globalSpacePositions[$key - 1];
                $increase = $spacePosition - $previousPos;
                if ($increase == 1) {
                    ++$spaceGroups[$spaceGroupIndex]['end'];
                } else {
                    ++$spaceGroupIndex;
                    $spaceGroups[$spaceGroupIndex] = ['start' => $spacePosition, 'end' => $spacePosition + 1];
                }
            }
        }

        // Clean "false positive" space groups.
        $spaceGroups = array_filter($spaceGroups, function ($spaceGroup) {
            return ($spaceGroup['end'] - $spaceGroup['start'] > 1) && $spaceGroup['start'] !== 0;
        });

        $spaceGroupsDates = [
            [
                "start" => 11,
                "end" => 12,
            ],
            [
                "start" => 22,
                "end" => 23,
            ],
        ];

        $spaceGroups = array_merge_recursive($spaceGroupsDates, $spaceGroups);

        return $spaceGroups;
    }

    /**
     * @param $rawRows
     *
     * @return array
     */
    private function guessWidth($rawRows)
    {
        $spaceGroups = $this->findSpaceGroups($rawRows);

        $widths = [];
        $spaceEnd = 0;
        foreach ($spaceGroups as $spaceGroupKey => $spaceGroup) {
            $spaceStart = $spaceGroup['start'];
            $widths[] = ['start' => $spaceEnd, 'length' => $spaceStart - $spaceEnd];
            $spaceEnd = $spaceGroup['end'];
        }
        $widths[] = ['start' => $spaceEnd, 'length' => strlen($rawRows[0]) - $spaceEnd];

        return $widths;
    }

    /**
     * @param $filePath
     *
     * @return string
     */
    private function getTextVersion($filePath)
    {
        $tmpPath = $this->temporaryDirectoryPath . '/' . rand(0, 10000) . '.txt';
        $process = new Process('/usr/bin/pdftotext -layout ' . $filePath . ' ' . $tmpPath);
        $this->logger->info('Execute Pdftotext', ['file' => $filePath]);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->logger->error($buffer);
            } else {
                $this->logger->info($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }
}
