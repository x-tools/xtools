<?php

/* @Swiftmailer/Collector/swiftmailer.html.twig */
class __TwigTemplate_b40302fef487bf429e16884107b5ea20823f3185534090680063b826c82aae1f extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@Swiftmailer/Collector/swiftmailer.html.twig", 1);
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
        $__internal_f3f0a74ed5c5853b0040a62bd95a844ed722afa74a494dca8e94a268b2dbccec = $this->env->getExtension("native_profiler");
        $__internal_f3f0a74ed5c5853b0040a62bd95a844ed722afa74a494dca8e94a268b2dbccec->enter($__internal_f3f0a74ed5c5853b0040a62bd95a844ed722afa74a494dca8e94a268b2dbccec_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@Swiftmailer/Collector/swiftmailer.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_f3f0a74ed5c5853b0040a62bd95a844ed722afa74a494dca8e94a268b2dbccec->leave($__internal_f3f0a74ed5c5853b0040a62bd95a844ed722afa74a494dca8e94a268b2dbccec_prof);

    }

    // line 3
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_db6eb0bba978d6befb3a6175dd6015391378e0cc21500d349b7a8eada767cb3b = $this->env->getExtension("native_profiler");
        $__internal_db6eb0bba978d6befb3a6175dd6015391378e0cc21500d349b7a8eada767cb3b->enter($__internal_db6eb0bba978d6befb3a6175dd6015391378e0cc21500d349b7a8eada767cb3b_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        // line 4
        echo "    ";
        $context["profiler_markup_version"] = ((array_key_exists("profiler_markup_version", $context)) ? (_twig_default_filter((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")), 1)) : (1));
        // line 5
        echo "
    ";
        // line 6
        if ($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array())) {
            // line 7
            echo "        ";
            ob_start();
            // line 8
            echo "            ";
            if (((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")) == 1)) {
                // line 9
                echo "                <img width=\"23\" height=\"28\" alt=\"Swiftmailer\" src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABcAAAAcCAYAAACK7SRjAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBNYWNpbnRvc2giIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6N0NEOTU1MjM0OThFMTFFMDg3NzJBNTE2ODgwQzMxMzQiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6N0NEOTU1MjQ0OThFMTFFMDg3NzJBNTE2ODgwQzMxMzQiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDoxMEQ5OTQ5QzQ5OEMxMUUwODc3MkE1MTY4ODBDMzEzNCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo3Q0Q5NTUyMjQ5OEUxMUUwODc3MkE1MTY4ODBDMzEzNCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PpkRnSAAAAJ0SURBVHjaYvz//z8DrQATAw3BqOFYAaO9vT1FBhw4cGCAXA5MipxBQUHT3r17l0AVAxkZ/wkLC89as2ZNIcjlYkALXKnlWqBZTH/+/PEDmQsynLW/v3+NoaHhN2oYDjJn8uTJK4BMNpDhPwsLCwOKiop2+fn5vafEYC8vrw8gc/Lz8wOB3B8gw/nev38vn5WV5eTg4LA/Ly/vESsrK2npmYmJITU19SnQ8L0gc4DxpwgyF2S4EEjB58+f+crLy31YWFjOt7S0XBYUFPxHjMEcHBz/6+rqboqJiZ0qKSnxBpkDlRICGc4MU/j792+2CRMm+L18+fLSxIkTDykoKPzBZ7CoqOi/np6eE8rKylvb29v9fvz4wYEkzYKRzjk5OX/LyMjcnDRpEkjjdisrK6wRraOj8wvokAMLFy788ejRoxcaGhrPCWai4ODgB8DUE3/mzBknYMToASNoMzAfvEVW4+Tk9LmhoWFbTU2NwunTpx2BjiiMjo6+hm4WCzJHUlLyz+vXrxkfP36sDOI/ffpUPikpibe7u3sLsJjQW7VqlSrQxe+Avjmanp7u9PbtWzGQOmCCkARmmu/m5uYfT548yY/V5UpKSl+2b9+uiiz26dMnIWBSDTp27NgdYGrYCIzwE7m5uR4wg2Hg/PnzSsDI/QlKOcjZ3wGUBLm5uf+DwLdv38gub4AG/xcSEvr35s0bZmCB5sgCE/z69SsjyDJKMtG/f/8YQQYD8wkoGf8H51AbG5sH1CpbQBnQ09PzBiiHgoIFFHlBQGwLxLxUMP8dqJgH4k3gIhfIkAKVYkDMTmmhCHIxEL8A4peMo02L4WU4QIABANxZAoDIQDv3AAAAAElFTkSuQmCC\" />
                <span class=\"sf-toolbar-status\">";
                // line 10
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array()), "html", null, true);
                echo "</span>
            ";
            } else {
                // line 12
                echo "                ";
                echo twig_include($this->env, $context, "@Swiftmailer/Collector/icon.svg");
                echo "
                <span class=\"sf-toolbar-value\">";
                // line 13
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array()), "html", null, true);
                echo "</span>
            ";
            }
            // line 15
            echo "        ";
            $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
            // line 16
            echo "
        ";
            // line 17
            ob_start();
            // line 18
            echo "            <div class=\"sf-toolbar-info-piece\">
                <b>Sent messages</b>
                <span class=\"sf-toolbar-status\">";
            // line 20
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array()), "html", null, true);
            echo "</span>
            </div>

            ";
            // line 23
            if (((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")) == 1)) {
                // line 24
                echo "                ";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array()));
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
                foreach ($context['_seq'] as $context["_key"] => $context["name"]) {
                    // line 25
                    echo "                    <div class=\"sf-toolbar-info-piece\">
                        <b>";
                    // line 26
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "</b>
                        <span>";
                    // line 27
                    echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array(0 => $context["name"]), "method"), "html", null, true);
                    echo "</span>
                    </div>
                    <div class=\"sf-toolbar-info-piece\">
                        <b>Is spooled?</b>
                        <span>";
                    // line 31
                    echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "isSpool", array(0 => $context["name"]), "method")) ? ("yes") : ("no"));
                    echo "</span>
                    </div>

                    ";
                    // line 34
                    if ( !$this->getAttribute($context["loop"], "first", array())) {
                        // line 35
                        echo "                        <hr>
                    ";
                    }
                    // line 37
                    echo "                ";
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
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['name'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 38
                echo "            ";
            } else {
                // line 39
                echo "                ";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array()));
                foreach ($context['_seq'] as $context["_key"] => $context["name"]) {
                    // line 40
                    echo "                    <div class=\"sf-toolbar-info-piece\">
                        <b>";
                    // line 41
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo " mailer</b>
                        <span class=\"sf-toolbar-status\">";
                    // line 42
                    echo twig_escape_filter($this->env, (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "messageCount", array(0 => $context["name"]), "method", true, true)) ? (_twig_default_filter($this->getAttribute((isset($context["collector"]) ? $context["collector"] : null), "messageCount", array(0 => $context["name"]), "method"), 0)) : (0)), "html", null, true);
                    echo "</span>
                        &nbsp; (<small>";
                    // line 43
                    echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "isSpool", array(0 => $context["name"]), "method")) ? ("spooled") : ("sent"));
                    echo "</small>)
                    </div>
                ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['name'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 46
                echo "            ";
            }
            // line 47
            echo "        ";
            $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
            // line 48
            echo "
        ";
            // line 49
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", array("link" => (isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url"))));
            echo "
    ";
        }
        
        $__internal_db6eb0bba978d6befb3a6175dd6015391378e0cc21500d349b7a8eada767cb3b->leave($__internal_db6eb0bba978d6befb3a6175dd6015391378e0cc21500d349b7a8eada767cb3b_prof);

    }

    // line 53
    public function block_menu($context, array $blocks = array())
    {
        $__internal_5f02e08cdb204c46f91718bc80850256bae68a45afb7c8ebd854f31535dace44 = $this->env->getExtension("native_profiler");
        $__internal_5f02e08cdb204c46f91718bc80850256bae68a45afb7c8ebd854f31535dace44->enter($__internal_5f02e08cdb204c46f91718bc80850256bae68a45afb7c8ebd854f31535dace44_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "menu"));

        // line 54
        echo "    ";
        $context["profiler_markup_version"] = ((array_key_exists("profiler_markup_version", $context)) ? (_twig_default_filter((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")), 1)) : (1));
        // line 55
        echo "
    <span class=\"label ";
        // line 56
        echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array())) ? ("") : ("disabled"));
        echo "\">
        ";
        // line 57
        if (((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")) == 1)) {
            // line 58
            echo "            <span class=\"icon\"><img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACoAAAAeCAYAAABaKIzgAAAEDElEQVR42u2XWUhUURjHy6isILAebO+lonpJ8qkHM3NFVFxAUVFM0RSXUKnwwdAQn3wQE0MyA1MwBEUQcxvHZTTTHNcZl9DUGqd0JBOzcsZ7+n8XJ0Z0GueOVwg68GfmnLn3O7/7Lee7s4cxZpHq6uos0bb3+Q+6DrG3u7v7RHt7u3tbW9uD5ubm8qamJnlDQ4NKIpG8HhkZOU3X7BYoD9Tb22sjk8mcWltb70ul0pcAegugBfzOjKmzs/MJ7j0kCujw8PBhADkAKAVAz+EZGYA+08bmCN79qdFo7sGmjaWg+wgIIUtoaWl5CqDmxsbGj7SJpYK3uYWFBRnsDmEfWyGg+zs6OkIBNEoGxVB9fT2bnZ1VIHff03xmZuY29rUyF9QWT6sWC5I0OTk5rVAo3unnSqXyEfa1Nhf0Kp5UKRYk8lsDD0oM1/r6+l5h32Pmgl5UqVTP5ubmlEgBHRlCobC8vDzm5eXFHB0dRZWzs/OXsLCwu5SCpkBPo4DaMVRI9rbp6Wk1vnP+/v5kaFfk4eGhAcdJU6Dn+/v7q9aTnpPL5YqVlRV5eXm5Fk+7Gx7lCgsL68Fx2RToWST7C8McQgr8yMrKWkLu/hQz/LDNIZojycnJb8BxwRTocYT8oSEoQs8bSkpK0k5MTGgiIiK4nYYMDg7mcBLIo6OjP9Ec44opUGvIF+eoTg/a1dX1x2BISMgyKncpLS1tbacgU1NT2djY2HxoaOi8fg3jhilQK+gmQvBVD4qQbzDs4+ND6bBWUFCgtRQyJyeHdwSKdcODY9zaTgu9jheMcWOgJFdXV1ZZWclqamp0bm5uQoqGVVRUrFHBuru782tCQC+hW0j/BkpCPlGXIY9wfn5+5hQN5T3dS+cz5btg0DNTU1NFpkBra2tZaWkpy8jIYOPj4ywmJmY7RcMGBwdZZmYmKykpoa7ELPGozeLiYrIetKenZ5OhuLg4ft3T05OfJyQk8LDp6el/LRq6JiUlheb8vXgzY7m5uYJBD0LeeDnRApQ8sKloqK3GxsZuWEPrYzhiWHFx8ZZFMzo6yiIjIw1DTTZ+qNXqMRcXF0GgVpADKltDoCisDcbj4+NZfn7+ll5D9fKeprYbFRXFwsPDWVVV1SodPwEBAVueEtnZ2QNIhTkhoKRrAxhb5WhRURFzcnIyGmIcX9rq6uoPq6urAzqdrresrGwIHtMZux62OOT6AD4FgZ5bXl5+DMhv6P16j/KhCwoK2vHO5O3t/SsxMfG7ENAjkAOUBUkMvMVDiiFKDSGge6Gj0CUoGmffpvwSEfg7dUch/0LtkWcdvr6+a2JDBgYG6tDt6DXPTgjoKegOVAo1QVKR1AgVQ8GQrRDQA+uw9ushuSWSyLYdQRr7K/JP6DcTwr8i7Fj8pwAAAABJRU5ErkJggg==\" alt=\"Swiftmailer\" /></span>
        ";
        } else {
            // line 60
            echo "            <span class=\"icon\">";
            echo twig_include($this->env, $context, "@Swiftmailer/Collector/icon.svg");
            echo "</span>
        ";
        }
        // line 62
        echo "
        <strong>E-Mails</strong>
        ";
        // line 64
        if (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array()) > 0)) {
            // line 65
            echo "            <span class=\"count\">
                <span>";
            // line 66
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array()), "html", null, true);
            echo "</span>
            </span>
        ";
        }
        // line 69
        echo "    </span>
";
        
        $__internal_5f02e08cdb204c46f91718bc80850256bae68a45afb7c8ebd854f31535dace44->leave($__internal_5f02e08cdb204c46f91718bc80850256bae68a45afb7c8ebd854f31535dace44_prof);

    }

    // line 72
    public function block_panel($context, array $blocks = array())
    {
        $__internal_5112d399afdbb08eeeb80e959ed510e25f4c9ecd42c82b5adbcf92be59c60a8e = $this->env->getExtension("native_profiler");
        $__internal_5112d399afdbb08eeeb80e959ed510e25f4c9ecd42c82b5adbcf92be59c60a8e->enter($__internal_5112d399afdbb08eeeb80e959ed510e25f4c9ecd42c82b5adbcf92be59c60a8e_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panel"));

        // line 73
        echo "    ";
        $context["profiler_markup_version"] = ((array_key_exists("profiler_markup_version", $context)) ? (_twig_default_filter((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")), 1)) : (1));
        // line 74
        echo "
    ";
        // line 75
        if (((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")) == 1)) {
            // line 76
            echo "        <style>
            h3 { margin-top: 2em; }
            h3 span { color: #999; font-weight: normal; }
            h3 small { color: #999; }
            h4 { font-size: 14px; font-weight: bold; }
            .card { background: #F5F5F5; margin: .5em 0 1em; padding: .5em; }
            .card .label { display: block; font-size: 13px; font-weight: bold; margin-bottom: .5em; }
            .card .card-block { margin-bottom: 1em; }
        </style>
    ";
        }
        // line 86
        echo "
    <h2>E-mails</h2>

    ";
        // line 89
        if ( !$this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array())) {
            // line 90
            echo "        <div class=\"empty\">
            <p>No e-mail messages were sent.</p>
        </div>
    ";
        }
        // line 94
        echo "
    ";
        // line 95
        if ((((isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")) == 1) || (twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array())) > 1))) {
            // line 96
            echo "        <table>
            <thead>
                <tr>
                    <th scope=\"col\">Mailer Name</th>
                    <th scope=\"col\">Num. of messages</th>
                    <th scope=\"col\">Messages status</th>
                    <th scope=\"col\">Notes</th>
                </tr>
            </thead>
            <tbody>
                ";
            // line 106
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array()));
            foreach ($context['_seq'] as $context["_key"] => $context["name"]) {
                // line 107
                echo "                    <tr>
                        <th class=\"font-normal\">";
                // line 108
                echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                echo "</th>
                        <td class=\"font-normal\">";
                // line 109
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array(0 => $context["name"]), "method"), "html", null, true);
                echo "</td>
                        <td class=\"font-normal\">";
                // line 110
                echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "isSpool", array(0 => $context["name"]), "method")) ? ("spooled") : ("sent"));
                echo "</td>
                        <td class=\"font-normal\">";
                // line 111
                echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "isDefaultMailer", array(0 => $context["name"]), "method")) ? ("This is the default mailer") : (""));
                echo "</td>
                    </tr>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['name'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 114
            echo "            </tbody>
        </table>
    ";
        } else {
            // line 117
            echo "        <div class=\"metrics\">
            ";
            // line 118
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array()));
            foreach ($context['_seq'] as $context["_key"] => $context["name"]) {
                // line 119
                echo "                <div class=\"metric\">
                    <span class=\"value\">";
                // line 120
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array(0 => $context["name"]), "method"), "html", null, true);
                echo "</span>
                    <span class=\"label\">";
                // line 121
                echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "isSpool", array(0 => $context["name"]), "method")) ? ("spooled") : ("sent"));
                echo " ";
                echo ((($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messageCount", array(0 => $context["name"]), "method") == 1)) ? ("message") : ("messages"));
                echo "</span>
                </div>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['name'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 124
            echo "        </div>
    ";
        }
        // line 126
        echo "
    ";
        // line 127
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array()));
        foreach ($context['_seq'] as $context["_key"] => $context["name"]) {
            // line 128
            echo "        ";
            if ((twig_length_filter($this->env, $this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "mailers", array())) > 1)) {
                // line 129
                echo "            <h3>
                ";
                // line 130
                echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                echo " <span>mailer</span>
                <small>";
                // line 131
                echo (($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "isDefaultMailer", array(0 => $context["name"]), "method")) ? ("(default app mailer)") : (""));
                echo "</small>
            </h3>
        ";
            }
            // line 134
            echo "
        ";
            // line 135
            if ( !$this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messages", array(0 => $context["name"]), "method")) {
                // line 136
                echo "            <div class=\"empty\">
                <p>No e-mail messages were sent.</p>
            </div>
        ";
            } else {
                // line 140
                echo "            ";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "messages", array(0 => $context["name"]), "method"));
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
                foreach ($context['_seq'] as $context["_key"] => $context["message"]) {
                    // line 141
                    echo "                ";
                    if (($this->getAttribute($context["loop"], "length", array()) > 1)) {
                        // line 142
                        echo "                    <h4>E-mail #";
                        echo twig_escape_filter($this->env, $this->getAttribute($context["loop"], "index", array()), "html", null, true);
                        echo " details</h4>
                ";
                    } else {
                        // line 144
                        echo "                    <h4>E-mail details</h4>
                ";
                    }
                    // line 146
                    echo "
                <div class=\"card\">
                    <div class=\"card-block\">
                        <span class=\"label\">Message headers</span>
                        <pre>";
                    // line 150
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable($this->getAttribute($this->getAttribute($context["message"], "headers", array()), "all", array()));
                    foreach ($context['_seq'] as $context["_key"] => $context["header"]) {
                        // line 151
                        echo twig_escape_filter($this->env, $context["header"], "html", null, true);
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['header'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 152
                    echo "</pre>
                    </div>

                    <div class=\"card-block\">
                        <span class=\"label\">Message body</span>
                        <pre>";
                    // line 158
                    if (($this->getAttribute((isset($context["messagePart"]) ? $context["messagePart"] : null), "charset", array(), "any", true, true) && $this->getAttribute($context["message"], "charset", array()))) {
                        // line 159
                        echo twig_escape_filter($this->env, twig_convert_encoding($this->getAttribute($context["message"], "body", array()), "UTF-8", $this->getAttribute($context["message"], "charset", array())), "html", null, true);
                    } else {
                        // line 161
                        echo twig_escape_filter($this->env, $this->getAttribute($context["message"], "body", array()), "html", null, true);
                    }
                    // line 163
                    echo "</pre>
                    </div>

                    ";
                    // line 166
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable($this->getAttribute($context["message"], "children", array()));
                    foreach ($context['_seq'] as $context["_key"] => $context["messagePart"]) {
                        if (twig_in_filter($this->getAttribute($context["messagePart"], "contentType", array()), array(0 => "text/plain", 1 => "text/html"))) {
                            // line 167
                            echo "                        <div class=\"card-block\">
                            <span class=\"label\">Alternative part (";
                            // line 168
                            echo twig_escape_filter($this->env, $this->getAttribute($context["messagePart"], "contentType", array()), "html", null, true);
                            echo ")</span>
                            <pre>";
                            // line 170
                            if ($this->getAttribute($context["messagePart"], "charset", array())) {
                                // line 171
                                echo twig_escape_filter($this->env, twig_convert_encoding($this->getAttribute($context["messagePart"], "body", array()), "UTF-8", $this->getAttribute($context["messagePart"], "charset", array())), "html", null, true);
                            } else {
                                // line 173
                                echo twig_escape_filter($this->env, $this->getAttribute($context["messagePart"], "body", array()), "html", null, true);
                            }
                            // line 175
                            echo "</pre>
                        </div>
                    ";
                        }
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['messagePart'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 178
                    echo "                </div>
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
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['message'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 180
                echo "        ";
            }
            // line 181
            echo "    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['name'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        
        $__internal_5112d399afdbb08eeeb80e959ed510e25f4c9ecd42c82b5adbcf92be59c60a8e->leave($__internal_5112d399afdbb08eeeb80e959ed510e25f4c9ecd42c82b5adbcf92be59c60a8e_prof);

    }

    public function getTemplateName()
    {
        return "@Swiftmailer/Collector/swiftmailer.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  516 => 181,  513 => 180,  498 => 178,  489 => 175,  486 => 173,  483 => 171,  481 => 170,  477 => 168,  474 => 167,  469 => 166,  464 => 163,  461 => 161,  458 => 159,  456 => 158,  449 => 152,  443 => 151,  439 => 150,  433 => 146,  429 => 144,  423 => 142,  420 => 141,  402 => 140,  396 => 136,  394 => 135,  391 => 134,  385 => 131,  381 => 130,  378 => 129,  375 => 128,  371 => 127,  368 => 126,  364 => 124,  353 => 121,  349 => 120,  346 => 119,  342 => 118,  339 => 117,  334 => 114,  325 => 111,  321 => 110,  317 => 109,  313 => 108,  310 => 107,  306 => 106,  294 => 96,  292 => 95,  289 => 94,  283 => 90,  281 => 89,  276 => 86,  264 => 76,  262 => 75,  259 => 74,  256 => 73,  250 => 72,  242 => 69,  236 => 66,  233 => 65,  231 => 64,  227 => 62,  221 => 60,  217 => 58,  215 => 57,  211 => 56,  208 => 55,  205 => 54,  199 => 53,  189 => 49,  186 => 48,  183 => 47,  180 => 46,  171 => 43,  167 => 42,  163 => 41,  160 => 40,  155 => 39,  152 => 38,  138 => 37,  134 => 35,  132 => 34,  126 => 31,  119 => 27,  115 => 26,  112 => 25,  94 => 24,  92 => 23,  86 => 20,  82 => 18,  80 => 17,  77 => 16,  74 => 15,  69 => 13,  64 => 12,  59 => 10,  56 => 9,  53 => 8,  50 => 7,  48 => 6,  45 => 5,  42 => 4,  36 => 3,  11 => 1,);
    }
}
/* {% extends '@WebProfiler/Profiler/layout.html.twig' %}*/
/* */
/* {% block toolbar %}*/
/*     {% set profiler_markup_version = profiler_markup_version|default(1) %}*/
/* */
/*     {% if collector.messageCount %}*/
/*         {% set icon %}*/
/*             {% if profiler_markup_version == 1 %}*/
/*                 <img width="23" height="28" alt="Swiftmailer" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABcAAAAcCAYAAACK7SRjAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBNYWNpbnRvc2giIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6N0NEOTU1MjM0OThFMTFFMDg3NzJBNTE2ODgwQzMxMzQiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6N0NEOTU1MjQ0OThFMTFFMDg3NzJBNTE2ODgwQzMxMzQiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDoxMEQ5OTQ5QzQ5OEMxMUUwODc3MkE1MTY4ODBDMzEzNCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo3Q0Q5NTUyMjQ5OEUxMUUwODc3MkE1MTY4ODBDMzEzNCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PpkRnSAAAAJ0SURBVHjaYvz//z8DrQATAw3BqOFYAaO9vT1FBhw4cGCAXA5MipxBQUHT3r17l0AVAxkZ/wkLC89as2ZNIcjlYkALXKnlWqBZTH/+/PEDmQsynLW/v3+NoaHhN2oYDjJn8uTJK4BMNpDhPwsLCwOKiop2+fn5vafEYC8vrw8gc/Lz8wOB3B8gw/nev38vn5WV5eTg4LA/Ly/vESsrK2npmYmJITU19SnQ8L0gc4DxpwgyF2S4EEjB58+f+crLy31YWFjOt7S0XBYUFPxHjMEcHBz/6+rqboqJiZ0qKSnxBpkDlRICGc4MU/j792+2CRMm+L18+fLSxIkTDykoKPzBZ7CoqOi/np6eE8rKylvb29v9fvz4wYEkzYKRzjk5OX/LyMjcnDRpEkjjdisrK6wRraOj8wvokAMLFy788ejRoxcaGhrPCWai4ODgB8DUE3/mzBknYMToASNoMzAfvEVW4+Tk9LmhoWFbTU2NwunTpx2BjiiMjo6+hm4WCzJHUlLyz+vXrxkfP36sDOI/ffpUPikpibe7u3sLsJjQW7VqlSrQxe+Avjmanp7u9PbtWzGQOmCCkARmmu/m5uYfT548yY/V5UpKSl+2b9+uiiz26dMnIWBSDTp27NgdYGrYCIzwE7m5uR4wg2Hg/PnzSsDI/QlKOcjZ3wGUBLm5uf+DwLdv38gub4AG/xcSEvr35s0bZmCB5sgCE/z69SsjyDJKMtG/f/8YQQYD8wkoGf8H51AbG5sH1CpbQBnQ09PzBiiHgoIFFHlBQGwLxLxUMP8dqJgH4k3gIhfIkAKVYkDMTmmhCHIxEL8A4peMo02L4WU4QIABANxZAoDIQDv3AAAAAElFTkSuQmCC" />*/
/*                 <span class="sf-toolbar-status">{{ collector.messageCount }}</span>*/
/*             {% else %}*/
/*                 {{ include('@Swiftmailer/Collector/icon.svg') }}*/
/*                 <span class="sf-toolbar-value">{{ collector.messageCount }}</span>*/
/*             {% endif %}*/
/*         {% endset %}*/
/* */
/*         {% set text %}*/
/*             <div class="sf-toolbar-info-piece">*/
/*                 <b>Sent messages</b>*/
/*                 <span class="sf-toolbar-status">{{ collector.messageCount }}</span>*/
/*             </div>*/
/* */
/*             {% if profiler_markup_version == 1 %}*/
/*                 {% for name in collector.mailers %}*/
/*                     <div class="sf-toolbar-info-piece">*/
/*                         <b>{{ name }}</b>*/
/*                         <span>{{ collector.messageCount(name) }}</span>*/
/*                     </div>*/
/*                     <div class="sf-toolbar-info-piece">*/
/*                         <b>Is spooled?</b>*/
/*                         <span>{{ collector.isSpool(name) ? 'yes' : 'no' }}</span>*/
/*                     </div>*/
/* */
/*                     {% if not loop.first %}*/
/*                         <hr>*/
/*                     {% endif %}*/
/*                 {% endfor %}*/
/*             {% else %}*/
/*                 {% for name in collector.mailers %}*/
/*                     <div class="sf-toolbar-info-piece">*/
/*                         <b>{{ name }} mailer</b>*/
/*                         <span class="sf-toolbar-status">{{ collector.messageCount(name)|default(0) }}</span>*/
/*                         &nbsp; (<small>{{ collector.isSpool(name) ? 'spooled' : 'sent' }}</small>)*/
/*                     </div>*/
/*                 {% endfor %}*/
/*             {% endif %}*/
/*         {% endset %}*/
/* */
/*         {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { 'link': profiler_url }) }}*/
/*     {% endif %}*/
/* {% endblock %}*/
/* */
/* {% block menu %}*/
/*     {% set profiler_markup_version = profiler_markup_version|default(1) %}*/
/* */
/*     <span class="label {{ collector.messageCount ? '' : 'disabled' }}">*/
/*         {% if profiler_markup_version == 1 %}*/
/*             <span class="icon"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACoAAAAeCAYAAABaKIzgAAAEDElEQVR42u2XWUhUURjHy6isILAebO+lonpJ8qkHM3NFVFxAUVFM0RSXUKnwwdAQn3wQE0MyA1MwBEUQcxvHZTTTHNcZl9DUGqd0JBOzcsZ7+n8XJ0Z0GueOVwg68GfmnLn3O7/7Lee7s4cxZpHq6uos0bb3+Q+6DrG3u7v7RHt7u3tbW9uD5ubm8qamJnlDQ4NKIpG8HhkZOU3X7BYoD9Tb22sjk8mcWltb70ul0pcAegugBfzOjKmzs/MJ7j0kCujw8PBhADkAKAVAz+EZGYA+08bmCN79qdFo7sGmjaWg+wgIIUtoaWl5CqDmxsbGj7SJpYK3uYWFBRnsDmEfWyGg+zs6OkIBNEoGxVB9fT2bnZ1VIHff03xmZuY29rUyF9QWT6sWC5I0OTk5rVAo3unnSqXyEfa1Nhf0Kp5UKRYk8lsDD0oM1/r6+l5h32Pmgl5UqVTP5ubmlEgBHRlCobC8vDzm5eXFHB0dRZWzs/OXsLCwu5SCpkBPo4DaMVRI9rbp6Wk1vnP+/v5kaFfk4eGhAcdJU6Dn+/v7q9aTnpPL5YqVlRV5eXm5Fk+7Gx7lCgsL68Fx2RToWST7C8McQgr8yMrKWkLu/hQz/LDNIZojycnJb8BxwRTocYT8oSEoQs8bSkpK0k5MTGgiIiK4nYYMDg7mcBLIo6OjP9Ec44opUGvIF+eoTg/a1dX1x2BISMgyKncpLS1tbacgU1NT2djY2HxoaOi8fg3jhilQK+gmQvBVD4qQbzDs4+ND6bBWUFCgtRQyJyeHdwSKdcODY9zaTgu9jheMcWOgJFdXV1ZZWclqamp0bm5uQoqGVVRUrFHBuru782tCQC+hW0j/BkpCPlGXIY9wfn5+5hQN5T3dS+cz5btg0DNTU1NFpkBra2tZaWkpy8jIYOPj4ywmJmY7RcMGBwdZZmYmKykpoa7ELPGozeLiYrIetKenZ5OhuLg4ft3T05OfJyQk8LDp6el/LRq6JiUlheb8vXgzY7m5uYJBD0LeeDnRApQ8sKloqK3GxsZuWEPrYzhiWHFx8ZZFMzo6yiIjIw1DTTZ+qNXqMRcXF0GgVpADKltDoCisDcbj4+NZfn7+ll5D9fKeprYbFRXFwsPDWVVV1SodPwEBAVueEtnZ2QNIhTkhoKRrAxhb5WhRURFzcnIyGmIcX9rq6uoPq6urAzqdrresrGwIHtMZux62OOT6AD4FgZ5bXl5+DMhv6P16j/KhCwoK2vHO5O3t/SsxMfG7ENAjkAOUBUkMvMVDiiFKDSGge6Gj0CUoGmffpvwSEfg7dUch/0LtkWcdvr6+a2JDBgYG6tDt6DXPTgjoKegOVAo1QVKR1AgVQ8GQrRDQA+uw9ushuSWSyLYdQRr7K/JP6DcTwr8i7Fj8pwAAAABJRU5ErkJggg==" alt="Swiftmailer" /></span>*/
/*         {% else %}*/
/*             <span class="icon">{{ include('@Swiftmailer/Collector/icon.svg') }}</span>*/
/*         {% endif %}*/
/* */
/*         <strong>E-Mails</strong>*/
/*         {% if collector.messageCount > 0 %}*/
/*             <span class="count">*/
/*                 <span>{{ collector.messageCount }}</span>*/
/*             </span>*/
/*         {% endif %}*/
/*     </span>*/
/* {% endblock %}*/
/* */
/* {% block panel %}*/
/*     {% set profiler_markup_version = profiler_markup_version|default(1) %}*/
/* */
/*     {% if profiler_markup_version == 1 %}*/
/*         <style>*/
/*             h3 { margin-top: 2em; }*/
/*             h3 span { color: #999; font-weight: normal; }*/
/*             h3 small { color: #999; }*/
/*             h4 { font-size: 14px; font-weight: bold; }*/
/*             .card { background: #F5F5F5; margin: .5em 0 1em; padding: .5em; }*/
/*             .card .label { display: block; font-size: 13px; font-weight: bold; margin-bottom: .5em; }*/
/*             .card .card-block { margin-bottom: 1em; }*/
/*         </style>*/
/*     {% endif %}*/
/* */
/*     <h2>E-mails</h2>*/
/* */
/*     {% if not collector.mailers %}*/
/*         <div class="empty">*/
/*             <p>No e-mail messages were sent.</p>*/
/*         </div>*/
/*     {% endif %}*/
/* */
/*     {% if profiler_markup_version == 1 or collector.mailers|length > 1 %}*/
/*         <table>*/
/*             <thead>*/
/*                 <tr>*/
/*                     <th scope="col">Mailer Name</th>*/
/*                     <th scope="col">Num. of messages</th>*/
/*                     <th scope="col">Messages status</th>*/
/*                     <th scope="col">Notes</th>*/
/*                 </tr>*/
/*             </thead>*/
/*             <tbody>*/
/*                 {% for name in collector.mailers %}*/
/*                     <tr>*/
/*                         <th class="font-normal">{{ name }}</th>*/
/*                         <td class="font-normal">{{ collector.messageCount(name) }}</td>*/
/*                         <td class="font-normal">{{ collector.isSpool(name) ? 'spooled' : 'sent' }}</td>*/
/*                         <td class="font-normal">{{ collector.isDefaultMailer(name) ? 'This is the default mailer' }}</td>*/
/*                     </tr>*/
/*                 {% endfor %}*/
/*             </tbody>*/
/*         </table>*/
/*     {% else %}*/
/*         <div class="metrics">*/
/*             {% for name in collector.mailers %}*/
/*                 <div class="metric">*/
/*                     <span class="value">{{ collector.messageCount(name) }}</span>*/
/*                     <span class="label">{{ collector.isSpool(name) ? 'spooled' : 'sent' }} {{ collector.messageCount(name) == 1 ? 'message' : 'messages' }}</span>*/
/*                 </div>*/
/*             {% endfor %}*/
/*         </div>*/
/*     {% endif %}*/
/* */
/*     {% for name in collector.mailers %}*/
/*         {% if collector.mailers|length > 1 %}*/
/*             <h3>*/
/*                 {{ name }} <span>mailer</span>*/
/*                 <small>{{ collector.isDefaultMailer(name) ? '(default app mailer)' }}</small>*/
/*             </h3>*/
/*         {% endif %}*/
/* */
/*         {% if not collector.messages(name) %}*/
/*             <div class="empty">*/
/*                 <p>No e-mail messages were sent.</p>*/
/*             </div>*/
/*         {% else %}*/
/*             {% for message in collector.messages(name) %}*/
/*                 {% if loop.length > 1 %}*/
/*                     <h4>E-mail #{{ loop.index }} details</h4>*/
/*                 {% else %}*/
/*                     <h4>E-mail details</h4>*/
/*                 {% endif %}*/
/* */
/*                 <div class="card">*/
/*                     <div class="card-block">*/
/*                         <span class="label">Message headers</span>*/
/*                         <pre>{% for header in message.headers.all %}*/
/*                             {{- header -}}*/
/*                         {% endfor %}</pre>*/
/*                     </div>*/
/* */
/*                     <div class="card-block">*/
/*                         <span class="label">Message body</span>*/
/*                         <pre>*/
/*                             {%- if messagePart.charset is defined and message.charset %}*/
/*                                 {{- message.body|convert_encoding('UTF-8', message.charset) }}*/
/*                             {%- else %}*/
/*                                 {{- message.body }}*/
/*                             {%- endif -%}*/
/*                         </pre>*/
/*                     </div>*/
/* */
/*                     {% for messagePart in message.children if messagePart.contentType in ['text/plain', 'text/html'] %}*/
/*                         <div class="card-block">*/
/*                             <span class="label">Alternative part ({{ messagePart.contentType }})</span>*/
/*                             <pre>*/
/*                                 {%- if messagePart.charset %}*/
/*                                     {{- messagePart.body|convert_encoding('UTF-8', messagePart.charset) }}*/
/*                                 {%- else %}*/
/*                                     {{- messagePart.body }}*/
/*                                 {%- endif -%}*/
/*                             </pre>*/
/*                         </div>*/
/*                     {% endfor %}*/
/*                 </div>*/
/*             {% endfor %}*/
/*         {% endif %}*/
/*     {% endfor %}*/
/* {% endblock %}*/
/* */
