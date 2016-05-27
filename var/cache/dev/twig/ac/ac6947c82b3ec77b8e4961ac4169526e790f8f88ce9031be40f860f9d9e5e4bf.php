<?php

/* editCounter/index.html.twig */
class __TwigTemplate_4767dbcff3569efe26e1e15b30ca0c20c266840f09f810482096de1e69e8184f extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("base.html.twig", "editCounter/index.html.twig", 1);
        $this->blocks = array(
            'title' => array($this, 'block_title'),
            'body' => array($this, 'block_body'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "base.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_1fc0395550bc3d925e8a2798901b0762ae56406f6e3181b29bee385b6d0bcb04 = $this->env->getExtension("native_profiler");
        $__internal_1fc0395550bc3d925e8a2798901b0762ae56406f6e3181b29bee385b6d0bcb04->enter($__internal_1fc0395550bc3d925e8a2798901b0762ae56406f6e3181b29bee385b6d0bcb04_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "editCounter/index.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_1fc0395550bc3d925e8a2798901b0762ae56406f6e3181b29bee385b6d0bcb04->leave($__internal_1fc0395550bc3d925e8a2798901b0762ae56406f6e3181b29bee385b6d0bcb04_prof);

    }

    // line 3
    public function block_title($context, array $blocks = array())
    {
        $__internal_9f1f1cf6bcc72ec2084060d78fc464a2b0665d76ada96e0f16f93d3429799b54 = $this->env->getExtension("native_profiler");
        $__internal_9f1f1cf6bcc72ec2084060d78fc464a2b0665d76ada96e0f16f93d3429799b54->enter($__internal_9f1f1cf6bcc72ec2084060d78fc464a2b0665d76ada96e0f16f93d3429799b54_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        echo "Welcome to x!'s tools!";
        
        $__internal_9f1f1cf6bcc72ec2084060d78fc464a2b0665d76ada96e0f16f93d3429799b54->leave($__internal_9f1f1cf6bcc72ec2084060d78fc464a2b0665d76ada96e0f16f93d3429799b54_prof);

    }

    // line 5
    public function block_body($context, array $blocks = array())
    {
        $__internal_e552d02a97c4c88307d159b5dc8ee58e139edfc16dde69d74fbaf87fd2fe1ea0 = $this->env->getExtension("native_profiler");
        $__internal_e552d02a97c4c88307d159b5dc8ee58e139edfc16dde69d74fbaf87fd2fe1ea0->enter($__internal_e552d02a97c4c88307d159b5dc8ee58e139edfc16dde69d74fbaf87fd2fe1ea0_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 6
        echo "    <h3 style=\"width:80%; margin-bottom: 0.4em; margin-left:auto; margin-right:auto\">Edit Counter<small style=\"color:inherit\"> &nbsp;&bull;&nbsp; Analysis of user contributions</small></h3>
    <form class=\"form-horizontal\" style=\"width:80%; margin:0 auto\" action=\"/ec/get\" method=\"get\" accept-charset=\"utf-8\" >
        <fieldset>
            <legend></legend>

            <div class=\"input-group\">
                <span class=\"input-group-addon form-label\">Username</span>
                <input type=\"text\" class=\"form-control\" value=\"\" name=\"user\">
\t\t\t<span class=\"input-group-addon glyphicon glyphicon-info-sign tooltipcss\"  >
\t\t\t\t<span>
        \t\t\t<img class=\"callout\" src=\"../static/images/callout.png\" />
        \t\t\tUsername or IPv4 or IPv6
    \t\t\t</span>
\t\t\t</span>
            </div>

            <div class=\"input-group\">
                <span class=\"input-group-addon form-label\">Wiki</span>
                <input type=\"text\" class=\"form-control\" ";
        // line 24
        if (array_key_exists("project", $context)) {
            echo "value=\"";
            echo twig_escape_filter($this->env, (isset($context["project"]) ? $context["project"] : $this->getContext($context, "project")), "html", null, true);
            echo "\" ";
        }
        echo "name=\"project\">
\t\t\t<span class=\"input-group-addon glyphicon glyphicon-info-sign tooltipcss\"  >
\t\t\t\t<span>
        \t\t\t<img class=\"callout\" src=\"../static/images/callout.png\" />
        \t\t\t<strong>Accepted formats :</strong><br />
        \t\t\tenwiki or en.wikipedia or <br />https://de.wikipedia.org ...
    \t\t\t</span>
\t\t\t</span>
            </div>

            <br />
            <input class=\"btn btn-large btn-primary\" type=\"submit\" value=\"Submit\" />
        </fieldset>
    </form>
    </div><br />
";
        
        $__internal_e552d02a97c4c88307d159b5dc8ee58e139edfc16dde69d74fbaf87fd2fe1ea0->leave($__internal_e552d02a97c4c88307d159b5dc8ee58e139edfc16dde69d74fbaf87fd2fe1ea0_prof);

    }

    public function getTemplateName()
    {
        return "editCounter/index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  73 => 24,  53 => 6,  47 => 5,  35 => 3,  11 => 1,);
    }
}
/* {% extends 'base.html.twig' %}*/
/* */
/* {% block title %}Welcome to x!'s tools!{% endblock %}*/
/* */
/* {% block body %}*/
/*     <h3 style="width:80%; margin-bottom: 0.4em; margin-left:auto; margin-right:auto">Edit Counter<small style="color:inherit"> &nbsp;&bull;&nbsp; Analysis of user contributions</small></h3>*/
/*     <form class="form-horizontal" style="width:80%; margin:0 auto" action="/ec/get" method="get" accept-charset="utf-8" >*/
/*         <fieldset>*/
/*             <legend></legend>*/
/* */
/*             <div class="input-group">*/
/*                 <span class="input-group-addon form-label">Username</span>*/
/*                 <input type="text" class="form-control" value="" name="user">*/
/* 			<span class="input-group-addon glyphicon glyphicon-info-sign tooltipcss"  >*/
/* 				<span>*/
/*         			<img class="callout" src="../static/images/callout.png" />*/
/*         			Username or IPv4 or IPv6*/
/*     			</span>*/
/* 			</span>*/
/*             </div>*/
/* */
/*             <div class="input-group">*/
/*                 <span class="input-group-addon form-label">Wiki</span>*/
/*                 <input type="text" class="form-control" {% if project is defined %}value="{{ project }}" {% endif %}name="project">*/
/* 			<span class="input-group-addon glyphicon glyphicon-info-sign tooltipcss"  >*/
/* 				<span>*/
/*         			<img class="callout" src="../static/images/callout.png" />*/
/*         			<strong>Accepted formats :</strong><br />*/
/*         			enwiki or en.wikipedia or <br />https://de.wikipedia.org ...*/
/*     			</span>*/
/* 			</span>*/
/*             </div>*/
/* */
/*             <br />*/
/*             <input class="btn btn-large btn-primary" type="submit" value="Submit" />*/
/*         </fieldset>*/
/*     </form>*/
/*     </div><br />*/
/* {% endblock %}*/
/* */
