<?php
/**
 * This file contains only the MetaController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller serves everything for the Meta tool.
 */
class MetaController extends XtoolsController
{
    /**
     * Display the form.
     * @Route("/meta", name="meta")
     * @Route("/meta", name="Meta")
     * @Route("/meta/", name="MetaSlash")
     * @Route("/meta/index.php", name="MetaIndexPhp")
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        if (isset($params['start']) && isset($params['end'])) {
            return $this->redirectToRoute('MetaResult', $params);
        }

        return $this->render('meta/index.html.twig', [
            'xtPage' => 'meta',
            'xtPageTitle' => 'tool-meta',
            'xtSubtitle' => 'tool-meta-desc',
        ]);
    }

    /**
     * Display the results.
     * @Route("/meta/{start}/{end}/{legacy}", name="MetaResult")
     * @param string $start    Start date
     * @param string $end      End date
     * @param string [$legacy] Non-blank value indicates to show stats for legacy XTools
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction($start, $end, $legacy = false)
    {
        $db = $legacy ? 'toolsdb' : 'default';
        $table = $legacy ? 's51187__metadata.xtools_timeline' : 'usage_timeline';

        $client = $this->container
            ->get('doctrine')
            ->getManager($db)
            ->getConnection();

        $query = $client->prepare(
            "SELECT * FROM $table WHERE date >= :start AND date <= :end"
        );
        $query->bindParam('start', $start);
        $query->bindParam('end', $end);
        $query->execute();

        $data = $query->fetchAll();

        // Create array of totals, along with formatted timeline data as needed by Chart.js
        $totals = [];
        $dateLabels = [];
        $timeline = [];
        $startObj = new DateTime($start);
        $endObj = new DateTime($end);
        $numDays = (int) $endObj->diff($startObj)->format("%a");
        $grandSum = 0;

        // Generate array of date labels
        for (
            $dateObj = new DateTime($start);
            $dateObj <= $endObj;
            $dateObj->modify('+1 day')
        ) {
            $dateLabels[] = $dateObj->format('Y-m-d');
        }

        foreach ($data as $entry) {
            if (!isset($totals[$entry['tool']])) {
                $totals[$entry['tool']] = (int) $entry['count'];

                // Create arrays for each tool, filled with zeros for each date in the timeline
                $timeline[$entry['tool']] = array_fill(0, $numDays, 0);
            } else {
                $totals[$entry['tool']] += (int) $entry['count'];
            }

            $date = new DateTime($entry['date']);
            $dateIndex = (int) $date->diff($startObj)->format("%a");
            $timeline[$entry['tool']][$dateIndex] = (int) $entry['count'];

            $grandSum += $entry['count'];
        }
        arsort($totals);

        return $this->render(
            'meta/result.html.twig',
            [
            'xtPage' => 'meta',
            'start' => $start,
            'end' => $end,
            'data' => $data,
            'totals' => $totals,
            'grandSum' => $grandSum,
            'dateLabels' => $dateLabels,
            'timeline' => $timeline,
            ]
        );
    }

    /**
     * Record usage of a particular XTools tool. This is called automatically
     *   in base.html.twig via JavaScript so that it is done asynchronously
     * @Route("/meta/usage/{tool}/{project}/{token}")
     * @param  $request Request
     * @param  string $tool    Internal name of tool
     * @param  string $project Project domain such as en.wikipedia.org
     * @param  string $token   Unique token for this request, so we don't have people
     *                         meddling with these statistics
     * @return Response
     * @codeCoverageIgnore
     */
    public function recordUsage(Request $request, $tool, $project, $token)
    {
        // Validate method and token.
        if ($request->getMethod() !== 'PUT' || !$this->isCsrfTokenValid('intention', $token)) {
            throw $this->createAccessDeniedException('This endpoint is for internal use only.');
        }

        // Ready the response object.
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        // Don't update counts for tools that aren't enabled
        if (!$this->container->getParameter("enable.$tool")) {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->setContent(json_encode([
                'error' => 'This tool is disabled'
            ]));
            return $response;
        }

        $conn = $this->container->get('doctrine')->getManager('default')->getConnection();
        $date =  date('Y-m-d');

        // Increment count in timeline
        $existsSql = "SELECT 1 FROM usage_timeline
                      WHERE date = '$date'
                      AND tool = '$tool'";

        if (count($conn->query($existsSql)->fetchAll()) === 0) {
            $createSql = "INSERT INTO usage_timeline
                          VALUES(NULL, '$date', '$tool', 1)";
            $conn->query($createSql);
        } else {
            $updateSql = "UPDATE usage_timeline
                          SET count = count + 1
                          WHERE tool = '$tool'
                          AND date = '$date'";
            $conn->query($updateSql);
        }

        // Update per-project usage, if applicable
        if (!$this->container->getParameter('app.single_wiki')) {
            $existsSql = "SELECT 1 FROM usage_projects
                          WHERE tool = '$tool'
                          AND project = '$project'";

            if (count($conn->query($existsSql)->fetchAll()) === 0) {
                $createSql = "INSERT INTO usage_projects
                              VALUES(NULL, '$tool', '$project', 1)";
                $conn->query($createSql);
            } else {
                $updateSql = "UPDATE usage_projects
                              SET count = count + 1
                              WHERE tool = '$tool'
                              AND project = '$project'";
                $conn->query($updateSql);
            }
        }

        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        $response->setContent(json_encode([]));
        return $response;
    }

    /**
     * Display the tagging interface.
     *
     * @Route("/meta/tag", name="MetaTag")
     *
     * @return Response
     */
    public function tagIndexAction()
    {
        if ($this->container->getParameter("kernel.environment") !== "dev") {
            // We don't want any more than this, this must be hidden entirely on
            // prod environments.
            throw new FileNotFoundException();
        }

        $path = realpath($this->getParameter('kernel.root_dir').'/..');

        $currentVersion = $this->container->getParameter("app.version");

        $request = Request::createFromGlobals();

        $newVersion = $request->get("version");
        $newReleaseNotes = $request->get("releaseNotes");
        $newGit = $request->get("git");

        if ($newVersion !== null) {
            $fRL = "$path/RELEASE_NOTES.md";
            $fAV = "$path/app/config/version.yml";
            $fRM = "$path/README.md";

            // Release Notes First

            $releaseNotes = file_get_contents($fRL);

            $releaseNotes = str_replace("# Release Notes #", "", $releaseNotes);

            $releaseNotes = $newReleaseNotes . "\r\n\r\n" . $releaseNotes;

            $releaseNotes = "## $newVersion ##" . "\r\n" . $releaseNotes;

            $releaseNotes = "# Release Notes #\r\n\r\n$releaseNotes";

            file_put_contents($fRL, $releaseNotes);

            unset($releaseNotes);

            // Version parameter next

            $version = file_get_contents($fAV);

            $version = str_replace($currentVersion, $newVersion, $version);

            file_put_contents($fAV, $version);

            unset($version);

            // Readme

            $readme = file_get_contents($fRM);

            $readme = str_replace($currentVersion, $newVersion, $readme);

            file_put_contents($fRM, $readme);

            $this->addFlash("success", "Files have been saved");

            // Lastly, git operations

            if ($newGit == "on") {
                chdir($path);

                system("git add $fRL");
                system("git add $fAV");
                system("git add $fRM");

                system(
                    "git commit -m \"Tagging version $newVersion (automated)\""
                );

                system("git tag -a $newVersion -m \"version $newVersion\"");

                $this->addFlash(
                    "success",
                    "Git operations succeeded.  Please \"git push\" to finish."
                );
            }

            return ($this->redirectToRoute("MetaTag"));
        }

        return $this->render(
            "meta/tagIndex.html.twig",
            [
                'xtPage' => 'Tagging Interface',
                'currentVersion' => $currentVersion
            ]
        );
    }

}