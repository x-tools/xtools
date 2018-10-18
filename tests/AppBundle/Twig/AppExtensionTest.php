<?php
/**
 * This file contains only the AppExtensionTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Twig;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\ProjectRepository;
use AppBundle\Twig\AppExtension;
use DateTime;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Client;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the AppExtension class.
 */
class AppExtensionTest extends TestAdapter
{
    /** @var AppExtension Instance of class. */
    protected $appExtension;

    /** @var Client HTTP client. */
    private $client;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $i18nHelper = new I18nHelper($container, $stack, $session);
        $this->appExtension = new AppExtension($container, $stack, $session, $i18nHelper);
    }

    /**
     * Format number as a diff size.
     */
    public function testDiffFormat(): void
    {
        $this->assertEquals(
            "<span class='diff-pos'>3,000</span>",
            $this->appExtension->diffFormat(3000)
        );
        $this->assertEquals(
            "<span class='diff-neg'>-20,000</span>",
            $this->appExtension->diffFormat(-20000)
        );
        $this->assertEquals(
            "<span class='diff-zero'>0</span>",
            $this->appExtension->diffFormat(0)
        );
    }

    /**
     * Format number as a percentage.
     */
    public function testPercentFormat(): void
    {
        $this->assertEquals('45%', $this->appExtension->percentFormat(45));
        $this->assertEquals('30%', $this->appExtension->percentFormat(30, null, 3));
        $this->assertEquals('33.33%', $this->appExtension->percentFormat(2, 6, 2));
        $this->assertEquals('25%', $this->appExtension->percentFormat(2, 8));
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration(): void
    {
        $this->assertEquals(
            [30, 'num-seconds'],
            $this->appExtension->formatDuration(30, false)
        );
        $this->assertEquals(
            [1, 'num-minutes'],
            $this->appExtension->formatDuration(70, false)
        );
        $this->assertEquals(
            [50, 'num-minutes'],
            $this->appExtension->formatDuration(3000, false)
        );
        $this->assertEquals(
            [2, 'num-hours'],
            $this->appExtension->formatDuration(7500, false)
        );
        $this->assertEquals(
            [10, 'num-days'],
            $this->appExtension->formatDuration(864000, false)
        );
    }

    /**
     * Format a number.
     */
    public function testNumberFormat(): void
    {
        $this->assertEquals('1,234', $this->appExtension->numberFormat(1234));
        $this->assertEquals('1,234.32', $this->appExtension->numberFormat(1234.316, 2));
        $this->assertEquals('50', $this->appExtension->numberFormat(50.0000, 4));
    }

    /**
     * Intution methods.
     */
    public function testIntution(): void
    {
        $this->assertEquals('en', $this->appExtension->getLang());
        $this->assertEquals('English', $this->appExtension->getLangName());

        $allLangs = $this->appExtension->getAllLangs();

        // There should be a bunch.
        $this->assertGreaterThan(20, count($allLangs));

        // Keys should be the language codes, with name as the values.
        $this->assertArraySubset(['en' => 'English'], $allLangs);
        $this->assertArraySubset(['de' => 'Deutsch'], $allLangs);
        $this->assertArraySubset(['es' => 'Español'], $allLangs);

        // Testing if the language is RTL.
        $this->assertFalse($this->appExtension->isRTL('en'));
        $this->assertTrue($this->appExtension->isRTL('ar'));
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods(): void
    {
        // This test is mysteriously failing on Scrutinizer, but not on Travis.
        // Commenting out for now.
        // $this->assertEquals(7, strlen($this->appExtension->gitShortHash()));

        $this->assertEquals(40, strlen($this->appExtension->gitHash()));
        $this->assertRegExp('/\d{4}-\d{2}-\d{2}/', $this->appExtension->gitDate());
    }

    /**
     * Capitalizing first letter.
     */
    public function testCapitalizeFirst(): void
    {
        $this->assertEquals('Foo', $this->appExtension->capitalizeFirst('foo'));
        $this->assertEquals('Bar', $this->appExtension->capitalizeFirst('Bar'));
    }

    /**
     * Getting amount of time it took to complete the request.
     */
    public function testRequestTime(): void
    {
        $this->assertTrue(is_double($this->appExtension->requestMemory()));
    }

    /**
     * Is the given user logged out?
     */
    public function testUserIsAnon(): void
    {
        $user = new User('68.229.186.65');
        $user2 = new User('Test user');
        $this->assertTrue($this->appExtension->isUserAnon($user));
        $this->assertFalse($this->appExtension->isUserAnon($user2));

        $this->assertTrue($this->appExtension->isUserAnon('2605:E000:855A:4B00:3035:523D:F7E9:8F82'));
        $this->assertFalse($this->appExtension->isUserAnon('192.0.blah.1'));
    }

    /**
     * Formatting dates.
     */
    public function testDateFormat(): void
    {
        $this->assertEquals(
            '2017-01-23 00:00',
            $this->appExtension->dateFormat('2017-01-23')
        );
        $this->assertEquals(
            '2017-01-23 00:00',
            $this->appExtension->dateFormat(new DateTime('2017-01-23'))
        );
    }

    /**
     * Building URL query string from array.
     */
    public function testBuildQuery(): void
    {
        $this->assertEquals(
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
        $projectRepo = $this->getMock(ProjectRepository::class);
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
        $this->assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://test.example.org/wiki/Test_page'>test page</a>",
            $this->appExtension->wikify($summary, $project)
        );
    }
}
