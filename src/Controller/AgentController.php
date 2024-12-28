<?php

namespace App\Controller;

use App\Form\ExcelImportType;
use App\Form\SheetSelectionType;
use App\Service\ExcelExportService;
use App\Service\ExcelImportService;
use App\Service\FileExcelService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgentController extends AbstractController
{
    private ExcelImportService $excelImportService;
    private ExcelExportService $excelExportService;
    private LoggerInterface $logger;
    private FileExcelService $fileExcelService;
    private RequestStack $requestStack;

    public function __construct(
        ExcelImportService $excelImportService,
        ExcelExportService $excelExportService,
        LoggerInterface $logger,
        FileExcelService $fileExcelService,
        RequestStack $requestStack
    ) {
        // Injecté via les paramètres de configuration
        $this->excelImportService = $excelImportService;
        $this->excelExportService = $excelExportService;
        $this->logger = $logger;
        $this->fileExcelService = $fileExcelService;
        $this->requestStack = $requestStack;
    }

    #[Route("/agent/import", name: "agent_import", methods: ["GET", "POST"])]
    public function importFile(Request $request): Response
    {
        $form = $this->createForm(ExcelImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->render("agent/_import.html.twig", [
                "form" => $form->createView(),
            ]);
        }

        if (!$form->isValid()) {
            $this->logger->warning("Formulaire d'import soumis mais invalide.");
            $this->addFlash("error", "Formulaire invalide.");

            return $this->render("agent/_import.html.twig", [
                "form" => $form->createView(),
            ]);
        }

        $file = $form->get("file")->getData();
        $filePath = $this->fileExcelService->saveUploadedFile($file);

        if (!$filePath) {
            $this->addFlash(
                "error",
                'Erreur lors de l\'enregistrement du fichier.'
            );

            return $this->redirectToRoute("agent_import");
        }

        $this->logger->info("Fichier enregistré avec succès : " . $filePath);
        $this->requestStack->getSession()->set("uploadedFilePath", $filePath);

        return $this->redirectToRoute("agent_choose_sheet");
    }

    #[
        Route(
            "/agent/choose-sheet",
            name: "agent_choose_sheet",
            methods: ["GET", "POST"]
        )
    ]
    public function chooseSheet(Request $request): Response
    {
        $filePath = $this->getSessionValue("uploadedFilePath");
        if (!$this->fileExists($filePath)) {
            return $this->handleFileError(
                "Fichier non spécifié ou introuvable.",
                "agent_import"
            );
        }

        try {
            $sheetNames = $this->excelImportService->getSheetNames($filePath);
        } catch (\Exception $e) {
            return $this->handleException(
                $e,
                "Erreur lors de la récupération des feuilles.",
                "agent_import"
            );
        }

        $sheetForm = $this->createForm(SheetSelectionType::class, null, [
            "sheets" => $sheetNames,
        ]);
        $sheetForm->handleRequest($request);

        if ($sheetForm->isSubmitted() && $sheetForm->isValid()) {
            $sheetIndex = $sheetForm->get("sheet")->getData();

            try {
                $data = $this->excelImportService->importAndSortAvailabilityData(
                    $filePath,
                    (int) $sheetIndex
                );
                $maxSpvs = $this->excelExportService->calculateMaxSpvs($data);

                return $this->render("agent/_import_success.html.twig", [
                    "data" => $data,
                    "maxSpvs" => $maxSpvs,
                    "filePath" => base64_encode($filePath),
                    "sheetIndex" => $sheetIndex,
                    "sheetName" => $sheetNames[$sheetIndex],
                ]);
            } catch (\Exception $e) {
                return $this->handleException(
                    $e,
                    'Erreur lors de l\'importation des données.',
                    "agent_choose_sheet"
                );
            }
        }

        return $this->render("agent/choose_sheet.html.twig", [
            "sheetForm" => $sheetForm->createView(),
        ]);
    }

    #[
        Route(
            "/agent/export-excel/{filePath}/{sheetIndex}/{sheetName}",
            name: "agent_export_excel",
            methods: ["GET"]
        )
    ]
    public function exportExcel(
        string $filePath,
        int $sheetIndex,
        string $sheetName
    ): Response {
        $filePath = base64_decode($filePath);

        try {
            $data = $this->excelImportService->importAndSortAvailabilityData(
                $filePath,
                $sheetIndex
            );
            $spreadsheet = $this->excelExportService->createExcelFile(
                $data,
                $sheetName
            );
            $tempFile = $this->excelExportService->saveExcelFile($spreadsheet);

            return $this->excelExportService->createExcelDownloadResponse(
                $tempFile,
                $sheetName
            );
        } catch (\Exception $e) {
            return $this->handleException(
                $e,
                'Erreur lors de l\'exportation des données.',
                "agent_choose_sheet"
            );
        }
    }

    // --- Méthodes privées ---

    private function getSessionValue(string $key)
    {
        return $this->requestStack->getSession()->get($key);
    }

    private function fileExists(string $filePath): bool
    {
        return file_exists($filePath);
    }

    private function handleFileError(string $message, string $route): Response
    {
        $this->addFlash("error", $message);
        $this->logger->error($message);

        return $this->redirectToRoute($route);
    }

    private function handleException(
        \Exception $errorMessage,
        string $message,
        string $route
    ): Response {
        $this->logger->error("$message : {$errorMessage->getMessage()}");
        $this->addFlash("error", $message);

        return $this->redirectToRoute($route);
    }
}
