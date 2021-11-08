<?php

namespace Gedmo\Tests\SoftDeleteable;

use Doctrine\Common\EventManager;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Gedmo\Tests\SoftDeleteable\Fixture\Document\User;
use Gedmo\Tests\Tool\BaseTestCaseMongoODM;

/**
 * These are tests for SoftDeleteable behavior
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Patrik Votoček <patrik@votocek.cz>
 *
 * @see http://www.gediminasm.org
 *
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class SoftDeleteableDocumentTest extends BaseTestCaseMongoODM
{
    public const ARTICLE_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\Article';
    public const COMMENT_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\Comment';
    public const PAGE_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\Page';
    public const MEGA_PAGE_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\MegaPage';
    public const MODULE_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\Module';
    public const OTHER_ARTICLE_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\OtherArticle';
    public const OTHER_COMMENT_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\OtherComment';
    public const USER_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\User';
    public const USER__TIME_AWARE_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\UserTimeAware';
    public const MAPPED_SUPERCLASS_CHILD_CLASS = 'Gedmo\Tests\SoftDeleteable\Fixture\Document\Child';
    public const SOFT_DELETEABLE_FILTER_NAME = 'soft-deleteable';

    private $softDeleteableListener;

    protected function setUp(): void
    {
        parent::setUp();

        $evm = new EventManager();
        $this->softDeleteableListener = new SoftDeleteableListener();
        $evm->addEventSubscriber($this->softDeleteableListener);
        $config = $this->getMockAnnotatedConfig();
        $config->addFilter(self::SOFT_DELETEABLE_FILTER_NAME, 'Gedmo\SoftDeleteable\Filter\ODM\SoftDeleteableFilter');

        $this->dm = $this->getMockDocumentManager($evm, $config);
        $this->dm->getFilterCollection()->enable(self::SOFT_DELETEABLE_FILTER_NAME);
    }

    /**
     * @test
     */
    public function shouldSoftlyDeleteIfColumnNameDifferFromPropertyName()
    {
        $repo = $this->dm->getRepository(self::USER_CLASS);

        $newUser = new User();

        $username = 'test_user';
        $newUser->setUsername($username);

        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => $username]);

        static::assertNull($user->getDeletedAt());

        $this->dm->remove($user);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => $username]);

        static::assertNull($user);
    }

    /**
     * Tests the filter by enabling and disabling it between
     * some user persists actions.
     */
    public function testSoftDeleteableFilter()
    {
        $filter = $this->dm->getFilterCollection()->getFilter(self::SOFT_DELETEABLE_FILTER_NAME);
        $filter->disableForDocument(self::USER_CLASS);

        $repo = $this->dm->getRepository(self::USER_CLASS);

        $newUser = new User();
        $username = 'test_user';
        $newUser->setUsername($username);
        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => $username]);

        static::assertNull($user->getDeletedAt());
        $this->dm->remove($user);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => $username]);

        static::assertNotNull($user->getDeletedAt());

        $filter->enableForDocument(self::USER_CLASS);

        $user = $repo->findOneBy(['username' => $username]);
        static::assertNull($user);
    }

    /**
     * Tests the filter with time aware option by enabling and disabling it between
     * some user persists actions.
     *
     * @TODO: not supported in ODM yet
     * test
     */
    public function shouldSupportSoftDeleteableFilterTimeAware()
    {
        $filter = $this->dm->getFilterCollection()->getFilter(self::SOFT_DELETEABLE_FILTER_NAME);
        $filter->disableForDocument(self::USER__TIME_AWARE_CLASS);

        $repo = $this->dm->getRepository(self::USER__TIME_AWARE_CLASS);

        //Find entity with deletedAt date in future
        $newUser = new User();
        $username = 'test_user';
        $newUser->setUsername($username);
        $newUser->setDeletedAt(new \DateTime('tomorrow'));
        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => $username]);

        $this->dm->remove($user);
        $this->dm->flush();

        //Don't find entity with deletedAt date in past
        $newUser = new User();
        $username = 'test_user';
        $newUser->setUsername($username);
        $newUser->setDeletedAt(new \DateTime('yesterday'));
        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => $username]);

        static::assertNull($user);
        $this->dm->remove($user);
        $this->dm->flush();
    }

    public function testPostSoftDeleteEventIsDispatched()
    {
        $subscriber = $this->getMockBuilder("Doctrine\Common\EventSubscriber")
            ->setMethods([
                'getSubscribedEvents',
                'preSoftDelete',
                'postSoftDelete',
            ])
            ->getMock();

        $subscriber->expects(static::once())
            ->method('getSubscribedEvents')
            ->willReturn([SoftDeleteableListener::PRE_SOFT_DELETE, SoftDeleteableListener::POST_SOFT_DELETE]);

        $subscriber->expects(static::once())
            ->method('preSoftDelete')
            ->with(static::anything());

        $subscriber->expects(static::once())
            ->method('postSoftDelete')
            ->with(static::anything());

        $this->dm->getEventManager()->addEventSubscriber($subscriber);

        $repo = $this->dm->getRepository(self::USER_CLASS);

        $newUser = new User();
        $username = 'test_user';
        $newUser->setUsername($username);

        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(['username' => 'test_user']);

        static::assertNull($user->getDeletedAt());

        $this->dm->remove($user);
        $this->dm->flush();
    }
}
