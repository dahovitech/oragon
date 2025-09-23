<?php

namespace App\Bundle\MediaBundle\Controller\Admin;

use App\Bundle\MediaBundle\Entity\Media;
use App\Bundle\MediaBundle\Repository\MediaRepository;
use App\Bundle\MediaBundle\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/media', name: 'admin_media_')]
#[IsGranted('ROLE_ADMIN')]
class MediaController extends AbstractController
{
    public function __construct(
        private MediaUploader $mediaUploader,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(MediaRepository $mediaRepository, Request $request): Response
    {
        $search = $request->query->get('search');
        $type = $request->query->get('type');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        if ($search) {
            $medias = $mediaRepository->searchMedia($search);
            $totalItems = count($medias);
        } elseif ($type) {
            $medias = match($type) {
                'images' => $mediaRepository->findImages(),
                'videos' => $mediaRepository->findVideos(),
                'audio' => $mediaRepository->findAudio(),
                default => $mediaRepository->findAll()
            };
            $totalItems = count($medias);
        } else {
            $medias = $mediaRepository->findWithPagination($page, $limit);
            $totalItems = $mediaRepository->countTotal();
        }

        $totalPages = ceil($totalItems / $limit);
        $statistics = $mediaRepository->getMediaStatistics();

        return $this->render('@MediaBundle/admin/media/index.html.twig', [
            'medias' => $medias,
            'statistics' => $statistics,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'type' => $type,
        ]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->files->get('file');
            
            if (!$uploadedFile instanceof UploadedFile) {
                return new JsonResponse([
                    'success' => false, 
                    'message' => 'Aucun fichier uploadé'
                ], 400);
            }

            // Validate file
            $errors = $this->mediaUploader->validateFile($uploadedFile);
            if (!empty($errors)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => implode(', ', $errors)
                ], 400);
            }

            $alt = $request->request->get('alt');
            $media = $this->mediaUploader->upload($uploadedFile, $alt);

            return new JsonResponse([
                'success' => true,
                'message' => 'Fichier uploadé avec succès',
                'media' => [
                    'id' => $media->getId(),
                    'fileName' => $media->getFileName(),
                    'alt' => $media->getAlt(),
                    'webPath' => $media->getWebPath(),
                    'extension' => $media->getExtension(),
                    'fileSize' => $media->getFormattedFileSize(),
                    'isImage' => $media->isImage(),
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/multi-upload', name: 'multi_upload', methods: ['POST'])]
    public function multiUpload(Request $request): JsonResponse
    {
        try {
            $files = $request->files->get('files', []);
            $alts = $request->request->all('alts') ?: [];
            
            if (empty($files)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucun fichier à uploader'
                ], 400);
            }

            $uploadedMedias = [];
            $errors = [];

            foreach ($files as $index => $file) {
                if ($file instanceof UploadedFile) {
                    $validationErrors = $this->mediaUploader->validateFile($file);
                    if (!empty($validationErrors)) {
                        $errors[] = $file->getClientOriginalName() . ': ' . implode(', ', $validationErrors);
                        continue;
                    }

                    try {
                        $alt = $alts[$index] ?? null;
                        $media = $this->mediaUploader->upload($file, $alt);
                        $uploadedMedias[] = [
                            'id' => $media->getId(),
                            'fileName' => $media->getFileName(),
                            'alt' => $media->getAlt(),
                            'webPath' => $media->getWebPath(),
                            'extension' => $media->getExtension(),
                            'fileSize' => $media->getFormattedFileSize(),
                        ];
                    } catch (\Exception $e) {
                        $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                    }
                }
            }

            $response = [
                'success' => !empty($uploadedMedias),
                'uploadedCount' => count($uploadedMedias),
                'medias' => $uploadedMedias,
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['message'] = 'Certains fichiers n\'ont pas pu être uploadés';
            } else {
                $response['message'] = count($uploadedMedias) . ' fichier(s) uploadé(s) avec succès';
            }

            return new JsonResponse($response);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Media $media): Response
    {
        return $this->render('@MediaBundle/admin/media/show.html.twig', [
            'media' => $media,
            'fileExists' => $this->mediaUploader->fileExists($media),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Media $media): Response
    {
        if ($request->isMethod('POST')) {
            $alt = $request->request->get('alt');
            if ($alt !== null) {
                $media->setAlt($alt);
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Média mis à jour avec succès.');
                return $this->redirectToRoute('admin_media_index');
            }
        }

        return $this->render('@MediaBundle/admin/media/edit.html.twig', [
            'media' => $media,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Media $media): Response
    {
        if ($this->isCsrfTokenValid('delete'.$media->getId(), $request->request->get('_token'))) {
            try {
                $this->mediaUploader->delete($media);
                $this->addFlash('success', 'Média supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_media_index');
    }

    #[Route('/selector', name: 'selector', methods: ['GET'])]
    public function selector(Request $request, MediaRepository $mediaRepository): Response
    {
        $search = $request->query->get('search');
        $type = $request->query->get('type');
        
        if ($search) {
            $medias = $mediaRepository->searchMedia($search);
        } elseif ($type) {
            $medias = match($type) {
                'images' => $mediaRepository->findImages(),
                'videos' => $mediaRepository->findVideos(),
                'audio' => $mediaRepository->findAudio(),
                default => $mediaRepository->findBy([], ['createdAt' => 'DESC'], 50)
            };
        } else {
            $medias = $mediaRepository->findBy([], ['createdAt' => 'DESC'], 50);
        }

        return $this->render('@MediaBundle/admin/media/selector.html.twig', [
            'medias' => $medias,
            'search' => $search,
            'type' => $type,
        ]);
    }

    #[Route('/api/list', name: 'api_list', methods: ['GET'])]
    public function apiList(MediaRepository $mediaRepository, Request $request): JsonResponse
    {
        $search = $request->query->get('search');
        $type = $request->query->get('type');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(10, $request->query->getInt('limit', 20)));

        if ($search) {
            $medias = $mediaRepository->searchMedia($search);
        } elseif ($type) {
            $medias = match($type) {
                'images' => $mediaRepository->findImages(),
                'videos' => $mediaRepository->findVideos(),
                'audio' => $mediaRepository->findAudio(),
                default => $mediaRepository->findWithPagination($page, $limit)
            };
        } else {
            $medias = $mediaRepository->findWithPagination($page, $limit);
        }

        $mediaData = array_map(function(Media $media) {
            return [
                'id' => $media->getId(),
                'fileName' => $media->getFileName(),
                'alt' => $media->getAlt(),
                'webPath' => $media->getWebPath(),
                'extension' => $media->getExtension(),
                'fileSize' => $media->getFormattedFileSize(),
                'mimeType' => $media->getMimeType(),
                'isImage' => $media->isImage(),
                'isVideo' => $media->isVideo(),
                'isAudio' => $media->isAudio(),
                'createdAt' => $media->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $medias);

        return new JsonResponse([
            'success' => true,
            'medias' => $mediaData,
            'count' => count($mediaData),
        ]);
    }
}