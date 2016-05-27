<?php

/* @WebProfiler/Collector/form.html.twig */
class __TwigTemplate_e6c7ea1887439d70a9279aa65c20c3fc65344d53c6db89d9f21532f70dd7e700 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/form.html.twig", 1);
        $this->blocks = array(
            'toolbar' => array($this, 'block_toolbar'),
            'menu' => array($this, 'block_menu'),
            'head' => array($this, 'block_head'),
            'panel' => array($this, 'block_panel'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "@WebProfiler/Profiler/layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_6bf76cfd587c114d81c4b50ca41343c0e1bb321d2f18ca6829df2a296b9636de = $this->env->getExtension("native_profiler");
        $__internal_6bf76cfd587c114d81c4b50ca41343c0e1bb321d2f18ca6829df2a296b9636de->enter($__internal_6bf76cfd587c114d81c4b50ca41343c0e1bb321d2f18ca6829df2a296b9636de_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/form.html.twig"));

        // line 3
        $context["__internal_8368653d2dc67995758bb059918465c24de409fe3717b5367c0e2cf630e0f955"] = $this;
        // line 1
        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_6bf76cfd587c114d81c4b50ca41343c0e1bb321d2f18ca6829df2a296b9636de->leave($__internal_6bf76cfd587c114d81c4b50ca41343c0e1bb321d2f18ca6829df2a296b9636de_prof);

    }

    // line 5
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_789ea6dcfcba9a6e1d476033e935c3c471e87935249a33679537367655b3e393 = $this->env->getExtension("native_profiler");
        $__internal_789ea6dcfcba9a6e1d476033e935c3c471e87935249a33679537367655b3e393->enter($__internal_789ea6dcfcba9a6e1d476033e935c3c471e87935249a33679537367655b3e393_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        // line 6
        echo "    ";
        if ((($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array()) > 0) || twig_length_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array())))) {
            // line 7
            echo "        ";
            $context["status_color"] = (($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array())) ? ("red") : (""));
            // line 8
            echo "        ";
            ob_start();
            // line 9
            echo "            ";
            echo twig_include($this->env, $context, "@WebProfiler/Icon/form.svg");
            echo "
            <span class=\"sf-toolbar-value\">
                ";
            // line 11
            echo twig_escape_filter($this->env, (($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array())) ? ($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array())) : (twig_length_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array())))), "html", null, true);
            echo "
            </span>
        ";
            $context["icon"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
            // line 14
            echo "
        ";
            // line 15
            ob_start();
            // line 16
            echo "            <div class=\"sf-toolbar-info-piece\">
                <b>Number of forms</b>
                <span class=\"sf-toolbar-status\">";
            // line 18
            echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array())), "html", null, true);
            echo "</span>
            </div>
            <div class=\"sf-toolbar-info-piece\">
                <b>Number of errors</b>
                <span class=\"sf-toolbar-status sf-toolbar-status-";
            // line 22
            echo ((($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array()) > 0)) ? ("red") : (""));
            echo "\">";
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array()), "html", null, true);
            echo "</span>
            </div>
        ";
            $context["text"] = ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
            // line 25
            echo "
        ";
            // line 26
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar_item.html.twig", array("link" => (isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url")), "status" => (isset($context["status_color"]) ? $context["status_color"] : $this->getContext($context, "status_color"))));
            echo "
    ";
        }
        
        $__internal_789ea6dcfcba9a6e1d476033e935c3c471e87935249a33679537367655b3e393->leave($__internal_789ea6dcfcba9a6e1d476033e935c3c471e87935249a33679537367655b3e393_prof);

    }

    // line 30
    public function block_menu($context, array $blocks = array())
    {
        $__internal_bb5f7ce4bfc7ee1750721494499aba0373bb1be12da7bac515cf6bacc37bb943 = $this->env->getExtension("native_profiler");
        $__internal_bb5f7ce4bfc7ee1750721494499aba0373bb1be12da7bac515cf6bacc37bb943->enter($__internal_bb5f7ce4bfc7ee1750721494499aba0373bb1be12da7bac515cf6bacc37bb943_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "menu"));

        // line 31
        echo "    <span class=\"label label-status-";
        echo (($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array())) ? ("error") : (""));
        echo " ";
        echo ((twig_test_empty($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array()))) ? ("disabled") : (""));
        echo "\">
        <span class=\"icon\">";
        // line 32
        echo twig_include($this->env, $context, "@WebProfiler/Icon/form.svg");
        echo "</span>
        <strong>Forms</strong>
        ";
        // line 34
        if (($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array()) > 0)) {
            // line 35
            echo "            <span class=\"count\">
                <span>";
            // line 36
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "nb_errors", array()), "html", null, true);
            echo "</span>
            </span>
        ";
        }
        // line 39
        echo "    </span>
";
        
        $__internal_bb5f7ce4bfc7ee1750721494499aba0373bb1be12da7bac515cf6bacc37bb943->leave($__internal_bb5f7ce4bfc7ee1750721494499aba0373bb1be12da7bac515cf6bacc37bb943_prof);

    }

    // line 42
    public function block_head($context, array $blocks = array())
    {
        $__internal_64f4aa6b9c3e3d7673785d468dbc40aa4953bde3edba11d0e21637a95440626c = $this->env->getExtension("native_profiler");
        $__internal_64f4aa6b9c3e3d7673785d468dbc40aa4953bde3edba11d0e21637a95440626c->enter($__internal_64f4aa6b9c3e3d7673785d468dbc40aa4953bde3edba11d0e21637a95440626c_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 43
        echo "    ";
        $this->displayParentBlock("head", $context, $blocks);
        echo "

    <style>
        #tree-menu {
            float: left;
            padding-right: 10px;
            width: 230px;
        }
        #tree-menu ul {
            list-style: none;
            margin: 0;
            padding-left: 0;
        }
        #tree-menu li {
            margin: 0;
            padding: 0;
            width: 100%;
        }
        #tree-menu .empty {
            border: 0;
            mmargin: 0;
            padding: 0;
        }
        #tree-details-container {
            border-left: 1px solid #DDD;
            margin-left: 250px;
            padding-left: 20px;
        }
        .tree-details {
            padding-bottom: 40px;
        }
        .tree-details h3 {
            font-size: 18px;
            position: relative;
        }

        .toggle-icon {
            display: inline-block;
            background: url(\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAgBAMAAADpp+X/AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDgwx4LcKwAAAABVQTFRFAAAA////////////////ZmZm////bvjBwAAAAAV0Uk5TABZwsuCVEUjgAAAAAWJLR0QF+G/pxwAAAE1JREFUGNNjSHMSYGBgUEljSGYAAzMGBwiDhUEBwmBiEIAwGBmwgTQgQGWgA7h2uIFwK+CWwp1BpHvYEqDuATEYkBlY3IOmBq6dCPcAAIT5Eg2IksjQAAAAAElFTkSuQmCC\") no-repeat top left #5eb5e0;
        }
        .closed .toggle-icon, .closed.toggle-icon {
            background-position: bottom left;
        }
        .toggle-icon.empty {
            background-image: url(\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QAZgBmAGYHukptAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDhIf6CA40AAAAFRJREFUOMvtk7ENACEMA61vfx767MROWfO+AdGBHlNyTZrYUZRYDBII4NWE1pNdpFarfgLUbpDaBEgBYRiEVjsvDLa1l6O4Z3wkFWN+OfLKdpisOH/TlICzukmUJwAAAABJRU5ErkJggg==\");
        }

        .tree .tree-inner {
            cursor: pointer;
            padding: 5px 7px 5px 22px;
            position: relative;

        }
        .tree .toggle-button {
            /* provide a bigger clickable area than just 10x10px */
            width: 16px;
            height: 16px;
            margin-left: -18px;
        }
        .tree .toggle-icon {
            width: 10px;
            height: 10px;
            /* position the icon in the center of the clickable area */
            margin-left: 3px;
            margin-top: 3px;
            background-size: 10px 20px;
            background-color: #AAA;
        }
        .tree .toggle-icon.empty {
            width: 10px;
            height: 10px;
            position: absolute;
            top: 50%;
            margin-top: -5px;
            margin-left: -15px;
            background-size: 10px 10px;
        }
        .tree ul ul .tree-inner {
            padding-left: 37px;
        }
        .tree ul ul ul .tree-inner {
            padding-left: 52px;
        }
        .tree ul ul ul ul .tree-inner {
            padding-left: 67px;
        }
        .tree ul ul ul ul ul .tree-inner {
            padding-left: 82px;
        }
        .tree .tree-inner:hover {
            background: #dfdfdf;
        }
        .tree .tree-inner.active, .tree .tree-inner.active:hover {
            background: #E0E0E0;
            font-weight: bold;
        }
        .tree .tree-inner.active .toggle-icon, .tree .tree-inner:hover .toggle-icon, .tree .tree-inner.active:hover .toggle-icon {
            background-image: url(\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAgBAMAAADpp+X/AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDhEYXWn+sAAAABhQTFRFAAAA39/f39/f39/f39/fZmZm39/f////gc3YPwAAAAV0Uk5TAAtAc6ZeVyCYAAAAAWJLR0QF+G/pxwAAAE1JREFUGNNjSHMSYGBgUEljSGYAAzMGBwiDhUEBwmBiEIAwGBmwgXIgQGWgA7h2uIFwK+CWwp1BpHvYC6DuATEYkBlY3IOmBq6dCPcAADqLE4MnBi/fAAAAAElFTkSuQmCC\");
            background-color: #999;
        }
        .tree .tree-inner.active .toggle-icon.empty, .tree .tree-inner:hover .toggle-icon.empty, .tree .tree-inner.active:hover .toggle-icon.empty {
            background-image: url(\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQBAMAAADt3eJSAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDhoucSey4gAAABVQTFRFAAAA39/f39/f39/f39/fZmZm39/fD5Dx2AAAAAV0Uk5TAAtAc6ZeVyCYAAAAAWJLR0QF+G/pxwAAADJJREFUCNdjSHMSYGBgUEljSGYAAzMGBwiDhUEBwmBiEIAwGBnIA3DtcAPhVsAthTkDAFOfBKW9C1iqAAAAAElFTkSuQmCC\");
        }
        .tree-details .toggle-icon {
            width: 16px;
            height: 16px;
            /* vertically center the button */
            position: absolute;
            top: 50%;
            margin-top: -9px;
            margin-left: 6px;
        }
        .form-type {
            color: #999;
        }
        .badge-error {
            float: right;
            background: #B0413E;
            color: #FFF;
            padding: 1px 4px;
            font-size: 10px;
            font-weight: bold;
            vertical-align: middle;
        }
        .errors h3 {
            color: #B0413E;
        }
        .errors th {
            background: #B0413E;
            color: #FFF;
        }
        .errors .toggle-icon {
            background-color: #B0413E;
        }
        h3 a, h3 a:hover, h3 a:focus {
            color: inherit;
            text-decoration: inherit;
        }
    </style>
";
        
        $__internal_64f4aa6b9c3e3d7673785d468dbc40aa4953bde3edba11d0e21637a95440626c->leave($__internal_64f4aa6b9c3e3d7673785d468dbc40aa4953bde3edba11d0e21637a95440626c_prof);

    }

    // line 184
    public function block_panel($context, array $blocks = array())
    {
        $__internal_ecf69ebf72691dcfc2db3ad458e2cd588db13d38a951795c0bb6e5b5fcbac033 = $this->env->getExtension("native_profiler");
        $__internal_ecf69ebf72691dcfc2db3ad458e2cd588db13d38a951795c0bb6e5b5fcbac033->enter($__internal_ecf69ebf72691dcfc2db3ad458e2cd588db13d38a951795c0bb6e5b5fcbac033_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panel"));

        // line 185
        echo "    <h2>Forms</h2>

    ";
        // line 187
        if (twig_length_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array()))) {
            // line 188
            echo "        <div id=\"tree-menu\" class=\"tree\">
            <ul>
            ";
            // line 190
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array()));
            foreach ($context['_seq'] as $context["formName"] => $context["formData"]) {
                // line 191
                echo "                ";
                echo $context["__internal_8368653d2dc67995758bb059918465c24de409fe3717b5367c0e2cf630e0f955"]->getform_tree_entry($context["formName"], $context["formData"], true);
                echo "
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['formName'], $context['formData'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 193
            echo "            </ul>
        </div>

        <div id=\"tree-details-container\">
            ";
            // line 197
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms", array()));
            foreach ($context['_seq'] as $context["formName"] => $context["formData"]) {
                // line 198
                echo "                ";
                echo $context["__internal_8368653d2dc67995758bb059918465c24de409fe3717b5367c0e2cf630e0f955"]->getform_tree_details($context["formName"], $context["formData"], $this->getAttribute($this->getAttribute((isset($context["collector"]) ? $context["collector"] : $this->getContext($context, "collector")), "data", array()), "forms_by_hash", array()));
                echo "
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['formName'], $context['formData'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 200
            echo "        </div>
    ";
        } else {
            // line 202
            echo "        <div class=\"empty\">
            <p>No forms were submitted for this request.</p>
        </div>
    ";
        }
        // line 206
        echo "
    <script>
    function Toggler(storage) {
        \"use strict\";

        var STORAGE_KEY = 'sf_toggle_data',

            states = {},

            isCollapsed = function (button) {
                return Sfjs.hasClass(button, 'closed');
            },

            isExpanded = function (button) {
                return !isCollapsed(button);
            },

            expand = function (button) {
                var targetId = button.dataset.toggleTargetId,
                    target = document.getElementById(targetId);

                if (!target) {
                    throw \"Toggle target \" + targetId + \" does not exist\";
                }

                if (isCollapsed(button)) {
                    Sfjs.removeClass(button, 'closed');
                    Sfjs.removeClass(target, 'hidden');

                    states[targetId] = 1;
                    storage.setItem(STORAGE_KEY, states);
                }
            },

            collapse = function (button) {
                var targetId = button.dataset.toggleTargetId,
                    target = document.getElementById(targetId);

                if (!target) {
                    throw \"Toggle target \" + targetId + \" does not exist\";
                }

                if (isExpanded(button)) {
                    Sfjs.addClass(button, 'closed');
                    Sfjs.addClass(target, 'hidden');

                    states[targetId] = 0;
                    storage.setItem(STORAGE_KEY, states);
                }
            },

            toggle = function (button) {
                if (Sfjs.hasClass(button, 'closed')) {
                    expand(button);
                } else {
                    collapse(button);
                }
            },

            initButtons = function (buttons) {
                states = storage.getItem(STORAGE_KEY, {});

                // must be an object, not an array or anything else
                // `typeof` returns \"object\" also for arrays, so the following
                // check must be done
                // see http://stackoverflow.com/questions/4775722/check-if-object-is-array
                if ('[object Object]' !== Object.prototype.toString.call(states)) {
                    states = {};
                }

                for (var i = 0, l = buttons.length; i < l; ++i) {
                    var targetId = buttons[i].dataset.toggleTargetId,
                        target = document.getElementById(targetId);

                    if (!target) {
                        throw \"Toggle target \" + targetId + \" does not exist\";
                    }

                    // correct the initial state of the button
                    if (Sfjs.hasClass(target, 'hidden')) {
                        Sfjs.addClass(buttons[i], 'closed');
                    }

                    // attach listener for expanding/collapsing the target
                    clickHandler(buttons[i], toggle);

                    if (states.hasOwnProperty(targetId)) {
                        // open or collapse based on stored data
                        if (0 === states[targetId]) {
                            collapse(buttons[i]);
                        } else {
                            expand(buttons[i]);
                        }
                    }
                }
            };

        return {
            initButtons: initButtons,

            toggle: toggle,

            isExpanded: isExpanded,

            isCollapsed: isCollapsed,

            expand: expand,

            collapse: collapse
        };
    }

    function JsonStorage(storage) {
        var setItem = function (key, data) {
                storage.setItem(key, JSON.stringify(data));
            },

            getItem = function (key, defaultValue) {
                var data = storage.getItem(key);

                if (null !== data) {
                    try {
                        return JSON.parse(data);
                    } catch(e) {
                    }
                }

                return defaultValue;
            };

        return {
            setItem: setItem,

            getItem: getItem
        };
    }

    function TabView() {
        \"use strict\";

        var activeTab = null,

            activeTarget = null,

            select = function (tab) {
                var targetId = tab.dataset.tabTargetId,
                    target = document.getElementById(targetId);

                if (!target) {
                    throw \"Tab target \" + targetId + \" does not exist\";
                }

                if (activeTab) {
                    Sfjs.removeClass(activeTab, 'active');
                }

                if (activeTarget) {
                    Sfjs.addClass(activeTarget, 'hidden');
                }

                Sfjs.addClass(tab, 'active');
                Sfjs.removeClass(target, 'hidden');

                activeTab = tab;
                activeTarget = target;
            },

            initTabs = function (tabs) {
                for (var i = 0, l = tabs.length; i < l; ++i) {
                    var targetId = tabs[i].dataset.tabTargetId,
                        target = document.getElementById(targetId);

                    if (!target) {
                        throw \"Tab target \" + targetId + \" does not exist\";
                    }

                    clickHandler(tabs[i], select);

                    Sfjs.addClass(target, 'hidden');
                }

                if (tabs.length > 0) {
                    select(tabs[0]);
                }
            };

        return {
            initTabs: initTabs,

            select: select
        };
    }

    var tabTarget = new TabView(),
        toggler = new Toggler(new JsonStorage(sessionStorage)),
        clickHandler = function (element, callback) {
            Sfjs.addEventListener(element, 'click', function (e) {
                if (!e) {
                    e = window.event;
                }

                callback(this);

                if (e.preventDefault) {
                    e.preventDefault();
                } else {
                    e.returnValue = false;
                }

                e.stopPropagation();

                return false;
            });
        };

    tabTarget.initTabs(document.querySelectorAll('.tree .tree-inner'));
    toggler.initButtons(document.querySelectorAll('a.toggle-button'));
    </script>
";
        
        $__internal_ecf69ebf72691dcfc2db3ad458e2cd588db13d38a951795c0bb6e5b5fcbac033->leave($__internal_ecf69ebf72691dcfc2db3ad458e2cd588db13d38a951795c0bb6e5b5fcbac033_prof);

    }

    // line 426
    public function getform_tree_entry($__name__ = null, $__data__ = null, $__expanded__ = null)
    {
        $context = $this->env->mergeGlobals(array(
            "name" => $__name__,
            "data" => $__data__,
            "expanded" => $__expanded__,
            "varargs" => func_num_args() > 3 ? array_slice(func_get_args(), 3) : array(),
        ));

        $blocks = array();

        ob_start();
        try {
            $__internal_b1363ed8a6ab1ae816421f1d55afd0f13236a222cefaa16f0a859ff01f94ff41 = $this->env->getExtension("native_profiler");
            $__internal_b1363ed8a6ab1ae816421f1d55afd0f13236a222cefaa16f0a859ff01f94ff41->enter($__internal_b1363ed8a6ab1ae816421f1d55afd0f13236a222cefaa16f0a859ff01f94ff41_prof = new Twig_Profiler_Profile($this->getTemplateName(), "macro", "form_tree_entry"));

            // line 427
            echo "    ";
            $context["tree"] = $this;
            // line 428
            echo "    <li>
        <div class=\"tree-inner\" data-tab-target-id=\"";
            // line 429
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
            echo "-details\">
            ";
            // line 430
            if (($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "errors", array(), "any", true, true) && (twig_length_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "errors", array())) > 0))) {
                // line 431
                echo "                <div class=\"badge-error\">";
                echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "errors", array())), "html", null, true);
                echo "</div>
            ";
            }
            // line 433
            echo "
            ";
            // line 434
            if ( !twig_test_empty($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "children", array()))) {
                // line 435
                echo "                <a class=\"toggle-button\" data-toggle-target-id=\"";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-children\" href=\"#\"><span class=\"toggle-icon\"></span></a>
            ";
            } else {
                // line 437
                echo "                <div class=\"toggle-icon empty\"></div>
            ";
            }
            // line 439
            echo "
            ";
            // line 440
            echo twig_escape_filter($this->env, ((array_key_exists("name", $context)) ? (_twig_default_filter((isset($context["name"]) ? $context["name"] : $this->getContext($context, "name")), "(no name)")) : ("(no name)")), "html", null, true);
            echo " ";
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "type_class", array(), "any", true, true)) {
                echo "[<abbr title=\"";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "type_class", array()), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_last($this->env, twig_split_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "type_class", array()), "\\")), "html", null, true);
                echo "</abbr>]";
            }
            // line 441
            echo "        </div>

        ";
            // line 443
            if ( !twig_test_empty($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "children", array()))) {
                // line 444
                echo "            <ul id=\"";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-children\" ";
                if ( !(isset($context["expanded"]) ? $context["expanded"] : $this->getContext($context, "expanded"))) {
                    echo "class=\"hidden\"";
                }
                echo ">
                ";
                // line 445
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "children", array()));
                foreach ($context['_seq'] as $context["childName"] => $context["childData"]) {
                    // line 446
                    echo "                    ";
                    echo $context["tree"]->getform_tree_entry($context["childName"], $context["childData"], false);
                    echo "
                ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['childName'], $context['childData'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 448
                echo "            </ul>
        ";
            }
            // line 450
            echo "    </li>
";
            
            $__internal_b1363ed8a6ab1ae816421f1d55afd0f13236a222cefaa16f0a859ff01f94ff41->leave($__internal_b1363ed8a6ab1ae816421f1d55afd0f13236a222cefaa16f0a859ff01f94ff41_prof);

        } catch (Exception $e) {
            ob_end_clean();

            throw $e;
        }

        return ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
    }

    // line 453
    public function getform_tree_details($__name__ = null, $__data__ = null, $__forms_by_hash__ = null)
    {
        $context = $this->env->mergeGlobals(array(
            "name" => $__name__,
            "data" => $__data__,
            "forms_by_hash" => $__forms_by_hash__,
            "varargs" => func_num_args() > 3 ? array_slice(func_get_args(), 3) : array(),
        ));

        $blocks = array();

        ob_start();
        try {
            $__internal_879c006267f3213cbe8d339840453a5e64a8c4c26ddbf3d759fbc9aa9ad6ca63 = $this->env->getExtension("native_profiler");
            $__internal_879c006267f3213cbe8d339840453a5e64a8c4c26ddbf3d759fbc9aa9ad6ca63->enter($__internal_879c006267f3213cbe8d339840453a5e64a8c4c26ddbf3d759fbc9aa9ad6ca63_prof = new Twig_Profiler_Profile($this->getTemplateName(), "macro", "form_tree_details"));

            // line 454
            echo "    ";
            $context["tree"] = $this;
            // line 455
            echo "    <div class=\"tree-details\" ";
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "id", array(), "any", true, true)) {
                echo "id=\"";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-details\"";
            }
            echo ">
        <h2>
            ";
            // line 457
            echo twig_escape_filter($this->env, ((array_key_exists("name", $context)) ? (_twig_default_filter((isset($context["name"]) ? $context["name"] : $this->getContext($context, "name")), "(no name)")) : ("(no name)")), "html", null, true);
            echo "
            ";
            // line 458
            if (($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "type_class", array(), "any", true, true) && $this->getAttribute((isset($context["data"]) ? $context["data"] : null), "type", array(), "any", true, true))) {
                // line 459
                echo "            <span class=\"form-type\">[<abbr title=\"";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "type_class", array()), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "type", array()), "html", null, true);
                echo "</abbr>]</span>
            ";
            }
            // line 461
            echo "        </h2>

        ";
            // line 463
            if (($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "errors", array(), "any", true, true) && (twig_length_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "errors", array())) > 0))) {
                // line 464
                echo "        <div class=\"errors\">
            <h3>
                <a class=\"toggle-button\" data-toggle-target-id=\"";
                // line 466
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-errors\" href=\"#\">
                    Errors <span class=\"toggle-icon\"></span>
                </a>
            </h3>

            <table id=\"";
                // line 471
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-errors\">
                <thead>
                    <tr>
                        <th>Message</th>
                        <th>Origin</th>
                        <th>Cause</th>
                    </tr>
                </thead>
                <tbody>
                ";
                // line 480
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "errors", array()));
                foreach ($context['_seq'] as $context["_key"] => $context["error"]) {
                    // line 481
                    echo "                <tr>
                    <td>";
                    // line 482
                    echo twig_escape_filter($this->env, $this->getAttribute($context["error"], "message", array()), "html", null, true);
                    echo "</td>
                    <td>
                        ";
                    // line 484
                    if (twig_test_empty($this->getAttribute($context["error"], "origin", array()))) {
                        // line 485
                        echo "                            <em>This form.</em>
                        ";
                    } elseif ( !$this->getAttribute(                    // line 486
(isset($context["forms_by_hash"]) ? $context["forms_by_hash"] : null), $this->getAttribute($context["error"], "origin", array()), array(), "array", true, true)) {
                        // line 487
                        echo "                            <em>Unknown.</em>
                        ";
                    } else {
                        // line 489
                        echo "                            ";
                        echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["forms_by_hash"]) ? $context["forms_by_hash"] : $this->getContext($context, "forms_by_hash")), $this->getAttribute($context["error"], "origin", array()), array(), "array"), "name", array()), "html", null, true);
                        echo "
                        ";
                    }
                    // line 491
                    echo "                    </td>
                    <td>
                        ";
                    // line 493
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable($this->getAttribute($context["error"], "trace", array()));
                    $context['_iterated'] = false;
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
                    foreach ($context['_seq'] as $context["_key"] => $context["trace"]) {
                        // line 494
                        echo "                            ";
                        if ( !$this->getAttribute($context["loop"], "first", array())) {
                            // line 495
                            echo "                                <span class=\"newline\">Caused by:</span>
                            ";
                        }
                        // line 497
                        echo "
                            ";
                        // line 498
                        if ($this->getAttribute($context["trace"], "root", array(), "any", true, true)) {
                            // line 499
                            echo "                                <strong class=\"newline\">";
                            echo twig_escape_filter($this->env, $this->getAttribute($context["trace"], "class", array()), "html", null, true);
                            echo "</strong>
                                <pre>";
                            // line 501
                            echo twig_escape_filter($this->env, $this->getAttribute($context["trace"], "root", array()), "html", null, true);
                            // line 502
                            if ( !twig_test_empty($this->getAttribute($context["trace"], "path", array()))) {
                                // line 503
                                if ((twig_first($this->env, $this->getAttribute($context["trace"], "path", array())) != "[")) {
                                    echo ".";
                                }
                                // line 504
                                echo twig_escape_filter($this->env, $this->getAttribute($context["trace"], "path", array()), "html", null, true);
                            }
                            // line 505
                            echo " = ";
                            echo twig_escape_filter($this->env, $this->getAttribute($context["trace"], "value", array()), "html", null, true);
                            // line 506
                            echo "</pre>
                            ";
                        } elseif ($this->getAttribute(                        // line 507
$context["trace"], "message", array(), "any", true, true)) {
                            // line 508
                            echo "                                <strong class=\"newline\">";
                            echo twig_escape_filter($this->env, $this->getAttribute($context["trace"], "class", array()), "html", null, true);
                            echo "</strong>
                                <pre>";
                            // line 509
                            echo twig_escape_filter($this->env, $this->getAttribute($context["trace"], "message", array()), "html", null, true);
                            echo "</pre>
                            ";
                        } else {
                            // line 511
                            echo "                                <pre>";
                            echo twig_escape_filter($this->env, $context["trace"], "html", null, true);
                            echo "</pre>
                            ";
                        }
                        // line 513
                        echo "                        ";
                        $context['_iterated'] = true;
                        ++$context['loop']['index0'];
                        ++$context['loop']['index'];
                        $context['loop']['first'] = false;
                        if (isset($context['loop']['length'])) {
                            --$context['loop']['revindex0'];
                            --$context['loop']['revindex'];
                            $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                        }
                    }
                    if (!$context['_iterated']) {
                        // line 514
                        echo "                            <em>Unknown.</em>
                        ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['trace'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 516
                    echo "                    </td>
                </tr>
                ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['error'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 519
                echo "                </tbody>
            </table>
        </div>
        ";
            }
            // line 523
            echo "
        ";
            // line 524
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "default_data", array(), "any", true, true)) {
                // line 525
                echo "        <h3>
            <a class=\"toggle-button\" data-toggle-target-id=\"";
                // line 526
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-default_data\" href=\"#\">
                Default Data <span class=\"toggle-icon\"></span>
            </a>
        </h3>

        <div id=\"";
                // line 531
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-default_data\">
            <table>
                <thead>
                    <tr>
                        <th width=\"180\">Property</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class=\"font-normal\" scope=\"row\">Model Format</th>
                        <td>
                            ";
                // line 543
                if ($this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "default_data", array(), "any", false, true), "model", array(), "any", true, true)) {
                    // line 544
                    echo "                                ";
                    echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "default_data", array()), "model", array()), "html", null, true);
                    echo "
                            ";
                } else {
                    // line 546
                    echo "                                <em class=\"font-normal text-muted\">same as normalized format</em>
                            ";
                }
                // line 548
                echo "                        </td>
                    </tr>
                    <tr>
                        <th class=\"font-normal\" scope=\"row\">Normalized Format</th>
                        <td>";
                // line 552
                echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "default_data", array()), "norm", array()), "html", null, true);
                echo "</td>
                    </tr>
                    <tr>
                        <th class=\"font-normal\" scope=\"row\">View Format</th>
                        <td>
                            ";
                // line 557
                if ($this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "default_data", array(), "any", false, true), "view", array(), "any", true, true)) {
                    // line 558
                    echo "                                ";
                    echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "default_data", array()), "view", array()), "html", null, true);
                    echo "
                            ";
                } else {
                    // line 560
                    echo "                                <em class=\"font-normal text-muted\">same as normalized format</em>
                            ";
                }
                // line 562
                echo "                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        ";
            }
            // line 568
            echo "
        ";
            // line 569
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "submitted_data", array(), "any", true, true)) {
                // line 570
                echo "        <h3>
            <a class=\"toggle-button\" data-toggle-target-id=\"";
                // line 571
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-submitted_data\" href=\"#\">
                Submitted Data <span class=\"toggle-icon\"></span>
            </a>
        </h3>

        <div id=\"";
                // line 576
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-submitted_data\">
        ";
                // line 577
                if ($this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "submitted_data", array(), "any", false, true), "norm", array(), "any", true, true)) {
                    // line 578
                    echo "            <table>
                <thead>
                    <tr>
                        <th width=\"180\">Property</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class=\"font-normal\" scope=\"row\">View Format</th>
                        <td>
                            ";
                    // line 589
                    if ($this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "submitted_data", array(), "any", false, true), "view", array(), "any", true, true)) {
                        // line 590
                        echo "                                ";
                        echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "submitted_data", array()), "view", array()), "html", null, true);
                        echo "
                            ";
                    } else {
                        // line 592
                        echo "                                <em class=\"font-normal text-muted\">same as normalized format</em>
                            ";
                    }
                    // line 594
                    echo "                        </td>
                    </tr>
                    <tr>
                        <th class=\"font-normal\" scope=\"row\">Normalized Format</th>
                        <td>";
                    // line 598
                    echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "submitted_data", array()), "norm", array()), "html", null, true);
                    echo "</td>
                    </tr>
                    <tr>
                        <th class=\"font-normal\" scope=\"row\">Model Format</th>
                        <td>
                            ";
                    // line 603
                    if ($this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "submitted_data", array(), "any", false, true), "model", array(), "any", true, true)) {
                        // line 604
                        echo "                                ";
                        echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "submitted_data", array()), "model", array()), "html", null, true);
                        echo "
                            ";
                    } else {
                        // line 606
                        echo "                                <em class=\"font-normal text-muted\">same as normalized format</em>
                            ";
                    }
                    // line 608
                    echo "                        </td>
                    </tr>
                </tbody>
            </table>
        ";
                } else {
                    // line 613
                    echo "            <div class=\"empty\">
                <p>This form was not submitted.</p>
            </div>
        ";
                }
                // line 617
                echo "        </div>
        ";
            }
            // line 619
            echo "
        ";
            // line 620
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "passed_options", array(), "any", true, true)) {
                // line 621
                echo "        <h3>
            <a class=\"toggle-button\" data-toggle-target-id=\"";
                // line 622
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-passed_options\" href=\"#\">
                Passed Options <span class=\"toggle-icon\"></span>
            </a>
        </h3>

        <div id=\"";
                // line 627
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-passed_options\">
            ";
                // line 628
                if (twig_length_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "passed_options", array()))) {
                    // line 629
                    echo "            <table>
                <thead>
                    <tr>
                        <th width=\"180\">Option</th>
                        <th>Passed Value</th>
                        <th>Resolved Value</th>
                    </tr>
                </thead>
                <tbody>
                ";
                    // line 638
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "passed_options", array()));
                    foreach ($context['_seq'] as $context["option"] => $context["value"]) {
                        // line 639
                        echo "                <tr>
                    <th>";
                        // line 640
                        echo twig_escape_filter($this->env, $context["option"], "html", null, true);
                        echo "</th>
                    <td>";
                        // line 641
                        echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                        echo "</td>
                    <td>
                        ";
                        // line 643
                        if (($this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "resolved_options", array()), $context["option"], array(), "array") === $context["value"])) {
                            // line 644
                            echo "                            <em class=\"font-normal text-muted\">same as passed value</em>
                        ";
                        } else {
                            // line 646
                            echo "                            ";
                            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "resolved_options", array()), $context["option"], array(), "array"), "html", null, true);
                            echo "
                        ";
                        }
                        // line 648
                        echo "                    </td>
                </tr>
                ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['option'], $context['value'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 651
                    echo "                </tbody>
            </table>
            ";
                } else {
                    // line 654
                    echo "                <div class=\"empty\">
                    <p>No options where passed when constructing this form.</p>
                </div>
            ";
                }
                // line 658
                echo "        </div>
        ";
            }
            // line 660
            echo "
        ";
            // line 661
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "resolved_options", array(), "any", true, true)) {
                // line 662
                echo "        <h3>
            <a class=\"toggle-button\" data-toggle-target-id=\"";
                // line 663
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-resolved_options\" href=\"#\">
                Resolved Options <span class=\"toggle-icon\"></span>
            </a>
        </h3>

        <div id=\"";
                // line 668
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-resolved_options\" class=\"hidden\">
            <table>
                <thead>
                    <tr>
                        <th width=\"180\">Option</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                ";
                // line 677
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "resolved_options", array()));
                foreach ($context['_seq'] as $context["option"] => $context["value"]) {
                    // line 678
                    echo "                <tr>
                    <th scope=\"row\">";
                    // line 679
                    echo twig_escape_filter($this->env, $context["option"], "html", null, true);
                    echo "</th>
                    <td>";
                    // line 680
                    echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                    echo "</td>
                </tr>
                ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['option'], $context['value'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 683
                echo "                </tbody>
            </table>
        </div>
        ";
            }
            // line 687
            echo "
        ";
            // line 688
            if ($this->getAttribute((isset($context["data"]) ? $context["data"] : null), "view_vars", array(), "any", true, true)) {
                // line 689
                echo "        <h3>
            <a class=\"toggle-button\" data-toggle-target-id=\"";
                // line 690
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-view_vars\" href=\"#\">
                View Variables <span class=\"toggle-icon\"></span>
            </a>
        </h3>

        <div id=\"";
                // line 695
                echo twig_escape_filter($this->env, $this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "id", array()), "html", null, true);
                echo "-view_vars\" class=\"hidden\">
            <table>
                <thead>
                    <tr>
                        <th width=\"180\">Variable</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                ";
                // line 704
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "view_vars", array()));
                foreach ($context['_seq'] as $context["variable"] => $context["value"]) {
                    // line 705
                    echo "                <tr>
                    <th scope=\"row\">";
                    // line 706
                    echo twig_escape_filter($this->env, $context["variable"], "html", null, true);
                    echo "</th>
                    <td>";
                    // line 707
                    echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                    echo "</td>
                </tr>
                ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['variable'], $context['value'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 710
                echo "                </tbody>
            </table>
        </div>
        ";
            }
            // line 714
            echo "    </div>

    ";
            // line 716
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["data"]) ? $context["data"] : $this->getContext($context, "data")), "children", array()));
            foreach ($context['_seq'] as $context["childName"] => $context["childData"]) {
                // line 717
                echo "        ";
                echo $context["tree"]->getform_tree_details($context["childName"], $context["childData"], (isset($context["forms_by_hash"]) ? $context["forms_by_hash"] : $this->getContext($context, "forms_by_hash")));
                echo "
    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['childName'], $context['childData'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            
            $__internal_879c006267f3213cbe8d339840453a5e64a8c4c26ddbf3d759fbc9aa9ad6ca63->leave($__internal_879c006267f3213cbe8d339840453a5e64a8c4c26ddbf3d759fbc9aa9ad6ca63_prof);

        } catch (Exception $e) {
            ob_end_clean();

            throw $e;
        }

        return ('' === $tmp = ob_get_clean()) ? '' : new Twig_Markup($tmp, $this->env->getCharset());
    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/form.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  1266 => 717,  1262 => 716,  1258 => 714,  1252 => 710,  1243 => 707,  1239 => 706,  1236 => 705,  1232 => 704,  1220 => 695,  1212 => 690,  1209 => 689,  1207 => 688,  1204 => 687,  1198 => 683,  1189 => 680,  1185 => 679,  1182 => 678,  1178 => 677,  1166 => 668,  1158 => 663,  1155 => 662,  1153 => 661,  1150 => 660,  1146 => 658,  1140 => 654,  1135 => 651,  1127 => 648,  1121 => 646,  1117 => 644,  1115 => 643,  1110 => 641,  1106 => 640,  1103 => 639,  1099 => 638,  1088 => 629,  1086 => 628,  1082 => 627,  1074 => 622,  1071 => 621,  1069 => 620,  1066 => 619,  1062 => 617,  1056 => 613,  1049 => 608,  1045 => 606,  1039 => 604,  1037 => 603,  1029 => 598,  1023 => 594,  1019 => 592,  1013 => 590,  1011 => 589,  998 => 578,  996 => 577,  992 => 576,  984 => 571,  981 => 570,  979 => 569,  976 => 568,  968 => 562,  964 => 560,  958 => 558,  956 => 557,  948 => 552,  942 => 548,  938 => 546,  932 => 544,  930 => 543,  915 => 531,  907 => 526,  904 => 525,  902 => 524,  899 => 523,  893 => 519,  885 => 516,  878 => 514,  865 => 513,  859 => 511,  854 => 509,  849 => 508,  847 => 507,  844 => 506,  841 => 505,  838 => 504,  834 => 503,  832 => 502,  830 => 501,  825 => 499,  823 => 498,  820 => 497,  816 => 495,  813 => 494,  795 => 493,  791 => 491,  785 => 489,  781 => 487,  779 => 486,  776 => 485,  774 => 484,  769 => 482,  766 => 481,  762 => 480,  750 => 471,  742 => 466,  738 => 464,  736 => 463,  732 => 461,  724 => 459,  722 => 458,  718 => 457,  708 => 455,  705 => 454,  688 => 453,  673 => 450,  669 => 448,  660 => 446,  656 => 445,  647 => 444,  645 => 443,  641 => 441,  631 => 440,  628 => 439,  624 => 437,  618 => 435,  616 => 434,  613 => 433,  607 => 431,  605 => 430,  601 => 429,  598 => 428,  595 => 427,  578 => 426,  353 => 206,  347 => 202,  343 => 200,  334 => 198,  330 => 197,  324 => 193,  315 => 191,  311 => 190,  307 => 188,  305 => 187,  301 => 185,  295 => 184,  147 => 43,  141 => 42,  133 => 39,  127 => 36,  124 => 35,  122 => 34,  117 => 32,  110 => 31,  104 => 30,  94 => 26,  91 => 25,  83 => 22,  76 => 18,  72 => 16,  70 => 15,  67 => 14,  61 => 11,  55 => 9,  52 => 8,  49 => 7,  46 => 6,  40 => 5,  33 => 1,  31 => 3,  11 => 1,);
    }
}
/* {% extends '@WebProfiler/Profiler/layout.html.twig' %}*/
/* */
/* {% from _self import form_tree_entry, form_tree_details %}*/
/* */
/* {% block toolbar %}*/
/*     {% if collector.data.nb_errors > 0 or collector.data.forms|length %}*/
/*         {% set status_color = collector.data.nb_errors ? 'red' : '' %}*/
/*         {% set icon %}*/
/*             {{ include('@WebProfiler/Icon/form.svg') }}*/
/*             <span class="sf-toolbar-value">*/
/*                 {{ collector.data.nb_errors ?: collector.data.forms|length }}*/
/*             </span>*/
/*         {% endset %}*/
/* */
/*         {% set text %}*/
/*             <div class="sf-toolbar-info-piece">*/
/*                 <b>Number of forms</b>*/
/*                 <span class="sf-toolbar-status">{{ collector.data.forms|length }}</span>*/
/*             </div>*/
/*             <div class="sf-toolbar-info-piece">*/
/*                 <b>Number of errors</b>*/
/*                 <span class="sf-toolbar-status sf-toolbar-status-{{ collector.data.nb_errors > 0 ? 'red' }}">{{ collector.data.nb_errors }}</span>*/
/*             </div>*/
/*         {% endset %}*/
/* */
/*         {{ include('@WebProfiler/Profiler/toolbar_item.html.twig', { link: profiler_url, status: status_color }) }}*/
/*     {% endif %}*/
/* {% endblock %}*/
/* */
/* {% block menu %}*/
/*     <span class="label label-status-{{ collector.data.nb_errors ? 'error' }} {{ collector.data.forms is empty ? 'disabled' }}">*/
/*         <span class="icon">{{ include('@WebProfiler/Icon/form.svg') }}</span>*/
/*         <strong>Forms</strong>*/
/*         {% if collector.data.nb_errors > 0 %}*/
/*             <span class="count">*/
/*                 <span>{{ collector.data.nb_errors }}</span>*/
/*             </span>*/
/*         {% endif %}*/
/*     </span>*/
/* {% endblock %}*/
/* */
/* {% block head %}*/
/*     {{ parent() }}*/
/* */
/*     <style>*/
/*         #tree-menu {*/
/*             float: left;*/
/*             padding-right: 10px;*/
/*             width: 230px;*/
/*         }*/
/*         #tree-menu ul {*/
/*             list-style: none;*/
/*             margin: 0;*/
/*             padding-left: 0;*/
/*         }*/
/*         #tree-menu li {*/
/*             margin: 0;*/
/*             padding: 0;*/
/*             width: 100%;*/
/*         }*/
/*         #tree-menu .empty {*/
/*             border: 0;*/
/*             mmargin: 0;*/
/*             padding: 0;*/
/*         }*/
/*         #tree-details-container {*/
/*             border-left: 1px solid #DDD;*/
/*             margin-left: 250px;*/
/*             padding-left: 20px;*/
/*         }*/
/*         .tree-details {*/
/*             padding-bottom: 40px;*/
/*         }*/
/*         .tree-details h3 {*/
/*             font-size: 18px;*/
/*             position: relative;*/
/*         }*/
/* */
/*         .toggle-icon {*/
/*             display: inline-block;*/
/*             background: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAgBAMAAADpp+X/AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDgwx4LcKwAAAABVQTFRFAAAA////////////////ZmZm////bvjBwAAAAAV0Uk5TABZwsuCVEUjgAAAAAWJLR0QF+G/pxwAAAE1JREFUGNNjSHMSYGBgUEljSGYAAzMGBwiDhUEBwmBiEIAwGBmwgTQgQGWgA7h2uIFwK+CWwp1BpHvYEqDuATEYkBlY3IOmBq6dCPcAAIT5Eg2IksjQAAAAAElFTkSuQmCC") no-repeat top left #5eb5e0;*/
/*         }*/
/*         .closed .toggle-icon, .closed.toggle-icon {*/
/*             background-position: bottom left;*/
/*         }*/
/*         .toggle-icon.empty {*/
/*             background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QAZgBmAGYHukptAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDhIf6CA40AAAAFRJREFUOMvtk7ENACEMA61vfx767MROWfO+AdGBHlNyTZrYUZRYDBII4NWE1pNdpFarfgLUbpDaBEgBYRiEVjsvDLa1l6O4Z3wkFWN+OfLKdpisOH/TlICzukmUJwAAAABJRU5ErkJggg==");*/
/*         }*/
/* */
/*         .tree .tree-inner {*/
/*             cursor: pointer;*/
/*             padding: 5px 7px 5px 22px;*/
/*             position: relative;*/
/* */
/*         }*/
/*         .tree .toggle-button {*/
/*             /* provide a bigger clickable area than just 10x10px *//* */
/*             width: 16px;*/
/*             height: 16px;*/
/*             margin-left: -18px;*/
/*         }*/
/*         .tree .toggle-icon {*/
/*             width: 10px;*/
/*             height: 10px;*/
/*             /* position the icon in the center of the clickable area *//* */
/*             margin-left: 3px;*/
/*             margin-top: 3px;*/
/*             background-size: 10px 20px;*/
/*             background-color: #AAA;*/
/*         }*/
/*         .tree .toggle-icon.empty {*/
/*             width: 10px;*/
/*             height: 10px;*/
/*             position: absolute;*/
/*             top: 50%;*/
/*             margin-top: -5px;*/
/*             margin-left: -15px;*/
/*             background-size: 10px 10px;*/
/*         }*/
/*         .tree ul ul .tree-inner {*/
/*             padding-left: 37px;*/
/*         }*/
/*         .tree ul ul ul .tree-inner {*/
/*             padding-left: 52px;*/
/*         }*/
/*         .tree ul ul ul ul .tree-inner {*/
/*             padding-left: 67px;*/
/*         }*/
/*         .tree ul ul ul ul ul .tree-inner {*/
/*             padding-left: 82px;*/
/*         }*/
/*         .tree .tree-inner:hover {*/
/*             background: #dfdfdf;*/
/*         }*/
/*         .tree .tree-inner.active, .tree .tree-inner.active:hover {*/
/*             background: #E0E0E0;*/
/*             font-weight: bold;*/
/*         }*/
/*         .tree .tree-inner.active .toggle-icon, .tree .tree-inner:hover .toggle-icon, .tree .tree-inner.active:hover .toggle-icon {*/
/*             background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAgBAMAAADpp+X/AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDhEYXWn+sAAAABhQTFRFAAAA39/f39/f39/f39/fZmZm39/f////gc3YPwAAAAV0Uk5TAAtAc6ZeVyCYAAAAAWJLR0QF+G/pxwAAAE1JREFUGNNjSHMSYGBgUEljSGYAAzMGBwiDhUEBwmBiEIAwGBmwgXIgQGWgA7h2uIFwK+CWwp1BpHvYC6DuATEYkBlY3IOmBq6dCPcAADqLE4MnBi/fAAAAAElFTkSuQmCC");*/
/*             background-color: #999;*/
/*         }*/
/*         .tree .tree-inner.active .toggle-icon.empty, .tree .tree-inner:hover .toggle-icon.empty, .tree .tree-inner.active:hover .toggle-icon.empty {*/
/*             background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQBAMAAADt3eJSAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3QweDhoucSey4gAAABVQTFRFAAAA39/f39/f39/f39/fZmZm39/fD5Dx2AAAAAV0Uk5TAAtAc6ZeVyCYAAAAAWJLR0QF+G/pxwAAADJJREFUCNdjSHMSYGBgUEljSGYAAzMGBwiDhUEBwmBiEIAwGBnIA3DtcAPhVsAthTkDAFOfBKW9C1iqAAAAAElFTkSuQmCC");*/
/*         }*/
/*         .tree-details .toggle-icon {*/
/*             width: 16px;*/
/*             height: 16px;*/
/*             /* vertically center the button *//* */
/*             position: absolute;*/
/*             top: 50%;*/
/*             margin-top: -9px;*/
/*             margin-left: 6px;*/
/*         }*/
/*         .form-type {*/
/*             color: #999;*/
/*         }*/
/*         .badge-error {*/
/*             float: right;*/
/*             background: #B0413E;*/
/*             color: #FFF;*/
/*             padding: 1px 4px;*/
/*             font-size: 10px;*/
/*             font-weight: bold;*/
/*             vertical-align: middle;*/
/*         }*/
/*         .errors h3 {*/
/*             color: #B0413E;*/
/*         }*/
/*         .errors th {*/
/*             background: #B0413E;*/
/*             color: #FFF;*/
/*         }*/
/*         .errors .toggle-icon {*/
/*             background-color: #B0413E;*/
/*         }*/
/*         h3 a, h3 a:hover, h3 a:focus {*/
/*             color: inherit;*/
/*             text-decoration: inherit;*/
/*         }*/
/*     </style>*/
/* {% endblock %}*/
/* */
/* {% block panel %}*/
/*     <h2>Forms</h2>*/
/* */
/*     {% if collector.data.forms|length %}*/
/*         <div id="tree-menu" class="tree">*/
/*             <ul>*/
/*             {% for formName, formData in collector.data.forms %}*/
/*                 {{ form_tree_entry(formName, formData, true) }}*/
/*             {% endfor %}*/
/*             </ul>*/
/*         </div>*/
/* */
/*         <div id="tree-details-container">*/
/*             {% for formName, formData in collector.data.forms %}*/
/*                 {{ form_tree_details(formName, formData, collector.data.forms_by_hash) }}*/
/*             {% endfor %}*/
/*         </div>*/
/*     {% else %}*/
/*         <div class="empty">*/
/*             <p>No forms were submitted for this request.</p>*/
/*         </div>*/
/*     {% endif %}*/
/* */
/*     <script>*/
/*     function Toggler(storage) {*/
/*         "use strict";*/
/* */
/*         var STORAGE_KEY = 'sf_toggle_data',*/
/* */
/*             states = {},*/
/* */
/*             isCollapsed = function (button) {*/
/*                 return Sfjs.hasClass(button, 'closed');*/
/*             },*/
/* */
/*             isExpanded = function (button) {*/
/*                 return !isCollapsed(button);*/
/*             },*/
/* */
/*             expand = function (button) {*/
/*                 var targetId = button.dataset.toggleTargetId,*/
/*                     target = document.getElementById(targetId);*/
/* */
/*                 if (!target) {*/
/*                     throw "Toggle target " + targetId + " does not exist";*/
/*                 }*/
/* */
/*                 if (isCollapsed(button)) {*/
/*                     Sfjs.removeClass(button, 'closed');*/
/*                     Sfjs.removeClass(target, 'hidden');*/
/* */
/*                     states[targetId] = 1;*/
/*                     storage.setItem(STORAGE_KEY, states);*/
/*                 }*/
/*             },*/
/* */
/*             collapse = function (button) {*/
/*                 var targetId = button.dataset.toggleTargetId,*/
/*                     target = document.getElementById(targetId);*/
/* */
/*                 if (!target) {*/
/*                     throw "Toggle target " + targetId + " does not exist";*/
/*                 }*/
/* */
/*                 if (isExpanded(button)) {*/
/*                     Sfjs.addClass(button, 'closed');*/
/*                     Sfjs.addClass(target, 'hidden');*/
/* */
/*                     states[targetId] = 0;*/
/*                     storage.setItem(STORAGE_KEY, states);*/
/*                 }*/
/*             },*/
/* */
/*             toggle = function (button) {*/
/*                 if (Sfjs.hasClass(button, 'closed')) {*/
/*                     expand(button);*/
/*                 } else {*/
/*                     collapse(button);*/
/*                 }*/
/*             },*/
/* */
/*             initButtons = function (buttons) {*/
/*                 states = storage.getItem(STORAGE_KEY, {});*/
/* */
/*                 // must be an object, not an array or anything else*/
/*                 // `typeof` returns "object" also for arrays, so the following*/
/*                 // check must be done*/
/*                 // see http://stackoverflow.com/questions/4775722/check-if-object-is-array*/
/*                 if ('[object Object]' !== Object.prototype.toString.call(states)) {*/
/*                     states = {};*/
/*                 }*/
/* */
/*                 for (var i = 0, l = buttons.length; i < l; ++i) {*/
/*                     var targetId = buttons[i].dataset.toggleTargetId,*/
/*                         target = document.getElementById(targetId);*/
/* */
/*                     if (!target) {*/
/*                         throw "Toggle target " + targetId + " does not exist";*/
/*                     }*/
/* */
/*                     // correct the initial state of the button*/
/*                     if (Sfjs.hasClass(target, 'hidden')) {*/
/*                         Sfjs.addClass(buttons[i], 'closed');*/
/*                     }*/
/* */
/*                     // attach listener for expanding/collapsing the target*/
/*                     clickHandler(buttons[i], toggle);*/
/* */
/*                     if (states.hasOwnProperty(targetId)) {*/
/*                         // open or collapse based on stored data*/
/*                         if (0 === states[targetId]) {*/
/*                             collapse(buttons[i]);*/
/*                         } else {*/
/*                             expand(buttons[i]);*/
/*                         }*/
/*                     }*/
/*                 }*/
/*             };*/
/* */
/*         return {*/
/*             initButtons: initButtons,*/
/* */
/*             toggle: toggle,*/
/* */
/*             isExpanded: isExpanded,*/
/* */
/*             isCollapsed: isCollapsed,*/
/* */
/*             expand: expand,*/
/* */
/*             collapse: collapse*/
/*         };*/
/*     }*/
/* */
/*     function JsonStorage(storage) {*/
/*         var setItem = function (key, data) {*/
/*                 storage.setItem(key, JSON.stringify(data));*/
/*             },*/
/* */
/*             getItem = function (key, defaultValue) {*/
/*                 var data = storage.getItem(key);*/
/* */
/*                 if (null !== data) {*/
/*                     try {*/
/*                         return JSON.parse(data);*/
/*                     } catch(e) {*/
/*                     }*/
/*                 }*/
/* */
/*                 return defaultValue;*/
/*             };*/
/* */
/*         return {*/
/*             setItem: setItem,*/
/* */
/*             getItem: getItem*/
/*         };*/
/*     }*/
/* */
/*     function TabView() {*/
/*         "use strict";*/
/* */
/*         var activeTab = null,*/
/* */
/*             activeTarget = null,*/
/* */
/*             select = function (tab) {*/
/*                 var targetId = tab.dataset.tabTargetId,*/
/*                     target = document.getElementById(targetId);*/
/* */
/*                 if (!target) {*/
/*                     throw "Tab target " + targetId + " does not exist";*/
/*                 }*/
/* */
/*                 if (activeTab) {*/
/*                     Sfjs.removeClass(activeTab, 'active');*/
/*                 }*/
/* */
/*                 if (activeTarget) {*/
/*                     Sfjs.addClass(activeTarget, 'hidden');*/
/*                 }*/
/* */
/*                 Sfjs.addClass(tab, 'active');*/
/*                 Sfjs.removeClass(target, 'hidden');*/
/* */
/*                 activeTab = tab;*/
/*                 activeTarget = target;*/
/*             },*/
/* */
/*             initTabs = function (tabs) {*/
/*                 for (var i = 0, l = tabs.length; i < l; ++i) {*/
/*                     var targetId = tabs[i].dataset.tabTargetId,*/
/*                         target = document.getElementById(targetId);*/
/* */
/*                     if (!target) {*/
/*                         throw "Tab target " + targetId + " does not exist";*/
/*                     }*/
/* */
/*                     clickHandler(tabs[i], select);*/
/* */
/*                     Sfjs.addClass(target, 'hidden');*/
/*                 }*/
/* */
/*                 if (tabs.length > 0) {*/
/*                     select(tabs[0]);*/
/*                 }*/
/*             };*/
/* */
/*         return {*/
/*             initTabs: initTabs,*/
/* */
/*             select: select*/
/*         };*/
/*     }*/
/* */
/*     var tabTarget = new TabView(),*/
/*         toggler = new Toggler(new JsonStorage(sessionStorage)),*/
/*         clickHandler = function (element, callback) {*/
/*             Sfjs.addEventListener(element, 'click', function (e) {*/
/*                 if (!e) {*/
/*                     e = window.event;*/
/*                 }*/
/* */
/*                 callback(this);*/
/* */
/*                 if (e.preventDefault) {*/
/*                     e.preventDefault();*/
/*                 } else {*/
/*                     e.returnValue = false;*/
/*                 }*/
/* */
/*                 e.stopPropagation();*/
/* */
/*                 return false;*/
/*             });*/
/*         };*/
/* */
/*     tabTarget.initTabs(document.querySelectorAll('.tree .tree-inner'));*/
/*     toggler.initButtons(document.querySelectorAll('a.toggle-button'));*/
/*     </script>*/
/* {% endblock %}*/
/* */
/* {% macro form_tree_entry(name, data, expanded) %}*/
/*     {% import _self as tree %}*/
/*     <li>*/
/*         <div class="tree-inner" data-tab-target-id="{{ data.id }}-details">*/
/*             {% if data.errors is defined and data.errors|length > 0 %}*/
/*                 <div class="badge-error">{{ data.errors|length }}</div>*/
/*             {% endif %}*/
/* */
/*             {% if data.children is not empty %}*/
/*                 <a class="toggle-button" data-toggle-target-id="{{ data.id }}-children" href="#"><span class="toggle-icon"></span></a>*/
/*             {% else %}*/
/*                 <div class="toggle-icon empty"></div>*/
/*             {% endif %}*/
/* */
/*             {{ name|default('(no name)') }} {% if data.type_class is defined %}[<abbr title="{{ data.type_class }}">{{ data.type_class|split('\\')|last }}</abbr>]{% endif %}*/
/*         </div>*/
/* */
/*         {% if data.children is not empty %}*/
/*             <ul id="{{ data.id }}-children" {% if not expanded %}class="hidden"{% endif %}>*/
/*                 {% for childName, childData in data.children %}*/
/*                     {{ tree.form_tree_entry(childName, childData, false) }}*/
/*                 {% endfor %}*/
/*             </ul>*/
/*         {% endif %}*/
/*     </li>*/
/* {% endmacro %}*/
/* */
/* {% macro form_tree_details(name, data, forms_by_hash) %}*/
/*     {% import _self as tree %}*/
/*     <div class="tree-details" {% if data.id is defined %}id="{{ data.id }}-details"{% endif %}>*/
/*         <h2>*/
/*             {{ name|default('(no name)') }}*/
/*             {% if data.type_class is defined and data.type is defined %}*/
/*             <span class="form-type">[<abbr title="{{ data.type_class }}">{{ data.type }}</abbr>]</span>*/
/*             {% endif %}*/
/*         </h2>*/
/* */
/*         {% if data.errors is defined and data.errors|length > 0 %}*/
/*         <div class="errors">*/
/*             <h3>*/
/*                 <a class="toggle-button" data-toggle-target-id="{{ data.id }}-errors" href="#">*/
/*                     Errors <span class="toggle-icon"></span>*/
/*                 </a>*/
/*             </h3>*/
/* */
/*             <table id="{{ data.id }}-errors">*/
/*                 <thead>*/
/*                     <tr>*/
/*                         <th>Message</th>*/
/*                         <th>Origin</th>*/
/*                         <th>Cause</th>*/
/*                     </tr>*/
/*                 </thead>*/
/*                 <tbody>*/
/*                 {% for error in data.errors %}*/
/*                 <tr>*/
/*                     <td>{{ error.message }}</td>*/
/*                     <td>*/
/*                         {% if error.origin is empty %}*/
/*                             <em>This form.</em>*/
/*                         {% elseif forms_by_hash[error.origin] is not defined %}*/
/*                             <em>Unknown.</em>*/
/*                         {% else %}*/
/*                             {{ forms_by_hash[error.origin].name }}*/
/*                         {% endif %}*/
/*                     </td>*/
/*                     <td>*/
/*                         {% for trace in error.trace %}*/
/*                             {% if not loop.first %}*/
/*                                 <span class="newline">Caused by:</span>*/
/*                             {% endif %}*/
/* */
/*                             {% if trace.root is defined %}*/
/*                                 <strong class="newline">{{ trace.class }}</strong>*/
/*                                 <pre>*/
/*                                     {{- trace.root -}}*/
/*                                     {%- if trace.path is not empty -%}*/
/*                                         {%- if trace.path|first != '[' %}.{% endif -%}*/
/*                                         {{- trace.path -}}*/
/*                                     {%- endif %} = {{ trace.value -}}*/
/*                                 </pre>*/
/*                             {% elseif trace.message is defined %}*/
/*                                 <strong class="newline">{{ trace.class }}</strong>*/
/*                                 <pre>{{ trace.message }}</pre>*/
/*                             {% else %}*/
/*                                 <pre>{{ trace }}</pre>*/
/*                             {% endif %}*/
/*                         {% else %}*/
/*                             <em>Unknown.</em>*/
/*                         {% endfor %}*/
/*                     </td>*/
/*                 </tr>*/
/*                 {% endfor %}*/
/*                 </tbody>*/
/*             </table>*/
/*         </div>*/
/*         {% endif %}*/
/* */
/*         {% if data.default_data is defined %}*/
/*         <h3>*/
/*             <a class="toggle-button" data-toggle-target-id="{{ data.id }}-default_data" href="#">*/
/*                 Default Data <span class="toggle-icon"></span>*/
/*             </a>*/
/*         </h3>*/
/* */
/*         <div id="{{ data.id }}-default_data">*/
/*             <table>*/
/*                 <thead>*/
/*                     <tr>*/
/*                         <th width="180">Property</th>*/
/*                         <th>Value</th>*/
/*                     </tr>*/
/*                 </thead>*/
/*                 <tbody>*/
/*                     <tr>*/
/*                         <th class="font-normal" scope="row">Model Format</th>*/
/*                         <td>*/
/*                             {% if data.default_data.model is defined %}*/
/*                                 {{ data.default_data.model }}*/
/*                             {% else %}*/
/*                                 <em class="font-normal text-muted">same as normalized format</em>*/
/*                             {% endif %}*/
/*                         </td>*/
/*                     </tr>*/
/*                     <tr>*/
/*                         <th class="font-normal" scope="row">Normalized Format</th>*/
/*                         <td>{{ data.default_data.norm }}</td>*/
/*                     </tr>*/
/*                     <tr>*/
/*                         <th class="font-normal" scope="row">View Format</th>*/
/*                         <td>*/
/*                             {% if data.default_data.view is defined %}*/
/*                                 {{ data.default_data.view }}*/
/*                             {% else %}*/
/*                                 <em class="font-normal text-muted">same as normalized format</em>*/
/*                             {% endif %}*/
/*                         </td>*/
/*                     </tr>*/
/*                 </tbody>*/
/*             </table>*/
/*         </div>*/
/*         {% endif %}*/
/* */
/*         {% if data.submitted_data is defined %}*/
/*         <h3>*/
/*             <a class="toggle-button" data-toggle-target-id="{{ data.id }}-submitted_data" href="#">*/
/*                 Submitted Data <span class="toggle-icon"></span>*/
/*             </a>*/
/*         </h3>*/
/* */
/*         <div id="{{ data.id }}-submitted_data">*/
/*         {% if data.submitted_data.norm is defined %}*/
/*             <table>*/
/*                 <thead>*/
/*                     <tr>*/
/*                         <th width="180">Property</th>*/
/*                         <th>Value</th>*/
/*                     </tr>*/
/*                 </thead>*/
/*                 <tbody>*/
/*                     <tr>*/
/*                         <th class="font-normal" scope="row">View Format</th>*/
/*                         <td>*/
/*                             {% if data.submitted_data.view is defined %}*/
/*                                 {{ data.submitted_data.view }}*/
/*                             {% else %}*/
/*                                 <em class="font-normal text-muted">same as normalized format</em>*/
/*                             {% endif %}*/
/*                         </td>*/
/*                     </tr>*/
/*                     <tr>*/
/*                         <th class="font-normal" scope="row">Normalized Format</th>*/
/*                         <td>{{ data.submitted_data.norm }}</td>*/
/*                     </tr>*/
/*                     <tr>*/
/*                         <th class="font-normal" scope="row">Model Format</th>*/
/*                         <td>*/
/*                             {% if data.submitted_data.model is defined %}*/
/*                                 {{ data.submitted_data.model }}*/
/*                             {% else %}*/
/*                                 <em class="font-normal text-muted">same as normalized format</em>*/
/*                             {% endif %}*/
/*                         </td>*/
/*                     </tr>*/
/*                 </tbody>*/
/*             </table>*/
/*         {% else %}*/
/*             <div class="empty">*/
/*                 <p>This form was not submitted.</p>*/
/*             </div>*/
/*         {% endif %}*/
/*         </div>*/
/*         {% endif %}*/
/* */
/*         {% if data.passed_options is defined %}*/
/*         <h3>*/
/*             <a class="toggle-button" data-toggle-target-id="{{ data.id }}-passed_options" href="#">*/
/*                 Passed Options <span class="toggle-icon"></span>*/
/*             </a>*/
/*         </h3>*/
/* */
/*         <div id="{{ data.id }}-passed_options">*/
/*             {% if data.passed_options|length %}*/
/*             <table>*/
/*                 <thead>*/
/*                     <tr>*/
/*                         <th width="180">Option</th>*/
/*                         <th>Passed Value</th>*/
/*                         <th>Resolved Value</th>*/
/*                     </tr>*/
/*                 </thead>*/
/*                 <tbody>*/
/*                 {% for option, value in data.passed_options %}*/
/*                 <tr>*/
/*                     <th>{{ option }}</th>*/
/*                     <td>{{ value }}</td>*/
/*                     <td>*/
/*                         {% if data.resolved_options[option] is same as(value) %}*/
/*                             <em class="font-normal text-muted">same as passed value</em>*/
/*                         {% else %}*/
/*                             {{ data.resolved_options[option] }}*/
/*                         {% endif %}*/
/*                     </td>*/
/*                 </tr>*/
/*                 {% endfor %}*/
/*                 </tbody>*/
/*             </table>*/
/*             {% else %}*/
/*                 <div class="empty">*/
/*                     <p>No options where passed when constructing this form.</p>*/
/*                 </div>*/
/*             {% endif %}*/
/*         </div>*/
/*         {% endif %}*/
/* */
/*         {% if data.resolved_options is defined %}*/
/*         <h3>*/
/*             <a class="toggle-button" data-toggle-target-id="{{ data.id }}-resolved_options" href="#">*/
/*                 Resolved Options <span class="toggle-icon"></span>*/
/*             </a>*/
/*         </h3>*/
/* */
/*         <div id="{{ data.id }}-resolved_options" class="hidden">*/
/*             <table>*/
/*                 <thead>*/
/*                     <tr>*/
/*                         <th width="180">Option</th>*/
/*                         <th>Value</th>*/
/*                     </tr>*/
/*                 </thead>*/
/*                 <tbody>*/
/*                 {% for option, value in data.resolved_options %}*/
/*                 <tr>*/
/*                     <th scope="row">{{ option }}</th>*/
/*                     <td>{{ value }}</td>*/
/*                 </tr>*/
/*                 {% endfor %}*/
/*                 </tbody>*/
/*             </table>*/
/*         </div>*/
/*         {% endif %}*/
/* */
/*         {% if data.view_vars is defined %}*/
/*         <h3>*/
/*             <a class="toggle-button" data-toggle-target-id="{{ data.id }}-view_vars" href="#">*/
/*                 View Variables <span class="toggle-icon"></span>*/
/*             </a>*/
/*         </h3>*/
/* */
/*         <div id="{{ data.id }}-view_vars" class="hidden">*/
/*             <table>*/
/*                 <thead>*/
/*                     <tr>*/
/*                         <th width="180">Variable</th>*/
/*                         <th>Value</th>*/
/*                     </tr>*/
/*                 </thead>*/
/*                 <tbody>*/
/*                 {% for variable, value in data.view_vars %}*/
/*                 <tr>*/
/*                     <th scope="row">{{ variable }}</th>*/
/*                     <td>{{ value }}</td>*/
/*                 </tr>*/
/*                 {% endfor %}*/
/*                 </tbody>*/
/*             </table>*/
/*         </div>*/
/*         {% endif %}*/
/*     </div>*/
/* */
/*     {% for childName, childData in data.children %}*/
/*         {{ tree.form_tree_details(childName, childData, forms_by_hash) }}*/
/*     {% endfor %}*/
/* {% endmacro %}*/
/* */
