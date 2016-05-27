<?php

/* @WebProfiler/Collector/router.html.twig */
class __TwigTemplate_54ff80cbee6d40f807a4747b6d738acd180c236d56e561e8194fdc4035e5ff6a extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/router.html.twig", 1);
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
        $__internal_3afa00781f35abe7a9de7d9ce4852392cdaa30ac3bd8851f741045f83b32286d = $this->env->getExtension("native_profiler");
        $__internal_3afa00781f35abe7a9de7d9ce4852392cdaa30ac3bd8851f741045f83b32286d->enter($__internal_3afa00781f35abe7a9de7d9ce4852392cdaa30ac3bd8851f741045f83b32286d_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/router.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_3afa00781f35abe7a9de7d9ce4852392cdaa30ac3bd8851f741045f83b32286d->leave($__internal_3afa00781f35abe7a9de7d9ce4852392cdaa30ac3bd8851f741045f83b32286d_prof);

    }

    // line 3
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_cc8b226fe53d5c66bbaf8961f70152a64b65dafa13e7341dc18f7c750703ffa8 = $this->env->getExtension("native_profiler");
        $__internal_cc8b226fe53d5c66bbaf8961f70152a64b65dafa13e7341dc18f7c750703ffa8->enter($__internal_cc8b226fe53d5c66bbaf8961f70152a64b65dafa13e7341dc18f7c750703ffa8_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        
        $__internal_cc8b226fe53d5c66bbaf8961f70152a64b65dafa13e7341dc18f7c750703ffa8->leave($__internal_cc8b226fe53d5c66bbaf8961f70152a64b65dafa13e7341dc18f7c750703ffa8_prof);

    }

    // line 5
    public function block_menu($context, array $blocks = array())
    {
        $__internal_b3beccfa075fc799833d9d1f0ee2465f723e6eb7d0aa0ccd1651ba1e51564c78 = $this->env->getExtension("native_profiler");
        $__internal_b3beccfa075fc799833d9d1f0ee2465f723e6eb7d0aa0ccd1651ba1e51564c78->enter($__internal_b3beccfa075fc799833d9d1f0ee2465f723e6eb7d0aa0ccd1651ba1e51564c78_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "menu"));

        // line 6
        echo "<span class=\"label\">
    <span class=\"icon\">";
        // line 7
        echo twig_include($this->env, $context, "@WebProfiler/Icon/router.svg");
        echo "</span>
    <strong>Routing</strong>
</span>
";
        
        $__internal_b3beccfa075fc799833d9d1f0ee2465f723e6eb7d0aa0ccd1651ba1e51564c78->leave($__internal_b3beccfa075fc799833d9d1f0ee2465f723e6eb7d0aa0ccd1651ba1e51564c78_prof);

    }

    // line 12
    public function block_panel($context, array $blocks = array())
    {
        $__internal_74d90f313f9175de49dc87838bce645051d8387d0a2015e082e68ffd57ba1528 = $this->env->getExtension("native_profiler");
        $__internal_74d90f313f9175de49dc87838bce645051d8387d0a2015e082e68ffd57ba1528->enter($__internal_74d90f313f9175de49dc87838bce645051d8387d0a2015e082e68ffd57ba1528_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "panel"));

        // line 13
        echo "    ";
        echo $this->env->getExtension('http_kernel')->renderFragment($this->env->getExtension('routing')->getPath("_profiler_router", array("token" => (isset($context["token"]) ? $context["token"] : $this->getContext($context, "token")))));
        echo "
";
        
        $__internal_74d90f313f9175de49dc87838bce645051d8387d0a2015e082e68ffd57ba1528->leave($__internal_74d90f313f9175de49dc87838bce645051d8387d0a2015e082e68ffd57ba1528_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/router.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  73 => 13,  67 => 12,  56 => 7,  53 => 6,  47 => 5,  36 => 3,  11 => 1,);
    }
}
/* {% extends '@WebProfiler/Profiler/layout.html.twig' %}*/
/* */
/* {% block toolbar %}{% endblock %}*/
/* */
/* {% block menu %}*/
/* <span class="label">*/
/*     <span class="icon">{{ include('@WebProfiler/Icon/router.svg') }}</span>*/
/*     <strong>Routing</strong>*/
/* </span>*/
/* {% endblock %}*/
/* */
/* {% block panel %}*/
/*     {{ render(path('_profiler_router', { token: token })) }}*/
/* {% endblock %}*/
/* */
