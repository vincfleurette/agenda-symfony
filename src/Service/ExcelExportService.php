<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class ExcelExportService
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createExcelFile(array $data, string $sheetName): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        $sheet->setCellValue("A1", "Date")->setCellValue("B1", "Équipe");

        $currentColumn = "C";
        $maxSpvs = $this->calculateMaxSpvs($data);

        // Ajouter des en-têtes dynamiques pour les SPVs
        for ($i = 1; $i <= $maxSpvs; ++$i) {
            $sheet->setCellValue("{$currentColumn}1", "$i");
            ++$currentColumn;
        }

        $row = 2;
        foreach ($data as $day) {
            $sheet->setCellValue("A{$row}", $day["date"] ?? "");
            $sheet->setCellValue("B{$row}", $day["equipe"] ?? "");

            $currentColumn = "C";
            foreach (
                array_merge(
                    $day["sortedDispo"]["fullDay"],
                    $day["sortedDispo"]["halfDay"]
                ) as $spv
            ) {
                $sheet->setCellValue("{$currentColumn}{$row}", $spv["nom spv"]);

                // Appliquer la couleur si nécessaire
                if (!empty($spv["color"])) {
                    $sheet
                        ->getStyle("{$currentColumn}{$row}")
                        ->getFill()
                        ->setFillType(
                            \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID
                        )
                        ->getStartColor()
                        ->setRGB(ltrim($spv["color"], "#"));
                }

                // Ajouter une bordure autour de la cellule
                $sheet
                    ->getStyle("{$currentColumn}{$row}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(
                        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                    );

                ++$currentColumn;
            }

            // Ajouter des bordures pour les colonnes "Date" et "Équipe"
            $sheet
                ->getStyle("A{$row}:B{$row}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );

            ++$row;
        }

        // Ajouter une bordure aux en-têtes
        $sheet
            ->getStyle("A1:{$currentColumn}1")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        // Ajuster automatiquement la largeur des colonnes
        foreach (range("A", $currentColumn) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function saveExcelFile(Spreadsheet $spreadsheet): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), "excel");
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    public function createExcelDownloadResponse(
        string $filePath,
        string $sheetName
    ): Response {
        $response = new Response(file_get_contents($filePath));
        $response->headers->set(
            "Content-Type",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );
        $response->headers->set(
            "Content-Disposition",
            'attachment;filename="Dispos ' . $sheetName . '.xlsx"'
        );
        $response->headers->set("Cache-Control", "max-age=0");
        unlink($filePath);

        return $response;
    }

    public function calculateMaxSpvs(array $data): int
    {
        return array_reduce(
            $data,
            function ($max, $day) {
                $spvsForDay =
                    count($day["sortedDispo"]["fullDay"]) +
                    count($day["sortedDispo"]["halfDay"]);

                return max($max, $spvsForDay);
            },
            0
        );
    }
}
