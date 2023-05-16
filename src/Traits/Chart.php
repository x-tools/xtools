<?php

declare(strict_types = 1);

namespace App\Traits;

trait Chart
{
    /**
     * Get color-blind friendly colors for use in charts
     * @param int $num Index of color
     * @return string RGBA color (so you can more easily adjust the opacity)
     */
    public static function getChartColor(int $num): string
    {
        $colors = [
            'rgba(171, 212, 235, 1)',
            'rgba(178, 223, 138, 1)',
            'rgba(251, 154, 153, 1)',
            'rgba(253, 191, 111, 1)',
            'rgba(202, 178, 214, 1)',
            'rgba(207, 182, 128, 1)',
            'rgba(141, 211, 199, 1)',
            'rgba(252, 205, 229, 1)',
            'rgba(255, 247, 161, 1)',
            'rgba(252, 146, 114, 1)',
            'rgba(217, 217, 217, 1)',
        ];

        return $colors[$num % count($colors)];
    }

    /**
     * Get the color for a given namespace.
     * @param int|null $nsId Namespace ID.
     * @return string Hex value of the color.
     * @codeCoverageIgnore
     */
    public static function getColorList(?int $nsId = null): string
    {
        $colors = [
            0 => '#FF5555',
            1 => '#55FF55',
            2 => '#FFEE22',
            3 => '#FF55FF',
            4 => '#5555FF',
            5 => '#55FFFF',
            6 => '#C00000',
            7 => '#0000C0',
            8 => '#008800',
            9 => '#00C0C0',
            10 => '#FFAFAF',
            11 => '#808080',
            12 => '#00C000',
            13 => '#404040',
            14 => '#C0C000',
            15 => '#C000C0',
            90 => '#991100',
            91 => '#99FF00',
            92 => '#000000',
            93 => '#777777',
            100 => '#75A3D1',
            101 => '#A679D2',
            102 => '#660000',
            103 => '#000066',
            104 => '#FAFFAF',
            105 => '#408345',
            106 => '#5c8d20',
            107 => '#e1711d',
            108 => '#94ef2b',
            109 => '#756a4a',
            110 => '#6f1dab',
            111 => '#301e30',
            112 => '#5c9d96',
            113 => '#a8cd8c',
            114 => '#f2b3f1',
            115 => '#9b5828',
            116 => '#002288',
            117 => '#0000CC',
            118 => '#99FFFF',
            119 => '#99BBFF',
            120 => '#FF99FF',
            121 => '#CCFFFF',
            122 => '#CCFF00',
            123 => '#CCFFCC',
            200 => '#33FF00',
            201 => '#669900',
            202 => '#666666',
            203 => '#999999',
            204 => '#FFFFCC',
            205 => '#FF00CC',
            206 => '#FFFF00',
            207 => '#FFCC00',
            208 => '#FF0000',
            209 => '#FF6600',
            250 => '#6633CC',
            251 => '#6611AA',
            252 => '#66FF99',
            253 => '#66FF66',
            446 => '#06DCFB',
            447 => '#892EE4',
            460 => '#99FF66',
            461 => '#99CC66',
            470 => '#CCCC33',
            471 => '#CCFF33',
            480 => '#6699FF',
            481 => '#66FFFF',
            484 => '#07C8D6',
            485 => '#2AF1FF',
            486 => '#79CB21',
            487 => '#80D822',
            490 => '#995500',
            491 => '#998800',
            710 => '#FFCECE',
            711 => '#FFC8F2',
            828 => '#F7DE00',
            829 => '#BABA21',
            866 => '#FFFFFF',
            867 => '#FFCCFF',
            1198 => '#FF34B3',
            1199 => '#8B1C62',
            2300 => '#A900B8',
            2301 => '#C93ED6',
            2302 => '#8A09C1',
            2303 => '#974AB8',
            2600 => '#000000',
        ];

        // Default to grey.
        return $colors[$nsId] ?? '#CCC';
    }
}
