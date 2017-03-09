<?php

namespace AppBundle\Helper;

use GuzzleHttp;
use Symfony\Component\Config\Definition\Exception\Exception;

class PageviewsHelper {
    public function getPageviews($project, $title, $start, $end) {
        $title = str_replace( ' ', '_', $title );
        $client = new GuzzleHttp\Client();

        $url = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' .
            "$project/all-access/user/" . rawurlencode( $title ) . '/daily/' . $start . '/' . $end;

        $res = $client->request('GET', $url);
        return json_decode( $res->getBody()->getContents() );
    }

    public function getLastDays( $project, $title, $days = 60 ) {
        $days -= 1;
        $start = date( 'Ymd', strtotime( "-$days days" ) );
        $end = date('Ymd');

        return $this->getPageviews( $project, $title, $start, $end );
    }

    public function sumLastDays( $project, $title, $days ) {
        $data = $this->getLastDays( $project, $title, $days );

        // FIXME: needs to handle gotchas
        return array_sum( array_map( function( $item ) {
            return $item->views;
        }, $data->items ) );
    }
}
