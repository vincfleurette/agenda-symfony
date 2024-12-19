<?php

namespace App\Controller;

use App\Form\ExcelImportType;
use App\Form\SheetSelectionType;
use App\Service\ExcelImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
        LoggerInterface $logger,
    ) {
        $this->excelImportService = $excelImportService;
        $this->formFactory = $formFactory;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    #[Route('/agent/import', name: 'agent_import', methods: ['GET', 'POST'])]
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
            $file = $form->get('file')->getData();

            // Sauvegarder le fichier téléchargé
            $filePath = $this->saveUploadedFile($file);

            if (!$filePath) {
                $this->addFlash(
                    'error',
                    'Erreur lors de l\'enregistrement du fichier.'
                );

                return $this->redirectToRoute('agent_import');
            }

            $this->logger->info(
                'Fichier enregistré avec succès : '.$filePath
            );

            // Enregistrer le chemin du fichier en session
            $this->requestStack
                ->getSession()
                ->set('uploadedFilePath', $filePath);

            return $this->redirectToRoute('agent_choose_sheet');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logger->warning("Formulaire d'import soumis mais invalide.");
        }

        return $this->render('agent/_import.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[
        Route(
            '/agent/choose-sheet',
            name: 'agent_choose_sheet',
            methods: ['GET', 'POST']
        )
    ]
    public function chooseSheet(Request $request): Response
    {
        $session = $this->requestStack->getSession();
        $filePath = $session->get('uploadedFilePath');

        if (!$filePath || !file_exists($filePath)) {
            $this->addFlash('error', 'Fichier non spécifié ou introuvable.');
            $this->logger->error(
                'Fichier introuvable : '.($filePath ?? 'NULL')
            );

            return $this->redirectToRoute('agent_import');
        }

        // $this->logger->info("Traitement du fichier pour la sélection de feuille : " . $filePath);

        try {
            $sheetNames = $this->excelImportService->getSheetNames($filePath);
            // $this->logger->info('Feuilles disponibles : ' . json_encode($sheetNames));
        } catch (\Exception $e) {
            $this->logger->error(
                'Erreur lors de la récupération des feuilles : '.
                    $e->getMessage()
            );
            $this->addFlash(
                'error',
                'Erreur lors de la récupération des feuilles.'
            );

            return $this->redirectToRoute('agent_import');
        }

        $sheetForm = $this->createForm(SheetSelectionType::class, null, [
            'sheets' => $sheetNames,
        ]);
        $sheetForm->handleRequest($request);

        if ($sheetForm->isSubmitted() && $sheetForm->isValid()) {
            $sheetIndex = $sheetForm->get('sheet')->getData();
            // $this->logger->info("Feuille sélectionnée avec l'index : " . $sheetIndex);

            try {
                $data = $this->excelImportService->importAndSortAvailabilityData(
                    filePath: $filePath,
                    sheetIndex: (int) $sheetIndex
                );
                // $this->logger->info("Données importées avec succès pour la feuille indexée à : " . $sheetIndex);
            } catch (\Exception $e) {
                $this->logger->error(
                    "Erreur lors de l'importation des données : ".
                        $e->getMessage()
                );
                $this->addFlash(
                    'error',
                    'Erreur lors de l\'importation des données.'
                );

                return $this->redirectToRoute('agent_choose_sheet');
            }
            // $this->logger->info("Données importées avec succès ", $data);
            // Calculate the maximum SPVs for any day
            $maxSpvs = 0;
            foreach ($data as $day) {
                $spvsForDay =
                    count($day['sortedDispo']['fullDay']) +
                    count($day['sortedDispo']['halfDay']);
                if ($spvsForDay > $maxSpvs) {
                    $maxSpvs = $spvsForDay;
                }
            }

            return $this->render('agent/_import_success.html.twig', [
                'data' => $data,
                'maxSpvs' => $maxSpvs,
                'filePath' => base64_encode($filePath), // Encode le chemin
                'sheetIndex' => $sheetIndex, // Si nécessaire pour le bouton d'export
            ]);
        }

        if ($sheetForm->isSubmitted() && !$sheetForm->isValid()) {
            $this->logger->warning(
                'Formulaire de sélection de feuille invalide.'
            );
        }

        return $this->render('agent/choose_sheet.html.twig', [
            'sheetForm' => $sheetForm->createView(),
        ]);
    }

    #[
        Route(
            '/agent/export-excel/{filePath}/{sheetIndex}',
            name: 'agent_export_excel',
            methods: ['GET']
        )
    ]
    public function exportExcel(string $filePath, int $sheetIndex): Response
    {
        $this->logger->info('Export Excel démarré.', [
            'filePath' => $filePath,
            'sheetIndex' => $sheetIndex,
        ]);

        // Décoder le chemin du fichier
        $filePath = base64_decode($filePath);

        try {
            // Importer les données via le service existant
            $data = $this->excelImportService->importAndSortAvailabilityData(
                filePath: $filePath,
                sheetIndex: $sheetIndex
            );

            $this->logger->info('Données importées avec succès pour Excel.', [
                'data_count' => count($data),
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                'Erreur lors de l\'export des données : '.$e->getMessage()
            );
            $this->addFlash('error', 'Erreur lors de l\'export des données.');

            return $this->redirectToRoute('agent_choose_sheet');
        }

        // Créer une instance de Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Disponibilités');

        // Ajouter des en-têtes dynamiques
        $sheet->setCellValue('A1', 'Date');
        $sheet->setCellValue('B1', 'Équipe');

        // Définir la colonne de départ pour les SPVs
        $currentColumn = 'C';

        // Identifier le nombre maximum de SPVs pour définir les colonnes
        $maxSpvs = 0;
        foreach ($data as $day) {
            $spvsForDay =
                count($day['sortedDispo']['fullDay']) +
                count($day['sortedDispo']['halfDay']);
            $maxSpvs = max($maxSpvs, $spvsForDay);
        }

        // Ajouter des colonnes pour chaque SPV (1 par SPV disponible)
        for ($i = 1; $i <= $maxSpvs; ++$i) {
            $sheet->setCellValue("{$currentColumn}1", "$i");
            ++$currentColumn;
        }

        // Ajouter les données au fichier Excel
        $row = 2; // Ligne de départ après les en-têtes
        foreach ($data as $day) {
            // Insérer la date et l'équipe
            $sheet->setCellValue("A{$row}", $day['date'] ?? '');
            $sheet->setCellValue("B{$row}", $day['equipe'] ?? '');

            // Ajouter les disponibilités des SPVs
            $spvs = array_merge(
                $day['sortedDispo']['fullDay'],
                $day['sortedDispo']['halfDay']
            );

            $currentColumn = 'C'; // Repartir à la colonne SPV
            foreach ($spvs as $spv) {
                $sheet->setCellValue("{$currentColumn}{$row}", $spv['nom spv']);

                // Appliquer une couleur si le SPV est en demi-journée
                if ($spv['partDay']) {
                    $color = $spv['color'] ?? null;

                    if ($color) {
                        $sheet
                            ->getStyle("{$currentColumn}{$row}")
                            ->getFill()
                            ->setFillType(
                                \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID
                            )
                            ->getStartColor()
                            ->setRGB(ltrim($color, '#'));
                    }
                }

                // Ajouter une bordure à la cellule non nulle
                $sheet
                    ->getStyle("{$currentColumn}{$row}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(
                        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                    );

                ++$currentColumn;
            }

            // Ajouter des bordures aux colonnes "Date" et "Équipe"
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
        foreach (range('A', $currentColumn) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Générer le fichier Excel
        $writer = new Xlsx($spreadsheet);

        // Écrire temporairement le fichier sur le disque
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        // Vérifier si le fichier a été généré avec succès
        if (!file_exists($tempFile)) {
            $this->logger->error('Le fichier Excel n\'a pas été généré.');
            $this->addFlash(
                'error',
                'Erreur lors de la génération du fichier Excel.'
            );

            return $this->redirectToRoute('agent_choose_sheet');
        }

        $this->logger->info('Fichier Excel généré avec succès.', [
            'file' => $tempFile,
        ]);

        // Créer une réponse HTTP pour le téléchargement
        $response = new Response();
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set(
            'Content-Disposition',
            'attachment;filename="disponibilites.xlsx"'
        );
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->setContent(file_get_contents($tempFile));

        // Supprimer le fichier temporaire
        unlink($tempFile);

        return $response;
    }

    private function saveUploadedFile(UploadedFile $file): ?string
    {
        $filesystem = new Filesystem();
        $uploadsDir =
            $this->getParameter('kernel.project_dir').'/var/uploads';

        try {
            if (!$filesystem->exists($uploadsDir)) {
                $filesystem->mkdir($uploadsDir);
            }

            $filePath =
                $uploadsDir.
                '/'.
                uniqid().
                '-'.
                $file->getClientOriginalName();
            $file->move($uploadsDir, basename($filePath));

            // $this->logger->info("Fichier déplacé avec succès vers : " . $filePath);
            return $filePath;
        } catch (\Exception $e) {
            $this->logger->error(
                "Erreur lors de l'enregistrement du fichier : ".
                    $e->getMessage()
            );
            $this->addFlash(
                'error',
                'Erreur lors de l\'enregistrement du fichier : '.
                    $e->getMessage()
            );

            return null;
        }
    }
}
