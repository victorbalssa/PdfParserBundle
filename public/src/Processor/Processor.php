<?php

namespace App\Processor;

/**
 * Class Processor.
 */
abstract class Processor
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function __toString()
    {
        return (string) $this->configuration['name'];
    }

    /**
     * @param string $debitRaw
     * @param string $creditRaw
     *
     * @return array
     */
    public function frenchTransactionFormatter($debitRaw, $creditRaw)
    {
        if (trim($debitRaw) !== '') {
            $debitRaw = preg_replace('/[^\d,]/', '', $debitRaw);
            $value = (float) str_replace(',', '.', str_replace(' ', '', $debitRaw));
            $value = '-'.$value;
        } else {
            $creditRaw = preg_replace('/[^\d,]/', '', $creditRaw);
            $value = (float) str_replace(',', '.', str_replace(' ', '', $creditRaw));
        }
        return [
            'value' => $value,
        ];
    }
}
