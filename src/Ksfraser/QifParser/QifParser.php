<?php

namespace Ksfraser\QifParser;

use Ksfraser\QifParser\Entities\QifStatement;
use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Parsers\DateParser;
use Ksfraser\QifParser\Parsers\AmountParser;
use Ksfraser\QifParser\Parsers\PayeeParser;
use Ksfraser\QifParser\Parsers\SplitParser;
use Ksfraser\QifParser\Services\FitidService;

/**
 * Main QIF Parser Orchestrator
 * 
 * Purpose: Manages the file reading loop, item parsing through SRP units, 
 * and entity population including deterministic FITID generation.
 * 
 * @requirement FR-1.1
 * @requirement FR-2.1.1
 */
class QifParser
{
    /** @var string */
    private $bankId;

    /** @var string */
    private $accountId;

    /** @var string */
    private $currency;

    /** @var string MDY or DMY */
    private $dateFormat;

    /** @var array Map of tag prefix to Parser objects */
    private $parsers = [];

    /** @var FitidService */
    private $fitidService;

    /**
     * @param string $bankId
     * @param string $accountId
     * @param string $currency
     * @param string $dateFormat
     */
    public function __construct(string $bankId, string $accountId, string $currency = 'USD', string $dateFormat = 'MDY')
    {
        $this->bankId = $bankId;
        $this->accountId = $accountId;
        $this->currency = $currency;
        $this->dateFormat = $dateFormat;

        $this->fitidService = new FitidService();
        $this->initializeParsers();
    }

    /**
     * @return void
     */
    private function initializeParsers(): void
    {
        $this->parsers['D'] = new DateParser($this->dateFormat);
        $this->parsers['T'] = new AmountParser();
        $this->parsers['P'] = new PayeeParser();
        $this->parsers['A'] = new PayeeParser(); // Address tags handled by same parser
        
        // Split tags (S, E, $) are handled by a single SplitParser statefully
        $splitParser = new SplitParser();
        $this->parsers['S'] = $splitParser;
        $this->parsers['E'] = $splitParser;
        $this->parsers['$'] = $splitParser;
    }

    /**
     * @param string $filePath
     * @return array
     * @throws \RuntimeException
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        $content = file_get_contents($filePath);
        $statement = $this->parseContent($content);

        return $statement->transactions;
    }

    /**
     * @param string $content
     * @return QifStatement
     */
    public function parseContent(string $content): QifStatement
    {
        // Handle various line endings (standardizing on \n)
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $statement = new QifStatement();
        $statement->bankId = $this->bankId;
        $statement->accountId = $this->accountId;
        $statement->currency = $this->currency;

        $currentTransaction = null;
        $fileSequence = 1;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $tag = substr($line, 0, 1);
            $value = trim(substr($line, 1));

            // !Type: Header
            if ($tag === '!') {
                $statement->type = $value;
                continue;
            }

            // ^ End of transaction marker
            if ($tag === '^') {
                if ($currentTransaction) {
                    $currentTransaction->fitid = $this->fitidService->generate($currentTransaction, $this->accountId, $this->bankId);
                    $statement->transactions[] = $currentTransaction;
                    $currentTransaction = null;
                    $fileSequence++;
                }
                continue;
            }

            // Start new transaction if none exists
            if (!$currentTransaction) {
                $currentTransaction = new QifTransaction();
                $currentTransaction->fileSequence = $fileSequence;
            }

            // Process via SRP Parsers
            if (isset($this->parsers[$tag])) {
                // For split items, we pass the full tag + value to the SplitParser to handle multi-char tag logic
                if ($tag === 'S' || $tag === 'E' || $tag === '$') {
                    $this->parsers[$tag]->parse($line, $currentTransaction);
                } else {
                    $this->parsers[$tag]->parse($value, $currentTransaction);
                }
            } else {
                // Fallback for codes like M (Memo), N (CheckNum), L (Category) not yet in SRP
                $this->handleLegacyCodes($tag, $value, $currentTransaction);
            }
        }

        return $statement;
    }

    /**
     * @param string $tag
     * @param string $value
     * @param QifTransaction $transaction
     * @return void
     */
    private function handleLegacyCodes(string $tag, string $value, QifTransaction $transaction): void
    {
        switch ($tag) {
            case 'M':
                $transaction->memo = $value;
                break;
            case 'N':
                $transaction->number = $value;
                break;
            case 'L':
                $transaction->category = $value;
                break;
        }
    }
}
