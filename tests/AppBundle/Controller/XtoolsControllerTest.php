<?php
/**
 * This file contains only the XtoolsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Controller\SimpleEditCounterController;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;
use Xtools\Page;
use Xtools\PageRepository;

/**
 * Integration/unit tests for the abstract XtoolsController.
 * @group integration
 */
class XtoolsControllerTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Client The Symfony client */
    protected $client;

    /**
     * Set up the tests.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();

        // SimpleEditCounterController used solely for testing, since we
        // can't instantiate the abstract class XtoolsController.
        $this->controller = new SimpleEditCounterController();
        $this->controller->setContainer($this->container);
    }

    /**
     * Make sure all parameters are correctly parsed.
     * @dataProvider paramsProvider
     */
    public function testParseQueryParams($params, $expected)
    {
        // Untestabe on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $request = new Request($params);
        $result = $this->controller->parseQueryParams($request);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data for self::testRevisionsProcessed().
     * @return string[]
     */
    public function paramsProvider()
    {
        return [
            [
                // Modern parameters.
                [
                    'project' => 'en.wikipedia.org',
                    'username' => 'Jimbo Wales',
                    'namespace' => '0',
                    'article' => 'Test page',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ], [
                    'project' => 'en.wikipedia.org',
                    'username' => 'Jimbo Wales',
                    'namespace' => '0',
                    'article' => 'Test page',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ]
            ], [
                // Legacy parameters mixed with modern.
                [
                    'project' => 'enwiki',
                    'user' => 'Test user',
                    'namespace' => '0',
                    'page' => 'Test page',
                ], [
                    'project' => 'enwiki',
                    'username' => 'Test user',
                    'namespace' => '0',
                    'article' => 'Test page',
                ]
            ], [
                // Missing parameters.
                [
                    'project' => 'en.wikipedia',
                    'article' => 'Test_page',
                ], [
                    'project' => 'en.wikipedia',
                    'article' => 'Test_page',
                ]
            ], [
                // Legacy style.
                [
                    'wiki' => 'wikipedia',
                    'lang' => 'de',
                    'page' => 'Test page',
                    'name' => 'Bob Dylan',
                    'begin' => '2016-01-01',
                    'end' => '2017-01-01',
                ], [
                    'project' => 'de.wikipedia.org',
                    'article' => 'Test page',
                    'username' => 'Bob Dylan',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ]
            ], [
                // Legacy style with metawiki.
                [
                    'wiki' => 'wikimedia',
                    'lang' => 'meta',
                    'page' => 'Test page',
                ], [
                    'project' => 'meta.wikimedia.org',
                    'article' => 'Test page',
                ]
            ], [
                // Legacy style of the legacy style.
                [
                    'wikilang' => 'da',
                    'wikifam' => '.wikipedia.org',
                    'page' => '311',
                ], [
                    'project' => 'da.wikipedia.org',
                    'article' => '311'
                ]
            ], [
                // Language-neutral project.
                [
                    'wiki' => 'wikidata',
                    'lang' => 'fr',
                    'page' => 'Q12345',
                ], [
                    'project' => 'wikidata.org',
                    'article' => 'Q12345',
                ]
            ], [
                // Language-neutral, ultra legacy style.
                [
                    'wikifam' => 'wikidata',
                    'wikilang' => 'fr',
                    'page' => 'Q12345',
                ], [
                    'project' => 'wikidata.org',
                    'article' => 'Q12345',
                ]
            ],
        ];
    }

    /**
     * Getting a Project from the project query string.
     */
    public function testProjectFromQuery()
    {
        // Untestabe on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $project = $this->controller->getProjectFromQuery([
            'project' => 'de.wiktionary.org'
        ]);
        $this->assertEquals(
            'de.wiktionary.org',
            $project->getDomain()
        );

        $project = $this->controller->getProjectFromQuery([]);
        $this->assertEquals(
            'en.wikipedia.org',
            $project->getDomain()
        );
    }

    /**
     * Validating the project and user parameters.
     */
    public function testValidateProjectAndUser()
    {
        // Untestabe on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $request = new Request([
            'project' => 'fr.wikibooks.org',
            'username' => 'MusikAnimal',
            'namespace' => '0',
        ]);
        list($project, $user) = $this->controller->validateProjectAndUser($request);
        $this->assertEquals('fr.wikibooks.org', $project->getDomain());
        $this->assertEquals('MusikAnimal', $user->getUsername());

        $request = new Request([
            'project' => 'fr.wikibooks.org',
            'username' => 'Not a real user 8723849237',
        ]);
        $ret = $this->controller->validateProjectAndUser($request);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $ret);

        $request = new Request([
            'project' => 'invalid.project.org',
            'username' => 'MusikAnimal',
        ]);
        $ret = $this->controller->validateProjectAndUser($request);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $ret);

        // Too high of an edit count.
        $request = new Request([
            'project' => 'en.wikipedia.org',
            'username' => 'Materialscientist',
        ]);
        $ret = $this->controller->validateProjectAndUser($request, 'homepage');
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $ret);
    }

    /**
     * Make sure standardized params are properly parsed.
     */
    public function testGetParams()
    {
        $request = new Request([
            'project' => 'enwiki',
            'username' => 'Test user',
            'namespace' => '0',
            'article' => 'Foo',
            'redirects' => ''
        ]);
        $this->assertEquals([
            'project' => 'enwiki',
            'username' => 'Test user',
            'namespace' => '0',
            'article' => 'Foo',
        ], $this->controller->getParams($request));
    }

    /**
     * Validate a page exists on a project.
     */
    public function testValidatePage()
    {
        // Untestabe on Travis :(
        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        $project = ProjectRepository::getProject('enwiki', $this->container);
        $this->assertInstanceOf(
            'Symfony\Component\HttpFoundation\RedirectResponse',
            $this->controller->getAndValidatePage($project, 'Test page')
        );

        $project = ProjectRepository::getProject('enwiki', $this->container);
        $this->assertInstanceOf(
            'Xtools\Page',
            $this->controller->getAndValidatePage($project, 'Bob Dylan')
        );
    }

    /**
     * Converting start/end dates into UTC timestamps.
     */
    public function testUTCFromDateParams()
    {
        $this->assertEquals(
            [1483228800, 1501545600],
            $this->controller->getUTCFromDateParams(
                '2017-01-01',
                '2017-08-01'
            )
        );

        $this->assertEquals(
            [1498867200, 1501545600],
            $this->controller->getUTCFromDateParams(
                null,
                '2017-08-01'
            )
        );

        $this->assertEquals(
            [1501545600, 1504224000],
            $this->controller->getUTCFromDateParams(
                '2017-09-01',
                '2017-08-01'
            )
        );

        // Without using defaults.
        $this->assertEquals(
            [false, 1501545600],
            $this->controller->getUTCFromDateParams(
                null,
                '2017-08-01',
                false
            )
        );

        $this->assertEquals(
            [1501545600, 1504224000],
            $this->controller->getUTCFromDateParams(
                '2017-09-01',
                '2017-08-01',
                false
            )
        );
    }
}
