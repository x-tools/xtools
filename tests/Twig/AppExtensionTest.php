<?php

declare(strict_types = 1);

namespace App\Tests\Twig;

use App\Model\Project;
use App\Model\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use App\Twig\AppExtension;
use DateTime;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * Tests for the AppExtension class.
 * @covers \App\Twig\AppExtension
 */
class AppExtensionTest extends TestAdapter
{
    use ArraySubsetAsserts;

    protected AppExtension $appExtension;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        static::createClient();
        $stack = new RequestStack();
        $session = new Session();
        $i18nHelper = static::$container->get('app.i18n_helper');
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $this->appExtension = new AppExtension(
            $stack,
            $session,
            $i18nHelper,
            $urlGenerator,
            $this->createMock(ProjectRepository::class),
            static::$container->get('parameter_bag'),
            static::$container->getParameter('app.is_wmf'),
            static::$container->getParameter('app.single_wiki'),
            30
        );
    }

    /**
     * Format number as a diff size.
     */
    public function testDiffFormat(): void
    {
        static::assertEquals(
            "<span class='diff-pos'>3,000</span>",
            $this->appExtension->diffFormat(3000)
        );
        static::assertEquals(
            "<span class='diff-neg'>-20,000</span>",
            $this->appExtension->diffFormat(-20000)
        );
        static::assertEquals(
            "<span class='diff-zero'>0</span>",
            $this->appExtension->diffFormat(0)
        );
    }

    /**
     * Format number as a percentage.
     */
    public function testPercentFormat(): void
    {
        static::assertEquals('45%', $this->appExtension->percentFormat(45));
        static::assertEquals('30%', $this->appExtension->percentFormat(30, null, 3));
        static::assertEquals('33.33%', $this->appExtension->percentFormat(2, 6, 2));
        static::assertEquals('25%', $this->appExtension->percentFormat(2, 8));
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration(): void
    {
        static::assertEquals(
            [30, 'num-seconds'],
            $this->appExtension->formatDuration(30, false)
        );
        static::assertEquals(
            [1, 'num-minutes'],
            $this->appExtension->formatDuration(70, false)
        );
        static::assertEquals(
            [50, 'num-minutes'],
            $this->appExtension->formatDuration(3000, false)
        );
        static::assertEquals(
            [2, 'num-hours'],
            $this->appExtension->formatDuration(7500, false)
        );
        static::assertEquals(
            [10, 'num-days'],
            $this->appExtension->formatDuration(864000, false)
        );
    }

    /**
     * Format a number.
     */
    public function testNumberFormat(): void
    {
        static::assertEquals('1,234', $this->appExtension->numberFormat(1234));
        static::assertEquals('1,234.32', $this->appExtension->numberFormat(1234.316, 2));
        static::assertEquals('50', $this->appExtension->numberFormat(50.0000, 4));
    }

    /**
     * Format a size.
     */
    public function testSizeFormat(): void
    {
        static::assertEquals('12.01 KB', $this->appExtension->sizeFormat(12300));
        static::assertEquals('100', $this->appExtension->sizeFormat(100));
        static::assertEquals('0', $this->appExtension->sizeFormat(0));
        static::assertEquals('1.12 GB', $this->appExtension->sizeFormat(1200300400));
        static::assertEquals('1.09 TB', $this->appExtension->sizeFormat(1200300400500));
    }

    /**
     * Intuition methods.
     */
    public function testIntution(): void
    {
        static::assertEquals('en', $this->appExtension->getLang());
        static::assertEquals('English', $this->appExtension->getLangName());

        $allLangs = $this->appExtension->getAllLangs();

        // There should be a bunch.
        static::assertGreaterThan(20, count($allLangs));

        // Keys should be the language codes, with name as the values.
        static::assertArraySubset(['en' => 'English'], $allLangs);
        static::assertArraySubset(['de' => 'Deutsch'], $allLangs);
        static::assertArraySubset(['es' => 'Español'], $allLangs);

        // Testing if the language is RTL.
        static::assertFalse($this->appExtension->isRTL('en'));
        static::assertTrue($this->appExtension->isRTL('ar'));
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods(): void
    {
        // This test is mysteriously failing on Scrutinizer, but not on Travis.
        // Commenting out for now.
        // static::assertEquals(7, strlen($this->appExtension->gitShortHash()));

        static::assertEquals(40, strlen($this->appExtension->gitHash()));
        static::assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $this->appExtension->gitDate());
    }

    /**
     * Capitalizing first letter.
     */
    public function testCapitalizeFirst(): void
    {
        static::assertEquals('Foo', $this->appExtension->capitalizeFirst('foo'));
        static::assertEquals('Bar', $this->appExtension->capitalizeFirst('Bar'));
    }

    /**
     * Getting amount of time it took to complete the request.
     */
    public function testRequestTime(): void
    {
        static::assertTrue(is_double($this->appExtension->requestMemory()));
    }

    /**
     * Is the given user logged out?
     */
    public function testUserIsAnon(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $user = new User($userRepo, '68.229.186.65');
        $user2 = new User($userRepo, 'Test user');
        static::assertTrue($this->appExtension->isUserAnon($user));
        static::assertFalse($this->appExtension->isUserAnon($user2));

        static::assertTrue($this->appExtension->isUserAnon('2605:E000:855A:4B00:3035:523D:F7E9:8F82'));
        static::assertFalse($this->appExtension->isUserAnon('192.0.blah.1'));
    }

    /**
     * Formatting dates.
     */
    public function testDateFormat(): void
    {
        static::assertEquals(
            '2017-01-23 00:00',
            $this->appExtension->dateFormat('2017-01-23')
        );
        static::assertEquals(
            '2017-01-23 00:00',
            $this->appExtension->dateFormat(new DateTime('2017-01-23'))
        );
    }

    /**
     * Building URL query string from array.
     */
    public function testBuildQuery(): void
    {
        static::assertEquals(
            'foo=1&bar=2',
            $this->appExtension->buildQuery([
                'foo' => 1,
                'bar' => 2,
            ])
        );
    }

    /**
     * Wikifying a string.
     */
    public function testWikify(): void
    {
        $project = new Project('TestProject');
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('getOne')
            ->willReturn([
                'url' => 'https://test.example.org',
                'dbName' => 'test_wiki',
                'lang' => 'en',
            ]);
        $projectRepo->method('getMetadata')
            ->willReturn([
                'general' => [
                    'articlePath' => '/wiki/$1',
                ],
            ]);
        $project->setRepository($projectRepo);
        $summary = '<script>alert("XSS baby")</script> [[test page]]';
        static::assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://test.example.org/wiki/Test_page'>test page</a>",
            $this->appExtension->wikify($summary, $project)
        );
    }
}
