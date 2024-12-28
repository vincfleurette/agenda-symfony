<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SpreadsheetService
{
    /**
     * Load a spreadsheet from a file.
     *
     * @throws Exception
     */
    public function loadSpreadsheet(string $filePath): Spreadsheet
    {
        return IOFactory::load($filePath); // Utilisation directe de la méthode statique
    }
}
