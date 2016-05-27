<?php

/* editCounter/result.html.twig */
class __TwigTemplate_d78927b91e67013769a3e9c413c9f0e344966d106b452463bf832071a0a940cf extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("base.html.twig", "editCounter/result.html.twig", 1);
        $this->blocks = array(
            'body' => array($this, 'block_body'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "base.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_c35b8f8d854e9ef3b18dc3cfa92c46a13d3bbf908f5bf122c5a0d0e90637307c = $this->env->getExtension("native_profiler");
        $__internal_c35b8f8d854e9ef3b18dc3cfa92c46a13d3bbf908f5bf122c5a0d0e90637307c->enter($__internal_c35b8f8d854e9ef3b18dc3cfa92c46a13d3bbf908f5bf122c5a0d0e90637307c_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "editCounter/result.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_c35b8f8d854e9ef3b18dc3cfa92c46a13d3bbf908f5bf122c5a0d0e90637307c->leave($__internal_c35b8f8d854e9ef3b18dc3cfa92c46a13d3bbf908f5bf122c5a0d0e90637307c_prof);

    }

    // line 3
    public function block_body($context, array $blocks = array())
    {
        $__internal_da369187005d6d6b3cb0f32cdc000e33a480ea2fb7fe36aa131498e0ff4d509b = $this->env->getExtension("native_profiler");
        $__internal_da369187005d6d6b3cb0f32cdc000e33a480ea2fb7fe36aa131498e0ff4d509b->enter($__internal_da369187005d6d6b3cb0f32cdc000e33a480ea2fb7fe36aa131498e0ff4d509b_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 4
        echo "    <div id=\"wrapper\">
        <div id=\"container\">
            <div id=\"welcome\">
                Edit Counter!
            </div>

        </div>
    </div>
    ";
        // line 12
        if (array_key_exists("project", $context)) {
            echo twig_escape_filter($this->env, (isset($context["project"]) ? $context["project"] : $this->getContext($context, "project")), "html", null, true);
        }
        // line 13
        echo "    ";
        if (array_key_exists("username", $context)) {
            echo twig_escape_filter($this->env, (isset($context["username"]) ? $context["username"] : $this->getContext($context, "username")), "html", null, true);
        }
        // line 14
        echo "    ";
        if (array_key_exists("wiki", $context)) {
            echo twig_escape_filter($this->env, (isset($context["wiki"]) ? $context["wiki"] : $this->getContext($context, "wiki")), "html", null, true);
        }
        // line 15
        echo "    ";
        if (array_key_exists("dbName", $context)) {
            echo twig_escape_filter($this->env, (isset($context["dbName"]) ? $context["dbName"] : $this->getContext($context, "dbName")), "html", null, true);
        }
        // line 16
        echo "    ";
        if (array_key_exists("lang", $context)) {
            echo twig_escape_filter($this->env, (isset($context["lang"]) ? $context["lang"] : $this->getContext($context, "lang")), "html", null, true);
        }
        // line 17
        echo "    ";
        if (array_key_exists("name", $context)) {
            echo twig_escape_filter($this->env, (isset($context["name"]) ? $context["name"] : $this->getContext($context, "name")), "html", null, true);
        }
        // line 18
        echo "    ";
        if (array_key_exists("family", $context)) {
            echo twig_escape_filter($this->env, (isset($context["family"]) ? $context["family"] : $this->getContext($context, "family")), "html", null, true);
        }
        // line 19
        echo "    ";
        if (array_key_exists("url", $context)) {
            echo twig_escape_filter($this->env, (isset($context["url"]) ? $context["url"] : $this->getContext($context, "url")), "html", null, true);
        }
        // line 20
        echo "    ";
        if (array_key_exists("users", $context)) {
            echo $this->env->getExtension('dump')->dump($this->env, $context, (isset($context["users"]) ? $context["users"] : $this->getContext($context, "users")));
        }
        
        $__internal_da369187005d6d6b3cb0f32cdc000e33a480ea2fb7fe36aa131498e0ff4d509b->leave($__internal_da369187005d6d6b3cb0f32cdc000e33a480ea2fb7fe36aa131498e0ff4d509b_prof);

    }

    public function getTemplateName()
    {
        return "editCounter/result.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  89 => 20,  84 => 19,  79 => 18,  74 => 17,  69 => 16,  64 => 15,  59 => 14,  54 => 13,  50 => 12,  40 => 4,  34 => 3,  11 => 1,);
    }
}
/* {% extends 'base.html.twig' %}*/
/* */
/* {% block body %}*/
/*     <div id="wrapper">*/
/*         <div id="container">*/
/*             <div id="welcome">*/
/*                 Edit Counter!*/
/*             </div>*/
/* */
/*         </div>*/
/*     </div>*/
/*     {% if project is defined %}{{ project }}{% endif %}*/
/*     {% if username is defined %}{{ username }}{% endif %}*/
/*     {% if wiki is defined %}{{ wiki }}{% endif %}*/
/*     {% if dbName is defined %}{{ dbName }}{% endif %}*/
/*     {% if lang is defined %}{{ lang }}{% endif %}*/
/*     {% if name is defined %}{{ name }}{% endif %}*/
/*     {% if family is defined %}{{ family }}{% endif %}*/
/*     {% if url is defined %}{{ url }}{% endif %}*/
/*     {% if users is defined %}{{ dump(users) }}{% endif %}*/
/* {% endblock %}*/
/* */
