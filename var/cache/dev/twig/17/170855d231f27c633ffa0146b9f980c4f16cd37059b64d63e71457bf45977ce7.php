<?php

/* @WebProfiler/Collector/config.html.twig */
class __TwigTemplate_33ea2c684f697c98437fc717f3aa2928db06647d0bf60dacf5462df2f47a0a0f extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/config.html.twig", 1);
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
        $__internal_7b42c47b6a2353263e454ab97b484c204b69205bc8ee01bef316c3f3c5af262f = $this->env->getExtension("native_profiler");
        $__internal_7b42c47b6a2353263e454ab97b484c204b69205bc8ee01bef316c3f3c5af262f->enter($__internal_7b42c47b6a2353263e454ab97b484c204b69205bc8ee01bef316c3f3c5af262f_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/config.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_7b42c47b6a2353263e454ab97b484c204b69205bc8ee01bef316c3f3c5af262f->leave($__internal_7b42c47b6a2353263e454ab97b484c204b69205bc8ee01bef316c3f3c5af262f_prof);

    }

    // line 3
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_fd33dccd812cb12f77a2458d72c42db9289ad4f6fc5fa0a69b46e21a6fc23779 = $this->env->getExtension("native_profiler");
        $__internal_fd33dccd812cb12f77a2458d72c42db9289ad4f6fc5fa0a69b46e21a6fc23779->enter($__internal_fd33dccd812cb12f77a2458d72c42db9289ad4f6fc5fa0a69b46e21a6fc23779_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        // line 4
        echo "    ";
        if (("unknown" == $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyState", array()))) {
            // line 5
            echo "        ";
            $context["block_status"] = "";
            // line 6
            echo "        ";
            $context["symfony_version_status"] = "Unable to retrieve information about the Symfony version.";
            // line 7
            echo "    ";
        } elseif (("eol" == $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyState", array()))) {
            // line 8
            echo "        ";
            $context["block_status"] = "red";
            // line 9
            echo "        ";
            $context["symfony_version_status"] = "This Symfony version will no longer receive security fixes.";
            // line 10
            echo "    ";
        } elseif (("eom" == $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyState", array()))) {
            // line 11
            echo "        ";
            $context["block_status"] = "yellow";
            // line 12
            echo "        ";
            $context["symfony_version_status"] = "This Symfony version will only receive security fixes.";
            // line 13
            echo "    ";
        } elseif (("dev" == $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyState", array()))) {
            // line 14
            echo "        ";
            $context["block_status"] = "yellow";
            // line 15
            echo "        ";
            $context["symfony_version_status"] = "This Symfony version is still in the development phase.";
            // line 16
            echo "    ";
        } else {
            // line 17
            echo "        ";
            $context["block_status"] = "";
            // line 18
            echo "        ";
            $context["symfony_version_status"] = "";
            // line 19
            echo "    ";
        }
        // line 20
        echo "
    ";
        // line 21
        ob_start();
        // line 22
        echo "        ";
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array())) {
            // line 23
            echo "            <span class=\"sf-toolbar-label\">";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array()), "html", null, true);
            echo "</span>
            <span class=\"sf-toolbar-value\">";
            // line 24
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationversion", array()), "html", null, true);
            echo "</span>
        ";
        } elseif ($this->getAttribute(        // line 25
(isset($context["collector"]) ? $context["collector"] : null), "symfonyState", array(), "any", true, true)) {
            // line 26
            echo "            <span class=\"sf-toolbar-label\">
                ";
            // line 27
            echo twig_include($this->env, $context, "@WebProfiler/Icon/symfony.svg");
            echo "
            </span>
            <span class=\"sf-toolbar-value\">";
            // line 29
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyversion", array()), "html", null, true);
            echo "</span>
        ";
        }
        // line 31
        echo "    ";
        $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 32
        echo "
    ";
        // line 33
        ob_start();
        // line 34
        echo "        <div class=\"sf-toolbar-info-group\">
            ";
        // line 35
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array())) {
            // line 36
            echo "                <div class=\"sf-toolbar-info-piece\">
                    <b>";
            // line 37
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array()), "html", null, true);
            echo "</b>
                    <span>";
            // line 38
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationversion", array()), "html", null, true);
            echo "</span>
                </div>
            ";
        }
        // line 41
        echo "
            <div class=\"sf-toolbar-info-piece\">
                <b>Profiler token</b>
                <span>
                    ";
        // line 45
        if ((isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url"))) {
            // line 46
            echo "                        <a href=\"";
            echo twig_escape_filter($this->env, (isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url")), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "token", array()), "html", null, true);
            echo "</a>
                    ";
        } else {
            // line 48
            echo "                        ";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "token", array()), "html", null, true);
            echo "
                    ";
        }
        // line 50
        echo "                </span>
            </div>

            ";
        // line 53
        if ( !("n/a" === $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "appname", array()))) {
            // line 54
            echo "                <div class=\"sf-toolbar-info-piece\">
                    <b>Kernel name</b>
                    <span>";
            // line 56
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "appname", array()), "html", null, true);
            echo "</span>
                </div>
            ";
        }
        // line 59
        echo "
            ";
        // line 60
        if ( !("n/a" === $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "env", array()))) {
            // line 61
            echo "                <div class=\"sf-toolbar-info-piece\">
                    <b>Environment</b>
                    <span>";
            // line 63
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "env", array()), "html", null, true);
            echo "</span>
                </div>
            ";
        }
        // line 66
        echo "
            ";
        // line 67
        if ( !("n/a" === $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "debug", array()))) {
            // line 68
            echo "                <div class=\"sf-toolbar-info-piece\">
                    <b>Debug</b>
                    <span class=\"sf-toolbar-status sf-toolbar-status-";
            // line 70
            echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "debug", array())) ? ("green") : ("red"));
            echo "\">";
            echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "debug", array())) ? ("enabled") : ("disabled"));
            echo "</span>
                </div>
            ";
        }
        // line 73
        echo "        </div>

        <div class=\"sf-toolbar-info-group\">
            <div class=\"sf-toolbar-info-piece sf-toolbar-info-php\">
                <b>PHP version</b>
                <span>
                    ";
        // line 79
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "phpversion", array()), "html", null, true);
        echo "
                    &nbsp; <a href=\"";
        // line 80
        echo $this->env->getExtension('routing')->getPath("_profiler_phpinfo");
        echo "\">View phpinfo()</a>
                </span>
            </div>

            <div class=\"sf-toolbar-info-piece sf-toolbar-info-php-ext\">
                <b>PHP Extensions</b>
                <span class=\"sf-toolbar-status sf-toolbar-status-";
        // line 86
        echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "hasxdebug", array())) ? ("green") : ("red"));
        echo "\">xdebug</span>
                <span class=\"sf-toolbar-status sf-toolbar-status-";
        // line 87
        echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "hasaccelerator", array())) ? ("green") : ("red"));
        echo "\">accel</span>
            </div>

            <div class=\"sf-toolbar-info-piece\">
                <b>PHP SAPI</b>
                <span>";
        // line 92
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "sapiName", array()), "html", null, true);
        echo "</span>
            </div>
        </div>

        <div class=\"sf-toolbar-info-group\">
            ";
        // line 97
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "symfonyversion", array(), "any", true, true)) {
            // line 98
            echo "                <div class=\"sf-toolbar-info-piece\">
                    <b>Resources</b>
                    <span>
                        ";
            // line 101
            if (("Silex" == $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array()))) {
                // line 102
                echo "                            <a href=\"http://silex.sensiolabs.org/documentation\" rel=\"help\">
                                Read Silex Docs
                            </a>
                        ";
            } else {
                // line 106
                echo "                            <a href=\"https://symfony.com/doc/";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyversion", array()), "html", null, true);
                echo "/index.html\" rel=\"help\">
                                Read Symfony ";
                // line 107
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyversion", array()), "html", null, true);
                echo " Docs
                            </a>
                        ";
            }
            // line 110
            echo "                    </span>
                </div>
            ";
        }
        // line 113
        echo "        </div>
    ";
        $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 115
        echo "
    ";
        // line 116
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", array("link" => true, "name" => "config", "status" => (isset($context["block_status"]) ? $context["block_status"] : $this->getContext($context, "block_status")), "additional_classes" => "sf-toolbar-block-right"));
        echo "
";
        
        $__internal_fd33dccd812cb12f77a2458d72c42db9289ad4f6fc5fa0a69b46e21a6fc23779->leave($__internal_fd33dccd812cb12f77a2458d72c42db9289ad4f6fc5fa0a69b46e21a6fc23779_prof);

    }

    // line 119
    public function block_menu($context, array $blocks = array())
    {
        $__internal_be2b82e32054184c7420f07621eab7aa31e3307f7e4c0dee235f5a2c11c8a3b5 = $this->env->getExtension("native_profiler");
        $__internal_be2b82e32054184c7420f07621eab7aa31e3307f7e4c0dee235f5a2c11c8a3b5->enter($__internal_be2b82e32054184c7420f07621eab7aa31e3307f7e4c0dee235f5a2c11c8a3b5_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "menu"));

        // line 120
        echo "    <span class=\"label label-status-";
        echo ((($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyState", array()) == "eol")) ? ("red") : (((twig_in_filter($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyState", array()), array(0 => "eom", 1 => "dev"))) ? ("yellow") : (""))));
        echo "\">
        <span class=\"icon\">";
        // line 121
        echo twig_include($this->env, $context, "@WebProfiler/Icon/config.svg");
        echo "</span>
        <strong>Configuration</strong>
    </span>
";
        
        $__internal_be2b82e32054184c7420f07621eab7aa31e3307f7e4c0dee235f5a2c11c8a3b5->leave($__internal_be2b82e32054184c7420f07621eab7aa31e3307f7e4c0dee235f5a2c11c8a3b5_prof);

    }

    // line 126
    public function block_panel($context, array $blocks = array())
    {
        $__internal_84fad03ef0d182556f9c91986c9280e761428e212c2f3c5a5ca18ed6e9584609 = $this->env->getExtension("native_profiler");
        $__internal_84fad03ef0d182556f9c91986c9280e761428e212c2f3c5a5ca18ed6e9584609->enter($__internal_84fad03ef0d182556f9c91986c9280e761428e212c2f3c5a5ca18ed6e9584609_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panel"));

        // line 127
        echo "    ";
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array())) {
            // line 128
            echo "        ";
            // line 129
            echo "        <h2>Project Configuration</h2>

        <div class=\"metrics\">
            <div class=\"metric\">
                <span class=\"value\">";
            // line 133
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationname", array()), "html", null, true);
            echo "</span>
                <span class=\"label\">Application name</span>
            </div>

            <div class=\"metric\">
                <span class=\"value\">";
            // line 138
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "applicationversion", array()), "html", null, true);
            echo "</span>
                <span class=\"label\">Application version</span>
            </div>
        </div>

        <p>
            Based on <a class=\"text-bold\" href=\"https://symfony.com\">Symfony ";
            // line 144
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyversion", array()), "html", null, true);
            echo "</a>
        </p>
    ";
        } else {
            // line 147
            echo "        <h2>Symfony Configuration</h2>

        <div class=\"metrics\">
            <div class=\"metric\">
                <span class=\"value\">";
            // line 151
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "symfonyversion", array()), "html", null, true);
            echo "</span>
                <span class=\"label\">Symfony version</span>
            </div>

            ";
            // line 155
            if (("n/a" != $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "appname", array()))) {
                // line 156
                echo "                <div class=\"metric\">
                    <span class=\"value\">";
                // line 157
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "appname", array()), "html", null, true);
                echo "</span>
                    <span class=\"label\">Application name</span>
                </div>
            ";
            }
            // line 161
            echo "
            ";
            // line 162
            if (("n/a" != $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "env", array()))) {
                // line 163
                echo "                <div class=\"metric\">
                    <span class=\"value\">";
                // line 164
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "env", array()), "html", null, true);
                echo "</span>
                    <span class=\"label\">Environment</span>
                </div>
            ";
            }
            // line 168
            echo "
            ";
            // line 169
            if (("n/a" != $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "debug", array()))) {
                // line 170
                echo "                <div class=\"metric\">
                    <span class=\"value\">";
                // line 171
                echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "debug", array())) ? ("enabled") : ("disabled"));
                echo "</span>
                    <span class=\"label\">Debug</span>
                </div>
            ";
            }
            // line 175
            echo "        </div>
    ";
        }
        // line 177
        echo "
    <h2>PHP Configuration</h2>

    <div class=\"metrics\">
        <div class=\"metric\">
            <span class=\"value\">";
        // line 182
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "phpversion", array()), "html", null, true);
        echo "</span>
            <span class=\"label\">PHP version</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 187
        echo twig_include($this->env, $context, (("@WebProfiler/Icon/" . (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "hasaccelerator", array())) ? ("yes") : ("no"))) . ".svg"));
        echo "</span>
            <span class=\"label\">PHP acceleration</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 192
        echo twig_include($this->env, $context, (("@WebProfiler/Icon/" . (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "hasxdebug", array())) ? ("yes") : ("no"))) . ".svg"));
        echo "</span>
            <span class=\"label\">Xdebug</span>
        </div>
    </div>

    <div class=\"metrics metrics-horizontal\">
        <div class=\"metric\">
            <span class=\"value\">";
        // line 199
        echo twig_include($this->env, $context, (("@WebProfiler/Icon/" . (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "haszendopcache", array())) ? ("yes") : ("no"))) . ".svg"));
        echo "</span>
            <span class=\"label\">OPcache</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 204
        echo twig_include($this->env, $context, (("@WebProfiler/Icon/" . (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "hasapc", array())) ? ("yes") : ("no"))) . ".svg"));
        echo "</span>
            <span class=\"label\">APC</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 209
        echo twig_include($this->env, $context, (("@WebProfiler/Icon/" . (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "hasxcache", array())) ? ("yes") : ("no"))) . ".svg"));
        echo "</span>
            <span class=\"label\">XCache</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 214
        echo twig_include($this->env, $context, (("@WebProfiler/Icon/" . (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "haseaccelerator", array())) ? ("yes") : ("no"))) . ".svg"));
        echo "</span>
            <span class=\"label\">EAccelerator</span>
        </div>
    </div>

    <p>
        <a href=\"";
        // line 220
        echo $this->env->getExtension('routing')->getPath("_profiler_phpinfo");
        echo "\">View full PHP configuration</a>
    </p>

    ";
        // line 223
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "bundles", array())) {
            // line 224
            echo "        <h2>Enabled Bundles <small>(";
            echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "bundles", array())), "html", null, true);
            echo ")</small></h2>
        <table>
            <thead>
                <tr>
                    <th class=\"key\">Name</th>
                    <th>Path</th>
                </tr>
            </thead>
            <tbody>
                ";
            // line 233
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_sort_filter(twig_get_array_keys_filter($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "bundles", array()))));
            foreach ($context['_seq'] as $context["_key"] => $context["name"]) {
                // line 234
                echo "                <tr>
                    <th scope=\"row\" class=\"font-normal\">";
                // line 235
                echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                echo "</th>
                    <td class=\"font-normal\">";
                // line 236
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "bundles", array()), $context["name"], array(), "array"), "html", null, true);
                echo "</td>
                </tr>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['name'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 239
            echo "            </tbody>
        </table>
    ";
        }
        
        $__internal_84fad03ef0d182556f9c91986c9280e761428e212c2f3c5a5ca18ed6e9584609->leave($__internal_84fad03ef0d182556f9c91986c9280e761428e212c2f3c5a5ca18ed6e9584609_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/config.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  535 => 239,  526 => 236,  522 => 235,  519 => 234,  515 => 233,  502 => 224,  500 => 223,  494 => 220,  485 => 214,  477 => 209,  469 => 204,  461 => 199,  451 => 192,  443 => 187,  435 => 182,  428 => 177,  424 => 175,  417 => 171,  414 => 170,  412 => 169,  409 => 168,  402 => 164,  399 => 163,  397 => 162,  394 => 161,  387 => 157,  384 => 156,  382 => 155,  375 => 151,  369 => 147,  363 => 144,  354 => 138,  346 => 133,  340 => 129,  338 => 128,  335 => 127,  329 => 126,  318 => 121,  313 => 120,  307 => 119,  298 => 116,  295 => 115,  291 => 113,  286 => 110,  280 => 107,  275 => 106,  269 => 102,  267 => 101,  262 => 98,  260 => 97,  252 => 92,  244 => 87,  240 => 86,  231 => 80,  227 => 79,  219 => 73,  211 => 70,  207 => 68,  205 => 67,  202 => 66,  196 => 63,  192 => 61,  190 => 60,  187 => 59,  181 => 56,  177 => 54,  175 => 53,  170 => 50,  164 => 48,  156 => 46,  154 => 45,  148 => 41,  142 => 38,  138 => 37,  135 => 36,  133 => 35,  130 => 34,  128 => 33,  125 => 32,  122 => 31,  117 => 29,  112 => 27,  109 => 26,  107 => 25,  103 => 24,  98 => 23,  95 => 22,  93 => 21,  90 => 20,  87 => 19,  84 => 18,  81 => 17,  78 => 16,  75 => 15,  72 => 14,  69 => 13,  66 => 12,  63 => 11,  60 => 10,  57 => 9,  54 => 8,  51 => 7,  48 => 6,  45 => 5,  42 => 4,  36 => 3,  11 => 1,);
    }
}
/* {% extends '@WebProfiler/Profiler/layout.html.twig' %}*/
/* */
/* {% block toolbar %}*/
/*     {% if 'unknown' == collector.symfonyState %}*/
/*         {% set block_status = '' %}*/
/*         {% set symfony_version_status = 'Unable to retrieve information about the Symfony version.' %}*/
/*     {% elseif 'eol' == collector.symfonyState %}*/
/*         {% set block_status = 'red' %}*/
/*         {% set symfony_version_status = 'This Symfony version will no longer receive security fixes.' %}*/
/*     {% elseif 'eom' == collector.symfonyState %}*/
/*         {% set block_status = 'yellow' %}*/
/*         {% set symfony_version_status = 'This Symfony version will only receive security fixes.' %}*/
/*     {% elseif 'dev' == collector.symfonyState %}*/
/*         {% set block_status = 'yellow' %}*/
/*         {% set symfony_version_status = 'This Symfony version is still in the development phase.' %}*/
/*     {% else %}*/
/*         {% set block_status = '' %}*/
/*         {% set symfony_version_status = '' %}*/
/*     {% endif %}*/
/* */
/*     {% set icon %}*/
/*         {% if collector.applicationname %}*/
/*             <span class="sf-toolbar-label">{{ collector.applicationname }}</span>*/
/*             <span class="sf-toolbar-value">{{ collector.applicationversion }}</span>*/
/*         {% elseif collector.symfonyState is defined %}*/
/*             <span class="sf-toolbar-label">*/
/*                 {{ include('@WebProfiler/Icon/symfony.svg') }}*/
/*             </span>*/
/*             <span class="sf-toolbar-value">{{ collector.symfonyversion }}</span>*/
/*         {% endif %}*/
/*     {% endset %}*/
/* */
/*     {% set text %}*/
/*         <div class="sf-toolbar-info-group">*/
/*             {% if collector.applicationname %}*/
/*                 <div class="sf-toolbar-info-piece">*/
/*                     <b>{{ collector.applicationname }}</b>*/
/*                     <span>{{ collector.applicationversion }}</span>*/
/*                 </div>*/
/*             {% endif %}*/
/* */
/*             <div class="sf-toolbar-info-piece">*/
/*                 <b>Profiler token</b>*/
/*                 <span>*/
/*                     {% if profiler_url %}*/
/*                         <a href="{{ profiler_url }}">{{ collector.token }}</a>*/
/*                     {% else %}*/
/*                         {{ collector.token }}*/
/*                     {% endif %}*/
/*                 </span>*/
/*             </div>*/
/* */
/*             {% if 'n/a' is not same as(collector.appname) %}*/
/*                 <div class="sf-toolbar-info-piece">*/
/*                     <b>Kernel name</b>*/
/*                     <span>{{ collector.appname }}</span>*/
/*                 </div>*/
/*             {% endif %}*/
/* */
/*             {% if 'n/a' is not same as(collector.env) %}*/
/*                 <div class="sf-toolbar-info-piece">*/
/*                     <b>Environment</b>*/
/*                     <span>{{ collector.env }}</span>*/
/*                 </div>*/
/*             {% endif %}*/
/* */
/*             {% if 'n/a' is not same as(collector.debug) %}*/
/*                 <div class="sf-toolbar-info-piece">*/
/*                     <b>Debug</b>*/
/*                     <span class="sf-toolbar-status sf-toolbar-status-{{ collector.debug ? 'green' : 'red' }}">{{ collector.debug ? 'enabled' : 'disabled' }}</span>*/
/*                 </div>*/
/*             {% endif %}*/
/*         </div>*/
/* */
/*         <div class="sf-toolbar-info-group">*/
/*             <div class="sf-toolbar-info-piece sf-toolbar-info-php">*/
/*                 <b>PHP version</b>*/
/*                 <span>*/
/*                     {{ collector.phpversion }}*/
/*                     &nbsp; <a href="{{ path('_profiler_phpinfo') }}">View phpinfo()</a>*/
/*                 </span>*/
/*             </div>*/
/* */
/*             <div class="sf-toolbar-info-piece sf-toolbar-info-php-ext">*/
/*                 <b>PHP Extensions</b>*/
/*                 <span class="sf-toolbar-status sf-toolbar-status-{{ collector.hasxdebug ? 'green' : 'red' }}">xdebug</span>*/
/*                 <span class="sf-toolbar-status sf-toolbar-status-{{ collector.hasaccelerator ? 'green' : 'red' }}">accel</span>*/
/*             </div>*/
/* */
/*             <div class="sf-toolbar-info-piece">*/
/*                 <b>PHP SAPI</b>*/
/*                 <span>{{ collector.sapiName }}</span>*/
/*             </div>*/
/*         </div>*/
/* */
/*         <div class="sf-toolbar-info-group">*/
/*             {% if collector.symfonyversion is defined %}*/
/*                 <div class="sf-toolbar-info-piece">*/
/*                     <b>Resources</b>*/
/*                     <span>*/
/*                         {% if 'Silex' == collector.applicationname %}*/
/*                             <a href="http://silex.sensiolabs.org/documentation" rel="help">*/
/*                                 Read Silex Docs*/
/*                             </a>*/
/*                         {% else %}*/
/*                             <a href="https://symfony.com/doc/{{ collector.symfonyversion }}/index.html" rel="help">*/
/*                                 Read Symfony {{ collector.symfonyversion }} Docs*/
/*                             </a>*/
/*                         {% endif %}*/
/*                     </span>*/
/*                 </div>*/
/*             {% endif %}*/
/*         </div>*/
/*     {% endset %}*/
/* */
/*     {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: true, name: 'config', status: block_status, additional_classes: 'sf-toolbar-block-right' }) }}*/
/* {% endblock %}*/
/* */
/* {% block menu %}*/
/*     <span class="label label-status-{{ collector.symfonyState == 'eol' ? 'red' : collector.symfonyState in ['eom', 'dev'] ? 'yellow' : '' }}">*/
/*         <span class="icon">{{ include('@WebProfiler/Icon/config.svg') }}</span>*/
/*         <strong>Configuration</strong>*/
/*     </span>*/
/* {% endblock %}*/
/* */
/* {% block panel %}*/
/*     {% if collector.applicationname %}*/
/*         {# this application is not the Symfony framework #}*/
/*         <h2>Project Configuration</h2>*/
/* */
/*         <div class="metrics">*/
/*             <div class="metric">*/
/*                 <span class="value">{{ collector.applicationname }}</span>*/
/*                 <span class="label">Application name</span>*/
/*             </div>*/
/* */
/*             <div class="metric">*/
/*                 <span class="value">{{ collector.applicationversion }}</span>*/
/*                 <span class="label">Application version</span>*/
/*             </div>*/
/*         </div>*/
/* */
/*         <p>*/
/*             Based on <a class="text-bold" href="https://symfony.com">Symfony {{ collector.symfonyversion }}</a>*/
/*         </p>*/
/*     {% else %}*/
/*         <h2>Symfony Configuration</h2>*/
/* */
/*         <div class="metrics">*/
/*             <div class="metric">*/
/*                 <span class="value">{{ collector.symfonyversion }}</span>*/
/*                 <span class="label">Symfony version</span>*/
/*             </div>*/
/* */
/*             {% if 'n/a' != collector.appname %}*/
/*                 <div class="metric">*/
/*                     <span class="value">{{ collector.appname }}</span>*/
/*                     <span class="label">Application name</span>*/
/*                 </div>*/
/*             {% endif %}*/
/* */
/*             {% if 'n/a' != collector.env %}*/
/*                 <div class="metric">*/
/*                     <span class="value">{{ collector.env }}</span>*/
/*                     <span class="label">Environment</span>*/
/*                 </div>*/
/*             {% endif %}*/
/* */
/*             {% if 'n/a' != collector.debug %}*/
/*                 <div class="metric">*/
/*                     <span class="value">{{ collector.debug ? 'enabled' : 'disabled' }}</span>*/
/*                     <span class="label">Debug</span>*/
/*                 </div>*/
/*             {% endif %}*/
/*         </div>*/
/*     {% endif %}*/
/* */
/*     <h2>PHP Configuration</h2>*/
/* */
/*     <div class="metrics">*/
/*         <div class="metric">*/
/*             <span class="value">{{ collector.phpversion }}</span>*/
/*             <span class="label">PHP version</span>*/
/*         </div>*/
/* */
/*         <div class="metric">*/
/*             <span class="value">{{ include('@WebProfiler/Icon/' ~ (collector.hasaccelerator ? 'yes' : 'no') ~ '.svg') }}</span>*/
/*             <span class="label">PHP acceleration</span>*/
/*         </div>*/
/* */
/*         <div class="metric">*/
/*             <span class="value">{{ include('@WebProfiler/Icon/' ~ (collector.hasxdebug ? 'yes' : 'no') ~ '.svg') }}</span>*/
/*             <span class="label">Xdebug</span>*/
/*         </div>*/
/*     </div>*/
/* */
/*     <div class="metrics metrics-horizontal">*/
/*         <div class="metric">*/
/*             <span class="value">{{ include('@WebProfiler/Icon/' ~ (collector.haszendopcache ? 'yes' : 'no') ~ '.svg') }}</span>*/
/*             <span class="label">OPcache</span>*/
/*         </div>*/
/* */
/*         <div class="metric">*/
/*             <span class="value">{{ include('@WebProfiler/Icon/' ~ (collector.hasapc ? 'yes' : 'no') ~ '.svg') }}</span>*/
/*             <span class="label">APC</span>*/
/*         </div>*/
/* */
/*         <div class="metric">*/
/*             <span class="value">{{ include('@WebProfiler/Icon/' ~ (collector.hasxcache ? 'yes' : 'no') ~ '.svg') }}</span>*/
/*             <span class="label">XCache</span>*/
/*         </div>*/
/* */
/*         <div class="metric">*/
/*             <span class="value">{{ include('@WebProfiler/Icon/' ~ (collector.haseaccelerator ? 'yes' : 'no') ~ '.svg') }}</span>*/
/*             <span class="label">EAccelerator</span>*/
/*         </div>*/
/*     </div>*/
/* */
/*     <p>*/
/*         <a href="{{ path('_profiler_phpinfo') }}">View full PHP configuration</a>*/
/*     </p>*/
/* */
/*     {% if collector.bundles %}*/
/*         <h2>Enabled Bundles <small>({{ collector.bundles|length }})</small></h2>*/
/*         <table>*/
/*             <thead>*/
/*                 <tr>*/
/*                     <th class="key">Name</th>*/
/*                     <th>Path</th>*/
/*                 </tr>*/
/*             </thead>*/
/*             <tbody>*/
/*                 {% for name in collector.bundles|keys|sort %}*/
/*                 <tr>*/
/*                     <th scope="row" class="font-normal">{{ name }}</th>*/
/*                     <td class="font-normal">{{ collector.bundles[name] }}</td>*/
/*                 </tr>*/
/*                 {% endfor %}*/
/*             </tbody>*/
/*         </table>*/
/*     {% endif %}*/
/* {% endblock %}*/
/* */
