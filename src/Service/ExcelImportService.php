<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelImportService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get sheet names from an Excel file.
     */
    public function getSheetNames(string $filePath): array
    {
        return $this->processSpreadsheet(
            $filePath,
            fn($spreadsheet) => $spreadsheet->getSheetNames()
        );
    }

    /**
     * Import and preprocess availability data from a specific sheet.
     */
    public function importAndSortAvailabilityData(
        string $filePath,
        int $sheetIndex
    ): array {
        return $this->processSpreadsheet($filePath, function (
            $spreadsheet
        ) use ($sheetIndex) {
            $sheet = $this->validateSheetIndex($spreadsheet, $sheetIndex);
            $rawData = $this->extractAvailabilityData($sheet);
            return $this->preprocessDayData($rawData);
        });
    }

    /**
     * General function to load and process a spreadsheet.
     */
    private function processSpreadsheet(string $filePath, callable $callback)
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            return $callback($spreadsheet);
        } catch (\Exception $e) {
            $this->logger->error(
                "Erreur lors du traitement du fichier Excel : {$e->getMessage()}"
            );
            throw $e;
        }
    }

    /**
     * Validate the sheet index and return the selected sheet.
     */
    private function validateSheetIndex($spreadsheet, int $sheetIndex)
    {
        $sheetNames = $spreadsheet->getSheetNames();
        if (!array_key_exists($sheetIndex, $sheetNames)) {
            throw new \Exception(
                sprintf(
                    "La feuille spécifiée avec l'index (%d) n'existe pas. Feuilles disponibles : %s",
                    $sheetIndex,
                    json_encode($sheetNames)
                )
            );
        }

        $sheet = $spreadsheet->getSheetByName($sheetNames[$sheetIndex]);
        if (!$sheet) {
            throw new \Exception(
                "Impossible de charger la feuille spécifiée : " .
                    $sheetNames[$sheetIndex]
            );
        }

        //$this->logger->info("Analyse de la feuille : {$sheetNames[$sheetIndex]}");
        return $sheet;
    }

    /**
     * Extract availability data from a specific sheet.
     */
    private function extractAvailabilityData($sheet): array
    {
        $data = [];
        $startRow = 5; // SPV names start from row 5
        $nameColumn = "A"; // SPV names column
        $firstDateColumn = "C"; // First date column

        $days = $this->extractDayHeaders($sheet, $firstDateColumn);

        foreach ($sheet->getRowIterator($startRow) as $row) {
            $rowIndex = $row->getRowIndex();
            $name = $sheet->getCell("{$nameColumn}{$rowIndex}")->getValue();

            if (!$name || strtolower(trim($name)) === "desiderata équipe :") {
                continue;
            }

            foreach ($days as $columnIndex => &$day) {
                $cell = $sheet->getCell("{$columnIndex}{$rowIndex}");
                $value = $cell->getValue();

                if ($value === null || $value === "") {
                    continue;
                }

                $day["dispo"][] = $this->createDispoEntry(
                    $name,
                    $value,
                    $sheet,
                    "{$columnIndex}{$rowIndex}"
                );
            }
        }

        return array_values($days);
    }

    /**
     * Extract day headers (number, name, team) from the sheet.
     */
    private function extractDayHeaders($sheet, string $firstDateColumn): array
    {
        $headers = [];

        foreach ($sheet->getColumnIterator($firstDateColumn) as $column) {
            $columnIndex = $column->getColumnIndex();
            $dayNumber = $sheet->getCell("{$columnIndex}4")->getValue();
            $dayName = $sheet->getCell("{$columnIndex}3")->getValue();
            $team = $sheet->getCell("{$columnIndex}2")->getValue();

            if ($dayNumber && $dayName && $team) {
                $headers[$columnIndex] = [
                    "num" => (int) $dayNumber,
                    "jour" => $dayName,
                    "equipe" => $team,
                    "dispo" => [],
                ];
            } else {
                break;
            }
        }

        return $headers;
    }

    /**
     * Create a single SPV availability entry.
     */
    private function createDispoEntry(
        string $name,
        $value,
        $sheet,
        string $cellReference
    ): array {
        $entry = [
            "nom spv" => $name,
            "value" => is_numeric($value) ? (float) $value : 0,
            "partDay" => $value === 0.5,
            "color" => null,
        ];

        if ($value === 0.5) {
            $style = $sheet->getStyle($cellReference);
            $color = $style
                ->getFill()
                ->getStartColor()
                ->getRGB();
            $entry["color"] = $color ? "#{$color}" : null;
        }

        return $entry;
    }

    /**
     * Preprocess day data to sort SPVs by value and color.
     */
    private function preprocessDayData(array $days): array
    {
        foreach ($days as &$day) {
            $fullDay = [];
            $halfDayBlue = [];
            $halfDayOrange = [];
            $otherHalfDay = [];

            foreach ($day["dispo"] as $spv) {
                if ($spv["value"] === 1.0) {
                    $fullDay[] = $spv;
                } elseif ($spv["value"] === 0.5) {
                    if ($spv["color"] === "#00B0F0") {
                        $halfDayBlue[] = $spv;
                    } elseif ($spv["color"] === "#F79646") {
                        $halfDayOrange[] = $spv;
                    } else {
                        $otherHalfDay[] = $spv; // Handle any other colors
                    }
                }
            }

            // Interleave halfDayBlue and halfDayOrange
            $interleavedHalfDay = [];
            $maxHalfDay = max(count($halfDayBlue), count($halfDayOrange));
            for ($i = 0; $i < $maxHalfDay; $i++) {
                if (isset($halfDayBlue[$i])) {
                    $interleavedHalfDay[] = $halfDayBlue[$i];
                }
                if (isset($halfDayOrange[$i])) {
                    $interleavedHalfDay[] = $halfDayOrange[$i];
                }
            }

            // Add any remaining SPVs from otherHalfDay to the interleaved list
            $interleavedHalfDay = array_merge(
                $interleavedHalfDay,
                $otherHalfDay
            );

            // Replace 'dispo' with the sorted structure
            $day["sortedDispo"] = [
                "fullDay" => $fullDay,
                "halfDay" => $interleavedHalfDay,
            ];
        }

        return $days;
    }
}
