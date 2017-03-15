<?php

namespace AppBundle\Twig;

class AppExtension extends Extension
{

    public function getName()
    {
        return 'app_extension';
    }

    public function getFunctions()
    {
        $options = ['is_safe' => ['html']];
        return [
            new \Twig_SimpleFunction('request_time', [ $this, 'requestTime' ], $options),
            new \Twig_SimpleFunction('memory_usage', [ $this, 'requestMemory' ], $options),
            new \Twig_SimpleFunction('year', [ $this, 'generateYear' ], $options),
            new \Twig_SimpleFunction('linkWiki', [ $this, 'linkToWiki' ], $options),
            new \Twig_SimpleFunction('linkWikiScript', [ $this, 'linkToWikiScript' ], $options),
            new \Twig_SimpleFunction('msgPrintExists', [ $this, 'intuitionMessagePrintExists' ], $options),
            new \Twig_SimpleFunction('msgExists', [ $this, 'intuitionMessageExists' ], $options),
            new \Twig_SimpleFunction('msg', [ $this, 'intuitionMessage' ], $options),
            new \Twig_SimpleFunction('msg_footer', [ $this, 'intuitionMessageFooter' ], $options),
            new \Twig_SimpleFunction('lang', [ $this, 'getLang' ], $options),
            new \Twig_SimpleFunction('langName', [ $this, 'getLangName' ], $options),
            new \Twig_SimpleFunction('allLangs', [ $this, 'getAllLangs' ]),
            new \Twig_SimpleFunction('isRTL', [ $this, 'intuitionIsRTL' ]),
            new \Twig_SimpleFunction('shortHash', [ $this, 'gitShortHash' ]),
            new \Twig_SimpleFunction('hash', [ $this, 'gitHash' ]),
            new \Twig_SimpleFunction('enabled', [ $this, 'tabEnabled' ]),
            new \Twig_SimpleFunction('tools', [ $this, 'allTools' ]),
            new \Twig_SimpleFunction('color', [ $this, 'getColorList' ]),
            new \Twig_SimpleFunction('isWMFLabs', [ $this, 'isWMFLabs' ]),
            new \Twig_SimpleFunction('isSingleWiki', [ $this, 'isSingleWiki' ]),
            new \Twig_SimpleFunction('getReplagThreshold', [ $this, 'getReplagThreshold' ]),
            new \Twig_SimpleFunction('loadStylesheetsFromCDN', [ $this, 'loadStylesheetsFromCDN' ]),
            new \Twig_SimpleFunction('isWMFLabs', [ $this, 'isWMFLabs' ]),
            new \Twig_SimpleFunction('replag', [ $this, 'replag' ]),
            new \Twig_SimpleFunction('link', [ $this, 'link' ]),
        ];
    }

    public function requestTime($decimals = 3)
    {

        return number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], $decimals);
    }

    public function requestMemory()
    {
        $mem = memory_get_usage(false);
        $div = pow(1024, 2);
        $mem = $mem / $div;

        return round($mem, 2);
    }

    public function generateYear()
    {
        return date('Y');
    }

    /**
     * This function mainly acts as a workaround, as only WMF wikis use /w/ or /wiki/ in their path.
     * @param string $url
     * @param string $page
     * @param string $secondary
     * @return string
     */
    public function linkToWiki($url, $page, $secondary = "")
    {
        $link = $url . "/";

        if ($this->isWMFLabs()) {
            $link .= "wiki/";
        }

        $link .= $page;

        if ($secondary != "") {
            $link .= "?$secondary";
        }

        return $link;
    }

    public function linkToWikiScript($url, $secondary)
    {
        $link = $url . "/";

        if ($this->isWMFLabs()) {
            $link .= "w/";
        }

        $link .= "index.php?$secondary";

        // $link = str_replace("//", "/", $link);

        return $link;
    }

    // TODO: refactor all intuition stuff so it can be used anywhere
    public function intuitionMessageExists($message = "")
    {
        return $this->getIntuition()->msgExists($message, [ "domain" => "xtools" ]);
    }

    public function intuitionMessagePrintExists($message = "", $vars = [])
    {
        if (is_array($message)) {
            $vars = $message;
            $message = $message[0];
            $vars = array_slice($vars, 1);
        }
        if ($this->intuitionMessageExists($message)) {
            return $this->intuitionMessage($message, $vars);
        } else {
            return $message;
        }
    }

    public function intuitionMessage($message = "", $vars = [])
    {
        return $this->getIntuition()->msg($message, [ "domain" => "xtools", "variables" => $vars ]);
    }

    public function intuitionMessageFooter()
    {
        $message = $this->getIntuition()->getFooterLine(TSINT_HELP_NONE);
        $message = preg_replace('/<a class.*?>Change language!<\/a>/i', "", $message);
        return $message;
    }

    public function getLang()
    {
        return $this->getIntuition()->getLang();
    }

    public function getLangName()
    {
        return in_array($this->getIntuition()->getLangName(), $this->getAllLangs())
            ? $this->getIntuition()->getLangName()
            : 'English';
    }

    /**
     * Get all available languages in the i18n directory
     * @return array Associative array of langKey => langName
     */
    public function getAllLangs()
    {
        $messageFiles = glob($this->container->getParameter("kernel.root_dir") . '/../i18n/*.json');

        $languages = array_values(array_unique(array_map(
            function ($filename) {
                return basename($filename, '.json');
            },
            $messageFiles
        )));

        $availableLanguages = [];

        foreach ($languages as $lang) {
            $availableLanguages[$lang] = ucfirst($this->getIntuition()->getLangName($lang));
        }
        asort($availableLanguages);

        return $availableLanguages;
    }

    public function intuitionIsRTL()
    {
        return $this->getIntuition()->isRTL($this->getIntuition()->getLang());
    }

    public function gitShortHash()
    {
        return exec("git rev-parse --short HEAD");
    }

    public function gitHash()
    {
        return exec("git rev-parse HEAD");
    }

    public function tabEnabled($tool = "index")
    {
        $param = false;
        if ($this->container->hasParameter("enable.$tool")) {
            $param = boolval($this->container->getParameter("enable.$tool"));
        }
        return $param;
    }

    public function allTools()
    {
        $retVal = [];
        if ($this->container->hasParameter("tools")) {
            $retVal = $this->container->getParameter("tools");
        }
        return $retVal;
    }

    public function getColorList($num = false)
    {
        $colors = [
            0 => '#Cc0000',# '#FF005A', #red '#FF5555',
            1 => '#F7b7b7',
            2 => '#5c8d20',# '#008800', #green'#55FF55',
            3 => '#85eD82',
            4 => '#2E97E0', # blue
            5 => '#B9E3F9',
            6 => '#e1711d',  # orange
            7 => '#ffc04c',
            8 => '#FDFF98', # yellow
            9 => '#5555FF',
            10 => '#55FFFF',
            11 => '#0000C0',  #
            12 => '#008800',  # green
            13 => '#00C0C0',
            14 => '#FFAFAF',  # rosé
            15 => '#808080',  # gray
            16 => '#00C000',
            17 => '#404040',
            18 => '#C0C000',  # green
            19 => '#C000C0',
            100 => '#75A3D1',  # blue
            101 => '#A679D2',  # purple
            102 => '#660000',
            103 => '#000066',
            104 => '#FAFFAF',  # caramel
            105 => '#408345',
            106 => '#5c8d20',
            107 => '#e1711d',  # red
            108 => '#94ef2b',  # light green
            109 => '#756a4a',  # brown
            110 => '#6f1dab',
            111 => '#301e30',
            112 => '#5c9d96',
            113 => '#a8cd8c',  # earth green
            114 => '#f2b3f1',  # light purple
            115 => '#9b5828',
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
            446 => '#06DCFB',
            447 => '#892EE4',
            460 => '#99FF66',
            461 => '#99CC66',  # green
            470 => '#CCCC33',  # ocker
            471 => '#CCFF33',
            480 => '#6699FF',
            481 => '#66FFFF',
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

            '#61a9f3',# blue
            '#f381b9',# pink
            '#61E3A9',
            '#D56DE2',
            '#85eD82',
            '#F7b7b7',
            '#CFDF49',
            '#88d8f2',
            '#07AF7B',# green
            '#B9E3F9',
            '#FFF3AD',
            '#EF606A',# red
            '#EC8833',
            '#FFF100',
            '#87C9A5',
            '#FFFB11',
            '#005EBC',
            '#9AEB67',
            '#FF4A26',
            '#FDFF98',
            '#6B7EFF',
            '#BCE02E',
            '#E0642E',
            '#E0D62E',
            '#02927F',
            '#FF005A',
            '#61a9f3', # blue' #FFFF55',
        ];

        if ($num === false) {
            return $colors;
        } else {
            return $colors[$num];
        }
    }

    public function isSingleWiki()
    {
        $param = true;
        if ($this->container->hasParameter("app.single_wiki")) {
            $param = boolval($this->container->getParameter("app.single_wiki"));
        }
        return $param;
    }

    public function getReplagThreshold()
    {
        $param = 30;
        if ($this->container->hasParameter("app.replag_threshold")) {
            $param = $this->container->getParameter("app.replag_threshold");
        };
        return $param;
    }

    public function loadStylesheetsFromCDN()
    {
        $param = false;
        if ($this->container->hasParameter("app.load_stylesheets_from_cdn")) {
            $param = boolval($this->container->getParameter("app.load_stylesheets_from_cdn"));
        }
        return $param;
    }

    public function isWMFLabs()
    {
        $param = false;
        if ($this->container->hasParameter("app.is_labs")) {
            $param = boolval($this->container->getParameter("app.is_labs"));
        }
        return $param;
    }

    public function replag()
    {
        $retVal = 0;

        if ($this->isWMFLabs()) {
            $project = $this->container->get("request_stack")->getCurrentRequest()->get('project');

            if (!isset($project)) {
                $project = "enwiki";
            }

            $stmt = "SELECT lag FROM `heartbeat_p`.`heartbeat` h
            RIGHT JOIN `meta_p`.`wiki` w on concat(h.shard, \".labsdb\")=w.slice
            where dbname like :project or name like :project or url like :project limit 1";

            $conn = $this->container->get('doctrine')->getManager("replicas")->getConnection();

            // Prepare the query and execute
            $resultQuery = $conn->prepare($stmt);
            $resultQuery->bindParam("project", $project);
            $resultQuery->execute();

            if ($resultQuery->errorCode() == 0) {
                $results = $resultQuery->fetchAll();

                if (isset($results[0]["lag"])) {
                    $retVal = $results[0]["lag"];
                }
            }
        }

        return $retVal;
    }

    public function link($path = "/")
    {
        $base_path = $this->container->getParameter("app.base_path");
        $retVal = $path;

        if (isset($base_path)) {
            $retVal = "$base_path/$path";
        }

        $retVal = str_replace("//", "/", $retVal);

        return $retVal;
    }
}
