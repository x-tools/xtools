<?php
/**
 * This file contains only the CategoryEditsController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\AutomatedEditsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\CategoryEdits;
use Xtools\CategoryEditsRepository;
use Xtools\Edit;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * This controller serves the Category Edits tool.
 */
class CategoryEditsController extends XtoolsController
{
    /** @var CategoryEdits The CategoryEdits instance. */
    protected $categoryEdits;

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var string[] The categories, with or without namespace. */
    protected $categories;

    /** @var string The start date. */
    protected $start = '';

    /** @var string The end date. */
    protected $end = '';

    /** @var string The OFFSET of contributions list. */
    protected $offset;

    /** @var int|string The namespace ID or 'all' for all namespaces. */
    protected $namespace;

    /** @var bool Whether or not this is a subrequest. */
    protected $isSubRequest;

    /** @var array Data that is passed to the view. */
    private $output;

    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'CategoryEdits';
    }

    /**
     * Display the search form.
     * @Route("/categoryedits", name="CategoryEdits")
     * @Route("/categoryedits/", name="CategoryEditsSlash")
     * @Route("/catedits", name="CategoryEditsShort")
     * @Route("/catedits/", name="CategoryEditsShortSlash")
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if at minimum project, username and categories are provided.
        if (isset($params['project']) && isset($params['username']) && isset($params['categories'])) {
            return $this->redirectToRoute('CategoryEditsResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        return $this->render('categoryEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-categoryedits',
            'xtSubtitle' => 'tool-categoryedits-desc',
            'xtPage' => 'categoryedits',

            // Defaults that will get overridden if in $params.
            'namespace' => 0,
            'start' => '',
            'end' => '',
        ], $params));
    }

    /**
     * Set defaults, and instantiate the CategoryEdits model. This is called at
     * the top of every view action.
     * @param Request $request The HTTP request.
     * @codeCoverageIgnore
     */
    private function setupCategoryEdits(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->validateProjectAndUser($request, 'CategoryEdits');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($this->project, $this->user) = $ret;
        }

        // Normalize all parameters and set class properties.
        // A redirect is returned if we want the normalized values in the URL.
        if ($this->normalizeAndSetParams($request) instanceof RedirectResponse) {
            return $ret;
        }

        $this->categoryEdits = new CategoryEdits(
            $this->project,
            $this->user,
            $this->categories,
            $this->start,
            $this->end,
            isset($this->offset) ? $this->offset : 0
        );
        $categoryEditsRepo = new CategoryEditsRepository();
        $categoryEditsRepo->setContainer($this->container);
        $this->categoryEdits->setRepository($categoryEditsRepo);

        $this->isSubRequest = $request->get('htmlonly')
            || $this->get('request_stack')->getParentRequest() !== null;

        $this->output = [
            'xtPage' => 'categoryedits',
            'xtTitle' => $this->user->getUsername(),
            'project' => $this->project,
            'user' => $this->user,
            'ce' => $this->categoryEdits,
            'is_sub_request' => $this->isSubRequest,
        ];
    }

    /**
     * Go through the categories, start, end, and offset parameters
     * and normalize values, and set them on class properties.
     * @param  Request $request
     * @return null|RedirectResponse Redirect if categoires were normalized.
     * @codeCoverageIgnore
     */
    private function normalizeAndSetParams(Request $request)
    {
        $categories = $request->get('categories');

        // Defaults
        $start = '';
        $end = '';

        // Some categories contain slashes, so we'll first make the differentiation
        // and extract out the start, end and offset params, even if they are empty.
        if (1 === preg_match(
            '/(.+?)\/(|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?(?:\/(\d+))?$/',
            $categories,
            $matches
        )) {
            $categories = $matches[1];
            $start = $matches[2];
            $end = isset($matches[3]) ? $matches[3] : null;
            $this->offset = isset($matches[4]) ? $matches[4] : 0;
        }

        // Split cateogries by pipe.
        $categories = explode('|', $categories);

        // Loop through the given categories, stripping out the namespace.
        // If a namespace was removed, it is flagged it as normalize
        // We look for the wiki's category namespace name, and the MediaWiki default
        // 'Category:', which sometimes is used cross-wiki (because it still works).
        $normalized = false;
        $nsName = $this->project->getNamespaces()[14].':';
        $this->categories = array_map(function ($category) use ($nsName, &$normalized) {
            if (strpos($category, $nsName) === 0 || strpos($category, 'Category:') === 0) {
                $normalized = true;
            }
            return ltrim(ltrim($category, $nsName.':'), 'Category:');
        }, $categories);

        // Redirect if normalized, since we don't want the Category: prefix in the URL.
        if ($normalized) {
            return $this->redirectToRoute($request->get('_route'), array_merge(
                $request->attributes->get('_route_params'),
                ['categories' => implode('|', $this->categories)]
            ));
        }

        // 'false' means the dates are optional and returned as 'false' if empty.
        list($this->start, $this->end) = $this->getUTCFromDateParams($start, $end, false);

        // Format dates as needed by User model, if the date is present.
        if ($this->start !== false) {
            $this->start = date('Y-m-d', $this->start);
        }
        if ($this->end !== false) {
            $this->end = date('Y-m-d', $this->end);
        }
    }

    /**
     * Display the results.
     * @Route(
     *     "/categoryedits/{project}/{username}/{categories}/{start}/{end}",
     *     name="CategoryEditsResult",
     *     requirements={
     *         "categories" = ".+",
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}"
     *     },
     *     defaults={"start" = "", "end" = ""}
     * )
     * @param Request $request The HTTP request.
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->setupCategoryEdits($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        // Render the view with all variables set.
        return $this->render('categoryEdits/result.html.twig', $this->output);
    }

    /**
     * Get edits my by a user to pages in given categories.
     * @Route(
     *   "/categoryedits-contributions/{project}/{username}/{categories}/{start}/{end}/{offset}",
     *   name="CategoryContributionsResult",
     *   requirements={
     *       "categories" = ".+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "|\d+"
     *   },
     *   defaults={"start" = "", "end" = "", "offset" = ""}
     * )
     * @param Request $request The HTTP request.
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function categoryContributionsAction(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->setupCategoryEdits($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return $this->render('categoryEdits/contributions.html.twig', $this->output);
    }

    /************************ API endpoints ************************/

    /**
     * Count the number of category edits the given user has made.
     * @Route(
     *   "/api/user/category_editcount/{project}/{username}/{categories}/{start}/{end}",
     *   name="UserApiCategoryEditCount",
     *   requirements={
     *       "categories" = ".+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}"
     *   },
     *   defaults={"start" = "", "end" = ""}
     * )
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function categoryEditCountApiAction(Request $request)
    {
        $this->recordApiUsage('user/category_editcount');

        $ret = $this->setupCategoryEdits($request);
        if ($ret instanceof RedirectResponse) {
            // FIXME: Refactor JSON errors/responses, use Intuition as a service.
            return new JsonResponse(
                [
                    'error' => $this->getFlashMessage(),
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $res = $this->getJsonData();
        $res['total_editcount'] = $this->categoryEdits->getEditCount();

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        $res['category_editcount'] = $this->categoryEdits->getCategoryEditCount();

        $response->setData($res);
        return $response;
    }

    /**
     * Get data that will be used in API responses.
     * @return array
     * @codeCoverageIgnore
     */
    private function getJsonData()
    {
        $ret = [
            'project' => $this->project->getDomain(),
            'username' => $this->user->getUsername(),
        ];

        foreach (['categories', 'start', 'end', 'offset'] as $param) {
            if (isset($this->{$param}) && $this->{$param} != '') {
                $ret[$param] = $this->{$param};
            }
        }

        return $ret;
    }
}
