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

/**
 * This controller serves everything for the Meta tool.
 */
class MetaController extends Controller
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
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if ($start != '' && $end != '') {
            return $this->redirectToRoute('MetaResult', [ 'start' => $start, 'end' => $end ]);
        }

        return $this->render('meta/index.html.twig', [
            'xtPage' => 'meta',
            'xtPageTitle' => 'Usage tracking',
            'xtSubtitle' => 'See which XTools are the most popular over time',
        ]);
    }

    /**
     * Display the results.
     * @Route("/meta/{start}/{end}", name="MetaResult")
     * @param Request $request
     * @return Response
     */
    public function resultAction(Request $request)
    {
        $start = $request->attributes->get('start');
        $end = $request->attributes->get('end');

        $this->client = $this->container
            ->get('doctrine')
            ->getManager('toolsdb')
            ->getConnection();

        $query = $this->client->createQueryBuilder();
        $query->select([ 'date', 'tool', 'count' ])
            ->where($query->expr()->gte('date', ':start'))
            ->where($query->expr()->lte('date', ':end'))
            ->where($query->expr()->neq('tool', '""'))
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->from('s51187__metadata.xtools_timeline');

        $data = $query->execute()->fetchAll();

        // Create array of totals, along with formatted timeline data as needed by Chart.js
        $totals = [];
        $dateLabels = [];
        $timeline = [];
        $startObj = new DateTime($start);
        $endObj = new DateTime($end);
        $numDays = (int) $endObj->diff($startObj)->format("%a");
        $grandSum = 0;

        foreach ($data as $entry) {
            $dateLabels[] = $entry['date'];
            if (!isset($totals[$entry['tool']])) {
                $totals[$entry['tool']] = $entry['count'];

                // Create arrays for each tool, filled with zeros for each date in the timeline
                $timeline[$entry['tool']] = array_fill(0, $numDays, 0);
            } else {
                $totals[$entry['tool']] += $entry['count'];
            }

            $date = new DateTime($entry['date']);
            $dateIndex = (int) $date->diff($startObj)->format("%a");
            $timeline[$entry['tool']][$dateIndex] = $entry['count'];

            $grandSum += $entry['count'];
        }
        arsort($totals);
        $dateLabels = array_unique($dateLabels);
        sort($dateLabels);

        return $this->render('meta/result.html.twig', [
            'xtPage' => 'meta',
            'start' => $start,
            'end' => $end,
            'data' => $data,
            'totals' => $totals,
            'grandSum' => $grandSum,
            'dateLabels' => $dateLabels,
            'timeline' => $timeline,
        ]);
    }
}
