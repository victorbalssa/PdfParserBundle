<?php

namespace App\Processor;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Class SgProProcessor.
 */
class SgProProcessor extends Processor implements ProcessorInterface
{
    protected $configuration = [
        'id' => 'sg_pro',
        'name' => 'Société Générale - Compte courant professionnel',
        'startConditions' => ['/Date\s+Valeur\s+/'],
        'endConditions' => [
            '/1 Depuis l\'étranger/', '/N° d\'adhérent JAZZ/', '/Société Générale\s+552 120 222 RCS Paris/','/Les écritures précédées du signe/',
        ],
        'rowMergeColumnTokens' => [0],
        'rowSkipConditions' => [ 'pli','Du', 'ca', 'ta', 'SOLDE PRÉCÉDENT AU', 'TOTAUX DES MOUVEMENTS', 'RA4-01K', 'NOUVEAU SOLDE AU', 'RA4-01P','RA419064','RA419105','RA419294','RA421090','RA421019','RA420321','RA420258','RA420027','RA419338','RA419310','suite >>>', '*** SOLDE AU', 'Soit pour information, solde en francs de', 'RELEVÉ DES OPÉRATIONS'],
        'rowsToSkip' => [0],
    ];

    /**
     * @param ArrayCollection $data
     *
     * @return ArrayCollection
     */
    public function format(ArrayCollection $data)
    {
        $data = $data->map(function ($item) {

            if (count($item) < 4) {
                return [
                    'date' => '',
                    'value_date' => '',
                    'label' => '',
                    'value' => '',
                    'debit' => false,
                ];
            }
            // Date
            $dateRaw = $item[0];
            $date = new \DateTime();
            $date->setDate((int) substr($dateRaw, 6, 4), (int) substr($dateRaw, 3, 2), (int) substr($dateRaw, 0, 2));
            $date = $date->format('Y-m-d');

            // Value Date
            $dateRaw = $item[1];
            $valueDate = new \DateTime();
            $valueDate->setDate((int) substr($dateRaw, 6, 4), (int) substr($dateRaw, 3, 2), (int) substr($dateRaw, 0, 2));
            $valueDate = $valueDate->format('Y-m-d');

            // Transaction
            if (count($item) > 5) {
                $transaction = $this->frenchTransactionFormatter($item[4], isset($item[5]) ? $item[5] : null);
            } else {
                $transaction = $this->frenchTransactionFormatter($item[3], isset($item[4]) ? $item[4] : null);
            }

            return [
                'date' => $date,
                'value_date' => $valueDate,
                'label' => $item[2],
                'value' => $transaction['value'],
                'debit' => false,
            ];
        });

        return $data;
    }
}
