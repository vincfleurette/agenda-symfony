<?php

namespace App\Controller;

use App\Form\ExcelImportType;
use App\Form\SheetSelectionType;
use App\Service\ExcelImportService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;

class AgentController extends AbstractController
{
    private ExcelImportService $excelImportService;
    private FormFactoryInterface $formFactory;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(
        ExcelImportService $excelImportService,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->excelImportService = $excelImportService;
        $this->formFactory = $formFactory;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    #[Route("/agent/import", name: "agent_import", methods: ["GET", "POST"])]
    public function importFile(Request $request): Response
    {
        $this->logger->info("Accès à la page d'importation.");

        $form = $this->createForm(ExcelImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info("Formulaire d'import soumis et valide.");

            /**
             * @var UploadedFile $file
             */
            $file = $form->get("file")->getData();

            // Sauvegarder le fichier téléchargé
            $filePath = $this->saveUploadedFile($file);

            if (!$filePath) {
                $this->addFlash(
                    "error",
                    'Erreur lors de l\'enregistrement du fichier.'
                );
                return $this->redirectToRoute("agent_import");
            }

            $this->logger->info(
                "Fichier enregistré avec succès : " . $filePath
            );

            // Enregistrer le chemin du fichier en session
            $this->requestStack
                ->getSession()
                ->set("uploadedFilePath", $filePath);

            return $this->redirectToRoute("agent_choose_sheet");
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logger->warning("Formulaire d'import soumis mais invalide.");
        }

        return $this->render("agent/_import.html.twig", [
            "form" => $form->createView(),
        ]);
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
        $session = $this->requestStack->getSession();
        $filePath = $session->get("uploadedFilePath");

        if (!$filePath || !file_exists($filePath)) {
            $this->addFlash("error", "Fichier non spécifié ou introuvable.");
            $this->logger->error(
                "Fichier introuvable : " . ($filePath ?? "NULL")
            );
            return $this->redirectToRoute("agent_import");
        }

        //$this->logger->info("Traitement du fichier pour la sélection de feuille : " . $filePath);

        try {
            $sheetNames = $this->excelImportService->getSheetNames($filePath);
            //$this->logger->info('Feuilles disponibles : ' . json_encode($sheetNames));
        } catch (\Exception $e) {
            $this->logger->error(
                "Erreur lors de la récupération des feuilles : " .
                    $e->getMessage()
            );
            $this->addFlash(
                "error",
                "Erreur lors de la récupération des feuilles."
            );
            return $this->redirectToRoute("agent_import");
        }

        $sheetForm = $this->createForm(SheetSelectionType::class, null, [
            "sheets" => $sheetNames,
        ]);
        $sheetForm->handleRequest($request);

        if ($sheetForm->isSubmitted() && $sheetForm->isValid()) {
            $sheetIndex = $sheetForm->get("sheet")->getData();
            //$this->logger->info("Feuille sélectionnée avec l'index : " . $sheetIndex);

            try {
                $data = $this->excelImportService->importAndSortAvailabilityData(
                    filePath: $filePath,
                    sheetIndex: (int) $sheetIndex
                );
                //$this->logger->info("Données importées avec succès pour la feuille indexée à : " . $sheetIndex);
            } catch (\Exception $e) {
                $this->logger->error(
                    "Erreur lors de l'importation des données : " .
                        $e->getMessage()
                );
                $this->addFlash(
                    "error",
                    'Erreur lors de l\'importation des données.'
                );
                return $this->redirectToRoute("agent_choose_sheet");
            }
            //$this->logger->info("Données importées avec succès ", $data);
            // Calculate the maximum SPVs for any day
            $maxSpvs = 0;
            foreach ($data as $day) {
                $spvsForDay =
                    count($day["sortedDispo"]["fullDay"]) +
                    count($day["sortedDispo"]["halfDay"]);
                if ($spvsForDay > $maxSpvs) {
                    $maxSpvs = $spvsForDay;
                }
            }

            return $this->render("agent/_import_success.html.twig", [
                "data" => $data,
                "maxSpvs" => $maxSpvs,
            ]);
        }

        if ($sheetForm->isSubmitted() && !$sheetForm->isValid()) {
            $this->logger->warning(
                "Formulaire de sélection de feuille invalide."
            );
        }

        return $this->render("agent/choose_sheet.html.twig", [
            "sheetForm" => $sheetForm->createView(),
        ]);
    }

    private function saveUploadedFile(UploadedFile $file): ?string
    {
        $filesystem = new Filesystem();
        $uploadsDir =
            $this->getParameter("kernel.project_dir") . "/var/uploads";

        try {
            if (!$filesystem->exists($uploadsDir)) {
                $filesystem->mkdir($uploadsDir);
            }

            $filePath =
                $uploadsDir .
                "/" .
                uniqid() .
                "-" .
                $file->getClientOriginalName();
            $file->move($uploadsDir, basename($filePath));

            //$this->logger->info("Fichier déplacé avec succès vers : " . $filePath);
            return $filePath;
        } catch (\Exception $e) {
            $this->logger->error(
                "Erreur lors de l'enregistrement du fichier : " .
                    $e->getMessage()
            );
            $this->addFlash(
                "error",
                'Erreur lors de l\'enregistrement du fichier : ' .
                    $e->getMessage()
            );
            return null;
        }
    }
}
