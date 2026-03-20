<?php

namespace Ksfraser\QifParser;

use Ksfraser\QifParser\Entities\QifStatement;
use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Parsers\AddressParser;
use Ksfraser\QifParser\Parsers\AmountParser;
use Ksfraser\QifParser\Parsers\CategoryParser;
use Ksfraser\QifParser\Parsers\DateParser;
use Ksfraser\QifParser\Parsers\MemoParser;
use Ksfraser\QifParser\Parsers\NullParser;
use Ksfraser\QifParser\Parsers\NumberParser;
use Ksfraser\QifParser\Parsers\ParserInterface;
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

    /** @var ParserInterface[] Map of tag character to Parser object */
    private $parsers = [];

    /**
     * Null Object parser used for any unrecognised QIF tag.
     * Prevents silent switch fall-through and makes intent explicit.
     *
     * @var NullParser
     */
    private $nullParser;

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
        $this->nullParser = new NullParser();

        $this->parsers['D'] = new DateParser($this->dateFormat);
        $this->parsers['T'] = new AmountParser();
        $payeeParser = new PayeeParser();
        $this->parsers['P'] = $payeeParser;
        $this->parsers['A'] = new AddressParser();
        $this->parsers['M'] = new MemoParser();
        $this->parsers['N'] = new NumberParser();
        $this->parsers['L'] = new CategoryParser();

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

            // Dispatch to the registered parser; NullParser handles any unrecognised tag silently.
            // Split parsers (S, E, $) require the full line to retain the tag character for context.
            $parser = $this->parsers[$tag] ?? $this->nullParser;
            if ($tag === 'S' || $tag === 'E' || $tag === '$') {
                $parser->parse($line, $currentTransaction);
            } else {
                $parser->parse($value, $currentTransaction);
            }
        }

        return $statement;
    }

}

