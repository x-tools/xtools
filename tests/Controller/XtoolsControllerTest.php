<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

use App\Controller\XtoolsController;
use App\Exception\XtoolsHttpException;
use App\Helper\I18nHelper;
use App\Tests\SessionHelper;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Integration/unit tests for the abstract XtoolsController.
 * @group integration
 * @covers \App\Controller\XtoolsController
 */
class XtoolsControllerTest extends ControllerTestAdapter
{
    use ArraySubsetAsserts;
    use SessionHelper;

    protected I18nHelper $i18n;
    protected ReflectionClass $reflectionClass;
    protected XtoolsController $controller;

    /**
     * Set up the tests.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->i18n = static::getContainer()->get('app.i18n_helper');
    }

    /**
     * Create a new controller, making a Request with the given params.
     * @param array $requestParams Parameters to use when instantiating the Request object.
     * @param array $methodOverrides Keys are method names, values are what they should return.
     * @return XtoolsController
     */
    private function getControllerWithRequest(array $requestParams = [], array $methodOverrides = []): XtoolsController
    {
        $session = $this->createSession($this->client);
        $requestStack = $this->getRequestStack($session, $requestParams);

        return new OverridableXtoolsController(
            static::getContainer(),
            $requestStack,
            static::getContainer()->get('doctrine'),
            static::getContainer()->get('cache.app'),
            $session->getFlashBag(),
            static::getContainer()->get('eight_points_guzzle.client.xtools'),
            $this->i18n,
            static::getContainer()->get('App\Repository\ProjectRepository'),
            static::getContainer()->get('App\Repository\UserRepository'),
            static::getContainer()->get('App\Repository\PageRepository'),
            static::getContainer()->get('twig'),
            static::getContainer()->getParameter('app.is_wmf'),
            static::getContainer()->getParameter('default_project'),
            $methodOverrides
        );
    }

    /**
     * Make sure all parameters are correctly parsed.
     * @dataProvider paramsProvider
     * @param array $params
     * @param array $expected
     */
    public function testParseQueryParams(array $params, array $expected): void
    {
        // Untestable in CI build :(
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $controller = $this->getControllerWithRequest($params);
        $result = $controller->parseQueryParams();
        static::assertEquals($expected, $result);
    }

    /**
     * Data for self::testRevisionsProcessed().
     * @return string[]
     */
    public function paramsProvider(): array
    {
        return [
            [
                // Modern parameters.
                [
                    'project' => 'en.wikipedia.org',
                    'username' => 'Jimbo Wales',
                    'namespace' => '0',
                    'page' => 'Test',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ], [
                    'project' => 'en.wikipedia.org',
                    'username' => 'Jimbo Wales',
                    'namespace' => '0',
                    'page' => 'Test',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ],
            ], [
                // Legacy parameters mixed with modern.
                [
                    'project' => 'enwiki',
                    'user' => 'GoldenRing',
                    'namespace' => '0',
                    'article' => 'Test',
                ], [
                    'project' => 'enwiki',
                    'username' => 'GoldenRing',
                    'namespace' => '0',
                    'page' => 'Test',
                ],
            ], [
                // Missing parameters.
                [
                    'project' => 'en.wikipedia',
                    'page' => 'Test',
                ], [
                    'project' => 'en.wikipedia',
                    'page' => 'Test',
                ],
            ], [
                // Legacy style.
                [
                    'wiki' => 'wikipedia',
                    'lang' => 'de',
                    'article' => 'Test',
                    'name' => 'Bob Dylan',
                    'begin' => '2016-01-01',
                    'end' => '2017-01-01',
                ], [
                    'project' => 'de.wikipedia.org',
                    'page' => 'Test',
                    'username' => 'Bob Dylan',
                    'start' => '2016-01-01',
                    'end' => '2017-01-01',
                ],
            ], [
                // Legacy style with metawiki.
                [
                    'wiki' => 'wikimedia',
                    'lang' => 'meta',
                    'page' => 'Test',
                ], [
                    'project' => 'meta.wikimedia.org',
                    'page' => 'Test',
                ],
            ], [
                // Legacy style of the legacy style.
                [
                    'wikilang' => 'da',
                    'wikifam' => '.wikipedia.org',
                    'page' => '311',
                ], [
                    'project' => 'da.wikipedia.org',
                    'page' => '311',
                ],
            ], [
                // Language-neutral project.
                [
                    'wiki' => 'wikidata',
                    'lang' => 'www',
                    'page' => 'Q12345',
                ], [
                    'project' => 'www.wikidata.org',
                    'page' => 'Q12345',
                ],
            ], [
                // Language-neutral, ultra legacy style.
                [
                    'wikifam' => 'wikidata',
                    'wikilang' => 'www',
                    'page' => 'Q12345',
                ], [
                    'project' => 'www.wikidata.org',
                    'page' => 'Q12345',
                ],
            ],
        ];
    }

    /**
     * Getting a Project from the project query string.
     */
    public function testProjectFromQuery(): void
    {
        // Untestable on Travis :(
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $controller = $this->getControllerWithRequest(['project' => 'de.wiktionary.org']);
        static::assertEquals(
            'de.wiktionary.org',
            $controller->getProjectFromQuery()->getDomain()
        );

        $controller = $this->getControllerWithRequest();
        static::assertEquals(
            'en.wikipedia.org',
            $controller->getProjectFromQuery()->getDomain()
        );
    }

    /**
     * Validating the project and user parameters.
     */
    public function testValidateProjectAndUser(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $controller = $this->getControllerWithRequest([
            'project' => 'fr.wikibooks.org',
            'username' => 'MusikAnimal',
            'namespace' => '0',
        ]);

        $project = $controller->validateProject('fr.wikibooks.org');
        static::assertEquals('fr.wikibooks.org', $project->getDomain());

        $user = $controller->validateUser('MusikAnimal');
        static::assertEquals('MusikAnimal', $user->getUsername());

        static::expectException(XtoolsHttpException::class);
        static::expectExceptionMessage('The requested user does not exist');
        $controller->validateUser('Not a real user 8723849237');
    }

    /**
     * Invalid projects.
     */
    public function testInvalidProject(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        static::expectException(XtoolsHttpException::class);
        static::expectExceptionMessage('invalid.project.og is not a valid project');
        $this->getControllerWithRequest(['project' => 'invalid.project.og'])
            ->validateProject('invalid.project.org');
    }

    /**
     * Users with too high of an edit count.
     */
    public function testTooHighEditCount(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        static::expectException(XtoolsHttpException::class);
        static::expectExceptionMessage('User has made too many edits! (Maximum 350000)');
        $this->getControllerWithRequest([
            'project' => 'en.wikipedia',
            '_controller' => 'App\Controller\DefaultController::indexAction',
        ], [
            'tooHighEditCountRoute' => 'homepage',
        ])->validateUser('Materialscientist');
    }

    /**
     * Users with a high enough edit count that the user must login.
     */
    public function testEditCountRequireLogin(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        static::expectException(AccessDeniedHttpException::class);
        static::expectExceptionMessage('error-login-required');
        $this->getControllerWithRequest([
            'project' => 'en.wikipedia',
            '_controller' => 'App\Controller\DefaultController::indexAction',
        ])->validateUser('Before My Ken');
    }

    /**
     * Make sure standardized params are properly parsed.
     */
    public function testGetParams(): void
    {
        // Untestable on Travis :(
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $controller = $this->getControllerWithRequest([
            'project' => 'enwiki',
            'username' => 'Jimbo Wales',
            'namespace' => '0',
            'article' => 'Foo',
            'redirects' => '',
        ]);

        static::assertEquals([
            'project' => 'enwiki',
            'username' => 'Jimbo Wales',
            'namespace' => '0',
            'article' => 'Foo',
        ], $controller->getParams());
    }

    /**
     * Validate a page exists on a project.
     */
    public function testValidatePage(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $controller = $this->getControllerWithRequest(['project' => 'enwiki']);
        static::expectException(XtoolsHttpException::class);
        $controller->validatePage('Test adjfaklsdjf');

        static::assertInstanceOf(
            'Xtools\Page',
            $controller->validatePage('Bob Dylan')
        );
    }

    /**
     * Converting start/end dates into UTC timestamps.
     */
    public function testUTCFromDateParams(): void
    {
        $controller = $this->getControllerWithRequest();

        // Both dates given, and are valid.
        static::assertEquals(
            [strtotime('2017-01-01'), strtotime('2017-08-01')],
            $controller->getUnixFromDateParams('2017-01-01', '2017-08-01')
        );

        // End date exceeds current date.
        [$start, $end] = $controller->getUnixFromDateParams('2017-01-01', '2050-08-01');
        static::assertEquals(strtotime('2017-01-01'), $start);
        static::assertEquals(date('Y-m-d', time()), date('Y-m-d', $end));

        // Start date is after end date.
        static::assertEquals(
            [strtotime('2017-08-01'), strtotime('2017-09-01')],
            $controller->getUnixFromDateParams('2017-09-01', '2017-08-01')
        );

        // Start date is empty, should become false.
        static::assertEquals(
            [false, strtotime('2017-08-01')],
            $controller->getUnixFromDateParams(null, '2017-08-01')
        );

        // Both dates empty. End date should become today.
        static::assertEquals(
            [false, strtotime('today midnight')],
            $controller->getUnixFromDateParams(null, null)
        );

        // XtoolsController::getUnixFromDateParams() will now enforce a maximum date span of 5 days.
        $controller = $this->getControllerWithRequest([], [
            'maxDays' => 5,
        ]);

        // Both dates given, exceeding max days, so start date should be end date - max days.
        static::assertEquals(
            [strtotime('2017-08-05'), strtotime('2017-08-10')],
            $controller->getUnixFromDateParams('2017-08-01', '2017-08-10')
        );

        // Only end date given, start should also be end date - max days.
        static::assertEquals(
            [strtotime('2017-08-05'), strtotime('2017-08-10')],
            $controller->getUnixFromDateParams(false, '2017-08-10')
        );

        // Start date after end date, exceeding max days.
        static::assertEquals(
            [strtotime('2017-08-05'), strtotime('2017-08-10')],
            $controller->getUnixFromDateParams('2017-08-10', '2017-07-01')
        );
    }

    /**
     * Test involving fetching and settings cookies.
     */
    public function testCookies(): void
    {
        $crawler = $this->client->request('GET', '/sc');
        static::assertEquals(
            static::getContainer()->getParameter('default_project'),
            $crawler->filter('#project_input')->attr('value')
        );

        // For now...
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $cookie = new Cookie('XtoolsProject', 'test.wikipedia');
        $this->client->getCookieJar()->set($cookie);

        $crawler = $this->client->request('GET', '/sc');
        static::assertEquals('test.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

        $this->client->request('GET', '/sc/enwiki/Example');
        static::assertEquals(
            'en.wikipedia.org',
            $this->client->getResponse()->headers->getCookies()[0]->getValue()
        );
    }

    /**
     * IP range handling.
     */
    public function testIpRangeRestriction(): void
    {
        // No exception.
        $this->getControllerWithRequest([
            'project' => 'fr.wikipedia',
            'user' => '174.197.128.0/18',
        ]);

        static::expectException(XtoolsHttpException::class);
        static::expectExceptionMessage('The requested IP range is larger than the CIDR limit of /16.');
        $this->getControllerWithRequest([
            'project' => 'fr.wikipedia',
            'user' => '174.197.128.0/1',
        ]);
    }

    public function testAddFullPageTitlesAndContinue(): void
    {
        $controller = $this->getControllerWithRequest([
            'project' => 'test.wikipedia',
            'limit' => 2,
        ]);
        $out = [ 'foo' => 'bar' ];
        $data = [
            [ 'page_title' => 'Test_page', 'namespace' => 0, 'timestamp' => '2020-01-02T12:59:59' ],
            [ 'page_title' => 'Test_page', 'namespace' => 1, 'timestamp' => '2020-01-03T12:59:59' ],
        ];
        $newOut = $controller->addFullPageTitlesAndContinue('edits', $out, $data);

        $this->assertSame([
            'foo' => 'bar',
            'edits' => [
                [
                    'full_page_title' => 'Test_page',
                    'page_title' => 'Test_page',
                    'namespace' => 0,
                    'timestamp' => '2020-01-02T12:59:59',
                ],
                [
                    'full_page_title' => 'Talk:Test_page',
                    'page_title' => 'Test_page',
                    'namespace' => 1,
                    'timestamp' => '2020-01-03T12:59:59',
                ],
            ],
            'continue' => '2020-01-03T12:59:59Z',
        ], $newOut);
    }

    public function testFormattedApiResponse(): void
    {
        $controller = $this->getControllerWithRequest([
            'project' => 'en.wikipedia',
            'categories' => 'Foo|Bar|Baz',
        ]);
        $controller->addFlashMessage('warning', 'You had better watch yourself!');
        $response = json_decode(
            $controller->getFormattedApiResponse(['data' => ['test' => 5]], Response::HTTP_BAD_GATEWAY)->getContent(),
            true
        );
        static::assertArraySubset([
            'warning' => ['You had better watch yourself!'],
            'project' => 'en.wikipedia.org',
            'categories' => ['Foo', 'Bar', 'Baz'],
            'data' => ['test' => 5],
        ], $response);
        static::assertGreaterThan(0, $response['elapsed_time']);
    }
}
