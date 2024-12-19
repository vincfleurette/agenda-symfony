<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;

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
            fn ($spreadsheet) => $spreadsheet->getSheetNames()
        );
    }

    /**
     * Import and preprocess availability data from a specific sheet.
     */
    public function importAndSortAvailabilityData(
        string $filePath,
        int $sheetIndex,
    ): array {
        return $this->processSpreadsheet($filePath, function (
            $spreadsheet,
        ) use ($sheetIndex) {
            $sheet = $this->validateSheetIndex($spreadsheet, $sheetIndex);
            $rawData = $this->extractAvailabilityData($sheet, $sheetIndex);

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
            throw new \Exception(sprintf("La feuille spécifiée avec l'index (%d) n'existe pas. Feuilles disponibles : %s", $sheetIndex, json_encode($sheetNames)));
        }

        $sheet = $spreadsheet->getSheetByName($sheetNames[$sheetIndex]);
        if (!$sheet) {
            throw new \Exception('Impossible de charger la feuille spécifiée : '.$sheetNames[$sheetIndex]);
        }

        // $this->logger->info("Analyse de la feuille : {$sheetNames[$sheetIndex]}");
        return $sheet;
    }

    /**
     * Extract availability data from a specific sheet.
     */
    private function extractAvailabilityData($sheet, int $sheetIndex): array
    {
        $data = [];
        $startRow = 5; // SPV names start from row 5
        $nameColumn = 'A'; // SPV names column
        $firstDateColumn = 'C'; // First date column

        $days = $this->extractDayHeaders($sheet, $firstDateColumn, $sheetIndex);

        foreach ($sheet->getRowIterator($startRow) as $row) {
            $rowIndex = $row->getRowIndex();
            $name = $sheet->getCell("{$nameColumn}{$rowIndex}")->getValue();

            if (!$name || 'desiderata équipe :' === strtolower(trim($name))) {
                continue;
            }

            foreach ($days as $columnIndex => &$day) {
                $cell = $sheet->getCell("{$columnIndex}{$rowIndex}");
                $value = $cell->getValue();

                if (null === $value || '' === $value) {
                    continue;
                }

                $day['dispo'][] = $this->createDispoEntry(
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
    private function extractDayHeaders(
        $sheet,
        string $firstDateColumn,
        int $sheetIndex,
    ): array {
        $headers = [];
        $year = date('Y'); // Année actuelle, ajustez si nécessaire

        foreach ($sheet->getColumnIterator($firstDateColumn) as $column) {
            $columnIndex = $column->getColumnIndex();
            $dayNumber = $sheet->getCell("{$columnIndex}4")->getValue();
            $team = $sheet->getCell("{$columnIndex}2")->getValue();

            // Vérifier si le numéro du jour est valide
            if (empty($dayNumber) || !is_numeric($dayNumber)) {
                /*$this->logger->error(
                    "Numéro de jour invalide ou absent à la colonne {$columnIndex}."
                );*/
                continue; // Ignorer cette colonne
            }

            // Créer la date complète
            $dateString = $this->generateFullDate(
                $year,
                $sheetIndex,
                (int) $dayNumber
            );

            $headers[$columnIndex] = [
                'date' => $dateString,
                'equipe' => $team,
                'dispo' => [],
            ];
        }

        return $headers;
    }

    /**
     * Générer une date complète (nom du jour, numéro du jour, mois) en français
     * avec IntlDateFormatter.
     */
    private function generateFullDate(
        string $year,
        int $monthIndex,
        int $dayNumber,
    ): string {
        if (empty($dayNumber) || $dayNumber < 1 || $dayNumber > 31) {
            throw new \InvalidArgumentException("Le numéro du jour ({$dayNumber}) est invalide.");
        }

        // Créer une instance de DateTime
        $date = \DateTime::createFromFormat(
            'Y-n-j',
            "{$year}-{$monthIndex}-{$dayNumber}"
        );
        if (!$date) {
            throw new \Exception("Date invalide générée avec : Année={$year}, Mois={$monthIndex}, Jour={$dayNumber}");
        }

        // Configurer IntlDateFormatter pour le français
        $formatter = new \IntlDateFormatter(
            'fr_FR', // Locale française
            \IntlDateFormatter::FULL, // Style complet
            \IntlDateFormatter::NONE, // Pas d'heure
            'UTC', // Fuseau horaire
            \IntlDateFormatter::GREGORIAN // Calendrier grégorien
        );

        // Modifier le format pour afficher le jour, le numéro et le mois
        $formatter->setPattern('EEEE d MMMM');

        return $formatter->format($date);
    }

    /**
     * Create a single SPV availability entry.
     */
    private function createDispoEntry(
        string $name,
        $value,
        $sheet,
        string $cellReference,
    ): array {
        $entry = [
            'nom spv' => $name,
            'value' => is_numeric($value) ? (float) $value : 0,
            'partDay' => 0.5 === $value,
            'color' => null,
        ];

        if (0.5 === $value) {
            $style = $sheet->getStyle($cellReference);
            $color = $style
                ->getFill()
                ->getStartColor()
                ->getRGB();
            $entry['color'] = $color ? "#{$color}" : null;
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

            foreach ($day['dispo'] as $spv) {
                if (1.0 === $spv['value']) {
                    $fullDay[] = $spv;
                } elseif (0.5 === $spv['value']) {
                    if ('#00B0F0' === $spv['color']) {
                        $halfDayBlue[] = $spv;
                    } elseif ('#F79646' === $spv['color']) {
                        $halfDayOrange[] = $spv;
                    } else {
                        $otherHalfDay[] = $spv; // Handle any other colors
                    }
                }
            }

            // Interleave halfDayBlue and halfDayOrange
            $interleavedHalfDay = [];
            $maxHalfDay = max(count($halfDayBlue), count($halfDayOrange));
            for ($i = 0; $i < $maxHalfDay; ++$i) {
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
            $day['sortedDispo'] = [
                'fullDay' => $fullDay,
                'halfDay' => $interleavedHalfDay,
            ];
        }

        return $days;
    }
}
