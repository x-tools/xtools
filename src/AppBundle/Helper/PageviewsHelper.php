<?php
/**
 * This file contains only the PageviewsHelper class.
 */

namespace AppBundle\Helper;

use GuzzleHttp;

/**
 * A helper class to retrieve data about page-views.
 */
class PageviewsHelper
{

    /**
     * Get page views.
     * @param string $project
     * @param string $title
     * @param string $start
     * @param string $end
     * @return string[]
     */
    public function getPageviews($project, $title, $start, $end)
    {
        $title = str_replace(' ', '_', $title);
        $client = new GuzzleHttp\Client();

        $url = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' .
            "$project/all-access/user/" . rawurlencode($title) . '/daily/' . $start . '/' . $end;

        $res = $client->request('GET', $url);
        return json_decode($res->getBody()->getContents());
    }

    /**
     * Get last days.
     * @param string $project
     * @param string $title
     * @param int $days
     * @return string[]
     */
    public function getLastDays($project, $title, $days = 60)
    {
        $start = date('Ymd', strtotime("-$days days"));
        $end = date('Ymd');

        return $this->getPageviews($project, $title, $start, $end);
    }

    /**
     * Sum last days.
     * @param string $project
     * @param string $title
     * @param int $days
     * @return int
     */
    public function sumLastDays($project, $title, $days)
    {
        $data = $this->getLastDays($project, $title, $days);

        // FIXME: needs to handle gotchas
        return array_sum(array_map(function ($item) {
            return $item->views;
        }, $data->items));
    }
}
