<?php

/* @WebProfiler/Profiler/toolbar.html.twig */
class __TwigTemplate_3c301daa39a1c6be6781710452d438f0420b529dba92b738ac2296195d37a337 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = array(
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_33687361d855dd0ec146406d24c8f4c9f767790513d4a169bf86185dd04dd01a = $this->env->getExtension("native_profiler");
        $__internal_33687361d855dd0ec146406d24c8f4c9f767790513d4a169bf86185dd04dd01a->enter($__internal_33687361d855dd0ec146406d24c8f4c9f767790513d4a169bf86185dd04dd01a_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Profiler/toolbar.html.twig"));

        // line 1
        echo "<!-- START of Symfony Web Debug Toolbar -->
";
        // line 2
        if (("normal" != (isset($context["position"]) ? $context["position"] : $this->getContext($context, "position")))) {
            // line 3
            echo "    <div id=\"sfMiniToolbar-";
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "\" class=\"sf-minitoolbar\" data-no-turbolink>
        <a href=\"javascript:void(0);\" title=\"Show Symfony toolbar\" tabindex=\"-1\" accesskey=\"D\" onclick=\"
            var elem = this.parentNode;
            if (elem.style.display == 'none') {
                document.getElementById('sfToolbarMainContent-";
            // line 7
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "').style.display = 'none';
                document.getElementById('sfToolbarClearer-";
            // line 8
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "').style.display = 'none';
                elem.style.display = 'block';
            } else {
                document.getElementById('sfToolbarMainContent-";
            // line 11
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "').style.display = 'block';
                document.getElementById('sfToolbarClearer-";
            // line 12
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "').style.display = 'block';
                elem.style.display = 'none'
            }

            Sfjs.setPreference('toolbar/displayState', 'block');
        \">
            ";
            // line 18
            echo twig_include($this->env, $context, "@WebProfiler/Icon/symfony.svg");
            echo "
        </a>
    </div>
    <style>
        ";
            // line 22
            echo twig_include($this->env, $context, "@WebProfiler/Profiler/toolbar.css.twig", array("position" => (isset($context["position"]) ? $context["position"] : $this->getContext($context, "position")), "floatable" => true));
            echo "
    </style>
    <div id=\"sfToolbarClearer-";
            // line 24
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "\" style=\"clear: both; height: 36px;\"></div>
";
        }
        // line 26
        echo "
<div id=\"sfToolbarMainContent-";
        // line 27
        echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
        echo "\" class=\"sf-toolbarreset clear-fix\" data-no-turbolink>
    ";
        // line 28
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["templates"]) ? $context["templates"] : $this->getContext($context, "templates")));
        foreach ($context['_seq'] as $context["name"] => $context["template"]) {
            // line 29
            echo "        ";
            echo twig_escape_filter($this->env, $this->getAttribute($context["template"], "renderblock", array(0 => "toolbar", 1 => array("collector" => $this->getAttribute(            // line 30
(isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "getcollector", array(0 => $context["name"]), "method"), "profiler_url" =>             // line 31
(isset($context["profiler_url"]) ? $context["profiler_url"] : $this->getContext($context, "profiler_url")), "token" => $this->getAttribute(            // line 32
(isset($context["profile"]) ? $context["profile"] : $this->getContext($context, "profile")), "token", array()), "name" =>             // line 33
$context["name"], "profiler_markup_version" =>             // line 34
(isset($context["profiler_markup_version"]) ? $context["profiler_markup_version"] : $this->getContext($context, "profiler_markup_version")))), "method"), "html", null, true);
            // line 36
            echo "
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['name'], $context['template'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 38
        echo "
    ";
        // line 39
        if (("normal" != (isset($context["position"]) ? $context["position"] : $this->getContext($context, "position")))) {
            // line 40
            echo "        <a class=\"hide-button\" title=\"Close Toolbar\" tabindex=\"-1\" accesskey=\"D\" onclick=\"
            var p = this.parentNode;
            p.style.display = 'none';
            (p.previousElementSibling || p.previousSibling).style.display = 'none';
            document.getElementById('sfMiniToolbar-";
            // line 44
            echo twig_escape_filter($this->env, (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")), "html", null, true);
            echo "').style.display = 'block';
            Sfjs.setPreference('toolbar/displayState', 'none');
        \">
            ";
            // line 47
            echo twig_include($this->env, $context, "@WebProfiler/Icon/close.svg");
            echo "
        </a>
    ";
        }
        // line 50
        echo "</div>
<!-- END of Symfony Web Debug Toolbar -->
";
        
        $__internal_33687361d855dd0ec146406d24c8f4c9f767790513d4a169bf86185dd04dd01a->leave($__internal_33687361d855dd0ec146406d24c8f4c9f767790513d4a169bf86185dd04dd01a_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Profiler/toolbar.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  124 => 50,  118 => 47,  112 => 44,  106 => 40,  104 => 39,  101 => 38,  94 => 36,  92 => 34,  91 => 33,  90 => 32,  89 => 31,  88 => 30,  86 => 29,  82 => 28,  78 => 27,  75 => 26,  70 => 24,  65 => 22,  58 => 18,  49 => 12,  45 => 11,  39 => 8,  35 => 7,  27 => 3,  25 => 2,  22 => 1,);
    }
}
/* <!-- START of Symfony Web Debug Toolbar -->*/
/* {% if 'normal' != position %}*/
/*     <div id="sfMiniToolbar-{{ token }}" class="sf-minitoolbar" data-no-turbolink>*/
/*         <a href="javascript:void(0);" title="Show Symfony toolbar" tabindex="-1" accesskey="D" onclick="*/
/*             var elem = this.parentNode;*/
/*             if (elem.style.display == 'none') {*/
/*                 document.getElementById('sfToolbarMainContent-{{ token }}').style.display = 'none';*/
/*                 document.getElementById('sfToolbarClearer-{{ token }}').style.display = 'none';*/
/*                 elem.style.display = 'block';*/
/*             } else {*/
/*                 document.getElementById('sfToolbarMainContent-{{ token }}').style.display = 'block';*/
/*                 document.getElementById('sfToolbarClearer-{{ token }}').style.display = 'block';*/
/*                 elem.style.display = 'none'*/
/*             }*/
/* */
/*             Sfjs.setPreference('toolbar/displayState', 'block');*/
/*         ">*/
/*             {{ include('@WebProfiler/Icon/symfony.svg') }}*/
/*         </a>*/
/*     </div>*/
/*     <style>*/
/*         {{ include('@WebProfiler/Profiler/toolbar.css.twig', { 'position': position, 'floatable': true }) }}*/
/*     </style>*/
/*     <div id="sfToolbarClearer-{{ token }}" style="clear: both; height: 36px;"></div>*/
/* {% endif %}*/
/* */
/* <div id="sfToolbarMainContent-{{ token }}" class="sf-toolbarreset clear-fix" data-no-turbolink>*/
/*     {% for name, template in templates %}*/
/*         {{ template.renderblock('toolbar', {*/
/*             'collector': profile.getcollector(name),*/
/*             'profiler_url': profiler_url,*/
/*             'token': profile.token,*/
/*             'name': name,*/
/*             'profiler_markup_version': profiler_markup_version*/
/*           })*/
/*         }}*/
/*     {% endfor %}*/
/* */
/*     {% if 'normal' != position %}*/
/*         <a class="hide-button" title="Close Toolbar" tabindex="-1" accesskey="D" onclick="*/
/*             var p = this.parentNode;*/
/*             p.style.display = 'none';*/
/*             (p.previousElementSibling || p.previousSibling).style.display = 'none';*/
/*             document.getElementById('sfMiniToolbar-{{ token }}').style.display = 'block';*/
/*             Sfjs.setPreference('toolbar/displayState', 'none');*/
/*         ">*/
/*             {{ include('@WebProfiler/Icon/close.svg') }}*/
/*         </a>*/
/*     {% endif %}*/
/* </div>*/
/* <!-- END of Symfony Web Debug Toolbar -->*/
/* */
