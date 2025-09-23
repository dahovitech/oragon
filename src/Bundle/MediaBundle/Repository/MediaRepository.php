<?php

namespace App\Bundle\MediaBundle\Repository;

use App\Bundle\MediaBundle\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Find media by extension
     */
    public function findByExtension(string $extension): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.extension = :extension')
            ->setParameter('extension', $extension)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find images only
     */
    public function findImages(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.extension IN (:extensions)')
            ->setParameter('extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find videos only
     */
    public function findVideos(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.extension IN (:extensions)')
            ->setParameter('extensions', ['mp4', 'avi', 'mov', 'wmv', 'flv'])
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audio files only
     */
    public function findAudio(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.extension IN (:extensions)')
            ->setParameter('extensions', ['mp3', 'wav', 'flac', 'aac'])
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search media by filename or alt text
     */
    public function searchMedia(string $search): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.alt LIKE :search OR m.fileName LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent media (last 30 days)
     */
    public function findRecentMedia(int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find media by MIME type
     */
    public function findByMimeType(string $mimeType): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.mimeType LIKE :mimeType')
            ->setParameter('mimeType', $mimeType . '%')
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find large files (over specified size in bytes)
     */
    public function findLargeFiles(int $sizeLimit = 10485760): array // 10MB default
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.fileSize > :size')
            ->setParameter('size', $sizeLimit)
            ->orderBy('m.fileSize', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get media statistics
     */
    public function getMediaStatistics(): array
    {
        $totalMedia = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalImages = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.extension IN (:extensions)')
            ->setParameter('extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])
            ->getQuery()
            ->getSingleScalarResult();

        $totalVideos = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.extension IN (:extensions)')
            ->setParameter('extensions', ['mp4', 'avi', 'mov', 'wmv', 'flv'])
            ->getQuery()
            ->getSingleScalarResult();

        $totalAudio = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.extension IN (:extensions)')
            ->setParameter('extensions', ['mp3', 'wav', 'flac', 'aac'])
            ->getQuery()
            ->getSingleScalarResult();

        $totalSize = $this->createQueryBuilder('m')
            ->select('SUM(m.fileSize)')
            ->getQuery()
            ->getSingleScalarResult();

        $recentMedia = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalMedia,
            'images' => $totalImages,
            'videos' => $totalVideos,
            'audio' => $totalAudio,
            'documents' => $totalMedia - $totalImages - $totalVideos - $totalAudio,
            'totalSize' => $totalSize ?: 0,
            'recent' => $recentMedia,
        ];
    }

    /**
     * Find media with pagination
     */
    public function findWithPagination(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total media
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}