<?php

/* @WebProfiler/Collector/time.html.twig */
class __TwigTemplate_b5d8f410512213ac9dcdff95c15f5cc89d2d8dfc6331308585bcb30bd372492d extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/time.html.twig", 1);
        $this->blocks = array(
            'toolbar' => array($this, 'block_toolbar'),
            'menu' => array($this, 'block_menu'),
            'panel' => array($this, 'block_panel'),
            'panelContent' => array($this, 'block_panelContent'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "@WebProfiler/Profiler/layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_b5b454cd16c46c8518e42b1b9dbbfe7a9a226e83610598999308420c67fcdc42 = $this->env->getExtension("native_profiler");
        $__internal_b5b454cd16c46c8518e42b1b9dbbfe7a9a226e83610598999308420c67fcdc42->enter($__internal_b5b454cd16c46c8518e42b1b9dbbfe7a9a226e83610598999308420c67fcdc42_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/time.html.twig"));

        // line 3
        $context["helper"] = $this;
        // line 5
        if ( !array_key_exists("colors", $context)) {
            // line 6
            $context["colors"] = array("default" => "#999", "section" => "#444", "event_listener" => "#00B8F5", "event_listener_loading" => "#00B8F5", "template" => "#66CC00", "doctrine" => "#FF6633", "propel" => "#FF6633");
        }
        // line 1
        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_b5b454cd16c46c8518e42b1b9dbbfe7a9a226e83610598999308420c67fcdc42->leave($__internal_b5b454cd16c46c8518e42b1b9dbbfe7a9a226e83610598999308420c67fcdc42_prof);

    }

    // line 17
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_ed493ccada8d4b796ed1892e5d33231d0579d46beed98373c590c80379ea1384 = $this->env->getExtension("native_profiler");
        $__internal_ed493ccada8d4b796ed1892e5d33231d0579d46beed98373c590c80379ea1384->enter($__internal_ed493ccada8d4b796ed1892e5d33231d0579d46beed98373c590c80379ea1384_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        // line 18
        echo "    ";
        $context["total_time"] = ((twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()))) ? (sprintf("%.0f", $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "duration", array()))) : ("n/a"));
        // line 19
        echo "    ";
        $context["initialization_time"] = ((twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()))) ? (sprintf("%.0f", $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "inittime", array()))) : ("n/a"));
        // line 20
        echo "    ";
        $context["status_color"] = (((twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array())) && ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "duration", array()) > 1000))) ? ("yellow") : (""));
        // line 21
        echo "
    ";
        // line 22
        ob_start();
        // line 23
        echo "        ";
        echo twig_include($this->env, $context, "@WebProfiler/Icon/time.svg");
        echo "
        <span class=\"sf-toolbar-value\">";
        // line 24
        echo twig_escape_filter($this->env, (isset($context["total_time"]) ? $context["total_time"] : $this->getContext($context, "total_time")), "html", null, true);
        echo "</span>
        <span class=\"sf-toolbar-label\">ms</span>
    ";
        $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 27
        echo "
    ";
        // line 28
        ob_start();
        // line 29
        echo "        <div class=\"sf-toolbar-info-piece\">
            <b>Total time</b>
            <span>";
        // line 31
        echo twig_escape_filter($this->env, (isset($context["total_time"]) ? $context["total_time"] : $this->getContext($context, "total_time")), "html", null, true);
        echo " ms</span>
        </div>
        <div class=\"sf-toolbar-info-piece\">
            <b>Initialization time</b>
            <span>";
        // line 35
        echo twig_escape_filter($this->env, (isset($context["initialization_time"]) ? $context["initialization_time"] : $this->getContext($context, "initialization_time")), "html", null, true);
        echo " ms</span>
        </div>
    ";
        $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 38
        echo "
    ";
        // line 39
        echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", array("link" => (isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url")), "status" => (isset($context["status_color"]) ? $context["status_color"] : $this->getContext($context, "status_color"))));
        echo "
";
        
        $__internal_ed493ccada8d4b796ed1892e5d33231d0579d46beed98373c590c80379ea1384->leave($__internal_ed493ccada8d4b796ed1892e5d33231d0579d46beed98373c590c80379ea1384_prof);

    }

    // line 42
    public function block_menu($context, array $blocks = array())
    {
        $__internal_20169d92dbb2e1c0a5ea991bb91ec086081c6559eb92c8e6642f849038056145 = $this->env->getExtension("native_profiler");
        $__internal_20169d92dbb2e1c0a5ea991bb91ec086081c6559eb92c8e6642f849038056145->enter($__internal_20169d92dbb2e1c0a5ea991bb91ec086081c6559eb92c8e6642f849038056145_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "menu"));

        // line 43
        echo "    <span class=\"label\">
        <span class=\"icon\">";
        // line 44
        echo twig_include($this->env, $context, "@WebProfiler/Icon/time.svg");
        echo "</span>
        <strong>Performance</strong>
    </span>
";
        
        $__internal_20169d92dbb2e1c0a5ea991bb91ec086081c6559eb92c8e6642f849038056145->leave($__internal_20169d92dbb2e1c0a5ea991bb91ec086081c6559eb92c8e6642f849038056145_prof);

    }

    // line 49
    public function block_panel($context, array $blocks = array())
    {
        $__internal_0bf726f46b8f5cb8e2c990f30da0540cff253950f586e47b723acaf4a296cbf5 = $this->env->getExtension("native_profiler");
        $__internal_0bf726f46b8f5cb8e2c990f30da0540cff253950f586e47b723acaf4a296cbf5->enter($__internal_0bf726f46b8f5cb8e2c990f30da0540cff253950f586e47b723acaf4a296cbf5_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panel"));

        // line 50
        echo "    <h2>Performance metrics</h2>

    <div class=\"metrics\">
        <div class=\"metric\">
            <span class=\"value\">";
        // line 54
        echo twig_escape_filter($this->env, sprintf("%.0f", $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "duration", array())), "html", null, true);
        echo " <span class=\"unit\">ms</span></span>
            <span class=\"label\">Total execution time</span>
        </div>

        <div class=\"metric\">
            <span class=\"value\">";
        // line 59
        echo twig_escape_filter($this->env, sprintf("%.0f", $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "inittime", array())), "html", null, true);
        echo " <span class=\"unit\">ms</span></span>
            <span class=\"label\">Symfony initialization</span>
        </div>

        ";
        // line 63
        if ((twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array())) > 0)) {
            // line 64
            echo "            <div class=\"metric\">
                <span class=\"value\">";
            // line 65
            echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array())), "html", null, true);
            echo "</span>
                <span class=\"label\">Sub-Requests</span>
            </div>

            ";
            // line 69
            $context["subrequests_time"] = 0;
            // line 70
            echo "            ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()));
            foreach ($context['_seq'] as $context["_key"] => $context["child"]) {
                // line 71
                echo "                ";
                $context["subrequests_time"] = ((isset($context["subrequests_time"]) ? $context["subrequests_time"] : $this->getContext($context, "subrequests_time")) + $this->getAttribute($this->getAttribute($this->getAttribute($this->getAttribute($context["child"], "getcollector", array(0 => "time"), "method"), "events", array()), "__section__", array()), "duration", array()));
                // line 72
                echo "            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['child'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 73
            echo "
            <div class=\"metric\">
                <span class=\"value\">";
            // line 75
            echo twig_escape_filter($this->env, (isset($context["subrequests_time"]) ? $context["subrequests_time"] : $this->getContext($context, "subrequests_time")), "html", null, true);
            echo " <span class=\"unit\">ms</span></span>
                <span class=\"label\">Sub-Requests time</span>
            </div>
        ";
        }
        // line 79
        echo "
        ";
        // line 80
        if ($this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "collectors", array()), "memory", array())) {
            // line 81
            echo "            <div class=\"metric\">
                <span class=\"value\">";
            // line 82
            echo twig_escape_filter($this->env, sprintf("%.2f", (($this->getAttribute($this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "collectors", array()), "memory", array()), "memory", array()) / 1024) / 1024)), "html", null, true);
            echo " <span class=\"unit\">MB</span></span>
                <span class=\"label\">Peak memory usage</span>
            </div>
        ";
        }
        // line 86
        echo "    </div>

    <h2>Execution timeline</h2>

    ";
        // line 90
        if (twig_test_empty($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()))) {
            // line 91
            echo "        <div class=\"empty\">
            <p>No timing events have been recorded. Are you sure that debugging is enabled in the kernel?</p>
        </div>
    ";
        } else {
            // line 95
            echo "        ";
            $this->displayBlock("panelContent", $context, $blocks);
            echo "
    ";
        }
        
        $__internal_0bf726f46b8f5cb8e2c990f30da0540cff253950f586e47b723acaf4a296cbf5->leave($__internal_0bf726f46b8f5cb8e2c990f30da0540cff253950f586e47b723acaf4a296cbf5_prof);

    }

    // line 99
    public function block_panelContent($context, array $blocks = array())
    {
        $__internal_b9cd38dd56a944868ade59382321fe59dc24e357ad83c510b3e317c563e6b3dd = $this->env->getExtension("native_profiler");
        $__internal_b9cd38dd56a944868ade59382321fe59dc24e357ad83c510b3e317c563e6b3dd->enter($__internal_b9cd38dd56a944868ade59382321fe59dc24e357ad83c510b3e317c563e6b3dd_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panelContent"));

        // line 100
        echo "    <form id=\"timeline-control\" action=\"\" method=\"get\">
        <input type=\"hidden\" name=\"panel\" value=\"time\">
        <label for=\"threshold\">Threshold</label>
        <input type=\"number\" size=\"3\" name=\"threshold\" id=\"threshold\" value=\"3\" min=\"0\"> ms
        <span class=\"help\">(timeline only displays events with a duration longer than this threshold)</span>
    </form>

    ";
        // line 107
        if ($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "parent", array())) {
            // line 108
            echo "        <h3>
            Sub-Request ";
            // line 109
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "getcollector", array(0 => "request"), "method"), "requestattributes", array()), "get", array(0 => "_controller"), "method"), "html", null, true);
            echo "
            <small>
                ";
            // line 111
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), "__section__", array()), "duration", array()), "html", null, true);
            echo " ms
                <a class=\"newline\" href=\"";
            // line 112
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("_profiler", array("token" => $this->getAttribute($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "parent", array()), "token", array()), "panel" => "time")), "html", null, true);
            echo "\">Return to parent request</a>
            </small>
        </h3>
    ";
        } elseif ((twig_length_filter($this->env, $this->getAttribute(        // line 115
(isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array())) > 0)) {
            // line 116
            echo "        <h3>
            Main Request <small>";
            // line 117
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), "__section__", array()), "duration", array()), "html", null, true);
            echo " ms</small>
        </h3>
    ";
        }
        // line 120
        echo "
    ";
        // line 121
        echo $context["helper"]->getdisplay_timeline(("timeline_" . (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token"))), $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), (isset($context["colors"]) ? $context["colors"] : $this->getContext($context, "colors")));
        echo "

    ";
        // line 123
        if (twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()))) {
            // line 124
            echo "        <p class=\"help\">Note: sections with a striped background correspond to sub-requests.</p>

        <h3>Sub-requests <small>(";
            // line 126
            echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array())), "html", null, true);
            echo ")</small></h3>

        ";
            // line 128
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()));
            foreach ($context['_seq'] as $context["_key"] => $context["child"]) {
                // line 129
                echo "            ";
                $context["events"] = $this->getAttribute($this->getAttribute($context["child"], "getcollector", array(0 => "time"), "method"), "events", array());
                // line 130
                echo "            <h4>
                <a href=\"";
                // line 131
                echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("_profiler", array("token" => $this->getAttribute($context["child"], "token", array()), "panel" => "time")), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute($context["child"], "getcollector", array(0 => "request"), "method"), "requestattributes", array()), "get", array(0 => "_controller"), "method"), "html", null, true);
                echo "</a>
                <small>";
                // line 132
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["events"]) ? $context["events"] : $this->getContext($context, "events")), "__section__", array()), "duration", array()), "html", null, true);
                echo " ms</small>
            </h4>

            ";
                // line 135
                echo $context["helper"]->getdisplay_timeline(("timeline_" . $this->getAttribute($context["child"], "token", array())), (isset($context["events"]) ? $context["events"] : $this->getContext($context, "events")), (isset($context["colors"]) ? $context["colors"] : $this->getContext($context, "colors")));
                echo "
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['child'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 137
            echo "    ";
        }
        // line 138
        echo "
    <script>";
        // line 139
        echo "//<![CDATA[
        /**
         * In-memory key-value cache manager
         */
        var cache = new function() {
            \"use strict\";
            var dict = {};

            this.get = function(key) {
                return dict.hasOwnProperty(key)
                    ? dict[key]
                    : null;
                };

            this.set = function(key, value) {
                dict[key] = value;

                return value;
            };
        };

        /**
         * Query an element with a CSS selector.
         *
         * @param string selector a CSS-selector-compatible query string.
         *
         * @return DOMElement|null
         */
        function query(selector)
        {
            \"use strict\";
            var key = 'SELECTOR: ' + selector;

            return cache.get(key) || cache.set(key, document.querySelector(selector));
        }

        /**
         * Canvas Manager
         */
        function CanvasManager(requests, maxRequestTime) {
            \"use strict\";

            var _drawingColors = ";
        // line 181
        echo twig_jsonencode_filter((isset($context["colors"]) ? $context["colors"] : $this->getContext($context, "colors")));
        echo ",
                _storagePrefix = 'timeline/',
                _threshold = 1,
                _requests = requests,
                _maxRequestTime = maxRequestTime;

            /**
             * Check whether this event is a child event.
             *
             * @return true if it is.
             */
            function isChildEvent(event)
            {
                return '__section__.child' === event.name;
            }

            /**
             * Check whether this event is categorized in 'section'.
             *
             * @return true if it is.
             */
            function isSectionEvent(event)
            {
                return 'section' === event.category;
            }

            /**
             * Get the width of the container.
             */
            function getContainerWidth()
            {
                return query('#collector-content h2').clientWidth;
            }

            /**
             * Draw one canvas.
             *
             * @param request   the request object
             * @param max       <subjected for removal>
             * @param threshold the threshold (lower bound) of the length of the timeline (in milliseconds).
             * @param width     the width of the canvas.
             */
            this.drawOne = function(request, max, threshold, width)
            {
                \"use strict\";
                var text,
                    ms,
                    xc,
                    drawableEvents,
                    mainEvents,
                    elementId = 'timeline_' + request.id,
                    canvasHeight = 0,
                    gapPerEvent = 38,
                    colors = _drawingColors,
                    space = 10.5,
                    ratio = (width - space * 2) / max,
                    h = space,
                    x = request.left * ratio + space, // position
                    canvas = cache.get(elementId) || cache.set(elementId, document.getElementById(elementId)),
                    ctx = canvas.getContext(\"2d\"),
                    scaleRatio,
                    devicePixelRatio;

                // Filter events whose total time is below the threshold.
                drawableEvents = request.events.filter(function(event) {
                    return event.duration >= threshold;
                });

                canvasHeight += gapPerEvent * drawableEvents.length;

                // For retina displays so text and boxes will be crisp
                devicePixelRatio = window.devicePixelRatio == \"undefined\" ? 1 : window.devicePixelRatio;
                scaleRatio = devicePixelRatio / 1;

                canvas.width = width * scaleRatio;
                canvas.height = canvasHeight * scaleRatio;

                canvas.style.width = width + 'px';
                canvas.style.height = canvasHeight + 'px';

                ctx.scale(scaleRatio, scaleRatio);

                ctx.textBaseline = \"middle\";
                ctx.lineWidth = 0;

                // For each event, draw a line.
                ctx.strokeStyle = \"#CCC\";

                drawableEvents.forEach(function(event) {
                    event.periods.forEach(function(period) {
                        var timelineHeadPosition = x + period.start * ratio;

                        if (isChildEvent(event)) {
                            /* create a striped background dynamically */
                            var img = new Image();
                            img.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKBAMAAAB/HNKOAAAAIVBMVEX////w8PDd7h7d7h7d7h7d7h7w8PDw8PDw8PDw8PDw8PAOi84XAAAAKUlEQVQImWNI71zAwMBQMYuBgY0BxExnADErGEDMTgYQE8hnAKtCZwIAlcMNSR9a1OEAAAAASUVORK5CYII=';
                            var pattern = ctx.createPattern(img, 'repeat');

                            ctx.fillStyle = pattern;
                            ctx.fillRect(timelineHeadPosition, 0, (period.end - period.start) * ratio, canvasHeight);
                        } else if (isSectionEvent(event)) {
                            var timelineTailPosition = x + period.end * ratio;

                            ctx.beginPath();
                            ctx.moveTo(timelineHeadPosition, 0);
                            ctx.lineTo(timelineHeadPosition, canvasHeight);
                            ctx.moveTo(timelineTailPosition, 0);
                            ctx.lineTo(timelineTailPosition, canvasHeight);
                            ctx.fill();
                            ctx.closePath();
                            ctx.stroke();
                        }
                    });
                });

                // Filter for main events.
                mainEvents = drawableEvents.filter(function(event) {
                    return !isChildEvent(event)
                });

                // For each main event, draw the visual presentation of timelines.
                mainEvents.forEach(function(event) {

                    h += 8;

                    // For each sub event, ...
                    event.periods.forEach(function(period) {
                        // Set the drawing style.
                        ctx.fillStyle = colors['default'];
                        ctx.strokeStyle = colors['default'];

                        if (colors[event.name]) {
                            ctx.fillStyle = colors[event.name];
                            ctx.strokeStyle = colors[event.name];
                        } else if (colors[event.category]) {
                            ctx.fillStyle = colors[event.category];
                            ctx.strokeStyle = colors[event.category];
                        }

                        // Draw the timeline
                        var timelineHeadPosition = x + period.start * ratio;

                        if (!isSectionEvent(event)) {
                            ctx.fillRect(timelineHeadPosition, h + 3, 2, 8);
                            ctx.fillRect(timelineHeadPosition, h, (period.end - period.start) * ratio || 2, 6);
                        } else {
                            var timelineTailPosition = x + period.end * ratio;

                            ctx.beginPath();
                            ctx.moveTo(timelineHeadPosition, h);
                            ctx.lineTo(timelineHeadPosition, h + 11);
                            ctx.lineTo(timelineHeadPosition + 8, h);
                            ctx.lineTo(timelineHeadPosition, h);
                            ctx.fill();
                            ctx.closePath();
                            ctx.stroke();

                            ctx.beginPath();
                            ctx.moveTo(timelineTailPosition, h);
                            ctx.lineTo(timelineTailPosition, h + 11);
                            ctx.lineTo(timelineTailPosition - 8, h);
                            ctx.lineTo(timelineTailPosition, h);
                            ctx.fill();
                            ctx.closePath();
                            ctx.stroke();

                            ctx.beginPath();
                            ctx.moveTo(timelineHeadPosition, h);
                            ctx.lineTo(timelineTailPosition, h);
                            ctx.lineTo(timelineTailPosition, h + 2);
                            ctx.lineTo(timelineHeadPosition, h + 2);
                            ctx.lineTo(timelineHeadPosition, h);
                            ctx.fill();
                            ctx.closePath();
                            ctx.stroke();
                        }
                    });

                    h += 30;

                    ctx.beginPath();
                    ctx.strokeStyle = \"#E0E0E0\";
                    ctx.moveTo(0, h - 10);
                    ctx.lineTo(width, h - 10);
                    ctx.closePath();
                    ctx.stroke();
                });

                h = space;

                // For each event, draw the label.
                mainEvents.forEach(function(event) {

                    ctx.fillStyle = \"#444\";
                    ctx.font = \"12px sans-serif\";
                    text = event.name;
                    ms = \"  \" + (event.duration < 1 ? event.duration : parseInt(event.duration, 10)) + \" ms / \" + event.memory + \" MB\";
                    if (x + event.starttime * ratio + ctx.measureText(text + ms).width > width) {
                        ctx.textAlign = \"end\";
                        ctx.font = \"10px sans-serif\";
                        ctx.fillStyle = \"#777\";
                        xc = x + event.endtime * ratio - 1;
                        ctx.fillText(ms, xc, h);

                        xc -= ctx.measureText(ms).width;
                        ctx.font = \"12px sans-serif\";
                        ctx.fillStyle = \"#222\";
                        ctx.fillText(text, xc, h);
                    } else {
                        ctx.textAlign = \"start\";
                        ctx.font = \"13px sans-serif\";
                        ctx.fillStyle = \"#222\";
                        xc = x + event.starttime * ratio + 1;
                        ctx.fillText(text, xc, h);

                        xc += ctx.measureText(text).width;
                        ctx.font = \"11px sans-serif\";
                        ctx.fillStyle = \"#777\";
                        ctx.fillText(ms, xc, h);
                    }

                    h += gapPerEvent;
                });
            };

            this.drawAll = function(width, threshold)
            {
                \"use strict\";

                width = width || getContainerWidth();
                threshold = threshold || this.getThreshold();

                var self = this;

                _requests.forEach(function(request) {
                    self.drawOne(request, _maxRequestTime, threshold, width);
                });
            };

            this.getThreshold = function() {
                var threshold = Sfjs.getPreference(_storagePrefix + 'threshold');

                if (null === threshold) {
                    return _threshold;
                }

                _threshold = parseInt(threshold);

                return _threshold;
            };

            this.setThreshold = function(threshold)
            {
                _threshold = threshold;

                Sfjs.setPreference(_storagePrefix + 'threshold', threshold);

                return this;
            };
        }

        function canvasAutoUpdateOnResizeAndSubmit(e) {
            e.preventDefault();
            canvasManager.drawAll();
        }

        function canvasAutoUpdateOnThresholdChange(e) {
            canvasManager
                .setThreshold(query('input[name=\"threshold\"]').value)
                .drawAll();
        }

        var requests_data = {
            \"max\": ";
        // line 454
        echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), "__section__", array()), "endtime", array())), "js", null, true);
        echo ",
            \"requests\": [
";
        // line 456
        echo $context["helper"]->getdump_request_data((isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), (isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), "__section__", array()), "origin", array()));
        echo "

";
        // line 458
        if (twig_length_filter($this->env, $this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()))) {
            // line 459
            echo "                ,
";
            // line 460
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "children", array()));
            $context['loop'] = array(
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            );
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["_key"] => $context["child"]) {
                // line 461
                echo $context["helper"]->getdump_request_data($this->getAttribute($context["child"], "token", array()), $context["child"], $this->getAttribute($this->getAttribute($context["child"], "getcollector", array(0 => "time"), "method"), "events", array()), $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "events", array()), "__section__", array()), "origin", array()));
                echo (($this->getAttribute($context["loop"], "last", array())) ? ("") : (","));
                echo "
";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['child'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
        }
        // line 464
        echo "            ]
        };

        var canvasManager = new CanvasManager(requests_data.requests, requests_data.max);

        query('input[name=\"threshold\"]').value = canvasManager.getThreshold();
        canvasManager.drawAll();

        // Update the colors of legends.
        var timelineLegends = document.querySelectorAll('.sf-profiler-timeline > .legends > span[data-color]');

        for (var i = 0; i < timelineLegends.length; ++i) {
            var timelineLegend = timelineLegends[i];

            timelineLegend.style.borderLeftColor = timelineLegend.getAttribute('data-color');
        }

        // Bind event handlers
        var elementTimelineControl = query('#timeline-control'),
            elementThresholdControl = query('input[name=\"threshold\"]');

        window.onresize = canvasAutoUpdateOnResizeAndSubmit;
        elementTimelineControl.onsubmit = canvasAutoUpdateOnResizeAndSubmit;

        elementThresholdControl.onclick = canvasAutoUpdateOnThresholdChange;
        elementThresholdControl.onchange = canvasAutoUpdateOnThresholdChange;
        elementThresholdControl.onkeyup = canvasAutoUpdateOnThresholdChange;

        window.setTimeout(function() {
            canvasAutoUpdateOnThresholdChange(null);
        }, 50);

    //]]>";
        // line 496
        echo "</script>
";
        
        $__internal_b9cd38dd56a944868ade59382321fe59dc24e357ad83c510b3e317c563e6b3dd->leave($__internal_b9cd38dd56a944868ade59382321fe59dc24e357ad83c510b3e317c563e6b3dd_prof);

    }

    // line 499
    public function getdump_request_data($__token__ = null, $__profile__ = null, $__events__ = null, $__origin__ = null)
    {
        $context = $this->env->mergeGlobals(array(
            "token" => $__token__,
            "profile" => $__profile__,
            "events" => $__events__,
            "origin" => $__origin__,
            "varargs" => func_num_args() > 4 ? array_slice(func_get_args(), 4) : array(),
        ));

        $blocks = array();

        ob_start();
        try {
            $__internal_9b83f50774123eac0f4af6d49f0b99f5f8ef43da4037b8c31978977f7f69e150 = $this->env->getExtension("native_profiler");
            $__internal_9b83f50774123eac0f4af6d49f0b99f5f8ef43da4037b8c31978977f7f69e150->enter($__internal_9b83f50774123eac0f4af6d49f0b99f5f8ef43da4037b8c31978977f7f69e150_prof = new Twig_Profiler_Profile($this->getTemplateName(), "macro", "dump_request_data"));

            // line 501
            $context["__internal_1637d0d69ac699cd3147047c52cee30881ded92f582f1d8aa48150893e5f8040"] = $this;
            // line 502
            echo "                {
                    \"id\": \"";
            // line 503
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "js", null, true);
            echo "\",
                    \"left\": ";
            // line 504
            echo twig_escape_filter($this->env, sprintf("%F", ($this->getAttribute($this->getAttribute((isset($context["events"]) ? $context["events"] : $this->getContext($context, "events")), "__section__", array()), "origin", array()) - (isset($context["origin"]) ? $context["origin"] : $this->getContext($context, "origin")))), "js", null, true);
            echo ",
                    \"events\": [
";
            // line 506
            echo $context["__internal_1637d0d69ac699cd3147047c52cee30881ded92f582f1d8aa48150893e5f8040"]->getdump_events((isset($context["events"]) ? $context["events"] : $this->getContext($context, "events")));
            echo "
                    ]
                }
";
            
            $__internal_9b83f50774123eac0f4af6d49f0b99f5f8ef43da4037b8c31978977f7f69e150->leave($__internal_9b83f50774123eac0f4af6d49f0b99f5f8ef43da4037b8c31978977f7f69e150_prof);

        } catch (Exception $e) {
            ob_end_clean();

            throw $e;
        }

        return ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
    }

    // line 512
    public function getdump_events($__events__ = null)
    {
        $context = $this->env->mergeGlobals(array(
            "events" => $__events__,
            "varargs" => func_num_args() > 1 ? array_slice(func_get_args(), 1) : array(),
        ));

        $blocks = array();

        ob_start();
        try {
            $__internal_2fcb78ebcbd19a13d4c58c2a9e154609bb440d7768f15495d453f91c2a51596c = $this->env->getExtension("native_profiler");
            $__internal_2fcb78ebcbd19a13d4c58c2a9e154609bb440d7768f15495d453f91c2a51596c->enter($__internal_2fcb78ebcbd19a13d4c58c2a9e154609bb440d7768f15495d453f91c2a51596c_prof = new Twig_Profiler_Profile($this->getTemplateName(), "macro", "dump_events"));

            // line 514
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable((isset($context["events"]) ? $context["events"] : $this->getContext($context, "events")));
            $context['loop'] = array(
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            );
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["name"] => $context["event"]) {
                // line 515
                if (("__section__" != $context["name"])) {
                    // line 516
                    echo "                        {
                            \"name\": \"";
                    // line 517
                    echo twig_escape_filter($this->env, $context["name"], "js", null, true);
                    echo "\",
                            \"category\": \"";
                    // line 518
                    echo twig_escape_filter($this->env, $this->getAttribute($context["event"], "category", array()), "js", null, true);
                    echo "\",
                            \"origin\": ";
                    // line 519
                    echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($context["event"], "origin", array())), "js", null, true);
                    echo ",
                            \"starttime\": ";
                    // line 520
                    echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($context["event"], "starttime", array())), "js", null, true);
                    echo ",
                            \"endtime\": ";
                    // line 521
                    echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($context["event"], "endtime", array())), "js", null, true);
                    echo ",
                            \"duration\": ";
                    // line 522
                    echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($context["event"], "duration", array())), "js", null, true);
                    echo ",
                            \"memory\": ";
                    // line 523
                    echo twig_escape_filter($this->env, sprintf("%.1F", (($this->getAttribute($context["event"], "memory", array()) / 1024) / 1024)), "js", null, true);
                    echo ",
                            \"periods\": [";
                    // line 525
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable($this->getAttribute($context["event"], "periods", array()));
                    $context['loop'] = array(
                      'parent' => $context['_parent'],
                      'index0' => 0,
                      'index'  => 1,
                      'first'  => true,
                    );
                    if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof Countable)) {
                        $length = count($context['_seq']);
                        $context['loop']['revindex0'] = $length - 1;
                        $context['loop']['revindex'] = $length;
                        $context['loop']['length'] = $length;
                        $context['loop']['last'] = 1 === $length;
                    }
                    foreach ($context['_seq'] as $context["_key"] => $context["period"]) {
                        // line 526
                        echo "{\"start\": ";
                        echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($context["period"], "starttime", array())), "js", null, true);
                        echo ", \"end\": ";
                        echo twig_escape_filter($this->env, sprintf("%F", $this->getAttribute($context["period"], "endtime", array())), "js", null, true);
                        echo "}";
                        echo (($this->getAttribute($context["loop"], "last", array())) ? ("") : (", "));
                        ++$context['loop']['index0'];
                        ++$context['loop']['index'];
                        $context['loop']['first'] = false;
                        if (isset($context['loop']['length'])) {
                            --$context['loop']['revindex0'];
                            --$context['loop']['revindex'];
                            $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                        }
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['period'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 528
                    echo "]
                        }";
                    // line 529
                    echo (($this->getAttribute($context["loop"], "last", array())) ? ("") : (","));
                    echo "
";
                }
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['name'], $context['event'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            
            $__internal_2fcb78ebcbd19a13d4c58c2a9e154609bb440d7768f15495d453f91c2a51596c->leave($__internal_2fcb78ebcbd19a13d4c58c2a9e154609bb440d7768f15495d453f91c2a51596c_prof);

        } catch (Exception $e) {
            ob_end_clean();

            throw $e;
        }

        return ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
    }

    // line 535
    public function getdisplay_timeline($__id__ = null, $__events__ = null, $__colors__ = null)
    {
        $context = $this->env->mergeGlobals(array(
            "id" => $__id__,
            "events" => $__events__,
            "colors" => $__colors__,
            "varargs" => func_num_args() > 3 ? array_slice(func_get_args(), 3) : array(),
        ));

        $blocks = array();

        ob_start();
        try {
            $__internal_ffae8afa9713009dc0f6684310ced1c9b84b598b67416419a269e18a9b99d5b8 = $this->env->getExtension("native_profiler");
            $__internal_ffae8afa9713009dc0f6684310ced1c9b84b598b67416419a269e18a9b99d5b8->enter($__internal_ffae8afa9713009dc0f6684310ced1c9b84b598b67416419a269e18a9b99d5b8_prof = new Twig_Profiler_Profile($this->getTemplateName(), "macro", "display_timeline"));

            // line 536
            echo "    <div class=\"sf-profiler-timeline\">
        <div class=\"legends\">
            ";
            // line 538
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable((isset($context["colors"]) ? $context["colors"] : $this->getContext($context, "colors")));
            foreach ($context['_seq'] as $context["category"] => $context["color"]) {
                // line 539
                echo "                <span data-color=\"";
                echo twig_escape_filter($this->env, $context["color"], "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, $context["category"], "html", null, true);
                echo "</span>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['category'], $context['color'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 541
            echo "        </div>
        <canvas width=\"680\" height=\"\" id=\"";
            // line 542
            echo twig_escape_filter($this->env, (isset($context["id"]) ? $context["id"] : $this->getContext($context, "id")), "html", null, true);
            echo "\" class=\"timeline\"></canvas>
    </div>
";
            
            $__internal_ffae8afa9713009dc0f6684310ced1c9b84b598b67416419a269e18a9b99d5b8->leave($__internal_ffae8afa9713009dc0f6684310ced1c9b84b598b67416419a269e18a9b99d5b8_prof);

        } catch (Exception $e) {
            ob_end_clean();

            throw $e;
        }

        return ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/time.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  971 => 542,  968 => 541,  957 => 539,  953 => 538,  949 => 536,  932 => 535,  903 => 529,  900 => 528,  881 => 526,  864 => 525,  860 => 523,  856 => 522,  852 => 521,  848 => 520,  844 => 519,  840 => 518,  836 => 517,  833 => 516,  831 => 515,  814 => 514,  799 => 512,  781 => 506,  776 => 504,  772 => 503,  769 => 502,  767 => 501,  749 => 499,  741 => 496,  707 => 464,  689 => 461,  672 => 460,  669 => 459,  667 => 458,  662 => 456,  657 => 454,  381 => 181,  337 => 139,  334 => 138,  331 => 137,  323 => 135,  317 => 132,  311 => 131,  308 => 130,  305 => 129,  301 => 128,  296 => 126,  292 => 124,  290 => 123,  285 => 121,  282 => 120,  276 => 117,  273 => 116,  271 => 115,  265 => 112,  261 => 111,  256 => 109,  253 => 108,  251 => 107,  242 => 100,  236 => 99,  225 => 95,  219 => 91,  217 => 90,  211 => 86,  204 => 82,  201 => 81,  199 => 80,  196 => 79,  189 => 75,  185 => 73,  179 => 72,  176 => 71,  171 => 70,  169 => 69,  162 => 65,  159 => 64,  157 => 63,  150 => 59,  142 => 54,  136 => 50,  130 => 49,  119 => 44,  116 => 43,  110 => 42,  101 => 39,  98 => 38,  92 => 35,  85 => 31,  81 => 29,  79 => 28,  76 => 27,  70 => 24,  65 => 23,  63 => 22,  60 => 21,  57 => 20,  54 => 19,  51 => 18,  45 => 17,  38 => 1,  35 => 6,  33 => 5,  31 => 3,  11 => 1,);
    }
}
/* {% extends '@WebProfiler/Profiler/layout.html.twig' %}*/
/* */
/* {% import _self as helper %}*/
/* */
/* {% if colors is not defined %}*/
/*     {% set colors = {*/
/*         'default':                '#999',*/
/*         'section':                '#444',*/
/*         'event_listener':         '#00B8F5',*/
/*         'event_listener_loading': '#00B8F5',*/
/*         'template':               '#66CC00',*/
/*         'doctrine':               '#FF6633',*/
/*         'propel':                 '#FF6633',*/
/*     } %}*/
/* {% endif %}*/
/* */
/* {% block toolbar %}*/
/*     {% set total_time = collector.events|length ? '%.0f'|format(collector.duration) : 'n/a' %}*/
/*     {% set initialization_time = collector.events|length ? '%.0f'|format(collector.inittime) : 'n/a' %}*/
/*     {% set status_color = collector.events|length and collector.duration > 1000 ? 'yellow' : '' %}*/
/* */
/*     {% set icon %}*/
/*         {{ include('@WebProfiler/Icon/time.svg') }}*/
/*         <span class="sf-toolbar-value">{{ total_time }}</span>*/
/*         <span class="sf-toolbar-label">ms</span>*/
/*     {% endset %}*/
/* */
/*     {% set text %}*/
/*         <div class="sf-toolbar-info-piece">*/
/*             <b>Total time</b>*/
/*             <span>{{ total_time }} ms</span>*/
/*         </div>*/
/*         <div class="sf-toolbar-info-piece">*/
/*             <b>Initialization time</b>*/
/*             <span>{{ initialization_time }} ms</span>*/
/*         </div>*/
/*     {% endset %}*/
/* */
/*     {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url, status: status_color }) }}*/
/* {% endblock %}*/
/* */
/* {% block menu %}*/
/*     <span class="label">*/
/*         <span class="icon">{{ include('@WebProfiler/Icon/time.svg') }}</span>*/
/*         <strong>Performance</strong>*/
/*     </span>*/
/* {% endblock %}*/
/* */
/* {% block panel %}*/
/*     <h2>Performance metrics</h2>*/
/* */
/*     <div class="metrics">*/
/*         <div class="metric">*/
/*             <span class="value">{{ '%.0f'|format(collector.duration) }} <span class="unit">ms</span></span>*/
/*             <span class="label">Total execution time</span>*/
/*         </div>*/
/* */
/*         <div class="metric">*/
/*             <span class="value">{{ '%.0f'|format(collector.inittime) }} <span class="unit">ms</span></span>*/
/*             <span class="label">Symfony initialization</span>*/
/*         </div>*/
/* */
/*         {% if profile.children|length > 0 %}*/
/*             <div class="metric">*/
/*                 <span class="value">{{ profile.children|length }}</span>*/
/*                 <span class="label">Sub-Requests</span>*/
/*             </div>*/
/* */
/*             {% set subrequests_time = 0 %}*/
/*             {% for child in profile.children %}*/
/*                 {% set subrequests_time = subrequests_time + child.getcollector('time').events.__section__.duration %}*/
/*             {% endfor %}*/
/* */
/*             <div class="metric">*/
/*                 <span class="value">{{ subrequests_time }} <span class="unit">ms</span></span>*/
/*                 <span class="label">Sub-Requests time</span>*/
/*             </div>*/
/*         {% endif %}*/
/* */
/*         {% if profile.collectors.memory %}*/
/*             <div class="metric">*/
/*                 <span class="value">{{ '%.2f'|format(profile.collectors.memory.memory / 1024 / 1024) }} <span class="unit">MB</span></span>*/
/*                 <span class="label">Peak memory usage</span>*/
/*             </div>*/
/*         {% endif %}*/
/*     </div>*/
/* */
/*     <h2>Execution timeline</h2>*/
/* */
/*     {% if collector.events is empty %}*/
/*         <div class="empty">*/
/*             <p>No timing events have been recorded. Are you sure that debugging is enabled in the kernel?</p>*/
/*         </div>*/
/*     {% else %}*/
/*         {{ block('panelContent') }}*/
/*     {% endif %}*/
/* {% endblock %}*/
/* */
/* {% block panelContent %}*/
/*     <form id="timeline-control" action="" method="get">*/
/*         <input type="hidden" name="panel" value="time">*/
/*         <label for="threshold">Threshold</label>*/
/*         <input type="number" size="3" name="threshold" id="threshold" value="3" min="0"> ms*/
/*         <span class="help">(timeline only displays events with a duration longer than this threshold)</span>*/
/*     </form>*/
/* */
/*     {% if profile.parent %}*/
/*         <h3>*/
/*             Sub-Request {{ profile.getcollector('request').requestattributes.get('_controller') }}*/
/*             <small>*/
/*                 {{ collector.events.__section__.duration }} ms*/
/*                 <a class="newline" href="{{ path('_profiler', { token: profile.parent.token, panel: 'time' }) }}">Return to parent request</a>*/
/*             </small>*/
/*         </h3>*/
/*     {% elseif profile.children|length > 0 %}*/
/*         <h3>*/
/*             Main Request <small>{{ collector.events.__section__.duration }} ms</small>*/
/*         </h3>*/
/*     {% endif %}*/
/* */
/*     {{ helper.display_timeline('timeline_' ~ token, collector.events, colors) }}*/
/* */
/*     {% if profile.children|length %}*/
/*         <p class="help">Note: sections with a striped background correspond to sub-requests.</p>*/
/* */
/*         <h3>Sub-requests <small>({{ profile.children|length }})</small></h3>*/
/* */
/*         {% for child in profile.children %}*/
/*             {% set events = child.getcollector('time').events %}*/
/*             <h4>*/
/*                 <a href="{{ path('_profiler', { token: child.token, panel: 'time' }) }}">{{ child.getcollector('request').requestattributes.get('_controller') }}</a>*/
/*                 <small>{{ events.__section__.duration }} ms</small>*/
/*             </h4>*/
/* */
/*             {{ helper.display_timeline('timeline_' ~ child.token, events, colors) }}*/
/*         {% endfor %}*/
/*     {% endif %}*/
/* */
/*     <script>{% autoescape 'js' %}//<![CDATA[*/
/*         /***/
/*          * In-memory key-value cache manager*/
/*          *//* */
/*         var cache = new function() {*/
/*             "use strict";*/
/*             var dict = {};*/
/* */
/*             this.get = function(key) {*/
/*                 return dict.hasOwnProperty(key)*/
/*                     ? dict[key]*/
/*                     : null;*/
/*                 };*/
/* */
/*             this.set = function(key, value) {*/
/*                 dict[key] = value;*/
/* */
/*                 return value;*/
/*             };*/
/*         };*/
/* */
/*         /***/
/*          * Query an element with a CSS selector.*/
/*          **/
/*          * @param string selector a CSS-selector-compatible query string.*/
/*          **/
/*          * @return DOMElement|null*/
/*          *//* */
/*         function query(selector)*/
/*         {*/
/*             "use strict";*/
/*             var key = 'SELECTOR: ' + selector;*/
/* */
/*             return cache.get(key) || cache.set(key, document.querySelector(selector));*/
/*         }*/
/* */
/*         /***/
/*          * Canvas Manager*/
/*          *//* */
/*         function CanvasManager(requests, maxRequestTime) {*/
/*             "use strict";*/
/* */
/*             var _drawingColors = {{ colors|json_encode|raw }},*/
/*                 _storagePrefix = 'timeline/',*/
/*                 _threshold = 1,*/
/*                 _requests = requests,*/
/*                 _maxRequestTime = maxRequestTime;*/
/* */
/*             /***/
/*              * Check whether this event is a child event.*/
/*              **/
/*              * @return true if it is.*/
/*              *//* */
/*             function isChildEvent(event)*/
/*             {*/
/*                 return '__section__.child' === event.name;*/
/*             }*/
/* */
/*             /***/
/*              * Check whether this event is categorized in 'section'.*/
/*              **/
/*              * @return true if it is.*/
/*              *//* */
/*             function isSectionEvent(event)*/
/*             {*/
/*                 return 'section' === event.category;*/
/*             }*/
/* */
/*             /***/
/*              * Get the width of the container.*/
/*              *//* */
/*             function getContainerWidth()*/
/*             {*/
/*                 return query('#collector-content h2').clientWidth;*/
/*             }*/
/* */
/*             /***/
/*              * Draw one canvas.*/
/*              **/
/*              * @param request   the request object*/
/*              * @param max       <subjected for removal>*/
/*              * @param threshold the threshold (lower bound) of the length of the timeline (in milliseconds).*/
/*              * @param width     the width of the canvas.*/
/*              *//* */
/*             this.drawOne = function(request, max, threshold, width)*/
/*             {*/
/*                 "use strict";*/
/*                 var text,*/
/*                     ms,*/
/*                     xc,*/
/*                     drawableEvents,*/
/*                     mainEvents,*/
/*                     elementId = 'timeline_' + request.id,*/
/*                     canvasHeight = 0,*/
/*                     gapPerEvent = 38,*/
/*                     colors = _drawingColors,*/
/*                     space = 10.5,*/
/*                     ratio = (width - space * 2) / max,*/
/*                     h = space,*/
/*                     x = request.left * ratio + space, // position*/
/*                     canvas = cache.get(elementId) || cache.set(elementId, document.getElementById(elementId)),*/
/*                     ctx = canvas.getContext("2d"),*/
/*                     scaleRatio,*/
/*                     devicePixelRatio;*/
/* */
/*                 // Filter events whose total time is below the threshold.*/
/*                 drawableEvents = request.events.filter(function(event) {*/
/*                     return event.duration >= threshold;*/
/*                 });*/
/* */
/*                 canvasHeight += gapPerEvent * drawableEvents.length;*/
/* */
/*                 // For retina displays so text and boxes will be crisp*/
/*                 devicePixelRatio = window.devicePixelRatio == "undefined" ? 1 : window.devicePixelRatio;*/
/*                 scaleRatio = devicePixelRatio / 1;*/
/* */
/*                 canvas.width = width * scaleRatio;*/
/*                 canvas.height = canvasHeight * scaleRatio;*/
/* */
/*                 canvas.style.width = width + 'px';*/
/*                 canvas.style.height = canvasHeight + 'px';*/
/* */
/*                 ctx.scale(scaleRatio, scaleRatio);*/
/* */
/*                 ctx.textBaseline = "middle";*/
/*                 ctx.lineWidth = 0;*/
/* */
/*                 // For each event, draw a line.*/
/*                 ctx.strokeStyle = "#CCC";*/
/* */
/*                 drawableEvents.forEach(function(event) {*/
/*                     event.periods.forEach(function(period) {*/
/*                         var timelineHeadPosition = x + period.start * ratio;*/
/* */
/*                         if (isChildEvent(event)) {*/
/*                             /* create a striped background dynamically *//* */
/*                             var img = new Image();*/
/*                             img.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKBAMAAAB/HNKOAAAAIVBMVEX////w8PDd7h7d7h7d7h7d7h7w8PDw8PDw8PDw8PDw8PAOi84XAAAAKUlEQVQImWNI71zAwMBQMYuBgY0BxExnADErGEDMTgYQE8hnAKtCZwIAlcMNSR9a1OEAAAAASUVORK5CYII=';*/
/*                             var pattern = ctx.createPattern(img, 'repeat');*/
/* */
/*                             ctx.fillStyle = pattern;*/
/*                             ctx.fillRect(timelineHeadPosition, 0, (period.end - period.start) * ratio, canvasHeight);*/
/*                         } else if (isSectionEvent(event)) {*/
/*                             var timelineTailPosition = x + period.end * ratio;*/
/* */
/*                             ctx.beginPath();*/
/*                             ctx.moveTo(timelineHeadPosition, 0);*/
/*                             ctx.lineTo(timelineHeadPosition, canvasHeight);*/
/*                             ctx.moveTo(timelineTailPosition, 0);*/
/*                             ctx.lineTo(timelineTailPosition, canvasHeight);*/
/*                             ctx.fill();*/
/*                             ctx.closePath();*/
/*                             ctx.stroke();*/
/*                         }*/
/*                     });*/
/*                 });*/
/* */
/*                 // Filter for main events.*/
/*                 mainEvents = drawableEvents.filter(function(event) {*/
/*                     return !isChildEvent(event)*/
/*                 });*/
/* */
/*                 // For each main event, draw the visual presentation of timelines.*/
/*                 mainEvents.forEach(function(event) {*/
/* */
/*                     h += 8;*/
/* */
/*                     // For each sub event, ...*/
/*                     event.periods.forEach(function(period) {*/
/*                         // Set the drawing style.*/
/*                         ctx.fillStyle = colors['default'];*/
/*                         ctx.strokeStyle = colors['default'];*/
/* */
/*                         if (colors[event.name]) {*/
/*                             ctx.fillStyle = colors[event.name];*/
/*                             ctx.strokeStyle = colors[event.name];*/
/*                         } else if (colors[event.category]) {*/
/*                             ctx.fillStyle = colors[event.category];*/
/*                             ctx.strokeStyle = colors[event.category];*/
/*                         }*/
/* */
/*                         // Draw the timeline*/
/*                         var timelineHeadPosition = x + period.start * ratio;*/
/* */
/*                         if (!isSectionEvent(event)) {*/
/*                             ctx.fillRect(timelineHeadPosition, h + 3, 2, 8);*/
/*                             ctx.fillRect(timelineHeadPosition, h, (period.end - period.start) * ratio || 2, 6);*/
/*                         } else {*/
/*                             var timelineTailPosition = x + period.end * ratio;*/
/* */
/*                             ctx.beginPath();*/
/*                             ctx.moveTo(timelineHeadPosition, h);*/
/*                             ctx.lineTo(timelineHeadPosition, h + 11);*/
/*                             ctx.lineTo(timelineHeadPosition + 8, h);*/
/*                             ctx.lineTo(timelineHeadPosition, h);*/
/*                             ctx.fill();*/
/*                             ctx.closePath();*/
/*                             ctx.stroke();*/
/* */
/*                             ctx.beginPath();*/
/*                             ctx.moveTo(timelineTailPosition, h);*/
/*                             ctx.lineTo(timelineTailPosition, h + 11);*/
/*                             ctx.lineTo(timelineTailPosition - 8, h);*/
/*                             ctx.lineTo(timelineTailPosition, h);*/
/*                             ctx.fill();*/
/*                             ctx.closePath();*/
/*                             ctx.stroke();*/
/* */
/*                             ctx.beginPath();*/
/*                             ctx.moveTo(timelineHeadPosition, h);*/
/*                             ctx.lineTo(timelineTailPosition, h);*/
/*                             ctx.lineTo(timelineTailPosition, h + 2);*/
/*                             ctx.lineTo(timelineHeadPosition, h + 2);*/
/*                             ctx.lineTo(timelineHeadPosition, h);*/
/*                             ctx.fill();*/
/*                             ctx.closePath();*/
/*                             ctx.stroke();*/
/*                         }*/
/*                     });*/
/* */
/*                     h += 30;*/
/* */
/*                     ctx.beginPath();*/
/*                     ctx.strokeStyle = "#E0E0E0";*/
/*                     ctx.moveTo(0, h - 10);*/
/*                     ctx.lineTo(width, h - 10);*/
/*                     ctx.closePath();*/
/*                     ctx.stroke();*/
/*                 });*/
/* */
/*                 h = space;*/
/* */
/*                 // For each event, draw the label.*/
/*                 mainEvents.forEach(function(event) {*/
/* */
/*                     ctx.fillStyle = "#444";*/
/*                     ctx.font = "12px sans-serif";*/
/*                     text = event.name;*/
/*                     ms = "  " + (event.duration < 1 ? event.duration : parseInt(event.duration, 10)) + " ms / " + event.memory + " MB";*/
/*                     if (x + event.starttime * ratio + ctx.measureText(text + ms).width > width) {*/
/*                         ctx.textAlign = "end";*/
/*                         ctx.font = "10px sans-serif";*/
/*                         ctx.fillStyle = "#777";*/
/*                         xc = x + event.endtime * ratio - 1;*/
/*                         ctx.fillText(ms, xc, h);*/
/* */
/*                         xc -= ctx.measureText(ms).width;*/
/*                         ctx.font = "12px sans-serif";*/
/*                         ctx.fillStyle = "#222";*/
/*                         ctx.fillText(text, xc, h);*/
/*                     } else {*/
/*                         ctx.textAlign = "start";*/
/*                         ctx.font = "13px sans-serif";*/
/*                         ctx.fillStyle = "#222";*/
/*                         xc = x + event.starttime * ratio + 1;*/
/*                         ctx.fillText(text, xc, h);*/
/* */
/*                         xc += ctx.measureText(text).width;*/
/*                         ctx.font = "11px sans-serif";*/
/*                         ctx.fillStyle = "#777";*/
/*                         ctx.fillText(ms, xc, h);*/
/*                     }*/
/* */
/*                     h += gapPerEvent;*/
/*                 });*/
/*             };*/
/* */
/*             this.drawAll = function(width, threshold)*/
/*             {*/
/*                 "use strict";*/
/* */
/*                 width = width || getContainerWidth();*/
/*                 threshold = threshold || this.getThreshold();*/
/* */
/*                 var self = this;*/
/* */
/*                 _requests.forEach(function(request) {*/
/*                     self.drawOne(request, _maxRequestTime, threshold, width);*/
/*                 });*/
/*             };*/
/* */
/*             this.getThreshold = function() {*/
/*                 var threshold = Sfjs.getPreference(_storagePrefix + 'threshold');*/
/* */
/*                 if (null === threshold) {*/
/*                     return _threshold;*/
/*                 }*/
/* */
/*                 _threshold = parseInt(threshold);*/
/* */
/*                 return _threshold;*/
/*             };*/
/* */
/*             this.setThreshold = function(threshold)*/
/*             {*/
/*                 _threshold = threshold;*/
/* */
/*                 Sfjs.setPreference(_storagePrefix + 'threshold', threshold);*/
/* */
/*                 return this;*/
/*             };*/
/*         }*/
/* */
/*         function canvasAutoUpdateOnResizeAndSubmit(e) {*/
/*             e.preventDefault();*/
/*             canvasManager.drawAll();*/
/*         }*/
/* */
/*         function canvasAutoUpdateOnThresholdChange(e) {*/
/*             canvasManager*/
/*                 .setThreshold(query('input[name="threshold"]').value)*/
/*                 .drawAll();*/
/*         }*/
/* */
/*         var requests_data = {*/
/*             "max": {{ "%F"|format(collector.events.__section__.endtime) }},*/
/*             "requests": [*/
/* {{ helper.dump_request_data(token, profile, collector.events, collector.events.__section__.origin) }}*/
/* */
/* {% if profile.children|length %}*/
/*                 ,*/
/* {% for child in profile.children %}*/
/* {{ helper.dump_request_data(child.token, child, child.getcollector('time').events, collector.events.__section__.origin) }}{{ loop.last ? '' : ',' }}*/
/* {% endfor %}*/
/* {% endif %}*/
/*             ]*/
/*         };*/
/* */
/*         var canvasManager = new CanvasManager(requests_data.requests, requests_data.max);*/
/* */
/*         query('input[name="threshold"]').value = canvasManager.getThreshold();*/
/*         canvasManager.drawAll();*/
/* */
/*         // Update the colors of legends.*/
/*         var timelineLegends = document.querySelectorAll('.sf-profiler-timeline > .legends > span[data-color]');*/
/* */
/*         for (var i = 0; i < timelineLegends.length; ++i) {*/
/*             var timelineLegend = timelineLegends[i];*/
/* */
/*             timelineLegend.style.borderLeftColor = timelineLegend.getAttribute('data-color');*/
/*         }*/
/* */
/*         // Bind event handlers*/
/*         var elementTimelineControl = query('#timeline-control'),*/
/*             elementThresholdControl = query('input[name="threshold"]');*/
/* */
/*         window.onresize = canvasAutoUpdateOnResizeAndSubmit;*/
/*         elementTimelineControl.onsubmit = canvasAutoUpdateOnResizeAndSubmit;*/
/* */
/*         elementThresholdControl.onclick = canvasAutoUpdateOnThresholdChange;*/
/*         elementThresholdControl.onchange = canvasAutoUpdateOnThresholdChange;*/
/*         elementThresholdControl.onkeyup = canvasAutoUpdateOnThresholdChange;*/
/* */
/*         window.setTimeout(function() {*/
/*             canvasAutoUpdateOnThresholdChange(null);*/
/*         }, 50);*/
/* */
/*     //]]>{% endautoescape %}</script>*/
/* {% endblock %}*/
/* */
/* {% macro dump_request_data(token, profile, events, origin) %}*/
/* {% autoescape 'js' %}*/
/* {% from _self import dump_events %}*/
/*                 {*/
/*                     "id": "{{ token }}",*/
/*                     "left": {{ "%F"|format(events.__section__.origin - origin) }},*/
/*                     "events": [*/
/* {{ dump_events(events) }}*/
/*                     ]*/
/*                 }*/
/* {% endautoescape %}*/
/* {% endmacro %}*/
/* */
/* {% macro dump_events(events) %}*/
/* {% autoescape 'js' %}*/
/* {% for name, event in events %}*/
/* {% if '__section__' != name %}*/
/*                         {*/
/*                             "name": "{{ name }}",*/
/*                             "category": "{{ event.category }}",*/
/*                             "origin": {{ "%F"|format(event.origin) }},*/
/*                             "starttime": {{ "%F"|format(event.starttime) }},*/
/*                             "endtime": {{ "%F"|format(event.endtime) }},*/
/*                             "duration": {{ "%F"|format(event.duration) }},*/
/*                             "memory": {{ "%.1F"|format(event.memory / 1024 / 1024) }},*/
/*                             "periods": [*/
/*                                 {%- for period in event.periods -%}*/
/*                                     {"start": {{ "%F"|format(period.starttime) }}, "end": {{ "%F"|format(period.endtime) }}}{{ loop.last ? '' : ', ' }}*/
/*                                 {%- endfor -%}*/
/*                             ]*/
/*                         }{{ loop.last ? '' : ',' }}*/
/* {% endif %}*/
/* {% endfor %}*/
/* {% endautoescape %}*/
/* {% endmacro %}*/
/* */
/* {% macro display_timeline(id, events, colors) %}*/
/*     <div class="sf-profiler-timeline">*/
/*         <div class="legends">*/
/*             {% for category, color in colors %}*/
/*                 <span data-color="{{ color }}">{{ category }}</span>*/
/*             {% endfor %}*/
/*         </div>*/
/*         <canvas width="680" height="" id="{{ id }}" class="timeline"></canvas>*/
/*     </div>*/
/* {% endmacro %}*/
/* */
