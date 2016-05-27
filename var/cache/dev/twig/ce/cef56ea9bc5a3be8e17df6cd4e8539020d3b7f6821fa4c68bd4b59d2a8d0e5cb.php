<?php

/* @WebProfiler/Collector/request.html.twig */
class __TwigTemplate_a6927be904556c6c02eee2629b300de340242632c211615a6246e08c28366ace extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/request.html.twig", 1);
        $this->blocks = array(
            'toolbar' => array($this, 'block_toolbar'),
            'menu' => array($this, 'block_menu'),
            'panel' => array($this, 'block_panel'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "@WebProfiler/Profiler/layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_91312daf0849384475af9240105f4a482863c7968fffa94fd6595de2b1d219d5 = $this->env->getExtension("native_profiler");
        $__internal_91312daf0849384475af9240105f4a482863c7968fffa94fd6595de2b1d219d5->enter($__internal_91312daf0849384475af9240105f4a482863c7968fffa94fd6595de2b1d219d5_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/request.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_91312daf0849384475af9240105f4a482863c7968fffa94fd6595de2b1d219d5->leave($__internal_91312daf0849384475af9240105f4a482863c7968fffa94fd6595de2b1d219d5_prof);

    }

    // line 3
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_257d8f0bd27412c20e46be210255a5ed515acd421a3da96c863cb80992812ad4 = $this->env->getExtension("native_profiler");
        $__internal_257d8f0bd27412c20e46be210255a5ed515acd421a3da96c863cb80992812ad4->enter($__internal_257d8f0bd27412c20e46be210255a5ed515acd421a3da96c863cb80992812ad4_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        // line 4
        echo "    ";
        ob_start();
        // line 5
        echo "        ";
        if ($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "controller", array(), "any", false, true), "class", array(), "any", true, true)) {
            // line 6
            echo "            ";
            $context["link"] = $this->env->getExtension('code')->getFileLink($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "file", array()), $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "line", array()));
            // line 7
            echo "            ";
            if ((isset($context["link"]) ? $context["link"] : $this->getContext($context, "link"))) {
                echo "<a href=\"";
                echo twig_escape_filter($this->env, (isset($context["link"]) ? $context["link"] : $this->getContext($context, "link")), "html", null, true);
                echo "\" title=\"";
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "file", array()), "html", null, true);
                echo "\">";
            } else {
                echo "<span>";
            }
            // line 8
            echo "
                ";
            // line 9
            echo twig_escape_filter($this->env, strip_tags($this->env->getExtension('code')->abbrClass($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "class", array()))), "html", null, true);
            // line 11
            if ($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "method", array())) {
                // line 12
                echo "&nbsp;::&nbsp;";
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "method", array()), "html", null, true);
            }
            // line 15
            if ((isset($context["link"]) ? $context["link"] : $this->getContext($context, "link"))) {
                echo "</a>";
            } else {
                echo "</span>";
            }
            // line 16
            echo "        ";
        } else {
            // line 17
            echo "            <span>";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "html", null, true);
            echo "</span>
        ";
        }
        // line 19
        echo "    ";
        $context["request_handler"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 20
        echo "
    ";
        // line 21
        $context["request_status_code_color"] = ((($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "statuscode", array()) >= 400)) ? ("red") : (((($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "statuscode", array()) >= 300)) ? ("yellow") : ("green"))));
        // line 22
        echo "
    ";
        // line 23
        ob_start();
        // line 24
        echo "        <span class=\"sf-toolbar-status sf-toolbar-status-";
        echo twig_escape_filter($this->env, (isset($context["request_status_code_color"]) ? $context["request_status_code_color"] : $this->getContext($context, "request_status_code_color")), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "statuscode", array()), "html", null, true);
        echo "</span>
        ";
        // line 25
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "route", array())) {
            // line 26
            echo "            <span class=\"sf-toolbar-label\">@</span>
            <span class=\"sf-toolbar-value sf-toolbar-info-piece-additional\">";
            // line 27
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "route", array()), "html", null, true);
            echo "</span>
        ";
        }
        // line 29
        echo "    ";
        $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 30
        echo "
    ";
        // line 31
        ob_start();
        // line 32
        echo "        <div class=\"sf-toolbar-info-piece\">
            <b>HTTP status</b>
            <span>";
        // line 34
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "statuscode", array()), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "statustext", array()), "html", null, true);
        echo "</span>
        </div>

        <div class=\"sf-toolbar-info-piece\">
            <b>Controller</b>
            <span>";
        // line 39
        echo twig_escape_filter($this->env, (isset($context["request_handler"]) ? $context["request_handler"] : $this->getContext($context, "request_handler")), "html", null, true);
        echo "</span>
        </div>

        ";
        // line 42
        if ($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "controller", array(), "any", false, true), "class", array(), "any", true, true)) {
            // line 43
            echo "            <div class=\"sf-toolbar-info-piece\">
                <b>Controller class</b>
                <span>";
            // line 45
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "controller", array()), "class", array()), "html", null, true);
            echo "</span>
            </div>
        ";
        }
        // line 48
        echo "
        <div class=\"sf-toolbar-info-piece\">
            <b>Route name</b>
            <span>";
        // line 51
        echo twig_escape_filter($this->env, (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "route", array(), "any", true, true)) ? (_twig_default_filter($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "route", array()), "NONE")) : ("NONE")), "html", null, true);
        echo "</span>
        </div>

        <div class=\"sf-toolbar-info-piece\">
            <b>Has session</b>
            <span>";
        // line 56
        if (twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sessionmetadata", array()))) {
            echo "yes";
        } else {
            echo "no";
        }
        echo "</span>
        </div>
    ";
        $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 59
        echo "
    ";
        // line 60
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", array("link" => (isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url"))));
        echo "
";
        
        $__internal_257d8f0bd27412c20e46be210255a5ed515acd421a3da96c863cb80992812ad4->leave($__internal_257d8f0bd27412c20e46be210255a5ed515acd421a3da96c863cb80992812ad4_prof);

    }

    // line 63
    public function block_menu($context, array $blocks = array())
    {
        $__internal_249827ed9c5e19cb0b65a7dc51cdb22c044cdb9d7ce04e5dab53c2a755008f68 = $this->env->getExtension("native_profiler");
        $__internal_249827ed9c5e19cb0b65a7dc51cdb22c044cdb9d7ce04e5dab53c2a755008f68->enter($__internal_249827ed9c5e19cb0b65a7dc51cdb22c044cdb9d7ce04e5dab53c2a755008f68_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "menu"));

        // line 64
        echo "    <span class=\"label\">
        <span class=\"icon\">";
        // line 65
        echo twig_include($this->env, $context, "@WebProfiler/Icon/request.svg");
        echo "</span>
        <strong>Request / Response</strong>
    </span>
";
        
        $__internal_249827ed9c5e19cb0b65a7dc51cdb22c044cdb9d7ce04e5dab53c2a755008f68->leave($__internal_249827ed9c5e19cb0b65a7dc51cdb22c044cdb9d7ce04e5dab53c2a755008f68_prof);

    }

    // line 70
    public function block_panel($context, array $blocks = array())
    {
        $__internal_c3fda968c11f27c045a747d572fe9064350502cb2e8ff4c8a4af64b16fa5f8ba = $this->env->getExtension("native_profiler");
        $__internal_c3fda968c11f27c045a747d572fe9064350502cb2e8ff4c8a4af64b16fa5f8ba->enter($__internal_c3fda968c11f27c045a747d572fe9064350502cb2e8ff4c8a4af64b16fa5f8ba_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panel"));

        // line 71
        echo "    <div class=\"sf-tabs\">
        <div class=\"tab\">
            <h3 class=\"tab-title\">Request</h3>

            <div class=\"tab-content\">
                <h3>GET Parameters</h3>

                ";
        // line 78
        if (twig_test_empty($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestquery", array()), "all", array()))) {
            // line 79
            echo "                    <div class=\"empty\">
                        <p>No GET parameters</p>
                    </div>
                ";
        } else {
            // line 83
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestquery", array())), false);
            echo "
                ";
        }
        // line 85
        echo "
                <h3>POST Parameters</h3>

                ";
        // line 88
        if (twig_test_empty($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestrequest", array()), "all", array()))) {
            // line 89
            echo "                    <div class=\"empty\">
                        <p>No POST parameters</p>
                    </div>
                ";
        } else {
            // line 93
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestrequest", array())), false);
            echo "
                ";
        }
        // line 95
        echo "
                <h3>Request Attributes</h3>

                ";
        // line 98
        if (twig_test_empty($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestattributes", array()), "all", array()))) {
            // line 99
            echo "                    <div class=\"empty\">
                        <p>No attributes</p>
                    </div>
                ";
        } else {
            // line 103
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestattributes", array())), false);
            echo "
                ";
        }
        // line 105
        echo "
                <h3>Cookies</h3>

                ";
        // line 108
        if (twig_test_empty($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestcookies", array()), "all", array()))) {
            // line 109
            echo "                    <div class=\"empty\">
                        <p>No cookies</p>
                    </div>
                ";
        } else {
            // line 113
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestcookies", array())), false);
            echo "
                ";
        }
        // line 115
        echo "
                <h3>Request Headers</h3>
                ";
        // line 117
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestheaders", array()), "labels" => array(0 => "Header", 1 => "Value")), false);
        echo "

                <h3>Request Content</h3>

                ";
        // line 121
        if (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "content", array()) == false)) {
            // line 122
            echo "                    <div class=\"empty\">
                        <p>Request content not available (it was retrieved as a resource).</p>
                    </div>
                ";
        } elseif ($this->getAttribute(        // line 125
(isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "content", array())) {
            // line 126
            echo "                    <div class=\"card\">
                        <pre class=\"break-long-words\">";
            // line 127
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "content", array()), "html", null, true);
            echo "</pre>
                    </div>
                ";
        } else {
            // line 130
            echo "                    <div class=\"empty\">
                        <p>No content</p>
                    </div>
                ";
        }
        // line 134
        echo "
                <h3>Server Parameters</h3>
                ";
        // line 136
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "requestserver", array())), false);
        echo "
            </div>
        </div>

        <div class=\"tab\">
            <h3 class=\"tab-title\">Response</h3>

            <div class=\"tab-content\">
                <h3>Response Headers</h3>

                ";
        // line 146
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "responseheaders", array()), "labels" => array(0 => "Header", 1 => "Value")), false);
        echo "
            </div>
        </div>

        <div class=\"tab ";
        // line 150
        echo ((twig_test_empty($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sessionmetadata", array()))) ? ("disabled") : (""));
        echo "\">
            <h3 class=\"tab-title\">Session</h3>

            <div class=\"tab-content\">
                <h3>Session Metadata</h3>

                ";
        // line 156
        if (twig_test_empty($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sessionmetadata", array()))) {
            // line 157
            echo "                    <div class=\"empty\">
                        <p>No session metadata</p>
                    </div>
                ";
        } else {
            // line 161
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/table.html.twig", array("data" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sessionmetadata", array())), false);
            echo "
                ";
        }
        // line 163
        echo "
                <h3>Session Attributes</h3>

                ";
        // line 166
        if (twig_test_empty($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sessionattributes", array()))) {
            // line 167
            echo "                    <div class=\"empty\">
                        <p>No session attributes</p>
                    </div>
                ";
        } else {
            // line 171
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/table.html.twig", array("data" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sessionattributes", array()), "labels" => array(0 => "Attribute", 1 => "Value")), false);
            echo "
                ";
        }
        // line 173
        echo "            </div>
        </div>

        <div class=\"tab ";
        // line 176
        echo ((twig_test_empty($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "flashes", array()))) ? ("disabled") : (""));
        echo "\">
            <h3 class=\"tab-title\">Flashes</h3>

            <div class=\"tab-content\">
                <h3>Flashes</h3>

                ";
        // line 182
        if (twig_test_empty($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "flashes", array()))) {
            // line 183
            echo "                    <div class=\"empty\">
                        <p>No flash messages were created.</p>
                    </div>
                ";
        } else {
            // line 187
            echo "                    ";
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/table.html.twig", array("data" => $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "flashes", array())), false);
            echo "
                ";
        }
        // line 189
        echo "            </div>
        </div>

        ";
        // line 192
        if ($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "parent", array())) {
            // line 193
            echo "        <div class=\"tab\">
            <h3 class=\"tab-title\">Parent Request</h3>

            <div class=\"tab-content\">
                <h3>
                    <a href=\"";
            // line 198
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("_profiler", array("token" => $this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "parent", array()), "token", array()))), "html", null, true);
            echo "\">Return to parent request</a>
                    <small>(token = ";
            // line 199
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "parent", array()), "token", array()), "html", null, true);
            echo ")</small>
                </h3>

                ";
            // line 202
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "parent", array()), "getcollector", array(0 => "request"), "method"), "requestattributes", array())), false);
            echo "
            </div>
        </div>
        ";
        }
        // line 206
        echo "
        ";
        // line 207
        if (twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()))) {
            // line 208
            echo "        <div class=\"tab\">
            <h3 class=\"tab-title\">Sub Requests <span class=\"badge\">";
            // line 209
            echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array())), "html", null, true);
            echo "</span></h3>

            <div class=\"tab-content\">
                ";
            // line 212
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()));
            foreach ($context['_seq'] as $context["_key"] => $context["child"]) {
                // line 213
                echo "                    <h3>
                        <a href=\"";
                // line 214
                echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("_profiler", array("token" => $this->getAttribute($context["child"], "token", array()))), "html", null, true);
                echo "\">";
                // line 215
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute($context["child"], "getcollector", array(0 => "request"), "method"), "requestattributes", array()), "get", array(0 => "_controller"), "method"), "html", null, true);
                // line 216
                echo "</a>
                        <small>(token = ";
                // line 217
                echo twig_escape_filter($this->env, $this->getAttribute($context["child"], "token", array()), "html", null, true);
                echo ")</small>
                    </h3>

                    ";
                // line 220
                echo twig_include($this->env, $context, "@WebProfiler/Profiler/bag.html.twig", array("bag" => $this->getAttribute($this->getAttribute($context["child"], "getcollector", array(0 => "request"), "method"), "requestattributes", array())), false);
                echo "
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['child'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 222
            echo "            </div>
        </div>
        ";
        }
        // line 225
        echo "    </div>
";
        
        $__internal_c3fda968c11f27c045a747d572fe9064350502cb2e8ff4c8a4af64b16fa5f8ba->leave($__internal_c3fda968c11f27c045a747d572fe9064350502cb2e8ff4c8a4af64b16fa5f8ba_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/request.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  507 => 225,  502 => 222,  494 => 220,  488 => 217,  485 => 216,  483 => 215,  480 => 214,  477 => 213,  473 => 212,  467 => 209,  464 => 208,  462 => 207,  459 => 206,  452 => 202,  446 => 199,  442 => 198,  435 => 193,  433 => 192,  428 => 189,  422 => 187,  416 => 183,  414 => 182,  405 => 176,  400 => 173,  394 => 171,  388 => 167,  386 => 166,  381 => 163,  375 => 161,  369 => 157,  367 => 156,  358 => 150,  351 => 146,  338 => 136,  334 => 134,  328 => 130,  322 => 127,  319 => 126,  317 => 125,  312 => 122,  310 => 121,  303 => 117,  299 => 115,  293 => 113,  287 => 109,  285 => 108,  280 => 105,  274 => 103,  268 => 99,  266 => 98,  261 => 95,  255 => 93,  249 => 89,  247 => 88,  242 => 85,  236 => 83,  230 => 79,  228 => 78,  219 => 71,  213 => 70,  202 => 65,  199 => 64,  193 => 63,  184 => 60,  181 => 59,  171 => 56,  163 => 51,  158 => 48,  152 => 45,  148 => 43,  146 => 42,  140 => 39,  130 => 34,  126 => 32,  124 => 31,  121 => 30,  118 => 29,  113 => 27,  110 => 26,  108 => 25,  101 => 24,  99 => 23,  96 => 22,  94 => 21,  91 => 20,  88 => 19,  82 => 17,  79 => 16,  73 => 15,  69 => 12,  67 => 11,  65 => 9,  62 => 8,  51 => 7,  48 => 6,  45 => 5,  42 => 4,  36 => 3,  11 => 1,);
    }
}
/* {% extends '@WebProfiler/Profiler/layout.html.twig' %}*/
/* */
/* {% block toolbar %}*/
/*     {% set request_handler %}*/
/*         {% if collector.controller.class is defined %}*/
/*             {% set link = collector.controller.file|file_link(collector.controller.line) %}*/
/*             {% if link %}<a href="{{ link }}" title="{{ collector.controller.file }}">{% else %}<span>{% endif %}*/
/* */
/*                 {{ collector.controller.class|abbr_class|striptags }}*/
/* */
/*                 {%- if collector.controller.method -%}*/
/*                     &nbsp;::&nbsp;{{ collector.controller.method }}*/
/*                 {%- endif -%}*/
/* */
/*             {% if link %}</a>{% else %}</span>{% endif %}*/
/*         {% else %}*/
/*             <span>{{ collector.controller }}</span>*/
/*         {% endif %}*/
/*     {% endset %}*/
/* */
/*     {% set request_status_code_color = (collector.statuscode >= 400) ? 'red' : (collector.statuscode >= 300) ? 'yellow' : 'green' %}*/
/* */
/*     {% set icon %}*/
/*         <span class="sf-toolbar-status sf-toolbar-status-{{ request_status_code_color }}">{{ collector.statuscode }}</span>*/
/*         {% if collector.route %}*/
/*             <span class="sf-toolbar-label">@</span>*/
/*             <span class="sf-toolbar-value sf-toolbar-info-piece-additional">{{ collector.route }}</span>*/
/*         {% endif %}*/
/*     {% endset %}*/
/* */
/*     {% set text %}*/
/*         <div class="sf-toolbar-info-piece">*/
/*             <b>HTTP status</b>*/
/*             <span>{{ collector.statuscode }} {{ collector.statustext }}</span>*/
/*         </div>*/
/* */
/*         <div class="sf-toolbar-info-piece">*/
/*             <b>Controller</b>*/
/*             <span>{{ request_handler }}</span>*/
/*         </div>*/
/* */
/*         {% if collector.controller.class is defined %}*/
/*             <div class="sf-toolbar-info-piece">*/
/*                 <b>Controller class</b>*/
/*                 <span>{{ collector.controller.class }}</span>*/
/*             </div>*/
/*         {% endif %}*/
/* */
/*         <div class="sf-toolbar-info-piece">*/
/*             <b>Route name</b>*/
/*             <span>{{ collector.route|default('NONE') }}</span>*/
/*         </div>*/
/* */
/*         <div class="sf-toolbar-info-piece">*/
/*             <b>Has session</b>*/
/*             <span>{% if collector.sessionmetadata|length %}yes{% else %}no{% endif %}</span>*/
/*         </div>*/
/*     {% endset %}*/
/* */
/*     {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url }) }}*/
/* {% endblock %}*/
/* */
/* {% block menu %}*/
/*     <span class="label">*/
/*         <span class="icon">{{ include('@WebProfiler/Icon/request.svg') }}</span>*/
/*         <strong>Request / Response</strong>*/
/*     </span>*/
/* {% endblock %}*/
/* */
/* {% block panel %}*/
/*     <div class="sf-tabs">*/
/*         <div class="tab">*/
/*             <h3 class="tab-title">Request</h3>*/
/* */
/*             <div class="tab-content">*/
/*                 <h3>GET Parameters</h3>*/
/* */
/*                 {% if collector.requestquery.all is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No GET parameters</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.requestquery }, with_context = false) }}*/
/*                 {% endif %}*/
/* */
/*                 <h3>POST Parameters</h3>*/
/* */
/*                 {% if collector.requestrequest.all is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No POST parameters</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.requestrequest }, with_context = false) }}*/
/*                 {% endif %}*/
/* */
/*                 <h3>Request Attributes</h3>*/
/* */
/*                 {% if collector.requestattributes.all is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No attributes</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.requestattributes }, with_context = false) }}*/
/*                 {% endif %}*/
/* */
/*                 <h3>Cookies</h3>*/
/* */
/*                 {% if collector.requestcookies.all is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No cookies</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.requestcookies }, with_context = false) }}*/
/*                 {% endif %}*/
/* */
/*                 <h3>Request Headers</h3>*/
/*                 {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.requestheaders, labels: ['Header', 'Value'] }, with_context = false) }}*/
/* */
/*                 <h3>Request Content</h3>*/
/* */
/*                 {% if collector.content == false %}*/
/*                     <div class="empty">*/
/*                         <p>Request content not available (it was retrieved as a resource).</p>*/
/*                     </div>*/
/*                 {% elseif collector.content %}*/
/*                     <div class="card">*/
/*                         <pre class="break-long-words">{{ collector.content }}</pre>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     <div class="empty">*/
/*                         <p>No content</p>*/
/*                     </div>*/
/*                 {% endif %}*/
/* */
/*                 <h3>Server Parameters</h3>*/
/*                 {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.requestserver }, with_context = false) }}*/
/*             </div>*/
/*         </div>*/
/* */
/*         <div class="tab">*/
/*             <h3 class="tab-title">Response</h3>*/
/* */
/*             <div class="tab-content">*/
/*                 <h3>Response Headers</h3>*/
/* */
/*                 {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: collector.responseheaders, labels: ['Header', 'Value'] }, with_context = false) }}*/
/*             </div>*/
/*         </div>*/
/* */
/*         <div class="tab {{ collector.sessionmetadata is empty ? 'disabled' }}">*/
/*             <h3 class="tab-title">Session</h3>*/
/* */
/*             <div class="tab-content">*/
/*                 <h3>Session Metadata</h3>*/
/* */
/*                 {% if collector.sessionmetadata is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No session metadata</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/table.html.twig', { data: collector.sessionmetadata }, with_context = false) }}*/
/*                 {% endif %}*/
/* */
/*                 <h3>Session Attributes</h3>*/
/* */
/*                 {% if collector.sessionattributes is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No session attributes</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/table.html.twig', { data: collector.sessionattributes, labels: ['Attribute', 'Value'] }, with_context = false) }}*/
/*                 {% endif %}*/
/*             </div>*/
/*         </div>*/
/* */
/*         <div class="tab {{ collector.flashes is empty ? 'disabled' }}">*/
/*             <h3 class="tab-title">Flashes</h3>*/
/* */
/*             <div class="tab-content">*/
/*                 <h3>Flashes</h3>*/
/* */
/*                 {% if collector.flashes is empty %}*/
/*                     <div class="empty">*/
/*                         <p>No flash messages were created.</p>*/
/*                     </div>*/
/*                 {% else %}*/
/*                     {{ include('@WebProfiler/Profiler/table.html.twig', { data: collector.flashes }, with_context = false) }}*/
/*                 {% endif %}*/
/*             </div>*/
/*         </div>*/
/* */
/*         {% if profile.parent %}*/
/*         <div class="tab">*/
/*             <h3 class="tab-title">Parent Request</h3>*/
/* */
/*             <div class="tab-content">*/
/*                 <h3>*/
/*                     <a href="{{ path('_profiler', { token: profile.parent.token }) }}">Return to parent request</a>*/
/*                     <small>(token = {{ profile.parent.token }})</small>*/
/*                 </h3>*/
/* */
/*                 {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: profile.parent.getcollector('request').requestattributes }, with_context = false) }}*/
/*             </div>*/
/*         </div>*/
/*         {% endif %}*/
/* */
/*         {% if profile.children|length %}*/
/*         <div class="tab">*/
/*             <h3 class="tab-title">Sub Requests <span class="badge">{{ profile.children|length }}</span></h3>*/
/* */
/*             <div class="tab-content">*/
/*                 {% for child in profile.children %}*/
/*                     <h3>*/
/*                         <a href="{{ path('_profiler', { token: child.token }) }}">*/
/*                             {{- child.getcollector('request').requestattributes.get('_controller') -}}*/
/*                         </a>*/
/*                         <small>(token = {{ child.token }})</small>*/
/*                     </h3>*/
/* */
/*                     {{ include('@WebProfiler/Profiler/bag.html.twig', { bag: child.getcollector('request').requestattributes }, with_context = false) }}*/
/*                 {% endfor %}*/
/*             </div>*/
/*         </div>*/
/*         {% endif %}*/
/*     </div>*/
/* {% endblock %}*/
/* */
