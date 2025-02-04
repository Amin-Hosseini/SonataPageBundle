<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\PageBundle\Tests\CmsManager;

use PHPUnit\Framework\TestCase;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\PageBundle\CmsManager\CmsSnapshotManager;
use Sonata\PageBundle\Exception\PageNotFoundException;
use Sonata\PageBundle\Model\Block;
use Sonata\PageBundle\Model\BlockInteractorInterface;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Model\SiteInterface;
use Sonata\PageBundle\Model\SnapshotInterface;
use Sonata\PageBundle\Model\SnapshotManagerInterface;
use Sonata\PageBundle\Model\SnapshotPageProxyInterface;
use Sonata\PageBundle\Model\TransformerInterface;
use Sonata\PageBundle\Tests\Model\Page;

final class SnapshotBlock extends Block
{
    public function setId($id)
    {
    }

    public function getId()
    {
    }
}

final class CmsSnapshotManagerTest extends TestCase
{
    protected CmsSnapshotManager $manager;

    protected $blockInteractor;

    protected $snapshotManager;

    protected $transformer;

    /**
     * Setup manager object to test.
     */
    protected function setUp(): void
    {
        $this->blockInteractor = $this->getMockBlockInteractor();
        $this->snapshotManager = $this->createMock(SnapshotManagerInterface::class);
        $this->transformer = $this->createMock(TransformerInterface::class);
        $this->manager = new CmsSnapshotManager($this->snapshotManager, $this->transformer);
    }

    /**
     * Test finding an existing container in a page.
     */
    public function testFindExistingContainer(): void
    {
        $block = new SnapshotBlock();
        $block->setSettings(['code' => 'findme']);

        $page = new Page();
        $page->addBlocks($block);

        $container = $this->manager->findContainer('findme', $page);

        static::assertSame(
            spl_object_hash($block),
            spl_object_hash($container),
            'should retrieve the block of the page'
        );
    }

    /**
     * Test finding an non-existing container in a page does NOT create a new block.
     */
    public function testFindNonExistingContainerCreatesNoNewBlock(): void
    {
        $page = new Page();

        $container = $this->manager->findContainer('newcontainer', $page);

        static::assertNull($container, 'should not create a new container block');
    }

    public function testGetPageWithUnknownPage(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->snapshotManager->expects(static::once())->method('findEnableSnapshot')->willReturn(null);

        $site = $this->createMock(SiteInterface::class);

        $snapshotManager = new CmsSnapshotManager($this->snapshotManager, $this->transformer);

        $snapshotManager->getPage($site, 1);
    }

    public function testGetPageWithId(): void
    {
        $cBlock = $this->createMock(BlockInterface::class);
        $cBlock->method('hasChildren')->willReturn(false);
        $cBlock->method('getId')->willReturn(2);

        $pBlock = $this->createMock(BlockInterface::class);
        $pBlock->method('getChildren')->willReturn([$cBlock]);
        $pBlock->method('hasChildren')->willReturn(true);
        $pBlock->method('getId')->willReturn(1);

        $page = $this->createMock(PageInterface::class);
        $page->method('getBlocks')->willReturnCallback(static function () use ($pBlock) {
            static $count;

            ++$count;

            if (1 === $count) {
                return [];
            }

            return [$pBlock];
        });

        $snapshot = $this->createMock(SnapshotInterface::class);
        $snapshot->expects(static::once())->method('getContent')->willReturn([
            // we don't care here about real values, the mock transformer will return the valid $pBlock instance
            'blocks' => [],
        ]);

        $this->snapshotManager
            ->expects(static::once())
            ->method('findEnableSnapshot')
            ->willReturn($snapshot);

        $this->transformer->expects(static::once())->method('load')->willReturn($page);

        $site = $this->createMock(SiteInterface::class);

        $snapshotManager = new CmsSnapshotManager($this->snapshotManager, $this->transformer);

        $page = $snapshotManager->getPage($site, 1);

        static::assertInstanceOf(SnapshotPageProxyInterface::class, $page);

        static::assertInstanceOf(BlockInterface::class, $snapshotManager->getBlock(1));
        static::assertInstanceOf(BlockInterface::class, $snapshotManager->getBlock(2));
    }

    /**
     * Returns a mock block interactor.
     */
    protected function getMockBlockInteractor(): BlockInteractorInterface
    {
        $callback = static function ($options) {
            $block = new SnapshotBlock();
            $block->setSettings($options);

            return $block;
        };

        $blockInteractor = $this->createMock(BlockInteractorInterface::class);
        $blockInteractor
            ->method('createNewContainer')
            ->willReturnCallback($callback);

        return $blockInteractor;
    }
}
