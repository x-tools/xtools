<?php
/**
 * This file contains only the MetaController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection;

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
     * @param string $start   Start date
     * @param string $end     End date
     * @param string $legacy  Non-blank value indicates to show stats for legacy XTools
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

        $toolUsage = $this->getToolUsageStats($client, $table, $start, $end);
        $apiUsage = $this->getApiUsageStats($client, $start, $end);

        return $this->render('meta/result.html.twig', [
            'xtPage' => 'meta',
            'start' => $start,
            'end' => $end,
            'toolUsage' => $toolUsage,
            'apiUsage' => $apiUsage,
        ]);
    }

    /**
     * Get usage statistics of the core tools.
     * @param  Connection $client
     * @param  string     $table Table to query.
     * @param  string     $start Start date.
     * @param  string     $end End date.
     * @return array
     * @codeCoverageIgnore
     */
    private function getToolUsageStats(Connection $client, $table, $start, $end)
    {
        $query = $client->prepare("SELECT * FROM $table
                                   WHERE date >= :start AND date <= :end");
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
        for ($dateObj = new DateTime($start); $dateObj <= $endObj; $dateObj->modify('+1 day')) {
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

        return [
            'totals' => $totals,
            'grandSum' => $grandSum,
            'dateLabels' => $dateLabels,
            'timeline' => $timeline,
        ];
    }

    /**
     * Get usage statistics of the API.
     * @param  Connection $client
     * @param  string     $start Start date.
     * @param  string     $end End date.
     * @return array
     * @codeCoverageIgnore
     */
    private function getApiUsageStats(Connection $client, $start, $end)
    {
        $query = $client->prepare("SELECT * FROM usage_api_timeline
                                   WHERE date >= :start AND date <= :end");
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
        for ($dateObj = new DateTime($start); $dateObj <= $endObj; $dateObj->modify('+1 day')) {
            $dateLabels[] = $dateObj->format('Y-m-d');
        }

        foreach ($data as $entry) {
            if (!isset($totals[$entry['endpoint']])) {
                $totals[$entry['endpoint']] = (int) $entry['count'];

                // Create arrays for each endpoint, filled with zeros for each date in the timeline
                $timeline[$entry['endpoint']] = array_fill(0, $numDays, 0);
            } else {
                $totals[$entry['endpoint']] += (int) $entry['count'];
            }

            $date = new DateTime($entry['date']);
            $dateIndex = (int) $date->diff($startObj)->format("%a");
            $timeline[$entry['endpoint']][$dateIndex] = (int) $entry['count'];

            $grandSum += $entry['count'];
        }
        arsort($totals);

        return [
            'totals' => $totals,
            'grandSum' => $grandSum,
            'dateLabels' => $dateLabels,
            'timeline' => $timeline,
        ];
    }

    /**
     * Record usage of a particular XTools tool. This is called automatically
     *   in base.html.twig via JavaScript so that it is done asynchronously
     * @Route("/meta/usage/{tool}/{project}/{token}")
     * @param  Request $request
     * @param  string $tool    Internal name of tool
     * @param  string $project Project domain such as en.wikipedia.org
     * @param  string $token   Unique token for this request, so we don't have people
     *                         meddling with these statistics
     * @return Response
     * @codeCoverageIgnore
     */
    public function recordUsage(Request $request, $tool, $project, $token)
    {
        // Ready the response object.
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        // Validate method and token.
        if ($request->getMethod() !== 'PUT' || !$this->isCsrfTokenValid('intention', $token)) {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->setContent(json_encode([
                'error' => 'This endpoint is for internal use only.'
            ]));
            return $response;
        }

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
}
