<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\Test\IZettle\Sync;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\PayPal\IZettle\Api\Image\BulkImageUpload;
use Swag\PayPal\IZettle\Api\IZettleRequestUri;
use Swag\PayPal\IZettle\Api\Service\MediaConverter;
use Swag\PayPal\IZettle\Client\IZettleClient;
use Swag\PayPal\IZettle\Client\IZettleClientFactory;
use Swag\PayPal\IZettle\DataAbstractionLayer\Entity\IZettleSalesChannelEntity;
use Swag\PayPal\IZettle\DataAbstractionLayer\Entity\IZettleSalesChannelMediaCollection;
use Swag\PayPal\IZettle\DataAbstractionLayer\Entity\IZettleSalesChannelMediaEntity;
use Swag\PayPal\IZettle\Resource\ImageResource;
use Swag\PayPal\IZettle\Sync\ImageSyncer;

class ImageSyncerTest extends TestCase
{
    private const DOMAIN_URL = 'https://some.shopware.domain';
    private const INVALID_MIME_TYPE = 'video/mp4';
    private const LOCAL_FILE_NAME = 'file';
    private const LOCAL_FILE_EXTENSION = 'jpg';
    private const MEDIA_ID_1 = 'mediaId1';
    private const MEDIA_ID_2 = 'mediaId2';
    private const MEDIA_ID_3 = 'mediaId3';
    private const MEDIA_ID_4 = 'mediaId4';
    private const MEDIA_URL_VALID = 'validUrl.jpg';
    private const MEDIA_URL_INVALID = 'invalidUrl.jpg';
    private const MEDIA_URL_EXISTING = 'existingUrl.jpg';
    private const IZETTLE_IMAGE_URL = 'https://image.izettle.com/product/BJfd5OBOEemBrw-6zpwgaA-F1EGGBqgEeq0Zcced6LHlQ.jpeg';
    private const IZETTLE_IMAGE_URL_EXISTING = 'https://image.izettle.com/product/CJfd5OBOEemBrw-6zpwgaA-F1EGGBqgEeq0Zcced6LHlQ.jpeg';
    private const IZETTLE_IMAGE_URL_INVALID = 'https://image.izettle.com/product/AJfd5OBOEemBrw-6zpwgaA-F1EGGBqgEeq0Zcced6LHlQ.jpeg';
    private const IZETTLE_IMAGE_LOOKUP_KEY = 'BJfd5OBOEemBrw-6zpwgaA-F1EGGBqgEeq0Zcced6LHlQ';
    private const IZETTLE_IMAGE_LOOKUP_KEY_EXISTING = 'CJfd5OBOEemBrw-6zpwgaA-F1EGGBqgEeq0Zcced6LHlQ';
    private const IZETTLE_IMAGE_LOOKUP_KEY_INVALID = 'AJfd5OBOEemBrw-6zpwgaA-F1EGGBqgEeq0Zcced6LHlQ';
    private const INVALID_SOURCE_URL = 'https://media3.giphy.com/media/3oeSAF90T9N04MyefS/giphy.gif';

    public function testImageSync(): void
    {
        $context = Context::createDefaultContext();

        $mediaRepository = $this->createMediaRepository($context);
        $imageResource = $this->createImageResource();
        $logger = $this->createLogger();

        $imageSyncer = new ImageSyncer(
            $mediaRepository,
            new MediaConverter($this->createUrlGenerator()),
            $imageResource,
            $logger
        );

        $imageSyncer->syncImages($this->createSalesChannel(), $context);
    }

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('getRelativeMediaUrl')->willReturn(
            self::MEDIA_URL_VALID,
            self::MEDIA_URL_INVALID,
            self::MEDIA_URL_EXISTING
        );

        return $urlGenerator;
    }

    private function createMediaRepository(Context $context): EntityRepositoryInterface
    {
        $mediaRepository = $this->createMock(EntityRepositoryInterface::class);
        $mediaRepository->method('search')->willReturn(
            new EntitySearchResult(
                4,
                new IZettleSalesChannelMediaCollection([
                    $this->createMedia(self::MEDIA_ID_1, self::MEDIA_URL_VALID),
                    $this->createMedia(self::MEDIA_ID_2, self::MEDIA_URL_INVALID),
                    $this->createMedia(self::MEDIA_ID_3, self::MEDIA_URL_VALID, null, false),
                    $this->createMedia(self::MEDIA_ID_4, self::DOMAIN_URL . '/' . self::MEDIA_URL_EXISTING, self::IZETTLE_IMAGE_LOOKUP_KEY_EXISTING),
                ]),
                null,
                new Criteria(),
                $context
            )
        );
        $mediaRepository->expects(static::once())->method('upsert')->with([
            [
                'mediaId' => self::MEDIA_ID_1,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'url' => self::IZETTLE_IMAGE_URL,
                'lookupKey' => self::IZETTLE_IMAGE_LOOKUP_KEY,
            ],
            [
                'mediaId' => self::MEDIA_ID_4,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'url' => self::IZETTLE_IMAGE_URL_EXISTING,
                'lookupKey' => self::IZETTLE_IMAGE_LOOKUP_KEY_EXISTING,
            ],
        ]);

        return $mediaRepository;
    }

    private function createMedia(string $id, string $url, ?string $lookupKey = null, bool $validMime = true): IZettleSalesChannelMediaEntity
    {
        $iZettleMedia = new IZettleSalesChannelMediaEntity();
        $media = new MediaEntity();
        $media->setId($id);
        $media->setUrl($url);
        $media->setFileName(self::LOCAL_FILE_NAME);
        $media->setFileExtension(self::LOCAL_FILE_EXTENSION);
        $media->setMimeType($validMime ? 'image/jpeg' : self::INVALID_MIME_TYPE);
        $iZettleMedia->setMedia($media);
        $iZettleMedia->setMediaId($media->getId());
        $iZettleMedia->setSalesChannelId(Defaults::SALES_CHANNEL);
        $iZettleMedia->setUniqueIdentifier(Uuid::randomHex());
        $iZettleMedia->setLookupKey($lookupKey);

        return $iZettleMedia;
    }

    private function createSalesChannel(): IZettleSalesChannelEntity
    {
        $iZettleSalesChannel = new IZettleSalesChannelEntity();
        $iZettleSalesChannel->setApiKey('apiKey');
        $iZettleSalesChannel->setProductStreamId('someProductStreamId');
        $iZettleSalesChannel->setSyncPrices(true);
        $iZettleSalesChannel->setSalesChannelId(Defaults::SALES_CHANNEL);
        $iZettleSalesChannel->setMediaDomain(self::DOMAIN_URL);

        return $iZettleSalesChannel;
    }

    private function createLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('info')->with(
            'Successfully uploaded {count} images.',
            ['count' => 2]
        );
        $logger->expects(static::exactly(3))->method('warning')->withConsecutive(
            [
                'Media Type {mimeType} is not supported by iZettle. Skipping image {fileName}.',
                [
                    'mimeType' => self::INVALID_MIME_TYPE,
                    'fileName' => self::LOCAL_FILE_NAME . '.' . self::LOCAL_FILE_EXTENSION,
                ],
            ],
            [
                'Could not match uploaded image to local media: {iZettleUrl}',
                ['iZettleUrl' => self::IZETTLE_IMAGE_URL_INVALID],
            ],
            [
                'Upload was not accepted by iZettle (is the URL publicly available?): {invalid}',
                ['invalid' => self::DOMAIN_URL . self::MEDIA_URL_INVALID],
            ]
        );

        return $logger;
    }

    private function createImageResource(): ImageResource
    {
        $client = $this->createMock(IZettleClient::class);
        $client->expects(static::once())->method('sendPostRequest')->with(
            IZettleRequestUri::IMAGE_RESOURCE_BULK,
            (new BulkImageUpload())->assign(['imageUploads' => [
                [
                    'imageFormat' => 'JPEG',
                    'imageUrl' => self::DOMAIN_URL . '/' . self::MEDIA_URL_VALID,
                ],
                [
                    'imageFormat' => 'JPEG',
                    'imageUrl' => self::DOMAIN_URL . '/' . self::MEDIA_URL_INVALID,
                ],
                [
                    'imageFormat' => 'JPEG',
                    'imageUrl' => self::DOMAIN_URL . '/' . self::MEDIA_URL_EXISTING,
                    'imageLookupKey' => self::IZETTLE_IMAGE_LOOKUP_KEY_EXISTING,
                ],
            ]])
        )->willReturn([
            'invalid' => [self::DOMAIN_URL . self::MEDIA_URL_INVALID],
            'uploaded' => [
                [
                    'imageLookupKey' => self::IZETTLE_IMAGE_LOOKUP_KEY,
                    'imageUrls' => [self::IZETTLE_IMAGE_URL],
                    'source' => self::DOMAIN_URL . '/' . self::MEDIA_URL_VALID,
                ],
                [
                    'imageLookupKey' => self::IZETTLE_IMAGE_LOOKUP_KEY_INVALID,
                    'imageUrls' => [self::IZETTLE_IMAGE_URL_INVALID],
                    'source' => self::INVALID_SOURCE_URL,
                ],
                [
                    'imageLookupKey' => self::IZETTLE_IMAGE_LOOKUP_KEY_EXISTING,
                    'imageUrls' => [self::IZETTLE_IMAGE_URL_EXISTING],
                    'source' => self::DOMAIN_URL . '/' . self::MEDIA_URL_EXISTING,
                ],
            ],
        ]);

        $clientFactory = $this->createMock(IZettleClientFactory::class);
        $clientFactory->method('createIZettleClient')->willReturn($client);

        return new ImageResource($clientFactory);
    }
}
