<?php
/**
 * This file contains only the CategoryEditsController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Xtools\CategoryEdits;
use Xtools\CategoryEditsRepository;

/**
 * This controller serves the Category Edits tool.
 */
class CategoryEditsController extends XtoolsController
{
    /** @var CategoryEdits The CategoryEdits instance. */
    protected $categoryEdits;

    /** @var string[] The categories, with or without namespace. */
    protected $categories;

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
     * CategoryEditsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $this->tooHighEditCountAction = $this->getIndexRoute();

        parent::__construct($requestStack, $container);
    }

    /**
     * Display the search form.
     * @Route("/categoryedits", name="CategoryEdits")
     * @Route("/categoryedits/", name="CategoryEditsSlash")
     * @Route("/categoryedits/{project}", name="CategoryEditsProject")
     * @return Response
     * @codeCoverageIgnore
     */
    public function indexAction()
    {
        // Redirect if at minimum project, username and categories are provided.
        if (isset($this->params['project']) && isset($this->params['username']) && isset($this->params['categories'])) {
            return $this->redirectToRoute('CategoryEditsResult', $this->params);
        }

        return $this->render('categoryEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-categoryedits',
            'xtSubtitle' => 'tool-categoryedits-desc',
            'xtPage' => 'categoryedits',

            // Defaults that will get overridden if in $params.
            'namespace' => 0,
            'start' => '',
            'end' => '',
            'username' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Set defaults, and instantiate the CategoryEdits model. This is called at the top of every view action.
     * @codeCoverageIgnore
     */
    private function setupCategoryEdits()
    {
        $this->extractCategories();

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
     * Go through the categories and normalize values, and set them on class properties.
     * @return null|RedirectResponse Redirect if categories were normalized.
     * @codeCoverageIgnore
     */
    private function extractCategories()
    {
        // Split categories by pipe.
        $categories = explode('|', $this->request->get('categories'));

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
            return $this->redirectToRoute($this->request->get('_route'), array_merge(
                $this->request->attributes->get('_route_params'),
                ['categories' => implode('|', $this->categories)]
            ));
        }

        return null;
    }

    /**
     * Display the results.
     * @Route(
     *     "/categoryedits/{project}/{username}/{categories}/{start}/{end}",
     *     name="CategoryEditsResult",
     *     requirements={
     *         "categories"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}"
     *     },
     *     defaults={"start" = false, "end" = false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction()
    {
        $this->setupCategoryEdits();

        // Render the view with all variables set.
        return $this->render('categoryEdits/result.html.twig', $this->output);
    }

    /**
     * Get edits my by a user to pages in given categories.
     * @Route(
     *   "/categoryedits-contributions/{project}/{username}/{categories}/{start}/{end}/{offset}",
     *   name="CategoryContributionsResult",
     *   requirements={
     *       "categories" = "(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "|\d+"
     *   },
     *   defaults={"start" = false, "end" = false, "offset" = 0}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function categoryContributionsAction()
    {
        $this->setupCategoryEdits();

        return $this->render('categoryEdits/contributions.html.twig', $this->output);
    }

    /************************ API endpoints ************************/

    /**
     * Count the number of category edits the given user has made.
     * @Route(
     *   "/api/user/category_editcount/{project}/{username}/{categories}/{start}/{end}",
     *   name="UserApiCategoryEditCount",
     *   requirements={
     *       "categories" = "(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}"
     *   },
     *   defaults={"start" = false, "end" = false}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function categoryEditCountApiAction()
    {
        $this->recordApiUsage('user/category_editcount');

        $this->setupCategoryEdits();

        $ret = [
            'total_editcount' => $this->categoryEdits->getEditCount(),
            'category_editcount' => $this->categoryEdits->getCategoryEditCount(),
        ];

        return $this->getFormattedApiResponse($ret);
    }
}
