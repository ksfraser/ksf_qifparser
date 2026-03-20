<?php

/**
 * QIF Anonymization/Sanitization Utility
 *
 * Scrubs real-world banking names, account numbers, and personal info
 * from raw QIF samples. Uses dynamic maps built during processing so that:
 *   - The first unique payee encountered becomes "Merchant1"
 *   - The second unique payee becomes "Merchant2", and so on
 *   - The same real payee always maps to the same alias within a run
 *   - Cities, categories, and split categories follow the same pattern
 *
 * Dynamic maps make the output non-reversible; there is no hardcoded
 * key→value table that could be used to recover the originals.
 *
 * Usage: php scripts/sanitize-qif.php <input_dir> <output_dir>
 *
 * @requirement FR-1.2.x (Security & Data Privacy)
 */

if ($argc < 3) {
    die("Usage: php scripts/sanitize-qif.php <input_dir> <output_dir>\n");
}

$inputDir  = rtrim($argv[1], DIRECTORY_SEPARATOR);
$outputDir = rtrim($argv[2], DIRECTORY_SEPARATOR);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// ---------------------------------------------------------------------------
// Dynamic maps — populated as files are processed and shared across all files
// in this run so that the same real value always maps to the same alias.
// ---------------------------------------------------------------------------
$payeeMap         = [];
$cityMap          = [];
$categoryMap      = [];
$splitCategoryMap = [];

$payeeCounter         = 1;
$cityCounter          = 1;
$categoryCounter      = 1;
$splitCategoryCounter = 1;

/**
 * Return (or create) a sequential alias for $value within $map.
 *
 * Values are normalised to lowercase before lookup so that case variants
 * of the same payee/city collapse to the same alias.
 *
 * @param string $value     Original real-world value.
 * @param array  &$map      Persistence map for this category (passed by ref).
 * @param string $prefix    Human-readable prefix, e.g. "Merchant".
 * @param int    &$counter  Next available sequence number (mutated on insert).
 * @return string           Alias such as "Merchant3".
 */
function getOrCreateAlias(string $value, array &$map, string $prefix, int &$counter): string
{
    $key = strtolower(trim($value));
    if (!isset($map[$key])) {
        $map[$key] = $prefix . $counter;
        $counter++;
    }
    return $map[$key];
}

// URL pattern reused across multiple tag handlers
const URL_PATTERN = '/\b(?:https?:\/\/)?(?:www\.)?[a-zA-Z0-9-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?\b/i';

// Province / state / country strings that must NOT be treated as city names
const NON_CITY_TERMS = '/^(canada|usa|ontario|quebec|alberta|bc|manitoba|saskatchewan|new brunswick|nova scotia|pei|newfoundland)$/i';

$files = glob("$inputDir/*.{qif,QIF}", GLOB_BRACE);

foreach ($files as $file) {
    $filename = basename($file);
    echo "Processing: $filename\n";

    $lines       = file($file);
    $outputLines = [];

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            $outputLines[] = $line;
            continue;
        }

        $tag     = substr($trimmedLine, 0, 1);
        $content = substr($trimmedLine, 1);

        // Amount tags: preserve verbatim (sign, decimals, currency prefix)
        if ($tag === 'T' || $tag === '$') {
            $outputLines[] = "$tag$content\n";
            continue;
        }

        switch ($tag) {
            case 'P': // Payee
                $content       = preg_replace(URL_PATTERN, 'example.com', $content);
                $alias         = getOrCreateAlias($content, $payeeMap, 'Merchant', $payeeCounter);
                $outputLines[] = "P$alias\n";
                break;

            case 'M': // Memo — redact embedded numbers and URLs; preserve structure
            case 'E': // Split memo — same treatment as M
                $content       = preg_replace('/\b\d{4,}\b/', 'REDACTED', $content);
                $content       = preg_replace(URL_PATTERN,    'example.com', $content);
                $outputLines[] = "$tag$content\n";
                break;

            case 'A': // Address lines
                // Anonymize postal codes (Canadian A1B 2C3 / A1B2C3 format)
                $content = preg_replace('/\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b/i', 'Z9Z 9Z9', $content);
                $content = preg_replace(URL_PATTERN, 'example.com', $content);

                // Treat purely alphabetic lines that are not province/country names as cities
                if (preg_match('/^[A-Za-z\s]+$/', $content) && !preg_match(NON_CITY_TERMS, $content)) {
                    $content = getOrCreateAlias($content, $cityMap, 'City', $cityCounter);
                }

                $outputLines[] = "A$content\n";
                break;

            case 'L': // Transaction category
                $alias         = getOrCreateAlias($content, $categoryMap, 'Category', $categoryCounter);
                $outputLines[] = "L$alias\n";
                break;

            case 'S': // Split category
                $alias         = getOrCreateAlias($content, $splitCategoryMap, 'SplitCategory', $splitCategoryCounter);
                $outputLines[] = "S$alias\n";
                break;

            case 'N': // Check / reference number — mask all but last 4 digits
                if (ctype_digit($content)) {
                    $outputLines[] = "N" . str_pad(substr($content, -4), strlen($content), '0', STR_PAD_LEFT) . "\n";
                } else {
                    $outputLines[] = "$tag$content\n";
                }
                break;

            default:
                $outputLines[] = $line;
        }
    }

    file_put_contents("$outputDir/$filename", implode('', $outputLines));
}

echo "Sanitization complete. Files saved to $outputDir\n";
echo "  Merchant aliases:       " . count($payeeMap)         . "\n";
echo "  City aliases:           " . count($cityMap)          . "\n";
echo "  Category aliases:       " . count($categoryMap)      . "\n";
echo "  Split category aliases: " . count($splitCategoryMap) . "\n";
