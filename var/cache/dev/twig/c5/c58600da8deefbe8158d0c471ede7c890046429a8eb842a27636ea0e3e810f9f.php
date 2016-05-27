<?php

/* @Twig/Exception/exception_full.html.twig */
class __TwigTemplate_f384ec9aa727ffe03ae9102c9e4b2cfa337f8c852e318fa076a66ec24d925c76 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@Twig/layout.html.twig", "@Twig/Exception/exception_full.html.twig", 1);
        $this->blocks = array(
            'head' => array($this, 'block_head'),
            'title' => array($this, 'block_title'),
            'body' => array($this, 'block_body'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "@Twig/layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_58a1dbb88b7f36b72e319947d6d4a5c3a441da2f0098732afc38088e7006ca59 = $this->env->getExtension("native_profiler");
        $__internal_58a1dbb88b7f36b72e319947d6d4a5c3a441da2f0098732afc38088e7006ca59->enter($__internal_58a1dbb88b7f36b72e319947d6d4a5c3a441da2f0098732afc38088e7006ca59_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@Twig/Exception/exception_full.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_58a1dbb88b7f36b72e319947d6d4a5c3a441da2f0098732afc38088e7006ca59->leave($__internal_58a1dbb88b7f36b72e319947d6d4a5c3a441da2f0098732afc38088e7006ca59_prof);

    }

    // line 3
    public function block_head($context, array $blocks = array())
    {
        $__internal_1d1f2e60a55560a542c7e6ac5ed0398c598fe1920ece08521896807b72c2ff8b = $this->env->getExtension("native_profiler");
        $__internal_1d1f2e60a55560a542c7e6ac5ed0398c598fe1920ece08521896807b72c2ff8b->enter($__internal_1d1f2e60a55560a542c7e6ac5ed0398c598fe1920ece08521896807b72c2ff8b_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 4
        echo "    <link href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('request')->generateAbsoluteUrl($this->env->getExtension('asset')->getAssetUrl("bundles/framework/css/exception.css")), "html", null, true);
        echo "\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />
";
        
        $__internal_1d1f2e60a55560a542c7e6ac5ed0398c598fe1920ece08521896807b72c2ff8b->leave($__internal_1d1f2e60a55560a542c7e6ac5ed0398c598fe1920ece08521896807b72c2ff8b_prof);

    }

    // line 7
    public function block_title($context, array $blocks = array())
    {
        $__internal_52b41621e39584c1fc3bb4729bf7a3ec2cbb9fc452cd3141619ed6ac1eda3a4a = $this->env->getExtension("native_profiler");
        $__internal_52b41621e39584c1fc3bb4729bf7a3ec2cbb9fc452cd3141619ed6ac1eda3a4a->enter($__internal_52b41621e39584c1fc3bb4729bf7a3ec2cbb9fc452cd3141619ed6ac1eda3a4a_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        // line 8
        echo "    ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "message", array()), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, (isset($context["status_code"]) ? $context["status_code"] : $this->getContext($context, "status_code")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, (isset($context["status_text"]) ? $context["status_text"] : $this->getContext($context, "status_text")), "html", null, true);
        echo ")
";
        
        $__internal_52b41621e39584c1fc3bb4729bf7a3ec2cbb9fc452cd3141619ed6ac1eda3a4a->leave($__internal_52b41621e39584c1fc3bb4729bf7a3ec2cbb9fc452cd3141619ed6ac1eda3a4a_prof);

    }

    // line 11
    public function block_body($context, array $blocks = array())
    {
        $__internal_ded64ec67b04708388c763dfa8da7c0ae6ebd1d500d16ad2934673bd7b04915f = $this->env->getExtension("native_profiler");
        $__internal_ded64ec67b04708388c763dfa8da7c0ae6ebd1d500d16ad2934673bd7b04915f->enter($__internal_ded64ec67b04708388c763dfa8da7c0ae6ebd1d500d16ad2934673bd7b04915f_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 12
        echo "    ";
        $this->loadTemplate("@Twig/Exception/exception.html.twig", "@Twig/Exception/exception_full.html.twig", 12)->display($context);
        
        $__internal_ded64ec67b04708388c763dfa8da7c0ae6ebd1d500d16ad2934673bd7b04915f->leave($__internal_ded64ec67b04708388c763dfa8da7c0ae6ebd1d500d16ad2934673bd7b04915f_prof);

    }

    public function getTemplateName()
    {
        return "@Twig/Exception/exception_full.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  78 => 12,  72 => 11,  58 => 8,  52 => 7,  42 => 4,  36 => 3,  11 => 1,);
    }
}
/* {% extends '@Twig/layout.html.twig' %}*/
/* */
/* {% block head %}*/
/*     <link href="{{ absolute_url(asset('bundles/framework/css/exception.css')) }}" rel="stylesheet" type="text/css" media="all" />*/
/* {% endblock %}*/
/* */
/* {% block title %}*/
/*     {{ exception.message }} ({{ status_code }} {{ status_text }})*/
/* {% endblock %}*/
/* */
/* {% block body %}*/
/*     {% include '@Twig/Exception/exception.html.twig' %}*/
/* {% endblock %}*/
/* */
